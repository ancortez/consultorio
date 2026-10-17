#!/usr/bin/env bash
# =====================================================================
#  ¿Se estan haciendo los respaldos?
#
#      bash revisar-respaldos.sh
#      bash revisar-respaldos.sh --instalar-cron    (si falta la tarea)
#
#  Por que existe
#  --------------
#  Angel pregunto como darse cuenta de si los respaldos corren. La
#  pregunta correcta, y la respuesta honesta era: mirando seis sitios
#  distintos —el cron, la carpeta, el log, el espacio libre, el .env y
#  el volumen de MinIO—. Nadie hace eso cada semana, y un respaldo que
#  nadie revisa es una suposicion.
#
#  Y hay un motivo concreto para dudar: el cron lo instala
#  instalar-servidor.sh, y este servidor se monto a mano subiendo los
#  archivos por SFTP. Si ese guion no corrio completo, la tarea no
#  existe y NO HAY RESPALDOS, sin que nada lo diga: el sistema funciona
#  igual de bien sin respaldar.
#
#  Lo que esto contesta, en un golpe:
#
#      1. ¿Esta puesta la tarea automatica? ¿A que hora?
#      2. ¿Cuando fue el ultimo respaldo de la BASE? ¿Cuanto pesa?
#      3. ¿Y el de los ARCHIVOS del expediente (MinIO)?
#      4. ¿Van cifrados?
#      5. ¿Hay espacio para el siguiente?
#      6. ¿El ultimo intento fallo? ¿Por que?
#      7. ¿Existe una copia FUERA de este servidor?
#
#  Devuelve 0 si todo esta en orden y 1 si hay algo que atender, asi
#  que tambien sirve para encadenarlo o mandarlo por correo.
# =====================================================================

set -uo pipefail

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ="$(cd "$AQUI/../.." && pwd)"

VERDE=$'\033[0;32m'; AMARILLO=$'\033[1;33m'; ROJO=$'\033[0;31m'
AZUL=$'\033[0;34m'; NEGRITA=$'\033[1m'; FIN=$'\033[0m'

paso()   { echo; echo "${AZUL}${NEGRITA}==> $*${FIN}"; }
ok()     { echo "    ${VERDE}ok${FIN}   $*"; }
aviso()  { echo "    ${AMARILLO}~${FIN}    $*"; }
mal()    { echo "    ${ROJO}!!${FIN}   $*"; PROBLEMAS=$((PROBLEMAS + 1)); }
sugerir(){ echo "         $*"; }

PROBLEMAS=0

# Esto se corre dentro del servidor: en la Mac no hay ni cron ni
# carpeta de respaldos, y el guion diria que no hay respaldos cuando lo
# que no hay es servidor.
source "$AQUI/solo-en-el-servidor.sh"
solo_en_el_servidor "$AQUI"

set -a; source "$AQUI/.env"; set +a

DESTINO="${CARPETA_RESPALDOS:-/var/respaldos/consultorio}"
DIAS_BASE="${DIAS_RESPALDO_BASE:-14}"
COPIAS_ARCHIVOS="${COPIAS_RESPALDO_ARCHIVOS:-4}"
DIA_ARCHIVOS="${DIA_RESPALDO_ARCHIVOS:-7}"
MINIMO_LIBRE_GB="${MINIMO_LIBRE_GB:-10}"
# Las dos rutas del sistema se pueden sustituir. No es para que nadie
# las cambie en el servidor —ahi van donde van— sino para poder correr
# este guion contra un servidor de mentira y comprobar que de verdad
# detecta lo que dice detectar. Un guion de diagnostico que nunca se
# probo contra un caso roto es una suposicion mas.
LOG="${RESPALDO_LOG:-/var/log/consultorio-respaldo.log}"
CRON="${RESPALDO_CRON:-/etc/cron.d/consultorio-respaldo}"

# Cuantas horas tiene un archivo. Es la cuenta de la que sale todo lo
# demas, asi que va en un solo sitio.
horas_desde() {
    local f="$1"
    echo $(( ( $(date +%s) - $(stat -c %Y "$f") ) / 3600 ))
}

mas_reciente() {
    # shellcheck disable=SC2012
    ls -t "$DESTINO"/$1 2>/dev/null | head -1
}

echo
echo "${NEGRITA}  Respaldos del consultorio${FIN}"
echo "  servidor: $(hostname)   carpeta: $DESTINO"

# ---------------------------------------------------------------------
# 1. La tarea automatica
# ---------------------------------------------------------------------
paso "La tarea automatica"

if [[ -f $CRON ]]; then
    LINEA="$(grep -v '^\s*\(#\|SHELL\|PATH\|$\)' "$CRON" | head -1)"
    ok "esta puesta en $CRON"
    echo "         $LINEA"

    # Que el guion al que apunta exista de verdad. Una tarea que llama
    # a un archivo que ya no esta falla todas las noches en silencio:
    # cron no avisa a nadie, solo escribe en el log.
    RUTA_CRON="$(echo "$LINEA" | grep -oE '/[^ ]*respaldar\.sh' | head -1)"
    if [[ -n $RUTA_CRON && ! -f $RUTA_CRON ]]; then
        mal "apunta a $RUTA_CRON, que no existe"
        sugerir "la tarea falla cada noche sin decir nada"
    fi

    # Y que cron este corriendo. Con el servicio apagado, el archivo
    # esta y no se ejecuta nunca.
    if ! pgrep -x cron >/dev/null 2>&1 && ! pgrep -x crond >/dev/null 2>&1; then
        mal "el servicio cron NO esta corriendo"
        sugerir "systemctl enable --now cron"
    fi
else
    mal "NO hay tarea automatica: los respaldos no se estan haciendo solos"
    sugerir "lo unico que respalda hoy es desplegar.sh, y solo cuando despliegas"
    sugerir "para arreglarlo:  bash revisar-respaldos.sh --instalar-cron"
fi

# ---------------------------------------------------------------------
# 2. El respaldo de la base
# ---------------------------------------------------------------------
paso "La base de datos (pacientes, consultas, cobros)"

ULTIMA_BASE="$(mas_reciente 'base-*.tar.gz*')"

if [[ -z $ULTIMA_BASE ]]; then
    mal "no hay NINGUN respaldo de la base en $DESTINO"
    sugerir "corre uno ahora mismo:  bash respaldar.sh --solo-base"
else
    HORAS=$(horas_desde "$ULTIMA_BASE")
    PESO="$(du -h "$ULTIMA_BASE" | cut -f1)"
    CUANTAS=$(find "$DESTINO" -name 'base-*.tar.gz*' | wc -l)

    echo "         ultimo: $(basename "$ULTIMA_BASE")  ($PESO)"
    echo "         hace ${HORAS}h  ·  $CUANTAS copia(s) guardada(s)"

    # La base se respalda TODAS las noches, asi que mas de dos dias
    # significa que algo dejo de correr. Se dan 48 y no 24 para que una
    # noche de mantenimiento no asuste.
    if (( HORAS > 48 )); then
        mal "el ultimo respaldo de la base es de hace ${HORAS} horas"
        sugerir "deberia haber uno de cada noche: algo dejo de correr"
    elif (( HORAS > 26 )); then
        aviso "van ${HORAS} horas desde el ultimo: revisalo manana"
    else
        ok "al dia"
    fi

    # Un volcado de la base no puede pesar cuatro kilobytes: el esquema
    # solo pesa mas. respaldar.sh ya lo comprueba al hacerlo, pero un
    # respaldo viejo pudo quedar truncado por falta de espacio.
    BYTES=$(stat -c%s "$ULTIMA_BASE")
    if (( BYTES < 10240 )); then
        mal "pesa $BYTES bytes: ese respaldo esta vacio o truncado"
    fi

    if (( CUANTAS < 2 )); then
        aviso "solo hay $CUANTAS copia: todavia no hay historial del que tirar"
    fi
fi

# ---------------------------------------------------------------------
# 3. Los archivos del expediente
# ---------------------------------------------------------------------
paso "Los archivos del expediente (radiografias, fotos, escaneos)"

if ! docker volume inspect minio_data_compartido >/dev/null 2>&1; then
    mal "no encuentro el volumen de MinIO: los archivos no se pueden respaldar"
    sugerir "revisa que MinIO este levantado"
else
    PESO_MB=$(docker run --rm -v minio_data_compartido:/origen:ro \
        alpine du -sm /origen 2>/dev/null | cut -f1 || echo '?')
    echo "         ocupan ahora: ${PESO_MB} MB"
fi

ULTIMO_DOCS="$(mas_reciente 'archivos-*.tar.gz*')"

if [[ -z $ULTIMO_DOCS ]]; then
    mal "no hay NINGUN respaldo de los archivos del expediente"
    sugerir "estos pesan y se respaldan una vez por semana, los domingos"
    sugerir "para hacer uno ahora:  bash respaldar.sh --completo"
    sugerir "(tarda, y necesita el doble de espacio del que ocupan)"
else
    DIAS=$(( $(horas_desde "$ULTIMO_DOCS") / 24 ))
    PESO="$(du -h "$ULTIMO_DOCS" | cut -f1)"
    CUANTOS=$(find "$DESTINO" -name 'archivos-*.tar.gz*' | wc -l)

    echo "         ultimo: $(basename "$ULTIMO_DOCS")  ($PESO)"
    echo "         hace ${DIAS} dia(s)  ·  $CUANTOS copia(s) guardada(s)"

    # Son semanales: mas de diez dias significa que se saltaron un
    # domingo, y lo mas probable es que fuera por falta de espacio
    # —respaldar.sh se salta los archivos antes que llenar el disco—.
    if (( DIAS > 10 )); then
        mal "el ultimo respaldo de archivos es de hace ${DIAS} dias"
        sugerir "son semanales: se saltaron al menos un domingo"
        sugerir "la causa mas comun es falta de espacio (mira el punto de abajo)"
    else
        ok "al dia"
    fi
fi

# ---------------------------------------------------------------------
# 4. Cifrado
# ---------------------------------------------------------------------
paso "Cifrado"

# El respaldo trae el expediente clinico completo de cada paciente. En
# Mexico los datos de salud son datos sensibles: sacarlos del servidor
# sin cifrar es el problema, no guardarlos aqui.
if [[ -n ${CLAVE_RESPALDO:-} ]]; then
    ok "CLAVE_RESPALDO esta puesta: los respaldos nuevos van cifrados"

    SIN_CIFRAR=$(find "$DESTINO" -name '*.tar.gz' | wc -l)
    if (( SIN_CIFRAR > 0 )); then
        aviso "hay $SIN_CIFRAR respaldo(s) viejo(s) sin cifrar en la carpeta"
        sugerir "son de antes de poner la clave; borralos cuando ya no te sirvan"
    fi
else
    mal "sin CLAVE_RESPALDO: los respaldos se guardan SIN CIFRAR"
    sugerir "traen el expediente clinico completo de cada paciente"
    sugerir "pon CLAVE_RESPALDO en infra/produccion/.env antes de bajarlos"
    sugerir "GUARDA esa clave aparte: sin ella el respaldo no se puede restaurar"
fi

# ---------------------------------------------------------------------
# 5. Espacio
# ---------------------------------------------------------------------
paso "Espacio en disco"

LIBRE=$(df -BG --output=avail "$DESTINO" 2>/dev/null | tail -1 | tr -dc '0-9')
USADO="$(du -sh "$DESTINO" 2>/dev/null | cut -f1)"

echo "         los respaldos ocupan: ${USADO:-0}"
echo "         libre en el disco:    ${LIBRE:-?} GB"

if [[ -n ${LIBRE:-} ]] && (( LIBRE < MINIMO_LIBRE_GB )); then
    mal "quedan ${LIBRE} GB, por debajo del minimo de ${MINIMO_LIBRE_GB} GB"
    sugerir "respaldar.sh se NIEGA a respaldar en estas condiciones"
    sugerir "un disco lleno tumba MySQL, que es peor que no respaldar"
    sugerir "baja respaldos viejos a tu computadora y borralos de aqui"
elif [[ -n ${LIBRE:-} ]] && (( LIBRE < MINIMO_LIBRE_GB * 2 )); then
    aviso "quedan ${LIBRE} GB: vas a chocar con el minimo pronto"
else
    ok "hay espacio"
fi

# ---------------------------------------------------------------------
# 6. El log del ultimo intento
# ---------------------------------------------------------------------
paso "Lo que dijo el ultimo intento"

if [[ ! -f $LOG ]]; then
    if [[ -f $CRON ]]; then
        aviso "no hay log en $LOG"
        sugerir "la tarea esta puesta pero parece que no ha corrido ninguna vez"
    else
        aviso "no hay log, y es lo esperado: no hay tarea automatica"
    fi
else
    # Del ultimo bloque, que empieza en la linea "=== Respaldo ".
    ULTIMO_BLOQUE="$(tac "$LOG" | sed -n '0,/=== Respaldo /p' | tac)"

    echo "$ULTIMO_BLOQUE" | sed 's/^/         /' | head -20

    if echo "$ULTIMO_BLOQUE" | grep -qi 'ERROR'; then
        mal "el ultimo intento registro un ERROR (arriba)"
    elif ! echo "$ULTIMO_BLOQUE" | grep -q '=== Fin ==='; then
        mal "el ultimo intento no llego al final: se corto a medias"
    else
        ok "termino bien"
    fi

    # Un log que crece sin parar acaba comiendose el disco que
    # justamente hace falta para respaldar.
    TAM_LOG=$(stat -c%s "$LOG")
    if (( TAM_LOG > 10485760 )); then
        aviso "el log pesa $(numfmt --to=iec "$TAM_LOG"): conviene rotarlo"
    fi
fi

# ---------------------------------------------------------------------
# 7. La copia de fuera
# ---------------------------------------------------------------------
paso "Copia fuera del servidor"

# Esto es lo mas importante del guion y lo que ninguna comprobacion
# tecnica puede resolver sola. Un respaldo que vive en el mismo
# servidor no protege del caso mas probable: que el servidor se pierda.
# Si el proveedor tiene un problema serio, se van el sistema y los
# respaldos juntos.
if [[ -n ${COPIA_FUERA:-} ]]; then
    ok "declarada en el .env: ${COPIA_FUERA}"
    aviso "esto es una nota tuya, no una comprobacion: nadie verifica que exista"
else
    mal "los respaldos SOLO existen en este servidor"
    sugerir "si el servidor se pierde, se pierden con el"
    sugerir "bajalos a tu computadora cada semana, por ejemplo:"
    sugerir "  scp root@$(hostname -I 2>/dev/null | awk '{print $1}'):$DESTINO/base-*.enc ."
    sugerir 'cuando ya tengas la costumbre, pon en el .env:'
    sugerir '  COPIA_FUERA="disco externo de la casa"'
    # Con comillas a proposito: el .env se carga con source, asi que un
    # valor de varias palabras sin comillas hace que bash trate la
    # segunda como un comando. Lo descubri probando este mismo guion:
    # el .env de mentira decia COPIA_FUERA=disco externo y bash
    # contesto "externo: command not found".

    sugerir "para que este aviso deje de salir"
fi

# ---------------------------------------------------------------------
#  Instalar la tarea, si se pidio
# ---------------------------------------------------------------------
if [[ ${1:-} == --instalar-cron ]]; then
    paso "Instalando la tarea automatica"

    if [[ $EUID -ne 0 ]]; then
        mal "esto hay que correrlo como root"
        sugerir "sudo bash revisar-respaldos.sh --instalar-cron"
    else
        # El dueño de la carpeta es quien va a escribir en ella. Se
        # deduce del propio proyecto en vez de pedirlo: asi no hay forma
        # de teclear otro usuario y dejar la tarea fallando por
        # permisos.
        USUARIO="$(stat -c '%U' "$RAIZ")"

        install -d -o "$USUARIO" -g "$USUARIO" "$DESTINO"

        cat > "$CRON" <<CRONFIN
# Respaldo diario del Sistema Consultorio Dental.
# 2:30 de la madrugada: nadie esta usando el sistema a esa hora.
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
30 2 * * * $USUARIO $RAIZ/infra/produccion/respaldar.sh >> $LOG 2>&1
CRONFIN
        chmod 644 "$CRON"

        touch "$LOG"
        chown "$USUARIO" "$LOG"

        ok "tarea instalada: todas las noches a las 2:30, como $USUARIO"
        ok "la base cada noche; los archivos del expediente los domingos"
        echo
        sugerir "para no esperar a la madrugada, corre uno ahora:"
        sugerir "  sudo -u $USUARIO bash respaldar.sh --solo-base"
        sugerir "y vuelve a correr este guion para comprobarlo"
    fi
fi

# ---------------------------------------------------------------------
#  Veredicto
# ---------------------------------------------------------------------
echo
if (( PROBLEMAS == 0 )); then
    echo "${VERDE}${NEGRITA}  Los respaldos se estan haciendo.${FIN}"
    echo
    exit 0
fi

echo "${ROJO}${NEGRITA}  $PROBLEMAS cosa(s) que atender.${FIN}"
echo "  Las lineas con !! son las que importan."
echo
exit 1
