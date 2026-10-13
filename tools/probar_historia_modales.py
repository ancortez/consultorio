#!/usr/bin/env python3
"""
Cuando se abre la ventanita de la pregunta extra, y cuando no.

Por que existe este arnes
-------------------------
La primera version de este mecanismo colgaba la pregunta del evento
'change' del formulario. Parecia razonable y estaba mal: 'change' no
distingue quien lo provoco. Salta igual cuando alguien pica "Si" que
cuando el codigo rellena el formulario con la historia ya guardada.

El resultado, que descubrio la clienta y no yo:

  - abrir un expediente ya capturado lanzaba las doce ventanas seguidas
  - el prellenado hacia lo mismo
  - escribir en cualquier campo y salir de el las volvia a abrir, asi
    que tampoco se podia llegar a guardar

Lo que se prueba aqui no es el dibujo sino QUIEN dispara la pregunta,
que es donde estuvo el error. Se porta la maquina de estados —elegir,
atender, el recorrido completo y la revision de lo que falta— y se
cuenta cuantas ventanas se abririan en cada situacion. Contar es lo
importante: el fallo no era que la ventana estuviera mal hecha, era que
aparecia cuando nadie la habia pedido.
"""
import pathlib
import re
import sys

FALLOS = []

# Las doce preguntas, tal como estan en la pantalla: de que respuesta
# cuelga cada una y con que valor aparece.
BLOQUES = {
    'ultima_visita_dentista': ('primera_visita_dentista', '0'),
    'complicaciones_detalle': ('complicaciones_parto',    '1'),
    'lactancia_hasta':        ('lactancia_materna',       '1'),
    'formula_hasta':          ('formula',                 '1'),
    'dieta_porque':           ('dieta_completa',          '0'),
    'alimentos_azucar':       ('momentos_azucar',         '!NO CONSUME'),
    'enfermedad_detalle':     ('enfermedad_cronica',      '1'),
    'discapacidad_detalle':   ('discapacidad',            '1'),
    'alergia_detalle':        ('alergia',                 '1'),
    'tratamiento_detalle':    ('tratamiento_medico',      '1'),
    'cirugias_detalle':       ('cirugias',                '1'),
    'otro_higiene_detalle':   ('otro_higiene',            '1'),
}


class Pantalla:
    """La historia clinica, con lo justo para razonar sobre las ventanas."""

    def __init__(self):
        self.respuesta = {}     # lo que vale cada selector
        self.detalle = {}       # la respuesta extra de cada bloque
        self.previo = {}
        self.abiertas = []      # cada ventana que se abrio, en orden
        self.enCurso = None

    # ---- toca(): ¿esta respuesta pide la pregunta extra? ----
    @staticmethod
    def toca(cuando, valor):
        if cuando.startswith('!'):
            return valor != '' and valor != cuando[1:]
        return valor == cuando

    # ---- atender(): pone al dia UN bloque ----
    def atender(self, campo, preguntar=False):
        disparador, cuando = BLOQUES[campo]
        valor = self.respuesta.get(disparador, '')

        if not self.toca(cuando, valor):
            self.detalle[campo] = ''
            return

        if preguntar and not self.detalle.get(campo) and self.enCurso is None:
            self.abiertas.append(campo)
            self.enCurso = campo

    # ---- elegir(): el unico camino por el que cambia una respuesta ----
    def elegir(self, disparador, valor, porPersona=False):
        self.previo[disparador] = self.respuesta.get(disparador, '')
        self.respuesta[disparador] = '' if valor is None else str(valor)

        for campo, (dep, _) in BLOQUES.items():
            if dep == disparador:
                self.atender(campo, porPersona)

    # ---- el recorrido completo, que NUNCA pregunta ----
    def aplicarDependencias(self):
        for campo in BLOQUES:
            self.atender(campo)

    # ---- contestar o cerrar la ventana ----
    def contestar(self, texto):
        assert self.enCurso, 'no hay ninguna ventana abierta'
        self.detalle[self.enCurso] = texto
        self.enCurso = None

    def cerrarSinContestar(self):
        assert self.enCurso, 'no hay ninguna ventana abierta'
        campo = self.enCurso
        self.enCurso = None
        disparador, _ = BLOQUES[campo]
        antes = self.previo.get(disparador, '')
        self.elegir(disparador, antes if antes != '' else None)
        self.detalle[campo] = ''

    # ---- lo que falta: aplica pero esta en blanco ----
    def respuestasQueFaltan(self):
        faltan = []
        for campo, (disparador, cuando) in BLOQUES.items():
            if not self.toca(cuando, self.respuesta.get(disparador, '')):
                continue
            if self.detalle.get(campo):
                continue
            faltan.append(campo)
        return faltan


def comprobar(nombre, obtenido, esperado):
    if obtenido != esperado:
        FALLOS.append(f'{nombre}\n      esperaba: {esperado!r}\n      obtuvo:   {obtenido!r}')
    else:
        print(f'  ok   {nombre}')


# =====================================================================
#  1. Abrir un expediente YA capturado
# =====================================================================
# Es el caso que reporto la clienta: entrar y recibir doce ventanas sin
# haber tocado nada.
GUARDADA = {
    'primera_visita_dentista': '0', 'complicaciones_parto': '1',
    'lactancia_materna': '1', 'formula': '0', 'dieta_completa': '1',
    'momentos_azucar': '3', 'enfermedad_cronica': '1', 'discapacidad': '0',
    'alergia': '1', 'tratamiento_medico': '0', 'cirugias': '0',
    'otro_higiene': '0',
}
DETALLES = {
    'ultima_visita_dentista': 'HACE 6 MESES',
    'complicaciones_detalle': 'PARTO PROLONGADO',
    'lactancia_hasta': 'AL ANIO',
    'alimentos_azucar': 'JUGOS',
    'enfermedad_detalle': 'ASMA',
    'alergia_detalle': 'PENICILINA',
}

p = Pantalla()
for disparador, valor in GUARDADA.items():
    p.elegir(disparador, valor)          # asi carga el codigo: sin persona
p.detalle.update(DETALLES)
p.aplicarDependencias()

comprobar('al abrir una historia guardada no se abre NINGUNA ventana',
          p.abiertas, [])

# =====================================================================
#  2. El prellenado
# =====================================================================
# Contesta varias preguntas de golpe y no puede saber los detalles.
HABITUAL = {'complicaciones_parto': '0', 'lactancia_materna': '1',
            'formula': '0', 'dieta_completa': '1', 'enfermedad_cronica': '0',
            'discapacidad': '0', 'alergia': '0', 'tratamiento_medico': '0',
            'cirugias': '0', 'otro_higiene': '0'}

p = Pantalla()
for disparador, valor in HABITUAL.items():
    p.elegir(disparador, valor)
p.aplicarDependencias()

comprobar('el prellenado tampoco abre ventanas', p.abiertas, [])

# Pero deja una respuesta a medias: "lactancia materna si" sin el
# "hasta cuando". Eso NO puede pasar al siguiente paso.
comprobar('y avisa de la respuesta que quedo a medias',
          p.respuestasQueFaltan(), ['lactancia_hasta'])

# =====================================================================
#  3. Alguien contesta de verdad
# =====================================================================
p = Pantalla()
p.elegir('alergia', '1', porPersona=True)

comprobar('picar "si" abre UNA ventana', len(p.abiertas), 1)
comprobar('y es la de esa pregunta, no la de otra',
          p.abiertas[0], 'alergia_detalle')

p.contestar('PENICILINA')
comprobar('contestada, la respuesta queda guardada',
          p.detalle['alergia_detalle'], 'PENICILINA')
comprobar('y ya no falta nada', p.respuestasQueFaltan(), [])

# Lo que mas dolia: despues de contestar, cualquier otro movimiento
# volvia a abrir ventanas. El recorrido completo no debe abrir ninguna.
antes = len(p.abiertas)
p.aplicarDependencias()
p.aplicarDependencias()
comprobar('despues de contestar, repintar no abre nada mas',
          len(p.abiertas), antes)

# =====================================================================
#  4. Cerrar sin contestar deshace la respuesta
# =====================================================================
p = Pantalla()
p.elegir('lactancia_materna', '0')              # estaba en "no"
p.elegir('lactancia_materna', '1', porPersona=True)
comprobar('cambiar a "si" abre su ventana', p.abiertas, ['lactancia_hasta'])

p.cerrarSinContestar()
comprobar('cerrar sin contestar regresa la respuesta a "no"',
          p.respuesta['lactancia_materna'], '0')
comprobar('y no deja un "si" a medias', p.respuestasQueFaltan(), [])

# Si no habia respuesta previa, se queda en blanco.
p = Pantalla()
p.elegir('cirugias', '1', porPersona=True)
p.cerrarSinContestar()
comprobar('sin respuesta previa, queda sin contestar',
          p.respuesta['cirugias'], '')

# =====================================================================
#  5. Cambiar un "si" por un "no" limpia el detalle
# =====================================================================
p = Pantalla()
p.elegir('alergia', '1', porPersona=True)
p.contestar('PENICILINA')
p.elegir('alergia', '0', porPersona=True)

comprobar('al pasar a "no", el detalle se borra',
          p.detalle.get('alergia_detalle'), '')
comprobar('y no se abre ventana por una pregunta que ya no aplica',
          p.abiertas, ['alergia_detalle'])

# =====================================================================
#  6. La pregunta que cuelga de un "no"
# =====================================================================
# "¿Es la primera visita?" pide "¿cuando fue la ultima vez?" con NO.
p = Pantalla()
p.elegir('primera_visita_dentista', '1', porPersona=True)
comprobar('primera visita SI no pregunta nada mas', p.abiertas, [])

p.elegir('primera_visita_dentista', '0', porPersona=True)
comprobar('primera visita NO pregunta cuando fue la ultima',
          p.abiertas, ['ultima_visita_dentista'])

# =====================================================================
#  7. La de "todas menos una"
# =====================================================================
p = Pantalla()
p.elegir('momentos_azucar', 'NO CONSUME', porPersona=True)
comprobar('"no consume" azucar no pregunta que alimentos', p.abiertas, [])

p.elegir('momentos_azucar', '3', porPersona=True)
comprobar('cualquier otra frecuencia si pregunta',
          p.abiertas, ['alimentos_azucar'])

# =====================================================================
#  8. Una sola ventana a la vez
# =====================================================================
# Si dos respuestas cambian casi a la vez, no pueden salir dos ventanas
# encimadas.
p = Pantalla()
p.elegir('alergia', '1', porPersona=True)
p.elegir('cirugias', '1', porPersona=True)
comprobar('con una ventana abierta no se abre otra encima',
          p.abiertas, ['alergia_detalle'])

p.contestar('PENICILINA')
p.elegir('cirugias', '1', porPersona=True)
comprobar('al cerrarse la primera, la siguiente ya puede abrir',
          p.abiertas, ['alergia_detalle', 'cirugias_detalle'])

# =====================================================================
#  9. Esta copia y el codigo de verdad dicen lo mismo
# =====================================================================
raiz = pathlib.Path(__file__).resolve().parent.parent
js = (raiz / 'assets/js/historia.js').read_text(encoding='utf-8')
html = (raiz / 'vistas/paciente/_tab_historia.php').read_text(encoding='utf-8')

# Los pares de la pantalla tienen que ser los que se prueban aqui.
reales = {}
for m in re.finditer(
        r'data-depende="(\w+)" data-cuando="([^"]+)">(.*?)</div>', html, re.S):
    nom = re.search(r'name="(\w+)"', m.group(3))
    if nom:
        reales[nom.group(1)] = (m.group(1), m.group(2))

comprobar('los 12 bloques de la pantalla son los que se prueban',
          sorted(reales.items()), sorted(BLOQUES.items()))

# Y lo esencial: que nadie vuelva a colgar la pregunta del 'change'.
escucha = re.search(r"addEventListener\('change',\s*(.*?)\)", js, re.S)
comprobar('el escucha de change ya no pregunta nada',
          'aplicarDependencias(true)' in js, False)
comprobar('la pregunta cuelga de quien pico, no del evento',
          'porPersona = false' in js, True)

print()
if FALLOS:
    print(f'*** {len(FALLOS)} PROBLEMA(S) ***')
    for f in FALLOS:
        print('  !!   ' + f)
    sys.exit(1)
print('*** TODO EN ORDEN ***')
