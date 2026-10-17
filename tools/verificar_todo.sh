#!/usr/bin/env bash
# =====================================================================
#  Correr todas las comprobaciones de una vez
#
#      ./tools/verificar_todo.sh
#
#  Devuelve 0 si todo esta en orden y 1 si algo falla, asi que se puede
#  encadenar o meter en un gancho de git.
#
#  Por que existe este guion y no un glob a mano: corriendo los arneses
#  con "tools/prueba_*.py" no se ejecuto ninguno —se llaman probar_, no
#  prueba_— y el bucle paso de largo sin decir nada. Un glob que no casa
#  con nada no es un error en bash: se expande a si mismo o a vacio y el
#  for simplemente no entra. Aqui se cuenta cuantos corrieron y se exige
#  que sean los que hay.
# =====================================================================

set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

VERDE=$'\033[0;32m'; ROJO=$'\033[0;31m'; NEGRITA=$'\033[1m'; FIN=$'\033[0m'

fallos=0
corridos=0

correr() {
    local archivo="$1" interprete
    case "$archivo" in
        *.js) interprete=node ;;
        *.py) interprete=python3 ;;
        *)    echo "  ${ROJO}!!${FIN}   $archivo: no se que correrlo con"; fallos=$((fallos+1)); return ;;
    esac

    if [[ ! -f $archivo ]]; then
        echo "  ${ROJO}!!${FIN}   $archivo: no existe"
        fallos=$((fallos+1)); return
    fi

    corridos=$((corridos+1))
    local salida
    if salida="$($interprete "$archivo" 2>&1)"; then
        printf '  %sok%s   %s\n' "$VERDE" "$FIN" "$archivo"
    else
        printf '  %s!!%s   %s\n' "$ROJO" "$FIN" "$archivo"
        echo "$salida" | grep -E '^\s*(!!|\*\*\*)' | sed 's/^/         /'
        fallos=$((fallos+1))
    fi
}

echo
echo "${NEGRITA}  Verificadores${FIN}"
correr tools/verificar.py
correr tools/verificar_php.py
correr tools/verificar_html.py
correr tools/verificar_expediente.py
correr tools/verificar_caja.py
correr tools/verificar_despliegue.py

echo
echo "${NEGRITA}  Arneses de logica${FIN}"
# Estos arneses portan el algoritmo de PHP a Python o Node y lo corren de
# verdad con datos. No hay PHP en esta maquina, asi que es la unica
# forma de saber que la cuenta sale, no solo que el codigo esta escrito.
correr tools/probar_doble_cobro.py
correr tools/probar_imagen.py
correr tools/probar_texto.py
correr tools/probar_odontograma.js
correr tools/probar_plantilla.py
correr tools/probar_tutoria.py
correr tools/probar_migrar.py
correr tools/probar_historia_modales.py
correr tools/probar_historia_navegador.js
correr tools/probar_anticipo.py
correr tools/probar_estados_plan.py
correr tools/probar_motivo_plan.py
correr tools/probar_consulta_navegador.js
correr tools/probar_complementos.py
correr tools/probar_saldo_a_favor.py
correr tools/probar_esquema.py
correr tools/probar_heic.py
correr tools/probar_cita_sin_hora.py
correr tools/probar_agenda_navegador.js
correr tools/probar_orden_pacientes.py
correr tools/probar_buscar_navegador.js
correr tools/probar_codigo_hallazgo.py
correr tools/probar_odontograma_estado.py
correr tools/probar_odontograma_navegador.js

# Si manianaa alguien agrega un archivo a tools/ y no lo mete en la lista
# de arriba, esto lo avisa en vez de dejarlo sin correr para siempre.
echo
echo "${NEGRITA}  La lista esta completa${FIN}"
hay=$(find tools -maxdepth 1 \( -name 'verificar_*.py' -o -name 'verificar.py' \
        -o -name 'probar_*' \) | wc -l | tr -d ' ')
if [[ $corridos -ne $hay ]]; then
    echo "  ${ROJO}!!${FIN}   hay $hay archivos de prueba en tools/ y solo se corrieron $corridos"
    echo "         agrega el que falta a este guion"
    fallos=$((fallos+1))
else
    echo "  ${VERDE}ok${FIN}   los $hay archivos de prueba de tools/ se corrieron"
fi

echo
echo "${NEGRITA}  Sintaxis del JavaScript${FIN}"
cuantos=0
for j in $(find assets/js -name '*.js' | sort); do
    cuantos=$((cuantos+1))
    node --check "$j" >/dev/null 2>&1 || {
        echo "  ${ROJO}!!${FIN}   $j"
        node --check "$j" 2>&1 | head -4 | sed 's/^/         /'
        fallos=$((fallos+1))
    }
done
if [[ $cuantos -eq 0 ]]; then
    echo "  ${ROJO}!!${FIN}   no se encontro ningun .js en assets/js"
    fallos=$((fallos+1))
else
    echo "  ${VERDE}ok${FIN}   $cuantos archivos"
fi

echo
if [[ $fallos -eq 0 ]]; then
    echo "${VERDE}${NEGRITA}  Todo en orden.${FIN}"
    echo
    exit 0
fi
echo "${ROJO}${NEGRITA}  $fallos comprobacion(es) con problemas.${FIN}"
echo
exit 1
