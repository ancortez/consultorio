#!/usr/bin/env bash
# =====================================================================
#  Bajar los respaldos del servidor a esta computadora
#
#  Se corre desde TU COMPUTADORA, con doble clic o desde la Terminal.
#
#      ./bajar-respaldos.command
#      ./bajar-respaldos.command --instalar    deja que corra solo
#      ./bajar-respaldos.command --quitar      deja de correr solo
#      ./bajar-respaldos.command --ver         ¿esta instalado?
#
#  Por que existe
#  --------------
#  Esta es la respuesta a "¿que pasa si se va el servidor?". Los
#  respaldos que hace el VPS cada noche viven en el MISMO disco que el
#  sistema: si el proveedor tiene un problema serio, si una factura se
#  queda sin pagar, si el disco muere, se van los dos juntos. El
#  respaldo que esta al lado de lo que respalda no es un respaldo, es
#  una copia.
#
#  Lo que lo arregla es tener las copias en otro sitio fisico, y la
#  forma que de verdad se sostiene en el tiempo es que no dependa de
#  acordarse: esta computadora las JALA sola.
#
#  Por que JALA y no el servidor EMPUJA
#  ------------------------------------
#  Es la decision importante del guion y vale explicarla.
#
#  Si el servidor empujara las copias hacia aqui, necesitaria una llave
#  para entrar a esta computadora. Entonces, quien tome el control del
#  servidor —y es la maquina expuesta a internet— entra tambien aqui, y
#  lo primero que hace cualquier programa de secuestro de datos es
#  buscar los respaldos y cifrarlos. Las copias de fuera dejarian de
#  ser copias de fuera.
#
#  Jalando, la llave vive aqui y apunta hacia alla. El servidor no sabe
#  que esta computadora existe y no puede tocar nada de este lado. Lo
#  peor que puede pasar es que un dia no haya nada que bajar.
#
#  Que baja y que no
#  -----------------
#  Baja TODO lo que haya en /var/respaldos/consultorio: los respaldos de
#  la base (chicos, de cada noche) y los de los archivos del expediente
#  (grandes, de los domingos). Usa rsync, asi que lo que ya esta no se
#  vuelve a bajar: la primera vez tarda, las siguientes son segundos.
#
#  Y NO borra nada de aqui. El servidor rota sus respaldos —14 dias de
#  base, 4 copias de archivos— y si esto los espejara, borraria de aqui
#  justo lo que el servidor ya olvido. Lo valioso de la copia de fuera
#  es precisamente que llega mas atras.
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
#  A donde y desde donde
# ---------------------------------------------------------------------
# El servidor se guarda en el mismo archivo que usa subir-al-servidor:
# es el mismo servidor y teclear la IP dos veces es teclearla mal una.
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
    [[ -n $SERVIDOR ]] || morir "Sin servidor no se puede bajar nada."
    echo "$SERVIDOR" > "$ARCHIVO_SERVIDOR"
    ok "guardado. La proxima vez ya no te lo pregunto."
fi

ORIGEN="${RESPALDOS_SERVIDOR:-/var/respaldos/consultorio}"

# ---------------------------------------------------------------------
#  Donde se guardan aqui
# ---------------------------------------------------------------------
# En ~/Respaldos-Nanai y NO dentro del proyecto. Dos razones, las dos
# importantes:
#
#   - el proyecto esta en git, y un respaldo con el expediente clinico
#     de todos los pacientes no puede acabar en un repositorio por un
#     "git add -A" distraido. Esta en .gitignore, y una carpeta fuera
#     no depende de que el .gitignore siga bien escrito.
#
#   - la carpeta del proyecto se borra y se vuelve a clonar sin pensar.
#     Los respaldos no.
DESTINO="${RESPALDOS_LOCALES:-$HOME/Respaldos-Nanai}"

# ---------------------------------------------------------------------
#  El respaldo mas reciente de la base
# ---------------------------------------------------------------------
# Se ordena por NOMBRE y no por fecha del archivo.
#
# Los nombres traen la fecha dentro —base-20261017-0230— asi que
# ordenarlos alfabeticamente los ordena cronologicamente, y eso no
# depende de nada. Por fecha del archivo si depende: rsync conserva las
# fechas, pero arrastrar la carpeta a un disco externo, meterla en un
# zip o copiarla sin -p las pone todas iguales, y entonces "el mas
# reciente" sale al azar. Me paso probando este mismo guion.
mas_reciente() {
    find "$DESTINO" -type f -name 'base-*' 2>/dev/null | sort -r | head -1
}

ETIQUETA="dev.nierika.nanai.respaldos"
PLIST="$HOME/Library/LaunchAgents/$ETIQUETA.plist"
BITACORA="$HOME/Library/Logs/nanai-respaldos.log"

# ---------------------------------------------------------------------
#  --ver
# ---------------------------------------------------------------------
if [[ ${1:-} == --ver ]]; then
    echo
    echo "${NEGRITA}  Copia automatica en esta computadora${FIN}"
    echo "  servidor: $SERVIDOR:$ORIGEN"
    echo "  destino:  $DESTINO"
    echo

    if [[ -f $PLIST ]]; then
        ok "esta instalada: corre todos los dias a las 20:30"
        echo "         $PLIST"
    else
        aviso "NO esta instalada: las copias solo se bajan cuando corres esto"
        echo "         para instalarla:  ./bajar-respaldos.command --instalar"
    fi

    if [[ -d $DESTINO ]]; then
        CUANTOS=$(find "$DESTINO" -type f \( -name 'base-*' -o -name 'archivos-*' \) \
            2>/dev/null | wc -l | tr -d ' ')
        echo
        ok "aqui hay $CUANTOS archivo(s), $(du -sh "$DESTINO" 2>/dev/null | cut -f1)"

        ULTIMO="$(mas_reciente)"
        [[ -n ${ULTIMO:-} ]] \
            && echo "         ultimo de base: $(basename "$ULTIMO")" \
            || aviso "no hay ningun respaldo de la base todavia"
    else
        aviso "todavia no existe $DESTINO"
    fi

    [[ -f $BITACORA ]] && {
        echo
        echo "  Ultimas lineas de la bitacora:"
        tail -6 "$BITACORA" | sed 's/^/         /'
    }
    echo
    exit 0
fi

# ---------------------------------------------------------------------
#  --instalar  /  --quitar
# ---------------------------------------------------------------------
# launchd y no cron: en macOS, cron existe pero esta de salida, y
# launchd ademas RECUPERA la corrida perdida. Eso importa aqui: la
# computadora de Angel no esta prendida a las 20:30 todos los dias, y
# con cron el dia que esta apagada simplemente no se baja nada. Con
# launchd, al prenderla corre lo que le faltaba.
if [[ ${1:-} == --instalar ]]; then
    paso "Instalando la copia automatica"

    mkdir -p "$HOME/Library/LaunchAgents" "$DESTINO"

    cat > "$PLIST" <<PLISTFIN
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN"
  "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>$ETIQUETA</string>

    <key>ProgramArguments</key>
    <array>
        <string>/bin/bash</string>
        <string>$(pwd)/bajar-respaldos.command</string>
    </array>

    <!-- 20:30: el consultorio ya cerro y el respaldo del servidor de
         anoche ya existe. El de HOY se baja maniana, y esta bien: lo
         que importa es que nunca pasen muchos dias. -->
    <key>StartCalendarInterval</key>
    <dict>
        <key>Hour</key><integer>20</integer>
        <key>Minute</key><integer>30</integer>
    </dict>

    <!-- Si la computadora estaba apagada a esa hora, corre al
         prenderla. Sin esto, un fin de semana sin encenderla son tres
         dias sin copia. -->
    <key>RunAtLoad</key>
    <false/>
    <key>StartOnMount</key>
    <false/>

    <key>StandardOutPath</key>
    <string>$BITACORA</string>
    <key>StandardErrorPath</key>
    <string>$BITACORA</string>

    <key>WorkingDirectory</key>
    <string>$(pwd)</string>
</dict>
</plist>
PLISTFIN

    # bootout antes de bootstrap: cargarlo dos veces falla con un error
    # que no explica nada ("Load failed: 5: Input/output error").
    launchctl bootout "gui/$(id -u)/$ETIQUETA" 2>/dev/null || true
    launchctl bootstrap "gui/$(id -u)" "$PLIST" \
        || morir "No se pudo instalar. Revisa $PLIST"

    ok "instalada: todos los dias a las 20:30"
    ok "si la computadora esta apagada, corre al prenderla"
    ok "bitacora: $BITACORA"
    echo
    aviso "Para que corra sin pedir contrasenia hace falta una llave ssh."
    aviso "Si nunca la has puesto:"
    echo "         ssh-keygen -t ed25519        (Enter a todo)"
    echo "         ssh-copy-id $SERVIDOR"
    echo
    aviso "Pruebala ahora mismo:  ./bajar-respaldos.command"
    echo
    exit 0
fi

if [[ ${1:-} == --quitar ]]; then
    paso "Quitando la copia automatica"
    launchctl bootout "gui/$(id -u)/$ETIQUETA" 2>/dev/null || true
    rm -f "$PLIST"
    ok "quitada. Los respaldos que ya bajaste siguen en $DESTINO"
    echo
    exit 0
fi

# ---------------------------------------------------------------------
#  Bajar
# ---------------------------------------------------------------------
echo
echo "${NEGRITA}  Bajando los respaldos de Nanai${FIN}"
echo "  $(date '+%Y-%m-%d %H:%M:%S')"
echo "  desde:  $SERVIDOR:$ORIGEN"
echo "  hacia:  $DESTINO"

mkdir -p "$DESTINO"

paso "Copiando"

# -a conserva fechas y permisos; --ignore-existing no vuelve a bajar lo
# que ya esta. Un respaldo no cambia nunca despues de hecho, asi que
# comparar tamanios y fechas seria gastar tiempo para llegar siempre a
# la misma conclusion.
#
# Y NO se usa --delete: el servidor rota sus respaldos y espejarlo
# borraria de aqui justo lo que alla ya se olvido. Lo valioso de esta
# copia es que llega mas atras.
#
# -e con BatchMode: corriendo desde launchd no hay nadie para contestar
# una pregunta. Sin esto, el dia que la llave falle el proceso se queda
# esperando una contrasenia para siempre, en silencio.
if rsync -a --ignore-existing --human-readable \
        -e 'ssh -o BatchMode=yes -o ConnectTimeout=20' \
        --include='base-*' --include='archivos-*' \
        --exclude='*' \
        "$SERVIDOR:$ORIGEN/" "$DESTINO/" 2>&1 | sed 's/^/    /'; then
    ok "copia terminada"
else
    echo
    morir "No se pudo copiar.
         Lo mas probable es que falte la llave ssh. Pruebala a mano:
             ssh $SERVIDOR 'ls $ORIGEN'
         Si te pide contrasenia, instala la llave:
             ssh-copy-id $SERVIDOR"
fi

# ---------------------------------------------------------------------
#  Que quedo
# ---------------------------------------------------------------------
paso "Lo que hay en esta computadora"

BASES=$(find "$DESTINO" -type f -name 'base-*' 2>/dev/null | wc -l | tr -d ' ')
DOCS=$(find "$DESTINO" -type f -name 'archivos-*' 2>/dev/null | wc -l | tr -d ' ')

echo "    $BASES de base  ·  $DOCS de archivos del expediente"
echo "    ocupan $(du -sh "$DESTINO" 2>/dev/null | cut -f1)"

ULTIMO="$(mas_reciente)"

if [[ -n ${ULTIMO:-} ]]; then
    ok "el mas reciente de la base: $(basename "$ULTIMO")"
else
    aviso "no bajo ningun respaldo de la base"
    aviso "revisa en el servidor:  bash revisar-respaldos.sh"
fi

# Lo que todavia falta, dicho sin adornos: dos copias en la misma casa
# son una sola copia frente a un robo o un incendio.
if (( BASES > 0 )); then
    echo
    aviso "Estas copias estan en ESTA computadora."
    aviso "Si quieres dormir tranquilo, arrastra la carpeta a un disco"
    aviso "externo o a tu nube de vez en cuando:"
    echo "         $DESTINO"
fi

echo
echo "${VERDE}${NEGRITA}  Listo.${FIN}"
echo
