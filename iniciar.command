#!/bin/bash
# =====================================================================
#  Sistema Consultorio Dental - arranque en un doble clic (macOS)
#  Da doble clic a este archivo en Finder. Se abre una Terminal sola.
#
#  Busca puertos libres automaticamente, asi que puede convivir con
#  otros contenedores que ya tengas corriendo.
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
echo -e "${MORADO}  Sistema Consultorio Dental${NC}"
echo -e "${MORADO}=====================================${NC}"
echo ""

# ---------- 1. Docker instalado? ----------
if ! command -v docker >/dev/null 2>&1; then
    echo -e "${ROJO}Docker no esta instalado.${NC}"
    echo ""
    echo "Descargalo de:  https://www.docker.com/products/docker-desktop/"
    echo "Instalalo, abrelo, y vuelve a dar doble clic a este archivo."
    pausa_y_salir 1
fi

# ---------- 2. Docker corriendo? ----------
if ! docker info >/dev/null 2>&1; then
    echo "Docker esta instalado pero no esta corriendo. Abriendolo..."
    open -a Docker 2>/dev/null

    echo -n "Esperando a que Docker arranque"
    for _ in $(seq 1 60); do
        if docker info >/dev/null 2>&1; then break; fi
        echo -n "."
        sleep 2
    done
    echo ""

    if ! docker info >/dev/null 2>&1; then
        echo -e "${ROJO}Docker no arranco.${NC}"
        echo "Abrelo a mano (icono de la ballena en la barra superior),"
        echo "espera a que diga 'Running' y vuelve a intentar."
        pausa_y_salir 1
    fi
fi

echo -e "${VERDE}Docker listo.${NC}"

# ---------- 3. Archivo .env ----------
if [ ! -f .env ]; then
    cp .env.example .env
    echo -e "${VERDE}Archivo .env creado.${NC}"
fi

# ---------- Red compartida con MinIO ----------
# El docker-compose la declara como externa. Si no existe, el arranque
# falla con "network sistemas declared as external, but could not be
# found". Se crea aqui para que eso no pase nunca.
if ! docker network inspect sistemas >/dev/null 2>&1; then
    echo "Creando la red compartida 'sistemas'..."
    docker network create sistemas >/dev/null
fi

# MinIO guarda los archivos del expediente. Si no esta arriba, el sistema
# funciona igual pero no se podran subir ni ver documentos.
if ! docker ps --format '{{.Names}}' | grep -q '^minio_compartido$'; then
    echo -e "${AMBAR}  Aviso: MinIO no esta corriendo.${NC}"
    echo "  El expediente electronico no podra guardar archivos."
    echo "  Levantalo con: infra/minio/iniciar-minio.command"
    echo ""
fi

# ---------- 4. Bajar lo que este a medias de este mismo proyecto ----------
# Si una corrida anterior quedo incompleta, sus contenedores siguen
# apartando puertos. Bajarlos primero libera esos puertos y deja que la
# busqueda vea el panorama real. No borra la base de datos.
echo ""
echo "Limpiando contenedores previos de este proyecto..."
docker compose down --remove-orphans >/dev/null 2>&1

# ---------- 5. Buscar puertos libres ----------
# Un puerto esta ocupado si algo escucha en el (lsof), si otro contenedor
# de Docker lo tiene publicado, o si ya se lo asignamos a otro servicio
# nuestro en esta misma corrida.
YA_ASIGNADOS=""

puerto_ocupado() {
    local p=$1
    # Ya se lo dimos a otro servicio nuestro hace un momento
    case " $YA_ASIGNADOS " in
        *" $p "*) return 0 ;;
    esac
    if lsof -nP -iTCP:"$p" -sTCP:LISTEN >/dev/null 2>&1; then
        return 0
    fi
    if docker ps --format '{{.Ports}}' 2>/dev/null | grep -qE "(^|[^0-9])${p}->"; then
        return 0
    fi
    return 1
}

# Busca el primer puerto libre a partir del deseado y lo aparta.
#
# OJO: deja el resultado en la variable PUERTO_LIBRE en lugar de
# imprimirlo. Si se llamara con $(buscar_libre ...), correria dentro de
# una subshell y la lista YA_ASIGNADOS no regresaria al script; entonces
# dos servicios podrian recibir el mismo puerto y chocar entre ellos.
PUERTO_LIBRE=""
buscar_libre() {
    local p=$1
    local intentos=0
    while puerto_ocupado "$p" && [ $intentos -lt 100 ]; do
        p=$((p + 1))
        intentos=$((intentos + 1))
    done
    YA_ASIGNADOS="$YA_ASIGNADOS $p"
    PUERTO_LIBRE="$p"
}

# Lee un valor del .env
leer_env() {
    grep -E "^$1=" .env 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '\r'
}

# Escribe (o reemplaza) un valor en el .env
escribir_env() {
    local clave=$1 valor=$2
    if grep -qE "^${clave}=" .env; then
        # -i '' es la sintaxis de sed en macOS
        sed -i '' -E "s|^${clave}=.*|${clave}=${valor}|" .env
    else
        printf '\n%s=%s\n' "$clave" "$valor" >> .env
    fi
}

echo ""
echo "Revisando puertos..."

hubo_cambio=0
for par in "APP_PORT:8090" "PMA_PORT:8091" "DB_PORT_HOST:3310"; do
    clave="${par%%:*}"
    respaldo="${par##*:}"

    deseado="$(leer_env "$clave")"
    [ -z "$deseado" ] && deseado="$respaldo"

    buscar_libre "$deseado"
    libre="$PUERTO_LIBRE"

    if [ "$libre" != "$deseado" ]; then
        echo -e "${AMBAR}  El puerto $deseado esta ocupado. Se usara el $libre para $clave.${NC}"
        escribir_env "$clave" "$libre"
        hubo_cambio=1
    else
        echo "  $clave -> $deseado  (libre)"
    fi
done

if [ $hubo_cambio -eq 1 ]; then
    echo ""
    echo "Los puertos quedaron guardados en el archivo .env."
fi

APP_PORT="$(leer_env APP_PORT)"
PMA_PORT="$(leer_env PMA_PORT)"
DB_PORT="$(leer_env DB_PORT_HOST)"

# Red de seguridad: dos servicios nuestros con el mismo puerto se pelean
# entre ellos. Si pasa, mejor cortar aqui con un mensaje claro que dejar
# que Docker falle con "port is already allocated".
if [ "$APP_PORT" = "$PMA_PORT" ] || [ "$APP_PORT" = "$DB_PORT" ] || [ "$PMA_PORT" = "$DB_PORT" ]; then
    echo ""
    echo -e "${ROJO}El archivo .env tiene puertos repetidos:${NC}"
    echo "   APP_PORT=$APP_PORT   PMA_PORT=$PMA_PORT   DB_PORT_HOST=$DB_PORT"
    echo ""
    echo "Da doble clic en ${AMBAR}reparar.command${NC} para dejarlo limpio."
    pausa_y_salir 1
fi

# ---------- 6. Levantar ----------
echo ""
echo "Levantando el sistema. La primera vez tarda unos minutos"
echo "porque descarga PHP y MySQL. Las siguientes son en segundos."
echo ""

if ! docker compose up -d --build; then
    echo ""
    echo -e "${ROJO}Algo fallo al levantar los contenedores.${NC}"
    echo "Copia el mensaje de arriba para revisarlo."
    pausa_y_salir 1
fi

# ---------- 7. Esperar a MySQL ----------
echo ""
echo -n "Preparando la base de datos"
for _ in $(seq 1 90); do
    if docker compose exec -T db mysqladmin ping -h localhost --silent >/dev/null 2>&1; then
        break
    fi
    echo -n "."
    sleep 2
done
echo ""

# ---------- 8. Listo ----------
echo ""
echo -e "${VERDE}=====================================${NC}"
echo -e "${VERDE}  Sistema levantado${NC}"
echo -e "${VERDE}=====================================${NC}"
echo ""
echo "  Sistema      http://localhost:${APP_PORT}"
echo "  phpMyAdmin   http://localhost:${PMA_PORT}   (usuario: root / clave: root)"
echo ""
echo "  Usuarios de prueba (clave para todos: Admin123!)"
echo "     recepcion    - pacientes, agenda, caja y facturacion"
echo "     dr.mendoza   - pacientes, consultas y tratamientos"
echo "     admin        - todo"
echo ""
echo "  Para apagarlo:  doble clic en detener.command"
echo ""

sleep 3
open "http://localhost:${APP_PORT}"

pausa_y_salir 0
