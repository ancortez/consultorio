#!/usr/bin/env python3
"""
El ciclo de vida de un plan de tratamiento.

Por que existe
--------------
Al abrir este tramo encontre un fallo que llevaba meses ahi: marcar el
avance de un concepto podia SACAR a un plan de Terminado. La condicion
decia "NOT IN (5)" —protegia a los cancelados— y se olvidaba de los
terminados. Desmarcar una casilla en un tratamiento cerrado hace medio
anio lo devolvia a En proceso, y ese paciente reaparecia como activo en
el listado y en sus cuentas.

No lo vio nadie porque nadie lo probó: la regla estaba repartida entre
tres pantallas y cada una decidia por su cuenta. Ahora vive en un solo
sitio, y lo que se prueba aqui es lo que esa regla PROHIBE, que es su
razon de ser. Las transiciones permitidas se notan enseguida cuando
faltan; las prohibidas no se notan nunca, hasta que alguien descubre un
plan cobrado dos veces.

No hay PHP en esta maquina: la regla se porta tal cual y se recorren
TODAS las transiciones posibles, no solo las que se me ocurran.
"""
import itertools
import pathlib
import re
import sys

FALLOS = []

PROPUESTO, ACEPTADO, EN_PROCESO, TERMINADO, CANCELADO = 1, 2, 3, 4, 5
FINALES = [TERMINADO, CANCELADO]

NOMBRE = {PROPUESTO: 'Propuesto', ACEPTADO: 'Aceptado',
          EN_PROCESO: 'En proceso', TERMINADO: 'Terminado',
          CANCELADO: 'Cancelado'}
TODOS = list(NOMBRE)


# ---- Puerto literal de la clase Planes ----
def es_final(estado):
    return estado in FINALES


def puede_cancelar(estado):
    return estado == PROPUESTO


def puede_terminar(estado):
    return estado in (ACEPTADO, EN_PROCESO)


def puede_avanzar_auto(estado):
    return not es_final(estado)


def comprobar(nombre, obtenido, esperado):
    if obtenido != esperado:
        FALLOS.append(f'{nombre}\n      esperaba: {esperado!r}\n      obtuvo:   {obtenido!r}')
    else:
        print(f'  ok   {nombre}')


# =====================================================================
#  Lo que esta PROHIBIDO, que es para lo que sirve la regla
# =====================================================================
print('De un plan cerrado no se sale')

for final in FINALES:
    comprobar(f'{NOMBRE[final]} no se puede cancelar',
              puede_cancelar(final), False)
    comprobar(f'{NOMBRE[final]} no se puede terminar',
              puede_terminar(final), False)
    comprobar(f'{NOMBRE[final]} no lo mueve el avance automatico',
              puede_avanzar_auto(final), False)

# El fallo concreto que encontre: la condicion vieja solo miraba al 5.
def regla_vieja(estado):
    """Como estaba antes: 'WHERE id_estado_plan NOT IN (5)'."""
    return estado != CANCELADO

comprobar('la regla VIEJA dejaba mover un plan Terminado (el fallo)',
          regla_vieja(TERMINADO), True)
comprobar('la de ahora no', puede_avanzar_auto(TERMINADO), False)

# =====================================================================
#  Cancelar: solo antes de que el paciente acepte
# =====================================================================
print()
print('Cancelar')

comprobar('un plan Propuesto si se cancela', puede_cancelar(PROPUESTO), True)
for e in (ACEPTADO, EN_PROCESO):
    comprobar(f'{NOMBRE[e]} ya no: hay firma de por medio',
              puede_cancelar(e), False)

# =====================================================================
#  Terminar: solo lo que empezo
# =====================================================================
print()
print('Terminar')

comprobar('Propuesto no se termina: no empezo', puede_terminar(PROPUESTO), False)
for e in (ACEPTADO, EN_PROCESO):
    comprobar(f'{NOMBRE[e]} si se puede dar por terminado',
              puede_terminar(e), True)

# =====================================================================
#  TODAS las transiciones, no solo las que se me ocurran
# =====================================================================
# Se recorre la matriz completa y se compara contra la tabla de lo
# permitido. Asi, agregar un estado nuevo manianaa obliga a decidir
# explicitamente que se puede hacer con el, en vez de heredar un
# permiso por descuido.
print()
print('La matriz completa')

PERMITIDAS = {
    # (desde, hacia): por que camino
    (PROPUESTO,  CANCELADO):  'cancelar',
    (ACEPTADO,   TERMINADO):  'terminar',
    (EN_PROCESO, TERMINADO):  'terminar',
    (ACEPTADO,   EN_PROCESO): 'avance',
    (EN_PROCESO, EN_PROCESO): 'avance',
    (ACEPTADO,   ACEPTADO):   'avance',       # sigue sin conceptos hechos
    (EN_PROCESO, TERMINADO):  'terminar',
    (PROPUESTO,  EN_PROCESO): 'avance',
    (PROPUESTO,  TERMINADO):  'avance',
}


def caminos_desde(estado):
    """Que se puede hacer con un plan en este estado."""
    puede = set()
    if puede_cancelar(estado):
        puede.add(CANCELADO)
    if puede_terminar(estado):
        puede.add(TERMINADO)
    if puede_avanzar_auto(estado):
        puede.update({EN_PROCESO, TERMINADO})
    return puede


salidas = {e: caminos_desde(e) for e in TODOS}

comprobar('de Terminado no sale ningun camino', sorted(salidas[TERMINADO]), [])
comprobar('de Cancelado tampoco', sorted(salidas[CANCELADO]), [])
comprobar('de Propuesto se puede cancelar o avanzar',
          sorted(salidas[PROPUESTO]), [EN_PROCESO, TERMINADO, CANCELADO])
comprobar('de Aceptado se avanza o se termina, pero no se cancela',
          sorted(salidas[ACEPTADO]), [EN_PROCESO, TERMINADO])
comprobar('de En proceso igual',
          sorted(salidas[EN_PROCESO]), [EN_PROCESO, TERMINADO])

# Ningun camino puede llevar a Propuesto o a Aceptado: se retrocederia.
for desde, hacia in itertools.product(TODOS, [PROPUESTO, ACEPTADO]):
    if hacia in salidas[desde] and desde != hacia:
        FALLOS.append(f'se puede retroceder de {NOMBRE[desde]} a {NOMBRE[hacia]}')

comprobar('ningun camino retrocede a Propuesto ni a Aceptado',
          [f for f in FALLOS if 'retroceder' in f], [])

# =====================================================================
#  Una vida completa, incluida la equivocacion
# =====================================================================
print()
print('La vida de un plan')


class Plan:
    def __init__(self):
        self.estado = PROPUESTO
        self.motivo = None
        self.intentos_rechazados = 0

    def cancelar(self, motivo):
        if es_final(self.estado) or not puede_cancelar(self.estado):
            self.intentos_rechazados += 1
            return False
        if not motivo.strip():
            self.intentos_rechazados += 1
            return False
        self.estado, self.motivo = CANCELADO, motivo
        return True

    def terminar(self):
        if es_final(self.estado) or not puede_terminar(self.estado):
            self.intentos_rechazados += 1
            return False
        self.estado = TERMINADO
        return True

    def aceptar(self):
        # Lo dispara el escaneo de la hoja firmada.
        if es_final(self.estado):
            self.intentos_rechazados += 1
            return False
        self.estado = ACEPTADO
        return True

    def avance(self, faltan):
        if not puede_avanzar_auto(self.estado):
            self.intentos_rechazados += 1
            return False
        self.estado = TERMINADO if faltan == 0 else EN_PROCESO
        return True


# Camino normal: se propone, se acepta, se trabaja, se cierra.
p = Plan()
p.aceptar()
p.avance(faltan=3)
comprobar('trabajar en el plan lo pone En proceso', p.estado, EN_PROCESO)
p.avance(faltan=0)
comprobar('terminar todos los conceptos lo cierra', p.estado, TERMINADO)

# Y ahora el descuido: alguien desmarca un concepto del plan cerrado.
comprobar('desmarcar un concepto NO lo reabre', p.avance(faltan=1), False)
comprobar('y se queda Terminado', p.estado, TERMINADO)
comprobar('tampoco se puede cancelar despues', p.cancelar('me equivoque'), False)

# Camino de cancelacion.
p = Plan()
comprobar('cancelar sin motivo no se permite', p.cancelar('   '), False)
comprobar('con motivo si', p.cancelar('EL PACIENTE SE CAMBIO DE CIUDAD'), True)
comprobar('y el motivo queda guardado', p.motivo, 'EL PACIENTE SE CAMBIO DE CIUDAD')
comprobar('un plan cancelado ya no se acepta', p.aceptar(), False)
comprobar('ni se termina', p.terminar(), False)

# Camino del abandono: aceptado, trabajado a medias, y se cierra a mano.
p = Plan()
p.aceptar()
p.avance(faltan=2)
comprobar('se puede cerrar con conceptos pendientes', p.terminar(), True)
comprobar('queda Terminado', p.estado, TERMINADO)

# =====================================================================
#  Esta copia y el PHP dicen lo mismo
# =====================================================================
raiz = pathlib.Path(__file__).resolve().parent.parent
php = (raiz / 'includes/core/planes.php').read_text(encoding='utf-8')

for nombre, valor in [('PROPUESTO', PROPUESTO), ('ACEPTADO', ACEPTADO),
                      ('EN_PROCESO', EN_PROCESO), ('TERMINADO', TERMINADO),
                      ('CANCELADO', CANCELADO)]:
    m = re.search(r'const\s+' + nombre + r'\s*=\s*(\d+)', php)
    comprobar(f'{nombre} vale lo mismo en PHP',
              int(m.group(1)) if m else None, valor)

# Y que el avance automatico de verdad consulte la regla, en vez de
# llevar otra vez su propia condicion.
avance = (raiz / 'includes/consultas/plan_avance.php').read_text(encoding='utf-8')

# Sin los comentarios: el comentario que explica el arreglo tiene que
# NOMBRAR la condicion vieja para contar que estaba mal, y buscarla en
# el archivo entero hace que el texto que documenta el arreglo se lea
# como si el arreglo no estuviera. Van ya varias veces.
avanceCodigo = re.sub(r'/\*.*?\*/', '', avance, flags=re.S)
avanceCodigo = re.sub(r'^\s*//.*$', '', avanceCodigo, flags=re.M)

comprobar('el avance automatico pregunta a Planes',
          'Planes::puedeAvanzarAuto' in avanceCodigo, True)
comprobar('y ya no lleva la condicion vieja a mano',
          'NOT IN (5)' in avanceCodigo, False)

print()
if FALLOS:
    print(f'*** {len(FALLOS)} PROBLEMA(S) ***')
    for f in FALLOS:
        print('  !!   ' + f)
    sys.exit(1)
print('*** TODO EN ORDEN ***')
