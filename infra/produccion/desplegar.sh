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

# Antes de nada: que esto no sea la Mac de alguien.
source "$AQUI/solo-en-el-servidor.sh"
solo_en_el_servidor "$AQUI"

# ---------------------------------------------------------------------
# 1. Respaldo
# ---------------------------------------------------------------------
if [[ ${1:-} != --sin-respaldo ]]; then
    paso "Respaldando antes de tocar nada"
    bash "$AQUI/respaldar.sh" | sed 's/^/    /'
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
# con datos reales nunca se disparan, asi que todo cambio de estructura
# posterior entra por db/migraciones/ y lo aplica migrar.sh.
#
# Antes esto solo avisaba y mandaba a aplicar el cambio a mano por
# phpMyAdmin. Eso funciona exactamente hasta la primera vez que alguien
# se salta el aviso, y entonces el sistema corre contra una base que no
# tiene las columnas que el codigo nuevo espera.
paso "Poniendo la base al dia"
if [[ -f "$AQUI/migrar.sh" ]]; then
    bash "$AQUI/migrar.sh" --ver

    if ! bash "$AQUI/migrar.sh"; then
        echo
        aviso "Las migraciones no terminaron."
        aviso "El codigo nuevo puede esperar columnas que la base no tiene."
        echo
        read -rp "    ¿Continuar de todas formas? [s/N] " r
        [[ ${r,,} == s ]] || exit 1
    fi
else
    aviso "No encontre migrar.sh. Revisa los cambios de db/ a mano."
fi

# Un cambio en 01_schema.sql sin migracion que lo acompanie es un
# descuido tipico: funciona en una base nueva y no en la del servidor.
if [[ -d .git ]] \
   && git diff --name-only HEAD@{1} HEAD 2>/dev/null | grep -q '^db/0[123]_'; then
    if ! git diff --name-only HEAD@{1} HEAD | grep -q '^db/migraciones/'; then
        echo
        aviso "${NEGRITA}Cambio el esquema pero no llego ninguna migracion.${FIN}"
        aviso "Una base nueva tendria el cambio; esta no. Revisa:"
        git diff --name-only HEAD@{1} HEAD | grep '^db/' | sed 's/^/        /'
        echo
        read -rp "    ¿Continuar? [s/N] " r
        [[ ${r,,} == s ]] || exit 1
    fi
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

    # ----------------------------------------------------------------
    # Las fotos de iPhone
    # ----------------------------------------------------------------
    # Esto existe por el 13 de octubre de 2026. Las .heic se dejaban
    # subir, la conversion fallaba y el archivo se guardaba tal cual: el
    # expediente quedo con fotos de hallazgos que no se ven en ningun
    # lado. La causa de fondo resulto ser que la imagen de PHP no se
    # habia reconstruido, y nada lo decia: la pantalla abria bien y el
    # fallo aparecia dias despues, en otro sitio.
    #
    # Levantar el sistema y preguntar si responde no basta. Si una pieza
    # del contenedor falta, el momento de enterarse es ahora —con el
    # despliegue recien hecho y alguien mirando— y no la semana que
    # viene con un paciente enfrente.
    HEIC="$(docker exec consultorio_app php -r '
        require "/var/www/html/includes/core/bootstrap.php";
        $como = null;
        $p = Imagen::porQueNoConvierteHeic($como);
        echo $p === null ? "ok|" . $como : "mal|" . $p;
    ' 2>/dev/null || echo 'mal|no se pudo preguntar')"

    if [[ ${HEIC%%|*} == ok ]]; then
        ok "fotos de iPhone: se convierten (${HEIC#*|})"
    else
        aviso "las fotos .heic NO se pueden convertir: ${HEIC#*|}"
        aviso "se van a RECHAZAR al subirlas, a proposito: guardarlas sin"
        aviso "convertir deja fotos que nadie puede ver despues."
    fi
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
