#!/usr/bin/env python3
"""
Los complementos de la historia clinica, impresos.

Por que existe este arnes
-------------------------
Doce preguntas de la historia abren otra: "¿alergico? -> ¿a que?". Y la
hoja que se IMPRIME es el documento que se firma.

Hasta ahora, cuando la respuesta era NO, el renglon del complemento
salia en blanco o no salia. En una hoja firmada un blanco no se lee como
"no aplica": se lee como "no se pregunto", y seis meses despues nadie
puede distinguir una cosa de la otra. Peor: un blanco se puede llenar a
mano DESPUES de la firma.

El consultorio dio la tabla de rellenos pregunta por pregunta, y no son
intercambiables: a "¿cirugias?" se contesta NINGUNA, a "¿hasta cuando
tomo formula?" se contesta N/A, y a "¿es la primera visita? SI" le
corresponde PRIMERA VEZ en el renglon de la ultima visita. Una regla de
doce casos escrita a mano se equivoca en uno y nadie lo nota, porque hay
que imprimir doce historias distintas para verlo.

Lo que de verdad se prueba aqui es el caso contrario: lo que sale
cuando la respuesta SI pedia detalle y no se dio ninguno.

Hasta el 16 de octubre de 2026 eso salia en blanco, y el razonamiento
era que rellenarlo con "NINGUNA" no taparia un hueco sino que firmaria
una contradiccion —la hoja diria que si es alergico y que no tiene
ninguna alergia—.

Ese razonamiento sigue siendo cierto para "NINGUNA" y deja de aplicar
para el texto nuevo: desde esa fecha sale "NO ESPECIFICA", que no se
contradice con nada porque dice exactamente lo que paso. El cambio vino
con el otro: los doce detalles pasaron a ser opcionales, asi que el caso
"respuesta si, detalle vacio" dejo de ser un error de captura y paso a
ser un estado normal del expediente. Y deja la hoja mejor que antes: un
renglon en blanco se lee como "no se pregunto" y se puede llenar a mano
despues de la firma.

El unico blanco que queda es cuando la pregunta madre no se contesto.
Ahi no hay nada que decir.

No hay PHP en esta maquina: la tabla se lee del propio archivo PHP y la
regla se porta tal cual.
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


# =====================================================================
#  La tabla que dio el consultorio, escrita a mano aqui
# =====================================================================
# A proposito NO se lee del PHP: si las dos copias salieran del mismo
# sitio, esta prueba no comprobaria nada. Esta es la tabla del correo de
# Angel, transcrita, y abajo se compara contra la del PHP.
#
#   complemento            depende                  cuando  relleno
ESPERADA = {
    'ultima_visita_dentista': ('primera_visita_dentista', '0',  'PRIMERA VEZ'),
    'complicaciones_detalle': ('complicaciones_parto',    '1',  'NINGUNA'),
    'lactancia_hasta':        ('lactancia_materna',       '1',  'N/A'),
    'formula_hasta':          ('formula',                 '1',  'N/A'),
    'dieta_porque':           ('dieta_completa',          '0',  'N/A'),
    'alimentos_azucar':       ('momentos_azucar', '!NO CONSUME', 'N/A'),
    'enfermedad_detalle':     ('enfermedad_cronica',      '1',  'NINGUNA'),
    'discapacidad_detalle':   ('discapacidad',            '1',  'NINGUNA'),
    'alergia_detalle':        ('alergia',                 '1',  'NINGUNA'),
    'tratamiento_detalle':    ('tratamiento_medico',      '1',  'NINGUNO'),
    'cirugias_detalle':       ('cirugias',                '1',  'NINGUNA'),
    'otro_higiene_detalle':   ('otro_higiene',            '1',  'NINGUNO'),
}


# Escrito a mano, igual que la tabla: si saliera del PHP esta prueba no
# comprobaria nada. Abajo se compara contra el PHP y contra el
# JavaScript, que son los dos sitios donde tiene que decir lo mismo.
SIN_ESPECIFICAR = 'NO ESPECIFICA'


# ---- Puerto literal de HistoriaClinica ----
def pide_complemento(cuando, valor):
    valor = (valor or '').strip()
    if cuando.startswith('!'):
        return valor != '' and valor != cuando[1:]
    return valor == cuando


def complemento_impreso(hc, campo):
    if campo not in ESPERADA:
        return (hc.get(campo) or '').strip()

    depende, cuando, relleno = ESPERADA[campo]

    capturado = (hc.get(campo) or '').strip()
    if capturado != '':
        return capturado

    respuesta = hc.get(depende)
    if respuesta is None or str(respuesta).strip() == '':
        return ''

    return (SIN_ESPECIFICAR if pide_complemento(cuando, str(respuesta))
            else relleno)


# =====================================================================
#  1. La tabla del PHP es la que pidio el consultorio
# =====================================================================
print('La tabla')

php = (RAIZ / 'includes/core/historia_clinica.php').read_text(encoding='utf-8')

bloque = php[php.index('COMPLEMENTOS = ['):]
bloque = bloque[:bloque.index('\n    ];')]

delPhp = {}
for m in re.finditer(
        r"'(\w+)'\s*=>\s*\[\s*'depende'\s*=>\s*'([^']+)',\s*"
        r"'cuando'\s*=>\s*'([^']+)',\s*'relleno'\s*=>\s*'([^']+)',",
        bloque):
    campo, depende, cuando, relleno = m.groups()
    delPhp[campo] = (depende, cuando, relleno)

comprobar('el PHP trae las doce', len(delPhp), 12)
comprobar('y dicen exactamente lo que pidio el consultorio', delPhp, ESPERADA)

# =====================================================================
#  2. Cada renglon de la tabla, con la opcion que NO lleva complemento
# =====================================================================
print()
print('Lo que se imprime cuando la respuesta no pide nada')

# La respuesta contraria a la que pide complemento, pregunta por
# pregunta. Es la columna "OPCION" de la tabla de Angel.
SIN_COMPLEMENTO = {
    'primera_visita_dentista': '1',           # SI es la primera
    'complicaciones_parto':    '0',           # NO hubo
    'lactancia_materna':       '0',
    'formula':                 '0',
    'dieta_completa':          '1',           # COMPLETA
    'momentos_azucar':         'NO CONSUME',
    'enfermedad_cronica':      '0',
    'discapacidad':            '0',
    'alergia':                 '0',
    'tratamiento_medico':      '0',
    'cirugias':                '0',
    'otro_higiene':            '0',
}

for campo, (depende, cuando, relleno) in ESPERADA.items():
    hc = {depende: SIN_COMPLEMENTO[depende], campo: None}
    comprobar(f'{depende} = {SIN_COMPLEMENTO[depende]!r} imprime {relleno!r}',
              complemento_impreso(hc, campo), relleno)

# =====================================================================
#  3. Un hueco de verdad se queda vacio
# =====================================================================
print()
print('Un hueco dice NO ESPECIFICA, y no se tapa con "NINGUNA"')

# Esto es lo mas importante del archivo, y es lo que cambio en octubre
# de 2026.
#
# "Alergico: SI" sin decir a que es un detalle que falta. Rellenarlo con
# "NINGUNA" seria una contradiccion firmada: la hoja diria que si es
# alergico y que no tiene ninguna alergia. Eso sigue prohibido.
#
# Lo que si se escribe es "NO ESPECIFICA", que no contradice nada. Y lo
# que NO puede volver a pasar es que el renglon salga en blanco: un
# blanco en una hoja firmada se lee como "no se pregunto" y se puede
# llenar a mano despues de la firma.
PIDE = {
    'primera_visita_dentista': '0',
    'complicaciones_parto':    '1',
    'lactancia_materna':       '1',
    'formula':                 '1',
    'dieta_completa':          '0',
    'momentos_azucar':         'DIARIO',
    'enfermedad_cronica':      '1',
    'discapacidad':            '1',
    'alergia':                 '1',
    'tratamiento_medico':      '1',
    'cirugias':                '1',
    'otro_higiene':            '1',
}

for campo, (depende, cuando, relleno) in ESPERADA.items():
    hc = {depende: PIDE[depende], campo: None}
    obtenido = complemento_impreso(hc, campo)

    comprobar(f'{depende} = {PIDE[depende]!r} sin detalle imprime NO ESPECIFICA',
              obtenido, SIN_ESPECIFICAR)
    # Y explicitamente no el relleno de la respuesta contraria, que es
    # el error que firmaria una contradiccion.
    if obtenido == relleno:
        FALLOS.append(f'{campo}: un detalle que falta se tapa con {relleno!r}')

# =====================================================================
#  4. Lo capturado manda siempre
# =====================================================================
print()
print('Lo capturado manda')

comprobar('con detalle escrito, se imprime el detalle',
          complemento_impreso({'alergia': '1', 'alergia_detalle': 'PENICILINA'},
                              'alergia_detalle'),
          'PENICILINA')

# Hasta cuando la respuesta dice que no hacia falta: si alguien escribio
# algo, es porque tenia algo que decir, y el expediente lo conserva.
comprobar('con detalle escrito y respuesta "no", tambien',
          complemento_impreso({'alergia': '0',
                               'alergia_detalle': 'TOLERO BIEN LA LIDOCAINA'},
                              'alergia_detalle'),
          'TOLERO BIEN LA LIDOCAINA')

# =====================================================================
#  5. Sin contestar la pregunta madre no se rellena nada
# =====================================================================
print()
print('Una historia a medio capturar')

# Aqui el blanco es correcto: la pregunta no se contesto. Poner
# "NINGUNA" afirmaria algo que nadie dijo.
for campo, (depende, cuando, relleno) in ESPERADA.items():
    hc = {depende: None, campo: None}
    if complemento_impreso(hc, campo) != '':
        FALLOS.append(f'{campo}: se rellena sin haber contestado {depende}')

comprobar('sin respuesta en la pregunta, el complemento queda en blanco',
          [f for f in FALLOS if 'se rellena sin haber' in f], [])

# Y el caso raro del "!": sin contestar la frecuencia de azucar, no se
# pide el detalle NI se rellena. Una regla invertida mal escrita daria
# "N/A" en una historia donde no se pregunto.
comprobar('el "!" no confunde "sin contestar" con "no consume"',
          complemento_impreso({'momentos_azucar': '', 'alimentos_azucar': None},
                              'alimentos_azucar'), '')

# =====================================================================
#  6. Las doce salen en la hoja impresa
# =====================================================================
print()
print('La hoja impresa las pinta todas')

impr = (RAIZ / 'historia_imprimir.php').read_text(encoding='utf-8')

for campo in ESPERADA:
    if f"'{campo}'" not in impr:
        FALLOS.append(f'{campo} no se imprime en la hoja')

comprobar('los doce complementos aparecen en la hoja',
          [f for f in FALLOS if 'no se imprime en la hoja' in f], [])

# Y pasan por la funcion que rellena, no por dato() a secas: con dato()
# el renglon vuelve a salir en blanco cuando la respuesta es NO.
sinRellenar = [c for c in ESPERADA if f"$hc['{c}']" in impr]
comprobar('ninguno se pinta sin pasar por el relleno', sinRellenar, [])

# =====================================================================
#  7. El PHP y el JavaScript dicen lo mismo
# =====================================================================
print()
print('El texto por omision, igual en los dos lados')

# La pantalla y la hoja impresa no pueden contestar distinto a la misma
# pregunta. Si la capsula dijera "SIN DATO" y la hoja "NO ESPECIFICA",
# el expediente firmado no coincidiria con lo que se vio al capturar, y
# el que lo note va a ser un auditor, no yo.
comprobar('el PHP define el texto por omision',
          f"SIN_ESPECIFICAR = '{SIN_ESPECIFICAR}'" in php, True)

js = (RAIZ / 'assets/js/historia.js').read_text(encoding='utf-8')
comprobar('el JavaScript define el mismo texto',
          f"SIN_ESPECIFICAR = '{SIN_ESPECIFICAR}'" in js, True)

# Y complementoImpreso tiene que USAR la constante. Que exista y no se
# use dejaria el renglon en blanco con la constante ahi de adorno.
impreso = php[php.index('function complementoImpreso'):]
comprobar('complementoImpreso devuelve la constante',
          'self::SIN_ESPECIFICAR' in impreso, True)

# El metodo que bloqueaba el guardado no puede volver: era lo que
# obligaba a inventar un detalle o a cambiar el "si" por un "no".
comprobar('complementosQueFaltan ya no existe',
          'function complementosQueFaltan' in php, False)
comprobar('y el JavaScript tampoco lo reimplementa',
          'function respuestasQueFaltan' in js, False)

print()
if FALLOS:
    print(f'*** {len(FALLOS)} PROBLEMA(S) ***')
    for f in FALLOS:
        print('  !!   ' + f)
    sys.exit(1)
print('*** TODO EN ORDEN ***')
