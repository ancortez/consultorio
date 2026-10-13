#!/usr/bin/env bash
# =====================================================================
#  Dejar los permisos como el servidor web los necesita
#
#      ./permisos.sh [carpeta]       (por omision, la raiz del proyecto)
#
#  Lo usan el instalador y el enlace con git. Esta aparte porque es la
#  misma receta en los dos sitios, y una receta repetida en dos
#  archivos es una receta que un dia dice dos cosas distintas.
#
#  Hay DOS duenos a proposito:
#
#    - el codigo pertenece al usuario del servidor y solo se lee
#    - storage/ pertenece a www-data (uid 33 DENTRO del contenedor),
#      porque quien escribe ahi es Apache, no tu
# =====================================================================

set -euo pipefail

DESTINO="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
USUARIO="${SUDO_USER:-$(id -un)}"

VERDE=$'\033[0;32m'; AMARILLO=$'\033[1;33m'; FIN=$'\033[0m'
ok() { echo "    ${VERDE}ok${FIN}   $*"; }

[[ -d $DESTINO ]] || { echo "No existe $DESTINO" >&2; exit 1; }

chown -R "$USUARIO:$USUARIO" "$DESTINO" 2>/dev/null || true

# Apache corre DENTRO del contenedor como www-data, que no es el dueno
# de estos archivos. Si el proyecto llego con permisos restrictivos
# —pasa siempre que se sube desde una Mac con rsync -a, y tambien
# cuando git escribe con un umask cerrado— Apache no puede leer ni
# index.php, y el navegador muestra "Permission denied" sobre archivos
# que estan perfectamente ahi.
find "$DESTINO" -type d -not -path '*/.git/*' -exec chmod 755 {} +
find "$DESTINO" -type f -not -path '*/.git/*' -exec chmod 644 {} +
ok "codigo legible para el servidor web"

chmod +x "$DESTINO"/infra/produccion/*.sh 2>/dev/null || true
chmod +x "$DESTINO"/*.command 2>/dev/null || true
ok "los guiones vuelven a ser ejecutables"

mkdir -p "$DESTINO/storage/logs" "$DESTINO/storage/uploads"
chmod -R 775 "$DESTINO/storage"

# El chown a www-data necesita root. Si no se puede, se AVISA en vez de
# detener el guion: el resto de los permisos ya quedaron bien y vale
# mas terminar con un aviso claro que dejarlo todo a medias. Pero se
# avisa de verdad, porque sin esto la aplicacion no puede escribir y el
# sintoma —no se suben documentos— no se parece a la causa.
if chown -R 33:33 "$DESTINO/storage" 2>/dev/null; then
    ok "storage escribible por el servidor web"
else
    echo "    ${AMARILLO:-}~${FIN}    no se pudo dar storage/ a www-data (uid 33)."
    echo "         Corre esto como root o el sistema no podra guardar archivos:"
    echo "             sudo chown -R 33:33 $DESTINO/storage"
fi

# Los .env traen las contrasenias de la base. El chmod general de
# arriba los dejo en 644; vuelven a 600.
chmod 600 "$DESTINO/infra/produccion/.env" "$DESTINO/infra/minio/.env" 2>/dev/null || true
ok "los .env en 600"
