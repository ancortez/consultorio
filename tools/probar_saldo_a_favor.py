#!/usr/bin/env python3
"""
El saldo a favor: lo que se le pide en efectivo y lo que dice la ficha.

Por que existe este arnes
-------------------------
El saldo a favor se construyo en la version 2.10 y Angel reporto que
"como quedo, no funciona". Tenia razon, y el fallo era de los que no se
ven mirando el codigo de una sola funcion:

El aviso de la pantalla decia, correctamente, "debe $1,000, se le
aplican $400 de su saldo a favor: hay que cobrarle $600". Y el campo de
efectivo seguia diciendo $1,000, porque se llenaba con la suma de los
cargos marcados a secas. Quien esta cobrando lee el CAMPO, no el aviso.
Asi que se recibian los mil completos, se aplicaba ademas el anticipo, y
al paciente le quedaba todavia mas a favor: exactamente la bola de nieve
que el saldo a favor venia a deshacer. Y el corte de caja pedia ese
efectivo porque de verdad habia entrado.

Dos reglas separadas que tienen que coincidir, y de ahi salia el error:
una decia la cifra y la otra la pedia. Aqui se corren las dos con los
mismos datos y se comprueba que den lo mismo.

La segunda mitad es la ficha. El recibo decia "total recibido $600"
sobre una cuenta de $1,000 y no explicaba el hueco: las aplicaciones del
saldo a favor cuelgan del pago VIEJO que trajo el dinero, asi que no
salian. Ahora salen como renglon negativo y la ficha tiene que cuadrar
sola: cubierto menos saldo aplicado = recibido.

No hay navegador ni PHP en esta maquina: las dos reglas se portan tal
cual y se corren con numeros.
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


def pesos(x):
    return round(x + 1e-9, 2)


# =====================================================================
#  Las dos reglas de la pantalla de cobro, portadas
# =====================================================================
def favor_en_juego(usa_favor, a_favor, debe):
    """Puerto de favorEnJuego(). Nunca mas de lo que se debe."""
    if not usa_favor:
        return 0.0
    return pesos(min(a_favor, debe))


def lo_que_dice_el_aviso(usa_favor, a_favor, debe):
    """Puerto de actualizarBoton(): la cifra que se LEE."""
    favor = favor_en_juego(usa_favor, a_favor, debe)
    return pesos(max(0, debe - favor))


def lo_que_se_pide(usa_favor, a_favor, debe, suma_marcada):
    """Puerto de sincronizarDesdeCargos(): la cifra que se PIDE."""
    favor = favor_en_juego(usa_favor, a_favor, debe)
    return pesos(max(0, suma_marcada - favor))


# =====================================================================
#  1. La cifra que se dice y la que se pide son la misma
# =====================================================================
print('El aviso y el campo coinciden')

# Marcando TODO lo que debe —el caso del 90%— las dos tienen que dar
# igual. Esta es la comprobacion que no existia.
CASOS = [
    # (debe, a_favor)
    (1000.00, 400.00),   # el caso que reporto Angel
    (1000.00,   0.00),   # sin anticipo
    (1000.00, 1000.00),  # el anticipo cubre justo
    (1000.00, 2500.00),  # el anticipo sobra
    ( 350.50, 120.25),   # con centavos
    (   0.00, 500.00),   # no debe nada y tiene a favor
]

for debe, a_favor in CASOS:
    dice = lo_que_dice_el_aviso(True, a_favor, debe)
    pide = lo_que_se_pide(True, a_favor, debe, debe)
    comprobar(f'debe {debe}, a favor {a_favor}: dice {dice} y pide lo mismo',
              pide, dice)

# Y el fallo concreto, con nombre: como estaba antes.
def regla_vieja(suma_marcada, *_):
    """sincronizarDesdeCargos() de antes: la suma a secas."""
    return pesos(suma_marcada)

comprobar('la regla VIEJA pedia los mil completos (el fallo)',
          regla_vieja(1000.00), 1000.00)
comprobar('la de ahora pide 600', lo_que_se_pide(True, 400, 1000, 1000), 600.00)

# =====================================================================
#  2. Con el interruptor apagado no cambia nada
# =====================================================================
print()
print('Sin usar el saldo a favor')

# Hay pacientes que prefieren dejar el anticipo quieto para el
# tratamiento largo y pagar aparte la limpieza de hoy. Esa decision es
# del mostrador, y apagado el interruptor el cobro es el de siempre.
comprobar('apagado, se le pide todo lo que debe',
          lo_que_se_pide(False, 400, 1000, 1000), 1000.00)
comprobar('y el aviso dice lo mismo',
          lo_que_dice_el_aviso(False, 400, 1000), 1000.00)

# =====================================================================
#  3. Marcando solo parte de lo que debe
# =====================================================================
print()
print('Marcando solo algunos cargos')

# Debe 1000 en dos cargos (600 y 400), tiene 400 a favor y se marca solo
# el de 600. El servidor gasta el saldo contra los cargos pendientes
# empezando por el mas viejo, asi que ese cargo queda en 200.
comprobar('se marca un cargo de 600 con 400 a favor: se piden 200',
          lo_que_se_pide(True, 400, 1000, 600), 200.00)

# Y nunca se pide un negativo, aunque el saldo pase de lo marcado.
comprobar('el saldo a favor nunca deja el efectivo en negativo',
          lo_que_se_pide(True, 900, 1000, 600), 0.00)

# =====================================================================
#  4. El paciente no paga ni un peso de mas
# =====================================================================
print()
print('La cuenta completa de una visita')

# Esto es lo que de verdad importa, y es lo que fallaba: a lo largo de
# un cobro con anticipo, lo que sale del bolsillo del paciente mas lo
# que ya habia entregado tiene que ser EXACTAMENTE lo que debia.
debe, a_favor = 1000.00, 400.00
efectivo = lo_que_se_pide(True, a_favor, debe, debe)
aplicado = favor_en_juego(True, a_favor, debe)

comprobar('efectivo mas saldo aplicado da la cuenta exacta',
          pesos(efectivo + aplicado), debe)
comprobar('y no le queda nada mas a favor por este cobro',
          pesos(a_favor - aplicado), 0.00)

# El caso al reves: el anticipo pasa de la cuenta. Se usa lo que cabe y
# el resto SIGUE a favor; no se le devuelve ni se pierde.
debe, a_favor = 300.00, 1000.00
efectivo = lo_que_se_pide(True, a_favor, debe, debe)
aplicado = favor_en_juego(True, a_favor, debe)

comprobar('con anticipo de sobra no se le cobra nada', efectivo, 0.00)
comprobar('se aplica solo lo que debia', aplicado, 300.00)
comprobar('y le quedan 700 a favor', pesos(a_favor - aplicado), 700.00)

# =====================================================================
#  5. La ficha cuadra sola
# =====================================================================
print()
print('La ficha de pago')


def ficha(aplicaciones, recibido):
    """Puerto del cuadre de recibo_imprimir.php.

    aplicaciones: lista de (monto, salio_de_saldo_a_favor)
    """
    cubierto = pesos(sum(m for m, _ in aplicaciones))
    de_favor = pesos(sum(m for m, f in aplicaciones if f))
    return {
        'cubierto': cubierto,
        'de_favor': de_favor,
        'recibido': pesos(recibido),
        'cuadra': pesos(cubierto - de_favor) == pesos(recibido),
    }


# El caso de Angel: cuenta de 1,000, se aplican 400 de saldo y entran
# 600 en efectivo.
f = ficha([(600.00, False), (400.00, True)], 600.00)
comprobar('la ficha dice que se cubrieron 1,000', f['cubierto'], 1000.00)
comprobar('y que 400 salieron del saldo a favor', f['de_favor'], 400.00)
comprobar('cubierto menos saldo da lo recibido: la ficha cuadra',
          f['cuadra'], True)

# Un cobro normal, sin saldo de por medio: no hay renglon negativo y
# cuadra igual.
f = ficha([(1000.00, False)], 1000.00)
comprobar('sin saldo a favor no hay renglon negativo', f['de_favor'], 0.00)
comprobar('y la ficha cuadra igual', f['cuadra'], True)

# Varios cargos, varios origenes, con centavos.
f = ficha([(120.25, True), (80.00, True), (150.25, False)], 150.25)
comprobar('con centavos y varios origenes tambien cuadra', f['cuadra'], True)

# Y el estado anterior: si las aplicaciones del saldo NO salen en la
# ficha, el papel no cuadra. Es el fallo que se arreglo.
f = ficha([(600.00, False)], 600.00)
comprobar('escondiendo el saldo, la ficha dice 600 sobre una cuenta de 1,000',
          f['cubierto'], 600.00)

# =====================================================================
#  6. El codigo de verdad hace estas dos cosas
# =====================================================================
print()
print('El codigo coincide con lo que se probo aqui')

js = (RAIZ / 'assets/js/cajas.js').read_text(encoding='utf-8')
jsCodigo = re.sub(r'/\*.*?\*/', '', js, flags=re.S)
jsCodigo = re.sub(r'^\s*//.*$', '', jsCodigo, flags=re.M)

# Buscar "favorEnJuego()" en el archivo entero NO sirve: encuentra la
# propia declaracion de la funcion, asi que la comprobacion pasaba
# aunque nadie la llamara. Lo probe rompiendo el codigo a proposito y
# esta fue la unica rotura de quince que se me escapo.
#
# Hay que mirar DENTRO de la funcion que propone el efectivo.
cuerpoSinc = jsCodigo[jsCodigo.index('function sincronizarDesdeCargos'):]
cuerpoSinc = cuerpoSinc[:cuerpoSinc.index('\n  }')]

comprobar('el efectivo propuesto resta el saldo a favor',
          'favorEnJuego()' in cuerpoSinc, True)
# Y que de verdad lo reste: con la suma a secas vuelve el fallo entero.
comprobar('y no se llena con la suma de los cargos a secas',
          re.search(r'formas\[0\]\.monto\s*=\s*suma\s*;', cuerpoSinc) is not None,
          False)

# El interruptor tiene que RECALCULAR, no solo repintar el aviso: eso
# era la mitad del fallo.
cambio = re.search(r"getElementById\('cbUsarAFavor'\)\s*\n?\s*"
                   r"\?\.addEventListener\('change',\s*(\w+)\)", jsCodigo)
comprobar('prender el interruptor recalcula el efectivo',
          cambio.group(1) if cambio else None, 'sincronizarDesdeCargos')

# La columna que une la aplicacion con el cobro que la decidio. Sin
# ella la ficha no puede saber que saldo se aplico en ESE cobro.
php = (RAIZ / 'includes/core/caja.php').read_text(encoding='utf-8')
comprobar('cobrar() apunta en que cobro se gasto el saldo',
          'aplicada_en_pago' in php, True)

recibo = (RAIZ / 'recibo_imprimir.php').read_text(encoding='utf-8')
comprobar('la ficha pide las aplicaciones del saldo a favor',
          'aplicada_en_pago = :en' in recibo, True)
comprobar('y las muestra como renglon negativo',
          '&minus;' in recibo, True)

# Cancelar un cobro devuelve el saldo que gasto. Si no, el paciente
# pierde su anticipo sin que nada lo registre.
comprobar('cancelar un cobro devuelve el saldo a favor que gasto',
          'DELETE FROM pago_aplicacion WHERE aplicada_en_pago' in php, True)

print()
if FALLOS:
    print(f'*** {len(FALLOS)} PROBLEMA(S) ***')
    for f in FALLOS:
        print('  !!   ' + f)
    sys.exit(1)
print('*** TODO EN ORDEN ***')
