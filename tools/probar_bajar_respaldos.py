#!/usr/bin/env python3
"""
El guion que baja los respaldos a la computadora.

Por que existe este arnes
-------------------------
Angel pregunto que pasa si se va el servidor. La respuesta es que los
respaldos viven en el MISMO disco que el sistema, asi que se irian con
el, y lo que lo arregla es tener las copias en otro sitio fisico sin
depender de acordarse. De ahi bajar-respaldos.command.

Un guion de copia tiene un problema propio: cuando falla, suele fallar
hacia el lado tranquilo. No baja nada, no dice nada, y durante meses
parece que hay copias. Los casos concretos que hay que cazar:

  1. QUE NO BORRE. El servidor rota sus respaldos —14 dias de base, 4
     copias de archivos—. Un rsync con --delete espejaria eso y
     borraria de AQUI justo lo que alla ya se olvido. Lo valioso de la
     copia de fuera es que llega mas atras; con --delete la copia de
     fuera valdria exactamente lo mismo que la de dentro.

  2. QUE NO SE CUELGUE. Corriendo desde launchd no hay nadie para
     contestar nada. Sin BatchMode, el dia que la llave ssh falle, ssh
     se queda esperando una contrasenia para siempre y la tarea nunca
     termina: ni baja ni avisa.

  3. QUE AVISE AL FALLAR, con el codigo de salida. Si termina en 0
     cuando no bajo nada, launchd lo apunta como un exito y la bitacora
     dice que todo va bien.

  4. QUE NO GUARDE EN EL PROYECTO. El proyecto esta en git, y un
     respaldo con el expediente clinico de todos los pacientes no puede
     acabar en un repositorio por un "git add -A" distraido.

El guion se corre de verdad, con un rsync de mentira en el PATH.
"""
import os
import pathlib
import re
import shutil
import stat
import subprocess
import sys
import tempfile

RAIZ = pathlib.Path(__file__).resolve().parent.parent
GUION = RAIZ / 'bajar-respaldos.command'
FALLOS = []


def comprobar(nombre, obtenido, esperado):
    if obtenido != esperado:
        FALLOS.append(f'{nombre}\n      esperaba: {esperado!r}\n'
                      f'      obtuvo:   {obtenido!r}')
    else:
        print(f'  ok   {nombre}')


class Teatro:
    """
    Una computadora de mentira: el guion, un rsync falso y dos carpetas.

    El rsync falso apunta lo que le pidieron en un archivo y copia lo
    que haya en el "servidor". Asi se puede comprobar CON QUE
    argumentos se llamo, que es donde estan las trampas.
    """

    def __init__(self, rsync_falla=False):
        self.base = pathlib.Path(tempfile.mkdtemp())
        shutil.copy(GUION, self.base / 'bajar-respaldos.command')

        (self.base / '.servidor').write_text('root@1.2.3.4\n', encoding='utf-8')

        self.servidor = self.base / 'servidor'
        self.servidor.mkdir()
        self.local = self.base / 'local'

        self.bin = self.base / 'bin'
        self.bin.mkdir()
        self.apunte = self.base / 'argumentos.txt'

        rsync = self.bin / 'rsync'
        rsync.write_text(
            '#!/usr/bin/env bash\n'
            f'printf "%s\\n" "$@" > "{self.apunte}"\n'
            + ('exit 23\n' if rsync_falla else
               # Copia lo del "servidor" al destino: el ultimo argumento
               # es el destino y el penultimo el origen.
               'origen="${@: -2:1}"; destino="${@: -1}"\n'
               'origen="${origen#*:}"\n'
               'mkdir -p "$destino"\n'
               'cp -R "$origen"/. "$destino"/ 2>/dev/null || true\n'
               'exit 0\n'),
            encoding='utf-8')
        rsync.chmod(rsync.stat().st_mode | stat.S_IEXEC)

    def respaldo(self, nombre, kb=200):
        f = self.servidor / nombre
        f.write_bytes(b'x' * (kb * 1024))

    def correr(self, *args):
        entorno = dict(os.environ)
        entorno['PATH'] = f"{self.bin}:{entorno['PATH']}"
        entorno['RESPALDOS_SERVIDOR'] = str(self.servidor)
        entorno['RESPALDOS_LOCALES'] = str(self.local)
        entorno['HOME'] = str(self.base / 'casa')
        (self.base / 'casa').mkdir(exist_ok=True)

        r = subprocess.run(
            ['bash', str(self.base / 'bajar-respaldos.command'), *args],
            capture_output=True, text=True, env=entorno, cwd=self.base)

        salida = re.sub(r'\x1b\[[0-9;]*m', '', r.stdout + r.stderr)
        return r.returncode, salida

    def argumentos(self):
        if not self.apunte.exists():
            return []
        return self.apunte.read_text(encoding='utf-8').splitlines()

    def tirar(self):
        shutil.rmtree(self.base, ignore_errors=True)


# =====================================================================
#  1. Baja lo que hay
# =====================================================================
print('La copia')

t = Teatro()
t.respaldo('base-20261017-0230.tar.gz.enc', kb=2048)
t.respaldo('base-20261016-0230.tar.gz.enc', kb=2048)
t.respaldo('archivos-20261012-0230.tar.gz.enc', kb=5000)
# Algo que NO es un respaldo: no tiene por que bajarse.
t.respaldo('notas-del-admin.txt', kb=1)

codigo, salida = t.correr()

comprobar('termina bien', codigo, 0)
comprobar('dice cuantos de base quedaron',
          '2 de base' in salida, True)
comprobar('y cuantos de archivos',
          '1 de archivos' in salida, True)
# El mas reciente se decide por NOMBRE, no por fecha del archivo. Los
# dos respaldos de esta prueba se crean en el mismo instante —y con un
# disco externo o un zip en medio, en la vida real tambien acaban
# iguales—, asi que por fecha salia cualquiera de los dos.
comprobar('nombra el mas reciente de la base',
          'base-20261017-0230' in salida, True)

bajados = sorted(p.name for p in t.local.iterdir()) if t.local.is_dir() else []
comprobar('los respaldos llegaron',
          [n for n in bajados if n.startswith(('base-', 'archivos-'))],
          ['archivos-20261012-0230.tar.gz.enc',
           'base-20261016-0230.tar.gz.enc',
           'base-20261017-0230.tar.gz.enc'])

args = t.argumentos()

# La trampa 1: --delete convertiria la copia de fuera en un espejo, y
# un espejo vale lo mismo que el original.
comprobar('NO se usa --delete', '--delete' in args, False)
comprobar('no se vuelve a bajar lo que ya esta',
          '--ignore-existing' in args, True)

# La trampa 2: sin BatchMode, launchd se cuelga esperando una
# contrasenia que nadie va a teclear.
ssh = [a for a in args if a.startswith('ssh ')]
comprobar('ssh va en modo desatendido',
          bool(ssh) and 'BatchMode=yes' in ssh[0], True)
comprobar('y con tiempo limite',
          bool(ssh) and 'ConnectTimeout' in ssh[0], True)

# Solo los respaldos: lo demas que haya en esa carpeta del servidor no
# es asunto de esta computadora.
comprobar('solo se piden los archivos de respaldo',
          '--include=base-*' in args and '--include=archivos-*' in args
          and '--exclude=*' in args, True)

t.tirar()


# =====================================================================
#  2. Cuando falla, lo dice
# =====================================================================
print()
print('Cuando algo sale mal')

t = Teatro(rsync_falla=True)
t.respaldo('base-20261017-0230.tar.gz.enc', kb=2048)

codigo, salida = t.correr()

# La trampa 3: terminar en 0 sin bajar nada deja a launchd apuntando un
# exito, y la bitacora diciendo que todo va bien.
comprobar('termina con error', codigo, 1)
comprobar('dice que no pudo copiar',
          'No se pudo copiar' in salida, True)
# Y dice QUE hacer: la causa mas comun es la llave ssh, y sin la pista
# el aviso manda a adivinar.
comprobar('y nombra la causa mas comun',
          'ssh-copy-id' in salida, True)
comprobar('con el comando para probarlo a mano',
          'ssh root@1.2.3.4' in salida, True)
t.tirar()


# =====================================================================
#  3. Sin respaldos de base, avisa
# =====================================================================
print()
print('Un servidor que no tiene respaldos')

t = Teatro()
t.respaldo('archivos-20261012-0230.tar.gz.enc', kb=5000)
codigo, salida = t.correr()

# Que haya archivos del expediente y no haya base es el peor estado
# posible y el mas facil de no notar: la carpeta tiene cosas.
comprobar('avisa de que no bajo ninguna base',
          'no bajo ningun respaldo de la base' in salida, True)
comprobar('y manda a revisar el servidor',
          'revisar-respaldos.sh' in salida, True)
t.tirar()


# =====================================================================
#  4. --ver y la instalacion
# =====================================================================
print()
print('Saber si esta puesta')

t = Teatro()
t.respaldo('base-20261017-0230.tar.gz.enc', kb=2048)
codigo, salida = t.correr('--ver')

comprobar('--ver no falla', codigo, 0)
comprobar('dice que NO esta instalada',
          'NO esta instalada' in salida, True)
comprobar('y como instalarla', '--instalar' in salida, True)
t.tirar()

texto = GUION.read_text(encoding='utf-8')

comprobar('sabe instalarse solo', '--instalar' in texto, True)
comprobar('y quitarse', '--quitar' in texto, True)

# launchd y no cron: en macOS cron esta de salida, y launchd ademas
# recupera la corrida perdida. Eso importa porque la computadora de
# Angel no esta prendida a las 20:30 todos los dias.
comprobar('usa launchd', 'launchctl bootstrap' in texto, True)
# bootout antes de bootstrap: cargarlo dos veces falla con un error que
# no explica nada.
comprobar('y se descarga antes de cargarse',
          texto.index('launchctl bootout') < texto.index('launchctl bootstrap'),
          True)
comprobar('corre al final del dia', 'StartCalendarInterval' in texto, True)

# La trampa 4: el destino NO puede estar dentro del proyecto.
comprobar('las copias se guardan fuera del proyecto',
          '$HOME/Respaldos-Nanai' in texto, True)
comprobar('y no en una carpeta del repositorio',
          './respaldos' in texto or 'storage/respaldos' in texto, False)

# La llave vive en la computadora y apunta al servidor, no al reves: un
# servidor comprometido no puede tocar las copias de aqui.
comprobar('se explica por que JALA en vez de que el servidor EMPUJE',
          'JALA' in texto and 'EMPUJA' in texto, True)


print()
if FALLOS:
    print(f'  *** {len(FALLOS)} fallo(s):')
    for f in FALLOS:
        print(f'  !!   {f}')
    sys.exit(1)
print('  Todo en orden: las copias salen del servidor y no se borran solas.')
