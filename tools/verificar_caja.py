"""
Revisiones del modulo de Cajas y Citas.

Lo que no se puede probar aqui (no hay PHP ni MySQL en este entorno)
se prueba con un puerto a Python de la logica: el reparto de un abono
contra los cargos y el arqueo del turno. Son las dos cuentas donde un
error se traduce en dinero mal registrado.
"""
import sys
import re, pathlib, math

raiz = pathlib.Path('.')
fallos, avisos = [], []

esq  = (raiz/'db/01_schema.sql').read_text(encoding='utf-8')
cat  = (raiz/'db/02_catalogos.sql').read_text(encoding='utf-8')
caja = (raiz/'includes/core/caja.php').read_text(encoding='utf-8')
carg = (raiz/'includes/core/cargos.php').read_text(encoding='utf-8')

def tabla(nombre):
    i = esq.index(f'CREATE TABLE {nombre} (')
    return esq[i:esq.index(') ENGINE', i)]

# ---------- 1. El modelo de cargos ----------
for t in ('cargo', 'pago_aplicacion', 'agenda_bloqueo', 'lista_espera',
          'recordatorio_regla', 'cita_recordatorio'):
    if f'CREATE TABLE {t} (' not in esq:
        fallos.append(f'falta la tabla {t}')
print(f'  ok   {6} tablas nuevas declaradas')

# pago_aplicacion apunta a cargo, no al renglon del plan
pa = tabla('pago_aplicacion')
if 'id_cargo' not in pa:
    fallos.append('pago_aplicacion no apunta a cargo')
if 'id_plan_detalle' in pa:
    fallos.append('pago_aplicacion sigue apuntando al renglon del plan')
print('  ok   los pagos se aplican contra cargos, no contra el plan')

# El cargo puede venir de tres lados y de ninguno
c = tabla('cargo')
for col in ('id_plan_tratamiento', 'id_plan_detalle', 'id_consulta',
            'id_procedimiento', 'id_usuario_descuento', 'motivo_descuento'):
    if col not in c: fallos.append(f'cargo sin columna {col}')
if 'estatus' not in c or 'motivo_cancelacion' not in c:
    fallos.append('cargo no guarda la cancelacion')
print('  ok   el cargo guarda de donde vino, su descuento y su cancelacion')

# ---------- 2. Vistas de saldo ----------
for v in ('v_cargo_saldo', 'v_plan_saldo', 'v_paciente_saldo', 'v_corte_resumen'):
    if f'CREATE OR REPLACE VIEW {v} AS' not in esq:
        fallos.append(f'falta la vista {v}')

# El saldo debe salir de restar, nunca de una columna guardada
vcs = esq[esq.index('CREATE OR REPLACE VIEW v_cargo_saldo AS'):]
vcs = vcs[:vcs.index(';')]
if 'c.importe - COALESCE(ap.aplicado, 0) AS saldo' not in vcs:
    fallos.append('v_cargo_saldo no calcula el saldo restando')
# Un pago cancelado no cuenta
if 'p.estatus = 1' not in vcs:
    fallos.append('v_cargo_saldo suma pagos cancelados')
# Un cargo cancelado no cuenta
if 'WHERE c.estatus = 1' not in vcs:
    fallos.append('v_cargo_saldo incluye cargos cancelados')
print('  ok   el saldo se calcula, y descarta lo cancelado de los dos lados')

# ---------- 3. Reglas de caja que no pueden faltar ----------
reglas = {
    'sin turno no se cobra':
        "No hay un turno de caja abierto",
    'cancelar solo el mismo dia':
        "Solo se puede cancelar un cobro el mismo dia",
    'cancelar solo con el turno abierto':
        "El turno de caja de ese cobro ya se cerro",
    'el cobro no se borra':
        "SET estatus = 0, id_usuario_cancela",
    'el reverso se registra':
        "CONCEPTO_CANCELA",
    'una caja no abre dos turnos':
        "ya tiene un turno abierto",
    'el reparto va del cargo mas viejo al mas nuevo':
        "foreach ($pendientes as $p)",
    'no se aplica mas de lo que el cargo debe':
        "min($monto, $porCargo[$idCargo], $porAplicar)",
}
for nombre, aguja in reglas.items():
    if aguja not in caja:
        fallos.append(f'la regla "{nombre}" no esta en Caja')
print(f'  ok   {len(reglas)} reglas de caja presentes')

# El descuento necesita permiso propio
cg = (raiz/'includes/cajas/cargo_guardar.php').read_text(encoding='utf-8')
if "Auth::puede('cajas.descuento')" not in cg:
    fallos.append('el descuento no pide el permiso cajas.descuento')
if "endpoint('cajas.cargo')" not in cg:
    fallos.append('cargo_guardar no exige el permiso cajas.cargo')
print('  ok   el descuento y el cargo piden su permiso')

# Un cargo con abonos no se puede cancelar ni reescribir
if 'ya tiene pagos aplicados' not in carg:
    fallos.append('se puede cancelar un cargo que ya tiene abonos')
if "if ((float) $cargo['aplicado'] > 0) { $intocables++; continue; }" not in carg:
    fallos.append('la sincronizacion del plan pisa cargos ya abonados')
print('  ok   lo ya abonado no se toca')

# Los cargos nacen al GUARDAR el plan, en cualquier estado menos el
# cancelado.
#
# Esto decia lo contrario hasta el 17 de octubre de 2026: exigia
# ESTADOS_COBRABLES = [2, 3, 4], o sea que un plan propuesto no
# generara cargos. Era el comportamiento que Angel reporto como fallo:
# la doctora captura el plan, lo explica y el tratamiento arranca, pero
# el pendiente de pago no aparecia hasta escanear la hoja firmada y
# volver a guardar, asi que en el mostrador el paciente no debia nada
# por un trabajo que ya se le estaba haciendo.
if 'ESTADOS_COBRABLES = [1, 2, 3, 4]' not in carg:
    fallos.append('los cargos del plan vuelven a esperar a que se acepte: '
                  'el pendiente de pago no apareceria al guardar')
# El cancelado tiene que seguir FUERA, y su rama tiene que retirar los
# cargos sin pagos: es la contrapartida de generarlos desde el principio.
if '5' in carg[carg.index('ESTADOS_COBRABLES'):
              carg.index('ESTADOS_COBRABLES') + 60]:
    fallos.append('un plan cancelado seguiria generando cargos')
if 'Se cancelo el plan de tratamiento.' not in carg:
    fallos.append('cancelar el plan no retira sus cargos sin pagos')
print('  ok   el plan genera cargos al guardarse, y el cancelado los retira')

# ---------- El tablero: fuera "Estado de caja" ----------
#
# La doctora la quito el 17 de octubre de 2026. Ensaniaba si hay una
# caja abierta, a que hora y con cuanto fondo, y en el uso diario no
# contestaba ninguna pregunta: quien abre la caja ya sabe que la abrio.
# Ocupaba el primer sitio de la columna —el que se lee— y empujaba hacia
# abajo las dos que si se usan.
#
# Se mira el codigo SIN los comentarios de PHP: el parrafo que explica
# el cambio nombra la tarjeta, y buscandola en el archivo completo esta
# comprobacion encontraba su propia explicacion.
_inicio = (raiz/'vistas/inicio.php').read_text(encoding='utf-8')
_inicioCodigo = re.sub(r'<\?php\s*/\*[\s\S]*?\*/\s*\?>', '', _inicio)
_inicioCodigo = re.sub(r'/\*[\s\S]*?\*/', '', _inicioCodigo)

if 'Estado de caja' in _inicioCodigo:
    fallos.append('volvio la tarjeta "Estado de caja" al tablero')
# Y su consulta: una que se sigue haciendo en cada carga para un dato
# que nadie ensenia es trabajo invisible.
if '$cajaAbierta' in _inicioCodigo:
    fallos.append('el tablero sigue pidiendo la caja abierta para nada')

# Las dos que SI se quedan, y en su CABECERA de tarjeta.
#
# Buscando el texto suelto no servia: "Pendientes de pago" aparece
# tambien en el titulo del modal de "ver todos", asi que quitando la
# tarjeta la comprobacion seguia encontrandolo y pasaba en verde. Lo que
# dice que la tarjeta existe es su card-header.
for _tarjeta in ('Pendientes de pago', 'Con saldo a favor'):
    if not re.search(r'card-header[\s\S]{0,300}?' + re.escape(_tarjeta),
                     _inicioCodigo):
        fallos.append(f'se perdio la tarjeta "{_tarjeta}" del tablero')
print('  ok   el tablero sin Estado de caja, con pendientes y saldo a favor')

# ---------- 4. Permisos nuevos, en el catalogo y en algun rol ----------
nuevos = ['cajas.descuento', 'cajas.cargo', 'cajas.estado_cuenta', 'consultas.todas']
for p in nuevos:
    if f"'{p}'" not in cat:
        fallos.append(f'el permiso {p} no esta en el catalogo')
    # Debe estar asignado a por lo menos un rol ademas del administrador
    bloque = cat[cat.index('-- Dentista'):]
    if f"'{p}'" not in bloque:
        avisos.append(f'{p} solo lo tiene el administrador')
print(f'  ok   {len(nuevos)} permisos nuevos en el catalogo')

# ---------- 5. Puerto del reparto de un abono ----------
def repartir(total, cargos, elegidas=None):
    """Mismo algoritmo que Caja::cobrar(), en Python."""
    saldo = {c['id']: float(c['saldo']) for c in cargos}
    aplic, resta = [], round(total, 2)

    for a in (elegidas or []):
        i, m = a['id'], round(a['monto'], 2)
        if m <= 0 or i not in saldo: continue
        m = min(m, saldo[i], resta)
        if m <= 0: continue
        aplic.append((i, m)); saldo[i] -= m; resta = round(resta - m, 2)

    for c in cargos:                       # ya vienen del mas viejo al mas nuevo
        if resta <= 0: break
        libre = saldo.get(c['id'], 0)
        if libre <= 0: continue
        m = round(min(libre, resta), 2)
        aplic.append((c['id'], m)); saldo[c['id']] -= m; resta = round(resta - m, 2)

    return aplic, resta

cargos = [{'id': 1, 'saldo': 700}, {'id': 2, 'saldo': 2200}, {'id': 3, 'saldo': 4500}]

pruebas = [
    # (descripcion, total, elegidas, aplicado esperado, a favor esperado)
    ('abono chico va al mas viejo', 500, None, [(1, 500)], 0),
    ('abono exacto del primero',    700, None, [(1, 700)], 0),
    ('abono que cruza dos cargos', 1000, None, [(1, 700), (2, 300)], 0),
    ('paga todo',                  7400, None, [(1,700),(2,2200),(3,4500)], 0),
    ('paga de mas: queda a favor', 8000, None, [(1,700),(2,2200),(3,4500)], 600),
    ('elige un cargo del medio',   2200, [{'id':2,'monto':2200}], [(2,2200)], 0),
    ('elige mas de lo que debe',   5000, [{'id':1,'monto':9999}],
        [(1,700),(2,2200),(3,2100)], 0),
    ('cargo inventado se ignora',   500, [{'id':99,'monto':500}], [(1,500)], 0),
]

for desc, total, eleg, esperado, favor in pruebas:
    ap, resta = repartir(total, cargos, eleg)
    if ap != esperado or abs(resta - favor) > 0.001:
        fallos.append(f'reparto "{desc}": dio {ap} / a favor {resta}, '
                      f'se esperaba {esperado} / {favor}')
print(f'  ok   {len(pruebas)} repartos de abono')

# El total aplicado nunca puede pasar del total cobrado
for desc, total, eleg, _, _ in pruebas:
    ap, resta = repartir(total, cargos, eleg)
    suma = round(sum(m for _, m in ap), 2)
    if suma + resta > total + 0.001:
        fallos.append(f'reparto "{desc}": aplico {suma} de un cobro de {total}')
    for i, m in ap:
        tope = next(c['saldo'] for c in cargos if c['id'] == i)
        if m > tope + 0.001:
            fallos.append(f'reparto "{desc}": al cargo {i} le aplico {m} y solo debia {tope}')
print('  ok   ningun reparto excede el cobro ni el saldo del cargo')

# ---------- 6. Puerto del arqueo del turno ----------
def arqueo(formas, contado):
    """Mismo calculo que Caja::resumen() + Caja::cerrar()."""
    ingresos = sum(f['ingresos'] for f in formas)
    egresos  = sum(f['egresos'] for f in formas)
    efectivo = sum(f['ingresos'] - f['egresos'] for f in formas if f['efectivo'])
    return {
        'ingresos': round(ingresos, 2),
        'egresos': round(egresos, 2),
        'esperado': round(efectivo, 2),
        'diferencia': round(contado - efectivo, 2),
    }

# Fondo 500 en efectivo, cobro de 1200 en efectivo, 3000 por transferencia,
# y una compra de material de 400 en efectivo.
formas = [
    {'nombre': 'Efectivo',       'efectivo': True,  'ingresos': 500 + 1200, 'egresos': 400},
    {'nombre': 'Transferencia',  'efectivo': False, 'ingresos': 3000,       'egresos': 0},
]
r = arqueo(formas, contado=1300)
casos_arqueo = [
    ('ingresos del dia',   r['ingresos'],   4700),
    ('egresos del dia',    r['egresos'],     400),
    # La transferencia suma al dia pero NO al cajon: 500+1200-400 = 1300
    ('efectivo esperado',  r['esperado'],   1300),
    ('cuadra exacto',      r['diferencia'],    0),
]
for nombre, dio, esperaba in casos_arqueo:
    if abs(dio - esperaba) > 0.001:
        fallos.append(f'arqueo "{nombre}": dio {dio}, se esperaba {esperaba}')

# Un faltante se reporta en negativo, no en valor absoluto
if arqueo(formas, contado=1250)['diferencia'] != -50:
    fallos.append('un faltante no sale en negativo')
if arqueo(formas, contado=1380)['diferencia'] != 80:
    fallos.append('un sobrante no sale en positivo')
print(f'  ok   arqueo: {len(casos_arqueo)} cifras, mas faltante y sobrante')

# ---------- 7. Recordatorios: la ventana de cada regla ----------
def toca(horas_faltan, reglas, hechos):
    """Mismo criterio que AgendaExtra::pendientes()."""
    for i, r in enumerate(reglas):
        if r['id'] in hechos: continue
        techo = r['horas']
        piso  = reglas[i+1]['horas'] if i+1 < len(reglas) else 0
        if horas_faltan <= techo and horas_faltan > piso:
            return r['id']
    return None

reglas_r = [{'id':1,'horas':48}, {'id':2,'horas':24}, {'id':3,'horas':2}]
casos_r = [
    (72, set(), None),   # todavia falta mucho
    (48, set(), 1),      # justo en la ventana de 48
    (30, set(), 1),      # sigue en la de 48 porque aun no entra la de 24
    (24, set(), 2),      # entro la de 24
    (10, set(), 2),
    (10, {2},   None),   # la de 24 ya se hizo y la de 2 aun no entra
    (2,  {2},   3),
    (0.5, {2,3}, None),  # todas hechas
    (1,  set(), 3),
]
for faltan, hechos, esperado in casos_r:
    dio = toca(faltan, reglas_r, hechos)
    if dio != esperado:
        fallos.append(f'recordatorio a {faltan} h con {hechos or "nada"} hecho: '
                      f'dio {dio}, se esperaba {esperado}')
print(f'  ok   {len(casos_r)} casos de ventana de recordatorio')

print()
for a in avisos: print('  ~   ', a)
for f in fallos: print('  !!  ', f)
print()
print('*** TODO EN ORDEN ***' if not fallos else f'*** {len(fallos)} PROBLEMA(S) ***')

# Salir con error cuando hay fallos.
#
# Sin esto el verificador IMPRIME el problema y devuelve 0, asi que
# cualquier guion que lo encadene con && sigue de largo y el fallo se
# queda en la pantalla, leido por nadie. Un verificador que siempre
# dice que si no verifica nada.
sys.exit(1 if fallos else 0)
