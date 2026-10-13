"""
Balance de las estructuras alternativas de PHP, con el lexer de verdad.

Se corre aparte de los demas verificadores porque necesita phply:

    pip install --break-system-packages phply
    python3 tools/verificar_php.py

if/foreach con dos puntos tienen que cerrar con endif/endforeach. Si
falta uno, PHP no compila y la pagina sale EN BLANCO: no hay media
pagina ni mensaje, se corta en seco.

Mi intento anterior con expresiones regulares daba falsos positivos
porque se tragaba lo que hay dentro de comentarios y cadenas. El lexer
ya sabe distinguirlos, que es justo lo dificil.
"""
import sys, glob, pathlib
from phply.phplex import lexer

ABRE  = {'IF': 'IF', 'FOREACH': 'FOREACH', 'FOR': 'FOR',
         'WHILE': 'WHILE', 'SWITCH': 'SWITCH'}
CIERRA = {'ENDIF': 'IF', 'ENDFOREACH': 'FOREACH', 'ENDFOR': 'FOR',
          'ENDWHILE': 'WHILE', 'ENDSWITCH': 'SWITCH'}

def revisar(ruta):
    s = pathlib.Path(ruta).read_text(encoding='utf-8')
    lx = lexer.clone(); lx.input(s)

    def tokens():
        while True:
            t = lx.token()
            if t is None: return
            yield t

    pila = []        # (tipo, linea)
    pendiente = None # estructura vista, esperando su '(' ... ')'
    cerrado = False  # ya se vio el ')' de esa estructura
    nivelPar = 0
    problemas = []

    for t in tokens():
        if t.type == 'LPAREN':
            nivelPar += 1
            continue
        if t.type == 'RPAREN':
            nivelPar -= 1
            # El ')' que cierra la condicion: lo que venga JUSTO despues
            # decide la forma.
            if pendiente and nivelPar == 0:
                cerrado = True
            continue

        if t.type in ABRE and nivelPar == 0:
            pendiente = (ABRE[t.type], t.lineno)
            cerrado = (t.type == 'ELSE')   # else no lleva condicion
            continue

        if pendiente and nivelPar == 0 and cerrado:
            # Solo el PRIMER token tras el ')' decide. Si fuera
            # cualquier ':' posterior, el de un ternario dentro del
            # cuerpo —"if ($x) return $a ? 'b' : 'c';"— se leeria como
            # apertura de bloque alternativo y daria un falso positivo.
            if t.type == 'COLON':
                pila.append(pendiente)
            pendiente = None
            cerrado = False
            continue

        if t.type in CIERRA:
            esperado = CIERRA[t.type]
            if not pila:
                problemas.append(f'linea {t.lineno}: sobra un {t.type.lower()}')
            elif pila[-1][0] != esperado:
                abierto, ln = pila[-1]
                problemas.append(
                    f'linea {t.lineno}: {t.type.lower()} cierra un '
                    f'{abierto.lower()} abierto en la linea {ln}')
                pila.pop()
            else:
                pila.pop()

    for tipo, ln in pila:
        problemas.append(f'linea {ln}: {tipo.lower()} sin cerrar')
    return problemas

objetivo = sys.argv[1:] or sorted(
    f for f in glob.glob('**/*.php', recursive=True) if '/storage/' not in f)

malos = 0
for f in objetivo:
    p = revisar(f)
    if p:
        malos += 1
        print(f"  {f}")
        for x in p: print(f"      {x}")

print(f"\n  {'todo balanceado' if not malos else str(malos) + ' archivo(s) con problema'}"
      f"  ({len(objetivo)} revisados)")


# =====================================================================
#  Cadenas partidas por un apostrofe de mas
# =====================================================================
# El error que llego a la pantalla de la clienta:
#
#     Parse error: syntax error, unexpected single-quoted string
#     "FOTO_PACIENTE", expecting ")"
#
# Yo habia escrito, dentro de una cadena de PHP delimitada por
# apostrofes:
#
#     AND td.clave = ''FOTO_PACIENTE''
#
# Doblar el apostrofe es la regla de SQL para meter un apostrofe DENTRO
# de un texto. Pero aqui la cadena de PHP tambien va entre apostrofes,
# asi que el primer par la CIERRA: PHP ve una cadena, una palabra
# suelta y otra cadena, tres cosas pegadas sin un solo operador.
#
# Por que importa cazarlo aqui: un error de sintaxis en un archivo
# incluido tumba la pagina a media construccion. La ficha salia sin sus
# tarjetas y la cortinilla se quedaba girando, porque el <script> del
# final nunca llegaba a escribirse. De cara a quien lo usa, eso no se
# parece en nada a un apostrofe de mas.
#
# El parser completo de phply no sirve: es de la epoca de PHP 5 y
# rechaza 66 archivos buenos por sintaxis moderna. El lexer si, y la
# firma es inconfundible: dos literales pegados sin operador. En PHP
# correcto eso no existe —para unir dos cadenas hace falta un punto.
def cadenasPartidas(ruta):
    lx = lexer.clone()
    lx.input(pathlib.Path(ruta).read_text(encoding='utf-8', errors='ignore'))

    anterior = None
    hallados = []
    while True:
        t = lx.token()
        if t is None:
            break
        if t.type in ('WHITESPACE', 'COMMENT', 'DOC_COMMENT'):
            continue
        if (anterior is not None
                and anterior.type == 'CONSTANT_ENCAPSED_STRING'
                and t.type in ('CONSTANT_ENCAPSED_STRING', 'STRING')):
            hallados.append(
                f'linea {t.lineno}: literal pegado a otro sin operador '
                f'({str(t.value)[:28]}). Casi siempre es un apostrofe '
                f'doblado dentro de una cadena: en PHP la cierra.')
        anterior = t
    return hallados


print()
print('Cadenas partidas por un apostrofe de mas')

partidos = 0
for f in objetivo:
    try:
        h = cadenasPartidas(f)
    except Exception:
        continue        # el lexer ya fallo arriba y se conto alli
    if h:
        partidos += 1
        print(f'  {f}')
        for x in h:
            print(f'      {x}')

if partidos:
    print(f'\n  {partidos} archivo(s) con una cadena partida')
else:
    print(f'  ok   ninguna cadena se parte en dos  ({len(objetivo)} revisados)')

sys.exit(1 if (malos or partidos) else 0)
