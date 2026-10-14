#!/usr/bin/env bash
# =====================================================================
#  ¿Estoy en el servidor?
#
#  Lo cargan desplegar.sh y enlazar-git.sh:
#
#      source "$AQUI/solo-en-el-servidor.sh"
#
#  Existe porque esos dos guiones viven en la MISMA carpeta en la Mac
#  y en el servidor —el proyecto es el mismo— y no hay nada en su
#  nombre que lo diga. Angel abrio la Terminal en su Mac, entro a
#  infra/produccion y corrio ./desplegar.sh, que es exactamente lo que
#  dicen las instrucciones... pero despues del ssh.
#
#  Lo que habria pasado: un git pull sobre su copia de trabajo y un
#  "docker compose -f docker-compose.produccion.yml up -d --build" en
#  su propia maquina. Ninguna de las dos cosas toca los expedientes del
#  servidor, pero levantan un segundo sistema en local que se parece al
#  bueno, y eso confunde durante horas.
#
#  Como se distingue: el .env de produccion vive SOLO en el servidor.
#  Esta en .gitignore, asi que nunca viaja; si esta, estas alla.
# =====================================================================

solo_en_el_servidor() {
    local aqui="$1"

    [[ -f "$aqui/.env" ]] && return 0

    local ROJO=$'\033[0;31m'; local NEGRITA=$'\033[1m'; local FIN=$'\033[0m'

    echo >&2
    echo "    ${ROJO}${NEGRITA}Esto se corre DENTRO del servidor.${FIN}" >&2
    echo >&2
    echo "    Estas en:  $(hostname 2>/dev/null)  —  $aqui" >&2
    echo "    y aqui no hay un infra/produccion/.env, que es el archivo" >&2
    echo "    con las claves del servidor y nunca sale de el." >&2
    echo >&2
    echo "    Para entrar:" >&2
    echo "        ${NEGRITA}ssh root@TU_SERVIDOR${FIN}" >&2
    echo "        ${NEGRITA}cd /opt/consultorio/infra/produccion${FIN}" >&2
    echo >&2
    echo "    Si lo que querias era actualizar TU computadora, el guion" >&2
    echo "    es otro, y esta en la raiz del proyecto:" >&2
    echo "        ${NEGRITA}./actualizar-local.command${FIN}" >&2
    echo >&2
    exit 1
}
