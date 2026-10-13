#!/usr/bin/env bash
# =====================================================================
#  Aplicar las migraciones pendientes de la base
#
#  Los archivos de db/ solo corren cuando MySQL arranca con su carpeta
#  de datos vacia. En un servidor con pacientes dentro eso no vuelve a
#  pasar nunca, asi que todo cambio de estructura posterior entra por
#  db/migraciones/ y lo aplica este guion.
#
#  Uso:
#      ./migrar.sh           aplica lo que falte
#      ./migrar.sh --ver     dice que falta, sin tocar nada
# =====================================================================

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

VERDE=$'\033[0;32m'; AMARILLO=$'\033[1;33m'; ROJO=$'\033[0;31m'
AZUL=$'\033[0;34m'; NEGRITA=$'\033[1m'; FIN=$'\033[0m'

paso()  { echo; echo "${AZUL}${NEGRITA}==> $*${FIN}"; }
ok()    { echo "    ${VERDE}ok${FIN}   $*"; }
aviso() { echo "    ${AMARILLO}~${FIN}    $*"; }
morir() { echo "    ${ROJO}!!${FIN}   $*" >&2; exit 1; }

SOLO_VER=0
[[ ${1:-} == --ver ]] && SOLO_VER=1

# ---------------------------------------------------------------------
# De donde salen las claves y como se llega a MySQL
# ---------------------------------------------------------------------
# El guion sirve igual en el servidor y en tu computadora. Lo unico que
# cambia es cual compose esta corriendo.
# El .env de produccion vive aqui mismo; el de local, en la raiz del
# proyecto. Cual se encuentre primero dice donde estamos parados.
if [[ -f .env ]]; then
    DONDE="servidor"
    COMPOSE="docker-compose.produccion.yml"
    ENV_ARCHIVO=".env"
elif [[ -f ../../.env ]]; then
    DONDE="local"
    COMPOSE="../../docker-compose.yml"
    ENV_ARCHIVO="../../.env"
else
    morir "No encontre un .env. ¿Estas en infra/produccion?"
fi

CLAVE="$(grep -E '^DB_ROOT_PASS=' "$ENV_ARCHIVO" | cut -d= -f2- || true)"
BASE="$(grep -E '^DB_NAME=' "$ENV_ARCHIVO" | cut -d= -f2- || true)"
BASE="${BASE:-consultorio_dental}"
[[ -n $CLAVE ]] || morir "No encontre DB_ROOT_PASS en $ENV_ARCHIVO"

CARPETA="$(cd ../../db/migraciones && pwd)"

# mysql() envuelve la llamada al contenedor. El -N -B devuelve los
# resultados pelados, sin marco ni encabezado, que es lo que conviene
# cuando el que lee es un guion y no una persona.
correr_sql() {
    docker exec -i consultorio_db mysql -uroot -p"$CLAVE" -N -B "$BASE" "$@"
}

docker exec consultorio_db true 2>/dev/null \
    || morir "El contenedor consultorio_db no esta corriendo."

echo
echo "${NEGRITA}  Migraciones de la base${FIN}"
echo "  ----------------------"
echo "  Donde:    $DONDE"
echo "  Base:     $BASE"
echo "  Archivos: $CARPETA"
[[ $SOLO_VER -eq 1 ]] && echo "  ${AMARILLO}Solo mirar: no se va a aplicar nada.${FIN}"
echo

# ---------------------------------------------------------------------
# La tabla de control
# ---------------------------------------------------------------------
# La huella del contenido es lo importante aqui. Detecta el error mas
# silencioso que existe en esto: que alguien edite una migracion
# DESPUES de que ya se aplico en algun lado. En ese servidor corrio la
# version vieja; en uno nuevo correria la nueva. Dos bases que deberian
# ser gemelas dejan de serlo y nadie se entera hasta que una consulta
# falla en un solo lugar.
correr_sql <<'SQLFIN'
CREATE TABLE IF NOT EXISTS migracion (
  archivo     VARCHAR(120) NOT NULL PRIMARY KEY,
  huella      CHAR(64)     NOT NULL,
  aplicada_el DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQLFIN

# ---------------------------------------------------------------------
# Que falta
# ---------------------------------------------------------------------
shopt -s nullglob
ARCHIVOS=("$CARPETA"/*.sql)
shopt -u nullglob

if [[ ${#ARCHIVOS[@]} -eq 0 ]]; then
    ok "No hay migraciones escritas todavia."
    exit 0
fi

# Las columnas que un archivo dice agregar: "tabla columna" por renglon.
#
# Los comentarios se quitan ANTES de buscar: 0002 explica en una linea
# que "MySQL 8 no admite IF NOT EXISTS ni en ADD COLUMN ni en ADD
# UNIQUE", y de ahi salia una columna fantasma llamada "ni" que nunca
# iba a existir. Un verificador que inventa fallos ensenia a ignorarlo.
columnas_que_agrega() {
    sed 's/--.*$//' "$1" \
        | grep -oE 'ALTER TABLE[[:space:]]+[a-z_]+|ADD COLUMN[[:space:]]+[a-z_]+' \
        | awk '/ALTER TABLE/ {t=$3} /ADD COLUMN/ && t != "" {print t, $3}'
}

# Las que de esas NO estan en la base.
columnas_que_faltan() {
    local faltan="" tabla columna existe
    while read -r tabla columna; do
        [[ -z $columna ]] && continue
        existe="$(correr_sql -e "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = '$tabla' AND column_name = '$columna';" \
             2>/dev/null | tr -d '[:space:]')"
        [[ ${existe:-0} -eq 0 ]] && faltan+=" $tabla.$columna"
    done < <(columnas_que_agrega "$1")
    echo "$faltan"
}

PENDIENTES=()
ALTERADAS=()
SIN_EFECTO=()

for ruta in "${ARCHIVOS[@]}"; do
    nombre="$(basename "$ruta")"
    huella="$(sha256sum "$ruta" | cut -d' ' -f1)"

    guardada="$(correr_sql -e \
        "SELECT huella FROM migracion WHERE archivo = '$nombre';" 2>/dev/null || true)"
    guardada="$(echo "$guardada" | tr -d '[:space:]')"

    if [[ -z $guardada ]]; then
        PENDIENTES+=("$ruta")
        continue
    fi

    if [[ $guardada != "$huella" ]]; then
        ALTERADAS+=("$nombre")
        continue
    fi

    # ----------------------------------------------------------------
    # Ya anotada: ¿pero de verdad hizo algo?
    # ----------------------------------------------------------------
    # Una migracion puede correr sin error y no agregar nada. Las que
    # agregan columnas lo hacen con un IF sobre information_schema
    # —MySQL 8 no admite ADD COLUMN IF NOT EXISTS— y si esa condicion
    # sale mal, la sentencia preparada acaba siendo un SELECT 1:
    # termina en exito, se anota como aplicada, y la columna nunca
    # existio.
    #
    # Lo peor de ese fallo es que se cura solo... hacia el lado malo:
    # al quedar anotada, las siguientes corridas dicen "la base esta al
    # dia" y no la vuelven a intentar nunca. El sistema arranca pidiendo
    # una columna que no esta y el error que llega a la pantalla no se
    # parece en nada a la causa.
    #
    # Por eso se revisan TAMBIEN las ya aplicadas, y la que no dejo
    # rastro se vuelve a poner en la fila.
    faltan="$(columnas_que_faltan "$ruta")"
    if [[ -n $faltan ]]; then
        SIN_EFECTO+=("$nombre:$faltan")
        PENDIENTES+=("$ruta")
    fi
done

if [[ ${#SIN_EFECTO[@]} -gt 0 ]]; then
    echo
    for a in "${SIN_EFECTO[@]}"; do
        echo "    ${AMARILLO}~${FIN}    ${a%%:*} figura aplicada pero falta:${a#*:}"
    done
    echo "         Se vuelve a intentar."
fi

# Una migracion aplicada que cambio de contenido no se arregla sola:
# hay que decidir a mano. Aplicarla de nuevo podria duplicar datos;
# ignorarla deja dos bases distintas. Se detiene y se avisa.
if [[ ${#ALTERADAS[@]} -gt 0 ]]; then
    echo
    for a in "${ALTERADAS[@]}"; do
        echo "    ${ROJO}!!${FIN}   $a cambio DESPUES de haberse aplicado aqui"
    done
    echo
    echo "    Una migracion aplicada no se edita: se escribe otra que"
    echo "    corrija. Esta base tiene la version vieja; un servidor"
    echo "    nuevo tendria la nueva, y dejarian de ser iguales."
    echo
    echo "    Si estas seguro de que el cambio es inofensivo (un"
    echo "    comentario, por ejemplo), actualiza la huella a mano:"
    echo "        UPDATE migracion SET huella = '...' WHERE archivo = '...';"
    echo
    morir "No se aplico nada."
fi

if [[ ${#PENDIENTES[@]} -eq 0 ]]; then
    ok "La base esta al dia (${#ARCHIVOS[@]} migracion(es) aplicadas)."
    exit 0
fi

paso "Pendientes: ${#PENDIENTES[@]}"
for ruta in "${PENDIENTES[@]}"; do
    echo "        $(basename "$ruta")"
done

if [[ $SOLO_VER -eq 1 ]]; then
    echo
    aviso "Para aplicarlas: ./migrar.sh"
    exit 0
fi

# ---------------------------------------------------------------------
# Respaldo antes de tocar
# ---------------------------------------------------------------------
# Una migracion cambia la estructura de una base con expedientes
# dentro. Si sale mal a la mitad, el camino de vuelta tiene que existir
# ANTES, no despues.
paso "Respaldando antes de aplicar"

# En el servidor se usa el respaldo de verdad, que cifra y rota copias.
# En local no: respaldar.sh escribe en /var/respaldos, que en tu
# computadora no existe ni tiene por que existir. Ahi basta con un
# volcado al lado del proyecto.
if [[ $DONDE == servidor && -x ./respaldar.sh ]]; then
    ./respaldar.sh >/dev/null 2>&1 && ok "respaldo hecho" \
        || aviso "el respaldo fallo; continuo bajo tu responsabilidad"
else
    mkdir -p ../../storage/respaldos
    RESPALDO="../../storage/respaldos/antes-de-migrar-$(date +%Y%m%d-%H%M%S).sql.gz"
    if docker exec consultorio_db mysqldump -uroot -p"$CLAVE" \
            --single-transaction --routines "$BASE" 2>/dev/null \
            | gzip > "$RESPALDO"; then
        ok "respaldo en storage/respaldos/$(basename "$RESPALDO")"
    else
        rm -f "$RESPALDO"
        aviso "no se pudo respaldar; continuo bajo tu responsabilidad"
    fi
fi

# ---------------------------------------------------------------------
# Aplicar
# ---------------------------------------------------------------------
paso "Aplicando"
for ruta in "${PENDIENTES[@]}"; do
    nombre="$(basename "$ruta")"
    huella="$(sha256sum "$ruta" | cut -d' ' -f1)"

    printf "    %-44s " "$nombre"

    # Cada migracion corre dentro de una transaccion cuando se puede.
    # MySQL hace commit implicito en los ALTER TABLE, asi que esto no
    # protege de todo: por eso las migraciones se escriben para poder
    # correrse dos veces sin romper.
    if salida="$(correr_sql < "$ruta" 2>&1)"; then
        # ------------------------------------------------------------
        # Se COMPRUEBA que la migracion hizo lo que dice
        # ------------------------------------------------------------
        # Que mysql no se queje no significa que haya pasado algo. Las
        # migraciones que agregan columnas lo hacen con un IF sobre
        # information_schema —MySQL 8 no admite ADD COLUMN IF NOT
        # EXISTS— y si esa condicion sale mal, la sentencia preparada
        # acaba siendo un SELECT 1: termina en exito y no agrega nada.
        #
        # Esto paso de verdad con 0009: la migracion se dio por
        # aplicada, se anoto en la tabla de control, y el sistema
        # arranco pidiendo una columna que no existia. El error que
        # llega a la pantalla —"Unknown column rp.requiere_tutor"— no se
        # parece en nada a la causa, y como quedo anotada, volver a
        # correr migrar.sh ya no la intentaba.
        #
        # Se leen los nombres del propio archivo y se confirma contra la
        # base. Si falta alguno, la migracion NO se anota: asi la
        # proxima corrida la vuelve a intentar.
        faltan="$(columnas_que_faltan "$ruta")"

        if [[ -n $faltan ]]; then
            echo "${ROJO}sin efecto${FIN}"
            echo
            echo "        Corrio sin error pero no agrego:$faltan"
            echo
            echo "        Casi siempre es la condicion sobre"
            echo "        information_schema. No se anota como aplicada,"
            echo "        asi que al arreglarla vuelve a intentarse."
            echo
            morir "Se detuvo en $nombre."
        fi

        correr_sql -e "INSERT INTO migracion (archivo, huella)
                       VALUES ('$nombre', '$huella')
                       ON DUPLICATE KEY UPDATE huella = VALUES(huella);"
        echo "${VERDE}ok${FIN}"
    else
        echo "${ROJO}fallo${FIN}"
        echo
        echo "$salida" | sed 's/^/        /'
        echo
        echo "    Las anteriores si quedaron aplicadas. Arregla esta y"
        echo "    vuelve a correr ./migrar.sh: las ya hechas se saltan."
        morir "Se detuvo en $nombre."
    fi
done

echo
echo "${VERDE}${NEGRITA}  Base al dia.${FIN}"
echo
