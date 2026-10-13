#!/usr/bin/env python3
"""
Las fotos de iPhone: cuando se guardan y cuando se rechazan.

Por que existe este arnes
-------------------------
Octubre de 2026. Se acepto el HEIC, la conversion fallaba, y el archivo
se guardaba tal cual. Durante dias el expediente fue recogiendo fotos de
hallazgos que no se ven en ningun lado: el album salia vacio, no habia
miniaturas, y al descargar una venia un .heic que el navegador no abre.
Nadie se habria enterado hasta necesitar esa radiografia.

La regla general del nucleo de imagenes es "vale mas un expediente
pesado que uno con huecos": si un archivo no se puede optimizar, se
guarda tal cual. Es buena regla y aqui estaba mal aplicada, porque para
el HEIC el resultado no es un expediente pesado: es un expediente que
PARECE completo y no lo es. Un hueco se ve; un .heic archivado no.

Asi que la regla se invierte SOLO para los formatos que hay que
convertir. Y eso es exactamente lo que se prueba aqui, en las dos
direcciones: que el HEIC que no se puede convertir se rechaza, y que un
PDF o un BMP que no se puede optimizar se sigue guardando. Equivocarse
en la segunda seria peor que el fallo original.

No hay PHP ni heif-convert en esta maquina: la decision se porta tal
cual y se corre con todos los casos.
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


IMAGENES = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'heic', 'heif']
DE_IPHONE = ['heic', 'heif']


class Rechazado(Exception):
    pass


def optimizar(ext, *, gd_abre=True, heif_convierte=True, hay_gd=True):
    """Puerto de la decision de Imagen::optimizar().

    Devuelve la extension con la que se GUARDA, o lanza Rechazado.
    """
    ext = ext.lower()

    if ext not in IMAGENES or not hay_gd:
        return ext                      # tal cual: un PDF, un Word

    if ext in DE_IPHONE:
        if not heif_convierte:
            # Aqui la regla se invierte: no se guarda.
            raise Rechazado(ext)
        ext = 'jpg'
        gd_abre = True                  # ya es un JPEG de verdad

    if not gd_abre:
        return ext                      # tal cual: imagen rara pero valida

    return 'jpg' if ext in ('bmp', 'webp', 'heic', 'heif') else ext


# =====================================================================
#  1. El HEIC que no se puede convertir NO se guarda
# =====================================================================
print('Un HEIC que no se puede convertir')

for ext in DE_IPHONE:
    try:
        optimizar(ext, heif_convierte=False)
        FALLOS.append(f'.{ext} sin conversion se guardo tal cual: el '
                      'expediente queda con una foto que nadie puede abrir')
    except Rechazado:
        print(f'  ok   .{ext} se rechaza en vez de guardarse sin convertir')

# Y el mensaje que llega a quien sube tiene que decir que hacer. Un
# "no se pudo" deja a la doctora sin salida con el paciente enfrente.
php = (RAIZ / 'includes/core/imagen.php').read_text(encoding='utf-8')
bloque = php[php.index('if (in_array($ext, self::DE_IPHONE, true))'):]
bloque = bloque[:bloque.index('$contenido = $convertido;')]

comprobar('se lanza una excepcion', 'throw new InvalidArgumentException' in bloque, True)

# Y que NO se devuelva el archivo. Buscar solo el throw no basta: con
# un "return $tal_cual" antes, el throw sigue escrito y nunca se
# alcanza. Lo probe rompiendolo asi a proposito y la comprobacion pasaba
# tan contenta; es la misma trampa de siempre, comprobar que el codigo
# esta en vez de que se ejecuta.
comprobar('y no se devuelve el archivo sin convertir',
          'tal_cual' in bloque, False)
comprobar('el mensaje dice que exportarla como JPEG',
          'JPEG' in bloque, True)
# Y, antes que nada, el ajuste del telefono: es lo unico que puede
# hacer la doctora, y arregla el problema de raiz. "Avisa para revisar
# el servidor" le sirve a quien mantiene el sistema, no a ella.
comprobar('y nombra el ajuste del iPhone que lo arregla de raiz',
          'Mas compatible' in bloque, True)
comprobar('y el motivo tecnico queda en el log',
          'error_log' in bloque, True)

# =====================================================================
#  2. Lo demas se sigue guardando tal cual
# =====================================================================
print()
print('Lo que NO hay que rechazar')

# Esta es la mitad que es facil romper al arreglar la otra. Si un PDF
# empezara a rechazarse por no ser optimizable, se perderia la mitad del
# expediente: consentimientos, credenciales, estudios externos.
for ext in ('pdf', 'doc', 'docx'):
    comprobar(f'un .{ext} se guarda tal cual', optimizar(ext), ext)

# Una imagen que GD no sabe abrir —corrupta, o un formato raro con
# extension valida— tambien se guarda: ahi si vale mas tenerla.
comprobar('un .png que GD no abre se guarda igual',
          optimizar('png', gd_abre=False), 'png')

# Sin GD en el servidor no se optimiza nada, y eso no puede impedir
# subir documentos.
comprobar('sin GD, un .jpg se guarda tal cual',
          optimizar('jpg', hay_gd=False), 'jpg')

# Pero un HEIC sin GD tampoco se puede convertir... y ahi SI se guardaria
# tal cual, porque ni siquiera se llega a intentar. Es un caso real y
# conviene saberlo: la comprobacion de arranque lo avisa aparte.
comprobar('sin GD, el .heic no pasa por la conversion',
          optimizar('heic', hay_gd=False), 'heic')

# =====================================================================
#  3. El camino bueno
# =====================================================================
print()
print('Cuando si se puede convertir')

for ext in DE_IPHONE:
    comprobar(f'.{ext} se guarda como .jpg', optimizar(ext), 'jpg')

comprobar('un .bmp tambien acaba en .jpg', optimizar('bmp'), 'jpg')
comprobar('un .jpg se queda .jpg', optimizar('jpg'), 'jpg')
comprobar('un .png se queda .png', optimizar('png'), 'png')

# =====================================================================
#  4. El motivo del fallo no se tira a la basura
# =====================================================================
print()
print('El motivo viaja')

# Este era el defecto de fondo. La version anterior abria los tubos de
# salida de heif-convert, los ponia en no bloqueante y los cerraba sin
# leerlos: cuando fallaba, lo UNICO que podia explicar por que —su
# propio mensaje— se perdia. Lo que quedaba era "no se pudo" y a
# adivinar entre tres causas con tres arreglos distintos.
conv = php[php.index('private static function deHeicAJpeg'):]
conv = conv[:conv.index('\n    }\n')]

comprobar('deHeicAJpeg devuelve el motivo', '&$porQue' in conv, True)

# ---- La trampa de proc_close ----
#
# Esta es la que de verdad rompio el HEIC, y no la encontre hasta que
# Angel me mando la foto de la pantalla.
#
# En cuanto proc_get_status() ve que el proceso termino, PHP lo recoge y
# se guarda su codigo de salida. proc_close() ya no tiene a quien
# preguntarle y devuelve -1 SIEMPRE que antes se haya llamado a
# proc_get_status... que es justo lo que hace el bucle de espera, porque
# es como sabe cuando parar.
#
# Resultado: heif-convert convertia la foto perfectamente, proc_close
# devolvia -1, y el sistema rechazaba la foto diciendo
#
#     "heif-convert salio con codigo -1 y sin decir nada"
#
# El "sin decir nada" era la pista: un programa que falla se queja. Lo
# lei como "fallo de forma misteriosa" y me fui a buscar paquetes que
# faltaban en el servidor.
comprobar('el codigo de salida se toma de proc_get_status',
          "$codigo = (int) $estado['exitcode']" in conv, True)
comprobar('y NO de proc_close, que devuelve -1 despues de proc_get_status',
          '$codigo = proc_close' in conv, False)

# El archivo producido se comprueba mirandolo, no fiandose del informe
# de otro programa sobre el. Es la leccion de lo anterior.
comprobar('se comprueba que el resultado sea una imagen de verdad',
          'getimagesizefromstring' in conv, True)
comprobar('y LEE lo que escribe el programa', 'fread(' in conv, True)
comprobar('incluido lo que quede al terminar',
          'stream_get_contents' in conv, True)
# ---- El 127, y el nombre que cambio ----
#
# libheif renombro su programa: heif-convert paso a llamarse heif-dec, y
# en las versiones nuevas de Debian ya solo viene el nombre nuevo. El
# paquete se instalaba, la imagen se construia sin un aviso, y el
# programa invocado no existia.
#
# El sintoma no fue "no encuentro heif-convert". Fue:
#
#     "heif-convert salio con codigo 127 y sin decir nada"
#
# Porque proc_open con un ARREGLO no pasa por una shell: PHP bifurca y
# el hijo intenta el exec; si el programa no esta, el padre recibe un
# proceso valido y un 127. Por eso la rama de "no esta instalado" que yo
# habia puesto —la del is_resource— era codigo muerto.
comprobar('se buscan los dos nombres del conversor',
          "'heif-dec'" in php and "'heif-convert'" in php, True)
comprobar('el 127 se traduce a "no hay conversor"',
          '$codigo === 127' in conv, True)
# Sobre el codigo SIN comentarios. El comentario que explica el arreglo
# tiene que nombrar la rama vieja para contar que estaba mal, y buscarla
# en el texto entero hace que la explicacion del arreglo se lea como si
# el arreglo no estuviera. Van ya varias veces con esta misma trampa.
convCodigo = re.sub(r'/\*.*?\*/', '', conv, flags=re.S)
convCodigo = re.sub(r'^\s*//.*$', '', convCodigo, flags=re.M)

comprobar('y no se confia en is_resource para saber si esta instalado',
          'no esta instalado' in convCodigo, False)
comprobar('y de "tardo demasiado"', 'tardo mas de 30' in conv, True)

# Un tope al texto: un programa en bucle podria escribir sin parar.
comprobar('el motivo tiene un tope de tamanio', '8192' in conv, True)

# =====================================================================
#  5. Hay forma de preguntar ANTES, y de reparar lo ya guardado
# =====================================================================
print()
print('Enterarse antes, y arreglar lo de atras')

comprobar('se puede preguntar si la conversion funciona',
          'function porQueNoConvierteHeic' in php, True)

# Y esa pregunta tiene que mirar si el ARCHIVO esta, no si proc_open
# "tuvo exito". Con la forma de arreglo, proc_open casi siempre devuelve
# un recurso aunque el programa no exista —el fallo ocurre en el hijo,
# despues—, asi que la comprobacion vieja decia "ok" en la instalacion
# de Angel, que no tenia conversor.
diag = php[php.index('function porQueNoConvierteHeic'):]
diag = diag[:diag.index('\n    }')]
comprobar('el diagnostico busca el programa, no arranca uno',
          'programaDeHeic()' in diag, True)
comprobar('y ya no se fia de que proc_open devuelva un recurso',
          'is_resource($p)' in diag, False)

# Y distingue "el programa esta" de "convierte".
#
# Son dos cosas distintas, y el diagnostico contestaba "convierte bien"
# habiendo comprobado solo lo primero. Afirmar mas de lo que se sabe es
# lo que me hizo perder tres intentos en este mismo fallo: cada
# veredicto demasiado seguro me mandaba a buscar la causa donde no
# estaba.
comprobar('el diagnostico dice CON QUE SEGURIDAD contesta',
          '$comoSeSupo' in diag, True)
comprobar('y hace la prueba completa fabricando un HEIC',
          'heicDePrueba()' in diag, True)
comprobar('que existe', 'function heicDePrueba' in php, True)
# Con el codificador del mismo paquete: nada de depender de que alguien
# deje una foto de muestra en el repositorio.
comprobar('usando heif-enc, que viene en el mismo paquete',
          'heif-enc' in php, True)
# Sin codificador NO se da por buena la instalacion a secas: se dice
# que solo se comprobo que el programa esta.
comprobar('sin codificador, el veredicto baja de tono',
          'solo se comprobo que el programa esta' in diag, True)

actualizar = (RAIZ / 'actualizar-local.command').read_text(encoding='utf-8')
comprobar('el guion de actualizacion lo pregunta',
          'porQueNoConvierteHeic' in actualizar, True)

reparar = RAIZ / 'tools/reparar_heic.php'
comprobar('existe el guion que repara las ya guardadas',
          reparar.is_file(), True)

if reparar.is_file():
    rep = reparar.read_text(encoding='utf-8')
    # Busca por la ruta Y por el mime: si alguno de los dos quedo mal,
    # el documento no se ve igual.
    comprobar('busca por extension y por mime',
              "LIKE '%.heic'" in rep and "'image/heic'" in rep, True)
    # Primero sube el nuevo, luego apunta la base, al final borra el
    # viejo. Al reves, un corte a media faena deja la base apuntando a
    # un archivo que ya no existe.
    comprobar('sube el nuevo antes de borrar el viejo',
              rep.index('Almacen::subir') < rep.index('Almacen::eliminar'), True)
    comprobar('y apunta la base antes de borrar',
              rep.index('UPDATE documento') < rep.index('Almacen::eliminar'), True)
    comprobar('tiene modo de solo mirar', '--ver' in rep, True)
    # Una foto que falle no puede detener el resto: un guion de
    # reparacion a medias es peor que no correrlo.
    comprobar('una foto que falla no detiene las demas',
              'continue' in rep, True)

# =====================================================================
#  6. Y la imagen de PHP trae con que convertir
# =====================================================================
print()
print('La imagen de PHP')

docker = (RAIZ / 'docker/php/Dockerfile').read_text(encoding='utf-8')

comprobar('instala el paquete del conversor', 'libheif-examples' in docker, True)
# El nombre viejo apuntando al nuevo: cualquier cosa que invoque
# heif-convert —un guion, una prueba, yo dentro de seis meses— lo
# encuentra.
comprobar('deja heif-convert apuntando a heif-dec',
          'ln -s heif-dec /usr/bin/heif-convert' in docker, True)
# Y si no quedo ninguno, la construccion FALLA. Antes solo avisaba, y
# el aviso se perdio entre doscientas lineas de apt.
comprobar('la construccion falla si no quedo ningun conversor',
          'no quedo instalado ningun conversor' in docker and 'exit 1' in docker,
          True)
# Lo que faltaba: el programa se instala pero en las versiones nuevas de
# Debian el decodificador de HEVC va en paquetes de complemento aparte,
# asi que heif-convert existia, corria, y fallaba en cada foto.
comprobar('y el complemento que decodifica',
          'libheif-plugin-libde265' in docker, True)
# Con "|| true": ese paquete no existe en las versiones donde el
# decodificador viene dentro de libheif1, y pedirlo abortaria la
# construccion entera.
comprobar('sin romper la construccion donde ese paquete no existe',
          '|| true' in docker, True)


print()
if FALLOS:
    print(f'*** {len(FALLOS)} PROBLEMA(S) ***')
    for f in FALLOS:
        print('  !!   ' + f)
    sys.exit(1)
print('*** TODO EN ORDEN ***')
