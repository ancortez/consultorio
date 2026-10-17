#!/usr/bin/env python3
"""
Las notas de evolucion: corregir el texto y la fecha sin perder el rastro.

Por que existe este arnes
-------------------------
La doctora pidio poder editar una nota ya guardada, el comentario y la
fecha. Hasta ahora no se podia a proposito: "es parte del registro
clinico y se le agrega, no se le corrige". El argumento era bueno para
el texto y no resolvia lo que ella necesita: una nota escrita el martes
puede referirse a lo que paso el viernes anterior —anota cuando tiene un
momento, no cuando pasa la cosa— y la unica forma de arreglar un dedazo
era escribir otra nota diciendo "donde dice X, lease Y".

Dejarlo editar es facil. Lo dificil es que siga siendo un registro
clinico, y ahi estan las trampas:

  1. SI LA FECHA EDITABLE FUERA LA DE CAPTURA, corregir una nota
     borraria la unica prueba de cuando se escribio de verdad. En un
     expediente, la diferencia entre "lo anoto ese dia" y "lo anoto tres
     meses despues" es la diferencia entre un registro y una
     reconstruccion. Son DOS fechas y una de las dos no se toca.

  2. UNA CORRECCION EN SILENCIO es peor que un dedazo: el expediente
     diria algo distinto de lo que se firmo y nada lo indicaria.

  3. EL CAMPO datetime-local no habla el formato de MySQL. Asignandole
     "2026-10-17 14:30:00" el navegador lo rechaza sin decir nada y el
     campo queda VACIO: corregir el texto de una nota le cambiaria la
     fecha sin que nadie lo pida.

  4. LA HORA LOCAL. toISOString() convierte a UTC, y en Nayarit son seis
     horas menos: una nota escrita a las 9 de la maniana se fecharia a
     las 3 de la madrugada.

No hay PHP ni MySQL en esta maquina: las reglas de la fecha se portan y
se corren con fechas de verdad.
"""
import datetime
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


def leer(rel):
    return (RAIZ / rel).read_text(encoding='utf-8')


def sin_comentarios(texto):
    texto = re.sub(r'/\*[\s\S]*?\*/', '', texto)
    texto = re.sub(r'(?m)^\s*(//|#|--)\s.*$', '', texto)
    texto = re.sub(r'(?m)\s+(//|#)\s.*$', '', texto)
    return texto


# =====================================================================
#  1. La regla de la fecha, portada del endpoint
# =====================================================================
print('La fecha que llega del formulario')

AHORA = datetime.datetime(2026, 10, 17, 14, 30, 0)


def fecha_de_la_nota(fecha, es_correccion, ahora=AHORA):
    """
    Puerto de la parte de nota_guardar.php que decide fecha_nota.

    Devuelve la fecha, o ('error', campo) cuando hay que rechazar.
    """
    if fecha == '':
        # Vacia al corregir es un borrado: la pantalla la manda siempre
        # con la que tenia. Poner la de hoy en silencio le cambiaria el
        # dia a la nota por un descuido.
        if es_correccion:
            return ('error', 'fecha')
        return None          # al alta, NOW() en el INSERT

    try:
        puesta = datetime.datetime.fromisoformat(fecha.replace('T', ' '))
    except ValueError:
        return ('error', 'fecha')

    # Una nota del futuro deja el expediente diciendo que algo se
    # observo un dia que todavia no llego. Dos dias de margen por si la
    # hora del servidor va atrasada.
    if puesta > ahora + datetime.timedelta(days=2):
        return ('error', 'fecha')

    # Solo el dia: se le pone la hora actual. A las 00:00 la nota se
    # ordenaria antes que todo lo de ese dia, y no es que se haya
    # escrito a medianoche.
    if len(fecha) <= 10:
        return puesta.replace(hour=ahora.hour, minute=ahora.minute,
                              second=ahora.second)
    return puesta


# El puerto de arriba esta escrito a mano, asi que cambiar el PHP no lo
# mueve: lo que prueba es la REGLA. Esto comprueba que el PHP la tenga,
# pedazo por pedazo. Sin esta parte, quitarle al endpoint la guardia de
# las fechas del futuro pasaba la prueba en verde.
_endpoint = sin_comentarios(leer('includes/consultas/nota_guardar.php'))

for fragmento, porque in [
    ("strtotime('+2 days')", 'la guardia de las fechas del futuro'),
    ('strlen($fecha) <= 10', 'la hora para las fechas que vienen sin ella'),
    ('} elseif ($idNota) {', 'el rechazo de la fecha vacia al corregir'),
    ("date('H:i:s')", 'la hora actual para una fecha de solo dia'),
]:
    if fragmento not in _endpoint:
        FALLOS.append(f'el endpoint perdio {porque}')
comprobar('el endpoint tiene las cuatro reglas de la fecha',
          [f for f in FALLOS if 'el endpoint perdio' in f], [])

comprobar('al alta sin fecha, la pone el servidor',
          fecha_de_la_nota('', False), None)
comprobar('al corregir sin fecha, se reclama',
          fecha_de_la_nota('', True), ('error', 'fecha'))

comprobar('una fecha con hora se respeta',
          fecha_de_la_nota('2026-10-10T09:15', False),
          datetime.datetime(2026, 10, 10, 9, 15))

# La trampa de las 00:00.
comprobar('solo el dia toma la hora actual',
          fecha_de_la_nota('2026-10-10', False),
          datetime.datetime(2026, 10, 10, 14, 30, 0))

comprobar('una fecha del futuro se rechaza',
          fecha_de_la_nota('2026-12-01T10:00', False), ('error', 'fecha'))
comprobar('dos dias de margen si pasan',
          fecha_de_la_nota('2026-10-18T10:00', False),
          datetime.datetime(2026, 10, 18, 10, 0))
comprobar('una fecha que no es fecha se rechaza',
          fecha_de_la_nota('el martes', False), ('error', 'fecha'))

# Una nota de hace anios SI se acepta: pasar al sistema un expediente
# viejo es exactamente lo que el consultorio esta haciendo.
comprobar('una nota de hace anios se acepta',
          fecha_de_la_nota('2019-03-04T11:00', False),
          datetime.datetime(2019, 3, 4, 11, 0))


# =====================================================================
#  2. La fecha de captura NO se toca
# =====================================================================
print()
print('El rastro de cuando se escribio')

endpoint = sin_comentarios(leer('includes/consultas/nota_guardar.php'))

correccion = endpoint[endpoint.index('UPDATE nota_evolucion'):]
correccion = correccion[:correccion.index(';')]

comprobar('al corregir se escribe la fecha de la nota',
          'fecha_nota = :f' in correccion, True)
# Esto es lo que hace que siga siendo un registro: la fecha de captura
# no entra en el UPDATE.
comprobar('y NO se toca la fecha de captura',
          'fecha_registro' in correccion, False)
comprobar('se anota cuando se corrigio',
          'fecha_modificacion = NOW()' in correccion, True)
comprobar('y quien lo hizo',
          'id_usuario_modifica = :u' in correccion, True)

# Al alta, la fecha de captura la pone la base por omision, y la de la
# nota cae en hoy si no vino ninguna.
alta = endpoint[endpoint.index('INSERT INTO nota_evolucion'):]
alta = alta[:alta.index(';')]
comprobar('al alta, sin fecha se usa la de hoy',
          'COALESCE(:f, NOW())' in alta, True)

# Una consulta CERRADA no impide corregir su nota: las notas son
# justamente el camino para agregar algo a una consulta cerrada, y si
# cerrarla congelara sus notas, el dedazo seria incorregible a partir
# del dia siguiente —que es cuando se nota—.
comprobar('corregir no mira si la consulta esta cerrada',
          'cerrada' in correccion, False)


# =====================================================================
#  3. Las dos fechas en la base
# =====================================================================
print()
print('La base')

esquema = leer('db/01_schema.sql')
tabla = esquema[esquema.index('CREATE TABLE nota_evolucion ('):]
tabla = tabla[:tabla.index('ENGINE=InnoDB;')]

for col in ('fecha_nota', 'fecha_registro', 'fecha_modificacion',
            'id_usuario_modifica'):
    if col not in tabla:
        FALLOS.append(f'la tabla no tiene {col}')
comprobar('la tabla tiene las cuatro columnas',
          [f for f in FALLOS if 'la tabla no tiene' in f], [])

comprobar('hay migracion para las bases que ya existen',
          (RAIZ / 'db/migraciones/0018_nota_editable.sql').exists(), True)

migracion = leer('db/migraciones/0018_nota_editable.sql')
comprobar('la migracion es repetible',
          'information_schema.columns' in migracion
          and 'PREPARE' in migracion, True)
# Las notas que ya existen tienen que quedar con fecha: sin el relleno
# saldrian sin fecha en pantalla hasta que alguien las corrigiera una
# por una.
comprobar('rellena las notas que ya existian',
          'SET fecha_nota = fecha_registro' in migracion, True)
# Cada columna con su propia comprobacion: juntas en un ALTER, si la
# migracion se corta entre las dos, la comprobacion ve la primera
# puesta y se salta las dos.
comprobar('cada columna lleva su propia comprobacion',
          migracion.count('information_schema.columns'), 3)


# =====================================================================
#  4. Lo que se lee de la base
# =====================================================================
print()
print('Las consultas que leen notas')

for rel in ('includes/consultas/consulta_obtener.php',
            'includes/consultas/plan_panel.php'):
    sql = sin_comentarios(leer(rel))
    nombre = pathlib.Path(rel).name

    # Las notas de antes de la migracion no tienen fecha_nota: sin el
    # COALESCE saldrian en blanco.
    # Con el alias: 'COALESCE(ne.fecha_nota, ...)' aparece tambien en el
    # ORDER BY, asi que buscandolo suelto la comprobacion pasaba aunque
    # la columna hubiera salido del SELECT —y entonces la pantalla no
    # tiene fecha que ensenar—.
    if 'COALESCE(ne.fecha_nota, ne.fecha_registro) AS fecha_nota' not in sql:
        FALLOS.append(f'{nombre}: no cae de regreso a la fecha de captura')
    if 'ne.fecha_modificacion' not in sql:
        FALLOS.append(f'{nombre}: no trae si la nota se corrigio')
    # Y se ordenan por la fecha de la NOTA: por la de captura, una nota
    # vieja escrita hoy saldria arriba de todo.
    if 'ORDER BY COALESCE(ne.fecha_nota' not in sql:
        FALLOS.append(f'{nombre}: ordena por la fecha de captura')

comprobar('las dos traen la fecha de la nota y la ordenan por ella',
          [f for f in FALLOS if ': no cae' in f or ': no trae' in f
           or ': ordena' in f], [])


# =====================================================================
#  5. La pantalla
# =====================================================================
print()
print('La pantalla')

modales = leer('vistas/consultas/_modales_consulta.php')
comprobar('el modal tiene el campo de fecha',
          'id="notaFecha"' in modales, True)
comprobar('y es de fecha con hora',
          'type="datetime-local"' in modales, True)
comprobar('y el id de la nota, que distingue alta de correccion',
          'id="notaId"' in modales, True)
# El texto que decia que no se puede editar tenia que irse: ahora se
# puede, y un aviso que miente ensenia a ignorar los avisos.
comprobar('ya no dice que no se puede editar',
          'No se puede' in modales and 'editar despu' in modales, False)

js = sin_comentarios(leer('assets/js/consultas.js'))

# La trampa del formato: datetime-local quiere "2026-10-17T14:30".
# Asignandole lo que manda MySQL, el navegador lo rechaza en silencio y
# el campo queda vacio.
# Dentro de abrirNota, no en todo el archivo: ese mismo .replace(' ',
# 'T') se usa tambien para calcular la duracion de una cita, asi que
# buscandolo suelto la comprobacion pasaba aunque abrirNota hubiera
# dejado de traducir la fecha.
abrir = js[js.index('function abrirNota'):]
abrir = abrir[:abrir.index('\n  }')]
comprobar('la fecha se traduce al formato del campo',
          ".replace(' ', 'T').substring(0, 16)" in abrir, True)

# Y la trampa de la zona: toISOString() pasa a UTC.
ahora = js[js.index('function ahoraParaCampo'):]
ahora = ahora[:ahora.index('\n  }')]
comprobar('la fecha de ahora se arma en hora local',
          'getFullYear' in ahora and 'toISOString' not in ahora, True)

comprobar('las notas se pueden picar para corregirlas',
          'data-editar-nota' in js, True)
comprobar('el modal sirve para las dos cosas',
          'function abrirNota' in js, True)

# La marca de corregida: en la consulta y en el panel del plan.
comprobar('se marca la nota corregida',
          js.count('marca-corregida') >= 2, True)
css = leer('assets/css/estilo.css')
comprobar('y tiene estilo',
          re.search(r'\.marca-corregida\s*\{', css) is not None, True)

# El aviso lo manda el servidor: "agregada" y "corregida" no son lo
# mismo, y antes habia uno fijo que decia siempre "Nota agregada".
comprobar('el aviso al guardar lo dice el servidor',
          "r.mensaje ?? 'Nota guardada'" in js, True)
comprobar('el servidor distingue las dos',
          "'Nota corregida.'" in endpoint and "'Nota agregada.'" in endpoint,
          True)


print()
if FALLOS:
    print(f'  *** {len(FALLOS)} fallo(s):')
    for f in FALLOS:
        print(f'  !!   {f}')
    sys.exit(1)
print('  Todo en orden: la nota se corrige y se sabe que se corrigio.')
