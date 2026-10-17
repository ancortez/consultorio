#!/usr/bin/env python3
"""
El codigo con el que se nombra un hallazgo: CLIN_01, RADIO_03.

Por que existe este arnes
-------------------------
La doctora lo pidio asi. El sentido es que la exploracion se dicta
—"clinico, foto; radiografico, foto"— y escribir una descripcion por
cada cosa con el paciente en el sillon no se hace: lo que se hacia era
dejarla en blanco, y el expediente acababa con una lista de hallazgos
sin nombre que nadie podia leer despues.

La regla parece una linea y tiene tres trampas:

  1. Esta escrita DOS veces: la pantalla propone el codigo al abrir el
     modal y el servidor lo pone cuando el campo llega vacio. Si las dos
     contaran distinto, lo que se ve al capturar no seria lo que queda
     guardado, y eso no se nota hasta que alguien compara el expediente
     con lo que recuerda haber escrito.

  2. Contar los hallazgos NO sirve. Con tres hallazgos, borrar el
     CLIN_02 deja dos, y el siguiente se llamaria CLIN_03 otra vez: dos
     hallazgos distintos del mismo dia con el mismo nombre, y el nombre
     es el que llevan sus radiografias en el expediente. Hay que tomar
     el maximo.

  3. El nombre viaja al expediente. Renumerar un hallazgo que ya existe
     deja el renglon del expediente diciendo una cosa y el hallazgo
     diciendo otra.

No hay PHP en esta maquina: la regla se porta desde el PHP, se corre con
datos, y se compara contra la copia del JavaScript.
"""
import json
import os
import pathlib
import re
import subprocess
import sys
import tempfile

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
    texto = re.sub(r'(?m)^\s*(//|#)\s.*$', '', texto)
    texto = re.sub(r'(?m)\s+(//|#)\s.*$', '', texto)
    return texto


# =====================================================================
#  La regla, portada del PHP
# =====================================================================
def prefijo_hallazgo(tipo):
    return 'RADIO' if str(tipo).strip().upper() == 'RADIOGRAFICO' else 'CLIN'


def codigo_hallazgo(prefijo, descripciones):
    maximo = 0
    for d in descripciones:
        m = re.match(rf'^{re.escape(prefijo)}_(\d+)', str(d or '').strip().upper())
        if m:
            maximo = max(maximo, int(m.group(1)))
    return f'{prefijo}_{maximo + 1:02d}'


# El prefijo del PHP se lee del archivo y se compara contra el puerto.
#
# Mi puerto esta escrito a mano, asi que cambiar el PHP no lo mueve: sin
# esto, dejar los dos tipos con el mismo prefijo pasaba la prueba. Lo
# que se compara es la decision del PHP, no mi copia de ella.
phpCrudo = sin_comentarios(leer('includes/core/funciones.php'))
reglaPhp = phpCrudo[phpCrudo.index('function prefijoHallazgo'):]
reglaPhp = reglaPhp[:reglaPhp.index('}')]

print('El prefijo sale del tipo')
comprobar('el PHP distingue el radiografico del clinico',
          "'RADIOGRAFICO' ? 'RADIO' : 'CLIN'" in reglaPhp, True)
comprobar('y los dos prefijos son distintos',
          len(set(re.findall(r"'(CLIN|RADIO)'", reglaPhp))), 2)
comprobar('clinico da CLIN', prefijo_hallazgo('CLINICO'), 'CLIN')
comprobar('radiografico da RADIO', prefijo_hallazgo('RADIOGRAFICO'), 'RADIO')
# Un tipo raro no puede dejar el hallazgo sin nombre: cae en el clinico,
# que es el que se usa noventa veces de cada cien.
comprobar('cualquier otra cosa cae en CLIN', prefijo_hallazgo('ALGO'), 'CLIN')
comprobar('y en minusculas tambien', prefijo_hallazgo('radiografico'), 'RADIO')


print()
print('El numero sale del maximo, no de contar')

comprobar('el primero de la consulta', codigo_hallazgo('CLIN', []), 'CLIN_01')
comprobar('con dos ya puestos, el tercero',
          codigo_hallazgo('CLIN', ['CLIN_01', 'CLIN_02']), 'CLIN_03')

# La trampa numero 2. Contando, esto daria CLIN_03 —que ya existe— y dos
# hallazgos distintos del mismo dia se llamarian igual.
comprobar('con un hueco en medio, NO se recicla el numero',
          codigo_hallazgo('CLIN', ['CLIN_01', 'CLIN_03']), 'CLIN_04')

# Las dos series son independientes: un hallazgo clinico y uno
# radiografico del mismo dia son CLIN_01 y RADIO_01.
mezcla = ['CLIN_01', 'RADIO_01', 'CLIN_02']
comprobar('el clinico cuenta solo los clinicos',
          codigo_hallazgo('CLIN', mezcla), 'CLIN_03')
comprobar('y el radiografico solo los radiograficos',
          codigo_hallazgo('RADIO', mezcla), 'RADIO_02')

# Un hallazgo DESCRITO a mano no corre la cuenta aunque mencione un
# codigo: el patron esta anclado al principio.
comprobar('una descripcion que menciona un codigo no cuenta',
          codigo_hallazgo('CLIN', ['DUELE AL MASTICAR, VER CLIN_09']),
          'CLIN_01')
comprobar('ni una descripcion normal',
          codigo_hallazgo('CLIN', ['CARIES OCLUSAL EN 46']), 'CLIN_01')

# Minusculas y espacios: lo que hay guardado de antes puede venir de
# cualquier forma.
comprobar('se reconoce en minusculas y con espacios',
          codigo_hallazgo('CLIN', ['  clin_07 ']), 'CLIN_08')

# Dos digitos, y mas alla del 99 los que hagan falta: truncar crearia
# el duplicado que todo esto viene a evitar.
comprobar('el 9 se escribe con dos digitos',
          codigo_hallazgo('CLIN', ['CLIN_08']), 'CLIN_09')
comprobar('y pasado el 99 no se trunca',
          codigo_hallazgo('CLIN', ['CLIN_99']), 'CLIN_100')


# =====================================================================
#  Las dos copias dan lo mismo
# =====================================================================
print()
print('El PHP y el JavaScript cuentan igual')

php = sin_comentarios(leer('includes/core/funciones.php'))
js  = sin_comentarios(leer('assets/js/consultas.js'))

comprobar('el PHP tiene las dos funciones',
          'function prefijoHallazgo' in php
          and 'function codigoHallazgo' in php, True)
comprobar('el JavaScript tambien',
          'function prefijoHallazgo' in js
          and 'function codigoHallazgo' in js, True)

# Las dos tienen que tomar el MAXIMO. Un count() o un .length en
# cualquiera de las dos es la trampa numero 2.
comprobar('el PHP toma el maximo', 'max($maximo' in php, True)
comprobar('el JavaScript toma el maximo', 'Math.max(maximo' in js, True)

# Y las dos tienen que anclar el patron al principio.
comprobar('el PHP ancla el patron', "'/^' . preg_quote" in php, True)
comprobar('el JavaScript tambien', '`^${prefijo}_(' in js, True)

# El JavaScript se corre DE VERDAD con el mismo lote de datos.
#
# Comparar los dos codigos leyendolos no sirve: son dos lenguajes y la
# diferencia que importa —un max contra un count, un ancla que falta— se
# ve igual en los dos. Lo que no miente es correrlos.
PRUEBAS = [
    ('CLIN',  []),
    ('CLIN',  ['CLIN_01', 'CLIN_02']),
    ('CLIN',  ['CLIN_01', 'CLIN_03']),
    ('CLIN',  ['CLIN_01', 'RADIO_01', 'CLIN_02']),
    ('RADIO', ['CLIN_01', 'RADIO_01', 'CLIN_02']),
    ('CLIN',  ['DUELE AL MASTICAR, VER CLIN_09']),
    ('CLIN',  ['  clin_07 ']),
    ('CLIN',  ['CLIN_99']),
    ('RADIO', []),
]

# Las dos funciones del JavaScript viven dentro de detalleConsulta() y
# usan la variable "datos" del modulo. Se extraen tal cual y se les
# pone un "datos" de mentira: asi se corre el codigo que se sirve, no
# una copia mia de el.
fuente = leer('assets/js/consultas.js')
trozo = fuente[fuente.index('  function codigoHallazgo(prefijo) {'):]
trozo = trozo[:trozo.index('\n  }') + 4]

# El guion se arma con una lista y se une con saltos de verdad. Escrito
# como una cadena con \n escapados ya me salio una vez con los saltos
# literales dentro, y node contesto un error de sintaxis en la linea 1
# que no decia nada de lo que pasaba.
guion = '\n'.join([
    'let datos = {};',
    trozo,
    'const casos = JSON.parse(process.argv[2]);',
    'const salida = casos.map(([prefijo, descripciones]) => {',
    '  datos = { hallazgos: descripciones.map((d) => ({ descripcion: d })) };',
    '  return codigoHallazgo(prefijo);',
    '});',
    'process.stdout.write(JSON.stringify(salida));',
])

with tempfile.TemporaryDirectory() as carpeta:
    archivo = os.path.join(carpeta, 'correr.js')
    with open(archivo, 'w', encoding='utf-8') as f:
        f.write(guion)

    corrida = subprocess.run(
        ['node', archivo, json.dumps(PRUEBAS)],
        capture_output=True, text=True)

if corrida.returncode != 0:
    FALLOS.append('el codigo del JavaScript no se pudo correr:\n      '
                  + (corrida.stderr.strip() or '(sin mensaje)'))
else:
    delJs  = json.loads(corrida.stdout)
    delPhp = [codigo_hallazgo(prefijo, desc) for prefijo, desc in PRUEBAS]

    for (prefijo, desc), php_, js_ in zip(PRUEBAS, delPhp, delJs):
        comprobar(f'{prefijo} con {desc!r}: las dos copias dan {php_}',
                  js_, php_)


# =====================================================================
#  Lo que pasa en la pantalla
# =====================================================================
print()
print('La pantalla')

vista = leer('vistas/consultas/_modales_consulta.php')

# El texto de que es opcional se fue de la DESCRIPCION: el campo llega
# con su codigo, no hay nada que dejar en blanco.
#
# Se mira solo ese bloque. En el de las imagenes el "(opcional)" sigue y
# sigue siendo cierto —un hallazgo puede no tener foto—, asi que
# buscarlo en todo el archivo se quejaba de una etiqueta correcta.
# Y sin los comentarios de PHP. El parrafo que explica el cambio dice
# literalmente 'Ya no dice "(opcional)"', asi que buscando sobre el
# archivo completo la comprobacion encontraba su propia explicacion y se
# quejaba. Es la sexta vez que caigo en esto en este proyecto.
vistaCodigo = re.sub(r'<\?php\s*/\*[\s\S]*?\*/\s*\?>', '', vista)

# El bloque acaba en la ETIQUETA del campo de archivos, no en el campo:
# el "(opcional)" de las imagenes vive en su label, que esta antes del
# input, asi que cortando en el input se colaba dentro y la
# comprobacion fallaba por una etiqueta de otro campo.
bloqueDesc = vistaCodigo[vistaCodigo.index('for="hzDescripcion"') - 200:
                         vistaCodigo.index('for="hzArchivos"')]
comprobar('la etiqueta de la descripcion ya no dice "(opcional)"',
          '(opcional)' in bloqueDesc, False)
comprobar('y la de las imagenes si lo sigue diciendo',
          '(opcional)' in vistaCodigo, True)

# El codigo NO se propone editando un hallazgo que ya existe: su nombre
# viajo al expediente como el nombre de sus imagenes, y renumerarlo
# dejaria el renglon del expediente diciendo otra cosa.
elegir = js[js.index('function elegirTipoHallazgo'):]
elegir = elegir[:elegir.index('\n  }')]
comprobar('editando un hallazgo no se renumera',
          "getElementById('hzId')?.value) return" in elegir, True)

# Y no pisa una descripcion escrita a mano.
comprobar('una descripcion escrita a mano no se pisa',
          'esCodigoDeHallazgo(texto)' in elegir, True)
comprobar('el reconocedor de codigos existe',
          'function esCodigoDeHallazgo' in js, True)

# El servidor lo pone tambien: si el campo llegara vacio por cualquier
# camino, el documento entraria al expediente sin nombre.
guarda = sin_comentarios(leer('includes/consultas/hallazgo_guardar.php'))
# La llamada tiene que estar DENTRO de la rama del campo vacio.
#
# Que exista no basta: con la condicion puesta a false la llamada sigue
# ahi, el campo vacio pasa de largo y el documento entra al expediente
# sin nombre. Se mide por posicion, entre la condicion y la validacion
# de las tres letras que viene despues.
comprobar('el servidor tiene la rama del campo vacio',
          "if ($descripcion === null || $descripcion === '') {" in guarda, True)

if "if ($descripcion === null || $descripcion === '') {" in guarda:
    desde = guarda.index("if ($descripcion === null || $descripcion === '') {")
    hasta = guarda.index('Escribe que observaste.')
    comprobar('y pone el codigo dentro de ella',
              desde < guarda.index('codigoHallazgo(') < hasta, True)
    comprobar('con el prefijo del tipo que se eligio',
              desde < guarda.index('prefijoHallazgo($tipo)') < hasta, True)
# Y ya no exige "texto o imagen": el hallazgo siempre tiene nombre, asi
# que la condicion no podria ser falsa nunca y solo confundiria.
comprobar('ya no exige texto o imagen',
          'sube al menos una imagen' in guarda, False)
# Lo que si sigue: tres letras minimas para lo escrito a mano.
comprobar('lo escrito a mano sigue pidiendo tres letras',
          'Escribe que observaste.' in guarda, True)


# =====================================================================
print()
if FALLOS:
    print(f'  *** {len(FALLOS)} fallo(s):')
    for f in FALLOS:
        print(f'  !!   {f}')
    sys.exit(1)
print('  Todo en orden: ningun hallazgo se queda sin nombre.')
