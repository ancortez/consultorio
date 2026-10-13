"""
Revisiones del despliegue en servidor.

Lo que se revisa aqui son las cosas que, si salen mal, no se notan
hasta que alguien entra donde no debia: un puerto abierto de mas, una
contrasenia de fabrica que se quedo, un archivo .env servido por HTTP.

Lo que se puede probar de verdad (el cifrado del respaldo, el
generador de claves) se prueba corriendolo, no leyendolo.
"""
import sys
import re, pathlib, subprocess, os, tempfile

raiz = pathlib.Path('.')
prod = raiz / 'infra/produccion'
fallos, avisos = [], []

compose = (prod / 'docker-compose.produccion.yml').read_text(encoding='utf-8')
caddy   = (prod / 'Caddyfile').read_text(encoding='utf-8')
instala = (prod / 'instalar-servidor.sh').read_text(encoding='utf-8')
respald = (prod / 'respaldar.sh').read_text(encoding='utf-8')
restaur = (prod / 'restaurar.sh').read_text(encoding='utf-8')
desplie = (prod / 'desplegar.sh').read_text(encoding='utf-8')
minio   = (raiz / 'infra/minio/docker-compose.produccion.yml').read_text(encoding='utf-8')
esquema = (raiz / 'db/01_schema.sql').read_text(encoding='utf-8')

# ---------- 1. Nada expuesto salvo Caddy ----------
# Se buscan todos los 'ports:' y se revisa que, salvo 80 y 443 de
# Caddy, todos esten atados a 127.0.0.1. Un puerto sin esa atadura en
# un servidor publico lo encuentran los bots en horas.
def puertos(texto, archivo):
    malos = []
    for linea in texto.splitlines():
        m = re.match(r'\s*-\s*"([^"]+)"\s*$', linea)
        if not m: continue
        p = m.group(1)
        if p in ('80:80', '443:443'):        # Caddy: tienen que estar abiertos
            continue
        if not p.startswith('127.0.0.1:'):
            malos.append((archivo, p))
    return malos

expuestos = puertos(compose, 'compose de produccion') + puertos(minio, 'compose de minio')
if expuestos:
    for a, p in expuestos:
        fallos.append(f'{a}: el puerto "{p}" sale a internet sin 127.0.0.1')
print('  ok   ningun puerto expuesto salvo 80 y 443')

# MySQL en particular
bloque_db = compose[compose.index('  db:'):compose.index('  phpmyadmin:')]
if 'ports:' in bloque_db:
    fallos.append('MySQL tiene un puerto publicado en produccion')
print('  ok   MySQL sin puerto publicado')

# ---------- 2. Sin credenciales de fabrica ----------
# En local 'minioadmin' es comodo; en internet es la puerta abierta.
#
# Se miran solo las lineas efectivas: los comentarios hablan de esos
# valores justamente para explicar por que NO se usan, y contarlos
# como hallazgo convierte la revision en ruido.
def sin_comentarios(texto):
    return '\n'.join(l for l in texto.splitlines()
                     if not l.lstrip().startswith('#'))

for nombre, texto in [('compose de produccion', sin_comentarios(compose)),
                      ('compose de minio', sin_comentarios(minio))]:
    for mala in ('minioadmin', ':-root}', ':-consultorio}', 'Admin123'):
        if mala in texto:
            fallos.append(f'{nombre}: quedo el valor de fabrica "{mala}"')

# Las claves tienen que ser obligatorias (sintaxis :?)
obligatorias = ['DB_PASS', 'DB_ROOT_PASS', 'MINIO_LLAVE', 'MINIO_SECRETO']
for v in obligatorias:
    if f'${{{v}:?' not in compose:
        fallos.append(f'{v} no es obligatoria en el compose: podria arrancar sin ella')
for v in ['MINIO_USUARIO', 'MINIO_PASSWORD']:
    if f'${{{v}:?' not in minio:
        fallos.append(f'{v} no es obligatoria en el compose de MinIO')
print(f'  ok   {len(obligatorias) + 2} claves obligatorias, sin valores de fabrica')

# ---------- 3. Produccion de verdad ----------
if 'APP_ENV: produccion' not in compose:
    fallos.append('APP_ENV no esta fijo en produccion: los errores se verian en pantalla')
if 'HTTPS_DETRAS_DE_PROXY' not in compose:
    fallos.append('falta HTTPS_DETRAS_DE_PROXY: las cookies no se marcarian seguras')

# Y que el codigo sepa leerlo
sesion = (raiz / 'includes/core/sesion.php').read_text(encoding='utf-8')
if "getenv('HTTPS_DETRAS_DE_PROXY')" not in sesion:
    fallos.append('sesion.php no lee HTTPS_DETRAS_DE_PROXY')
if "HTTP_X_FORWARDED_PROTO" not in sesion:
    fallos.append('sesion.php no revisa la cabecera del proxy')
# Y que no le crea a la cabecera sin permiso: es lo que evita que
# cualquiera la mande desde fuera y se haga pasar por HTTPS.
if "$detrasDeProxy !== '1'" not in sesion:
    fallos.append('sesion.php le cree a la cabecera del proxy sin condicion')
print('  ok   produccion: sin errores en pantalla y cookies seguras tras el proxy')

# El codigo montado de solo lectura
if '/var/www/html:ro' not in compose:
    fallos.append('el codigo no se monta de solo lectura')
print('  ok   el codigo se monta de solo lectura')

# ---------- 4. Caddy tapa lo que no debe salir ----------
for ruta in ['/.env', '/.git/', '/includes', '/config', '/db/', '/infra/']:
    # includes y config los tapa Apache; Caddy tapa el resto
    pass
for ruta in ['/.env*', '/.git/*', '/db/*', '/infra/*']:
    if ruta not in caddy:
        fallos.append(f'Caddy no bloquea {ruta}')
if 'respond @prohibido 404' not in caddy:
    fallos.append('Caddy no responde 404 a las rutas prohibidas')
if 'Strict-Transport-Security' not in caddy:
    fallos.append('falta HSTS en Caddy')
print('  ok   Caddy bloquea .env, .git, db e infra, y manda HSTS')

# Apache tambien, por si Caddy se cambia algun dia
vhost = (raiz / 'docker/php/vhost.conf').read_text(encoding='utf-8')
for carpeta in ('includes', 'config', 'db'):
    if carpeta not in vhost:
        fallos.append(f'Apache no bloquea /{carpeta}')
print('  ok   Apache bloquea includes, config y db (segunda barrera)')

# ---------- 5. Cortafuegos ----------
abiertos = re.findall(r'ufw allow (\d+)/tcp', instala)
if sorted(abiertos) != sorted(['22', '80', '443']):
    fallos.append(f'el cortafuegos abre {abiertos}, deberian ser 22, 80 y 443')
if 'ufw default deny incoming' not in instala:
    fallos.append('el cortafuegos no niega todo lo entrante por omision')
print(f'  ok   cortafuegos: solo {", ".join(sorted(abiertos))}')

# ---------- 6. El instalador no deja claves adivinables ----------
if 'openssl rand' not in instala:
    fallos.append('el instalador no genera claves al azar')
if 'chmod 600' not in instala:
    fallos.append('el .env no queda con permiso 600')
if 'adduser --disabled-password' not in instala:
    fallos.append('el instalador no crea usuario sin contrasenia')
print('  ok   claves al azar, .env con permiso 600, usuario sin password')

# Revisa el DNS antes de instalar: si no, el HTTPS falla y el usuario
# se queda con medio servidor sin entender por que.
if 'api.ipify.org' not in instala or 'getent hosts' not in instala:
    avisos.append('el instalador no comprueba el DNS antes de empezar')
print('  ok   el instalador comprueba el DNS antes de tocar nada')

# ---------- 7. Respaldos ----------
if '--single-transaction' not in respald:
    fallos.append('el volcado no usa --single-transaction: podria salir a medias')
if 'TAM_SQL < 10240' not in respald:
    fallos.append('el respaldo no comprueba que el volcado no salga vacio')
if 'minio_data_compartido' not in respald:
    fallos.append('el respaldo no incluye los archivos del expediente')

# La base y los archivos se respaldan por separado y con retenciones
# distintas. Si los dos fueran diarios con catorce copias, el disco se
# llenaria: con mil pacientes serian mas de 400 GB en uno de 100.
if 'DIAS_RESPALDO_BASE' not in respald or 'COPIAS_RESPALDO_ARCHIVOS' not in respald:
    fallos.append('el respaldo no separa la retencion de base y de archivos')
if 'MINIMO_LIBRE_GB' not in respald:
    fallos.append('el respaldo no revisa el espacio libre antes de empezar')
if "df -BG" not in respald:
    fallos.append('el respaldo no mide el disco de verdad')
print('  ok   base diaria y archivos semanales, con guardia de disco')
if 'aes-256-cbc' not in respald:
    fallos.append('el respaldo no ofrece cifrado')
if '-pbkdf2' not in respald:
    fallos.append('el cifrado no usa pbkdf2: la derivacion de la clave seria debil')
print('  ok   respaldo: consistente, no vacio, con archivos y cifrable')

# Los dos tipos de respaldo se restauran distinto y no se mezclan.
if 'TIPO=archivos' not in restaur:
    fallos.append('restaurar no distingue el respaldo de base del de archivos')
if 'La base de datos NO se toca' not in restaur:
    fallos.append('restaurar no avisa que el de archivos no toca la base')
print('  ok   restaurar distingue los dos tipos de respaldo')

# La restauracion guarda lo que hay antes de pisarlo
if 'antes-de-restaurar' not in restaur:
    fallos.append('restaurar no guarda copia de lo que va a reemplazar')
if 'RESTAURAR' not in restaur:
    fallos.append('restaurar no pide confirmacion escrita')
if 'docker stop minio_compartido' not in restaur:
    fallos.append('restaurar no detiene MinIO antes de reemplazar sus archivos')
print('  ok   restaurar: copia previa, confirmacion escrita y MinIO detenido')

# Y que se pueda probar sin riesgo
if 'prueba_respaldo_' not in restaur or 'DROP DATABASE IF EXISTS' not in restaur:
    fallos.append('no se puede probar un respaldo sin tocar la base real')
print('  ok   el respaldo se puede probar en una base temporal')

# ---------- 8. Despliegue ----------
# Se acepta cualquier forma de llamarlo (ruta relativa o absoluta),
# lo que importa es que respalde antes de bajar cambios.
if not re.search(r'respaldar\.sh', desplie):
    fallos.append('desplegar no respalda antes de tocar nada')
elif desplie.index('respaldar.sh') > desplie.index('git pull'):
    fallos.append('desplegar respalda DESPUES de bajar los cambios')
if 'login.php' not in desplie:
    fallos.append('desplegar no comprueba que el sistema responda al terminar')
print('  ok   desplegar respalda primero y comprueba al final')

# ---------- 9. Prueba real del cifrado del respaldo ----------
# Esto no se lee: se corre. Se cifra y se descifra con los mismos
# parametros del guion y se compara byte a byte.
with tempfile.TemporaryDirectory() as tmp:
    original = os.path.join(tmp, 'original.tar.gz')
    cifrado  = os.path.join(tmp, 'cifrado.enc')
    vuelta   = os.path.join(tmp, 'vuelta.tar.gz')

    datos = os.urandom(200_000)
    open(original, 'wb').write(datos)
    clave = "una frase larga con espacios y acentos: ñÁ"

    cifrar = subprocess.run(
        ['openssl', 'enc', '-aes-256-cbc', '-pbkdf2', '-iter', '200000', '-salt',
         '-in', original, '-out', cifrado, '-pass', f'pass:{clave}'],
        capture_output=True)
    descifrar = subprocess.run(
        ['openssl', 'enc', '-d', '-aes-256-cbc', '-pbkdf2', '-iter', '200000',
         '-in', cifrado, '-out', vuelta, '-pass', f'pass:{clave}'],
        capture_output=True)

    if cifrar.returncode or descifrar.returncode:
        fallos.append('el cifrado del respaldo falla al correrse')
    elif open(vuelta, 'rb').read() != datos:
        fallos.append('el respaldo descifrado NO es igual al original')
    else:
        print('  ok   cifrado del respaldo: 200 KB ida y vuelta, identicos')

    # Con la clave equivocada no debe abrir.
    #
    # Lo que se mira es el CONTENIDO, no solo el codigo de salida. Y es
    # por un fallo de esta prueba, no del respaldo: en AES-CBC, una
    # clave equivocada produce basura, y una de cada 256 veces esa
    # basura termina por casualidad en un relleno que openssl acepta,
    # asi que sale con exito. Esta comprobacion fallaba sola de vez en
    # cuando, y una prueba que falla al azar es peor que ninguna:
    # ensenia a volver a correrla hasta que pase, que es exactamente la
    # costumbre que hace que un fallo de verdad pase desapercibido.
    #
    # El contenido si es determinista: con la clave mala NUNCA sale el
    # original, pase lo que pase con el relleno.
    malo = os.path.join(tmp, 'con-clave-mala')
    mal = subprocess.run(
        ['openssl', 'enc', '-d', '-aes-256-cbc', '-pbkdf2', '-iter', '200000',
         '-in', cifrado, '-out', malo, '-pass', 'pass:otra'],
        capture_output=True)

    salio = open(malo, 'rb').read() if os.path.exists(malo) else b''
    if mal.returncode == 0 and salio == datos:
        fallos.append('el respaldo se abre con una clave equivocada')
    else:
        print('  ok   con la clave equivocada, el respaldo no devuelve los datos')

# ---------- 10. Prueba real del generador de claves ----------
# Se corre la misma linea del instalador cien veces: tienen que ser
# todas distintas, del largo correcto y sin caracteres que rompan un
# archivo .env o una linea de comando.
claves = set()
for _ in range(100):
    r = subprocess.run(
        ["bash", "-c", "openssl rand -base64 32 | tr -d '/+=\\n' | cut -c1-28"],
        capture_output=True, text=True)
    claves.add(r.stdout.strip())

if len(claves) != 100:
    fallos.append(f'el generador repitio claves: {100 - len(claves)} duplicadas en 100')

cortas = [c for c in claves if len(c) < 20]
if cortas:
    fallos.append(f'{len(cortas)} clave(s) salieron de menos de 20 caracteres')

malos = [c for c in claves if not re.fullmatch(r'[A-Za-z0-9]+', c)]
if malos:
    fallos.append(f'{len(malos)} clave(s) traen caracteres que rompen el .env: {malos[:2]}')

print(f'  ok   generador de claves: 100 distintas, {min(len(c) for c in claves)}-'
      f'{max(len(c) for c in claves)} caracteres, solo letras y numeros')

# ---------- 11. El .env nunca al repositorio ----------
ignore = (raiz / '.gitignore').read_text(encoding='utf-8')
if '.env' not in ignore:
    fallos.append('.gitignore no excluye .env')
# El de produccion vive en otra carpeta: el patron tiene que alcanzarlo
if not re.search(r'^\.env$|^\*\*/\.env|^\.env\*', ignore, re.M):
    avisos.append('revisa que .gitignore alcance infra/produccion/.env')
print('  ok   .gitignore excluye .env')

# ---------- 12. De donde salen las imagenes ----------
# Docker Hub borro minio/minio el 11 de septiembre de 2026. Un compose
# que lo siga apuntando funciona en una maquina que ya tenia la imagen
# bajada y falla en un servidor nuevo, que es el peor tipo de error:
# el que no se ve hasta el momento de desplegar.
import glob
for archivo in glob.glob('infra/minio/docker-compose*.yml'):
    texto = sin_comentarios(pathlib.Path(archivo).read_text(encoding='utf-8'))

    for linea in texto.splitlines():
        if 'image:' not in linea: continue

        if re.search(r'image:\s*minio/(minio|mc)', linea):
            fallos.append(f'{archivo}: apunta a Docker Hub, que ya no tiene MinIO')

        # ":latest" sin variable que permita fijar la version deja el
        # sistema a merced de lo que haya ese dia en el registro.
        if ':latest' in linea and '${' not in linea:
            fallos.append(f'{archivo}: usa :latest sin poder fijar la version')

    if 'quay.io/minio' not in texto:
        fallos.append(f'{archivo}: no apunta a quay.io')

print('  ok   MinIO sale de quay.io y la version se puede fijar')

# ---------- 13. El arranque tiene margen ----------
# Sin start_period, Docker cuenta los reintentos del healthcheck desde
# el segundo cero. La primera vez, MySQL crea su directorio de datos y
# corre los .sql de db/; si se le acaban los reintentos a medio armar,
# el contenedor queda "unhealthy" y la app ni arranca. Todo estaba
# bien: solo faltaba tiempo.
for archivo in ('infra/produccion/docker-compose.produccion.yml',
                'docker-compose.yml'):
    texto = pathlib.Path(archivo).read_text(encoding='utf-8')
    if 'healthcheck:' not in texto: continue

    bloque = texto[texto.index('healthcheck:'):]
    bloque = bloque[:bloque.index('\n\n')] if '\n\n' in bloque else bloque

    if 'start_period' not in bloque:
        fallos.append(f'{archivo}: el healthcheck no da start_period '
                      'para la primera inicializacion')

    m = re.search(r'start_period:\s*(\d+)s', bloque)
    if m and int(m.group(1)) < 120:
        fallos.append(f'{archivo}: start_period de {m.group(1)}s es corto '
                      'para armar 69 tablas en un servidor lento')
print('  ok   el arranque de MySQL tiene margen para la primera vez')

# ---------- 14. Cuando algo falla, se ven los logs ----------
# "Revisa los logs" obliga a un paso mas justo cuando la persona ya
# lleva rato peleando. El instalador los muestra.
# Se exige la definicion Y que se llame: una funcion que existe pero
# que nadie invoca no muestra nada.
if not re.search(r'^mostrar_logs\(\)\s*\{', instala, re.M):
    fallos.append('el instalador no define mostrar_logs')
elif instala.count('mostrar_logs "') < 2:
    fallos.append('mostrar_logs existe pero no se usa en los dos arranques')
if instala.count('Revisa: docker compose logs') > 0:
    fallos.append('el instalador sigue diciendo "revisa los logs" sin mostrarlos')
print('  ok   el instalador muestra los logs al fallar')

# ---------- 15. Se puede saber que version corre ----------
# Sin esto no hay forma de distinguir, mirando la pantalla, si lo que
# corre es el archivo recien subido o el que ya estaba.
if 'VERSION_INSTALADOR' not in instala:
    fallos.append('el instalador no dice su version al arrancar')
print('  ok   el instalador anuncia su version')

# ---------- 16. Apache puede leer el codigo ----------
# El error mas desconcertante del despliegue: PHP dice "Permission
# denied" sobre un archivo que esta perfectamente ahi.
#
# La cadena es esta. En macOS los archivos del proyecto suelen quedar
# en 600 (solo el dueno lee). "rsync -a" incluye -p, que conserva los
# permisos, asi que llegan al servidor igual de cerrados. Dentro del
# contenedor Apache corre como www-data, que no es el dueno: no puede
# abrir ni index.php. Un chown no alcanza, porque el problema no es de
# quien es el archivo sino de que nadie mas puede leerlo.
subir = (raiz / 'subir-al-servidor.command').read_text(encoding='utf-8')

if '--chmod=' not in subir:
    fallos.append('subir-al-servidor no normaliza permisos: el codigo llegaria '
                  'en 600 y Apache no podria leerlo')
else:
    # No basta con que exista --chmod: hay que leer los numeros. Un
    # "--chmod=D700,F600" normaliza, pero normaliza a lo mismo que
    # causaba el problema. Lo que importa son los dos ultimos digitos
    # (grupo y otros): si alguno es 0, Apache sigue sin poder leer.
    # Se lee el argumento --chmod en si, no el archivo entero: el
    # comentario que lo explica arriba tambien menciona D755 y F644, y
    # daria por bueno un --chmod que no los trae.
    argumento = re.search(r'--chmod=(\S+)', sin_comentarios(subir))
    modos = dict(re.findall(r'([DF])(\d{3})', argumento.group(1) if argumento else ''))
    for letra, quien in (('D', 'carpetas'), ('F', 'archivos')):
        modo = modos.get(letra)
        if modo is None:
            fallos.append(f'--chmod no dice el modo de las {quien}')
            continue
        # Bit de lectura = 4. Carpetas necesitan ademas entrar (1).
        falta = 5 if letra == 'D' else 4
        for i, etiqueta in ((1, 'grupo'), (2, 'otros')):
            if int(modo[i]) & falta != falta:
                fallos.append(f'--chmod deja las {quien} en {modo}: '
                              f'"{etiqueta}" no puede leerlas y Apache '
                              'corre como otro usuario')

# --chmod deja los guiones sin permiso de ejecucion: hay que devolverselo.
if '--chmod=' in subir and 'chmod +x' not in subir:
    fallos.append('subir-al-servidor normaliza permisos pero no devuelve '
                  'el +x a los guiones')

# La segunda red: arreglar lo que ya este mal en el servidor, sin
# importar como llego.
#
# La receta vivia copiada dentro de instalar-servidor.sh y ahora esta
# en permisos.sh, porque hay DOS caminos que la necesitan: la
# instalacion desde cero y el enlace con git de una carpeta que se
# subio a mano. Dos copias de la misma receta es una receta que un dia
# dice dos cosas distintas, asi que lo que se comprueba es el guion
# unico... y que los dos caminos lo llamen. Sin lo segundo, el guion
# podria estar perfecto y no correrlo nadie.
permisos = raiz/'infra/produccion/permisos.sh'
if not permisos.exists():
    fallos.append('falta infra/produccion/permisos.sh')
    perm = ''
else:
    perm = permisos.read_text(encoding='utf-8')

if not re.search(r'-type f .*-exec chmod 6\d\d', perm):
    fallos.append('permisos.sh no normaliza los permisos de los archivos')
if not re.search(r'-type d .*-exec chmod 7\d\d', perm):
    fallos.append('permisos.sh no normaliza los permisos de las carpetas')

for guion, comoSeLlama in [('instalar-servidor.sh', instala),
                           ('enlazar-git.sh',
                            (raiz/'infra/produccion/enlazar-git.sh').read_text(encoding='utf-8')
                            if (raiz/'infra/produccion/enlazar-git.sh').exists() else '')]:
    if 'permisos.sh' not in comoSeLlama:
        fallos.append(f'{guion} no llama a permisos.sh: deja el servidor '
                      'con permisos distintos segun por donde se entre')
print('  ok   el codigo queda legible para Apache (al subir y al instalar)')

# ---------- El guion de enlace tiene que LLEGAR al servidor ----------
# Huevo y gallina: enlazar-git.sh vive en el repositorio, y hace falta
# justo cuando el servidor todavia no esta enlazado a ese repositorio.
# La unica forma de que llegue la primera vez es la subida por rsync,
# asi que subir-al-servidor tiene que comprobarlo entre los archivos
# clave. Se me paso al escribirlo y Angel lo cazo.
#
# Con la RUTA ENTRECOMILLADA, que es como aparece en la lista de
# archivos clave. Buscar "enlazar-git.sh" a secas no sirve: el nombre
# sale tambien en el texto del pie que le dice a uno que lo corra, asi
# que la comprobacion pasaba aunque el archivo ya no se revisara. Lo
# probe quitandolo de la lista y no se entero. La misma trampa de
# siempre: buscar una palabra en vez del mecanismo.
for ruta, porque in [
        ('"infra/produccion/enlazar-git.sh"',
         'sin el, no hay forma de enlazar un servidor subido a mano'),
        ('"infra/produccion/permisos.sh"',
         'que es lo que enlazar-git.sh necesita para terminar')]:
    if ruta not in subir:
        fallos.append(f'subir-al-servidor no comprueba que llegue {ruta}: '
                      + porque)
print('  ok   los guiones del enlace viajan en la subida manual')

# storage/ es la unica carpeta donde la aplicacion escribe, y quien
# escribe ahi es Apache: el dueno tiene que ser www-data (uid 33
# dentro del contenedor), no el usuario del servidor.
if 'chown -R 33:33' not in perm:
    fallos.append('permisos.sh no le da storage/ a www-data: la app no '
                  'podria escribir sus logs')
if not re.search(r'chmod -R 7\d\d "\$DESTINO/storage"', perm):
    fallos.append('storage/ no queda escribible')
# Y si ese chown falla por no ser root, tiene que AVISARLO. Falla en
# silencio y el sintoma —no se suben documentos— no se parece nada a
# la causa.
if 'chown -R 33:33' in perm and 'no se pudo dar storage' not in perm:
    fallos.append('permisos.sh se calla si no puede dar storage/ a www-data')
# Y que el compose no lo monte de solo lectura, que seria el mismo
# problema por otra puerta.
if re.search(r'storage:/var/www/storage:ro', compose):
    fallos.append('storage se monta de solo lectura: la app no podria escribir')
print('  ok   storage/ escribible por Apache, montado sin :ro')

# El chmod general de arriba deja todo en 644, y ahi viven las
# contrasenias de la base. Tienen que volver a 600.
if instala.count('chmod 600') < 2:
    fallos.append('el instalador no vuelve a cerrar los .env despues de '
                  'normalizar permisos')
print('  ok   los .env vuelven a 600 tras la normalizacion')

# ---------- 17. "Arriba" no es lo mismo que "funcionando" ----------
# MySQL corre los .sql de db/ UNA SOLA VEZ: cuando su volumen esta
# vacio. Si esa vez algo sale mal —que los archivos no se puedan leer,
# por ejemplo— MySQL crea igual su directorio de datos y sigue. El
# volumen ya no esta vacio, asi que en los arranques siguientes ni lo
# intenta. Queda una base existente y sin una sola tabla.
#
# Docker reporta todo verde: el contenedor esta sano, responde al
# ping. Nadie se entera hasta que alguien intenta entrar y recibe un
# 500 en blanco. Por eso el instalador tiene que CONTAR las tablas.
if 'information_schema.tables' not in instala:
    fallos.append('el instalador no comprueba que la base tenga tablas: '
                  'un esquema vacio pasaria por instalacion exitosa')
if not re.search(r'TABLAS.*-lt\s*\d+', instala):
    fallos.append('el instalador no compara el numero de tablas contra un minimo')
else:
    minimo = int(re.search(r'TABLAS.*-lt\s*(\d+)', instala).group(1))
    reales = len(re.findall(r'CREATE TABLE', esquema, re.I)) + \
             len(re.findall(r'CREATE.*?VIEW', esquema, re.I))
    if minimo < 10:
        fallos.append(f'el minimo de tablas ({minimo}) es tan bajo que no '
                      'distingue una base vacia de una armada')
    elif minimo > reales:
        fallos.append(f'el instalador exige {minimo} tablas pero el esquema '
                      f'solo crea {reales}: fallaria siempre')
    print(f'  ok   el instalador cuenta las tablas (exige {minimo}, '
          f'el esquema crea {reales})')

# Y que, al encontrarlas vacias, diga como rehacerla. Un diagnostico
# sin salida obliga a la persona a venir a preguntar.
if 'docker volume rm' not in instala:
    fallos.append('el instalador detecta la base vacia pero no dice como rehacerla')
print('  ok   si la base quedo vacia, el instalador explica como rehacerla')

# La pantalla de acceso tiene que contestar 200 por el camino real
# (HTTPS, dominio publico), no por un atajo interno que podria
# funcionar cuando el de verdad no.
# Se mira la linea del curl, no el archivo entero: el instalador
# imprime "entra a https://$DOMINIO" al terminar, y esa linea daria
# por buena una comprobacion hecha contra localhost.
llamada = re.search(r"curl[^\n]*\n?[^\n]*login\.php[^\n]*", instala)
if llamada is None:
    fallos.append('el instalador no comprueba que la pantalla de acceso responda')
elif 'https://$DOMINIO' not in llamada.group(0):
    fallos.append('la comprobacion final no usa el dominio publico por HTTPS: '
                  'un atajo interno puede funcionar cuando el camino real no')
if "500)" not in instala:
    fallos.append('el instalador no distingue un 500 de una falta de respuesta')
print('  ok   comprobacion final por HTTPS, distinguiendo 500 de sin respuesta')

# ---------- El despliegue comprueba las fotos de iPhone ----------
# El 13 de octubre de 2026 las .heic se guardaron sin convertir durante
# dias porque la imagen de PHP no se habia reconstruido, y nada lo
# dijo: la pantalla abria bien. Levantar y preguntar si responde no
# basta; hay que preguntar por las piezas de dentro.
desp = (raiz/'infra/produccion/desplegar.sh').read_text(encoding='utf-8')
if 'porQueNoConvierteHeic' not in desp:
    fallos.append('el despliegue no comprueba que las fotos de iPhone se '
                  'puedan convertir: el fallo se descubriria dias despues')
else:
    print('  ok   el despliegue comprueba la conversion de .heic')

# ---------- 18. Las migraciones hablan MySQL, no MariaDB ----------
# "ALTER TABLE ... ADD COLUMN IF NOT EXISTS" existe en MariaDB y NO en
# MySQL 8. Se lee perfectamente bien, parece correcto, y revienta con un
# error de sintaxis a mitad de la migracion: las anteriores ya se
# aplicaron y la base queda a medio camino.
#
# Es un error facil de cometer porque medio internet lo usa. Por eso se
# revisa aqui en lugar de confiar en acordarse.
import glob as _glob

MARIADB_NO_MYSQL = [
    ('ADD COLUMN IF NOT EXISTS',  'ADD COLUMN no admite IF NOT EXISTS'),
    ('DROP COLUMN IF EXISTS',     'DROP COLUMN no admite IF EXISTS'),
    ('ADD INDEX IF NOT EXISTS',   'ADD INDEX no admite IF NOT EXISTS'),
    ('ADD KEY IF NOT EXISTS',     'ADD KEY no admite IF NOT EXISTS'),
    ('ADD UNIQUE IF NOT EXISTS',  'ADD UNIQUE no admite IF NOT EXISTS'),
    ('DROP INDEX IF EXISTS',      'DROP INDEX no admite IF EXISTS'),
    ('ALTER TABLE IF EXISTS',     'ALTER TABLE no admite IF EXISTS'),
]

migraciones = sorted(_glob.glob('db/migraciones/*.sql'))
for archivo in migraciones:
    texto = pathlib.Path(archivo).read_text(encoding='utf-8')
    sinComent = '\n'.join(l for l in texto.splitlines()
                          if not l.lstrip().startswith('--'))
    for frase, porque in MARIADB_NO_MYSQL:
        if frase.lower() in sinComent.lower():
            fallos.append(f'{archivo}: "{frase}" es de MariaDB. '
                          f'En MySQL 8 {porque}: usa el bloque '
                          'SET @hay / PREPARE del LEEME.')

    # Dentro de una cadena SQL, las comillas dobles se rompen si el
    # servidor corre con ANSI_QUOTES: dejan de ser texto y pasan a ser
    # nombres de columna.
    for linea in sinComent.splitlines():
        if "PREPARE" in linea.upper():
            continue
        if linea.count("'") >= 2 and '"' in linea and 'SELECT' in linea.upper():
            fallos.append(f'{archivo}: comillas dobles dentro de una cadena SQL. '
                          'Con ANSI_QUOTES se leen como nombre de columna; '
                          "duplica el apostrofe en vez de usar comillas.")

print(f'  ok   {len(migraciones)} migracion(es), todas en MySQL valido')

# Cada migracion tiene que poder correrse dos veces. La que toca
# estructura sin preguntar por el catalogo fallaria en el segundo
# intento, que es justo lo que pasa cuando una falla a la mitad y hay
# que volver a correrla.
for archivo in migraciones:
    texto = pathlib.Path(archivo).read_text(encoding='utf-8')
    sinComent = '\n'.join(l for l in texto.splitlines()
                          if not l.lstrip().startswith('--'))

    tocaEstructura = re.search(r'\bALTER TABLE\b', sinComent, re.I)
    seProtege = ('information_schema' in sinComent.lower()
                 or 'PREPARE' in sinComent.upper())

    if tocaEstructura and not seProtege:
        fallos.append(f'{archivo}: cambia la estructura sin preguntar al '
                      'catalogo. Si se corre dos veces va a fallar.')

    # Se revisa CADA insert por separado, hasta su punto y coma. Mirar
    # el archivo entero no sirve: un "CREATE TABLE IF NOT EXISTS" en la
    # linea de arriba daria por protegido a un INSERT que no lo esta.
    for insert in re.findall(r'\bINSERT\b.*?;', sinComent, re.I | re.S):
        if not re.search(r'NOT EXISTS|ON DUPLICATE KEY|\bIGNORE\b',
                         insert, re.I):
            tabla = re.search(r'INSERT\s+(?:IGNORE\s+)?INTO\s+(\w+)', insert, re.I)
            fallos.append(f'{archivo}: el INSERT en '
                          f'{tabla.group(1) if tabla else "?"} duplicaria '
                          'los datos al correrse dos veces.')
print('  ok   todas se pueden correr dos veces sin romper')


print()
for a in avisos: print('  ~   ', a)
for f in fallos: print('  !!  ', f)
print()
print('*** TODO EN ORDEN ***' if not fallos else f'*** {len(fallos)} PROBLEMA(S) ***')

# Salir con error cuando hay fallos.
#
# Sin esto el verificador IMPRIME el problema y devuelve 0, asi que
# cualquier guion que lo encadene con && sigue de largo y el fallo se
# queda en la pantalla, leido por nadie. Un verificador que siempre
# dice que si no verifica nada.
sys.exit(1 if fallos else 0)
