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

Lo que de verdad se prueba aqui es el caso contrario: que un hueco de
VERDAD siga saliendo vacio. Rellenar un "alergico: SI" al que le falta
el detalle con un "NINGUNA" no taparia un hueco, firmaria una
contradiccion. Eso es lo que hay que no hacer, y lo que nadie va a
descubrir mirando el codigo.

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

    return '' if pide_complemento(cuando, str(respuesta)) else relleno


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
print('Un hueco NO se tapa')

# Esto es lo mas importante del archivo. "Alergico: SI" sin decir a que
# es un dato que falta, y rellenarlo con "NINGUNA" convertiria un hueco
# en una contradiccion firmada: la hoja diria que si es alergico y que
# no tiene ninguna alergia.
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
    comprobar(f'{depende} = {PIDE[depende]!r} sin detalle deja el renglon vacio',
              complemento_impreso(hc, campo), '')

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

print()
if FALLOS:
    print(f'*** {len(FALLOS)} PROBLEMA(S) ***')
    for f in FALLOS:
        print('  !!   ' + f)
    sys.exit(1)
print('*** TODO EN ORDEN ***')
