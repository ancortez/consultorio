"""
Puerto de limpiarTexto() a Python. Las clases \h y \v de PCRE no existen
igual en Python, asi que se traducen: \h -> [ \t], que es lo que PCRE
entiende por espacio horizontal en la practica.
"""
import re

def limpiar_texto(t):
    if t is None: return None
    t = t.replace('\r\n','\n').replace('\r','\n')
    t = re.sub(r'[ \t\f\v ]+', ' ', t)   # \h de PCRE
    t = re.sub(r' *\n *', '\n', t)
    t = re.sub(r'\n{3,}', '\n\n', t)
    t = t.strip()
    return t or None

def limpiar_espacios(t):          # la de antes, para comparar
    if t is None: return None
    t = re.sub(r'\s+', ' ', t.strip())
    return t or None

receta = ("AMOXICILINA 500 MG\n"
          "1 CADA 8 HORAS POR 7 DIAS\n"
          "\n"
          "PARACETAMOL 160 MG/5ML\n"
          "5 ML SI HAY DOLOR O FIEBRE\n")

print("=== lo que pasaba antes ===")
print(repr(limpiar_espacios(receta)))
print()
print("=== lo que pasa ahora ===")
print(limpiar_texto(receta))
print()

fallos = []
def caso(desc, entra, espera):
    sale = limpiar_texto(entra)
    bien = sale == espera
    print(f"  {'ok  ' if bien else 'MAL '} {desc}")
    if not bien:
        print(f"        esperaba {espera!r}")
        print(f"        obtuve   {sale!r}")
        fallos.append(desc)

print("=== comprobaciones ===")
caso("conserva un salto simple",        "UNO\nDOS", "UNO\nDOS")
caso("conserva un renglon en blanco",   "UNO\n\nDOS", "UNO\n\nDOS")
caso("tres en blanco se vuelven dos",   "UNO\n\n\n\n\nDOS", "UNO\n\nDOS")
caso("colapsa espacios dentro del renglon", "UNO    DOS", "UNO DOS")
caso("quita espacios al final del renglon", "UNO   \nDOS", "UNO\nDOS")
caso("quita sangria al inicio del renglon", "UNO\n   DOS", "UNO\nDOS")
caso("saltos de Windows",               "UNO\r\nDOS", "UNO\nDOS")
caso("saltos de Mac viejo",             "UNO\rDOS", "UNO\nDOS")
caso("recorta arriba y abajo",          "\n\n  UNO\nDOS  \n\n", "UNO\nDOS")
caso("tabulador cuenta como espacio",   "UNO\t\tDOS", "UNO DOS")
caso("espacio duro del copiar-pegar",   "UNO  DOS", "UNO DOS")
caso("vacio sigue siendo nulo",         "   \n\n  ", None)
caso("nulo sigue siendo nulo",          None, None)
caso("una sola linea no cambia",        "AMOXICILINA 500 MG", "AMOXICILINA 500 MG")
caso("receta completa",                 receta,
     "AMOXICILINA 500 MG\n1 CADA 8 HORAS POR 7 DIAS\n\n"
     "PARACETAMOL 160 MG/5ML\n5 ML SI HAY DOLOR O FIEBRE")

print()
print("*** TODO EN ORDEN ***" if not fallos else f"*** {len(fallos)} PROBLEMA(S) ***")
