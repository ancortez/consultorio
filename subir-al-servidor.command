#!/usr/bin/env bash
# =====================================================================
#  Subir el proyecto al servidor
#
#  Se corre desde TU COMPUTADORA, con doble clic o desde la Terminal.
#  Sube el proyecto completo a /opt/consultorio, respetando la
#  estructura de carpetas.
#
#  Existe porque escribir las rutas a mano es facil de equivocar: un
#  archivo que aterriza en la carpeta de al lado deja el servidor
#  corriendo la version vieja sin que nada avise.
#
#  Uso:
#      ./subir-al-servidor.command
#      ./subir-al-servidor.command --probar   (dice que haria, sin hacerlo)
# =====================================================================

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

VERDE=$'\033[0;32m'; AMARILLO=$'\033[1;33m'; ROJO=$'\033[0;31m'
AZUL=$'\033[0;34m'; NEGRITA=$'\033[1m'; FIN=$'\033[0m'

paso()  { echo; echo "${AZUL}${NEGRITA}==> $*${FIN}"; }
ok()    { echo "    ${VERDE}ok${FIN}   $*"; }
aviso() { echo "    ${AMARILLO}~${FIN}    $*"; }
morir() { echo "    ${ROJO}!!${FIN}   $*" >&2; exit 1; }

# ---------------------------------------------------------------------
# A donde
# ---------------------------------------------------------------------
# Se guarda en un archivo para no tener que teclear la IP cada vez.
ARCHIVO_SERVIDOR=".servidor"

if [[ -f $ARCHIVO_SERVIDOR ]]; then
    SERVIDOR="$(cat "$ARCHIVO_SERVIDOR")"
else
    echo
    echo "${NEGRITA}  Primera vez${FIN}"
    echo "  ¿Cual es la direccion del servidor?"
    echo "  Ejemplo: root@75.119.155.144"
    echo
    read -rp "  Servidor: " SERVIDOR
    [[ -n $SERVIDOR ]] || morir "Sin servidor no se puede subir."
    echo "$SERVIDOR" > "$ARCHIVO_SERVIDOR"
    ok "guardado. La proxima vez ya no te lo pregunto."
fi

DESTINO="/opt/consultorio"
PROBAR=""
[[ ${1:-} == --probar ]] && PROBAR="--dry-run"

echo
echo "${NEGRITA}  Subir el Sistema Consultorio Dental${FIN}"
echo "  -----------------------------------"
echo "  Desde:  $(pwd)"
echo "  Hacia:  $SERVIDOR:$DESTINO"
[[ -n $PROBAR ]] && echo "  ${AMARILLO}Modo prueba: no se va a subir nada.${FIN}"
echo

# ---------------------------------------------------------------------
# Que no se suba
# ---------------------------------------------------------------------
# Tres cosas quedan fuera a proposito:
#
#   .env            el del servidor tiene SUS claves, generadas alla.
#                   Subir el de tu computadora las pisaria y el
#                   sistema dejaria de poder abrir su propia base.
#
#   storage/uploads archivos temporales, no hacen falta.
#
#   .git            pesa y no se necesita para correr.
EXCLUIR=(
    --exclude '.git'
    --exclude '.env'
    --exclude '.servidor'
    --exclude 'storage/uploads/*'
    --exclude 'storage/logs/*'
    --exclude 'storage/respaldos/*'
    --exclude '.DS_Store'
    --exclude 'node_modules'
    --exclude 'notas-temporales-sistema'
)

paso "Subiendo"

# Sobre los permisos, que es el error mas desconcertante de todos.
#
# En macOS los archivos del proyecto suelen quedar en 600 (solo el
# dueno lee). "rsync -a" los sube tal cual, porque -a incluye -p, que
# conserva permisos. Del otro lado, Apache corre como www-data: no es
# el dueno, asi que no puede leer ni un archivo. El navegador muestra
# "Permission denied" sobre un archivo que esta perfectamente ahi.
#
#   D755 = carpetas: el dueno escribe, todos entran y leen
#   F644 = archivos: el dueno escribe, todos leen
#
# --delete queda FUERA a proposito: borraria en el servidor cosas que
# no estan aqui, y ahi viven los respaldos y los .env.
# El rsync de macOS no es el mismo rsync de todo el mundo.
#
# Desde macOS 15, Apple reemplazo rsync por openrsync, y antes de eso
# traia un rsync 2.6.9 de 2006. Ninguno de los dos entiende
# --info=progress2, que es de rsync 3. El guion moria con un volcado de
# la ayuda de rsync y un mensaje mio que decia "¿la direccion del
# servidor es correcta?": la pista apuntaba al sitio equivocado.
#
# Asi que en vez de dar por hecho lo que soporta, se le pregunta. Las
# dos opciones son comodidades —ver el avance, ajustar permisos— y
# ninguna vale romper la subida.
AYUDA="$(rsync --help 2>&1 || true)"
OPCIONES=(-az)

if grep -q -- '--info=' <<<"$AYUDA"; then
    OPCIONES+=(--info=progress2)
else
    OPCIONES+=(--progress)
fi

# --chmod normaliza los permisos AL SUBIR. Si esta version no lo
# entiende no pasa nada: permisos.sh lo arregla del otro lado, y de
# hecho es quien manda. Esto solo ahorra un momento con los archivos
# a medio llegar.
if grep -q -- '--chmod' <<<"$AYUDA"; then
    OPCIONES+=(--chmod=D755,F644)
fi

rsync "${OPCIONES[@]}" $PROBAR "${EXCLUIR[@]}" \
      ./ "$SERVIDOR:$DESTINO/" \
    || morir "No se pudo subir.
         Si el error de arriba habla de una opcion que rsync no conoce,
         avisame con el texto: es cosa del guion, no tuya.
         Si habla de conexion, revisa la direccion del servidor; si
         cambiaste de servidor, borra el archivo .servidor."

[[ -n $PROBAR ]] && { echo; ok "Eso es lo que se subiria."; exit 0; }

ok "archivos subidos"

# Los permisos los deja permisos.sh, del lado del servidor.
#
# Antes esto repetia aqui media receta —el +x de los guiones y el 600
# de los .env— y se olvidaba de la otra mitad. Peor: dependia de que el
# --chmod del rsync hubiera funcionado, y acabamos de ver que ese
# --chmod puede no existir siquiera. permisos.sh hace las dos mitades y
# es el mismo que corren el instalador y el enlace con git.
ssh "$SERVIDOR" "bash $DESTINO/infra/produccion/permisos.sh $DESTINO" \
    2>/dev/null | sed 's/^/    /' \
    || aviso "no se pudieron acomodar los permisos; correlos alla:
         bash $DESTINO/infra/produccion/permisos.sh"

# ---------------------------------------------------------------------
# Comprobar que llego lo que tenia que llegar
# ---------------------------------------------------------------------
paso "Comprobando"

# sed y no "grep -oP": el grep de macOS es el de BSD y no tiene -P.
# Esta linea corre en la Mac, asi que siempre daba '?' y el guion no
# podia comparar versiones, que es justo para lo que existe.
VERSION_LOCAL="$(sed -n 's/.*VERSION_INSTALADOR="\([^"]*\)".*/\1/p' \
    infra/produccion/instalar-servidor.sh 2>/dev/null | head -1)"
VERSION_LOCAL="${VERSION_LOCAL:-?}"

VERSION_SERVIDOR="$(ssh "$SERVIDOR" \
    "sed -n 's/.*VERSION_INSTALADOR=\"\\([^\"]*\\)\".*/\\1/p' $DESTINO/infra/produccion/instalar-servidor.sh 2>/dev/null | head -1")"
VERSION_SERVIDOR="${VERSION_SERVIDOR:-?}"

if [[ $VERSION_LOCAL == "$VERSION_SERVIDOR" && $VERSION_LOCAL != '?' ]]; then
    ok "el instalador del servidor es la v$VERSION_SERVIDOR (la misma de aqui)"
else
    aviso "aqui hay v$VERSION_LOCAL y alla v$VERSION_SERVIDOR"
    aviso "Revisa que la direccion del servidor sea la correcta."
fi

# Que los archivos clave esten donde van, no en la carpeta de al lado.
paso "Revisando que nada haya aterrizado fuera de lugar"

FALTANTES=0
for archivo in \
    "infra/produccion/instalar-servidor.sh" \
    "infra/produccion/enlazar-git.sh" \
    "infra/produccion/permisos.sh" \
    "infra/produccion/docker-compose.produccion.yml" \
    "infra/produccion/Caddyfile" \
    "infra/minio/docker-compose.produccion.yml" \
    "config/config.php" \
    "index.php"
do
    if ssh "$SERVIDOR" "test -f $DESTINO/$archivo"; then
        ok "$archivo"
    else
        echo "    ${ROJO}!!${FIN}   falta $archivo"
        FALTANTES=$((FALTANTES + 1))
    fi
done

if (( FALTANTES > 0 )); then
    morir "Faltan $FALTANTES archivo(s). Algo salio mal en la subida."
fi

# ---------------------------------------------------------------------
# ¿Hace falta seguir subiendo a mano?
# ---------------------------------------------------------------------
# Subir por rsync funciona, pero cada version es volver a hacer esto.
# Con el servidor enlazado al repositorio, actualizar es un comando
# alla y nada aqui. Se avisa justo ahora, que es cuando se nota el
# trabajo repetido, y solo si de verdad no esta enlazado.
ENLAZADO=1
ssh "$SERVIDOR" "test -d $DESTINO/.git" 2>/dev/null || ENLAZADO=0

echo
echo "${VERDE}${NEGRITA}  Listo.${FIN}"
echo

if [[ $ENLAZADO -eq 0 ]]; then
    echo "  ${AMARILLO}Este servidor no esta enlazado con git.${FIN}"
    echo "  Por eso hay que subir los archivos a mano cada vez."
    echo
    echo "  Para no volver a hacerlo (respalda y no pierde los .env):"
    echo "      ${NEGRITA}ssh $SERVIDOR${FIN}"
    echo "      ${NEGRITA}cd $DESTINO/infra/produccion${FIN}"
    echo "      ${NEGRITA}./enlazar-git.sh${FIN}"
    echo
    echo "  Y de ahi en adelante, para cada version:  ./desplegar.sh"
    echo
    echo "  Antes de enlazar, asegurate de haber hecho ${NEGRITA}git push${FIN}:"
    echo "  el servidor va a tomar el codigo del repositorio, no de aqui."
else
    echo "  Ahora, en el servidor:"
    echo "      ${NEGRITA}ssh $SERVIDOR${FIN}"
    echo "      ${NEGRITA}cd $DESTINO/infra/produccion${FIN}"
    echo "      ${NEGRITA}./desplegar.sh${FIN}"
fi

echo
echo "  Si es la PRIMERA instalacion:  bash instalar-servidor.sh"
echo
echo "  Fijate que al arrancar diga ${NEGRITA}Version: v$VERSION_LOCAL${FIN}."
echo "  Si dice otra, es que esta corriendo un archivo viejo."
echo
