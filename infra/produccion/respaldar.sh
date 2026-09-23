#!/usr/bin/env bash
# =====================================================================
#  Respaldo del Sistema Consultorio Dental
#
#  Guarda dos cosas por separado, y esa separacion es el punto:
#
#    BASE DE DATOS  - pacientes, consultas, cobros, agenda.
#                     Pesa megabytes. Se respalda TODAS LAS NOCHES y
#                     se guardan 14 dias.
#
#    ARCHIVOS       - radiografias, fotos, escaneos (MinIO).
#                     Pesan gigabytes. Se respaldan UNA VEZ POR SEMANA
#                     y se guardan 4 copias.
#
#  ¿Por que no los dos diario? Porque no caben. Con mil pacientes los
#  archivos rondan los 30 GB; catorce copias serian 420 GB en un disco
#  de 100. El disco se llenaria y MySQL se caeria, que es peor que no
#  tener respaldo.
#
#  ¿Y no es riesgoso respaldar los archivos solo cada semana? Menos de
#  lo que parece. Un escaneo no se modifica una vez subido, y si se
#  pierde una semana de escaneos, el papel original sigue en el
#  consultorio y se vuelve a escanear. Un renglon de cobro perdido, en
#  cambio, no se recupera de ningun lado. Por eso la base va diario.
#
#  Uso:
#      ./respaldar.sh              base; archivos si toca (domingo)
#      ./respaldar.sh --completo   fuerza base y archivos
#      ./respaldar.sh --solo-base  solo la base
# =====================================================================

set -euo pipefail

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ="$(cd "$AQUI/../.." && pwd)"

DESTINO="${CARPETA_RESPALDOS:-/var/respaldos/consultorio}"
DIAS_BASE="${DIAS_RESPALDO_BASE:-14}"
COPIAS_ARCHIVOS="${COPIAS_RESPALDO_ARCHIVOS:-4}"
DIA_ARCHIVOS="${DIA_RESPALDO_ARCHIVOS:-7}"   # 7 = domingo

# Si el disco queda por debajo de esto, no se respalda. Un disco lleno
# tumba MySQL, y quedarse sin sistema por hacer un respaldo es el peor
# intercambio posible.
MINIMO_LIBRE_GB="${MINIMO_LIBRE_GB:-10}"

FECHA="$(date '+%Y%m%d-%H%M')"
TRABAJO="$(mktemp -d)"
trap 'rm -rf "$TRABAJO"' EXIT

decir() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

# ---------------------------------------------------------------------
# Que toca hacer hoy
# ---------------------------------------------------------------------
HACER_ARCHIVOS=0
case "${1:-}" in
    --completo)  HACER_ARCHIVOS=1 ;;
    --solo-base) HACER_ARCHIVOS=0 ;;
    *)           [[ $(date '+%u') == "$DIA_ARCHIVOS" ]] && HACER_ARCHIVOS=1 ;;
esac

decir "=== Respaldo $FECHA ==="
decir "    base: si  |  archivos: $( ((HACER_ARCHIVOS)) && echo si || echo 'no (toca el domingo)')"

[[ -f "$AQUI/.env" ]] || { decir "ERROR: falta $AQUI/.env"; exit 1; }
set -a; source "$AQUI/.env"; set +a

mkdir -p "$DESTINO"

# ---------------------------------------------------------------------
# Guardia de espacio
# ---------------------------------------------------------------------
libre_gb() { df -BG --output=avail "$DESTINO" | tail -1 | tr -dc '0-9'; }

LIBRE=$(libre_gb)
decir "Espacio libre: ${LIBRE} GB"

if (( LIBRE < MINIMO_LIBRE_GB )); then
    decir "ERROR: quedan ${LIBRE} GB, menos del minimo de ${MINIMO_LIBRE_GB} GB."
    decir "       No se respalda para no llenar el disco y tumbar MySQL."
    decir "       Borra respaldos viejos o amplia el almacenamiento."
    exit 1
fi

# ---------------------------------------------------------------------
# 1. Base de datos (siempre)
# ---------------------------------------------------------------------
decir "Volcando la base de datos..."

# --single-transaction saca una foto consistente sin bloquear las
# tablas: el consultorio puede seguir trabajando durante el respaldo.
# Sin esto, un cobro a medio guardar podria quedar partido.
if ! docker exec consultorio_db mysqldump \
        --user=root --password="$DB_ROOT_PASS" \
        --single-transaction --quick --routines --triggers --events \
        --default-character-set=utf8mb4 \
        "$DB_NAME" > "$TRABAJO/base.sql" 2>"$TRABAJO/error.txt"; then
    decir "ERROR al volcar la base:"
    cat "$TRABAJO/error.txt"
    exit 1
fi

TAM_SQL=$(stat -c%s "$TRABAJO/base.sql")

# Un volcado de menos de 10 KB significa que algo salio mal: el
# esquema solo ya pesa mas que eso. Mas vale fallar ruidosamente que
# guardar catorce dias de respaldos vacios.
if (( TAM_SQL < 10240 )); then
    decir "ERROR: el volcado pesa $TAM_SQL bytes. Algo salio mal."
    exit 1
fi
decir "  base: $(numfmt --to=iec "$TAM_SQL")"

cp "$AQUI/.env" "$TRABAJO/env-produccion.txt"
[[ -f "$RAIZ/infra/minio/.env" ]] && cp "$RAIZ/infra/minio/.env" "$TRABAJO/env-minio.txt"

{
    echo "fecha=$FECHA"
    echo "tipo=base"
    echo "version=$(grep -oP "APP_VERSION', '\K[^']+" "$RAIZ/config/config.php" 2>/dev/null || echo '?')"
    echo "commit=$(git -C "$RAIZ" rev-parse --short HEAD 2>/dev/null || echo '?')"
    echo "servidor=$(hostname)"
} > "$TRABAJO/acerca-de.txt"

ARCHIVO_BASE="$DESTINO/base-$FECHA.tar.gz"
tar czf "$ARCHIVO_BASE" -C "$TRABAJO" base.sql acerca-de.txt \
    $(cd "$TRABAJO" && ls env-*.txt 2>/dev/null || true)

# ---------------------------------------------------------------------
# 2. Archivos del expediente (solo cuando toca)
# ---------------------------------------------------------------------
ARCHIVO_DOCS=""

if (( HACER_ARCHIVOS )); then
    if docker volume inspect minio_data_compartido >/dev/null 2>&1; then

        # Cuanto pesa, para saber si cabe antes de empezar. Un tar a
        # medias por falta de espacio deja un archivo que parece
        # respaldo y no lo es.
        PESO_MB=$(docker run --rm -v minio_data_compartido:/origen:ro \
            alpine du -sm /origen 2>/dev/null | cut -f1 || echo 0)

        decir "Archivos del expediente: $(numfmt --to=iec $((PESO_MB * 1024 * 1024)))"

        # Se pide el doble: el tar comprimido mas margen para trabajar.
        NECESARIO_GB=$(( (PESO_MB / 1024) * 2 + 1 ))

        if (( LIBRE < NECESARIO_GB + MINIMO_LIBRE_GB )); then
            decir "  AVISO: se necesitan ~${NECESARIO_GB} GB y solo hay ${LIBRE} GB."
            decir "         Se salta el respaldo de archivos. La base SI se respaldo."
            decir "         Baja los respaldos viejos o amplia el almacenamiento."
        else
            decir "Copiando los archivos del expediente..."

            # Se copia el volumen tal cual desde un contenedor de un
            # solo uso. Es mas confiable que usar 'mc mirror': no
            # depende de credenciales ni de que MinIO este respondiendo.
            docker run --rm \
                -v minio_data_compartido:/origen:ro \
                -v "$TRABAJO:/salida" \
                alpine tar czf /salida/archivos.tar.gz -C /origen . 2>/dev/null

            ARCHIVO_DOCS="$DESTINO/archivos-$FECHA.tar.gz"
            mv "$TRABAJO/archivos.tar.gz" "$ARCHIVO_DOCS"
            decir "  archivos: $(numfmt --to=iec "$(stat -c%s "$ARCHIVO_DOCS")")"
        fi
    else
        decir "  AVISO: no se encontro el volumen de MinIO."
    fi
fi

# ---------------------------------------------------------------------
# 3. Cifrado
# ---------------------------------------------------------------------
# El respaldo trae el expediente clinico completo de cada paciente. Si
# se va a sacar del servidor (a un disco, a la nube, por correo), tiene
# que ir cifrado: en Mexico los datos de salud son datos sensibles y la
# ley pide medidas reforzadas para protegerlos.
cifrar() {
    local f="$1"
    [[ -n ${CLAVE_RESPALDO:-} && -f $f ]] || return 0

    openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt \
        -in "$f" -out "$f.enc" -pass "pass:$CLAVE_RESPALDO"
    rm -f "$f"
    chmod 600 "$f.enc"
    decir "  cifrado: $(basename "$f.enc")"
}

if [[ -n ${CLAVE_RESPALDO:-} ]]; then
    cifrar "$ARCHIVO_BASE"
    [[ -n $ARCHIVO_DOCS ]] && cifrar "$ARCHIVO_DOCS"
else
    chmod 600 "$ARCHIVO_BASE"
    [[ -n $ARCHIVO_DOCS ]] && chmod 600 "$ARCHIVO_DOCS"
    decir "  AVISO: sin cifrar. Pon CLAVE_RESPALDO en el .env antes de"
    decir "         sacar estos respaldos del servidor."
fi

# ---------------------------------------------------------------------
# 4. Rotacion, distinta para cada uno
# ---------------------------------------------------------------------
# La base por dias: es chica y entre mas atras se pueda volver, mejor.
BORRADOS=$(find "$DESTINO" -name 'base-*.tar.gz*' \
    -mtime "+$DIAS_BASE" -print -delete | wc -l)
(( BORRADOS > 0 )) && decir "Se borraron $BORRADOS respaldo(s) de base de mas de $DIAS_BASE dias."

# Los archivos por cantidad: pesan, y lo que importa es tener unas
# pocas generaciones, no una ventana de tiempo.
mapfile -t VIEJOS < <(ls -t "$DESTINO"/archivos-*.tar.gz* 2>/dev/null | tail -n "+$((COPIAS_ARCHIVOS + 1))")
if (( ${#VIEJOS[@]} > 0 )); then
    rm -f "${VIEJOS[@]}"
    decir "Se borraron ${#VIEJOS[@]} respaldo(s) de archivos; se conservan $COPIAS_ARCHIVOS."
fi

# ---------------------------------------------------------------------
# 5. Resumen
# ---------------------------------------------------------------------
CUANTAS_BASES=$(find "$DESTINO" -name 'base-*.tar.gz*' | wc -l)
CUANTOS_DOCS=$(find "$DESTINO" -name 'archivos-*.tar.gz*' | wc -l)
ESPACIO=$(du -sh "$DESTINO" | cut -f1)

decir "En total: $CUANTAS_BASES de base, $CUANTOS_DOCS de archivos, $ESPACIO"
decir "Libre despues: $(libre_gb) GB"

# ---------------------------------------------------------------------
# 6. Recordatorio incomodo pero necesario
# ---------------------------------------------------------------------
# Un respaldo que vive en el mismo servidor no protege del caso mas
# probable: que el servidor se pierda. Si el proveedor tiene un
# problema, se pierden el sistema y los respaldos a la vez.
if [[ -z ${COPIA_FUERA:-} ]]; then
    decir "AVISO: estos respaldos solo existen en este servidor."
    decir "       Si el servidor se pierde, se pierden con el."
    decir "       Bajalos a tu computadora al menos una vez por semana."
fi

decir "=== Fin ==="
