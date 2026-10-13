"""
Balance de <div> en una vista, contando solo el HTML que llega al
navegador.

El PHP se quita primero. Sus comentarios y sus cadenas pueden traer
etiquetas sueltas —un '<div' dentro de un comentario que explica el
diseno, por ejemplo— y eso descuadra cualquier conteo hecho sobre el
archivo crudo.
"""
import re, sys, pathlib

import glob

objetivo = sys.argv[1:] or sorted(
    f for f in glob.glob('vistas/**/*.php', recursive=True)
    if '/storage/' not in f) + sorted(glob.glob('*.php'))

malos = 0
for ruta in objetivo:
    s = pathlib.Path(ruta).read_text(encoding='utf-8')
    html = re.sub(r'<\?(?:php|=).*?\?>', '', s, flags=re.S)

    pila = []
    linea = 1
    sobran = []
    for m in re.finditer(r'<div\b|</div>|\n', html):
        if m.group(0) == '\n':
            linea += 1
            continue
        if m.group(0) == '</div>':
            if pila:
                pila.pop()
            else:
                sobran.append(linea)
        else:
            pila.append(linea)

    abren = len(re.findall(r'<div\b', html))
    cierran = html.count('</div>')
    if pila or sobran:
        malos += 1
        print(f'  MAL {ruta}: {abren} abren, {cierran} cierran')
        for ln in pila:
            print(f'        sin cerrar, cerca del renglon {ln} del HTML')
        for ln in sobran:
            print(f'        sobra un cierre cerca del renglon {ln} del HTML')

print(f"  {'todo balanceado' if not malos else str(malos) + ' archivo(s) con problema'}"
      f'  ({len(objetivo)} revisados)')
sys.exit(1 if malos else 0)
