#!/usr/bin/env bash
# =====================================================================
#  Desplegar una version nueva
#
#  Lo que corres en el servidor cada vez que hay cambios. Hace lo
#  minimo y en el orden correcto:
#
#      respaldar -> bajar cambios -> reconstruir -> revisar
#
#  El respaldo va PRIMERO a proposito. Si el despliegue sale mal, la
#  diferencia entre un susto y un desastre es tener el estado de hace
#  dos minutos.
#
#  Uso:
#      ./desplegar.sh
#      ./desplegar.sh --sin-respaldo     (solo si acabas de respaldar)
# =====================================================================

set -euo pipefail

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ="$(cd "$AQUI/../.." && pwd)"

VERDE=$'\033[0;32m'; AMARILLO=$'\033[1;33m'; ROJO=$'\033[0;31m'
AZUL=$'\033[0;34m'; NEGRITA=$'\033[1m'; FIN=$'\033[0m'

paso()  { echo; echo "${AZUL}${NEGRITA}==> $*${FIN}"; }
ok()    { echo "    ${VERDE}ok${FIN}   $*"; }
aviso() { echo "    ${AMARILLO}~${FIN}    $*"; }
morir() { echo "    ${ROJO}!!${FIN}   $*" >&2; exit 1; }

cd "$RAIZ"

# ---------------------------------------------------------------------
# 1. Respaldo
# ---------------------------------------------------------------------
if [[ ${1:-} != --sin-respaldo ]]; then
    paso "Respaldando antes de tocar nada"
    "$AQUI/respaldar.sh" | sed 's/^/    /'
    ok "respaldo hecho"
else
    aviso "Sin respaldo previo, por peticion."
fi

# ---------------------------------------------------------------------
# 2. Cambios
# ---------------------------------------------------------------------
paso "Bajando los cambios"

VERSION_ANTES="$(grep -oP "APP_VERSION', '\K[^']+" config/config.php 2>/dev/null || echo '?')"

if [[ -d .git ]]; then
    # Un archivo tocado a mano en el servidor haria fallar el pull sin
    # decir por que. Mas vale avisarlo antes.
    if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
        aviso "Hay cambios locales sin guardar en el servidor:"
        git status --short --untracked-files=no | sed 's/^/        /'
        read -rp "    ¿Descartarlos y seguir? [s/N] " r
        [[ ${r,,} == s ]] || exit 1
        git reset --hard HEAD
    fi

    git pull --ff-only || morir "No se pudo actualizar desde git."
    ok "codigo al dia"
else
    aviso "Esto no es un repositorio git: sube los archivos a mano."
fi

VERSION_AHORA="$(grep -oP "APP_VERSION', '\K[^']+" config/config.php 2>/dev/null || echo '?')"
[[ $VERSION_ANTES != "$VERSION_AHORA" ]] \
    && ok "version $VERSION_ANTES -> ${NEGRITA}$VERSION_AHORA${FIN}" \
    || ok "version $VERSION_AHORA"

# ---------------------------------------------------------------------
# 3. Cambios de base de datos
# ---------------------------------------------------------------------
# Los .sql de db/ solo corren cuando la base nace vacia. En un servidor
# con datos reales nunca se disparan, asi que un cambio de esquema hay
# que aplicarlo a mano. Se avisa en vez de intentar adivinar: aplicar
# un ALTER equivocado sobre datos de pacientes es peor que no aplicarlo.
if [[ -d .git ]] && git diff --name-only HEAD@{1} HEAD 2>/dev/null | grep -q '^db/'; then
    echo
    aviso "${NEGRITA}Cambiaron archivos de db/.${FIN}"
    aviso "Los .sql NO se aplican solos sobre una base con datos."
    aviso "Revisa que cambio y aplicalo a mano por phpMyAdmin:"
    git diff --name-only HEAD@{1} HEAD | grep '^db/' | sed 's/^/        /'
    echo
    read -rp "    ¿Continuar con el despliegue? [s/N] " r
    [[ ${r,,} == s ]] || exit 1
fi

# ---------------------------------------------------------------------
# 4. Reconstruir
# ---------------------------------------------------------------------
paso "Levantando la version nueva"
cd "$AQUI"
docker compose -f docker-compose.produccion.yml up -d --build \
    || morir "No levanto. Revisa: docker compose logs"
ok "contenedores arriba"

# ---------------------------------------------------------------------
# 5. Revisar que quedo bien
# ---------------------------------------------------------------------
paso "Comprobando"

set -a; source "$AQUI/.env"; set +a

# Se le da un momento a Apache para terminar de arrancar antes de
# declarar que algo fallo.
for intento in 1 2 3 4 5 6 7 8 9 10; do
    CODIGO="$(curl -fsS -o /dev/null -w '%{http_code}' \
        --max-time 5 "https://$DOMINIO/login.php" 2>/dev/null || echo '000')"
    [[ $CODIGO == 200 ]] && break
    sleep 2
done

if [[ $CODIGO == 200 ]]; then
    ok "https://$DOMINIO responde bien"
else
    echo
    morir "El sistema responde $CODIGO. Revisa con:
        docker compose -f $AQUI/docker-compose.produccion.yml logs --tail 50 app"
fi

# Que la app siga alcanzando MinIO. Es la falla silenciosa mas comun:
# el sistema abre, pero los documentos no se pueden subir ni ver.
if docker exec consultorio_app php -r '
    $c = curl_init(getenv("MINIO_ENDPOINT") . "/minio/health/live");
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 5]);
    curl_exec($c);
    exit(curl_getinfo($c, CURLINFO_HTTP_CODE) === 200 ? 0 : 1);
' 2>/dev/null; then
    ok "el almacenamiento de archivos responde"
else
    aviso "MinIO no responde. El sistema abre, pero no podra guardar"
    aviso "ni mostrar documentos. Revisa: cd ../minio && docker compose ps"
fi

echo
echo "${VERDE}${NEGRITA}  Desplegado. Version $VERSION_AHORA en https://$DOMINIO${FIN}"
echo
