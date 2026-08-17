#!/bin/bash
# =====================================================================
#  Sistema Consultorio Dental - reparacion
#
#  Usa esto cuando los puertos quedaron mal repartidos o el arranque
#  se quedo a medias. Deja todo como recien instalado:
#    1. Baja y borra los contenedores del proyecto
#    2. Borra la base de datos y la vuelve a crear desde los scripts
#    3. Reescribe el .env con puertos limpios y distintos entre si
#    4. Levanta el sistema
#
#  Solo se pierden los datos de demostracion, que se vuelven a cargar.
# =====================================================================

cd "$(dirname "$0")" || exit 1

MORADO='\033[0;35m'
VERDE='\033[0;32m'
AMBAR='\033[0;33m'
ROJO='\033[0;31m'
NC='\033[0m'

echo ""
echo -e "${MORADO}=====================================${NC}"
echo -e "${MORADO}  Reparacion del entorno${NC}"
echo -e "${MORADO}=====================================${NC}"
echo ""

if ! docker info >/dev/null 2>&1; then
    echo -e "${ROJO}Docker no esta corriendo. Abrelo y vuelve a intentar.${NC}"
    echo ""
    read -n 1 -s -r -p "Presiona cualquier tecla para cerrar..."
    exit 1
fi

# ---------- 1. Borrar todo lo del proyecto ----------
echo "Bajando y borrando contenedores y base de datos del proyecto..."
docker compose down -v --remove-orphans 2>/dev/null

# Por si quedaron contenedores sueltos con esos nombres.
for c in consultorio_app consultorio_db consultorio_pma; do
    if docker ps -aq -f "name=^${c}$" | grep -q .; then
        echo "  Quitando contenedor suelto: $c"
        docker rm -f "$c" >/dev/null 2>&1
    fi
done

echo -e "${VERDE}Limpieza terminada.${NC}"

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

# ---------- 2. Buscar tres puertos libres y DISTINTOS ----------
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
    while puerto_ocupado "$p" && [ $i -lt 100 ]; do
        p=$((p + 1)); i=$((i + 1))
    done
    YA_ASIGNADOS="$YA_ASIGNADOS $p"
    PUERTO_LIBRE="$p"
}

echo ""
echo "Buscando tres puertos libres..."

buscar_libre 8090; APP_PORT="$PUERTO_LIBRE"
buscar_libre 8091; PMA_PORT="$PUERTO_LIBRE"
buscar_libre 3310; DB_PORT="$PUERTO_LIBRE"

echo "  Sistema      -> $APP_PORT"
echo "  phpMyAdmin   -> $PMA_PORT"
echo "  MySQL        -> $DB_PORT"

# Red de seguridad: si por lo que sea salieron repetidos, aqui se corta.
if [ "$APP_PORT" = "$PMA_PORT" ] || [ "$APP_PORT" = "$DB_PORT" ] || [ "$PMA_PORT" = "$DB_PORT" ]; then
    echo ""
    echo -e "${ROJO}Los puertos salieron repetidos. Algo raro paso.${NC}"
    echo "Edita el archivo .env a mano y ponles valores distintos."
    echo ""
    read -n 1 -s -r -p "Presiona cualquier tecla para cerrar..."
    exit 1
fi

# ---------- 3. Reescribir el .env desde cero ----------
cat > .env <<EOF
# Generado por reparar.command el $(date '+%d/%m/%Y a las %H:%M')
APP_ENV=desarrollo
APP_PORT=${APP_PORT}
APP_TZ=America/Mazatlan

DB_NAME=consultorio_dental
DB_USER=consultorio
DB_PASS=consultorio
DB_ROOT_PASS=root
DB_PORT_HOST=${DB_PORT}

PMA_PORT=${PMA_PORT}

# MinIO vive en infra/minio y se alcanza por la red 'sistemas'
MINIO_ENDPOINT=http://minio:9000
MINIO_BUCKET=consultorio-dental
MINIO_LLAVE=minioadmin
MINIO_SECRETO=minioadmin123
EOF

echo ""
echo -e "${VERDE}Archivo .env reescrito.${NC}"

# ---------- 4. Levantar ----------
echo ""
echo "Levantando el sistema..."
echo ""

if ! docker compose up -d --build; then
    echo ""
    echo -e "${ROJO}Fallo el arranque.${NC}"
    echo "Copia el mensaje de arriba para revisarlo."
    echo ""
    read -n 1 -s -r -p "Presiona cualquier tecla para cerrar..."
    exit 1
fi

# ---------- 5. Esperar a que MySQL cargue los scripts ----------
echo ""
echo -n "Cargando la base de datos (esto tarda, son 58 tablas)"
for _ in $(seq 1 90); do
    if docker compose exec -T db \
        mysql -uroot -proot -e "SELECT 1 FROM consultorio_dental.usuario LIMIT 1" \
        >/dev/null 2>&1; then
        break
    fi
    echo -n "."
    sleep 2
done
echo ""

# ---------- 6. Comprobar que quedo bien ----------
echo ""
echo "Verificando..."

tablas=$(docker compose exec -T db mysql -uroot -proot -N -B -e \
    "SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema='consultorio_dental'" 2>/dev/null | tr -d '\r')
pacientes=$(docker compose exec -T db mysql -uroot -proot -N -B -e \
    "SELECT COUNT(*) FROM consultorio_dental.registro_paciente" 2>/dev/null | tr -d '\r')

if [ -n "$tablas" ] && [ "$tablas" -gt 40 ] 2>/dev/null; then
    echo -e "${VERDE}  Base de datos: $tablas tablas y vistas, $pacientes pacientes de demo.${NC}"
else
    echo -e "${AMBAR}  La base no cargo completa. Revisa con:  docker compose logs db${NC}"
fi

codigo=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:${APP_PORT}/login.php" 2>/dev/null)
if [ "$codigo" = "200" ]; then
    echo -e "${VERDE}  El sistema responde correctamente.${NC}"
else
    echo -e "${AMBAR}  El sistema respondio codigo ${codigo:-sin respuesta}. Revisa con:  docker compose logs app${NC}"
fi

# Que la app alcance a MinIO por dentro de Docker
if docker compose exec -T app php -r \
    'exit(@file_get_contents("http://minio:9000/minio/health/live") === false ? 1 : 0);' \
    >/dev/null 2>&1; then
    echo -e "${VERDE}  El almacenamiento de archivos responde.${NC}"
else
    echo -e "${AMBAR}  La app no alcanza a MinIO. Levantalo con infra/minio/iniciar-minio.command${NC}"
fi

# ---------- 7. Listo ----------
echo ""
echo -e "${VERDE}=====================================${NC}"
echo -e "${VERDE}  Listo${NC}"
echo -e "${VERDE}=====================================${NC}"
echo ""
echo "  Sistema      http://localhost:${APP_PORT}"
echo "  phpMyAdmin   http://localhost:${PMA_PORT}   (root / root)"
echo ""
echo "  Entra con:  recepcion  /  Admin123!"
echo ""

sleep 2
open "http://localhost:${APP_PORT}"

echo ""
read -n 1 -s -r -p "Presiona cualquier tecla para cerrar..."
