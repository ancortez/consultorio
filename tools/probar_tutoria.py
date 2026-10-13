#!/usr/bin/env python3
"""
La regla del tutor, portada a Python y corrida de verdad.

No hay PHP en esta maquina, asi que la unica forma de saber que la
regla sale bien es reescribirla tal cual y darle los casos. Y esta
regla lo merece: tiene cuatro entradas, un NULL que significa dos cosas
distintas segun donde caiga, y un umbral de edad que solo aplica a una
de las ramas. Leyendola parece obvia; corriendola es donde se ve.

Lo que mas me preocupaba al escribirla, y por eso esta probado:

  - que al cumplir 24 no se pregunte en CADA visita
  - que quien ya dijo "respondo por mi mismo" no vuelva a ser molestado
  - que un expediente viejo (sin edad de decision) se revise una vez
"""
import pathlib
import re
import sys

FALLOS = []

MAYORIA_EDAD = 18
EDAD_REVISION = 24


def hay_que_preguntar(edad, requiere_tutor, decidido_edad):
    """Puerto literal de Tutoria::hayQuePreguntar()."""
    if edad is None:
        return False
    if edad < MAYORIA_EDAD:
        return False
    if requiere_tutor is None:
        return True
    if int(requiere_tutor) == 0:
        return False
    if edad >= EDAD_REVISION:
        return decidido_edad is None or decidido_edad < EDAD_REVISION
    return False


def lleva_tutor(edad, requiere_tutor):
    """Puerto literal de Tutoria::llevaTutor()."""
    if requiere_tutor is not None:
        return int(requiere_tutor) == 1
    return True


def comprobar(nombre, obtenido, esperado):
    if obtenido != esperado:
        FALLOS.append(f'{nombre}: esperaba {esperado!r}, obtuvo {obtenido!r}')
    else:
        print(f'  ok   {nombre}')


# =====================================================================
#  Menores: nunca se pregunta
# =====================================================================
for edad in (0, 5, 12, 17):
    comprobar(f'a los {edad} anios no se pregunta',
              hay_que_preguntar(edad, None, None), False)

comprobar('un menor siempre lleva tutor', lleva_tutor(10, None), True)

# =====================================================================
#  El dia que cumple 18
# =====================================================================
comprobar('al cumplir 18, sin decidir, se pregunta',
          hay_que_preguntar(18, None, None), True)
comprobar('a los 20 sin decidir, se sigue preguntando',
          hay_que_preguntar(20, None, None), True)

# Mientras nadie conteste, el tutor se sigue viendo: esconderlo antes
# de que alguien decida seria borrar de la vista un dato capturado.
comprobar('sin decidir, el tutor se sigue mostrando',
          lleva_tutor(20, None), True)

# =====================================================================
#  Ya contesto: responde por si mismo
# =====================================================================
for edad in (18, 24, 30, 70):
    comprobar(f'a los {edad}, decidido que no, no se vuelve a preguntar',
              hay_que_preguntar(edad, 0, 18), False)

comprobar('quien no requiere tutor, no lo lleva', lleva_tutor(30, 0), False)

# =====================================================================
#  Ya contesto: sigue con tutor
# =====================================================================
comprobar('decidido a los 18 con tutor, a los 19 no se pregunta',
          hay_que_preguntar(19, 1, 18), False)
comprobar('decidido a los 18 con tutor, a los 23 todavia no',
          hay_que_preguntar(23, 1, 18), False)
comprobar('decidido a los 18 con tutor, a los 24 SI se pregunta',
          hay_que_preguntar(24, 1, 18), True)

# El caso que de verdad me preocupaba: contestar a los 24 y que NO
# vuelva a preguntar al dia siguiente, ni a los 25, ni nunca mas.
comprobar('contestado A LOS 24, a los 24 ya no se pregunta',
          hay_que_preguntar(24, 1, 24), False)
for edad in (25, 30, 45, 80):
    comprobar(f'contestado a los 24, a los {edad} tampoco',
              hay_que_preguntar(edad, 1, 24), False)

# Y si lo contestan despues, tampoco se repite.
comprobar('contestado a los 31, a los 40 no se pregunta',
          hay_que_preguntar(40, 1, 31), False)

# =====================================================================
#  Expedientes que ya existian cuando se agrego la columna
# =====================================================================
# La migracion les pone requiere_tutor = 1 (tienen tutor capturado) y
# deja la edad en NULL, porque de verdad no sabemos cuando se decidio.
comprobar('expediente viejo con tutor, a los 20: no se pregunta',
          hay_que_preguntar(20, 1, None), False)
comprobar('expediente viejo con tutor, a los 24: se revisa una vez',
          hay_que_preguntar(24, 1, None), True)
comprobar('expediente viejo, menor de edad: no se pregunta',
          hay_que_preguntar(9, 1, None), False)

# =====================================================================
#  Sin fecha de nacimiento
# =====================================================================
# Preguntar "¿lleva tutor?" sobre alguien de edad desconocida es pedirle
# al usuario que adivine. Primero que capturen la fecha.
comprobar('sin edad no se pregunta nada',
          hay_que_preguntar(None, None, None), False)
comprobar('sin edad, el tutor se sigue mostrando',
          lleva_tutor(None, None), True)

# =====================================================================
#  Una sola pregunta por expediente y por tramo
# =====================================================================
# Se simula la vida de un paciente: entra de 8 anios y vuelve cada anio
# hasta los 40. Se cuenta cuantas veces el sistema interrumpe.
def simular(responde_con_tutor):
    """Devuelve en que edades se pregunto."""
    requiere, decidido = None, None
    preguntadas = []
    for edad in range(8, 41):
        # Dos visitas por anio: la regla no puede depender de cuantas
        # veces se abra la ficha.
        for _ in range(2):
            if hay_que_preguntar(edad, requiere, decidido):
                preguntadas.append(edad)
                requiere = 1 if responde_con_tutor else 0
                decidido = edad
    return preguntadas

comprobar('siguiendo con tutor: se pregunta a los 18 y a los 24, y ya',
          simular(True), [18, 24])
comprobar('pasando a responder por si mismo: se pregunta una sola vez',
          simular(False), [18])

# =====================================================================
#  La regla de PHP y esta copia dicen lo mismo
# =====================================================================
# Un puerto que se queda viejo es peor que no tener puerto: da luz verde
# sobre codigo que ya no existe. Se comprueban las constantes.
raiz = pathlib.Path(__file__).resolve().parent.parent
php = (raiz / 'includes/core/tutoria.php').read_text(encoding='utf-8')

for nombre, valor in [('MAYORIA_EDAD', MAYORIA_EDAD),
                      ('EDAD_REVISION', EDAD_REVISION)]:
    m = re.search(r'const\s+' + nombre + r'\s*=\s*(\d+)', php)
    comprobar(f'{nombre} coincide con PHP',
              int(m.group(1)) if m else None, valor)

print()
if FALLOS:
    print(f'*** {len(FALLOS)} PROBLEMA(S) ***')
    for f in FALLOS:
        print('  !!   ' + f)
    sys.exit(1)
print('*** TODO EN ORDEN ***')
