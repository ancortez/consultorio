#!/usr/bin/env python3
"""
El orden del buscador de pacientes.

Por que existe este arnes
-------------------------
Angel pidio tres cosas para Pacientes > Buscar paciente: dos columnas de
fecha —alta y ultima consulta—, poder ordenar por ellas y por el nombre,
y que el nombre se pueda ordenar por nombres o por apellidos.

Suena a un ORDER BY y tiene tres trampas, ninguna visible leyendo el
codigo de una funcion:

  1. El ORDER BY no admite marcadores de posicion. No hay forma de
     mandar la columna como dato, asi que la unica defensa contra una
     inyeccion es que lo que llega de fuera no se use NUNCA: lo que
     viaja es una clave y aqui se traduce. Una lista blanca que se
     saltara un caso seria un agujero del tamanio de la base de datos.

  2. En MySQL un NULL es mas chico que cualquier valor. ultima_consulta
     es NULL para quien nunca ha venido, asi que ordenando de la Z a la
     A esos pacientes se van a la CABEZA de la lista: el buscador
     empezaria con los que nunca han pisado el consultorio. Un paciente
     sin consultas no es "el mas reciente" ni "el mas antiguo": su sitio
     es abajo en los dos sentidos.

  3. El sentido por omision no es el mismo para todo. Una fecha se mira
     de lo mas nuevo a lo mas viejo y un nombre de la A a la Z.
     Conservando el sentido al cambiar de criterio, pasar de "ultima
     consulta" a "apellidos" dejaria la lista empezando por la Z.

No hay PHP ni MySQL en esta maquina: la regla se porta y se corre
ordenando pacientes de verdad.
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
    """El codigo sin sus comentarios.

    La trampa en la que ya cai cinco veces en este proyecto: buscar una
    palabra y encontrarla en el comentario que la explica. El comentario
    sobrevive a que alguien borre el mecanismo.
    """
    texto = re.sub(r'/\*[\s\S]*?\*/', '', texto)
    texto = re.sub(r'(?m)^\s*(//|#|--)\s.*$', '', texto)
    texto = re.sub(r'(?m)\s+(//|#)\s.*$', '', texto)
    return texto


# =====================================================================
#  La lista blanca sale del propio PHP
# =====================================================================
# Transcribirla a mano aqui no serviria: lo que hay que comprobar es que
# la del PHP no deje pasar nada mas, no que yo sepa escribirla.
php = leer('includes/paciente/listar.php')
codigo = sin_comentarios(php)

bloque = codigo[codigo.index('const ORDENES = ['):]
bloque = bloque[:bloque.index('];')]

ORDENES = dict(re.findall(r"'(\w+)'\s*=>\s*'([\w.]+)'", bloque))

comprobar('el PHP ofrece los cuatro criterios que se pidieron',
          sorted(ORDENES), ['alta', 'apellidos', 'nombres', 'ultima_consulta'])
comprobar('y los apellidos salen de su propia columna, no del nombre completo',
          ORDENES['apellidos'], 'vp.ap_paterno')
comprobar('los nombres tambien',  ORDENES['nombres'], 'vp.nombres')


# =====================================================================
#  1. Nada de fuera llega al ORDER BY
# =====================================================================
print()
print('Lo que llega de fuera no entra en la consulta')


def columna_de(orden):
    """Puerto de la traduccion clave -> columna."""
    return ORDENES.get(orden if orden in ORDENES else 'ultima_consulta')


# Lo que un atacante mandaria. Ninguno puede salir de la lista blanca.
ATAQUES = [
    'vp.nombres; DROP TABLE persona',
    '1 UNION SELECT contrasenia FROM usuario',
    "(SELECT 1 FROM usuario WHERE correo LIKE 'a%')",
    'vp.nombres--',
    '',
    'ALTA',               # la lista distingue mayusculas a proposito
    'fecha_alta',         # el nombre de la columna no es la clave
]
for malo in ATAQUES:
    if columna_de(malo) not in ORDENES.values():
        FALLOS.append(f'{malo!r} produjo una columna que no esta en la lista')

comprobar('ningun texto de fuera produce otra cosa que una de las cuatro',
          [f for f in FALLOS if 'produjo una columna' in f], [])

comprobar('lo que no esta en la lista cae en el orden de siempre',
          columna_de('vp.nombres; DROP TABLE persona'), 'vp.ultima_consulta')



# Y en el archivo: la columna se pega a la consulta —no hay otra forma—
# pero tiene que venir del arreglo, no del parametro.
armado = codigo[codigo.index('$columna = ORDENES['):]
armado = armado[:armado.index('$sql .= \' LIMIT')]
comprobar('la columna del ORDER BY sale del arreglo',
          '$columna = ORDENES[$orden]' in armado, True)
if re.search(r'ORDER BY[^"\']*\$orden', armado):
    FALLOS.append('el parametro crudo se pega al ORDER BY')
comprobar('el parametro crudo no aparece en el ORDER BY',
          [f for f in FALLOS if 'crudo se pega' in f], [])
# El sentido, igual: dos palabras posibles y ninguna viene de fuera.
comprobar('el sentido es ASC o DESC y nada mas',
          "$sentido = $baja ? 'DESC' : 'ASC'" in armado, True)

# =====================================================================
#  2. Ordenar de verdad, con pacientes de verdad
# =====================================================================
print()
print('La lista ordenada')

PACIENTES = [
    # nombres,  ap_paterno, fecha_alta,   ultima_consulta
    ('MATEO',   'GUZMAN',   '2019-03-04', '2026-10-02 10:30:00'),
    ('SOFIA',   'ALVAREZ',  '2026-09-28', None),              # nunca ha venido
    ('ANDRES',  'ZAMORA',   '2024-01-15', '2026-05-11 09:00:00'),
    ('ZOE',     'BRAVO',    '2026-10-01', None),              # nunca ha venido
    ('BRUNO',   'GUZMAN',   '2022-07-19', '2026-10-09 16:00:00'),
]
CAMPO = {'vp.nombres': 0, 'vp.ap_paterno': 1,
         'vp.fecha_alta': 2, 'vp.ultima_consulta': 3}


def ordenar(orden, baja):
    """
    Puerto del ORDER BY, con su "IS NULL" delante.

    El id como ultimo desempate: sin el, dos altas del mismo dia pueden
    salir en un orden distinto en cada consulta —MySQL no promete nada—
    y la lista "baila" al recargar sin que nada haya cambiado.
    """
    i = CAMPO[columna_de(orden)]

    def clave(fila):
        valor = fila[i]
        # Primero el IS NULL (0 o 1), que NO se invierte: por eso va en
        # su propia clave y con signo fijo.
        return (1 if valor is None else 0,)

    def clave2(fila):
        return fila[i] or ''

    filas = sorted(PACIENTES, key=clave2, reverse=baja)
    return [f[0] for f in sorted(filas, key=clave)]


comprobar('por ultima consulta, lo mas reciente primero',
          ordenar('ultima_consulta', True)[:3], ['BRUNO', 'MATEO', 'ANDRES'])

# La trampa numero 2. Invertido, los que nunca han venido se irian
# arriba si el "IS NULL" se invirtiera con lo demas.
comprobar('invertido, los que nunca han venido siguen al final',
          ordenar('ultima_consulta', False)[:3], ['ANDRES', 'MATEO', 'BRUNO'])

# Lo de arriba prueba la REGLA, con un puerto escrito aqui. Esto prueba
# que el SQL de verdad la tiene: sin el "IS NULL" delante, ordenando de
# la Z a la A los pacientes que nunca han venido se irian a la cabeza
# —en MySQL un NULL es mas chico que cualquier valor— y el buscador
# empezaria por los que no han pisado el consultorio.
#
# Y tiene que ir ANTES de la columna con su sentido, no despues: puesto
# al final no decide nada, porque el primer criterio ya ordeno todo.
orden_sql = armado[armado.index('ORDER BY'):]
orden_sql = orden_sql[:orden_sql.index('";')]
comprobar('el ORDER BY manda los vacios al final',
          '$columna IS NULL' in orden_sql, True)
comprobar('y lo hace antes de ordenar por la columna',
          orden_sql.index('IS NULL') < orden_sql.index('$columna $sentido'),
          True)
comprobar('y son esos dos los que quedan abajo',
          sorted(ordenar('ultima_consulta', False)[3:]), ['SOFIA', 'ZOE'])

comprobar('por fecha de alta, lo mas nuevo primero',
          ordenar('alta', True),
          ['ZOE', 'SOFIA', 'ANDRES', 'BRUNO', 'MATEO'])

comprobar('por apellidos, de la A a la Z',
          ordenar('apellidos', False),
          ['SOFIA', 'ZOE', 'MATEO', 'BRUNO', 'ANDRES'])

# La razon de que nombres y apellidos sean dos opciones: dan listas
# distintas. Con una sola opcion "nombre completo" —que empieza por los
# nombres— la mitad de las busquedas del consultorio no se podria hacer.
comprobar('por nombres da otra lista que por apellidos',
          ordenar('nombres', False) != ordenar('apellidos', False), True)
comprobar('por nombres, de la A a la Z',
          ordenar('nombres', False),
          ['ANDRES', 'BRUNO', 'MATEO', 'SOFIA', 'ZOE'])


# =====================================================================
#  3. El sentido por omision
# =====================================================================
print()
print('Como arranca cada criterio')


def baja_por_omision(orden):
    """Puerto de la regla, que vive en PHP y en el JavaScript."""
    return orden in ('ultima_consulta', 'alta')


comprobar('las fechas arrancan de lo mas nuevo a lo mas viejo',
          [baja_por_omision('ultima_consulta'), baja_por_omision('alta')],
          [True, True])
comprobar('los nombres arrancan de la A a la Z',
          [baja_por_omision('nombres'), baja_por_omision('apellidos')],
          [False, False])

# La regla esta escrita dos veces —PHP para lo que devuelve, JavaScript
# para la flecha del boton— y tienen que coincidir. Si no, el boton
# diria una cosa y la lista vendria de la otra.
js = sin_comentarios(leer('assets/js/paciente.js'))
comprobar('el PHP tiene la regla',
          "['ultima_consulta', 'alta']" in codigo, True)
comprobar('el JavaScript tiene la misma',
          "['ultima_consulta', 'alta']" in js, True)


# =====================================================================
#  4. Que la pantalla lo ensenie
# =====================================================================
print()
print('La pantalla')

vista = leer('vistas/paciente/buscar_paciente.php')

comprobar('estan los cuatro criterios en el combo',
          all(f'value="{c}"' in vista for c in ORDENES), True)
comprobar('y el boton del sentido',
          'id="filtroSentido"' in vista, True)
comprobar('la tabla tiene las dos columnas de fecha',
          '>Alta<' in vista and 'lt. consulta' in vista, True)

# El colspan de los renglones vacios tiene que crecer con las columnas.
# Si se queda corto, el "Cargando..." y el "no se encontraron pacientes"
# dejan de ocupar el ancho de la tabla y el mensaje sale encogido en la
# primera columna.
columnas = len(re.findall(r'<th[ >]', vista))
colspans = set(re.findall(r'colspan="(\d+)"', vista))
comprobar('la cabecera tiene siete columnas', columnas, 7)
comprobar('y los renglones vacios de la vista ocupan las siete',
          colspans, {'7'})
# Solo dentro de la funcion del buscador: paciente.js pinta mas tablas
# —la de domicilios, por ejemplo— y buscar 'colspan="5"' en todo el
# archivo encontraba la de ellas y se quejaba de un renglon correcto.
buscador = js[js.index("getElementById('tablaPacientes')"):]
buscador = buscador[:buscador.index('function altaPaciente')]
comprobar('el JavaScript del buscador usa siete',
          'const COLUMNAS = 7;' in buscador, True)
comprobar('y no le quedo ningun colspan a mano',
          re.search(r'colspan="\d+"', buscador), None)

# La vista trae la columna, o las celdas saldrian vacias siempre.
# En el SELECT, no en cualquier parte del archivo: 'vp.fecha_alta'
# aparece tambien en la lista blanca del orden, asi que buscarlo en todo
# el codigo pasaba aunque la columna no se trajera —y entonces la celda
# de la tabla sale vacia siempre—.
seleccion = codigo[codigo.index('$sql = \'SELECT'):codigo.index('FROM v_paciente')]
comprobar('el SELECT del endpoint pide fecha_alta',
          'vp.fecha_alta' in seleccion, True)
comprobar('la vista de la base la expone',
          'fecha_apertura AS fecha_alta' in leer('db/01_schema.sql'), True)
comprobar('y hay migracion para las bases que ya existen',
          (RAIZ / 'db/migraciones/0016_v_paciente_fecha_alta.sql').exists(),
          True)

# Las dos definiciones de la vista tienen que ser la MISMA.
#
# v_paciente esta escrita dos veces: en 01_schema.sql, que es la base
# recien creada, y en la migracion, que es la base que ya existia.
# Divergen en silencio: el que toque una y se olvide de la otra deja el
# consultorio de Angel con una vista y una instalacion nueva con otra,
# y el sintoma aparece meses despues en una sola de las dos.
#
# Se comparan las columnas del SELECT, no el texto: la indentacion y los
# comentarios pueden diferir sin que la vista cambie.
def columnas_de_la_vista(texto):
    cuerpo = texto[texto.index('CREATE OR REPLACE VIEW v_paciente'):]
    cuerpo = cuerpo[:cuerpo.index('FROM registro_paciente')]
    cuerpo = re.sub(r'--[^\n]*', '', cuerpo)
    # Lo que interesa es el alias de cada columna, que es el nombre con
    # el que la consulta la pide.
    return sorted(set(re.findall(r'AS (\w+)', cuerpo))
                  | set(re.findall(r'(?m)^\s*(?:rp|p|g|o)\.(\w+),', cuerpo)))


comprobar('la vista de la migracion y la del esquema tienen lo mismo',
          columnas_de_la_vista(leer('db/migraciones/0016_v_paciente_fecha_alta.sql')),
          columnas_de_la_vista(leer('db/01_schema.sql')))

# Es la APERTURA del expediente y no el instante en que se creo el
# renglon: un paciente de 2019 que se paso a Nanai la semana pasada
# saldria como el mas nuevo del consultorio.
comprobar('la fecha de alta no sale de fecha_registro',
          'fecha_registro AS fecha_alta'
          in leer('db/migraciones/0016_v_paciente_fecha_alta.sql'),
          False)


# =====================================================================
print()
if FALLOS:
    print(f'  *** {len(FALLOS)} fallo(s):')
    for f in FALLOS:
        print(f'  !!   {f}')
    sys.exit(1)
print('  Todo en orden: la lista se ordena por donde se pidio.')
