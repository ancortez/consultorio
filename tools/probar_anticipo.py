#!/usr/bin/env python3
"""
El avance de un plan de tratamiento, con anticipos.

Por que existe
--------------
Lo pidio la doctora con un caso concreto: el paciente adelanta un
porcentaje al aceptar el plan, vuelve tres semanas despues, y en el
mostrador nadie se acuerda de cuanto dejo. Se discute delante del
paciente y a veces se le cobra de mas.

Son cuatro numeros que se parecen entre si —total, anticipado,
realizado, cubierto— y de ellos salen los dos que de verdad se usan.
Equivocarse en uno significa cobrarle mal a alguien, asi que la cuenta
se corre de verdad con casos en vez de leerla y darla por buena.

No hay PHP ni MySQL en la maquina donde se desarrolla, asi que el
algoritmo se porta tal cual y las tablas se simulan con listas.
"""
import pathlib
import re
import sys

FALLOS = []


def avance_plan(total, cargos, pagos):
    """Puerto literal de Cargos::avancePlan().

    cargos: [(importe, aplicado), ...]   lo que ya se hizo
    pagos:  [(monto, cancelado), ...]    lo que entro a cuenta del plan
    """
    realizado = sum(c[0] for c in cargos)
    cubierto = sum(c[1] for c in cargos)
    cobrado = sum(p[0] for p in pagos if not p[1])

    anticipo = round(max(0, cobrado - cubierto), 2)
    pendiente_de_lo_hecho = max(0, realizado - cubierto)
    por_cobrar = round(max(0, pendiente_de_lo_hecho - anticipo), 2)

    return {
        'total': round(total, 2),
        'anticipado': anticipo,
        'realizado': round(realizado, 2),
        'cubierto': round(cubierto, 2),
        'por_cobrar': por_cobrar,
        'resta': round(max(0, total - realizado), 2),
        'excedido': realizado > total + 0.009,
    }


def comprobar(nombre, obtenido, esperado):
    if obtenido != esperado:
        FALLOS.append(f'{nombre}\n      esperaba: {esperado!r}\n      obtuvo:   {obtenido!r}')
    else:
        print(f'  ok   {nombre}')


def mostrar(titulo, a):
    print(f'  .. {titulo}: total {a["total"]:,.0f} | anticipado '
          f'{a["anticipado"]:,.0f} | realizado {a["realizado"]:,.0f} | '
          f'cubierto {a["cubierto"]:,.0f} | POR COBRAR {a["por_cobrar"]:,.0f} | '
          f'resta {a["resta"]:,.0f}')


# =====================================================================
#  El caso que conto la doctora
# =====================================================================
# Plan de 20,000. El paciente adelanta el 30% al aceptarlo: 6,000.
print('El caso de la doctora: anticipo del 30%')

a = avance_plan(20000, cargos=[], pagos=[(6000, False)])
mostrar('al firmar, sin hacer nada todavia', a)
comprobar('el anticipo se ve completo', a['anticipado'], 6000)
comprobar('y no hay nada que cobrar hoy', a['por_cobrar'], 0)
comprobar('falta por hacer el tratamiento entero', a['resta'], 20000)

# Primera cita: se hacen 4,500 de trabajo. Nadie ha aplicado nada aun.
a = avance_plan(20000, cargos=[(4500, 0)], pagos=[(6000, False)])
mostrar('tras la primera cita (4,500 de trabajo)', a)
comprobar('NO se le cobra: lo cubre su anticipo', a['por_cobrar'], 0)
comprobar('el anticipo sigue entero hasta que se aplique',
          a['anticipado'], 6000)

# Recepcion aplica 4,500 del anticipo a ese cargo.
a = avance_plan(20000, cargos=[(4500, 4500)], pagos=[(6000, False)])
mostrar('aplicando el anticipo a esa cita', a)
comprobar('del anticipo quedan 1,500', a['anticipado'], 1500)
comprobar('nada por cobrar', a['por_cobrar'], 0)
comprobar('y falta por hacer 15,500', a['resta'], 15500)

# Segunda cita: 5,000 de trabajo. El anticipo ya solo alcanza 1,500.
a = avance_plan(20000, cargos=[(4500, 4500), (5000, 0)],
                pagos=[(6000, False)])
mostrar('segunda cita (5,000 de trabajo)', a)
comprobar('se le cobran 3,500, no 5,000: el resto sale del anticipo',
          a['por_cobrar'], 3500)

# =====================================================================
#  Los bordes, que es donde se cobra mal
# =====================================================================
print()
print('Los bordes')

# Sin anticipo: se cobra todo lo hecho.
a = avance_plan(10000, cargos=[(3000, 0)], pagos=[])
comprobar('sin anticipo se cobra todo lo realizado', a['por_cobrar'], 3000)

# Anticipo MAYOR que el plan entero: el paciente pago todo por
# adelantado. Nunca debe salir un "por cobrar" negativo ni un anticipo
# inflado por encima de lo que entrego.
a = avance_plan(5000, cargos=[(5000, 5000)], pagos=[(8000, False)])
mostrar('pago 8,000 por un plan de 5,000', a)
comprobar('le quedan 3,000 a favor', a['anticipado'], 3000)
comprobar('y no se le cobra nada', a['por_cobrar'], 0)

# Un pago CANCELADO no cuenta: su dinero se devolvio.
a = avance_plan(10000, cargos=[(3000, 0)],
                pagos=[(5000, False), (5000, True)])
comprobar('un pago cancelado no cuenta como anticipo', a['anticipado'], 5000)

# Plan sin nada: todos en cero, sin dividir entre cero ni nada raro.
a = avance_plan(0, cargos=[], pagos=[])
comprobar('un plan en ceros no truena',
          [a['total'], a['anticipado'], a['por_cobrar'], a['resta']],
          [0, 0, 0, 0])

# Se hizo MAS de lo pactado: se agregaron procedimientos despues de
# firmar. "Resta" no puede salir negativo —decir -500 confunde— pero
# tiene que avisarse de que se excedio.
a = avance_plan(5000, cargos=[(6000, 0)], pagos=[])
mostrar('se hizo mas de lo pactado', a)
comprobar('la resta no sale negativa', a['resta'], 0)
comprobar('pero se avisa de que se excedio', a['excedido'], True)
comprobar('y se cobra lo que de verdad se hizo', a['por_cobrar'], 6000)

# Centavos: que no aparezca un peso de la nada por redondeo.
a = avance_plan(1000.00, cargos=[(333.33, 0), (333.33, 0), (333.34, 0)],
                pagos=[(500.00, False)])
comprobar('con centavos la cuenta cuadra', a['por_cobrar'], 500.00)
comprobar('y no sobra ni falta un centavo en la resta', a['resta'], 0.00)

# El anticipo NO se come lo que ya estaba cubierto: aplicar dos veces
# el mismo dinero seria regalarle el tratamiento.
a = avance_plan(10000, cargos=[(4000, 4000)], pagos=[(4000, False)])
comprobar('el dinero ya aplicado no vuelve a contar como anticipo',
          a['anticipado'], 0)
comprobar('y no queda nada por cobrar de lo hecho', a['por_cobrar'], 0)

# =====================================================================
#  La vida de un plan completo
# =====================================================================
# Se simula el plan de principio a fin y se comprueba que al final el
# paciente pago exactamente lo pactado: ni un peso de mas.
print()
print('Un plan de principio a fin')

TOTAL = 12000
cargos = []
pagos = [(4000, False)]          # anticipo inicial
cobrado_en_mostrador = 0

for cita in range(4):            # cuatro citas de 3,000
    cargos.append([3000, 0])
    a = avance_plan(TOTAL, [tuple(c) for c in cargos], pagos)
    cobrado_en_mostrador += a['por_cobrar']

    # Lo que se cobra hoy se aplica, y el anticipo disponible tambien.
    por_aplicar = a['por_cobrar'] + a['anticipado']
    for c in cargos:
        if por_aplicar <= 0:
            break
        falta = c[0] - c[1]
        pone = min(falta, por_aplicar)
        c[1] += pone
        por_aplicar -= pone

    if a['por_cobrar'] > 0:
        pagos.append((a['por_cobrar'], False))

final = avance_plan(TOTAL, [tuple(c) for c in cargos], pagos)
mostrar('al terminar las cuatro citas', final)

entregado = sum(p[0] for p in pagos if not p[1])
comprobar('el paciente entrego exactamente el total del plan',
          round(entregado, 2), float(TOTAL))
comprobar('en el mostrador se le cobraron 8,000 (12,000 menos su anticipo)',
          round(cobrado_en_mostrador, 2), 8000.0)
comprobar('al final no queda nada por cobrar', final['por_cobrar'], 0)
comprobar('ni nada a favor', final['anticipado'], 0)
comprobar('ni nada por hacer', final['resta'], 0)

# =====================================================================
#  Gastar el saldo a favor al cobrar
# =====================================================================
# Puerto de lo que hace Caja::cobrar cuando se marca "usarlo en este
# cobro": recorre los pagos del paciente del mas viejo al mas nuevo y
# va tapando cargos con lo que a cada uno le sobra.
#
# Lo delicado es que el dinero no se aplique dos veces: una vez al
# cargo y otra como "sigue a favor". Seria regalarle el tratamiento.
print()
print('Gastar el saldo a favor al cobrar')


def usar_a_favor(cargos_pendientes, bolsas):
    """cargos_pendientes: [saldo, ...] | bolsas: [libre, ...] (viejo primero)

    Devuelve (aplicado_total, cargos_despues, bolsas_despues).
    """
    cargos = list(cargos_pendientes)
    bolsas = list(bolsas)
    aplicado = 0.0

    for i, falta in enumerate(cargos):
        for j, libre in enumerate(bolsas):
            if falta <= 0.009:
                break
            if libre <= 0.009:
                continue
            pone = round(min(libre, falta), 2)
            bolsas[j] = round(libre - pone, 2)
            falta = round(falta - pone, 2)
            aplicado = round(aplicado + pone, 2)
        cargos[i] = falta

    return aplicado, cargos, bolsas


# Un anticipo que cubre justo el cargo.
ap, cargos, bolsas = usar_a_favor([3000], [3000])
comprobar('el anticipo tapa el cargo exacto', (ap, cargos, bolsas),
          (3000, [0], [0]))

# Alcanza para parte: queda deuda y la bolsa en cero.
ap, cargos, bolsas = usar_a_favor([5000], [2000])
comprobar('si no alcanza, cubre lo que puede',
          (ap, cargos, bolsas), (2000, [3000], [0]))

# Sobra: la bolsa conserva el resto, que sigue a favor.
ap, cargos, bolsas = usar_a_favor([1000], [4000])
comprobar('si sobra, el resto sigue a favor',
          (ap, cargos, bolsas), (1000, [0], [3000]))

# Varios cargos y varias bolsas: se gasta la mas vieja primero.
ap, cargos, bolsas = usar_a_favor([1000, 2000], [1500, 3000])
comprobar('se gasta el dinero mas viejo primero',
          (ap, cargos, bolsas), (3000, [0, 0], [0, 1500]))

# Nada que aplicar: ni truena ni inventa.
comprobar('sin bolsas no pasa nada', usar_a_favor([1000], []), (0.0, [1000], []))
comprobar('sin cargos tampoco', usar_a_favor([], [5000]), (0.0, [], [5000]))

# EL CASO QUE IMPORTA: el dinero no se cuenta dos veces. Lo aplicado
# tiene que salir exactamente de lo que desaparecio de las bolsas.
ap, cargos, bolsas = usar_a_favor([800, 1200, 400], [1000, 900])
comprobar('lo aplicado sale justo de las bolsas',
          ap, round(1900 - sum(bolsas), 2))
comprobar('y la deuda baja en lo mismo',
          round(2400 - sum(cargos), 2), ap)

# Centavos repartidos entre varios cargos.
ap, cargos, bolsas = usar_a_favor([333.33, 333.33, 333.34], [1000.00])
comprobar('con centavos no se pierde ni se inventa un peso',
          (ap, cargos, sum(bolsas)), (1000.00, [0, 0, 0], 0))

# =====================================================================
#  Esta copia y el PHP dicen lo mismo
# =====================================================================
raiz = pathlib.Path(__file__).resolve().parent.parent
php = (raiz / 'includes/core/cargos.php').read_text(encoding='utf-8')

cuerpo = php[php.index('public static function avancePlan'):]
cuerpo = cuerpo[:cuerpo.index("\n    }")]

for formula, queEs in [
        (r'max\(0,\s*\$cobrado\s*-\s*\$cubierto\)', 'el anticipo'),
        (r'max\(0,\s*\$realizado\s*-\s*\$cubierto\)', 'lo pendiente de lo hecho'),
        (r'max\(0,\s*\$pendienteDeLoHecho\s*-\s*\$anticipo\)', 'lo que se cobra hoy'),
        (r'max\(0,\s*\$total\s*-\s*\$realizado\)', 'lo que falta por hacer')]:
    if not re.search(formula, cuerpo):
        FALLOS.append(f'el PHP ya no calcula {queEs} como esta copia')

# Los pagos cancelados tienen que quedar fuera, o un reembolso contaria
# como anticipo y se le regalaria el tratamiento al paciente.
if 'estatus = 1' not in cuerpo:
    FALLOS.append('el PHP suma tambien los pagos cancelados')

comprobar('las cuatro formulas del PHP coinciden con esta copia',
          [f for f in FALLOS if 'el PHP' in f], [])

print()
if FALLOS:
    print(f'*** {len(FALLOS)} PROBLEMA(S) ***')
    for f in FALLOS:
        print('  !!   ' + f)
    sys.exit(1)
print('*** TODO EN ORDEN ***')
