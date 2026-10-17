FROM php:8.2-apache

# libheif-examples trae heif-convert, que es como se leen las fotos de
# iPhone. GD no sabe abrir HEIC y no hay extension de PHP para ello en
# esta imagen; el ejecutable pesa poco y hace exactamente una cosa.
# default-mysql-client trae mysqldump y mysql, que es como el sistema
# crea y restaura un respaldo de la base desde Configuracion >
# Respaldos.
#
# Por que no usar docker desde PHP: habria que darle al contenedor el
# socket de Docker, y un contenedor con ese socket puede arrancar otro
# contenedor con el disco del servidor montado. O sea: una falla en la
# aplicacion web dejaria de ser "alguien ve expedientes" y pasaria a ser
# "alguien es dueño del servidor". El cliente de MySQL le habla a la
# base por la red interna, que es exactamente el permiso que ya tiene.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libicu-dev \
        libheif-examples \
        default-mysql-client \
        unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mysqli gd zip intl opcache exif \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Y ademas el complemento que DECODIFICA.
#
# Octubre de 2026: las fotos de iPhone se dejaban subir y despues no se
# veian en ningun lado —album vacio, sin miniaturas, y al descargarlas
# venia un .heic—. La causa estaba aqui: instalar libheif-examples pone
# el programa heif-convert, pero en las versiones nuevas de Debian el
# decodificador de HEVC se reparte en paquetes de complemento aparte.
# Asi que heif-convert existia, corria, y fallaba en cada foto.
#
# Es el peor tipo de dependencia que falta: la imagen se construye sin
# un solo aviso y el fallo aparece semanas despues, en otra pantalla, en
# forma de foto que no se ve.
#
# Va en su propio RUN y con "|| true" porque el nombre del paquete solo
# existe en las versiones donde hace falta: en las anteriores el
# decodificador viene dentro de libheif1 y pedirlo aborta la
# construccion entera. Lo que no puede pasar es que la imagen deje de
# construirse por esto.
RUN apt-get update \
    && (apt-get install -y --no-install-recommends \
            libheif-plugin-libde265 libheif-plugin-aomdec \
        || apt-get install -y --no-install-recommends libde265-0 \
        || true) \
    && rm -rf /var/lib/apt/lists/*

# El conversor cambio de nombre.
#
# Lo que durante anios se llamo heif-convert pasa a llamarse heif-dec en
# las versiones recientes de libheif, y en las nuevas de Debian ya solo
# viene el nombre nuevo. El paquete se instala, la imagen se construye
# sin un solo aviso, y el programa que el codigo invoca no existe: el
# proceso sale con 127 —"no se pudo ejecutar"— y el mensaje que llega a
# la pantalla parece un fallo misterioso del conversor. No hay
# conversor: ese es el punto.
#
# El PHP ya busca los dos nombres. Esto es la otra mitad: dejar el
# nombre viejo apuntando al nuevo, para que cualquier cosa que lo
# invoque —un guion, una prueba, yo dentro de seis meses— lo encuentre.
RUN if [ ! -e /usr/bin/heif-convert ] && [ -e /usr/bin/heif-dec ]; then \
        ln -s heif-dec /usr/bin/heif-convert; \
        echo "[ok] heif-convert -> heif-dec"; \
    fi

# Y se comprueba que de verdad este, al construir. Si falta, la
# construccion FALLA.
#
# Antes solo avisaba, y el aviso se perdio entre doscientas lineas de
# apt: la imagen se dio por buena y el fallo aparecio dias despues, en
# otra pantalla, como fotos que no se ven. Una dependencia que falta
# tiene que detener la construccion; es el unico momento en que alguien
# la esta mirando.
RUN set -e; \
    prog=""; \
    for p in heif-dec heif-convert; do \
        if command -v "$p" >/dev/null 2>&1; then prog="$p"; break; fi; \
    done; \
    if [ -z "$prog" ]; then \
        echo "[ERROR] no quedo instalado ningun conversor de HEIC"; \
        echo "        (se buscaron heif-dec y heif-convert)"; \
        echo "        Sin el, las fotos .heic de iPhone se rechazan."; \
        exit 1; \
    fi; \
    echo "[ok] conversor de HEIC: $(command -v "$prog")"

COPY php.ini /usr/local/etc/php/conf.d/consultorio.ini
COPY vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
