"""Puerto de Imagen::optimizar para probar la logica de decision."""
import io, sys
import numpy as np
from PIL import Image

PERFILES = {'RADIOGRAFIA': (2400, 90), '_': (1600, 82)}
IMAGENES = {'jpg','jpeg','png','webp','bmp','heic','heif'}
DE_IPHONE = {'heic','heif'}

# Si heif-convert esta o no. Se puede apagar para probar que pasa en un
# servidor que no reconstruyo la imagen de PHP.
HAY_CONVERSOR = True


def de_heic_a_jpeg(contenido: bytes):
    """Puerto de Imagen::deHeicAJpeg.

    Aqui no se convierte de verdad —no hay libheif en esta maquina— sino
    que se simula el resultado: lo que se prueba es el CAMINO, que es
    donde estaba el riesgo. Sin conversor devuelve None, y el archivo
    tiene que salir intacto en vez de perderse.
    """
    if not HAY_CONVERSOR:
        return None
    # Un HEIC de verdad lleva 'ftypheic' en la cabecera; el falso que
    # usa esta prueba trae un JPEG pegado detras para poder seguir.
    i = contenido.find(b'\xff\xd8\xff')
    return contenido[i:] if i >= 0 else None


def optimizar(contenido: bytes, ext: str, clave: str = '_'):
    ext = ext.lower()
    tal_cual = {'contenido': contenido, 'ext': ext, 'optimizada': False}
    if ext not in IMAGENES:
        return tal_cual

    if ext in DE_IPHONE:
        convertido = de_heic_a_jpeg(contenido)
        if convertido is None:
            return tal_cual
        contenido, ext = convertido, 'jpg'

    try:
        im = Image.open(io.BytesIO(contenido)); im.load()
    except Exception:
        return tal_cual

    lado, calidad = PERFILES.get(clave, PERFILES['_'])
    ancho, alto = im.size
    largo = max(ancho, alto)
    escala = lado / largo if largo > lado else 1.0
    nuevo = (max(1, round(ancho*escala)), max(1, round(alto*escala)))

    fondo = Image.new('RGB', nuevo, (255,255,255))
    chica = im.resize(nuevo, Image.LANCZOS)
    fondo.paste(chica, (0,0), chica if chica.mode in ('RGBA','LA') else None)
    buf = io.BytesIO(); fondo.save(buf, 'JPEG', quality=calidad)
    salida = buf.getvalue()

    if len(salida) >= len(contenido) and ext != 'bmp':
        return tal_cual
    return {'contenido': salida, 'ext': 'jpg', 'optimizada': True}

rng = np.random.default_rng(7)
def hacer(ancho, alto, formato):
    a = rng.integers(0, 256, (alto, ancho, 3), dtype=np.uint8)
    buf = io.BytesIO(); Image.fromarray(a).save(buf, formato); return buf.getvalue()

def kb(b): return f"{len(b)/1024:,.0f} KB"
fallos = []

if '--corto' not in sys.argv:
    print(f"{'caso':<42} {'entra':>12} {'sale':>12} {'ext':>5}  {'ahorro':>7}")
    print("-"*86)
    for nombre, datos, ext, clave in [
        ("Foto de celular 4032x3024 JPEG",       hacer(4032,3024,'JPEG'), 'jpg', 'FOTOGRAFIA'),
        ("Radiografia panoramica 2800x1400",     hacer(2800,1400,'JPEG'), 'jpg', 'RADIOGRAFIA'),
        ("Radiografia periapical BMP sin compr.",hacer(1200,1600,'BMP'),  'bmp', 'RADIOGRAFIA'),
        ("Captura chica 800x600 PNG",            hacer(800,600,'PNG'),    'png', 'FOTOGRAFIA'),
        ("Escaneo 1000x1400 ya chico JPEG",      hacer(1000,1400,'JPEG'), 'jpg', 'RADIOGRAFIA'),
    ]:
        r = optimizar(datos, ext, clave)
        print(f"{nombre:<42} {kb(datos):>12} {kb(r['contenido']):>12} {r['ext']:>5}"
              f"  {100*(1-len(r['contenido'])/len(datos)):>6.0f}%")
    print()

print("=== comprobaciones ===")

def escala_de(ancho, alto, clave):
    lado = PERFILES.get(clave, PERFILES['_'])[0]
    largo = max(ancho, alto)
    return lado / largo if largo > lado else 1.0

esc = escala_de(800, 600, 'FOTOGRAFIA')
print(f"  una imagen de 800x600 se escala x{esc}" + ("  ok" if esc == 1.0 else "  MAL"))
if esc != 1.0: fallos.append('agranda las imagenes chicas')

grande = hacer(3000,2000,'JPEG')
wrx = Image.open(io.BytesIO(optimizar(grande,'jpg','RADIOGRAFIA')['contenido'])).size[0]
wfo = Image.open(io.BytesIO(optimizar(grande,'jpg','FOTOGRAFIA')['contenido'])).size[0]
print(f"  3000px -> radiografia {wrx}px, fotografia {wfo}px"
      + ("  ok" if (wrx,wfo)==(2400,1600) else "  MAL"))
if (wrx,wfo)!=(2400,1600): fallos.append('los perfiles no se aplican')

bmp = hacer(8,8,'BMP'); r = optimizar(bmp,'bmp','RADIOGRAFIA')
print(f"  un BMP de 8x8 ({len(bmp)} B) sale .{r['ext']} ({len(r['contenido'])} B, mas pesado)"
      + ("  ok" if r['ext']=='jpg' else "  MAL"))
if r['ext']!='jpg': fallos.append('un BMP se quedo sin convertir')

pdf = b'%PDF-1.4 fake'; r = optimizar(pdf,'pdf','OTRO_CONSULTA')
print("  un PDF queda intacto" + ("  ok" if r['contenido']==pdf and r['ext']=='pdf' else "  MAL"))
if r['contenido']!=pdf: fallos.append('toco un PDF')

# Un PNG con una zona transparente: esa zona tiene que quedar clara,
# no negra. La imagen lleva ruido a proposito: un PNG de un solo color
# comprime tanto que el JPEG saldria mas pesado, el optimizador
# devolveria el original y la prueba no estaria probando nada.
a = rng.integers(0,256,(1200,1800,4),dtype=np.uint8)
a[:, :, 3] = 255
a[0:50, 0:50, 3] = 0        # esquina transparente
buf = io.BytesIO(); Image.fromarray(a, 'RGBA').save(buf,'PNG')
r = optimizar(buf.getvalue(), 'png', 'FOTOGRAFIA')
esquina = Image.open(io.BytesIO(r['contenido'])).convert('RGB').getpixel((5,5))
claro = min(esquina) > 200
print(f"  un PNG transparente queda sobre fondo {esquina}" + ("  ok" if claro else "  MAL"))
if not claro: fallos.append('la transparencia se rellena de negro')

roto = b'\xff\xd8 esto no es una imagen'; r = optimizar(roto,'jpg','FOTOGRAFIA')
print("  una imagen corrupta se conserva" + ("  ok" if r['contenido']==roto else "  MAL"))
if r['contenido']!=roto: fallos.append('perdio un archivo ilegible')

# ---------------------------------------------------------------------
#  Las fotos de iPhone (.heic)
# ---------------------------------------------------------------------
print()
print("Fotos de iPhone")

# Un HEIC de mentira: la cabecera que de verdad llevan, y detras un
# JPEG para que el puerto pueda seguir. Lo que se prueba es el camino.
def heic_falso(ancho, alto):
    buf = io.BytesIO()
    Image.fromarray(
        rng.integers(0, 256, (alto, ancho, 3), dtype=np.uint8)
    ).save(buf, 'JPEG', quality=95)
    return b'\x00\x00\x00\x18ftypheic\x00\x00\x00\x00heicmif1' + buf.getvalue()

foto = heic_falso(3024, 4032)      # lo que saca un iPhone
r = optimizar(foto, 'heic', 'FOTOGRAFIA')

print(f"  una foto .heic de 3024x4032 ({kb(foto)}) sale .{r['ext']} ({kb(r['contenido'])})"
      + ("  ok" if r['ext'] == 'jpg' and r['optimizada'] else "  MAL"))
if r['ext'] != 'jpg':
    fallos.append('una foto de iPhone no se convierte a jpg')

# Y se reduce como cualquier otra: el lado largo al tope del perfil.
if r['optimizada']:
    w, h = Image.open(io.BytesIO(r['contenido'])).size
    print(f"  y queda en {w}x{h}" + ("  ok" if max(w, h) == 1600 else "  MAL"))
    if max(w, h) != 1600:
        fallos.append('la foto de iPhone no se redimensiona al perfil')

# Una radiografia en HEIC usa el perfil de radiografia, no el general.
rx = heic_falso(3000, 4000)
r = optimizar(rx, 'heic', 'RADIOGRAFIA')
if r['optimizada']:
    w, h = Image.open(io.BytesIO(r['contenido'])).size
    print(f"  una radiografia .heic queda en {w}x{h}"
          + ("  ok" if max(w, h) == 2400 else "  MAL"))
    if max(w, h) != 2400:
        fallos.append('la radiografia heic no usa su perfil')

# Lo que mas importa: en un servidor SIN heif-convert, el archivo no se
# pierde. Sale intacto, como cualquier formato que no se pueda
# optimizar. Vale mas un expediente pesado que uno con huecos.
# Estamos al nivel del modulo: basta reasignar el nombre. 'global' aqui
# es un error de sintaxis, no una precaucion.
HAY_CONVERSOR = False
r = optimizar(foto, 'heic', 'FOTOGRAFIA')
print("  sin heif-convert, la foto se conserva intacta"
      + ("  ok" if r['contenido'] == foto and r['ext'] == 'heic' else "  MAL"))
if r['contenido'] != foto:
    fallos.append('sin conversor se pierde la foto de iPhone')
HAY_CONVERSOR = True

print()
print("*** TODO EN ORDEN ***" if not fallos else f"*** {len(fallos)} PROBLEMA(S): {fallos} ***")
