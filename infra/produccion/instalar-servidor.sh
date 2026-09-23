#!/usr/bin/env bash
# =====================================================================
#  Instalador del servidor - Sistema Consultorio Dental
#
#  Deja un servidor Ubuntu limpio listo para correr el sistema:
#  usuario sin privilegios, Docker, cortafuegos, claves generadas al
#  azar y todo arriba con HTTPS.
#
#  Se corre UNA vez, como root, en un servidor recien creado:
#
#      bash instalar-servidor.sh
#
#  Es idempotente: si algo falla a la mitad, se puede volver a correr
#  sin romper lo que ya quedo hecho.
# =====================================================================

set -euo pipefail

# ---------------------------------------------------------------------
# Colores y ayudas
# ---------------------------------------------------------------------
ROJO=$'\033[0;31m'; VERDE=$'\033[0;32m'; AMARILLO=$'\033[1;33m'
AZUL=$'\033[0;34m'; NEGRITA=$'\033[1m'; FIN=$'\033[0m'

paso()  { echo; echo "${AZUL}${NEGRITA}==> $*${FIN}"; }
ok()    { echo "    ${VERDE}ok${FIN}   $*"; }
aviso() { echo "    ${AMARILLO}~${FIN}    $*"; }
error() { echo "    ${ROJO}!!${FIN}   $*" >&2; }

morir() { error "$*"; exit 1; }

# ---------------------------------------------------------------------
# Revisiones antes de tocar nada
# ---------------------------------------------------------------------
[[ $EUID -eq 0 ]] || morir "Este instalador se corre como root. Usa: sudo bash $0"

if ! grep -qi ubuntu /etc/os-release 2>/dev/null; then
    aviso "Esto se probo en Ubuntu. En otra distribucion puede fallar."
    read -rp "    ¿Continuar de todas formas? [s/N] " r
    [[ ${r,,} == s ]] || exit 1
fi

USUARIO="${USUARIO_SISTEMA:-consultorio}"
DESTINO="/opt/consultorio"

# Este guion vive dentro del proyecto, asi que su propia ubicacion dice
# donde esta el codigo. Es la senial mas confiable que hay: no depende
# de que exista .git ni de que alguien defina una variable.
AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ORIGEN="$(cd "$AQUI/../.." && pwd)"

echo
echo "${NEGRITA}  Instalador del Sistema Consultorio Dental${FIN}"
echo "  ----------------------------------------"
echo "  Servidor:  $(hostname)"
echo "  Usuario:   $USUARIO"
echo "  Carpeta:   $DESTINO"
echo

# ---------------------------------------------------------------------
# 1. ¿Donde esta el codigo?
#
# Esto se revisa ANTES de tocar el sistema. La version anterior lo
# revisaba al final y fallaba despues de diez minutos de trabajo, con
# todo a medio instalar. Una revision que puede fallar va primero.
#
# El codigo se reconoce por sus archivos, no por .git: la forma normal
# de subirlo es con rsync excluyendo .git, y exigir .git hacia que el
# instalador no reconociera un proyecto que estaba perfectamente ahi.
# ---------------------------------------------------------------------
paso "Buscando el sistema"

es_el_proyecto() {
    [[ -f "$1/index.php" && -f "$1/config/config.php" \
       && -d "$1/infra/produccion" ]]
}

if es_el_proyecto "$DESTINO"; then
    # Ya esta donde debe. Da igual como haya llegado.
    ok "el sistema ya esta en $DESTINO"
    [[ -d "$DESTINO/.git" ]] && ok "y es un repositorio git (se puede actualizar solo)"

elif es_el_proyecto "$ORIGEN"; then
    # El instalador se corrio desde una copia en otro lado. Se mueve.
    ok "el sistema esta en $ORIGEN"
    echo "    Se va a copiar a $DESTINO, que es donde tiene que vivir."
    mkdir -p "$DESTINO"
    cp -a "$ORIGEN/." "$DESTINO/"
    ok "copiado a $DESTINO"

elif [[ -n ${REPO_GIT:-} ]]; then
    if [[ -e $DESTINO && -n "$(ls -A "$DESTINO" 2>/dev/null)" ]]; then
        error "$DESTINO ya tiene archivos, pero no parecen ser el sistema."
        echo
        echo "    Ahi dentro hay:"
        ls -A "$DESTINO" | head -8 | sed 's/^/        /'
        echo
        echo "    Revisa que sea, y si no sirve, borralo:"
        echo "        rm -rf $DESTINO"
        echo "    Luego vuelve a correr este instalador."
        exit 1
    fi

    mkdir -p "$DESTINO"
    git clone "$REPO_GIT" "$DESTINO" || morir "No se pudo clonar $REPO_GIT"
    ok "clonado desde $REPO_GIT"

    es_el_proyecto "$DESTINO" \
        || morir "Lo clonado no parece el sistema. ¿Es el repositorio correcto?"

else
    error "No encontre el sistema."
    echo
    echo "    Se busco en:"
    echo "        $DESTINO"
    echo "        $ORIGEN"
    echo
    echo "    Sube el proyecto y vuelve a correr el instalador."
    echo
    echo "    ${NEGRITA}Con git${FIN} (en el servidor):"
    echo "        export REPO_GIT=https://github.com/tuusuario/consultorio.git"
    echo "        bash \$0"
    echo
    echo "    ${NEGRITA}Sin git${FIN} (desde tu computadora):"
    echo "        rsync -avz --exclude '.git' --exclude '.env' \\"
    echo "              --exclude 'storage/uploads' \\"
    echo "              ./ root@TU_IP:$DESTINO/"
    exit 1
fi

# ---------------------------------------------------------------------
# 2. Datos que hay que preguntar
# ---------------------------------------------------------------------
paso "Datos del sistema"

leer() {
    local variable="$1" pregunta="$2" obligatorio="${3:-si}" valor=""
    while true; do
        read -rp "    ${pregunta}: " valor
        valor="${valor// /}"
        if [[ -n $valor || $obligatorio == no ]]; then
            printf -v "$variable" '%s' "$valor"
            return
        fi
        error "Este dato es obligatorio."
    done
}

leer DOMINIO "Dominio del sistema (ej. consultorio.midominio.com)"
leer CORREO  "Tu correo (para avisos del certificado)"

echo
echo "    La consola de MinIO deja ver y descargar TODOS los documentos"
echo "    del expediente. Mientras no la necesites desde fuera, es mas"
echo "    seguro dejarla sin publicar y llegarle por tunel SSH."
read -rp "    ¿Publicar la consola de MinIO con su propio dominio? [s/N] " PUB_MINIO

DOMINIO_MINIO=""
if [[ ${PUB_MINIO,,} == s ]]; then
    leer DOMINIO_MINIO "Dominio para la consola (ej. archivos.midominio.com)"
fi

# Verificacion del DNS ANTES de instalar nada. Si el dominio no apunta
# todavia, Let's Encrypt va a fallar y el usuario se queda con medio
# servidor montado sin entender por que.
paso "Revisando que el dominio apunte aqui"

IP_SERVIDOR="$(curl -fsS --max-time 10 https://api.ipify.org || echo '')"
IP_DOMINIO="$(getent hosts "$DOMINIO" 2>/dev/null | awk '{print $1}' | head -1 || echo '')"

if [[ -z $IP_SERVIDOR ]]; then
    aviso "No se pudo averiguar la IP publica; me salto la revision."
elif [[ -z $IP_DOMINIO ]]; then
    aviso "$DOMINIO todavia no resuelve a ninguna IP."
    echo "    Crea un registro A: $DOMINIO -> $IP_SERVIDOR"
    echo "    Los cambios de DNS tardan de 5 minutos a un par de horas."
    read -rp "    ¿Continuar igual? El HTTPS fallara hasta que propague [s/N] " r
    [[ ${r,,} == s ]] || exit 1
elif [[ $IP_DOMINIO != "$IP_SERVIDOR" ]]; then
    aviso "$DOMINIO apunta a $IP_DOMINIO, pero este servidor es $IP_SERVIDOR."
    read -rp "    ¿Continuar igual? [s/N] " r
    [[ ${r,,} == s ]] || exit 1
else
    ok "$DOMINIO apunta correctamente a $IP_SERVIDOR"
fi

# ---------------------------------------------------------------------
# 3. Sistema al dia
# ---------------------------------------------------------------------
paso "Actualizando el sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq
apt-get install -y -qq ca-certificates curl gnupg git ufw fail2ban \
                       unattended-upgrades openssl >/dev/null
ok "paquetes al dia"

# Las actualizaciones de seguridad se instalan solas. Un servidor que
# nadie parcha es cuestion de tiempo.
dpkg-reconfigure -f noninteractive unattended-upgrades >/dev/null 2>&1 || true
ok "actualizaciones de seguridad automaticas"

# ---------------------------------------------------------------------
# 4. Usuario sin privilegios
# ---------------------------------------------------------------------
paso "Usuario de trabajo"

if id "$USUARIO" &>/dev/null; then
    ok "el usuario $USUARIO ya existia"
else
    adduser --disabled-password --gecos "" "$USUARIO" >/dev/null
    ok "usuario $USUARIO creado"
fi

usermod -aG sudo "$USUARIO"

# Se le copian las llaves SSH de root para que pueda entrar igual.
if [[ -f /root/.ssh/authorized_keys ]]; then
    install -d -m 700 -o "$USUARIO" -g "$USUARIO" "/home/$USUARIO/.ssh"
    install -m 600 -o "$USUARIO" -g "$USUARIO" \
        /root/.ssh/authorized_keys "/home/$USUARIO/.ssh/authorized_keys"
    ok "llaves SSH copiadas a $USUARIO"
else
    aviso "root no tiene llaves SSH. Ponle contrasenia a $USUARIO:"
    passwd "$USUARIO"
fi

# ---------------------------------------------------------------------
# 5. Docker
# ---------------------------------------------------------------------
paso "Docker"

if command -v docker &>/dev/null; then
    ok "Docker ya estaba instalado"
else
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
        | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
        > /etc/apt/sources.list.d/docker.list

    apt-get update -qq
    apt-get install -y -qq docker-ce docker-ce-cli containerd.io \
                           docker-buildx-plugin docker-compose-plugin >/dev/null
    ok "Docker instalado"
fi

usermod -aG docker "$USUARIO"
systemctl enable --now docker >/dev/null 2>&1 || true

# La red compartida con MinIO. Se crea una sola vez.
docker network create sistemas >/dev/null 2>&1 && ok "red 'sistemas' creada" \
    || ok "la red 'sistemas' ya existia"

# ---------------------------------------------------------------------
# 6. Cortafuegos
# ---------------------------------------------------------------------
paso "Cortafuegos"

ufw --force reset >/dev/null
ufw default deny incoming  >/dev/null
ufw default allow outgoing >/dev/null
ufw allow 22/tcp  comment 'SSH'   >/dev/null
ufw allow 80/tcp  comment 'HTTP'  >/dev/null
ufw allow 443/tcp comment 'HTTPS' >/dev/null
ufw --force enable >/dev/null

ok "solo abiertos 22 (SSH), 80 y 443 (web)"

# fail2ban banea a quien insiste con contrasenias equivocadas por SSH.
systemctl enable --now fail2ban >/dev/null 2>&1 || true
ok "fail2ban vigilando los intentos de SSH"

# Docker se salta ufw al publicar puertos, por eso en el compose de
# produccion todo lo que no es Caddy va atado a 127.0.0.1. Se avisa
# para que nadie agregue un 'ports:' suelto mas adelante sin saberlo.
aviso "Docker puede saltarse el cortafuegos: nunca publiques un puerto"
aviso "sin el 127.0.0.1: delante. Revisa el compose antes de cambiarlo."

# ---------------------------------------------------------------------
# 7. Claves
# ---------------------------------------------------------------------
paso "Generando claves"

chown -R "$USUARIO:$USUARIO" "$DESTINO"

# openssl con base64 y sin caracteres raros: las contrasenias van a
# viajar por archivos .env y lineas de comando, y un '$' o una comilla
# en medio rompe cosas de formas dificiles de diagnosticar.
clave() { openssl rand -base64 32 | tr -d '/+=\n' | cut -c1-28; }

ENV_APP="$DESTINO/infra/produccion/.env"
ENV_MINIO="$DESTINO/infra/minio/.env"

if [[ -f $ENV_APP ]]; then
    aviso "Ya existe $ENV_APP: se conserva, no se tocan las claves."
else
    DB_PASS="$(clave)"
    DB_ROOT="$(clave)"
    MINIO_USER="consultorio"
    MINIO_PASS="$(clave)"

    URL_CONSOLA="http://localhost:${MINIO_PORT_CONSOLA:-9101}"
    [[ -n $DOMINIO_MINIO ]] && URL_CONSOLA="https://$DOMINIO_MINIO"

    cat > "$ENV_APP" <<ENVFIN
# =====================================================================
#  Generado por instalar-servidor.sh el $(date '+%d/%m/%Y a las %H:%M')
#
#  ESTE ARCHIVO TIENE LAS CONTRASENIAS. No lo subas a git, no lo
#  mandes por correo y guarda una copia en tu gestor de contrasenias:
#  si se pierde y no tienes respaldo, se pierde el acceso a la base.
# =====================================================================

DOMINIO=$DOMINIO
CORREO_CERTIFICADO=$CORREO
APP_TZ=America/Mazatlan

DB_NAME=consultorio_dental
DB_USER=consultorio
DB_PASS=$DB_PASS
DB_ROOT_PASS=$DB_ROOT

MINIO_ENDPOINT=http://minio:9000
MINIO_BUCKET=consultorio-dental
MINIO_LLAVE=$MINIO_USER
MINIO_SECRETO=$MINIO_PASS
ENVFIN

    cat > "$ENV_MINIO" <<ENVFIN
# Generado por instalar-servidor.sh el $(date '+%d/%m/%Y a las %H:%M')
#
# Tienen que ser LOS MISMOS valores que MINIO_LLAVE y MINIO_SECRETO
# del .env de produccion: son las credenciales con que el sistema
# guarda y lee los documentos.
MINIO_USUARIO=$MINIO_USER
MINIO_PASSWORD=$MINIO_PASS
MINIO_BUCKETS=consultorio-dental
MINIO_PORT_CONSOLA=9101
MINIO_URL_CONSOLA=$URL_CONSOLA
ENVFIN

    chmod 600 "$ENV_APP" "$ENV_MINIO"
    chown "$USUARIO:$USUARIO" "$ENV_APP" "$ENV_MINIO"
    ok "claves generadas al azar y guardadas con permiso 600"
fi

# ---------------------------------------------------------------------
# 8. Consola de MinIO, si se pidio publicarla
# ---------------------------------------------------------------------
EXTRA="$DESTINO/infra/produccion/extra"
mkdir -p "$EXTRA"

if [[ -n $DOMINIO_MINIO ]]; then
    sed "s|DOMINIO_MINIO_AQUI|$DOMINIO_MINIO|" \
        "$DESTINO/infra/produccion/minio.caddy.ejemplo" > "$EXTRA/minio.caddy"
    ok "la consola de MinIO se publicara en https://$DOMINIO_MINIO"
else
    rm -f "$EXTRA/minio.caddy"
    ok "la consola de MinIO queda privada (tunel SSH)"
fi
chown -R "$USUARIO:$USUARIO" "$EXTRA"

# ---------------------------------------------------------------------
# 9. Arranque
# ---------------------------------------------------------------------
paso "Levantando MinIO"
sudo -u "$USUARIO" bash -c "cd '$DESTINO/infra/minio' && \
    docker compose -f docker-compose.produccion.yml up -d" \
    || morir "MinIO no levanto. Revisa: docker compose logs"
ok "MinIO arriba"

paso "Levantando el sistema"
sudo -u "$USUARIO" bash -c "cd '$DESTINO/infra/produccion' && \
    docker compose -f docker-compose.produccion.yml up -d --build" \
    || morir "El sistema no levanto. Revisa: docker compose logs"
ok "sistema arriba"

# ---------------------------------------------------------------------
# 10. Respaldos automaticos
# ---------------------------------------------------------------------
paso "Respaldos automaticos"

install -d -o "$USUARIO" -g "$USUARIO" /var/respaldos/consultorio

cat > /etc/cron.d/consultorio-respaldo <<CRONFIN
# Respaldo diario del Sistema Consultorio Dental.
# 2:30 de la madrugada: nadie esta usando el sistema a esa hora.
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
30 2 * * * $USUARIO $DESTINO/infra/produccion/respaldar.sh >> /var/log/consultorio-respaldo.log 2>&1
CRONFIN

chmod 644 /etc/cron.d/consultorio-respaldo
ok "respaldo diario a las 2:30 AM en /var/respaldos/consultorio"

# ---------------------------------------------------------------------
# Listo
# ---------------------------------------------------------------------
echo
echo "${VERDE}${NEGRITA}  =====================================================${FIN}"
echo "${VERDE}${NEGRITA}   Listo. El sistema esta en linea.${FIN}"
echo "${VERDE}${NEGRITA}  =====================================================${FIN}"
echo
echo "  Sistema:       ${NEGRITA}https://$DOMINIO${FIN}"
[[ -n $DOMINIO_MINIO ]] && \
echo "  Archivos:      ${NEGRITA}https://$DOMINIO_MINIO${FIN}"
echo "  Usuario:       admin"
echo "  Contrasenia:   Admin123!   ${AMARILLO}(cambiala en el primer acceso)${FIN}"
echo
echo "  ${NEGRITA}Lo primero que tienes que hacer:${FIN}"
echo "   1. Entrar y cambiar la contrasenia de admin."
echo "   2. Guardar una copia de $ENV_APP en tu gestor de contrasenias."
echo "   3. Probar que un respaldo se restaura:"
echo "        $DESTINO/infra/produccion/respaldar.sh"
echo "        $DESTINO/infra/produccion/restaurar.sh --listar"
echo
echo "  ${NEGRITA}Para entrar a phpMyAdmin${FIN} (desde tu computadora):"
echo "     ssh -L 8081:localhost:8081 $USUARIO@${IP_SERVIDOR:-TU_IP}"
echo "     y abre http://localhost:8081"
echo
if [[ -z $DOMINIO_MINIO ]]; then
echo "  ${NEGRITA}Para entrar a la consola de MinIO:${FIN}"
echo "     ssh -L 9101:localhost:9101 $USUARIO@${IP_SERVIDOR:-TU_IP}"
echo "     y abre http://localhost:9101"
echo
fi
echo "  El certificado HTTPS puede tardar un minuto en emitirse."
echo "  Si ves un aviso de seguridad, espera y recarga."
echo
