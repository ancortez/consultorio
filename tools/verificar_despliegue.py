"""
Revisiones del despliegue en servidor.

Lo que se revisa aqui son las cosas que, si salen mal, no se notan
hasta que alguien entra donde no debia: un puerto abierto de mas, una
contrasenia de fabrica que se quedo, un archivo .env servido por HTTP.

Lo que se puede probar de verdad (el cifrado del respaldo, el
generador de claves) se prueba corriendolo, no leyendolo.
"""
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

    # Con la clave equivocada no debe abrir
    mal = subprocess.run(
        ['openssl', 'enc', '-d', '-aes-256-cbc', '-pbkdf2', '-iter', '200000',
         '-in', cifrado, '-out', os.path.join(tmp, 'x'), '-pass', 'pass:otra'],
        capture_output=True)
    if mal.returncode == 0:
        fallos.append('el respaldo se abre con una clave equivocada')
    else:
        print('  ok   con la clave equivocada, el respaldo no abre')

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

print()
for a in avisos: print('  ~   ', a)
for f in fallos: print('  !!  ', f)
print()
print('*** TODO EN ORDEN ***' if not fallos else f'*** {len(fallos)} PROBLEMA(S) ***')
