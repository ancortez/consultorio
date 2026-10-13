#!/usr/bin/env bash
# =====================================================================
#  ¿Por que no se convierten las fotos de iPhone?
#
#      bash tools/diagnostico_heic.sh
#
#  Esto no arregla nada: imprime los hechos de DENTRO del contenedor
#  para no tener que adivinarlos desde fuera.
#
#  Existe porque llevo tres intentos fallidos sobre el mismo fallo, y
#  los tres por lo mismo: deducir que estaba pasando a partir de un
#  mensaje en una pantalla. Cada vez que cambie algo, el mensaje cambio
#  y volvi a deducir mal. Preguntarle a la maquina cuesta diez segundos
#  y contesta de una vez.
# =====================================================================

set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

VERDE=$'\033[0;32m'; ROJO=$'\033[0;31m'; AMAR=$'\033[1;33m'
NEG=$'\033[1m'; FIN=$'\033[0m'

echo
echo "${NEG}  Diagnostico de las fotos .heic${FIN}"
echo "  ------------------------------"
echo

if ! docker compose ps app >/dev/null 2>&1; then
    echo "  ${ROJO}!!${FIN}  El contenedor no esta corriendo. Levantalo con:"
    echo "        docker compose up -d"
    echo
    exit 1
fi

# ---------------------------------------------------------------------
# 1. ¿La imagen es la de ahora?
# ---------------------------------------------------------------------
# La pregunta de fondo en todos los intentos anteriores. El codigo PHP
# esta montado desde la carpeta, asi que cambia sin reconstruir; el
# Dockerfile NO. Una imagen vieja con codigo nuevo es exactamente el
# estado en el que el conversor falta aunque el Dockerfile lo instale.
echo "${NEG}  1. La imagen de PHP${FIN}"

# Se le pregunta al CONTENEDOR por su imagen, no a "compose images".
#
# La primera version usaba "docker compose images app --format json" y
# le sacaba el ID con un grep. No funciono —contesto "no se pudo
# saber"— y justo esa era la pregunta que importaba, asi que el
# diagnostico dejo sin contestar lo unico que no se podia deducir desde
# fuera. Preguntarle al contenedor por su propia imagen funciona en
# cualquier version de compose.
IMAGEN="$(docker inspect consultorio_app --format '{{.Image}}' 2>/dev/null)"
CREADA=""
[[ -n ${IMAGEN:-} ]] && CREADA="$(docker image inspect "$IMAGEN" \
          --format '{{.Created}}' 2>/dev/null)"

# En segundos desde 1970, para poder compararlas de verdad en vez de
# dejarle la comparacion de dos textos a quien lee.
seg_de_fecha() {
    date -j -f '%Y-%m-%dT%H:%M:%S' "${1:0:19}" '+%s' 2>/dev/null \
        || date -d "$1" '+%s' 2>/dev/null || echo 0
}
# El -c de GNU va PRIMERO, y despues el -f de BSD.
#
# Al reves no funciona, y de una forma traicionera: "stat -f" existe en
# los dos, pero en GNU significa "informacion del SISTEMA DE ARCHIVOS".
# Asi que en Linux imprime un parrafo sobre bloques e inodos, sale con
# exito, y el "||" nunca llega a probar la otra forma. El resultado era
# una fecha que no era una fecha. En BSD/macOS, "stat -c" simplemente no
# existe y falla limpio, que es lo que hace util este orden.
seg_de_archivo() {
    local s
    s="$(stat -c %Y "$1" 2>/dev/null)" || s="$(stat -f %m "$1" 2>/dev/null)"
    [[ $s =~ ^[0-9]+$ ]] && echo "$s" || echo 0
}

IMG_SEG=$([[ -n $CREADA ]] && seg_de_fecha "$CREADA" || echo 0)
DOC_SEG=$(seg_de_archivo docker/php/Dockerfile)

echo "        imagen construida:  ${CREADA:-no se pudo saber}"
echo "        Dockerfile cambiado: $(date -r "$DOC_SEG" 2>/dev/null \
                                     || date -d "@$DOC_SEG" 2>/dev/null)"

if [[ $IMG_SEG -eq 0 ]]; then
    echo "        ${AMAR}~${FIN}  no se pudo comparar"
elif [[ $DOC_SEG -gt $IMG_SEG ]]; then
    echo
    echo "        ${ROJO}>>> FALTA RECONSTRUIR <<<${FIN}"
    echo "        El Dockerfile cambio DESPUES de construirse la imagen."
    echo "        El codigo PHP se actualiza solo (esta montado desde la"
    echo "        carpeta); la imagen NO. Corre:"
    echo
    echo "            ${NEG}docker compose build app && docker compose up -d${FIN}"
else
    echo "        ${VERDE}ok${FIN}  la imagen es posterior al Dockerfile"
fi
echo

# ---------------------------------------------------------------------
# 2. ¿Esta el programa?
# ---------------------------------------------------------------------
echo "${NEG}  2. El conversor${FIN}"
for p in heif-dec heif-convert; do
    RUTA="$(docker compose exec -T app sh -c "command -v $p" 2>/dev/null | tr -d '\r')"
    if [[ -n $RUTA ]]; then
        echo "        ${VERDE}ok${FIN}   $p -> $RUTA"
    else
        echo "        ${ROJO}--${FIN}   $p no esta"
    fi
done
echo

echo "${NEG}  3. Los paquetes de libheif instalados${FIN}"
docker compose exec -T app sh -c \
    "dpkg -l 2>/dev/null | grep -i heif || echo '(ninguno)'" \
    2>/dev/null | sed 's/^/        /'
echo

echo "${NEG}  4. Lo que trae el paquete de los programas${FIN}"
docker compose exec -T app sh -c \
    "dpkg -L libheif-examples 2>/dev/null | grep bin/ || echo '(el paquete no esta instalado)'" \
    2>/dev/null | sed 's/^/        /'
echo

# ---------------------------------------------------------------------
# 5. Lo que ve PHP
# ---------------------------------------------------------------------
# Apache puede darle a PHP un PATH distinto del de la shell. Si el
# programa esta y PHP no lo encuentra, la diferencia aparece aqui.
echo "${NEG}  5. Lo que ve PHP${FIN}"
docker compose exec -T app php -r '
    require "/var/www/html/includes/core/bootstrap.php";
    echo "        PATH:        " . (getenv("PATH") ?: "(vacio)") . "\n";
    echo "        proc_open:   " . (function_exists("proc_open") ? "si" : "NO") . "\n";
    echo "        GD:          " . (function_exists("imagecreatefromstring") ? "si" : "NO") . "\n";
    $como = null;
    $p = Imagen::porQueNoConvierteHeic($como);
    echo "        veredicto:   " . ($p === null ? "convierte bien" : $p) . "\n";
    echo "        como se supo: " . $como . "\n";
' 2>&1 | sed 's/^\s*//' | sed 's/^/        /' | sed 's/^        PATH/PATH/' | sed 's/^/  /'
echo

echo "${NEG}  6. Que hacer con lo de arriba${FIN}"
echo "        · Si el Dockerfile es mas nuevo que la imagen:"
echo "              docker compose build app && docker compose up -d"
echo "        · Si NINGUN paquete de libheif aparece en el punto 3:"
echo "              la construccion no lo instalo. Mandame el punto 3 y el 4."
echo "        · Si el programa SI esta (punto 2) y PHP dice que no:"
echo "              es el PATH del punto 5. Mandamelo."
echo
