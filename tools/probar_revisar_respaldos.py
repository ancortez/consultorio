#!/usr/bin/env python3
"""
El guion que dice si se estan haciendo los respaldos.

Por que existe este arnes
-------------------------
Angel pregunto como darse cuenta de si los respaldos corren, y la
respuesta fue un guion de diagnostico: revisar-respaldos.sh.

Un guion de diagnostico tiene un problema propio y serio: si se
equivoca, se equivoca diciendo que TODO ESTA BIEN. Y a eso nadie le
pide explicaciones. Un respaldo que no existe mas un revisor que dice
que si existe es peor que no tener revisor, porque el revisor tapa la
unica senial que quedaba.

Asi que aqui se le monta un servidor de mentira —carpetas, un cron
falso, respaldos con fechas puestas a mano— y se comprueba que
distingue los casos. Sobre todo los malos: hay un caso por cada cosa
que puede estar rota en el servidor de la doctora.

El guion se corre de verdad, con bash. No se lee: se ejecuta y se mira
que dice y con que codigo sale.
"""
import os
import pathlib
import re
import shutil
import subprocess
import sys
import tempfile
import time

RAIZ = pathlib.Path(__file__).resolve().parent.parent
GUION = RAIZ / 'infra/produccion/revisar-respaldos.sh'
FALLOS = []


def comprobar(nombre, obtenido, esperado):
    if obtenido != esperado:
        FALLOS.append(f'{nombre}\n      esperaba: {esperado!r}\n'
                      f'      obtuvo:   {obtenido!r}')
    else:
        print(f'  ok   {nombre}')


class ServidorDeMentira:
    """
    Un servidor con lo justo para que el guion crea que esta en uno.

    Lo que lo convence es el infra/produccion/.env: esa es la marca que
    usa solo-en-el-servidor.sh, y existe justamente para que estos
    guiones no se corran por error en la Mac de Angel.
    """

    def __init__(self, env_extra=''):
        self.base = pathlib.Path(tempfile.mkdtemp())
        self.produccion = self.base / 'infra/produccion'
        self.produccion.mkdir(parents=True)

        # El guion se copia, junto con el guardia que carga con source.
        for nombre in ('revisar-respaldos.sh', 'solo-en-el-servidor.sh',
                       'respaldar.sh'):
            shutil.copy(RAIZ / 'infra/produccion' / nombre, self.produccion)

        self.respaldos = self.base / 'respaldos'
        self.respaldos.mkdir()

        self.log = self.base / 'respaldo.log'
        self.cron = self.base / 'cron-consultorio'

        (self.produccion / '.env').write_text(
            'DB_NAME=consultorio\n'
            'DB_ROOT_PASS=secreta\n'
            f'CARPETA_RESPALDOS={self.respaldos}\n'
            'MINIMO_LIBRE_GB=1\n'
            + env_extra,
            encoding='utf-8')

    def respaldo(self, nombre, kb=200, hace_horas=1):
        """Un archivo de respaldo con su peso y su edad."""
        f = self.respaldos / nombre
        f.write_bytes(b'x' * (kb * 1024))
        cuando = time.time() - hace_horas * 3600
        os.utime(f, (cuando, cuando))
        return f

    def poner_cron(self, texto=None):
        self.cron.write_text(texto if texto is not None else (
            'SHELL=/bin/bash\n'
            f'30 2 * * * alguien {self.produccion}/respaldar.sh '
            f'>> {self.log} 2>&1\n'), encoding='utf-8')

    def poner_log(self, texto):
        self.log.write_text(texto, encoding='utf-8')

    def correr(self, *args):
        entorno = dict(os.environ,
                       RESPALDO_LOG=str(self.log),
                       RESPALDO_CRON=str(self.cron))
        r = subprocess.run(
            ['bash', str(self.produccion / 'revisar-respaldos.sh'), *args],
            capture_output=True, text=True, env=entorno)
        # Sin los colores: estorban al buscar texto.
        salida = re.sub(r'\x1b\[[0-9;]*m', '', r.stdout + r.stderr)
        return r.returncode, salida

    def tirar(self):
        shutil.rmtree(self.base, ignore_errors=True)


LOG_BUENO = (
    '[2026-10-17 02:30:00] === Respaldo 20261017-0230 ===\n'
    '[2026-10-17 02:30:01] Volcando la base de datos...\n'
    '[2026-10-17 02:30:04]   base: 2.1M\n'
    '[2026-10-17 02:30:05] === Fin ===\n'
)


# =====================================================================
#  1. No se corre fuera del servidor
# =====================================================================
print('El guardia')

s = ServidorDeMentira()
(s.produccion / '.env').unlink()
codigo, salida = s.correr()
comprobar('sin el .env del servidor se niega a correr', codigo, 1)
comprobar('y explica que esto va dentro del servidor',
          'DENTRO del servidor' in salida, True)
s.tirar()


# =====================================================================
#  2. Todo en orden
# =====================================================================
print()
print('Un servidor que si respalda')

s = ServidorDeMentira('CLAVE_RESPALDO=unaclave\n'
                      'COPIA_FUERA="disco externo"\n')
s.poner_cron()
s.poner_log(LOG_BUENO)
s.respaldo('base-20261017-0230.tar.gz.enc', kb=2048, hace_horas=5)
s.respaldo('base-20261016-0230.tar.gz.enc', kb=2048, hace_horas=29)
s.respaldo('archivos-20261012-0230.tar.gz.enc', kb=50000, hace_horas=5 * 24)

codigo, salida = s.correr()

# El volumen de MinIO no existe aqui, asi que ese punto se va a quejar:
# es correcto y es justo lo que diria en un servidor con MinIO apagado.
sinMinio = 'no encuentro el volumen de MinIO' in salida

comprobar('ve la tarea automatica', 'esta puesta en' in salida, True)
comprobar('ve el respaldo de la base al dia',
          re.search(r'base-20261017.*\n.*hace 5h', salida) is not None, True)
comprobar('ve el respaldo de los archivos',
          'archivos-20261012' in salida, True)
comprobar('y dice que los archivos estan al dia',
          'hace 5 dia(s)' in salida, True)
comprobar('ve que van cifrados',
          'los respaldos nuevos van cifrados' in salida, True)
comprobar('ve que el ultimo intento termino bien',
          'termino bien' in salida, True)
comprobar('ve la copia de fuera', 'disco externo' in salida, True)

# Lo unico que deberia quejarse es lo que esta maquina no tiene: el
# volumen de MinIO y el servicio cron. Las dos quejas son correctas
# —en un servidor con MinIO apagado o cron muerto hay que decirlo— y
# aqui son ruido del laboratorio, no del guion.
RUIDO = ('volumen de MinIO', 'cron NO esta corriendo')
quejas = [l for l in salida.splitlines() if l.strip().startswith('!!')]
otras = [q for q in quejas if not any(r in q for r in RUIDO)]
comprobar('no se queja de nada mas que de lo que falta en esta maquina',
          otras, [])
s.tirar()


# =====================================================================
#  3. Los casos malos, uno por uno
# =====================================================================
print()
print('Lo que tiene que cazar')


def caso(nombre, preparar, texto_esperado, env_extra=''):
    """Monta un servidor roto de una forma y comprueba que se note."""
    s = ServidorDeMentira(env_extra)
    preparar(s)
    codigo, salida = s.correr()

    quejas = '\n'.join(
        l for l in salida.splitlines() if l.strip().startswith('!!'))

    # Dos cosas: que lo diga, y que salga con codigo 1. Sin el codigo no
    # sirve para encadenarlo ni para mandarlo por correo cuando falla.
    comprobar(f'caza: {nombre}',
              (texto_esperado.lower() in quejas.lower(), codigo),
              (True, 1))
    s.tirar()


def completo(s):
    """Un servidor correcto, del que cada caso rompe una sola cosa."""
    s.poner_cron()
    s.poner_log(LOG_BUENO)
    s.respaldo('base-20261017-0230.tar.gz.enc', kb=2048, hace_horas=5)
    s.respaldo('archivos-20261012-0230.tar.gz.enc', kb=50000, hace_horas=5 * 24)


# El caso que importa de verdad: este servidor se monto a mano subiendo
# los archivos por SFTP, asi que instalar-servidor.sh pudo no correr y
# la tarea puede no existir. El sistema funciona igual de bien sin
# respaldar, y nada lo dice.
caso('que no haya tarea automatica',
     lambda s: completo(s) or s.cron.unlink(),
     'NO hay tarea automatica')

caso('que no haya ningun respaldo de la base',
     lambda s: (s.poner_cron(), s.poner_log(LOG_BUENO)),
     'no hay NINGUN respaldo de la base')

# Mas de 48 horas significa que la tarea dejo de correr: la base se
# respalda todas las noches.
# El viejo tiene que ser el UNICO: el guion mira el MAS RECIENTE, asi
# que dejando tambien uno de hace cinco horas no habria nada que cazar.
# Esto me lo ensenio el propio arnes: el caso pasaba en verde porque la
# preparacion se contradecia a si misma.
caso('que la base lleve dias sin respaldarse',
     lambda s: (s.poner_cron(), s.poner_log(LOG_BUENO),
                s.respaldo('base-20261010-0230.tar.gz.enc',
                           kb=2048, hace_horas=7 * 24),
                s.respaldo('archivos-20261012-0230.tar.gz.enc',
                           kb=50000, hace_horas=5 * 24)),
     'es de hace 168 horas')

# Un volcado truncado por falta de espacio: el archivo esta, parece un
# respaldo, y no se puede restaurar.
caso('un respaldo de la base truncado',
     lambda s: (s.poner_cron(), s.poner_log(LOG_BUENO),
                s.respaldo('base-20261017-0230.tar.gz.enc',
                           kb=2, hace_horas=1)),
     'esta vacio o truncado')

caso('que no haya respaldo de los archivos del expediente',
     lambda s: (s.poner_cron(), s.poner_log(LOG_BUENO),
                s.respaldo('base-20261017-0230.tar.gz.enc',
                           kb=2048, hace_horas=5)),
     'no hay NINGUN respaldo de los archivos')

caso('que los archivos lleven semanas sin respaldarse',
     lambda s: (s.poner_cron(), s.poner_log(LOG_BUENO),
                s.respaldo('base-20261017-0230.tar.gz.enc',
                           kb=2048, hace_horas=5),
                s.respaldo('archivos-20260901-0230.tar.gz.enc',
                           kb=50000, hace_horas=40 * 24)),
     'es de hace 40 dias')

# Sin cifrar: el respaldo trae el expediente clinico completo de cada
# paciente, y en Mexico los datos de salud son datos sensibles.
caso('que los respaldos vayan sin cifrar',
     completo,
     'SIN CIFRAR')

caso('que el ultimo intento haya dado error',
     lambda s: (completo(s), s.poner_log(
         '[2026-10-17 02:30:00] === Respaldo 20261017-0230 ===\n'
         '[2026-10-17 02:30:01] Volcando la base de datos...\n'
         '[2026-10-17 02:30:02] ERROR al volcar la base:\n'
         '[2026-10-17 02:30:02] Access denied for user\n')),
     'registro un ERROR')

# Un respaldo que se corta a medias no deja ningun ERROR en el log: el
# proceso se murio. Lo que falta es la linea del final.
caso('que el ultimo intento se haya cortado a medias',
     lambda s: (completo(s), s.poner_log(
         '[2026-10-17 02:30:00] === Respaldo 20261017-0230 ===\n'
         '[2026-10-17 02:30:01] Volcando la base de datos...\n')),
     'se corto a medias')

# Una tarea que llama a un archivo que ya no esta falla todas las
# noches y cron no avisa a nadie.
caso('que la tarea apunte a un guion que no existe',
     lambda s: (completo(s), s.poner_cron(
         'SHELL=/bin/bash\n'
         '30 2 * * * alguien /opt/noexiste/respaldar.sh >> /tmp/x.log 2>&1\n')),
     'que no existe')

caso('que no haya copia fuera del servidor',
     lambda s: completo(s),
     'SOLO existen en este servidor',
     env_extra='CLAVE_RESPALDO=unaclave\n')


# =====================================================================
#  4. Lo que NO debe quejarse
# =====================================================================
print()
print('Lo que no debe dar falsa alarma')

# Un respaldo de hace 29 horas es normal: la tarea corre a las 2:30 y
# alguien puede mirar esto a las 7 de la mañana del dia siguiente. Si
# se quejara a las 24 horas justas, se quejaria casi todos los dias y
# el aviso dejaria de leerse.
s = ServidorDeMentira('CLAVE_RESPALDO=k\nCOPIA_FUERA=disco\n')
s.poner_cron()
s.poner_log(LOG_BUENO)
s.respaldo('base-20261016-0230.tar.gz.enc', kb=2048, hace_horas=29)
s.respaldo('archivos-20261012-0230.tar.gz.enc', kb=50000, hace_horas=5 * 24)
codigo, salida = s.correr()

quejas = '\n'.join(
    l for l in salida.splitlines() if l.strip().startswith('!!'))
comprobar('29 horas no es un fallo, es un aviso',
          'es de hace 29 horas' in quejas, False)
comprobar('y se dice como aviso', 'van 29 horas' in salida, True)
s.tirar()

# Los archivos del expediente son SEMANALES: seis dias esta bien. Una
# comprobacion copiada de la base se quejaria cada semana.
s = ServidorDeMentira('CLAVE_RESPALDO=k\nCOPIA_FUERA=disco\n')
s.poner_cron()
s.poner_log(LOG_BUENO)
s.respaldo('base-20261017-0230.tar.gz.enc', kb=2048, hace_horas=5)
s.respaldo('archivos-20261011-0230.tar.gz.enc', kb=50000, hace_horas=6 * 24)
codigo, salida = s.correr()
quejas = '\n'.join(
    l for l in salida.splitlines() if l.strip().startswith('!!'))
comprobar('seis dias sin respaldar los archivos esta bien',
          'respaldo de archivos' in quejas, False)
s.tirar()


# =====================================================================
#  5. La ayuda para arreglarlo
# =====================================================================
print()
print('Decir que esta mal no basta')

s = ServidorDeMentira()
s.poner_log(LOG_BUENO)
codigo, salida = s.correr()

# Cada queja tiene que venir con que hacer. Un diagnostico que solo
# dice "no hay respaldos" deja a Angel buscando en los archivos del
# proyecto a que guion llamar.
comprobar('sin tarea, dice como instalarla',
          '--instalar-cron' in salida, True)
comprobar('sin respaldo de la base, dice como hacer uno',
          'respaldar.sh --solo-base' in salida, True)
comprobar('sin respaldo de archivos, dice como hacer uno',
          'respaldar.sh --completo' in salida, True)
comprobar('y avisa de que los archivos tardan y pesan',
          'necesita el doble de espacio' in salida, True)
s.tirar()

# El guion tiene que poder instalar la tarea, no solo decir que falta.
texto = (RAIZ / 'infra/produccion/revisar-respaldos.sh').read_text(
    encoding='utf-8')
comprobar('sabe instalar la tarea', '--instalar-cron' in texto, True)
comprobar('y exige root para hacerlo', 'EUID -ne 0' in texto, True)
# El usuario de la tarea se deduce del proyecto: pidiendolo, un dedazo
# deja la tarea fallando por permisos todas las noches en silencio.
comprobar('el usuario de la tarea sale del proyecto',
          "stat -c '%U'" in texto, True)


print()
if FALLOS:
    print(f'  *** {len(FALLOS)} fallo(s):')
    for f in FALLOS:
        print(f'  !!   {f}')
    sys.exit(1)
print('  Todo en orden: el revisor caza los respaldos que no existen.')
