#!/usr/bin/env python3
"""
El odontograma del paciente: el dibujo y el estado de cada diente.

(El arnes hermano, probar_odontograma.js, prueba otra cosa: con que
denticion abre el selector segun la edad del paciente. Son dos ficheros
porque son dos preguntas, y la de alla ya existia.)

Por que existe este arnes
-------------------------
La doctora pidio tres cosas: una pestania con el odontograma
interactivo, poder marcar uno o varios dientes con su estado actual
—sano, amalgama, faltante— con codigo de colores, y que el selector de
organo dentario del plan ensenie ese mismo estado.

Lo que puede salir mal no es el dibujo, es lo de alrededor:

  1. DOS DENTADURAS. El dibujo estaba dentro de _modal_dientes.php. Si
     la pestania nueva lo copiara, serian dos dentaduras que se separan
     en cuanto alguien corrija una raiz en una sola. Tiene que haber un
     solo sitio que dibuje dientes.

  2. LOS 52 DIENTES. La notacion FDI no es correlativa: salta del 18 al
     21 y del 48 al 31, y la denticion temporal empieza en el 51. Una
     lista escrita a mano se come un diente y nadie lo nota hasta que
     un paciente tiene caries justo en ese.

  3. MARCAR NO ES QUITAR. "Sano" es una afirmacion de la doctora; quitar
     la marca deja el diente sin nada dicho. Si quitar guardara "sano",
     corregir un error de captura se volveria imposible.

  4. EL ESTADO Y LA SELECCION se pintan a la vez y no pueden taparse: en
     el selector del plan se elige donde va el procedimiento mirando
     como esta la boca. Si el relleno de la seleccion pisara el del
     estado, el diente elegido perderia su color clinico justo cuando
     hace falta.

No hay PHP ni MySQL en esta maquina: la lista de dientes y la forma de
cada uno se leen del PHP y se comprueban con la notacion FDI de verdad.
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


def leer(rel):
    return (RAIZ / rel).read_text(encoding='utf-8')


def sin_comentarios(texto):
    texto = re.sub(r'/\*[\s\S]*?\*/', '', texto)
    texto = re.sub(r'(?m)^\s*(//|#|--)\s.*$', '', texto)
    texto = re.sub(r'(?m)\s+(//|#)\s.*$', '', texto)
    return texto


nucleo = leer('includes/core/odontograma.php')
codigo = sin_comentarios(nucleo)


# =====================================================================
#  1. Los 52 dientes, leidos del PHP
# =====================================================================
print('La dentadura completa')


def arcada_del_php(nombre):
    bloque = codigo[codigo.index(f'public const {nombre} = ['):]
    bloque = bloque[:bloque.index('];')]
    # Cada corchete interior es un cuadrante.
    return [[int(n) for n in re.findall(r'\d+', fila)]
            for fila in re.findall(r'\[([\d,\s]+)\]', bloque)]


permanentes = arcada_del_php('PERMANENTES')
temporales  = arcada_del_php('TEMPORALES')

dientesPerm = [n for fila in permanentes for n in fila]
dientesTemp = [n for fila in temporales for n in fila]

# La notacion FDI: cuadrantes 1 a 4 para los permanentes, con ocho
# dientes cada uno, y 5 a 8 para los temporales, con cinco.
esperadosPerm = sorted(c * 10 + d for c in (1, 2, 3, 4) for d in range(1, 9))
esperadosTemp = sorted(c * 10 + d for c in (5, 6, 7, 8) for d in range(1, 6))

comprobar('estan los 32 permanentes, sin faltar ni repetir',
          sorted(dientesPerm), esperadosPerm)
comprobar('y los 20 temporales', sorted(dientesTemp), esperadosTemp)
comprobar('son 52 en total', len(dientesPerm) + len(dientesTemp), 52)

# El orden importa: los cuadrantes de la derecha del paciente van al
# reves porque el dentista los ve espejados. Un cuadrante ordenado al
# derecho dibujaria la boca invertida.
comprobar('el superior derecho va del 18 al 11',
          permanentes[0], [18, 17, 16, 15, 14, 13, 12, 11])
comprobar('y el superior izquierdo del 21 al 28',
          permanentes[1], [21, 22, 23, 24, 25, 26, 27, 28])
comprobar('el inferior derecho va del 48 al 41',
          permanentes[2], [48, 47, 46, 45, 44, 43, 42, 41])


# =====================================================================
#  2. La forma de cada diente
# =====================================================================
print()
print('La forma sale de la notacion')


def forma(n):
    """Puerto de Odontograma::forma()."""
    pos = n % 10
    temporal = n >= 51
    if pos <= 2:
        return 'incisivo'
    if pos == 3:
        return 'canino'
    if pos <= 5:
        return 'molar' if temporal else 'premolar'
    return 'molar'


# El puerto de arriba esta escrito a mano, asi que cambiar el PHP no lo
# mueve. Esto compara la DECISION del PHP, renglon por renglon, con la
# que prueba el puerto: sin esto, quitarle al PHP la rama de los
# temporales pasaba la prueba.
reglaForma = codigo[codigo.index('function forma(int $n)'):]
reglaForma = reglaForma[:reglaForma.index('\n    }')]

for fragmento, porque in [
    ("$pos <= 2", 'los incisivos'),
    ("$pos === 3", 'el canino'),
    ("$temporal ? 'molar' : 'premolar'", 'los temporales sin premolares'),
    ("$n >= 51", 'de donde empiezan los temporales'),
]:
    if fragmento not in reglaForma:
        FALLOS.append(f'la regla del PHP perdio {porque}')
comprobar('el PHP decide la forma con la misma regla que esta prueba',
          [f for f in FALLOS if 'la regla del PHP' in f], [])

comprobar('el 11 es un incisivo', forma(11), 'incisivo')
comprobar('el 13 es el canino', forma(13), 'canino')
comprobar('el 14 es un premolar', forma(14), 'premolar')
comprobar('el 16 es un molar', forma(16), 'molar')

# La trampa de la denticion de leche: no hay premolares, asi que el 54
# y el 55 ya son molares. Una regla copiada de los permanentes dibujaria
# premolares que no existen en una boca de nino, que es justo la boca
# que mas se ve en este consultorio.
comprobar('en los temporales el 54 ya es molar', forma(54), 'molar')
comprobar('y el 55 tambien', forma(55), 'molar')
comprobar('pero el 53 sigue siendo el canino', forma(53), 'canino')
comprobar('y el 51 un incisivo', forma(51), 'incisivo')

# Las cuatro formas tienen que estar dibujadas, o un diente saldria sin
# corona: PHP avisaria de un indice que no existe.
# Con espacios opcionales: las claves van alineadas a mano y
# "'canino'   =>" no es lo mismo que "'canino' =>" para un buscador
# literal.
for f in ('incisivo', 'canino', 'premolar', 'molar'):
    if not re.search(rf"'{f}'\s*=>", codigo):
        FALLOS.append(f'no hay corona dibujada para el {f}')
comprobar('las cuatro formas estan dibujadas',
          [f for f in FALLOS if 'no hay corona' in f], [])

# Y toda la dentadura tiene que caer en una de las cuatro.
formas = {forma(n) for n in dientesPerm + dientesTemp}
comprobar('ningun diente se queda sin forma',
          formas <= {'incisivo', 'canino', 'premolar', 'molar'}, True)


# =====================================================================
#  3. Un solo sitio dibuja dientes
# =====================================================================
print()
print('El dibujo, en un solo sitio')

modal = leer('vistas/consultas/_modal_dientes.php')
tab   = leer('vistas/paciente/_tab_odontograma.php')

comprobar('el selector del plan usa el nucleo',
          'Odontograma::arcada(' in modal, True)
comprobar('y la pestania del paciente tambien',
          'Odontograma::arcada(' in tab, True)

# Lo que NO puede volver: que cada vista dibuje su propio diente. Se
# busca el SVG, que es la huella de dibujar a mano.
for nombre, texto in (('el selector del plan', sin_comentarios(modal)),
                      ('la pestania del paciente', sin_comentarios(tab))):
    if 'viewBox="0 0 40 52"' in texto or 'dibujo-diente' in texto:
        FALLOS.append(f'{nombre} volvio a dibujar el diente por su cuenta')
comprobar('ninguna vista dibuja dientes por su cuenta',
          [f for f in FALLOS if 'por su cuenta' in f], [])


# =====================================================================
#  4. Marcar no es lo mismo que quitar
# =====================================================================
print()
print('Marcar y quitar')

# En el nucleo: con condicion null se BORRA, no se guarda nada.
marcar = codigo[codigo.index('function marcar('):]
marcar = marcar[:marcar.index('return count($dientes);')]

comprobar('se borra lo que haya antes de insertar',
          'DELETE FROM odontograma_detalle' in marcar, True)
comprobar('y solo se inserta si hay condicion',
          'if ($idCondicion !== null) {' in marcar, True)
# El orden: borrar y despues insertar. Al reves, el DELETE se llevaria
# lo que se acaba de poner.
comprobar('se borra ANTES de insertar',
          marcar.index('DELETE FROM') < marcar.index('INSERT INTO'), True)
# Y las dos cosas en una transaccion: a medias dejaria el diente sin
# estado y sin aviso.
comprobar('las dos cosas van en una transaccion',
          'beginTransaction' in marcar and 'rollBack' in marcar, True)

# Los numeros que llegan por POST se comprueban contra el catalogo: un
# id inventado reventaria la llave foranea con un error de servidor en
# vez de un aviso.
comprobar('los dientes se validan contra el catalogo',
          'array_intersect($dientes, $validos)' in marcar, True)

endpoint = sin_comentarios(leer('includes/paciente/odontograma_guardar.php'))
comprobar('el endpoint exige permiso de editar',
          "endpoint('paciente.editar')" in endpoint, True)
comprobar('el 0 significa quitar',
          '$idCondicion ?: null' in endpoint, True)
comprobar('y una condicion que no existe se avisa, no revienta',
          'Esa condicion no existe.' in endpoint, True)
comprobar('un expediente inventado tampoco revienta',
          'El paciente no existe.' in endpoint, True)

js = sin_comentarios(leer('assets/js/odontograma.js'))
comprobar('quitar la marca se confirma',
          'idCondicion === 0' in js and 'confirmar(' in js, True)
# Sin depender de las mayusculas ni del corte de linea: el texto del
# aviso se parte en varias cadenas y la primera letra va en mayuscula.
comprobar('y se dice que no es lo mismo que marcar sanos',
          re.search(r'no es lo mismo que marcarlos',
                    leer('assets/js/odontograma.js'),
                    re.IGNORECASE) is not None,
          True)

# Picar dos condiciones seguidas mandaria dos guardados sobre los
# mismos dientes y el segundo pisaria al primero sin que nadie sepa
# cual gano.
comprobar('los botones se apagan mientras se guarda',
          'b.disabled = true' in js, True)
comprobar('y se vuelven a prender pase lo que pase',
          '} finally {' in js, True)


# =====================================================================
#  5. El estado y la seleccion no se tapan
# =====================================================================
print()
print('El color del estado y el de la seleccion')

css = leer('assets/css/estilo.css')


def defineClase(texto, selector):
    return re.search(re.escape(selector) + r'\s*\{', texto) is not None


comprobar('el estado pinta el diente', defineClase(css, '.diente.con-estado .cr'),
          True)
comprobar('la seleccion sigue pintando', defineClase(css, '.diente.elegido .cr'),
          True)

# La combinacion de las dos es la que importa: elegido Y con estado
# tiene que conservar el color del estado, porque es el dato clinico, y
# marcar la seleccion de otra forma.
comprobar('elegido con estado tiene su propia regla',
          defineClase(css, '.diente.elegido.con-estado .cr'), True)

combinada = css[css.index('.diente.elegido.con-estado .cr'):]
combinada = combinada[:combinada.index('}')]
comprobar('y conserva el color del estado en el relleno',
          'fill: var(--estado' in combinada, True)

# El color viaja en una variable CSS y no en un fill en linea: escrito
# como style="fill:#..." pisaria tambien el color de la seleccion.
comprobar('el color viaja en una variable CSS',
          "'style=\"--estado:'" in codigo, True)
# Sobre el codigo SIN comentarios: el parrafo que explica por que no se
# hace dice literalmente 'style="fill:#..."', asi que buscandolo en el
# archivo completo la comprobacion encontraba su propia explicacion. Es
# la septima vez que caigo en esto en este proyecto.
if re.search(r"style=\"fill", codigo):
    FALLOS.append('el nucleo escribe el fill en linea: pisaria la seleccion')
comprobar('y no como un fill en linea',
          [f for f in FALLOS if 'fill en linea' in f], [])


# =====================================================================
#  6. La pestania y la base
# =====================================================================
print()
print('La pestania y la base')

detalle = leer('vistas/paciente/detalle_paciente.php')
comprobar('la pestania esta en la ficha del paciente',
          'data-bs-target="#tab-odontograma"' in detalle, True)
comprobar('y su contenido se incluye',
          "_tab_odontograma.php" in detalle, True)

indice = leer('index.php')
comprobar('el JavaScript se carga en la ficha',
          'odontograma.js' in indice, True)

esquema = leer('db/01_schema.sql')
comprobar('el esquema acota el odontograma actual a uno por paciente',
          'uq_odo_actual' in esquema, True)
comprobar('y hay migracion para las bases que ya existen',
          (RAIZ / 'db/migraciones/0017_odontograma_actual.sql').exists(), True)

migracion = leer('db/migraciones/0017_odontograma_actual.sql')
comprobar('la migracion es repetible',
          'information_schema.statistics' in migracion
          and 'PREPARE' in migracion, True)
# La llave va sobre una EXPRESION: sobre las dos columnas limitaria
# tambien los otros tipos a uno por paciente, y el dia que se guarde la
# historia por fechas habria que empezar quitandola.
comprobar('la llave acota solo el tipo actual',
          "IF(tipo = ''actual''" in migracion, True)

# La carrera entre el SELECT y el INSERT: dos pestanias marcando a la
# vez llegan las dos a crear el odontograma, y la segunda choca con la
# llave. Lo que hay que hacer es volver a consultar, no reventar.
crear = codigo[codigo.index('function idDelPaciente('):]
crear = crear[:crear.index('\n    }')]
comprobar('la carrera al crear el odontograma se resuelve',
          "getCode() !== '23000'" in crear, True)

print()
if FALLOS:
    print(f'  *** {len(FALLOS)} fallo(s):')
    for f in FALLOS:
        print(f'  !!   {f}')
    sys.exit(1)
print('  Todo en orden: una dentadura, 52 dientes y su estado.')
