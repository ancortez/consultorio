"""
Puerto de la proteccion contra doble cobro.

Lo que se prueba no es SQL: es la regla. Dada una secuencia de envios
—algunos repetidos, algunos simultaneos, algunos legitimos— cuantos
cobros tienen que quedar registrados.
"""
import re, threading, time

UUID = re.compile(r'^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$', re.I)

class Base:
    """
    Imita la tabla pago con su indice unico sobre huella_envio.

    El candado de aqui MODELA el indice unico de MySQL, que es atomico
    por construccion. No es parte del sistema a probar: quitarlo no
    prueba nada util, porque en la base real esa garantia no se puede
    quitar. Lo que si se prueba es el codigo que vive ENCIMA del indice:
    que consulte antes, que atrape el choque y que devuelva el cobro
    que ya existia en vez de reventar.
    """
    def __init__(self):
        self.pagos = []
        self.folios_usados = 0
        self.candado = threading.Lock()

    def insertar(self, huella, monto, lento=False):
        # El indice unico es atomico: o entra, o choca.
        with self.candado:
            if lento: time.sleep(0.03)
            if huella is not None and any(p['huella'] == huella for p in self.pagos):
                raise KeyError('uq_pago_huella')
            pago = {'id': len(self.pagos)+1, 'folio': f"RC{len(self.pagos)+1:05d}",
                    'huella': huella, 'monto': monto}
            self.pagos.append(pago)
            return pago

    def por_huella(self, huella):
        if huella is None: return None
        return next((p for p in self.pagos if p['huella'] == huella), None)


def huella_valida(v):
    v = (v or '').strip()
    return v.lower() if UUID.match(v) else None


ERRORES = []

def cobrar_en_hilo(base, huella, monto, lento):
    try:
        cobrar(base, huella, monto, lento)
    except Exception as e:
        # Una excepcion dentro de un hilo no llega al hilo principal.
        # Sin recogerla, la prueba pasaria aunque el cobro reventara.
        ERRORES.append(e)


def cobrar(base, huella_cruda, monto, lento=False):
    """Devuelve (pago, repetido)."""
    huella = huella_valida(huella_cruda)

    # 1. Lo barato: ¿ya existe?
    if huella is not None:
        ya = base.por_huella(huella)
        if ya is not None:
            return ya, True

    # El folio se aparta ANTES de insertar, igual que en el sistema.
    # Por eso la revision previa importa aunque el indice unico ya
    # garantice un solo cobro: sin ella, cada envio repetido consume un
    # folio que nadie va a usar y la numeracion de recibos queda con
    # huecos. Un talonario con saltos es justo lo que un contador pide
    # explicar.
    base.folios_usados += 1

    # 2. La red de abajo: el indice unico.
    try:
        return base.insertar(huella, monto, lento), False
    except KeyError:
        ya = base.por_huella(huella)
        if ya is not None:
            return ya, True
        raise


fallos = []
def caso(desc, esperados, fn):
    base = Base()
    fn(base)
    bien = len(base.pagos) == esperados
    print(f"  {'ok  ' if bien else 'MAL '} {desc}: {len(base.pagos)} cobro(s), "
          f"esperaba {esperados}")
    if not bien: fallos.append(desc)
    return base

H1 = '3f2a1b4c-5d6e-4f70-8a9b-0c1d2e3f4a5b'
H2 = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'

print("=== lo que pasa en caja ===")

caso("un cobro normal", 1, lambda b: cobrar(b, H1, 500))

caso("doble clic (misma huella)", 1,
     lambda b: [cobrar(b, H1, 500), cobrar(b, H1, 500)])

caso("cinco clics desesperados", 1,
     lambda b: [cobrar(b, H1, 500) for _ in range(5)])

caso("dos cobros legitimos al mismo paciente", 2,
     lambda b: [cobrar(b, H1, 500), cobrar(b, H2, 300)])

def simultaneo(b):
    # Dos peticiones que llegan tan juntas que las dos leen "no existe".
    hilos = [threading.Thread(target=cobrar_en_hilo, args=(b, H1, 500, True)) for _ in range(2)]
    [h.start() for h in hilos]; [h.join() for h in hilos]
caso("dos peticiones simultaneas", 1, simultaneo)

def muchos_simultaneos(b):
    hilos = [threading.Thread(target=cobrar_en_hilo, args=(b, H1, 500, True)) for _ in range(8)]
    [h.start() for h in hilos]; [h.join() for h in hilos]
caso("ocho peticiones simultaneas", 1, muchos_simultaneos)

# Sin huella el sistema funciona como antes: cada envio es un cobro.
# Es a proposito: nunca se bloquea un cobro por no traer huella.
caso("sin huella: no protege, pero no estorba", 2,
     lambda b: [cobrar(b, None, 500), cobrar(b, None, 500)])

caso("huella con forma invalida se ignora", 2,
     lambda b: [cobrar(b, 'LA-MISMA-SIEMPRE', 500), cobrar(b, 'LA-MISMA-SIEMPRE', 500)])

print()
print("=== detalles ===")

b = Base()
p1, r1 = cobrar(b, H1, 500)
p2, r2 = cobrar(b, H1, 500)
print(f"  {'ok  ' if (not r1 and r2) else 'MAL '} el segundo envio se marca como repetido")
if not (not r1 and r2): fallos.append('no marca repetido')
print(f"  {'ok  ' if p1['folio'] == p2['folio'] else 'MAL '} "
      f"devuelve el mismo folio ({p1['folio']})")
if p1['folio'] != p2['folio']: fallos.append('folios distintos')

# Mayusculas y minusculas son la misma huella.
b = Base()
cobrar(b, H1.upper(), 500); cobrar(b, H1.lower(), 500)
print(f"  {'ok  ' if len(b.pagos)==1 else 'MAL '} MAYUSCULAS y minusculas son la misma")
if len(b.pagos)!=1: fallos.append('distingue mayusculas')

print()
b = Base()
for _ in range(4): cobrar(b, H1, 500)
sin_huecos = b.folios_usados == 1
print(f"  {'ok  ' if sin_huecos else 'MAL '} cuatro clics consumen "
      f"{b.folios_usados} folio(s): la numeracion no queda con huecos")
if not sin_huecos: fallos.append('desperdicia folios en cada reintento')

if ERRORES:
    print(f"  MAL  {len(ERRORES)} error(es) dentro de los hilos: {ERRORES[0]}")
    fallos.append('excepciones en hilos')
else:
    print("  ok   ningun hilo reviento")

print()
print("*** TODO EN ORDEN ***" if not fallos else f"*** {len(fallos)} PROBLEMA(S) ***")
