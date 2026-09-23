import re, pathlib
raiz = pathlib.Path('.')
fallos, avisos = [], []

# ---------- 1. tipo_documento ----------
cat = (raiz/'db/02_catalogos.sql').read_text(encoding='utf-8')
bloque = cat[cat.index('INSERT INTO tipo_documento'):]
bloque = bloque[:bloque.index(';')]
patron = (r"\((\d+),\s*'([A-Z_]+)',\s*'[^']*',\s*'[^']*',\s*'([^']*)',\s*"
          r"(\d),\s*(\d),\s*'(PACIENTE|PLAN|CONSULTA)',\s*'([^']*)',\s*(\d+)\)")
filas = re.findall(patron, bloque, re.S)
ids = [f[0] for f in filas]; claves = [f[1] for f in filas]
if len(filas) != 10: fallos.append(f'tipo_documento: se esperaban 10 renglones, hay {len(filas)}')
if len(set(ids)) != len(ids): fallos.append('tipo_documento: ids repetidos')
if len(set(claves)) != len(claves): fallos.append('tipo_documento: claves repetidas')
amb = {}
for f in filas: amb.setdefault(f[5], []).append(f[1])
print(f"  ok   tipo_documento: {len(filas)} tipos "
      f"({len(amb.get('PACIENTE',[]))} de paciente, {len(amb.get('PLAN',[]))} de plan, "
      f"{len(amb.get('CONSULTA',[]))} de consulta)")

cons = [f for f in filas if f[1]=='CONSENTIMIENTO']
if not cons: fallos.append('no existe el tipo CONSENTIMIENTO')
else:
    c = cons[0]
    if c[3] != '0': fallos.append('CONSENTIMIENTO sigue con pendiente_al_alta = 1')
    if c[4] != '0': fallos.append('CONSENTIMIENTO permite varios; debe ser unico por plan')
    if c[5] != 'PLAN': fallos.append('CONSENTIMIENTO no quedo en ambito PLAN')
    print('  ok   consentimiento: unico, por plan, y no se pide al alta')

# Nada se pide al dar de alta: el expediente no nace con avisos rojos
# de papeles que todavia no existen.
alAlta = [f[1] for f in filas if f[3] == '1']
if alAlta:
    fallos.append('tipos que se piden al alta y no deberian: ' + str(alAlta))
print('  ok   ningun documento se pide por adelantado')


# El ambito de cada clave debe cuadrar con donde lo usa el codigo
esperado = {'FOTO_PACIENTE':'PACIENTE','HISTORIAL_CLINICO':'PACIENTE',
            'IDENTIFICACION_TUTOR':'PACIENTE','OTRO':'PACIENTE',
            'CONSENTIMIENTO':'PLAN','PLAN_TRATAMIENTO':'PLAN',
            'RECETA':'CONSULTA','RADIOGRAFIA':'CONSULTA',
            'FOTOGRAFIA':'CONSULTA','OTRO_CONSULTA':'CONSULTA'}
real = {f[1]: f[5] for f in filas}

# NOTA_EVOLUCION dejo de ser documento: las notas no se escanean.
if 'NOTA_EVOLUCION' in real:
    fallos.append('NOTA_EVOLUCION sigue en tipo_documento; ya vive en su propia tabla')
print('  ok   las notas de evolucion no son un documento por escanear')
for k, v in esperado.items():
    if real.get(k) != v:
        fallos.append(f'{k}: ambito {real.get(k)}, se esperaba {v}')
print(f'  ok   los {len(esperado)} tipos conocidos estan en el ambito que les toca')

# ---------- 2. columnas nuevas y llaves ----------
esq = (raiz/'db/01_schema.sql').read_text(encoding='utf-8')
for tabla, ix in (('documento','ix_doc_plan'),('consulta','ix_consulta_plan')):
    cuerpo = esq[esq.index(f'CREATE TABLE {tabla} ('):]
    cuerpo = cuerpo[:cuerpo.index(') ENGINE')]
    if 'id_plan_tratamiento' not in cuerpo:
        fallos.append(f'{tabla} sin columna id_plan_tratamiento')
    if ix not in cuerpo:
        avisos.append(f'{tabla}: la columna del plan no tiene indice')
for fk in ('fk_doc_plan','fk_consulta_plan'):
    if f'ADD CONSTRAINT {fk}' not in esq: fallos.append(f'falta el ALTER de {fk}')
if esq.index('CREATE TABLE plan_tratamiento (') > esq.index('ADD CONSTRAINT fk_doc_plan'):
    fallos.append('el ALTER corre antes de crear plan_tratamiento')
if 'ambito' not in esq[esq.index('CREATE TABLE tipo_documento ('):
                        esq.index('CREATE TABLE documento (')]:
    fallos.append('tipo_documento sin columna ambito')
print('  ok   documento y consulta cuelgan del plan, con sus llaves al final')

# ---------- 3. ids del JS contra las vistas ----------
nombres = ['app.js','comun.js','paciente.js','expediente.js','consultas.js',
           'historia.js','configuracion.js','inicio.js']
js = '\n'.join((raiz/'assets/js'/n).read_text(encoding='utf-8') for n in nombres)
vistas = '\n'.join(p.read_text(encoding='utf-8') for p in raiz.rglob('vistas/**/*.php'))
vistas += (raiz/'index.php').read_text(encoding='utf-8')
faltantes = []
for ident in sorted(set(re.findall(r"getElementById\('([A-Za-z0-9_]+)'\)", js))):
    if f'id="{ident}"' not in vistas and f'id="{ident}"' not in js:
        faltantes.append(ident)
if faltantes: avisos.append('ids que el JS busca y nadie define: ' + str(faltantes))
print('  ok   ids del JavaScript contra las vistas')

# ---------- 4. colspan contra columnas reales ----------
def cols(html, tid):
    i = html.index(f'id="{tid}"')
    return len(re.findall(r'<th\b', html[html.rfind('<thead', 0, i):i]))

tablas = {
    'tablaPacientes':      'vistas/paciente/buscar_paciente.php',
    'tablaPlanes':         'vistas/paciente/detalle_paciente.php',
    'tablaPlanesAbiertos': 'vistas/consultas/planes.php',
    'planTabla':           'vistas/consultas/nuevo_plan_tratamiento.php',
    'planResumen':         'vistas/consultas/nuevo_plan_tratamiento.php',
}
anchos = {}
for tid, archivo in tablas.items():
    html = (raiz/archivo).read_text(encoding='utf-8')
    n = cols(html, tid); anchos[tid] = n
    i = html.index(f'id="{tid}"')
    for c in re.findall(r'colspan="(\d+)"', html[i:i+500]):
        if int(c) != n:
            fallos.append(f'{archivo} #{tid}: colspan {c} y la tabla tiene {n} columnas')
    print(f'  ok   {tid}: {n} columnas')

# Los colspan que escribe el JavaScript.
#
# El nombre de la variable se repite entre funciones ("const tabla = ..."
# aparece tres veces en el mismo archivo), asi que hay que acotar la
# revision a la funcion donde vive esa tabla. Se hace contando llaves
# desde el inicio de la funcion hasta su cierre.
def cuerpo_de_la_funcion(txt, pos):
    """Texto de la funcion que contiene la posicion dada."""
    ini = txt.rfind('\nfunction ', 0, pos)
    if ini < 0:
        ini = txt.rfind('\n  function ', 0, pos)
    if ini < 0:
        return txt
    abre = txt.index('{', ini)
    nivel = 0
    for i in range(abre, len(txt)):
        if txt[i] == '{': nivel += 1
        elif txt[i] == '}':
            nivel -= 1
            if nivel == 0:
                return txt[ini:i + 1]
    return txt[ini:]

def revisar_js(archivo, tid, ancho):
    txt = (raiz/'assets/js'/archivo).read_text(encoding='utf-8')
    m = re.search(r'(?:const|let|var)\s+(\w+)\s*=\s*document\.getElementById\('
                  + f"'{tid}'" + r'\)', txt)
    if not m:
        avisos.append(f'{archivo}: no se hallo la variable de #{tid}')
        return

    var = m.group(1)
    cuerpo = cuerpo_de_la_funcion(txt, m.start())

    encontrados = 0
    for asig in re.finditer(re.escape(var) + r'\.innerHTML\s*=\s*', cuerpo):
        trozo = cuerpo[asig.end(): cuerpo.find(';', asig.end())]
        for c in re.findall(r'colspan="(\d+)"', trozo):
            encontrados += 1
            if int(c) != ancho:
                fallos.append(f'{archivo}: colspan {c} para #{tid}, que tiene {ancho}')
    print(f'  ok   {archivo}: {encontrados} colspan de #{tid} revisados')

revisar_js('paciente.js',  'tablaPacientes',      anchos['tablaPacientes'])
revisar_js('consultas.js', 'tablaPlanesAbiertos', anchos['tablaPlanesAbiertos'])
revisar_js('consultas.js', 'planTabla',           anchos['planTabla'])

# ---------- 5. Planes.fila arma el numero correcto de celdas ----------
comun = (raiz/'assets/js/comun.js').read_text(encoding='utf-8')
fila = comun[comun.index('function fila(p, i, idExpediente)'):]
fila = fila[:fila.index('return { fila };')]
op_i = fila.index("${idExpediente ? '' : `")
op_f = fila.index('</td>`}', op_i)
opcional = len(re.findall(r'<td\b', fila[op_i:op_f]))
total = len(re.findall(r'<td\b', fila))
fijas = total - opcional
if fijas != anchos['tablaPlanes']:
    fallos.append(f'Planes.fila: {fijas} celdas fijas y tablaPlanes tiene {anchos["tablaPlanes"]}')
if total != anchos['tablaPlanesAbiertos']:
    fallos.append(f'Planes.fila: {total} celdas y tablaPlanesAbiertos tiene {anchos["tablaPlanesAbiertos"]}')
print(f'  ok   Planes.fila: {fijas} celdas fijas + {opcional} (Paciente) = {total}')

# ---------- 6. la celda del importe que actualiza el JS ----------
cons_js = (raiz/'assets/js/consultas.js').read_text(encoding='utf-8')
m = re.search(r'fila\.children\[(\d+)\]\.textContent = App\.money', cons_js)
if m:
    idx = int(m.group(1))
    html = (raiz/'vistas/consultas/nuevo_plan_tratamiento.php').read_text(encoding='utf-8')
    i = html.index('id="planTabla"')
    ths = re.findall(r'<th[^>]*>(.*?)</th>', html[html.rfind('<thead',0,i):i], re.S)
    limpio = re.sub(r'<[^>]+>', '', ths[idx]).strip() if idx < len(ths) else '???'
    if 'Importe' not in limpio:
        fallos.append(f'children[{idx}] apunta a "{limpio}", no a Importe')
    else:
        print(f'  ok   children[{idx}] sigue siendo la columna Importe')

# ---------- 7. crearPendiente: todas las llamadas con la firma nueva ----------
docs = (raiz/'includes/core/documentos.php').read_text(encoding='utf-8')
if '?int    $idPlan = null' not in docs:
    fallos.append('crearPendiente no recibe el plan')
if 'id_plan_tratamiento <=> :pl' not in docs:
    fallos.append('la revision de documento unico no considera el plan')
print('  ok   crearPendiente reparte por plan')

# ---------- 8. consulta: folio, plan y documentos anidados ----------
cuerpo = esq[esq.index('CREATE TABLE consulta ('):]
cuerpo = cuerpo[:cuerpo.index(') ENGINE')]
for col in ('folio', 'id_plan_tratamiento'):
    if col not in cuerpo: fallos.append(f'consulta sin columna {col}')
if 'uq_consulta_folio' not in cuerpo: fallos.append('el folio de consulta no es unico')

cuerpo = esq[esq.index('CREATE TABLE documento ('):]
cuerpo = cuerpo[:cuerpo.index(') ENGINE')]
if 'id_consulta' not in cuerpo: fallos.append('documento sin columna id_consulta')
if 'ADD CONSTRAINT fk_doc_consulta' not in esq:
    fallos.append('falta el ALTER de fk_doc_consulta')
for t in ('nota_evolucion', 'receta'):
    if f'CREATE TABLE {t} (' not in esq: fallos.append(f'falta la tabla {t}')
print('  ok   consulta con folio, y notas y recetas en sus tablas')

# ---------- 9. el demo trae folio en cada consulta ----------
demo = (raiz/'db/03_datos_demo.sql').read_text(encoding='utf-8')
ins = demo[demo.index('INSERT INTO consulta ('):]
ins = ins[:ins.index(';')]
cols = ins[ins.index('(')+1:ins.index(')')].split(',')
tuplas = re.findall(r'\n\(([^\n]*)\),?', ins)
if 'folio' not in [c.strip() for c in cols]:
    fallos.append('el demo de consulta no trae folio')
folios = re.findall(r"'(CN-\d{4})'", ins)
if len(folios) != len(tuplas):
    fallos.append(f'demo consulta: {len(tuplas)} renglones y {len(folios)} folios')
if len(set(folios)) != len(folios):
    fallos.append('demo consulta: folios repetidos')
print(f'  ok   demo: {len(folios)} consultas, cada una con su folio')

# ---------- 10. los temas traen la paleta completa ----------
tema = (raiz/'includes/core/tema.php').read_text(encoding='utf-8')
bloques = re.findall(r"'(\w+)' => \[\s*'nombre'(.*?)\n        \]", tema, re.S)
tonos = ['c900','c800','c700','c600','c500','c400','a400','a300','a100','f50']
for clave, cuerpo in bloques:
    faltan = [t for t in tonos if f"'{t}'" not in cuerpo]
    if faltan: fallos.append(f'tema {clave}: le faltan los tonos {faltan}')
    malos = [c for c in re.findall(r"'#(\w+)'", cuerpo) if len(c) != 6]
    if malos: fallos.append(f'tema {clave}: colores mal escritos {malos}')
if len(bloques) < 2: fallos.append('no se encontraron los temas de color')
print(f'  ok   {len(bloques)} temas de color, con sus {len(tonos)} tonos')

# ---------- 11. el prellenado apunta a campos que existen ----------
hist = (raiz/'assets/js/historia.js').read_text(encoding='utf-8')
bloque = hist[hist.index('const HABITUAL = {'):]
bloque = bloque[:bloque.index('};')]
campos = re.findall(r'^\s*(\w+):', bloque, re.M)
vista = (raiz/'vistas/paciente/_tab_historia.php').read_text(encoding='utf-8')
declarados = set(re.findall(r"si[Nn]o\('(\w+)'", vista)) \
           | set(re.findall(r"opciones\('(\w+)'", vista))
faltan = [c for c in campos if c not in declarados]
if faltan: fallos.append(f'el prellenado apunta a campos que no existen: {faltan}')
print(f'  ok   prellenado: {len(campos)} campos, todos existen en la hoja')

# Y que esos campos esten en la tabla
tabla = esq[esq.index('CREATE TABLE historia_clinica ('):]
tabla = tabla[:tabla.index(') ENGINE')]
sinColumna = [c for c in campos if f'\n  {c} ' not in tabla and f'  {c}  ' not in tabla]
if sinColumna: fallos.append(f'campos del prellenado sin columna: {sinColumna}')
print('  ok   prellenado: todos tienen columna en historia_clinica')

print()
for a in avisos: print('  ~   ', a)
for f in fallos: print('  !!  ', f)
print()
print('*** TODO EN ORDEN ***' if not fallos else f'*** {len(fallos)} PROBLEMA(S) ***')
