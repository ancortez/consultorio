#!/usr/bin/env python3
"""
Traducir "falta migrar" cuando pasa.

Por que existe este arnes
-------------------------
Tres veces ya, el codigo de una version nueva llego a la computadora de
Angel sin su migracion, y lo que vio fue esto:

    Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not
    found: 1054 Unknown column 'motivo_cancelacion' in 'field list' in
    /var/www/html/config/conexion.php:59 Stack trace: #0 ...

Ese mensaje nombra una columna, un archivo de conexion y un rastro de
llamadas, y ninguna de las tres cosas es la causa ni la solucion. Y
aparece dentro de la pantalla del plan de tratamiento, asi que la
conclusion natural es "se rompio el modulo de planes".

Lo que se arreglo no es la migracion —eso lo hace migrar.sh— sino la
traduccion. Y lo importante de un traductor asi es lo que NO traduce:
si un error de verdad se disfraza de migracion pendiente, se corre
migrar.sh, no pasa nada, y el rastro se enfria. Eso es lo que mas se
prueba aqui.

Se reconoce por el codigo SQLSTATE y no por el texto del mensaje: el
texto cambia entre versiones de MySQL y entre idiomas, el codigo no. La
tentacion de buscar "Unknown column" en el mensaje es justo la que se
evita, y aqui se comprueba que no se haya colado.
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


COLUMNA_AUSENTE = '42S22'
TABLA_AUSENTE = '42S02'


# ---- Puerto literal de Esquema ----
def nombre_en_el_mensaje(mensaje):
    m = re.search(r"'([^']+)'", mensaje)
    return m.group(1) if m else None


def aviso_de_falta_migrar(es_pdo, estado, mensaje):
    if not es_pdo:
        return None
    if estado == COLUMNA_AUSENTE:
        return ('columna', nombre_en_el_mensaje(mensaje))
    if estado == TABLA_AUSENTE:
        return ('tabla', nombre_en_el_mensaje(mensaje))
    return None


# =====================================================================
#  1. El error real de Angel se reconoce
# =====================================================================
print('Lo que vio Angel')

EL_DE_VERDAD = ("SQLSTATE[42S22]: Column not found: 1054 Unknown column "
                "'motivo_cancelacion' in 'field list'")

comprobar('el error del plan se reconoce como falta de migracion',
          aviso_de_falta_migrar(True, '42S22', EL_DE_VERDAD),
          ('columna', 'motivo_cancelacion'))

comprobar('y antes de eso, el de requiere_tutor tambien',
          aviso_de_falta_migrar(
              True, '42S22',
              "SQLSTATE[42S22]: Column not found: 1054 Unknown column "
              "'rp.requiere_tutor' in 'field list'"),
          ('columna', 'rp.requiere_tutor'))

comprobar('una tabla que falta tambien',
          aviso_de_falta_migrar(
              True, '42S02',
              "SQLSTATE[42S02]: Base table or view not found: 1146 Table "
              "'consultorio_dental.imprimible' doesn't exist"),
          ('tabla', 'consultorio_dental.imprimible'))

# =====================================================================
#  2. Lo que NO se traduce
# =====================================================================
print()
print('Lo que NO se disfraza de migracion pendiente')

# Este es el corazon del asunto. Un fallo de verdad que dijera "corre
# migrar.sh" manda a Angel a correr un guion que no arregla nada, y
# mientras tanto el rastro del fallo de verdad se enfria.
OTROS = [
    ('23000', "Duplicate entry 'RC-0012' for key 'uq_pago_folio'",
     'un folio repetido'),
    ('42000', "You have an error in your SQL syntax",
     'un SQL mal escrito'),
    ('HY000', "Incorrect datetime value: '2026-13-45'",
     'una fecha imposible'),
    ('08S01', "Lost connection to MySQL server during query",
     'la base que se cayo'),
    ('23000', "Cannot add or update a child row: a foreign key constraint fails",
     'una llave foranea'),
]

for estado, mensaje, que in OTROS:
    comprobar(f'{que} NO se traduce',
              aviso_de_falta_migrar(True, estado, mensaje), None)

# Y nada que no sea un error de la base.
comprobar('un error de PHP cualquiera tampoco',
          aviso_de_falta_migrar(False, '42S22', 'Division by zero'), None)

# =====================================================================
#  3. El mensaje aguanta que MySQL cambie de palabras
# =====================================================================
print()
print('Si MySQL cambia el texto')

# El codigo es lo estable. Un mensaje en otro idioma, o de una version
# que redacte distinto, se tiene que seguir reconociendo.
comprobar('un mensaje con otras palabras se reconoce igual',
          aviso_de_falta_migrar(True, '42S22',
                                "columna desconocida 'fecha_terminado'"),
          ('columna', 'fecha_terminado'))

# Y si el formato cambia tanto que no hay comillas, se avisa sin nombre
# en vez de reventar: un aviso sin nombre sigue siendo mejor que un
# rastro de pila.
comprobar('sin comillas, se avisa sin nombre',
          aviso_de_falta_migrar(True, '42S22', 'algo raro sin comillas'),
          ('columna', None))

# =====================================================================
#  4. El PHP dice lo mismo que esta copia
# =====================================================================
print()
print('El PHP coincide')

php = (RAIZ / 'includes/core/esquema.php').read_text(encoding='utf-8')

for nombre, valor in [('COLUMNA_AUSENTE', COLUMNA_AUSENTE),
                      ('TABLA_AUSENTE', TABLA_AUSENTE)]:
    m = re.search(r"const\s+" + nombre + r"\s*=\s*'([^']+)'", php)
    comprobar(f'{nombre} vale lo mismo en PHP',
              m.group(1) if m else None, valor)

# Que se mire el SQLSTATE y no el texto del mensaje. Buscar "Unknown
# column" en el mensaje es la version que parece funcionar y se rompe en
# cuanto MySQL cambia de idioma o de redaccion.
phpCodigo = re.sub(r'/\*.*?\*/', '', php, flags=re.S)
phpCodigo = re.sub(r'^\s*(?://|\*).*$', '', phpCodigo, flags=re.M)
comprobar('no se reconoce por el texto del mensaje',
          'Unknown column' in phpCodigo, False)
comprobar('se reconoce por el SQLSTATE', 'errorInfo[0]' in phpCodigo, True)

# =====================================================================
#  5. Y alguien lo usa de verdad
# =====================================================================
print()
print('Quien lo usa')

# Las dos puertas por donde sale un error: la pantalla y los endpoints.
# Con una sola, la mitad de los casos sigue mostrando el rastro crudo.
indice = (RAIZ / 'index.php').read_text(encoding='utf-8')
comprobar('la pantalla traduce el error antes de mostrarlo',
          'Esquema::avisoDeFaltaMigrar' in indice, True)
comprobar('y vuelve a lanzar lo que no sea eso',
          'throw $e' in indice, True)

endpoint = (RAIZ / 'includes/_endpoint.php').read_text(encoding='utf-8')
comprobar('los endpoints tambien lo traducen',
          'Esquema::avisoDeFaltaMigrar' in endpoint, True)

# El aviso tiene que decir QUE TECLEAR. Un mensaje que explica el
# problema sin decir la salida cuesta la misma ida y vuelta que el
# rastro de pila.
comprobar('el aviso dice el comando que hay que correr',
          'migrar.sh' in php, True)

print()
if FALLOS:
    print(f'*** {len(FALLOS)} PROBLEMA(S) ***')
    for f in FALLOS:
        print('  !!   ' + f)
    sys.exit(1)
print('*** TODO EN ORDEN ***')
