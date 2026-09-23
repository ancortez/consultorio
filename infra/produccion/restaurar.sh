#!/usr/bin/env bash
# =====================================================================
#  Restauracion del Sistema Consultorio Dental
#
#  Un respaldo que nunca se probo no es un respaldo: es una carpeta con
#  archivos que uno supone que sirven. Este guion existe para que lo
#  pruebes sin miedo.
#
#  Hay dos tipos de respaldo, porque se hacen por separado:
#
#      base-FECHA.tar.gz       la base de datos (diario)
#      archivos-FECHA.tar.gz   el expediente escaneado (semanal)
#
#  Restaurar la base sola es lo normal: es lo que se pierde de verdad.
#  Los archivos solo hace falta restaurarlos si se perdio el volumen
#  entero de MinIO.
#
#  Uso:
#      ./restaurar.sh --listar            ver los respaldos que hay
#      ./restaurar.sh --probar            probar el ultimo SIN tocar nada
#      ./restaurar.sh --probar ARCHIVO    probar uno en concreto
#      ./restaurar.sh ARCHIVO             restaurar de verdad
#
#  --probar abre el respaldo en una base temporal, cuenta los
#  renglones y la borra. Es la unica forma honesta de saber que el
#  respaldo sirve, y se puede correr un martes cualquiera sin riesgo.
# =====================================================================

set -euo pipefail

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DESTINO="${CARPETA_RESPALDOS:-/var/respaldos/consultorio}"

ROJO=$'\033[0;31m'; VERDE=$'\033[0;32m'; AMARILLO=$'\033[1;33m'
NEGRITA=$'\033[1m'; FIN=$'\033[0m'

decir() { echo "$*"; }
ok()    { echo "  ${VERDE}ok${FIN}   $*"; }
aviso() { echo "  ${AMARILLO}~${FIN}    $*"; }
morir() { echo "  ${ROJO}!!${FIN}   $*" >&2; exit 1; }

[[ -f "$AQUI/.env" ]] || morir "Falta $AQUI/.env"
set -a; source "$AQUI/.env"; set +a

# ---------------------------------------------------------------------
# --listar
# ---------------------------------------------------------------------
if [[ ${1:-} == --listar ]]; then
    decir
    decir "${NEGRITA}Respaldos en $DESTINO${FIN}"
    decir
    if ! ls "$DESTINO"/{base,archivos}-*.tar.gz* >/dev/null 2>&1; then
        aviso "No hay ninguno todavia. Corre ./respaldar.sh"
        exit 0
    fi

    # Del mas reciente al mas viejo, que es como se buscan. Separados
    # por tipo para que se vea de un golpe que hay de cada uno.
    decir "  ${NEGRITA}Base de datos${FIN} (diario)"
    ls -lht "$DESTINO"/base-*.tar.gz* 2>/dev/null \
        | awk '{printf "    %-10s %s %s %s  %s\n", $5, $6, $7, $8, $9}' \
        || echo "    (ninguno)"

    decir
    decir "  ${NEGRITA}Archivos del expediente${FIN} (semanal)"
    ls -lht "$DESTINO"/archivos-*.tar.gz* 2>/dev/null \
        | awk '{printf "    %-10s %s %s %s  %s\n", $5, $6, $7, $8, $9}' \
        || echo "    (ninguno)"
    decir
    exit 0
fi

MODO_PRUEBA=0
if [[ ${1:-} == --probar ]]; then
    MODO_PRUEBA=1
    shift
fi

# ---------------------------------------------------------------------
# Que archivo
# ---------------------------------------------------------------------
ARCHIVO="${1:-}"
if [[ -z $ARCHIVO ]]; then
    # Sin decir cual, se usa la base mas reciente: es lo que casi
    # siempre se quiere restaurar.
    ARCHIVO="$(ls -t "$DESTINO"/base-*.tar.gz* 2>/dev/null | head -1 || true)"
    [[ -n $ARCHIVO ]] || morir "No hay respaldos de base. Corre ./respaldar.sh"
    decir "Usando el mas reciente: $(basename "$ARCHIVO")"
fi
[[ -f $ARCHIVO ]] || morir "No existe: $ARCHIVO"

# Que tipo de respaldo es, por el nombre.
TIPO=base
[[ $(basename "$ARCHIVO") == archivos-* ]] && TIPO=archivos

TRABAJO="$(mktemp -d)"
trap 'rm -rf "$TRABAJO"' EXIT

# ---------------------------------------------------------------------
# Descifrar y abrir
# ---------------------------------------------------------------------
decir
decir "${NEGRITA}Abriendo el respaldo${FIN}"

if [[ $ARCHIVO == *.enc ]]; then
    [[ -n ${CLAVE_RESPALDO:-} ]] || morir "El respaldo esta cifrado y falta CLAVE_RESPALDO en el .env"

    openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 \
        -in "$ARCHIVO" -out "$TRABAJO/paquete.tar.gz" \
        -pass "pass:$CLAVE_RESPALDO" 2>/dev/null \
        || morir "No se pudo descifrar. ¿Es la clave correcta?"
    ok "descifrado"
else
    cp "$ARCHIVO" "$TRABAJO/paquete.tar.gz"
fi

if [[ $TIPO == archivos ]]; then
    # Los de archivos son el tar del volumen tal cual, sin envoltura.
    ok "abierto (respaldo de archivos del expediente)"
else
    tar xzf "$TRABAJO/paquete.tar.gz" -C "$TRABAJO" || morir "El archivo esta danado."
    [[ -f "$TRABAJO/base.sql" ]] || morir "El respaldo no trae la base de datos."
    ok "abierto"
fi

if [[ -f "$TRABAJO/acerca-de.txt" ]]; then
    decir
    sed 's/^/  /' "$TRABAJO/acerca-de.txt"
fi

# ---------------------------------------------------------------------
# --probar: base temporal, contar, borrar
# ---------------------------------------------------------------------
if (( MODO_PRUEBA )) && [[ $TIPO == archivos ]]; then
    CUANTOS=$(tar tzf "$TRABAJO/paquete.tar.gz" 2>/dev/null | grep -c . || echo 0)
    decir
    if (( CUANTOS > 0 )); then
        ok "el respaldo se abre bien: $CUANTOS archivo(s) dentro"
        decir
        decir "  ${NEGRITA}Una muestra${FIN}"
        tar tzf "$TRABAJO/paquete.tar.gz" 2>/dev/null | head -5 | sed 's/^/    /'
        decir
        ok "${VERDE}${NEGRITA}El respaldo sirve.${FIN}"
    else
        morir "El respaldo de archivos esta vacio."
    fi
    decir
    exit 0
fi

if (( MODO_PRUEBA )); then
    PRUEBA="prueba_respaldo_$$"
    decir
    decir "${NEGRITA}Probando en la base temporal $PRUEBA${FIN}"
    decir "  (la base de produccion no se toca)"

    limpiar_prueba() {
        docker exec consultorio_db mysql --user=root --password="$DB_ROOT_PASS" \
            -e "DROP DATABASE IF EXISTS \`$PRUEBA\`;" 2>/dev/null || true
    }
    trap 'limpiar_prueba; rm -rf "$TRABAJO"' EXIT

    docker exec consultorio_db mysql --user=root --password="$DB_ROOT_PASS" \
        -e "CREATE DATABASE \`$PRUEBA\` CHARACTER SET utf8mb4;" 2>/dev/null \
        || morir "No se pudo crear la base de prueba."

    docker exec -i consultorio_db mysql --user=root --password="$DB_ROOT_PASS" \
        "$PRUEBA" < "$TRABAJO/base.sql" 2>/dev/null \
        || morir "El respaldo NO se pudo restaurar. Revisalo."

    ok "el respaldo se restaura sin errores"

    decir
    decir "${NEGRITA}Lo que trae dentro${FIN}"
    docker exec consultorio_db mysql --user=root --password="$DB_ROOT_PASS" \
        --batch --skip-column-names "$PRUEBA" -e "
        SELECT CONCAT('  pacientes:  ', COUNT(*)) FROM registro_paciente
        UNION ALL SELECT CONCAT('  consultas:  ', COUNT(*)) FROM consulta
        UNION ALL SELECT CONCAT('  planes:     ', COUNT(*)) FROM plan_tratamiento
        UNION ALL SELECT CONCAT('  cargos:     ', COUNT(*)) FROM cargo
        UNION ALL SELECT CONCAT('  pagos:      ', COUNT(*)) FROM pago
        UNION ALL SELECT CONCAT('  documentos: ', COUNT(*)) FROM documento;
    " 2>/dev/null || aviso "No se pudieron contar los renglones."

    if [[ -f "$TRABAJO/archivos.tar.gz" ]]; then
        CUANTOS=$(tar tzf "$TRABAJO/archivos.tar.gz" 2>/dev/null | grep -c . || echo 0)
        decir "  archivos:   $CUANTOS en el almacenamiento"
    fi

    decir
    ok "${VERDE}${NEGRITA}El respaldo sirve.${FIN}"
    decir
    exit 0
fi

# ---------------------------------------------------------------------
# Restauracion de verdad
#
# Los dos tipos de respaldo se restauran distinto y no se mezclan: uno
# reemplaza la base y el otro el almacen de archivos.
# ---------------------------------------------------------------------
decir
decir "${ROJO}${NEGRITA}  ATENCION${FIN}"

if [[ $TIPO == archivos ]]; then
    decir "${ROJO}  Esto REEMPLAZA todos los archivos del expediente${FIN}"
    decir "${ROJO}  (radiografias, fotos, escaneos) con los del respaldo.${FIN}"
    decir "${ROJO}  Lo que se haya subido despues de${FIN}"
    decir "${ROJO}  $(basename "$ARCHIVO") se pierde.${FIN}"
    decir "${ROJO}  La base de datos NO se toca.${FIN}"
else
    decir "${ROJO}  Esto REEMPLAZA la base '$DB_NAME' con la del respaldo.${FIN}"
    decir "${ROJO}  Lo que se haya capturado despues de${FIN}"
    decir "${ROJO}  $(basename "$ARCHIVO") se pierde.${FIN}"
    decir "${ROJO}  Los archivos del expediente NO se tocan.${FIN}"
fi
decir

read -rp "  Escribe RESTAURAR para continuar: " CONFIRMA
[[ $CONFIRMA == RESTAURAR ]] || { decir "  Cancelado."; exit 0; }

# ---------------------------------------------------------------------
# Archivos del expediente
# ---------------------------------------------------------------------
if [[ $TIPO == archivos ]]; then
    decir
    decir "Restaurando los archivos del expediente..."

    # MinIO tiene que estar detenido: reemplazar sus archivos por
    # debajo mientras corre lo deja viendo un estado a medias.
    docker stop minio_compartido >/dev/null 2>&1 || true

    docker run --rm \
        -v minio_data_compartido:/destino \
        -v "$TRABAJO:/entrada:ro" \
        alpine sh -c "rm -rf /destino/* /destino/..?* 2>/dev/null; \
                      tar xzf /entrada/paquete.tar.gz -C /destino" \
        || { docker start minio_compartido >/dev/null 2>&1; \
             morir "Fallo la restauracion de los archivos."; }

    docker start minio_compartido >/dev/null 2>&1 || true
    ok "archivos restaurados"

    decir
    ok "${VERDE}${NEGRITA}Restauracion terminada.${FIN}"
    decir "  Entra al expediente de algun paciente y revisa que sus"
    decir "  documentos se vean."
    decir
    exit 0
fi

# ---------------------------------------------------------------------
# Base de datos
# ---------------------------------------------------------------------
# Antes de pisar nada, un respaldo de lo que hay. Si la restauracion
# resulta ser la equivocada, todavia hay camino de vuelta.
decir
decir "Guardando primero lo que hay ahora, por si acaso..."
ANTES="$DESTINO/antes-de-restaurar-$(date '+%Y%m%d-%H%M').sql"
docker exec consultorio_db mysqldump --user=root --password="$DB_ROOT_PASS" \
    --single-transaction --quick "$DB_NAME" > "$ANTES" 2>/dev/null \
    && ok "guardado en $ANTES" \
    || aviso "no se pudo (¿la base esta vacia?)"

decir
decir "Restaurando la base..."
docker exec -i consultorio_db mysql --user=root --password="$DB_ROOT_PASS" \
    -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;
        CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

docker exec -i consultorio_db mysql --user=root --password="$DB_ROOT_PASS" \
    "$DB_NAME" < "$TRABAJO/base.sql" || morir "Fallo la restauracion de la base."
ok "base restaurada"

decir
aviso "Los archivos del expediente no se tocaron: siguen como estaban."
aviso "Si tambien hay que restaurarlos, busca el respaldo semanal:"
aviso "    ./restaurar.sh --listar"

decir
decir "Reiniciando el sistema..."
(cd "$AQUI" && docker compose -f docker-compose.produccion.yml restart app >/dev/null)

decir
ok "${VERDE}${NEGRITA}Restauracion terminada.${FIN}"
decir "  Entra al sistema y revisa que todo este en su lugar."
decir
