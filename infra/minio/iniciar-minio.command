#!/bin/bash
# =====================================================================
#  MinIO compartido - arranque en un doble clic (macOS)
#
#  Levanta el almacenamiento de archivos que usan todos tus sistemas.
#  Se levanta UNA vez y se queda corriendo; los demas proyectos se
#  conectan a el por la red 'sistemas'.
# =====================================================================

cd "$(dirname "$0")" || exit 1

MORADO='\033[0;35m'
VERDE='\033[0;32m'
AMBAR='\033[0;33m'
ROJO='\033[0;31m'
NC='\033[0m'

pausa_y_salir() {
    echo ""
    read -n 1 -s -r -p "Presiona cualquier tecla para cerrar..."
    exit "${1:-1}"
}

echo ""
echo -e "${MORADO}=====================================${NC}"
echo -e "${MORADO}  MinIO compartido${NC}"
echo -e "${MORADO}=====================================${NC}"
echo ""

# ---------- 1. Docker ----------
if ! command -v docker >/dev/null 2>&1; then
    echo -e "${ROJO}Docker no esta instalado.${NC}"
    echo "Descargalo de: https://www.docker.com/products/docker-desktop/"
    pausa_y_salir 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "Abriendo Docker..."
    open -a Docker 2>/dev/null
    echo -n "Esperando"
    for _ in $(seq 1 60); do
        if docker info >/dev/null 2>&1; then break; fi
        echo -n "."
        sleep 2
    done
    echo ""
    if ! docker info >/dev/null 2>&1; then
        echo -e "${ROJO}Docker no arranco.${NC}"
        pausa_y_salir 1
    fi
fi

echo -e "${VERDE}Docker listo.${NC}"

# ---------- 2. Archivo .env ----------
if [ ! -f .env ]; then
    cp .env.example .env
    echo -e "${VERDE}Archivo .env creado.${NC}"
fi

leer_env() { grep -E "^$1=" .env 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '\r'; }
escribir_env() {
    if grep -qE "^$1=" .env; then
        sed -i '' -E "s|^$1=.*|$1=$2|" .env
    else
        printf '\n%s=%s\n' "$1" "$2" >> .env
    fi
}

# ---------- 3. Red compartida ----------
# Es lo que permite que el consultorio le hable a MinIO por su nombre
# sin exponer el puerto fuera de Docker.
if ! docker network inspect sistemas >/dev/null 2>&1; then
    echo "Creando la red compartida 'sistemas'..."
    docker network create sistemas >/dev/null
    echo -e "${VERDE}Red creada.${NC}"
else
    echo "Red 'sistemas' ya existe."
fi

# ---------- 4. Puertos libres ----------
YA_ASIGNADOS=""
PUERTO_LIBRE=""

puerto_ocupado() {
    local p=$1
    case " $YA_ASIGNADOS " in *" $p "*) return 0 ;; esac
    if lsof -nP -iTCP:"$p" -sTCP:LISTEN >/dev/null 2>&1; then return 0; fi
    if docker ps --format '{{.Ports}}' 2>/dev/null | grep -qE "(^|[^0-9])${p}->"; then return 0; fi
    return 1
}

buscar_libre() {
    local p=$1 i=0
    while puerto_ocupado "$p" && [ $i -lt 100 ]; do p=$((p + 1)); i=$((i + 1)); done
    YA_ASIGNADOS="$YA_ASIGNADOS $p"
    PUERTO_LIBRE="$p"
}

# Si MinIO ya esta arriba, sus puertos actuales no cuentan como ocupados.
if docker ps --format '{{.Names}}' | grep -q '^minio_compartido$'; then
    echo "MinIO ya esta corriendo. Se conservan sus puertos."
else
    echo ""
    echo "Revisando puertos..."
    for par in "MINIO_PORT_API:9100" "MINIO_PORT_CONSOLA:9101"; do
        clave="${par%%:*}"
        deseado="$(leer_env "$clave")"
        [ -z "$deseado" ] && deseado="${par##*:}"

        buscar_libre "$deseado"
        if [ "$PUERTO_LIBRE" != "$deseado" ]; then
            echo -e "${AMBAR}  El puerto $deseado esta ocupado. Se usara el $PUERTO_LIBRE.${NC}"
            escribir_env "$clave" "$PUERTO_LIBRE"
        else
            echo "  $clave -> $deseado  (libre)"
        fi
    done
fi

API="$(leer_env MINIO_PORT_API)"
CONSOLA="$(leer_env MINIO_PORT_CONSOLA)"
USUARIO="$(leer_env MINIO_USUARIO)"
PASSWORD="$(leer_env MINIO_PASSWORD)"
BUCKETS="$(leer_env MINIO_BUCKETS)"

# ---------- 5. Levantar ----------
echo ""
echo "Levantando MinIO..."
echo ""

if ! docker compose up -d; then
    echo ""
    echo -e "${ROJO}Fallo el arranque.${NC}"
    pausa_y_salir 1
fi

echo ""
echo -n "Esperando a que responda"
for _ in $(seq 1 45); do
    if curl -s -o /dev/null "http://localhost:${API}/minio/health/live"; then break; fi
    echo -n "."
    sleep 1
done
echo ""

# ---------- 6. Listo ----------
echo ""
echo -e "${VERDE}=====================================${NC}"
echo -e "${VERDE}  MinIO listo${NC}"
echo -e "${VERDE}=====================================${NC}"
echo ""
echo "  Consola web   http://localhost:${CONSOLA}"
echo "  Usuario       ${USUARIO}"
echo "  Contrasena    ${PASSWORD}"
echo ""
echo "  Buckets       ${BUCKETS}"
echo ""
echo "  Los sistemas se conectan por dentro de Docker a:"
echo "     http://minio:9000     (red 'sistemas')"
echo ""
echo -e "${AMBAR}  Para agregar otro sistema:${NC}"
echo "   1. Agrega su bucket a MINIO_BUCKETS en el archivo .env"
echo "   2. Vuelve a dar doble clic aqui"
echo "   3. En el docker-compose de ese sistema, agrega:"
echo "        networks: [sistemas]"
echo "      y al final del archivo:"
echo "        networks:"
echo "          sistemas:"
echo "            external: true"
echo ""

sleep 2
open "http://localhost:${CONSOLA}"

pausa_y_salir 0
