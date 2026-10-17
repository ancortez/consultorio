#!/usr/bin/env python3
"""
migrar.sh corrido de verdad, contra un MySQL de mentira.

Por que existe este arnes
-------------------------
El 13 de octubre de 2026 una migracion quedo anotada como aplicada sin
haber agregado sus columnas. El sistema arranco pidiendo
`rp.requiere_tutor` y lo que llego a la pantalla fue:

    Unknown column 'rp.requiere_tutor' in 'field list'

Un error que no se parece en nada a la causa. Y lo peor: como figuraba
aplicada, volver a correr migrar.sh decia "la base esta al dia" y no la
intentaba de nuevo. El sistema no tenia forma de salir solo de ahi.

El arreglo fue que migrar.sh COMPRUEBE que cada migracion dejo rastro,
incluidas las que ya figuran aplicadas. Esta prueba es la que dice que
ese arreglo funciona, y correrlo es la unica forma de saberlo: no hay
MySQL en la maquina donde se desarrolla, asi que se levanta uno de
mentira sobre sqlite que entiende lo justo —information_schema, la
tabla de control y los ALTER— y se corre el guion de verdad contra el.

Tres escenarios, que son los tres estados en los que se puede estar:

    1. normal          nada aplicado, todo corre y las columnas quedan
    2. sin efecto      una migracion corre y no agrega: se detiene
    3. anotada en vano es el caso real: figura aplicada y falta la
                       columna; tiene que volver a intentarse sola
"""
import hashlib
import os
import pathlib
import re
import shutil
import sqlite3
import subprocess
import sys
import tempfile

RAIZ = pathlib.Path(__file__).resolve().parent.parent
FALLOS = []

def tablasQueTocan():
    """Las tablas que mencionan las migraciones, leidas de ellas mismas.

    Estaban escritas a mano y se quedaron viejas en cuanto agregue una
    migracion sobre plan_tratamiento: la prueba fallaba por una tabla
    que no existia en el teatro, no por un fallo de verdad. Una lista a
    mano al lado de algo que crece es una lista que caduca.
    """
    tablas = set()
    for f in sorted((RAIZ / 'db/migraciones').glob('*.sql')):
        sinComentarios = re.sub(r'--.*$', '', f.read_text(encoding='utf-8'),
                                flags=re.M)
        tablas.update(re.findall(r'ALTER TABLE\s+(\w+)', sinComentarios))
    return sorted(tablas)


TABLAS = tablasQueTocan()


# =====================================================================
#  El MySQL de mentira
# =====================================================================
FALSO_MYSQL = r'''
import sqlite3, sys, re, os
BD = os.environ['SIMUL_BD']
sql = sys.argv[1]
con = sqlite3.connect(BD); cur = con.cursor()

# information_schema.columns: se contesta mirando sqlite.
m = re.search(r"information_schema\.columns.*?table_name\s*=\s*'(\w+)'"
              r".*?column_name\s*=\s*'(\w+)'", sql, re.S)
if m and 'SET @' not in sql:
    tabla, col = m.groups()
    try:
        cols = [r[1] for r in cur.execute('PRAGMA table_info(%s)' % tabla)]
    except Exception:
        cols = []
    print(1 if col in cols else 0); sys.exit(0)

def agregar(pieza):
    # ROMPER simula la migracion que corre sin error y no agrega nada:
    # el fallo que de verdad ocurrio.
    if os.environ.get('ROMPER') == '1':
        return
    t = re.search(r'ALTER TABLE\s+(\w+)', pieza)
    # TODAS las columnas del ALTER, no solo la primera. Un ALTER con dos
    # ADD COLUMN es valido en MySQL, y agregando solo una el simulador
    # decia "sin efecto" por una migracion correcta: la prueba fallaba
    # por el teatro, no por el codigo.
    cols = re.findall(r'ADD COLUMN\s+(\w+)', pieza)
    if not (t and cols):
        return
    for col in cols:
        try:
            cur.execute('ALTER TABLE %s ADD COLUMN %s INTEGER'
                        % (t.group(1), col))
        except Exception:
            pass

for pieza in [p.strip() for p in sql.split(';') if p.strip()]:
    U = pieza.upper()
    try:
        if U.startswith('SELECT HUELLA'):
            a = re.search(r"archivo = '([^']+)'", pieza).group(1)
            r = cur.execute('SELECT huella FROM migracion WHERE archivo=?', (a,)).fetchone()
            if r: print(r[0])
        elif U.startswith('CREATE TABLE IF NOT EXISTS MIGRACION'):
            cur.execute('CREATE TABLE IF NOT EXISTS migracion '
                        '(archivo TEXT PRIMARY KEY, huella TEXT)')
        elif U.startswith('INSERT INTO MIGRACION'):
            a = re.search(r"VALUES \('([^']+)', '([^']+)'\)", pieza)
            cur.execute('INSERT OR REPLACE INTO migracion VALUES (?,?)', a.groups())
        elif 'ADD COLUMN' in U:
            agregar(pieza)
    except Exception:
        pass
con.commit()
'''

FALSO_DOCKER = r'''#!/usr/bin/env bash
if [[ "$1 $2" == "exec consultorio_db" ]]; then
  shift 2
  [[ $1 == true ]] && exit 0
  # El respaldo se contesta sin leer stdin: si no, el guion se cuelga.
  for a in "$@"; do [[ $a == mysqldump ]] && { echo "-- volcado falso"; exit 0; }; done
fi
sql=""; hay_e=0
for ((i=1; i<=$#; i++)); do
  if [[ ${!i} == -e ]]; then j=$((i+1)); sql="${!j}"; hay_e=1; fi
done
[[ $hay_e -eq 0 ]] && sql="$(cat)"
python3 "$SIMUL_PY" "$sql"
'''


def montar(carpeta: pathlib.Path):
    """Deja listo el teatro: el docker falso, el guion y las migraciones."""
    (carpeta / 'bin').mkdir(parents=True)
    py = carpeta / 'mysql.py'
    py.write_text(FALSO_MYSQL, encoding='utf-8')

    docker = carpeta / 'bin' / 'docker'
    docker.write_text(FALSO_DOCKER, encoding='utf-8')
    docker.chmod(0o755)

    shutil.copytree(RAIZ / 'infra', carpeta / 'infra', symlinks=True)
    shutil.copytree(RAIZ / 'db/migraciones', carpeta / 'db/migraciones')
    (carpeta / '.env').write_text('DB_ROOT_PASS=x\nDB_NAME=prueba\n', encoding='utf-8')
    return py


def basePrimeriza(ruta: pathlib.Path, anotarTodo: bool = False):
    con = sqlite3.connect(ruta)
    for t in TABLAS:
        con.execute(f'CREATE TABLE {t} (id INTEGER)')

    if anotarTodo:
        # El estado en el que quedo la instalacion de verdad: todas
        # figuran aplicadas, con su huella buena, y las columnas no
        # estan por ningun lado.
        con.execute('CREATE TABLE migracion (archivo TEXT PRIMARY KEY, huella TEXT)')
        for f in sorted((RAIZ / 'db/migraciones').glob('*.sql')):
            con.execute('INSERT INTO migracion VALUES (?,?)',
                        (f.name, hashlib.sha256(f.read_bytes()).hexdigest()))
    con.commit()
    con.close()


def correrMigrar(carpeta: pathlib.Path, bd: pathlib.Path, py: pathlib.Path,
                 romper: bool = False):
    entorno = dict(os.environ)
    entorno['PATH'] = f"{carpeta / 'bin'}:{entorno['PATH']}"
    entorno['SIMUL_BD'] = str(bd)
    entorno['SIMUL_PY'] = str(py)
    if romper:
        entorno['ROMPER'] = '1'

    return subprocess.run(
        ['bash', 'migrar.sh'],
        cwd=carpeta / 'infra/produccion',
        capture_output=True, text=True, env=entorno, timeout=120)


def columnas(bd: pathlib.Path, tabla: str):
    con = sqlite3.connect(bd)
    try:
        return [r[1] for r in con.execute(f'PRAGMA table_info({tabla})')]
    finally:
        con.close()


def comprobar(nombre, obtenido, esperado):
    if obtenido != esperado:
        FALLOS.append(f'{nombre}\n      esperaba: {esperado!r}\n      obtuvo:   {obtenido!r}')
    else:
        print(f'  ok   {nombre}')


# =====================================================================
#  1. Todo normal
# =====================================================================
with tempfile.TemporaryDirectory() as t:
    carpeta = pathlib.Path(t)
    py = montar(carpeta)
    bd = carpeta / 'base.sqlite'
    basePrimeriza(bd)

    r = correrMigrar(carpeta, bd, py)
    comprobar('con la base virgen, migrar termina bien', r.returncode, 0)
    comprobar('las columnas de 0009 quedan puestas',
              sorted(set(columnas(bd, 'registro_paciente')) - {'id'}),
              ['requiere_tutor', 'tutor_decidido_edad'])

# =====================================================================
#  2. Una migracion que corre y no agrega nada
# =====================================================================
with tempfile.TemporaryDirectory() as t:
    carpeta = pathlib.Path(t)
    py = montar(carpeta)
    bd = carpeta / 'base.sqlite'
    basePrimeriza(bd)

    r = correrMigrar(carpeta, bd, py, romper=True)

    # Lo esencial: NO puede decir que todo salio bien.
    comprobar('una migracion sin efecto detiene el guion', r.returncode, 1)
    comprobar('y lo dice con esas palabras',
              'sin efecto' in r.stdout, True)

    # Y no se anota: si se anotara, no se volveria a intentar nunca.
    con = sqlite3.connect(bd)
    anotadas = [x[0] for x in con.execute('SELECT archivo FROM migracion')]
    con.close()
    comprobar('la que no dejo rastro NO queda anotada',
              '0002_cobro_sin_doble_clic.sql' in anotadas, False)

# =====================================================================
#  3. El caso real: anotada como aplicada, columnas ausentes
# =====================================================================
with tempfile.TemporaryDirectory() as t:
    carpeta = pathlib.Path(t)
    py = montar(carpeta)
    bd = carpeta / 'base.sqlite'
    basePrimeriza(bd, anotarTodo=True)

    r = correrMigrar(carpeta, bd, py)

    comprobar('se da cuenta de que figuran aplicadas sin estarlo',
              'figura aplicada pero falta' in r.stdout, True)
    comprobar('y termina bien despues de rehacerlas', r.returncode, 0)

    # Lo que importa de verdad: la base queda buena sin que nadie tenga
    # que tocar la tabla de control a mano.
    comprobar('registro_paciente queda con sus dos columnas',
              sorted(set(columnas(bd, 'registro_paciente')) - {'id'}),
              ['requiere_tutor', 'tutor_decidido_edad'])
    comprobar('y las de las migraciones viejas tambien',
              'huella_envio' in columnas(bd, 'pago'), True)

    # Y una segunda corrida ya no tiene nada que hacer.
    r2 = correrMigrar(carpeta, bd, py)
    comprobar('la siguiente corrida ya no rehace nada',
              'figura aplicada pero falta' in r2.stdout, False)

print()
if FALLOS:
    print(f'*** {len(FALLOS)} PROBLEMA(S) ***')
    for f in FALLOS:
        print('  !!   ' + f)
    sys.exit(1)
print('*** TODO EN ORDEN ***')
