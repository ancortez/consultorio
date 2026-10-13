#!/usr/bin/env python3
"""
El motivo que se propone para una cita de plan de tratamiento.

Por que existe este arnes
-------------------------
Son dos reglas chicas con una forma muy facil de equivocarse, y ninguna
de las dos avisa cuando se rompe: el expediente simplemente queda mal
escrito, y eso no se descubre hasta que alguien lo lee en voz alta
delante del paciente.

  1. El ORDINAL. "1RA CITA", "2DA CITA". La tentacion es sacarlo de una
     cuenta —la terminacion segun el ultimo digito— y esa cuenta se
     equivoca en el 11 y el 12, que son VA (undecima, duodecima) y no
     "11RA"/"12DA". Y el 11 y el 12 no son un caso raro de laboratorio:
     son la cita de cualquier plan largo de ortodoncia.

  2. La PROPUESTA no puede pisar lo escrito a mano. El campo se propone
     lleno, pero si alguien lo corrige y luego cambia de plan, su
     correccion tiene que quedarse. Una propuesta que borra lo que
     alguien tecleo deja de ser una propuesta y se convierte en un
     campo calculado con disfraz.

No hay PHP ni navegador en esta maquina: las dos reglas se portan tal
cual y se corren con datos. La primera se compara ademas contra la
tabla del PHP, leida del archivo, para que las dos copias no se
separen.
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
#  1. El ordinal
# =====================================================================
TABLA = {
    1: '1RA',   2: '2DA',   3: '3RA',   4: '4TA',   5: '5TA',
    6: '6TA',   7: '7MA',   8: '8VA',   9: '9NA',  10: '10MA',
    11: '11VA', 12: '12VA', 13: '13RA', 14: '14TA', 15: '15TA',
    16: '16TA', 17: '17MA', 18: '18VA', 19: '19NA', 20: '20MA',
}


def ordinal_corto(n):
    """Puerto literal de ordinalCorto()."""
    if n < 1:
        return '1RA'
    return TABLA.get(n, f'No. {n}')


def motivo_de_cita_de_plan(folio, consultas_previas):
    return (ordinal_corto(consultas_previas + 1)
            + ' CITA PLAN DE TRATAMIENTO ' + folio.upper())


print('El ordinal')

comprobar('la primera cita de un plan nuevo', ordinal_corto(1), '1RA')
comprobar('la segunda', ordinal_corto(2), '2DA')
comprobar('la tercera', ordinal_corto(3), '3RA')

# El caso que tumba a cualquier regla aritmetica. Mirar el ultimo digito
# daria "11RA" y "12DA", que no se escriben asi en ningun lado.
comprobar('la once NO es 11RA', ordinal_corto(11), '11VA')
comprobar('la doce NO es 12DA', ordinal_corto(12), '12VA')
comprobar('la trece si vuelve a seguir a la unidad', ordinal_corto(13), '13RA')
comprobar('y la diecisiete tambien', ordinal_corto(17), '17MA')

# Pasando de 20 se deja de abreviar a proposito: un plan con 21
# consultas existe, pero "21RA" no se escribe, y escribir mal el
# expediente por completar una tabla no vale la pena.
comprobar('pasando de 20 se deja de abreviar', ordinal_corto(21), 'No. 21')
comprobar('y no revienta', ordinal_corto(99), 'No. 99')

# Un plan sin consultas pide la primera; un numero imposible tampoco
# puede devolver vacio ni reventar.
comprobar('cero cae en la primera', ordinal_corto(0), '1RA')
comprobar('un negativo tambien', ordinal_corto(-3), '1RA')

print()
print('El motivo completo')

comprobar('un plan sin consultas propone la primera cita',
          motivo_de_cita_de_plan('PT-008', 0),
          '1RA CITA PLAN DE TRATAMIENTO PT-008')
comprobar('con dos consultas encima propone la tercera',
          motivo_de_cita_de_plan('PT-008', 2),
          '3RA CITA PLAN DE TRATAMIENTO PT-008')
comprobar('el folio sube a mayusculas',
          motivo_de_cita_de_plan('pt-012', 10),
          '11VA CITA PLAN DE TRATAMIENTO PT-012')

# =====================================================================
#  2. Esta copia y el PHP dicen lo mismo
# =====================================================================
print()
print('El PHP dice lo mismo')

php = (RAIZ / 'includes/core/funciones.php').read_text(encoding='utf-8')

bloque = php[php.index('function ordinalCorto'):]
bloque = bloque[:bloque.index('function motivoDeCitaDePlan')]

delPhp = {int(n): v for n, v in re.findall(r"(\d+)\s*=>\s*'([^']+)'", bloque)}
comprobar('la tabla del PHP es la misma que esta', delPhp, TABLA)

# Y que el PHP no haya inventado una regla aritmetica al lado de la
# tabla: un "% 10" aqui seria justo el fallo que la tabla evita.
comprobar('el PHP no saca el ordinal de una cuenta',
          '% 10' in bloque or 'intdiv' in bloque, False)


# =====================================================================
#  3. La propuesta no pisa lo escrito a mano
# =====================================================================
print()
print('La propuesta respeta lo que alguien escribio')


def proponer(actual, nuevo, todas_las_propuestas):
    """Puerto literal de la regla de proponerDelPlan().

    Pisa el campo solo si esta vacio o si tiene TEXTUALMENTE la
    propuesta de algun plan. Cualquier otra cosa es texto de una
    persona y se queda.
    """
    actual = actual.strip()
    es_mia = actual in todas_las_propuestas
    return nuevo if (actual == '' or es_mia) else actual


PROPUESTAS = [
    '1RA CITA PLAN DE TRATAMIENTO PT-008',
    '3RA CITA PLAN DE TRATAMIENTO PT-012',
]

comprobar('un campo vacio se llena',
          proponer('', PROPUESTAS[0], PROPUESTAS), PROPUESTAS[0])

comprobar('un campo con la propuesta de OTRO plan se reemplaza',
          proponer(PROPUESTAS[1], PROPUESTAS[0], PROPUESTAS), PROPUESTAS[0])

comprobar('un campo escrito a mano NO se toca',
          proponer('SE CAYO EN LA ESCUELA Y SE ROMPIO EL 11',
                   PROPUESTAS[0], PROPUESTAS),
          'SE CAYO EN LA ESCUELA Y SE ROMPIO EL 11')

# El caso que importa de verdad: alguien matiza la propuesta —le agrega
# una palabra— y luego cambia de plan. Lo que escribio se queda.
comprobar('una propuesta MATIZADA a mano tampoco se pisa',
          proponer(PROPUESTAS[1] + ' (LLEGO TARDE)', PROPUESTAS[0], PROPUESTAS),
          PROPUESTAS[1] + ' (LLEGO TARDE)')

# Espacios de sobra no convierten un texto propio en propuesta ni al
# contrario: "  1RA CITA..." sigue siendo la propuesta.
comprobar('los espacios de sobra no enganian a la comparacion',
          proponer('  ' + PROPUESTAS[1] + '  ', PROPUESTAS[0], PROPUESTAS),
          PROPUESTAS[0])

# Y el campo vacio de espacios cuenta como vacio.
comprobar('un campo con solo espacios cuenta como vacio',
          proponer('    ', PROPUESTAS[0], PROPUESTAS), PROPUESTAS[0])

print()
if FALLOS:
    print(f'*** {len(FALLOS)} PROBLEMA(S) ***')
    for f in FALLOS:
        print('  !!   ' + f)
    sys.exit(1)
print('*** TODO EN ORDEN ***')
