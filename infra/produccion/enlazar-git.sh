#!/usr/bin/env bash
# =====================================================================
#  Enlazar con git una carpeta que se subio a mano
#
#      ./enlazar-git.sh
#
#  La primera instalacion se subio con rsync, asi que /opt/consultorio
#  tiene los archivos pero no el repositorio. desplegar.sh necesita un
#  "git pull" para traer los cambios, y sin repositorio se salta ese
#  paso y avisa que hay que subirlos a mano.
#
#  Esto lo convierte en un repositorio de verdad SIN perder nada de lo
#  que hay en el servidor.
#
#  Lo que NO se toca, y por que
#  ----------------------------
#  git reset --hard solo pisa archivos que el repositorio conoce. Los
#  que no estan en el repositorio —y estan todos en .gitignore— se
#  quedan donde estan:
#
#      infra/produccion/.env      las contrasenias de la base
#      infra/minio/.env           las llaves del almacen
#      storage/                   logs y archivos subidos
#      *.sql.gz, *.tar.gz.enc     los respaldos
#
#  Aun asi, el guion respalda antes. Esto se hace una vez sobre un
#  servidor con expedientes dentro, y "estoy casi seguro" no es
#  suficiente para eso.
# =====================================================================

set -euo pipefail

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ="$(cd "$AQUI/../.." && pwd)"
REPO="${REPO:-https://github.com/ancortez/consultorio.git}"
RAMA="${RAMA:-main}"

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

echo
echo "${NEGRITA}  Enlazar $RAIZ con git${FIN}"
echo "  ------------------------------------------"
echo "  Repositorio: $REPO"
echo "  Rama:        $RAMA"
echo

command -v git >/dev/null || morir "git no esta instalado: apt install -y git"

# ---------------------------------------------------------------------
# ¿Ya estaba enlazado?
# ---------------------------------------------------------------------
if [[ -d .git ]]; then
    ok "esta carpeta YA es un repositorio"
    echo "        remoto actual: $(git remote get-url origin 2>/dev/null || echo 'ninguno')"
    echo
    aviso "No hay nada que enlazar. Para traer cambios: ./desplegar.sh"
    exit 0
fi

# ---------------------------------------------------------------------
# 1. Respaldo
# ---------------------------------------------------------------------
paso "Respaldando antes de tocar nada"
if [[ -f "$AQUI/respaldar.sh" ]]; then
    bash "$AQUI/respaldar.sh" | sed 's/^/    /' || aviso "el respaldo fallo"
else
    aviso "no encontre respaldar.sh"
fi

# Y una copia de los .env aparte, por si acaso. Son cuatro lineas de
# texto y son lo unico verdaderamente irrecuperable de esta carpeta:
# sin ellos, la base de datos queda inaccesible.
# En el HOME de quien corre esto, no en /root a secas: si el guion se
# corre con un usuario normal, el mkdir falla y —con set -e— se lleva
# por delante el guion entero antes de haber hecho nada. Lo descubri
# probandolo; en el servidor corre como root y habria funcionado, que
# es justo como estas cosas pasan desapercibidas hasta el dia que no.
COPIA="${HOME:-/tmp}/env-antes-de-git-$(date +%Y%m%d-%H%M%S)"
if mkdir -p "$COPIA" 2>/dev/null; then
    cp -a infra/produccion/.env "$COPIA/produccion.env" 2>/dev/null || true
    cp -a infra/minio/.env      "$COPIA/minio.env"      2>/dev/null || true
    chmod 700 "$COPIA" 2>/dev/null || true
    ok "copia de los .env en $COPIA"
else
    COPIA="(no se pudo crear)"
    aviso "no se pudo copiar los .env aparte; el respaldo de arriba sigue valiendo"
fi

# ---------------------------------------------------------------------
# 2. Enlazar
# ---------------------------------------------------------------------
paso "Creando el repositorio"

git init -q
git remote add origin "$REPO"

# safe.directory: git se niega a trabajar en una carpeta de otro dueno,
# y aqui los archivos son del usuario mientras el guion puede correr
# como root. El aviso que da es largo y no explica que hacer.
git config --global --add safe.directory "$RAIZ" 2>/dev/null || true

paso "Bajando el repositorio"
if ! git fetch --depth 1 origin "$RAMA" 2>&1 | sed 's/^/    /'; then
    echo
    morir "No se pudo bajar de $REPO.

        Si el repositorio es PRIVADO, git necesita credenciales. La
        forma limpia en un servidor es una llave de despliegue:

          1. En el servidor:
                 ssh-keygen -t ed25519 -C 'vps-consultorio' -f ~/.ssh/github -N ''
                 cat ~/.ssh/github.pub

          2. En GitHub: el repositorio > Settings > Deploy keys >
             Add deploy key. Pega lo que salio y DEJA sin marcar
             'Allow write access': el servidor solo necesita leer.

          3. Aqui:
                 printf 'Host github.com\\n  IdentityFile ~/.ssh/github\\n' >> ~/.ssh/config
                 REPO=git@github.com:ancortez/consultorio.git ./enlazar-git.sh"
fi

# ---------------------------------------------------------------------
# 3. Apuntar los archivos de aqui al repositorio
# ---------------------------------------------------------------------
# reset --hard y no checkout: la carpeta ya tiene los archivos, y lo
# que se quiere es declararlos iguales a los del repositorio. Lo que el
# repositorio no conoce (.env, storage) ni se mira.
paso "Alineando los archivos con el repositorio"

ANTES="$(grep -oP "APP_VERSION', '\K[^']+" config/config.php 2>/dev/null || echo '?')"

git reset --hard "origin/$RAMA" >/dev/null \
    || morir "No se pudo alinear con origin/$RAMA"
git branch -u "origin/$RAMA" "$RAMA" 2>/dev/null \
    || git checkout -B "$RAMA" --track "origin/$RAMA" >/dev/null 2>&1 || true

AHORA="$(grep -oP "APP_VERSION', '\K[^']+" config/config.php 2>/dev/null || echo '?')"
ok "version $ANTES -> ${NEGRITA}$AHORA${FIN}"

# ---------------------------------------------------------------------
# 4. Comprobar que no se perdio lo que no debia
# ---------------------------------------------------------------------
paso "Comprobando lo que NO debia tocarse"

FALTA=0
for a in infra/produccion/.env infra/minio/.env; do
    if [[ -f $a ]]; then
        ok "$a sigue ahi"
    else
        echo "    ${ROJO}!!${FIN}   $a NO esta. Recuperalo de $COPIA"
        FALTA=1
    fi
done
[[ -d storage ]] && ok "storage/ sigue ahi" || aviso "storage/ no esta"

[[ $FALTA -eq 1 ]] && morir "Falta un .env. No sigas hasta recuperarlo."

# ---------------------------------------------------------------------
# 5. Permisos
# ---------------------------------------------------------------------
# git escribe los archivos con el umask de quien corre el guion, asi
# que despues de un reset --hard hay que volver a dejarlos como Apache
# los necesita. Saltarse esto da un "Permission denied" sobre archivos
# que estan perfectamente ahi.
paso "Acomodando permisos"
bash "$AQUI/permisos.sh" "$RAIZ"

echo
echo "${VERDE}${NEGRITA}  Enlazado.${FIN} De aqui en adelante, para actualizar:"
echo
echo "      cd $AQUI && ./desplegar.sh"
echo
