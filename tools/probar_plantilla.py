#!/usr/bin/env python3
"""
La sustitucion de etiquetas del recordatorio, portada a Python y
corrida de verdad.

No hay PHP en esta maquina, asi que la unica forma de saber que la
cuenta sale es reescribir el algoritmo tal cual y darle datos. Lo que
se prueba es el orden: '{paciente}' es un prefijo de
'{paciente_completo}', y sustituir el corto primero deja el pegote
'MARIA_completo}' en medio del mensaje.
"""
import re
import sys
import pathlib

FALLOS = []


def sustituir(plantilla: str, valores: dict) -> str:
    """Puerto literal de AgendaExtra::sustituir(): UNA sola pasada."""
    return re.sub(r'\{(\w+)\}',
                  lambda m: valores.get(m.group(1), m.group(0)),
                  plantilla)


def comprobar(nombre, obtenido, esperado):
    if obtenido != esperado:
        FALLOS.append(f'{nombre}\n      esperaba: {esperado!r}\n      obtuvo:   {obtenido!r}')
    else:
        print(f'  ok   {nombre}')


DATOS = {
    'paciente_completo': 'MARIA FERNANDA GUTIERREZ LOPEZ',
    'paciente':    'MARIA',
    'fecha':       'Lunes 13 de octubre de 2026',
    'hora':        '10:30 am',
    'dentista':    'DRA. ADRIANA LOPEZ RUELAS',
    'consultorio': 'CDEO',
}

# ---- El caso que motiva el orden ----
comprobar('la etiqueta larga no la parte la corta',
          sustituir('{paciente_completo}', DATOS),
          'MARIA FERNANDA GUTIERREZ LOPEZ')

comprobar('las dos juntas, cada una en lo suyo',
          sustituir('{paciente} / {paciente_completo}', DATOS),
          'MARIA / MARIA FERNANDA GUTIERREZ LOPEZ')

comprobar('y al reves tambien',
          sustituir('{paciente_completo} / {paciente}', DATOS),
          'MARIA FERNANDA GUTIERREZ LOPEZ / MARIA')

# ---- La plantilla de siempre ----
PLANTILLA = ('Hola, le escribimos de {consultorio}.\n\n'
             'Le recordamos la cita de {paciente} el {fecha} a las {hora}'
             ' con {dentista}.\n\n'
             'Si necesita cambiarla, responda a este mensaje. Gracias.')

armado = sustituir(PLANTILLA, DATOS)
comprobar('no queda ninguna etiqueta sin sustituir',
          re.findall(r'\{[a-z_]+\}', armado), [])
comprobar('el mensaje de siempre sale completo', armado,
          'Hola, le escribimos de CDEO.\n\n'
          'Le recordamos la cita de MARIA el Lunes 13 de octubre de 2026 '
          'a las 10:30 am con DRA. ADRIANA LOPEZ RUELAS.\n\n'
          'Si necesita cambiarla, responda a este mensaje. Gracias.')

# ---- Lo que escribe una persona de verdad ----
comprobar('una etiqueta repetida se sustituye las dos veces',
          sustituir('{paciente}, {paciente}', DATOS), 'MARIA, MARIA')

comprobar('una etiqueta inventada se queda tal cual',
          sustituir('Hola {telefono}', DATOS), 'Hola {telefono}')

comprobar('una llave suelta no rompe nada',
          sustituir('El 50% { de descuento', DATOS), 'El 50% { de descuento')

comprobar('sin etiquetas, el texto pasa intacto',
          sustituir('Buenos dias.', DATOS), 'Buenos dias.')

# ---- Lo que escribe el paciente no se vuelve instruccion ----
# En recepcion se teclea de todo. Si el nombre capturado trae algo que
# parece una etiqueta, tiene que salir tal cual y no sustituirse: el
# mensaje lo redacta la doctora, no el dato.
TRAVIESO = dict(DATOS, paciente='MARIA {hora}')
comprobar('una etiqueta dentro de un valor NO se sustituye',
          sustituir('Cita de {paciente} a las {hora}', TRAVIESO),
          'Cita de MARIA {hora} a las 10:30 am')

comprobar('ni aunque el valor sea una etiqueta entera',
          sustituir('{paciente}', dict(DATOS, paciente='{dentista}')),
          '{dentista}')

# ---- La lista de etiquetas de PHP y la de la pantalla coinciden ----
# Si la pantalla ofrece un boton que el armado no sabe sustituir, la
# doctora manda un mensaje con "{sala}" literal al paciente.
raiz = pathlib.Path(__file__).resolve().parent.parent
php = (raiz / 'includes/core/agenda_extra.php').read_text(encoding='utf-8')

bloque = php[php.index('public static function etiquetas()'):]
# Hasta el '];' que cierra el arreglo, no hasta la primera llave: la
# primera llave del bloque es la de '{paciente}' y cortar ahi dejaba
# una lista vacia que casaba con cualquier cosa.
bloque = bloque[:bloque.index('];')]
ofrecidas = set(re.findall(r"'\{(\w+)\}'\s*=>", bloque))

comprobar('las etiquetas que se ofrecen son las que se saben sustituir',
          sorted(ofrecidas), sorted(DATOS))

# Igual aqui: la constante se arma con varias cadenas concatenadas, y
# el ';' que la cierra esta al final. Se corta en el primer ';' que
# cae al principio de una linea, que es como esta escrita.
plantillaPhp = php[php.index('PLANTILLA_POR_OMISION'):]
corte = re.search(r';\s*\n', plantillaPhp)
plantillaPhp = plantillaPhp[:corte.start()] if corte else plantillaPhp
usadas = set(re.findall(r'\{(\w+)\}', plantillaPhp))
sobran = usadas - ofrecidas
comprobar('la plantilla de origen solo usa etiquetas conocidas',
          sorted(sobran), [])

print()
if FALLOS:
    print(f'*** {len(FALLOS)} PROBLEMA(S) ***')
    for f in FALLOS:
        print('  !!   ' + f)
    sys.exit(1)
print('*** TODO EN ORDEN ***')
