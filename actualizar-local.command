#!/usr/bin/env bash
# =====================================================================
#  Actualizar el sistema en tu computadora
#
#  Se corre con doble clic, o desde la Terminal:
#      ./actualizar-local.command
#
#  Hace, en orden, las cuatro cosas que hay que hacer despues de un
#  cambio de version:
#
#      1. Reconstruye la imagen de PHP (esta ronda agrego la extension
#         exif, y sin reconstruir no aparece).
#      2. Levanta los contenedores.
#      3. Aplica las migraciones pendientes de la base.
#      4. Comprueba que el sistema conteste.
#
#  El orden importa. Las migraciones necesitan MySQL arriba, y el codigo
#  nuevo necesita las columnas que crean las migraciones. Hacerlo al
#  reves deja el sistema esperando columnas que no existen.
# =====================================================================

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

VERDE=$'\033[0;32m'; AMARILLO=$'\033[1;33m'; ROJO=$'\033[0;31m'
AZUL=$'\033[0;34m'; NEGRITA=$'\033[1m'; FIN=$'\033[0m'

paso()  { echo; echo "${AZUL}${NEGRITA}==> $*${FIN}"; }
ok()    { echo "    ${VERDE}ok${FIN}   $*"; }
aviso() { echo "    ${AMARILLO}~${FIN}    $*"; }
morir() { echo "    ${ROJO}!!${FIN}   $*" >&2; echo; read -rp "  Enter para cerrar..."; exit 1; }

VERSION="$(grep -oE "APP_VERSION', '[^']+" config/config.php | cut -d"'" -f3)"

echo
echo "${NEGRITA}  Actualizar Nanai${FIN}"
echo "  ----------------"
echo "  Carpeta:  $(pwd)"
echo "  Version:  ${NEGRITA}v$VERSION${FIN}"
echo

# ---------------------------------------------------------------------
# Antes de tocar nada
# ---------------------------------------------------------------------
command -v docker >/dev/null \
    || morir "No encuentro Docker. Abre Docker Desktop y vuelve a intentar."

docker info >/dev/null 2>&1 \
    || morir "Docker esta instalado pero no esta corriendo. Abre Docker Desktop."

[[ -f .env ]] \
    || morir "Falta el archivo .env. Copialo de .env.example y pon tus claves."

# MinIO vive en su propio proyecto y guarda los archivos del expediente.
# Si no esta arriba, el sistema levanta igual pero no se ven las
# radiografias, y eso confunde mas que un error claro.
if ! docker ps --format '{{.Names}}' | grep -q '^minio_compartido$'; then
    aviso "MinIO no esta corriendo: los documentos del expediente no se veran."
    aviso "Para levantarlo:  cd infra/minio && docker compose up -d"
    echo
    read -rp "  ¿Continuar de todas formas? [s/N] " r
    [[ ${r,,} == s ]] || exit 0
fi

# ---------------------------------------------------------------------
# 0. Comprobar el codigo antes de levantarlo
# ---------------------------------------------------------------------
# Esto es gratis y tarda dos segundos: si falta un </div> o una columna
# que el codigo usa no esta en la base, es mejor saberlo ahora que
# viendo una pantalla en blanco.
#
# AVISA, pero NO detiene. La version anterior preguntaba "¿levantar de
# todas formas?" y se salia si contestabas que no... y lo que se saltaba
# era la migracion de la base. Resultado: codigo nuevo contra base
# vieja, que es el peor de los dos mundos, y el error que sale no se
# parece en nada a la causa.
#
# Una comprobacion no puede ponerse entre tu y la migracion. Si algo
# esta mal, lo que hay que hacer es arreglarlo y volver a correr esto;
# dejar la base a medias no ayuda a nadie.
#
# Y se llama con "bash" y no con "./": el bit de ejecutable no
# sobrevive a como se sincronizan estos archivos, asi que "./" fallaba
# con "Permission denied" —un fallo que no tiene NADA que ver con la
# salud del codigo— y disparaba justamente esa pregunta.
paso "Comprobando el codigo"

if bash tools/verificar_todo.sh >/tmp/nanai_verificar.txt 2>&1; then
    ok "las comprobaciones pasaron"
else
    echo
    grep -E '^\s*(\[0;31m)?!!' /tmp/nanai_verificar.txt | sed 's/^/        /'
    echo
    aviso "hay comprobaciones con problemas (el detalle: /tmp/nanai_verificar.txt)"
    aviso "sigo adelante: la base tiene que quedar al dia de todas formas."
fi

# ---------------------------------------------------------------------
# 1. Reconstruir y levantar
# ---------------------------------------------------------------------
paso "Reconstruyendo la imagen de PHP"
echo "    Por si el Dockerfile cambio de extensiones. Si no cambio,"
echo "    Docker reusa lo que ya tiene y esto tarda un segundo."
echo

docker compose build app || morir "No se pudo reconstruir la imagen."
ok "imagen lista"

paso "Levantando los contenedores"
docker compose up -d || morir "Los contenedores no levantaron."
ok "arriba"

# ---------------------------------------------------------------------
# 2. Esperar a MySQL
# ---------------------------------------------------------------------
# "docker compose up" devuelve en cuanto los contenedores arrancan, que
# no es lo mismo que MySQL listo para recibir consultas. Migrar antes de
# tiempo falla con un error de conexion que no dice nada.
paso "Esperando a la base"

CLAVE="$(grep -E '^DB_ROOT_PASS=' .env | cut -d= -f2-)"

listo=0
for _ in $(seq 1 60); do
    if docker exec consultorio_db mysqladmin ping -h localhost \
            -uroot -p"$CLAVE" --silent &>/dev/null; then
        listo=1; break
    fi
    printf '.'
    sleep 2
done
echo

[[ $listo -eq 1 ]] || morir "MySQL no respondio en dos minutos."
ok "la base responde"

# ---------------------------------------------------------------------
# 3. Migraciones
# ---------------------------------------------------------------------
# Los .sql de db/ solo corren cuando MySQL arranca con su carpeta de
# datos vacia. Tu base ya tiene pacientes de prueba dentro, asi que todo
# cambio de estructura entra por aqui.
paso "Poniendo la base al dia"

( cd infra/produccion && bash migrar.sh ) \
    || morir "Las migraciones no terminaron. El sistema puede quedar a medias."

# ---------------------------------------------------------------------
# 4. ¿Contesta?
# ---------------------------------------------------------------------
# ---------------------------------------------------------------------
# 3.5 ¿Se pueden convertir las fotos de iPhone?
# ---------------------------------------------------------------------
# Esto existe por un fallo concreto. Durante unos dias las fotos .heic
# se dejaban subir, la conversion fallaba y el archivo se guardaba tal
# cual: el expediente quedo con fotos que no se ven en ningun lado. La
# imagen de PHP se construia sin un solo aviso, y la primera senial
# aparecio semanas despues, en otra pantalla.
#
# Preguntarlo aqui cuesta un segundo y se entera quien puede arreglarlo,
# en vez de la doctora con un paciente enfrente.
paso "Fotos de iPhone (.heic)"

HEIC="$(docker compose exec -T app php -r '
    require "/var/www/html/includes/core/bootstrap.php";
    $como = null;
    $p = Imagen::porQueNoConvierteHeic($como);
    echo $p === null ? "ok (" . $como . ")" : $p;
' 2>/dev/null || echo 'no se pudo preguntar')"

if [[ $HEIC == ok ]]; then
    ok "se convierten a JPEG correctamente"
else
    aviso "NO se pueden convertir: $HEIC"
    aviso "las fotos .heic se van a RECHAZAR al subirlas (a proposito:"
    aviso "guardarlas sin convertir deja fotos que no se ven)"
fi

# ---------------------------------------------------------------------
# 4. ¿Contesta?
# ---------------------------------------------------------------------
paso "Comprobando"

PUERTO="$(grep -E '^APP_PORT=' .env | cut -d= -f2- || echo 8080)"
PUERTO="${PUERTO:-8080}"
URL="http://localhost:${PUERTO}/login.php"

CODIGO="$(curl -s -o /dev/null -w '%{http_code}' -m 20 "$URL" 2>/dev/null || echo 000)"

case "$CODIGO" in
    200) ok "la pantalla de acceso responde" ;;
    500) aviso "el sistema contesta con error 500. Lo que dice el log:"
         echo
         docker compose logs --tail 25 --no-color app 2>&1 \
             | grep -i 'error\|warning' | tail -12 | sed 's/^/        /'
         echo ;;
    000) aviso "nadie contesto en $URL. Dale unos segundos y recarga." ;;
    *)   aviso "respondio $CODIGO" ;;
esac

echo
echo "${VERDE}${NEGRITA}  Listo. v$VERSION corriendo en local.${FIN}"
echo
echo "  Abre:  ${NEGRITA}$URL${FIN}"
echo
echo "  ${NEGRITA}Que cambio en esta version${FIN}"
echo "    · El aviso de la foto .heic ahora dice lo que SI se"
echo "      puede hacer: Ajustes > Camara > Formatos > Mas"
echo "      compatible en el iPhone, y deja de pasar para siempre"
echo "    · Guion nuevo para ver que pasa de verdad dentro del"
echo "      contenedor:  bash tools/diagnostico_heic.sh"
echo "    · El conversor de HEIC cambio de nombre en Debian"
echo "      (heif-convert -> heif-dec). Por eso salia el codigo"
echo "      127: no habia conversor. Ahora se buscan los dos y"
echo "      la imagen FALLA al construirse si no queda ninguno"
echo "    · ARREGLADO DE VERDAD el .heic: heif-convert si"
echo "      convertia; yo leia mal su codigo de salida y por eso"
echo "      rechazaba fotos buenas (el famoso \"codigo -1\")"
echo "    · Si varias fotos fallan por lo mismo, el aviso dice"
echo "      el motivo una vez y no uno por foto"
echo "    · ARREGLADO: las fotos .heic ya se convierten de verdad."
echo "      Faltaba un paquete en la imagen de PHP, asi que se"
echo "      guardaban sin convertir y no se veian en ningun lado."
echo "    · Si una .heic no se puede convertir, ahora se RECHAZA:"
echo "      archivada sin convertir no se podria ver despues"
echo "    · Para las que ya quedaron asi, corre:"
echo "        docker compose exec app php tools/reparar_heic.php --ver"
echo "        docker compose exec app php tools/reparar_heic.php"
echo "    · ARREGLADO: el consentimiento impreso desde el plan ya"
echo "      sale con el nombre y la cedula de la dentista"
echo "    · ARREGLADO: los pendientes del plan se crean al GUARDAR,"
echo "      no al abrirse la ventana de impresion (que el navegador"
echo "      bloqueaba, y se perdia el papel Y el aviso)"
echo "    · Plan guardado: un boton por cada hoja, con su marca"
echo "      de pendiente de imprimir"
echo "    · ARREGLADO: el prellenado de la historia ya no abre la"
echo "      ventana de alimentos con azucar"
echo "    · ARREGLADO: la pestania de historia se apaga al guardar,"
echo "      sin recargar"
echo "    · Examen bucal: NORMAL o ANORMAL, tambien al imprimir"
echo "    · El RFC del tutor se acepta con o sin homoclave"
echo "    · Domicilio: se fue el boton de capturar un segundo"
echo "    · Expediente: miniatura de cada foto y de cada PDF, y"
echo "      Escaneado correctamente en vez de repetir el tipo"
echo
echo "  ${NEGRITA}Hay migracion nueva (0013).${FIN} Ya se aplico arriba."
echo
echo "  ${NEGRITA}Esta version reconstruye la imagen de PHP${FIN} por el"
echo "  paquete del HEIC. Si el paso de las fotos de iPhone dijo que"
echo "  NO se convierten, mandame el mensaje exacto: dice cual falta."
echo

read -rp "  Enter para cerrar..."
