#!/usr/bin/env python3
"""
Citas apartadas para el dia, sin hora todavia.

Por que existe este arnes
-------------------------
Angel lo pidio asi: "pacientes que se citan con mucho tiempo de
anticipacion, permitir agregar la cita solo para el dia sin definir la
hora. Ya que se vaya llegando la fecha, la doctora se encargara de
agendar a la hora que le toca al paciente".

La forma de guardarlo es poner las dos fechas a las 00:00 de ese dia, y
ahi esta el peligro de toda la funcion: para la base de datos las 00:00
son una hora perfectamente valida. Nada revienta. Lo que pasa es peor
que un error:

  1. La comprobacion de empalmes podria encontrar choques entre dos
     citas apartadas para el mismo dia —las dos a las 00:00— y no
     dejar apartar la segunda.
  2. El recordatorio de WhatsApp le diria al paciente que lo esperan "a
     las 00:00", o calcularia las horas que faltan contra medianoche y
     saldria medio dia antes.
  3. Las cuatro pantallas que ensenian la hora escribirian "00:00", que
     no se lee como un dato que falta: se lee como un horario.

Ninguna de las tres se ve mirando el codigo de una funcion sola, y las
tres son lo que un paciente nota. Aqui se portan las reglas de PHP y se
corren con datos, y despues se revisa que las pantallas no se hayan
quedado atras.

No hay PHP ni MySQL en esta maquina: es la unica forma de saber que las
cuentas salen y no solo que el codigo esta escrito.
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


def leer(relativo):
    return (RAIZ / relativo).read_text(encoding='utf-8')


def sin_comentarios(texto):
    """
    El codigo sin sus comentarios.

    Esta es la trampa en la que ya cai cinco veces en este proyecto:
    buscar una palabra y encontrarla en el comentario que explica por que
    esta ahi. El comentario sobrevive a que alguien borre el mecanismo, y
    entonces la comprobacion pasa mientras la funcion ya no existe.
    """
    texto = re.sub(r'/\*[\s\S]*?\*/', '', texto)
    texto = re.sub(r'(?m)^\s*(//|#|--)\s.*$', '', texto)
    texto = re.sub(r'(?m)\s+(//|#)\s.*$', '', texto)
    return texto


# =====================================================================
#  1. Como se guarda: puerto de cita_guardar.php
# =====================================================================
BLOQUE = 30


def guardar_fechas(fecha, hora, duracion_min, sin_hora):
    """
    Puerto de la parte de cita_guardar.php que decide inicio y fin.

    Devuelve (inicio, fin, valida_empalmes).
    """
    if sin_hora:
        inicio = f'{fecha} 00:00:00'
        return inicio, inicio, False

    h, m = (int(x) for x in hora.split(':'))
    total = h * 60 + m
    fin = total + duracion_min
    return (f'{fecha} {h:02d}:{m:02d}:00',
            f'{fecha} {fin // 60:02d}:{fin % 60:02d}:00',
            True)


def errores_de_validacion(fecha, hora, duracion_min, sin_hora,
                          expediente=13, dentista=3):
    """Puerto de la lista de errores de cita_guardar.php."""
    errores = {}
    if not expediente:
        errores['id_registro_paciente'] = 'Selecciona el paciente.'
    if not dentista:
        errores['id_usuario_dentista'] = 'Selecciona el dentista.'
    if fecha == '':
        errores['fecha'] = 'Selecciona la fecha.'

    if not sin_hora and hora == '':
        errores['hora'] = 'Selecciona la hora.'
    if not sin_hora and not (15 <= duracion_min <= 480):
        errores['duracion_min'] = 'La duracion debe estar entre 15 y 480.'
    return errores


i, f, valida = guardar_fechas('2026-11-27', '', 30, True)
comprobar('sin hora: las dos fechas a las 00:00 del dia pedido',
          (i, f), ('2026-11-27 00:00:00', '2026-11-27 00:00:00'))
comprobar('sin hora: no se comprueban empalmes', valida, False)

i, f, valida = guardar_fechas('2026-11-27', '10:30', 60, False)
comprobar('con hora: inicio y fin se separan la duracion',
          (i, f), ('2026-11-27 10:30:00', '2026-11-27 11:30:00'))
comprobar('con hora: si se comprueban empalmes', valida, True)

comprobar('sin hora: no se exige la hora',
          errores_de_validacion('2026-11-27', '', 30, True), {})
comprobar('sin hora: la fecha SI se sigue exigiendo',
          list(errores_de_validacion('', '', 30, True)), ['fecha'])
comprobar('con hora: sin hora elegida se reclama',
          list(errores_de_validacion('2026-11-27', '', 30, False)), ['hora'])

# La duracion solo se juzga cuando la cita lleva hora. Sin esto, una
# cita apartada para el dia —que no manda duracion— seria rechazada por
# "la duracion debe estar entre 15 y 480 minutos", un mensaje que no
# tiene ningun sentido para quien solo quiso apartar el dia.
comprobar('sin hora: una duracion de cero no estorba',
          errores_de_validacion('2026-11-27', '', 0, True), {})
comprobar('con hora: una duracion de cero si se reclama',
          list(errores_de_validacion('2026-11-27', '09:00', 0, False)),
          ['duracion_min'])


# =====================================================================
#  2. Empalmes: puerto de Agenda::validarEspacio()
# =====================================================================
def empalma(nueva, existentes):
    """
    Puerto de la consulta de validarEspacio(), con su "sin_hora = 0".

    nueva:      (inicio, fin)
    existentes: lista de (inicio, fin, sin_hora)
    """
    ini, fin = nueva
    for e_ini, e_fin, e_sin in existentes:
        if e_sin:
            continue            # AND c.sin_hora = 0
        if ini < e_fin and fin > e_ini:
            return True
    return False


UNA_CITA = [('2026-11-27 10:30:00', '2026-11-27 11:30:00', False)]
DOS_APARTADAS = [
    ('2026-11-27 00:00:00', '2026-11-27 00:00:00', True),
    ('2026-11-27 00:00:00', '2026-11-27 00:00:00', True),
]

comprobar('una cita normal choca con otra que la monta',
          empalma(('2026-11-27 11:00:00', '2026-11-27 12:00:00'), UNA_CITA),
          True)

# El caso que hubiera roto la funcion entera: dos pacientes citados para
# el mismo dia sin hora. Las dos a las 00:00; si contaran, la segunda no
# se podria apartar y la pantalla diria "ese horario ya esta ocupado"
# sobre un horario que nadie eligio.
comprobar('dos apartadas el mismo dia no chocan entre ellas',
          empalma(('2026-11-27 00:00:00', '2026-11-27 00:00:00'),
                  DOS_APARTADAS),
          False)

# Y al contrario: una cita de verdad a las nueve no puede verse
# bloqueada por los dias apartados.
comprobar('una cita con hora no la bloquea una apartada',
          empalma(('2026-11-27 09:00:00', '2026-11-27 09:30:00'),
                  DOS_APARTADAS),
          False)

# Lo de siempre sigue en pie: una apartada no exime de chocar cuando SI
# se le pone hora.
comprobar('al ponerle hora, vuelve a competir por el sillon',
          empalma(('2026-11-27 11:00:00', '2026-11-27 11:30:00'),
                  UNA_CITA + DOS_APARTADAS),
          True)


# =====================================================================
#  3. Lo que ve el paciente: puerto de horaCita() y cuandoCita()
# =====================================================================
def hora_cita(cita):
    return 'Sin hora' if int(cita.get('sin_hora', 0)) == 1 \
        else cita['fecha_inicio'][11:16]


def cuando_cita(cita):
    dia = cita['fecha_inicio'][:10]
    d, m, a = dia[8:10], dia[5:7], dia[:4]
    dia = f'{d}/{m}/{a}'
    return f'{dia} · sin hora' if int(cita.get('sin_hora', 0)) == 1 \
        else f'{dia} {cita["fecha_inicio"][11:16]}'


APARTADA = {'fecha_inicio': '2026-11-27 00:00:00', 'sin_hora': 1}
NORMAL = {'fecha_inicio': '2026-11-27 10:30:00', 'sin_hora': 0}

comprobar('la hora de una apartada no es "00:00"',
          hora_cita(APARTADA), 'Sin hora')
comprobar('la hora de una normal es su hora', hora_cita(NORMAL), '10:30')
comprobar('el "cuando" de una apartada conserva el dia',
          cuando_cita(APARTADA), '27/11/2026 · sin hora')
comprobar('el "cuando" de una normal lleva las dos cosas',
          cuando_cita(NORMAL), '27/11/2026 10:30')

# PDO devuelve los enteros como CADENA. Si la funcion comparara con ===
# contra el numero 1, o si confiara en que el valor es un int, el "1"
# que llega de la base no casaria y la pantalla volveria a escribir las
# 00:00. Esto ya paso en este proyecto con el id_cita de la vista de
# semana.
comprobar('el "1" que manda PDO como cadena tambien cuenta',
          hora_cita({'fecha_inicio': '2026-11-27 00:00:00',
                     'sin_hora': '1'}),
          'Sin hora')

# Y una cita vieja, de antes de la migracion, no trae la columna en el
# arreglo: no debe reventar ni inventarse que no tiene hora.
comprobar('una cita sin la columna se trata como cita con hora',
          hora_cita({'fecha_inicio': '2026-11-27 10:30:00'}), '10:30')


# =====================================================================
#  4. Cuando sale el recordatorio de una cita sin hora
# =====================================================================
print()
print('El recordatorio de un dia apartado')

# La hora de apertura sale del PHP, no de una copia a mano: si alguien
# cambia el horario del consultorio, esta prueba tiene que medir con el
# nuevo o estaria comprobando un horario que ya no existe.
HORA_APERTURA = int(re.search(
    r'const HORA_INICIO = (\d+)',
    leer('includes/core/agenda.php')).group(1))


def momento_de_referencia(cita):
    """Puerto de AgendaExtra::momentoDeReferencia()."""
    import datetime
    inicio = datetime.datetime.strptime(cita['fecha_inicio'],
                                        '%Y-%m-%d %H:%M:%S')
    if int(cita.get('sin_hora', 0)) != 1:
        return inicio
    return inicio.replace(hour=HORA_APERTURA, minute=0, second=0)


def horas_faltan(cita, ahora):
    import datetime
    ahora = datetime.datetime.strptime(ahora, '%Y-%m-%d %H:%M:%S')
    return round((momento_de_referencia(cita) - ahora).total_seconds() / 3600, 1)


APARTADA_27 = {'fecha_inicio': '2026-11-27 00:00:00', 'sin_hora': 1}

# Esta es la cuenta que importa. La regla de 24 horas dispara cuando
# faltan 24 o menos; midiendo contra las 00:00 del dia 27, eso pasaria a
# las 00:00 del dia 26 —de madrugada, nueve horas antes de que el
# consultorio abra—, asi que el recordatorio aparece en el tablero un
# dia completo antes de lo que toca y recepcion lo manda cuando no es.
comprobar('la vispera a media maniana faltan 24 horas o menos',
          horas_faltan(APARTADA_27, '2026-11-26 09:00:00') <= 24, True)
comprobar('y la vispera a medianoche todavia no',
          horas_faltan(APARTADA_27, '2026-11-26 00:00:00') > 24, True)
comprobar('la referencia es la apertura, no la medianoche',
          horas_faltan(APARTADA_27, '2026-11-27 00:00:00'), 9.0)

# Una cita con hora no cambia en nada.
comprobar('una cita con hora se mide contra su hora',
          horas_faltan({'fecha_inicio': '2026-11-27 10:30:00', 'sin_hora': 0},
                       '2026-11-27 08:30:00'),
          2.0)


def plantilla_de(cita, con, sin):
    """Puerto de la eleccion de plantilla en mensaje()."""
    return sin if int(cita.get('sin_hora', 0)) == 1 else con


def hora_de(cita):
    """Puerto de la etiqueta {hora}."""
    return ('por confirmar' if int(cita.get('sin_hora', 0)) == 1
            else cita['fecha_inicio'][11:16])


comprobar('una cita sin hora usa la plantilla sin hora',
          plantilla_de(APARTADA_27, 'CON', 'SIN'), 'SIN')
comprobar('y su etiqueta de hora no dice una hora',
          hora_de(APARTADA_27), 'por confirmar')
comprobar('una cita con hora usa la de siempre',
          plantilla_de({'fecha_inicio': '2026-11-27 10:30:00', 'sin_hora': '0'},
                       'CON', 'SIN'),
          'CON')


# =====================================================================
#  5. Que el mecanismo siga en su sitio
# =====================================================================
# Lo de arriba son puertos: prueban la regla, no el archivo. Esto
# comprueba que el archivo de verdad la tiene, y sobre el codigo sin
# comentarios para que el parrafo que explica el mecanismo no pase por
# el mecanismo.

agenda = sin_comentarios(leer('includes/core/agenda.php'))
comprobar('validarEspacio deja fuera las citas sin hora',
          'c.sin_hora = 0' in agenda, True)

# ---------------------------------------------------------------------
#  Los recordatorios: ahora SI entran, con su propio mensaje
# ---------------------------------------------------------------------
# Al principio las deje fuera de los recordatorios, porque el mensaje de
# siempre dice "lo esperamos el jueves a las 10:30". La doctora lo pidio
# al reves el 17 de octubre de 2026, y tiene razon de consultorio: una
# cita a seis semanas es justo la que el paciente olvida.
#
# Lo que no puede pasar es que el mensaje prometa una hora, y eso se
# resuelve con una segunda plantilla y con la etiqueta {hora} diciendo
# "por confirmar".
extra = sin_comentarios(leer('includes/core/agenda_extra.php'))

comprobar('los recordatorios ya no excluyen las citas sin hora',
          'c.sin_hora = 0' in extra, False)
comprobar('pero traen la columna, para saber que mensaje armar',
          'c.sin_hora' in extra, True)
comprobar('hay una segunda plantilla, sin hora',
          'PLANTILLA_SIN_HORA_POR_OMISION' in extra, True)
# La plantilla de las citas sin hora no puede llevar {hora}: es el
# mensaje que se le manda al paciente de una cita que todavia no la
# tiene.
plantilla = extra[extra.index('PLANTILLA_SIN_HORA_POR_OMISION'):]
plantilla = plantilla[:plantilla.index(';')]
comprobar('y esa plantilla no escribe la hora', '{hora}' in plantilla, False)

# Y si alguien deja {hora} escrita a mano desde Configuracion, se
# sustituye por "por confirmar". Sin esto el mensaje diria "a las 12:00
# am": una cita a medianoche, mandada por WhatsApp.
comprobar('la etiqueta {hora} no se puede volver las 00:00',
          "'por confirmar'" in extra, True)

# Las horas que faltan se miden contra la APERTURA del consultorio y no
# contra las 00:00: con la medianoche, el aviso de 24 horas saldria
# nueve horas antes de lo que toca.
comprobar('hay un momento de referencia aparte',
          'function momentoDeReferencia' in extra, True)
referencia = extra[extra.index('function momentoDeReferencia'):]
referencia = referencia[:referencia.index('\n    }')]
comprobar('y para las sin hora usa la hora de apertura',
          'Agenda::HORA_INICIO' in referencia, True)
comprobar('pendientes() lo usa en vez de la fecha cruda',
          'self::momentoDeReferencia($c) - $ahora' in extra, True)

# Las dos plantillas se pueden cambiar desde Configuracion, y las dos
# se guardan de un viaje: si una falla no se guarda ninguna.
guardaMsj = sin_comentarios(
    leer('includes/configuracion/recordatorio_mensaje_guardar.php'))
comprobar('el guardado conoce las dos claves',
          "'recordatorio_mensaje'" in guardaMsj
          and "'recordatorio_mensaje_sin_hora'" in guardaMsj, True)
comprobar('y no guarda a medias si una falla',
          'if ($errores) {' in guardaMsj
          and guardaMsj.index('if ($errores) {')
              < guardaMsj.index('INSERT INTO parametro'), True)

# La pantalla de inicio no puede escribir las 00:00 al lado del boton
# que manda el WhatsApp.
comprobar('el recordatorio de inicio usa cuandoCita',
          'cuandoCita($r)' in leer('vistas/inicio.php'), True)

guardar = sin_comentarios(leer('includes/consultas/cita_guardar.php'))
comprobar('cita_guardar lee el campo sin_hora',
          "param('sin_hora')" in guardar, True)
comprobar('cita_guardar escribe la columna en el alta y en el cambio',
          guardar.count(':sh') >= 4, True)
# El salto de validarEspacio tiene que estar DENTRO del camino de la
# cita con hora. Que exista la llamada no basta: puesta fuera del else
# —antes del if, o despues de cerrarlo— una apartada pasaria por la
# comprobacion de empalmes igual que antes y no se podrian apartar dos
# dias iguales.
#
# Se mide por posicion y entre DOS limites. Comparando solo contra el
# "} else {" bastaba con que la llamada estuviera mas abajo en el
# archivo, y moverla al cuerpo de la transaccion —que empieza en
# "$pdo = bd()"— tambien cumple eso. Este fue un fallo real de este
# mismo verificador: el sabotaje de mover la llamada no lo detectaba.
desde = guardar.index('if ($sinHora) {')
# El "} else {" que interesa es el de ESTE if, no el primero del
# archivo: mas arriba hay otro, el del procedimiento previsto. Buscarlo
# desde el principio daba una posicion anterior al if y la comprobacion
# salia falsa con el codigo correcto.
otro = guardar.index('} else {', desde)
hasta = guardar.index('$pdo = bd();')

comprobar('validarEspacio se llama una sola vez',
          guardar.count('Agenda::validarEspacio'), 1)
comprobar('validarEspacio solo se llama en el camino de la cita con hora',
          desde < otro < guardar.index('validarEspacio') < hasta, True)

estatus = sin_comentarios(leer('includes/consultas/cita_estatus.php'))
comprobar('no se puede confirmar una cita sin hora',
          "=== ESTADO_CONFIRMADA" in estatus
          and "sin_hora'] === 1" in estatus, True)

semana = sin_comentarios(leer('includes/consultas/semana.php'))
comprobar('la semana saca las sin hora de la rejilla',
          'foreach ($conHora as $c)' in semana, True)
comprobar('y las devuelve aparte, una lista por dia',
          "'sin_hora'" in semana and "'sin_hora_total'" in semana, True)

funciones = sin_comentarios(leer('includes/core/funciones.php'))
comprobar('las dos funciones de pantalla existen',
          'function horaCita' in funciones
          and 'function cuandoCita' in funciones, True)

# Las dos tienen que CONVERTIR el valor antes de compararlo.
#
# PDO devuelve los enteros como cadena: lo que llega es "1", no 1. Con
# una comparacion estricta contra el numero el "1" no casa, horaCita se
# va por la otra rama y la pantalla escribe las 00:00 de todas las
# citas apartadas. El puerto de arriba prueba la regla; esto prueba que
# el PHP de verdad la tiene, que es otra cosa.
comprobar('horaCita y cuandoCita convierten el valor de PDO',
          funciones.count("(int) ($cita['sin_hora'] ?? 0) === 1"), 2)

# Las cuatro pantallas que ensenian la hora de una cita tienen que
# pasar por las funciones. Una que se quede con fecha() escribiria las
# 00:00, y es justo el renglon que nadie vuelve a mirar.
inicio = leer('vistas/inicio.php')
comprobar('inicio: la agenda del dia usa horaCita',
          'horaCita($c)' in inicio, True)
comprobar('inicio: las citas por confirmar usan horaCita',
          'horaCita($pc)' in inicio, True)
comprobar('inicio: ya no queda ningun fecha(..., \'H:i\') de cita',
          re.search(r"fecha\(\$(c|pc)\['fecha_inicio'\], 'H:i'\)", inicio),
          None)
comprobar('inicio: una cita sin hora ofrece ponerle hora, no confirmarla',
          "sin_hora'] ?? 0) === 1" in inicio and 'vista=agenda&amp;fecha=' in inicio
          or "sin_hora'] ?? 0) === 1" in inicio and 'vista=agenda&fecha=' in inicio,
          True)
comprobar('inicio: las sin hora salen primero en la agenda del dia',
          'c.sin_hora DESC' in inicio, True)

paciente = leer('vistas/paciente/detalle_paciente.php')
comprobar('ficha del paciente: las dos listas de citas usan cuandoCita',
          paciente.count('cuandoCita($ci)'), 2)
comprobar('ficha del paciente: la consulta trae la columna',
          'c.sin_hora' in paciente, True)

listar = sin_comentarios(leer('includes/consultas/citas_listar.php'))
comprobar('el listado del dia trae la columna y ordena por ella',
          'c.sin_hora' in listar and 'c.sin_hora DESC' in listar, True)

js = sin_comentarios(leer('assets/js/consultas.js'))
comprobar('la agenda del dia pinta el chip en vez de las 00:00',
          'chip-sin-hora' in js, True)
comprobar('la agenda del dia no ofrece Confirmar sin hora',
          "Number(c.id_estado_cita) === 1 && Number(c.sin_hora) !== 1" in js,
          True)
comprobar('el interruptor del modal existe y se escucha',
          "getElementById('citaSinHora')" in js
          and 'function aplicarSinHora' in js, True)
comprobar('el boton de guardar se habilita sin elegir hora',
          '(!sin && !document.getElementById(\'citaHora\').value)' in js,
          True)
comprobar('la semana pinta la banda de las sin hora',
          'renglon-sin-hora' in js and 'pildora-sin-hora' in js, True)
comprobar('buscarCita tambien mira la banda',
          'datos.sin_hora ?? []' in js, True)
# El arrastre tiene que mirar .cita-bloque y no cualquier [data-cita]:
# las pildoras de la banda tambien llevan data-cita, y arrastrar una
# intentaria "conservarle la duracion" a una cita que no tiene ninguna.
comprobar('las pildoras de la banda no se arrastran',
          ".cita-bloque[data-cita]'" in js, True)

comun = sin_comentarios(leer('assets/js/comun.js'))
comprobar('el detalle de la cita no escribe "00:00 a 00:00"',
          'todavía sin hora' in comun, True)
comprobar('el detalle quita el boton de Confirmar sin hora',
          '!(sinHora && id === 2)' in comun, True)

css = leer('assets/css/estilo.css')
for clase in ('.chip-sin-hora', 'tr.fila-sin-hora',
              '.renglon-sin-hora', '.pildora-sin-hora'):
    comprobar(f'el CSS tiene {clase}', clase in css, True)

migracion = leer('db/migraciones/0014_cita_sin_hora.sql')
comprobar('la migracion es repetible',
          'information_schema.columns' in migracion
          and 'PREPARE' in migracion, True)
comprobar('el esquema de cero tambien trae la columna',
          'sin_hora' in leer('db/01_schema.sql'), True)


# =====================================================================
print()
if FALLOS:
    print(f'  *** {len(FALLOS)} fallo(s):')
    for f in FALLOS:
        print(f'  !!   {f}')
    sys.exit(1)
print('  Todo en orden: el dia se aparta sin inventar una hora.')
