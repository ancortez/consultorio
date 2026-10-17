#!/usr/bin/env python3
"""
Configuracion > Respaldos: crear, descargar y restaurar desde el sistema.

Por que existe este arnes
-------------------------
Angel pidio poder hacerlo sin entrar al servidor por ssh, y el motivo de
fondo es bueno: bajar una copia tiene que ser facil, porque lo que
cuesta trabajo no se hace.

Lo que se construyo es, a la vez, la pantalla mas peligrosa del sistema,
y por dos motivos distintos:

  1. LO QUE SE DESCARGA es el expediente clinico completo de todos los
     pacientes, en texto plano y en un solo archivo. Es el objeto mas
     sensible que existe aqui.

  2. RESTAURAR reemplaza TODOS los expedientes por los de otra fecha.
     Picado por error, el trabajo de semanas desaparece de la pantalla
     sin que nada se vea roto.

Asi que las trampas que hay que cazar son de seguridad, no de interfaz:

  a) El nombre del archivo llega por la direccion. Sin una lista blanca,
     "nombre=../../etc/passwd" convierte la descarga de respaldos en una
     descarga de cualquier cosa del servidor.

  b) El permiso. Si esto cuelga de config.consultorio —como las otras
     pantallas de Configuracion— recepcion podria bajarse el expediente
     de todos los pacientes.

  c) La contrasenia de la base. Pasada como --password=xxx, cualquiera
     que corra "ps" en el servidor la ve.

  d) Restaurar sin respaldo previo, o restaurando un archivo truncado:
     las dos cosas dejan la base a medias y el sistema abriendo como si
     nada.

No hay PHP en esta maquina: las reglas se portan y se corren, y lo demas
se comprueba sobre los archivos.
"""
import pathlib
import re
import sys

RAIZ = pathlib.Path(__file__).resolve().parent.parent
FALLOS = []


def comprobar(nombre, obtenido, esperado):
    if obtenido != esperado:
        FALLOS.append(f'{nombre}\n      esperaba: {esperado!r}\n'
                      f'      obtuvo:   {obtenido!r}')
    else:
        print(f'  ok   {nombre}')


def leer(rel):
    return (RAIZ / rel).read_text(encoding='utf-8')


def sin_comentarios(texto):
    texto = re.sub(r'/\*[\s\S]*?\*/', '', texto)
    texto = re.sub(r'(?m)^\s*(//|#|--)\s.*$', '', texto)
    texto = re.sub(r'(?m)\s+(//|#)\s.*$', '', texto)
    return texto


nucleo = leer('includes/core/respaldos.php')
codigo = sin_comentarios(nucleo)


# =====================================================================
#  1. El nombre del archivo: la lista blanca
# =====================================================================
print('Lo que llega por la direccion')

# El patron se LEE del PHP: escribirlo a mano aqui probaria mi copia, no
# la de verdad.
_m = re.search(r"private const PATRON = '/(.+)/';", codigo)
if _m is None:
    FALLOS.append('no encuentro el patron de nombres en el nucleo')
    PATRON = None
else:
    # El PHP usa \d y \. igual que Python: la expresion se puede usar
    # tal cual.
    PATRON = re.compile(_m.group(1))

comprobar('el nucleo tiene un patron de nombres', PATRON is not None, True)

if PATRON is not None:
    BUENOS = [
        'base-20261017-0230.sql',
        'base-20261017-0230.tar.gz',
        'base-20261017-0230.tar.gz.enc',
        'archivos-20261012-0230.tar.gz.enc',
    ]
    for n in BUENOS:
        if not PATRON.match(n):
            FALLOS.append(f'el patron rechaza un nombre valido: {n}')
    comprobar('acepta los nombres que escribe respaldar.sh',
              [f for f in FALLOS if 'rechaza un nombre valido' in f], [])

    # La trampa (a). Cada uno de estos es un intento real de salir de la
    # carpeta o de pedir otra cosa.
    MALOS = [
        '../../etc/passwd',
        '../infra/produccion/.env',
        'base-20261017-0230.sql/../../../etc/shadow',
        '/etc/passwd',
        'base-20261017-0230.sql%00.png',
        'base-2026-0230.sql',             # fecha mal formada
        'cualquier-cosa.sql',
        '.env',
        'base-20261017-0230.php',
        '',
    ]
    for n in MALOS:
        if PATRON.match(n):
            FALLOS.append(f'el patron ACEPTA un nombre peligroso: {n!r}')
    comprobar('rechaza todo lo que intenta salirse de la carpeta',
              [f for f in FALLOS if 'ACEPTA un nombre' in f], [])

# Y ademas basename. Es redundante hoy —el patron ya no deja pasar
# diagonales— y es la clase de redundancia que vale: el dia que alguien
# afloje la expresion, el basename sigue impidiendo que la ruta se
# escape.
comprobar('la ruta ademas se pasa por basename',
          'basename($nombre)' in codigo, True)


# =====================================================================
#  2. El permiso
# =====================================================================
print()
print('Quien puede entrar')

# La trampa (b): si cuelga de config.consultorio, recepcion se baja el
# expediente de todos los pacientes.
for rel in ('includes/configuracion/respaldos_listar.php',
            'includes/configuracion/respaldo_crear.php',
            'includes/configuracion/respaldo_restaurar.php'):
    texto = sin_comentarios(leer(rel))
    nombre = pathlib.Path(rel).name

    if "endpoint('config.respaldos')" not in texto:
        FALLOS.append(f'{nombre}: no exige el permiso config.respaldos')

comprobar('los tres endpoints exigen config.respaldos',
          [f for f in FALLOS if 'config.respaldos' in f], [])

bajar = sin_comentarios(leer('respaldo_bajar.php'))
comprobar('la descarga exige sesion',
          'Auth::exigirLogin()' in bajar, True)
comprobar('y el permiso',
          "Auth::exigirPermiso('config.respaldos')" in bajar, True)

# El permiso tiene que existir en el catalogo y en la migracion, y NO
# puede estar en ningun rol que no sea el administrador.
catalogo = leer('db/02_catalogos.sql')
comprobar('el permiso esta en el catalogo',
          "'config.respaldos'" in catalogo, True)

# Los roles 2, 3 y 4 se cargan con listas de claves. El permiso no
# puede aparecer en ninguna.
desdeDentista = catalogo[catalogo.index('-- Dentista'):]
comprobar('ningun rol que no sea el administrador lo tiene',
          'config.respaldos' in desdeDentista, False)

comprobar('hay migracion para las bases que ya existen',
          (RAIZ / 'db/migraciones/0019_permiso_respaldos.sql').exists(), True)

migracion = leer('db/migraciones/0019_permiso_respaldos.sql')
comprobar('la migracion es repetible',
          migracion.count('NOT EXISTS') >= 2, True)
# El permiso se asigna buscandolo por CLAVE: los ids dependen del orden
# en que se cargo el catalogo y no son iguales en dos instalaciones.
comprobar('el permiso se busca por clave, no por id',
          "p.clave = 'config.respaldos'" in migracion, True)
comprobar('y se asigna solo al rol 1',
          re.search(r'SELECT 1, p\.id_permiso', migracion) is not None, True)

menu = leer('vistas/configuracion/_menu_lateral.php')
comprobar('el menu lo esconde si no hay permiso',
          "'permiso' => 'config.respaldos'" in menu, True)


# =====================================================================
#  3. La contrasenia de la base
# =====================================================================
print()
print('Como se le habla a MySQL')

# La trampa (c).
if re.search(r'--password=', codigo):
    FALLOS.append('la contrasenia va en la linea de comandos: cualquiera '
                  'que corra "ps" en el servidor la ve')
comprobar('la contrasenia no va en la linea de comandos',
          [f for f in FALLOS if 'linea de comandos' in f], [])
comprobar('se le pasa por un archivo de configuracion',
          '--defaults-extra-file=' in codigo, True)
comprobar('que se borra siempre, pase lo que pase',
          codigo.count('} finally {') >= 2
          and '@unlink($claves)' in codigo, True)
comprobar('y que solo lo lee su dueño',
          'chmod($tmp, 0600)' in codigo, True)

# Sin shell: la forma de arreglo de proc_open no pasa por uno, asi que
# ningun valor puede convertirse en otro comando.
comprobar('los programas se corren sin shell',
          'proc_open($comando' in codigo, True)
comprobar('el comando se arma como arreglo',
          "'mysqldump'," in codigo and "'mysql'," in codigo, True)

# El codigo de salida se lee de proc_get_status, no de proc_close:
# habiendo llamado a proc_get_status, PHP ya recogio al hijo y
# proc_close devuelve -1 siempre. Eso costo cuatro diagnosticos
# equivocados con la conversion de HEIC.
comprobar('el codigo de salida sale de proc_get_status',
          "$estado['exitcode']" in codigo, True)
# Y el 127 se traduce: "no se pudo volcar" no dice que falta reconstruir
# la imagen.
comprobar('falta mysqldump se dice con sus palabras',
          'docker compose build app' in nucleo, True)

# Y el cliente tiene que estar en la imagen, o nada de esto corre.
# Sobre el Dockerfile SIN comentarios: el parrafo que explica por que
# se instala lo nombra, asi que buscandolo en el archivo completo la
# comprobacion encontraba su propia explicacion y pasaba aunque el
# paquete hubiera salido del apt-get. Es la octava vez que caigo en
# esto en este proyecto; por eso la funcion existe.
_dockerfile = re.sub(r'(?m)^\s*#.*$', '', leer('docker/php/Dockerfile'))
comprobar('la imagen instala el cliente de MySQL',
          'default-mysql-client' in _dockerfile, True)
# Y NO el socket de Docker: un contenedor con ese socket puede arrancar
# otro con el disco del servidor montado.
compose = leer('infra/produccion/docker-compose.produccion.yml')
comprobar('el contenedor NO recibe el socket de Docker',
          'docker.sock' in compose, False)
comprobar('pero si la carpeta de respaldos',
          '/var/respaldos' in compose, True)


# =====================================================================
#  4. Crear: las guardias
# =====================================================================
print()
print('Crear un respaldo')

crear = codigo[codigo.index('function crearDeLaBase'):]
crear = crear[:crear.index('function pesoDeLaBase')]

comprobar('se mira el espacio libre antes de empezar',
          'disk_free_space' in crear, True)
# Un volcado de menos de 10 KB no es un volcado: el esquema solo pesa
# mas. Y lo que se guarda no puede ser un archivo vacio que nadie va a
# abrir hasta el dia que lo necesite.
comprobar('un volcado vacio no se guarda',
          '< 10240' in crear and '@unlink($ruta)' in crear, True)
comprobar('no se pisa uno del mismo minuto',
          'file_exists($ruta)' in crear, True)
# Trae el expediente completo: solo el dueño lo lee.
comprobar('el archivo queda con permisos cerrados',
          'chmod($ruta, 0600)' in crear, True)
comprobar('y queda en la bitacora',
          "Bitacora::registrar('configuracion', 'ALTA', 'respaldo'" in crear,
          True)


# =====================================================================
#  5. Restaurar: lo que no se deshace
# =====================================================================
print()
print('Restaurar')

restaurar = codigo[codigo.index('function restaurar('):]
restaurar = restaurar[:restaurar.index('function revisarVolcado')]

# La trampa (d), primera mitad: el respaldo de seguridad va ANTES, y si
# falla no se restaura. Sin red abajo no se salta.
comprobar('se hace un respaldo de seguridad antes de tocar nada',
          'self::crearDeLaBase()' in restaurar, True)
comprobar('y antes de restaurar, no despues',
          restaurar.index('crearDeLaBase')
          < restaurar.index('correrMysqlRestaurando'), True)
comprobar('el archivo se revisa antes que nada',
          restaurar.index('revisarVolcado')
          < restaurar.index('crearDeLaBase'), True)

# Solo la base, y solo sin cifrar: una clave de descifrado escrita en un
# formulario web acaba en el historial del navegador y en los registros
# del servidor.
comprobar('no se restaura un respaldo de archivos',
          'Solo se puede restaurar un respaldo de la base' in nucleo, True)
comprobar('ni uno cifrado',
          "str_ends_with($nombre, '.enc')" in restaurar, True)

# La trampa (d), segunda mitad: un archivo truncado empieza perfecto. Lo
# que le falta es el final.
# Hasta la funcion siguiente: el corte por un comentario no sirve, los
# comentarios ya se quitaron de 'codigo'.
revisar = codigo[codigo.index('function revisarVolcado'):]
revisar = revisar[:revisar.index('function archivoDeClave')]
comprobar('se revisa el principio del archivo',
          'CREATE TABLE' in revisar or 'MySQL dump' in revisar, True)
comprobar('y tambien el FINAL, que es lo que le falta a uno truncado',
          'Dump completed' in revisar, True)

# Si la restauracion se cae a la mitad, el aviso tiene que ser el peor
# de todos: la base queda con unas tablas nuevas y otras viejas, y el
# sistema abre y miente.
comprobar('si falla a medias se dice que no se siga usando el sistema',
          'NO sigas usando el sistema' in nucleo, True)

endpoint = sin_comentarios(leer('includes/configuracion/respaldo_restaurar.php'))
# La palabra escrita a mano: un "¿seguro?" se contesta que si sin leer,
# y esto no se deshace.
comprobar('pide escribir RESTAURAR a mano',
          "!== 'RESTAURAR'" in endpoint, True)
comprobar('y queda en la bitacora',
          "'RESTAURA'" in codigo, True)


# =====================================================================
#  6. Lo que esta pantalla NO hace
# =====================================================================
print()
print('Lo que no hace, a proposito')

# No borra. La rotacion la hace respaldar.sh, que sabe cuantos conservar
# de cada tipo. Un boton de borrar al lado de uno de restaurar es la
# clase de vecindad que acaba en un clic equivocado sobre lo unico que
# quedaba.
if re.search(r'\bunlink\(\$ruta\)', codigo.replace('@unlink($ruta);', '')):
    FALLOS.append('el nucleo borra respaldos: eso lo hace respaldar.sh')
comprobar('no borra respaldos', [f for f in FALLOS if 'borra respaldos' in f],
          [])

js = sin_comentarios(leer('assets/js/configuracion.js'))
comprobar('la pantalla no ofrece borrar',
          'data-borrar' in js and 'respaldo' in js
          and 'eliminar-respaldo' in js, False)

# Y no respalda los archivos del expediente: pesan gigabytes y viven en
# el volumen de MinIO, al que este contenedor no tiene acceso ni debe
# tenerlo. La pantalla lo dice para que no parezca un olvido.
vista = leer('vistas/configuracion/respaldos.php')
comprobar('la pantalla explica que los archivos se respaldan aparte',
          'respaldar.sh --completo' in vista, True)
comprobar('y que estos respaldos viven en el mismo servidor',
          'mismo servidor' in vista, True)


# =====================================================================
print()
if FALLOS:
    print(f'  *** {len(FALLOS)} fallo(s):')
    for f in FALLOS:
        print(f'  !!   {f}')
    sys.exit(1)
print('  Todo en orden: se crea, se baja y se restaura sin abrir agujeros.')
