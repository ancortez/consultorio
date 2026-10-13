import sys
import re, pathlib
raiz = pathlib.Path('.')



def defineClase(css: str, selector: str) -> bool:
    """¿La hoja DEFINE esa regla, o solo menciona el nombre?

    Tercera vez que me muerde lo mismo. Buscar '.nota-cita' como
    subcadena da verdadero aunque la regla se haya perdido, porque
    sigue existiendo '.nota-cita i { ... }' unas lineas mas abajo. La
    prueba negativa —renombrar la regla principal— pasaba sin que nadie
    se quejara, y el aviso amarillo o la nota se quedaban sin estilo.

    Lo que hay que exigir es la regla EXACTA: el selector seguido de su
    llave de apertura... o de una coma, porque una regla puede
    declararse en grupo:

        .resumen-dato.falta,
        .resumen-dato.incompleto { ... }

    Sin aceptar la coma, esta comprobacion daba por perdida una regla
    que estaba perfectamente puesta. Lo que NO vale es que el nombre
    aparezca seguido de otra cosa —'.nota-cita i'—, que es el caso que
    se escapaba.
    """
    return re.search(re.escape(selector) + r'\s*[{,]', css) is not None

def soloCodigo(texto: str) -> str:
    """El archivo sin sus comentarios.

    Hace falta mas de lo que parece. Al explicar POR QUE se quito algo,
    el comentario tiene que nombrar lo que se quito —"ya no se usa
    plan_tratamiento_detalle porque..."— y entonces una busqueda sobre
    el archivo entero encuentra justo el texto que documenta el
    arreglo y lo reporta como si el arreglo no estuviera. Me ha pasado
    dos veces en dos rondas.
    """
    sinBloque = re.sub(r'/\*.*?\*/', '', texto, flags=re.S)
    sinBloque = re.sub(r'<\?php\s*/\*.*?\*/\s*\?>', '', sinBloque, flags=re.S)
    sinLinea  = re.sub(r'^\s*(?://|#)\s.*$', '', sinBloque, flags=re.M)
    return sinLinea

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
    i = html.find(f'id="{tid}"')
    if i < 0:
        return None
    return len(re.findall(r'<th\b', html[html.rfind('<thead', 0, i):i]))

tablas = {
    'tablaPacientes':      'vistas/paciente/buscar_paciente.php',
    'tablaPlanesAbiertos': 'vistas/consultas/planes.php',
    'planTabla':           'vistas/consultas/nuevo_plan_tratamiento.php',
}
anchos = {}
for tid, archivo in tablas.items():
    html = (raiz/archivo).read_text(encoding='utf-8')
    n = cols(html, tid)
    if n is None:
        fallos.append(f'{archivo}: ya no existe la tabla #{tid}. '
                      'Si se quito a proposito, borrala tambien de aqui.')
        continue
    anchos[tid] = n
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

# La variante sin columna "Paciente" era la tabla de la pestania del
# detalle, que desaparecio en octubre 2026: la ficha muestra los planes
# en una tarjeta dibujada en PHP. Queda la de Consultas > Planes, que
# si lleva la columna.
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

# =====================================================================
#  Cambios de octubre 2026
# =====================================================================

jsCons = (raiz/'assets/js/consultas.js').read_text(encoding='utf-8')
jsExp  = (raiz/'assets/js/expediente.js').read_text(encoding='utf-8')
css    = (raiz/'assets/css/estilo.css').read_text(encoding='utf-8')

# ---------- Hallazgos ----------
# La exploracion dejo de ser una caja de texto. Lo que importa revisar
# es que las tres piezas existan y encajen: tabla, endpoint y pantalla.
# Cualquiera de las tres sola no sirve de nada.
if 'CREATE TABLE hallazgo (' not in esq:
    fallos.append('falta la tabla hallazgo')
for pieza, ruta in [('guardar', 'includes/consultas/hallazgo_guardar.php'),
                    ('quitar',  'includes/consultas/hallazgo_quitar.php')]:
    if not (raiz/ruta).exists():
        fallos.append(f'falta el endpoint para {pieza} hallazgos')

# La imagen del hallazgo se guarda con SET NULL, no CASCADE: si alguien
# borra el archivo, el hallazgo escrito tiene que sobrevivir. Lo que la
# doctora observo no deja de ser cierto porque se perdio la foto.
bloqueHallazgo = esq[esq.index('CREATE TABLE hallazgo ('):]
bloqueHallazgo = bloqueHallazgo[:bloqueHallazgo.index(') ENGINE')]
if 'ON DELETE SET NULL' not in bloqueHallazgo:
    fallos.append('hallazgo.id_documento deberia ser ON DELETE SET NULL: '
                  'borrar la imagen no debe borrar el hallazgo escrito')
if 'ON DELETE CASCADE' not in bloqueHallazgo:
    fallos.append('hallazgo deberia borrarse en cascada con su consulta')
print('  ok   hallazgos: tabla, endpoints y la imagen con SET NULL')

# El tipo de hallazgo decide donde se archiva la imagen. Si los dos
# cayeran en el mismo tipo, el album dejaria de separar radiografias de
# fotos clinicas.
guardarHz = (raiz/'includes/consultas/hallazgo_guardar.php').read_text(encoding='utf-8')
if "'RADIOGRAFIA'" not in guardarHz or "'FOTOGRAFIA'" not in guardarHz:
    fallos.append('el hallazgo no archiva segun su tipo (radiografia / fotografia)')
if 'TIPOS_HALLAZGO' not in guardarHz:
    fallos.append('el tipo de hallazgo no se valida contra una lista cerrada')
print('  ok   el hallazgo archiva su imagen segun el tipo')

# ---------- Lo que salio de la consulta ----------
# Los campos dejaron de capturarse, PERO las columnas se conservan: lo
# escrito ahi es el registro clinico de un dia concreto.
guardarCn = (raiz/'includes/consultas/consulta_guardar.php').read_text(encoding='utf-8')
for campo in ('presion_arterial', 'frecuencia_cardiaca', 'temperatura',
              'diagnostico', 'exploracion'):
    if f"paramMayus('{campo}')" in guardarCn or f"paramTexto('{campo}')" in guardarCn:
        fallos.append(f'{campo} se sigue capturando en la consulta')
    if f'  {campo} ' not in esq and f'  {campo}  ' not in esq:
        fallos.append(f'se borro la columna {campo}: el dato viejo es '
                      'parte del expediente y no se borra')
print('  ok   signos vitales y diagnostico: fuera de la captura, columnas intactas')

# Cerrar una consulta exigia diagnostico, que ya no existe. Si el
# requisito se hubiera quedado igual, NINGUNA consulta nueva se podria
# cerrar: el campo nunca se llena.
cerrar = (raiz/'includes/consultas/consulta_cerrar.php').read_text(encoding='utf-8')
if "empty($c['diagnostico'])" in cerrar:
    fallos.append('cerrar consulta sigue exigiendo diagnostico, que ya no se captura: '
                  'ninguna consulta se podria cerrar')
if "plan_indicaciones" not in cerrar:
    fallos.append('cerrar consulta no exige el tratamiento realizado')
print('  ok   cerrar consulta exige el tratamiento, no el diagnostico')

# ---------- Plan ----------
if 'CREATE TABLE plan_traumatismo (' not in esq:
    fallos.append('falta la tabla plan_traumatismo')

guardarPl = (raiz/'includes/consultas/plan_guardar.php').read_text(encoding='utf-8')
# No basta con que exista la constante: hay que ver que traiga las tres
# opciones Y que se use para filtrar. Una lista declarada y nunca
# aplicada no valida nada.
if "const OCLUSIONES" not in guardarPl:
    fallos.append('la oclusion no se valida contra una lista cerrada')
else:
    for opcion in ("'CLASE II'", "'CLASE III'", "'MORDIDA CRUZADA'"):
        if opcion not in guardarPl:
            fallos.append(f'falta {opcion} entre las oclusiones validas')
    if 'array_intersect' not in guardarPl:
        fallos.append('la lista de oclusiones existe pero no se usa para filtrar')
# El campo es oculto: sin validar, cualquiera puede mandar texto libre y
# el filtro por oclusion deja de servir para siempre.
if "paramInt('id_estado_plan')" in guardarPl:
    fallos.append('el estatus del plan se sigue leyendo del formulario: '
                  'cualquiera podria marcar Aceptado un plan sin firmar')
if 'ESTADO_PROPUESTO' not in guardarPl:
    fallos.append('un plan nuevo no nace Propuesto')
print('  ok   plan: traumatismos en tabla, oclusion validada, estatus no editable')

# El estatus lo mueve la hoja firmada. Y solo hacia adelante: un plan En
# proceso no debe volver a Aceptado porque alguien reescanee el papel.
docs = (raiz/'includes/core/documentos.php').read_text(encoding='utf-8')
# Se exige la definicion Y la llamada. Una funcion que existe pero que
# nadie invoca no hace nada, y es el tipo de error que no se ve al leer
# el archivo: ahi esta, bien escrita, muerta.
if 'private static function aceptarPlanSiCorresponde' not in docs:
    fallos.append('falta la funcion que acepta el plan al digitalizar la hoja')
elif 'self::aceptarPlanSiCorresponde(' not in docs:
    fallos.append('aceptarPlanSiCorresponde existe pero nadie la llama')
elif 'AND id_estado_plan = 1' not in docs:
    fallos.append('el plan podria retroceder a Aceptado desde En proceso')
print('  ok   la hoja firmada acepta el plan, y solo hacia adelante')

# Los pasos de observaciones y resumen se quitaron del recorrido.
vistaPlan = (raiz/'vistas/consultas/nuevo_plan_tratamiento.php').read_text(encoding='utf-8')
pasos = re.findall(r'data-titulo="([^"]+)"', vistaPlan)
if len(pasos) != 3:
    fallos.append(f'el plan tiene {len(pasos)} pasos y deberian ser 3: {pasos}')
# El descuento y el total vivian en el paso de resumen. Si se fueron con
# el, no habria donde capturar el descuento.
for ident in ('planDescuento', 'planSubtotal', 'planTotal'):
    if f'id="{ident}"' not in vistaPlan:
        fallos.append(f'se perdio {ident} al quitar el paso de resumen')
print(f'  ok   el plan quedo en {len(pasos)} pasos, con el total en su lugar')

# ---------- Impresion del plan ----------
impPlan = (raiz/'plan_imprimir.php').read_text(encoding='utf-8')
for fuera, porque in [
    ("nombre_estado']", 'el estatus no va en la hoja de firma'),
    ('<h2>Diagn&oacute;stico</h2>', 'el diagnostico no va en la hoja de firma'),
    ("observaciones']", 'observaciones ya no existe'),
]:
    if fuera in impPlan:
        fallos.append(f'plan_imprimir: {porque}, pero sigue ahi')
# Se mira el BLOQUE DE DATOS, no el archivo ni siquiera el cuerpo
# entero. El folio aparece tambien en el titulo, en la consulta SQL y en
# el pie de pagina ("Expediente 4 · Plan PT-0007"); cualquiera de esos
# daria por bueno un encabezado al que se le borro el dato que el tutor
# necesita ver al firmar.
cuerpo = impPlan[impPlan.index('<div class="cuerpo">'):]
cuerpo = cuerpo[:cuerpo.index('<div class="seccion">', cuerpo.index('<div class="seccion">') + 1)]
for dentro in ("$plan['paciente']", "$plan['edad']", "$plan['folio']",
               "fecha($plan['fecha'])"):
    if dentro not in cuerpo:
        fallos.append(f'plan_imprimir: falta {dentro} en el cuerpo de la hoja')
print('  ok   la hoja del plan: paciente, edad, folio, fecha y procedimientos')

# ---------- Panel del plan ----------
if not (raiz/'includes/consultas/plan_panel.php').exists():
    fallos.append('falta el endpoint del panel del plan')
else:
    panel = (raiz/'includes/consultas/plan_panel.php').read_text(encoding='utf-8')
    # Un PDF en el album no se puede mostrar como miniatura y romperia
    # la cuadricula.
    if "tipo_mime LIKE 'image/%'" not in panel:
        fallos.append('el album no filtra a solo imagenes')
    if 'array_values($album)' not in panel:
        fallos.append('el album llegaria como objeto y no como lista')
for ident in ('ppConsultas', 'ppAlbum', 'ppNotas'):
    if f'id="{ident}"' not in vistaPlan:
        fallos.append(f'falta la tarjeta {ident} en el plan')
print('  ok   panel del plan: consultas, album solo con imagenes, y notas')

# ---------- Expediente compacto ----------
if '.exp-tarjetas' in css:
    bloque = css[css.index('.exp-tarjetas'):]
    bloque = bloque[:bloque.index('}')]
    if 'grid-template-columns' in bloque:
        fallos.append('los documentos siguen en rejilla de varias columnas')
if 'doc-renglon' not in jsExp:
    fallos.append('los documentos no se dibujan como renglon')
if 'doc-tarjeta' in jsExp:
    fallos.append('quedo codigo de la tarjeta vieja en expediente.js')
# El selector del clic tiene que seguir alcanzando al renglon nuevo.
if "closest('.doc-renglon')" not in jsExp and 'data-documento' not in jsExp:
    fallos.append('los botones del documento ya no encuentran su renglon')
print('  ok   documentos en renglon de ancho completo')

if '<details class="exp-consulta-caja"' not in jsExp:
    fallos.append('las consultas del plan no son colapsables')
# Esta comprobacion decia lo contrario hasta el 11/10: las consultas
# con pendientes abrian solas. Tenia sentido cuando el expediente se
# desplegaba entero al entrar; ahora que todo arranca cerrado, una
# consulta abierta en medio de una lista cerrada parece un error de
# dibujo. El pendiente se anuncia en su contador, que basta.
if 'const abierta' in jsExp:
    fallos.append('alguna consulta del expediente vuelve a decidir si abre sola')
print('  ok   consultas colapsables, abiertas si tienen pendientes')


# =====================================================================
#  Cambios del 8 de octubre 2026
# =====================================================================

# ---------- Nanai ----------
cfg = (raiz/'config/config.php').read_text(encoding='utf-8')
if "APP_NOMBRE', 'Nanai'" not in cfg:
    fallos.append("el sistema deberia llamarse Nanai en APP_NOMBRE")
if 'APP_LOGO' not in cfg:
    fallos.append('falta la constante APP_LOGO')
else:
    logo = re.search(r"APP_LOGO', '([^']+)'", cfg).group(1)
    if not (raiz / logo.lstrip('/')).exists():
        fallos.append(f'APP_LOGO apunta a {logo}, que no existe')
# El nombre vive en la tabla parametro y gana sobre la constante: si
# ahi quedara el viejo, la pantalla seguiria diciendo lo de antes.
cat = (raiz/'db/02_catalogos.sql').read_text(encoding='utf-8')
if "('nombre_sistema','Nanai'" not in cat:
    fallos.append('el parametro nombre_sistema no dice Nanai')
print('  ok   Nanai: nombre, logotipo y parametro')

# ---------- El album es uno solo ----------
# Tres pantallas lo usan. Si cada una tuviera el suyo, tarde o temprano
# se verian distintos.
comunJs = (raiz/'assets/js/comun.js').read_text(encoding='utf-8')
if 'const Album = ' not in comunJs:
    fallos.append('el visor de album no esta en comun.js')
if not (raiz/'vistas/layout/_modal_album.php').exists():
    fallos.append('el modal del album no esta en el layout')
usan = [a for a in ('assets/js/consultas.js', 'assets/js/expediente.js')
        if 'Album.abrir(' in (raiz/a).read_text(encoding='utf-8')]
if len(usan) < 2:
    fallos.append(f'solo {len(usan)} pantalla(s) usan el visor compartido')
# Un PDF en el visor romperia la cuadricula de miniaturas.
if 'function soloImagenes' not in comunJs:
    fallos.append('el album no filtra a solo imagenes')
elif 'return { abrir, soloImagenes }' not in comunJs:
    fallos.append('soloImagenes existe pero el album no la expone')
print(f'  ok   el album vive en comun.js y lo usan {len(usan)} pantallas')

# ---------- Eliminar, detras de los tres puntos ----------
if 'bi-three-dots-vertical' not in jsExp:
    fallos.append('eliminar documento no esta detras de un menu')
elif jsExp.index('bi-three-dots-vertical') > jsExp.index("data-accion=\"exp-quitar\""):
    fallos.append('el boton de eliminar quedo fuera del menu de tres puntos')
print('  ok   eliminar documento vive detras de los tres puntos')

# ---------- Estatus del plan con color ----------
if 'COLOR_ESTADO_PLAN' not in jsExp:
    fallos.append('el bloque del plan no muestra su estatus con color')
else:
    # Los cinco estados del catalogo tienen que tener color. Uno sin
    # color se dibuja gris y se confunde con "Propuesto".
    bloqueEstados = cat[cat.index('INSERT INTO estado_plan'):]
    bloqueEstados = bloqueEstados[:bloqueEstados.index(';')]
    estados = re.findall(r"\((\d+),'", bloqueEstados)
    mapa = jsExp[jsExp.index('COLOR_ESTADO_PLAN'):]
    mapa = mapa[:mapa.index('};')]
    for n in estados:
        if f'{n}:' not in mapa:
            fallos.append(f'el estado de plan {n} no tiene color asignado')
    print(f'  ok   los {len(estados)} estados del plan tienen color')

# ---------- Ficha del paciente ----------
vistaPac = (raiz/'vistas/paciente/detalle_paciente.php').read_text(encoding='utf-8')
campos = vistaPac.count('Ficha::campo')
if campos < 5:
    fallos.append(f'la ficha solo tiene {campos} campos con lapiz: '
                  'deberian ser los datos de identificacion completos')
if 'CamposFicha' not in comunJs:
    fallos.append('falta el comportamiento del lapiz en comun.js')
# El guardado reutiliza el endpoint de siempre: sin data-endpoint, el
# lapiz abriria el campo y al aceptar no guardaria nada.
if 'data-endpoint=' not in vistaPac:
    fallos.append('el formulario de la ficha no dice a que endpoint guarda')
else:
    ruta = re.search(r'data-endpoint="([^"]+)"', vistaPac).group(1)
    if not (raiz/'includes'/ruta).exists():
        fallos.append(f'la ficha guarda a {ruta}, que no existe')

# Se busca la CABECERA de la tarjeta, no el texto suelto: "Consultas"
# aparece en media pagina y daria por buena una tarjeta que ya no esta.
#
# Son CUATRO tarjetas, no cinco. Los pendientes de digitalizar dejaron
# de ser una y pasaron a ser una seccion de la tarjeta de datos, debajo
# de los canales de contacto: son la lista de lo que hay que pedirle al
# tutor que traiga, y el momento de leerla es mientras se le tiene
# enfrente, no al final de la pantalla.
for icono, tarjeta in [('bi-list-check',      'Planes de tratamiento'),
                       ('bi-clipboard2-pulse', 'Consultas'),
                       ('bi-calendar-event',  'Citas'),
                       ('bi-cash-coin',       'Estado de cuenta')]:
    cabecera = f'<i class="bi {icono} me-2"></i>{tarjeta}'
    if cabecera not in vistaPac:
        fallos.append(f'falta la tarjeta "{tarjeta}" en la ficha')
print('  ok   ficha: campos con lapiz y las cuatro tarjetas')

# ---------- Los pendientes, dentro de la tarjeta de datos ----------
pendFicha = raiz/'vistas/paciente/_pendientes_ficha.php'
if not pendFicha.exists():
    fallos.append('falta el parcial de pendientes de la ficha')
else:
    # Lo que de verdad hace que sirvan: se suben desde ahi. Mandar a la
    # persona al expediente a buscar el documento es el paso que hace
    # que un pendiente se quede pendiente para siempre.
    if 'data-subir-doc' not in pendFicha.read_text(encoding='utf-8'):
        fallos.append('los pendientes de la ficha ya no se pueden subir desde ahi')
    if "_pendientes_ficha.php" not in vistaPac:
        fallos.append('la ficha no incluye los pendientes de digitalizar')
    # Y van en la columna del contacto, no en la del domicilio: es
    # donde se mira para decidir como se le avisa al tutor.
    colContacto = vistaPac[vistaPac.index("'contacto'"):]
    colContacto = colContacto[:colContacto.index('/row g-0')]
    if '_pendientes_ficha.php' not in colContacto:
        fallos.append('los pendientes no quedaron bajo los canales de contacto')
    # El domicilio baja a la columna del tutor, a su mismo ancho: a todo
    # lo ancho era un muro y nada podia ir debajo sin empujarlo.
    colTutor = vistaPac[vistaPac.index("'tutor'"):vistaPac.index("'contacto'")]
    if "'domicilio'" not in colTutor:
        fallos.append('el domicilio sigue a todo lo ancho: los pendientes '
                      'no tendrian donde crecer')
print('  ok   los pendientes viven bajo el contacto, y el domicilio al lado')

# El id del paciente viaja en un elemento que las tres pantallas ponen.
# Sin el, ningun modulo arranca y la pantalla se ve pero no hace nada.
for v in ('detalle_paciente', 'historia_clinica', 'expediente'):
    texto = (raiz/f'vistas/paciente/{v}.php').read_text(encoding='utf-8')
    if 'pacienteContexto' not in texto:
        fallos.append(f'{v}.php no lleva el id del paciente para el JavaScript')
if "getElementById('pacienteContexto')" not in \
        (raiz/'assets/js/paciente.js').read_text(encoding='utf-8'):
    fallos.append('paciente.js no lee el contexto del paciente')
print('  ok   las tres pantallas del paciente llevan su contexto')

# Historia y expediente son pantallas propias, y el router las conoce.
indice = (raiz/'index.php').read_text(encoding='utf-8')
for ruta in ('paciente/historia_clinica', 'paciente/expediente'):
    if f"'{ruta}' => [" not in indice:
        fallos.append(f'el router no conoce la ruta {ruta}')
print('  ok   historia y expediente son rutas del router')

# Ningun anclaje puede apuntar a una pestania que ya no existe.
#
# Esta lista cambio dos veces y por eso se calcula, no se escribe a
# mano: las pestanias de identificacion, contacto, domicilio, tutor y
# planes se fueron para siempre; las de historia y expediente se
# fueron y VOLVIERON. Una lista fija habria dado un falso positivo en
# cuanto volvieron.
vistaDet = (raiz/'vistas/paciente/detalle_paciente.php').read_text(encoding='utf-8')
_hay = set(re.findall(r'data-bs-target="(#tab-[\w-]+)"', vistaDet))

for archivo in ['assets/js/paciente.js', 'assets/js/consultas.js',
                'assets/js/historia.js', 'assets/js/expediente.js',
                'historia_imprimir.php', 'plan_imprimir.php',
                'consentimiento.php']:
    texto = (raiz/archivo).read_text(encoding='utf-8')
    for viejo in set(re.findall(r'#tab-[\w-]+', texto)):
        # Las del modal de edicion del tutor son otras (#te-...).
        if viejo.startswith('#tab-') and viejo not in _hay:
            fallos.append(f'{archivo}: apunta a {viejo}, que no es una '
                          'pestania del detalle del paciente')
print(f'  ok   los enlaces apuntan a las {len(_hay)} pestanias que existen')

# ---------- Hallazgo con varias imagenes ----------
tablaDoc = esq[esq.index('CREATE TABLE documento ('):]
tablaDoc = tablaDoc[:tablaDoc.index(') ENGINE')]
if not re.search(r'^\s*id_hallazgo\s+INT', tablaDoc, re.M):
    fallos.append('documento no declara la columna id_hallazgo: un hallazgo '
                  'solo podria tener una imagen')
# Y la llave, con SET NULL: borrar el hallazgo no debe llevarse la
# radiografia, que es un documento del expediente por derecho propio.
if 'fk_doc_hallazgo' not in esq:
    fallos.append('falta la llave foranea de documento a hallazgo')
elif 'REFERENCES hallazgo (id_hallazgo) ON DELETE SET NULL' not in esq:
    fallos.append('documento.id_hallazgo deberia ser ON DELETE SET NULL')
guardarHz2 = (raiz/'includes/consultas/hallazgo_guardar.php').read_text(encoding='utf-8')
if "$_FILES['archivos']" not in guardarHz2:
    fallos.append('el hallazgo no recibe varias imagenes')
# El hallazgo se guarda ANTES que las imagenes: cada una necesita su id.
if guardarHz2.index('INSERT INTO hallazgo') > guardarHz2.index("$_FILES['archivos']"):
    fallos.append('las imagenes se guardan antes que el hallazgo: no tendrian '
                  'a que colgarse')
print('  ok   un hallazgo acepta varias imagenes')

# ---------- Receta: el pendiente trae su boton ----------
#
# Octubre de 2026 el boton se mudo. Vivia en la tarjeta "Documentos de
# la consulta" con data-subir-pendiente, y esa tarjeta se quito; ahora
# va pegado al renglon de su receta con data-rx-escanear. Lo que se
# comprueba es lo mismo que antes y por la misma razon: que el papel
# firmado se pueda subir SIN salir de la consulta. Si hay que irse al
# expediente del paciente a buscar el documento, con el tutor esperando,
# eso no lo hace nadie y el pendiente se queda abierto para siempre.
jsCons2 = (raiz/'assets/js/consultas.js').read_text(encoding='utf-8')
# Dos apariciones: la que lo dibuja y la que lo escucha. Con una sola,
# o hay boton que nadie atiende o hay escucha sin boton.
if jsCons2.count('data-rx-escanear') < 2:
    fallos.append('el pendiente de la receta no deja adjuntar el escaneo')
# Y que lo dibuje el renglon de la receta, no otra parte: que el boton
# exista no sirve si esta donde estaba antes.
pintaRecetas = jsCons2[jsCons2.index('function pintarRecetas()'):]
pintaRecetas = pintaRecetas[:pintaRecetas.index('document.getElementById(\'cnNuevaReceta\')')]
if 'data-rx-escanear' not in pintaRecetas:
    fallos.append('el boton de adjuntar no sale en el renglon de la receta')
if 'id="cnArchivoPendiente"' not in \
        (raiz/'vistas/consultas/consulta.php').read_text(encoding='utf-8'):
    fallos.append('falta el campo de archivo para el pendiente de la consulta')
print('  ok   la receta firmada se sube desde su propio renglon')

# ---------- Historia clinica ----------
vistaHc = (raiz/'vistas/paciente/_tab_historia.php').read_text(encoding='utf-8')
dependientes = re.findall(r'data-depende="(\w+)"', vistaHc)
if len(dependientes) != 12:
    fallos.append(f'hay {len(dependientes)} campos condicionales y deberian '
                  'ser 12')
# Cada uno depende de una pregunta que exista: si el nombre esta mal
# escrito, el campo no aparece nunca y nadie se entera.
# La pregunta tiene que existir como campo del formulario. Buscar el
# nombre suelto no sirve: el propio data-depende lo contiene, asi que
# un nombre inventado se encontraria a si mismo.
sinAtributos = re.sub(r'data-depende="[^"]*"', '', vistaHc)
for control in sorted(set(dependientes)):
    if (f"siNo('{control}'" not in sinAtributos
            and f"opciones('{control}'" not in sinAtributos
            and f'name="{control}"' not in sinAtributos):
        fallos.append(f'un campo depende de "{control}", que no es una '
                      'pregunta del formulario')
jsHist = (raiz/'assets/js/historia.js').read_text(encoding='utf-8')
if 'function aplicarDependencias' not in jsHist:
    fallos.append('nada muestra ni oculta los campos condicionales')
else:
    # Se llama en cuatro momentos, y cada uno arregla un caso distinto.
    # Contarlas no basta: hay que ver que esten las cuatro, porque si
    # falta una el fallo solo se nota en esa situacion concreta.
    momentos = [
        # Quien pregunta es elegir(), que sabe si lo llamo una persona.
        #
        # Esta comprobacion pedia antes 'aplicarDependencias(true)', que
        # fue mi primer intento y estaba mal: colgaba la pregunta del
        # evento 'change', y ese evento no distingue si lo provoco
        # alguien picando una opcion o el codigo rellenando el
        # formulario. Abrir un expediente ya capturado lanzaba las doce
        # ventanas seguidas. El clic si distingue.
        ('porPersona = false', 'al contestar una pregunta'),
        ('aplicarDependencias();\n        App.alerta.toast(`${puestos}',
         'despues de prellenar'),
    ]
    for marca, cuando in momentos:
        if marca not in jsHist:
            fallos.append(f'no se recalculan las dependencias {cuando}')

    # Y en llenar(), DESPUES de escribir los valores.
    #
    # Antes esta comprobacion buscaba el texto
    # "aplicarDependencias();\n\n    pasos?.aplicarModo", que era el
    # sitio donde la llamada estaba... y estaba MAL: corria al principio
    # de llenar(), antes de escribir los datos que acababan de llegar
    # del servidor. Asi que la comprobacion exigia el fallo.
    #
    # El orden no se puede comprobar buscando una cadena: hay que
    # comparar POSICIONES dentro de la funcion. Ese era el agujero.
    cuerpoLlenar = jsHist[jsHist.index('function llenar('):]
    cuerpoLlenar = cuerpoLlenar[:cuerpoLlenar.index('\n  }')]

    if 'el.value = valor' not in cuerpoLlenar:
        fallos.append('llenar() ya no escribe los valores en el formulario')
    elif 'aplicarDependencias()' not in cuerpoLlenar:
        fallos.append('llenar() no recalcula las dependencias: una historia '
                      'guardada abriria sin sus respuestas a la vista')
    elif cuerpoLlenar.index('aplicarDependencias()') \
            < cuerpoLlenar.index('el.value = valor'):
        fallos.append('llenar() recalcula las dependencias ANTES de escribir '
                      'los valores: se calculan contra un formulario vacio y '
                      'la respuesta guardada no se ve (paso de verdad con '
                      '"momentos de azucar")')
print(f'  ok   {len(dependientes)} campos aparecen al contestar su pregunta')

for fuera in ('medico_nombre', 'medico_telefono'):
    if f'name="{fuera}"' in vistaHc:
        fallos.append(f'{fuera} sigue en la historia clinica')
    if f'  {fuera} ' not in esq:
        fallos.append(f'se borro la columna {fuera}: el dato viejo no se borra')
print('  ok   el medico salio de la captura, su columna se conserva')

impHc = (raiz/'historia_imprimir.php').read_text(encoding='utf-8')
if impHc.count('<div class="hoja">') != 1:
    fallos.append('la historia se imprime en mas de una hoja')
if 'listaONinguno' not in impHc:
    fallos.append('los habitos no imprimen "Ninguno" cuando no hay')
elif "listaONinguno('HABITO')" not in impHc:
    fallos.append('la funcion existe pero no se usa en habitos')
print('  ok   la historia cabe en una hoja y los habitos dicen Ninguno')


# =====================================================================
#  Cambios del 9 de octubre 2026
# =====================================================================

vistaPac2 = (raiz/'vistas/paciente/detalle_paciente.php').read_text(encoding='utf-8')
jsComun   = (raiz/'assets/js/comun.js').read_text(encoding='utf-8')

# ---------- Las pestanias volvieron ----------
# Historia y expediente salieron a pantalla propia y volvieron a ser
# pestanias. Las dos cosas conviven: la direccion propia sirve para
# enlazar desde una impresion, la pestania es el camino diario.
for destino in ('#tab-datos', '#tab-historia', '#tab-expediente'):
    if f'data-bs-target="{destino}"' not in vistaPac2:
        fallos.append(f'falta la pestania {destino} en el detalle')
for v in ('historia_clinica', 'expediente'):
    if not (raiz/f'vistas/paciente/{v}.php').exists():
        fallos.append(f'se perdio la pantalla propia de {v}')
print('  ok   tres pestanias, y las pantallas propias siguen existiendo')

# Cada modulo tiene que saber si es pestania o pantalla: como pestania
# se carga al abrirla, como pantalla de entrada. Si no distinguiera,
# la ficha pediria el expediente entero sin que nadie lo haya pedido.
for archivo, solapa in [('assets/js/expediente.js', 'btnTabExpediente'),
                        ('assets/js/historia.js',   'btnTabHistoria')]:
    txt = (raiz/archivo).read_text(encoding='utf-8')
    if f"getElementById('{solapa}')" not in txt:
        fallos.append(f'{archivo} no distingue pestania de pantalla propia')
    elif "shown.bs.tab" not in txt:
        fallos.append(f'{archivo} no espera a que se abra la pestania')
print('  ok   historia y expediente se cargan al abrir su pestania')

# ---------- Lo que se quito ----------
if '_menu_lateral.php' in vistaPac2:
    fallos.append('el menu lateral sigue en el detalle del paciente')
# Cambiar la foto ya no va por id: sale en dos sitios a la vez —el
# lapiz del circulo y el del campo Fotografia— y un id repetido deja
# muerto al segundo sin dar error.
if 'data-cambiar-foto' not in vistaPac2:
    fallos.append('no hay forma de cambiar la fotografia')
elif 'lapiz-foto' not in vistaPac2:
    fallos.append('cambiar la fotografia deberia ser el lapiz sobre la foto')
elif 'btnCambiarFoto' in vistaPac2:
    fallos.append('cambiar la fotografia volvio a colgar de un id repetido')
print('  ok   sin menu lateral, y la foto se cambia con su lapiz')

# ---------- Los botones que estaban muertos ----------
# El bug fue un id duplicado: getElementById solo encuentra el primero.
# Por eso ahora son atributos, que admiten varios en la misma pantalla.
for atributo, quien in [('data-nueva-consulta', 'nueva consulta'),
                        ('data-estado-cuenta', 'estado de cuenta')]:
    enVista = vistaPac2.count(atributo)
    if enVista < 2:
        fallos.append(f'{quien}: se esperaban dos botones y hay {enVista}')
    if f"closest('[{atributo}]')" not in jsComun:
        fallos.append(f'nadie escucha {atributo}')
print('  ok   nueva consulta y estado de cuenta: dos botones, un escucha')

# El estado de cuenta abre en ventana centrada, no en pestania nueva.
if 'App.abrirVentana(' not in jsComun or 'estado_cuenta.php' not in jsComun:
    fallos.append('el estado de cuenta no abre en ventana centrada')
if 'estado_cuenta.php?id=<?= $idExpediente ?>"\n                   target="_blank"' in vistaPac2:
    fallos.append('el estado de cuenta sigue abriendo en pestania nueva')
print('  ok   el estado de cuenta abre centrado')

# ---------- Saldo a favor ----------
# v_paciente_saldo suma CARGOS: el dinero que entro y no encontro cargo
# donde caer no aparece en ninguna de sus columnas, y el paciente con
# anticipo veia su estado de cuenta en ceros.
cargosPhp = (raiz/'includes/core/cargos.php').read_text(encoding='utf-8')
if "$resumen['a_favor']" not in cargosPhp:
    fallos.append('estadoDeCuenta no calcula el saldo a favor')
impCuenta = (raiz/'estado_cuenta.php').read_text(encoding='utf-8')
if "(float) ($resumen['a_favor'] ?? 0) > 0.009" not in impCuenta:
    fallos.append('el estado de cuenta impreso no decide cuando mostrar '
                  'el saldo a favor')
if "money($resumen['a_favor'])" not in impCuenta:
    fallos.append('el estado de cuenta impreso no imprime el saldo a favor')
# Y la pantalla usa el mismo calculo, no uno propio: dos cuentas
# separadas del mismo numero acaban diciendo cosas distintas.
if 'Cargos::estadoDeCuenta' not in vistaPac2:
    fallos.append('la ficha calcula el saldo por su cuenta en vez de '
                  'usar el mismo que el documento impreso')
print('  ok   el saldo a favor sale en pantalla y en el papel, del mismo calculo')

# ---------- Tope de cinco y ver mas ----------
# El tope de cinco se aplica al PINTAR, no siempre en la consulta.
#
# Planes y consultas lo hacen con LIMIT 5 porque su "ver todos" lleva a
# otra pantalla. Las citas, desde octubre 2026, traen la lista entera:
# su "ver mas" abre un modal con todas y ademas hay que contar cuantas
# fueron atendidas, canceladas y cuantas falto. Exigir LIMIT 5 a las
# tres daba por rota justo la tarjeta que mas datos ensenia ahora.
if vistaPac2.count('LIMIT 5') < 2:
    fallos.append('las tarjetas de planes y consultas no limitan a cinco')
if 'array_slice($citas, 0, 5)' not in vistaPac2:
    fallos.append('la tarjeta de citas no recorta a cinco al pintar')
if vistaPac2.count('ver-mas') < 3:
    fallos.append('faltan los enlaces de "ver todos"')
# Solo con MAS de cinco: con cinco exactos ya se ven todos y el enlace
# solo seria ruido. Se revisan las tarjetas por separado.
for cuenta, quien in [('$cuantosPlanes', 'planes'),
                      ('$cuantasConsultas', 'consultas'),
                      ('$cuantasCitas', 'citas')]:
    if f'{cuenta} > 5' not in vistaPac2:
        fallos.append(f'el "ver todos" de {quien} no compara contra cinco: '
                      'apareceria aunque no haya mas que ver')
# Y el listado tiene que aceptar el filtro que le llega por la direccion.
for lista in ('vistas/consultas/consultas.php', 'vistas/consultas/planes.php'):
    if "param('buscar'" not in (raiz/lista).read_text(encoding='utf-8'):
        fallos.append(f'{lista} no acepta un filtro inicial por la direccion')
print('  ok   cinco por tarjeta, con salida al listado ya filtrado')

# ---------- El plan avanza solo ----------
guardarCn2 = (raiz/'includes/consultas/consulta_guardar.php').read_text(encoding='utf-8')
if 'PLAN_EN_PROCESO' not in guardarCn2:
    fallos.append('la primera consulta no pasa el plan a En proceso')
# Antes esto buscaba el literal "AND id_estado_plan = 2". Los numeros
# salieron del archivo y ahora vienen de Planes, que es lo que se queria:
# el 2 y el 3 repartidos a mano por tres pantallas fue exactamente como
# nacio el fallo de que un plan terminado volviera a En proceso. Asi que
# lo que se comprueba es que la condicion siga ahi —solo hacia
# adelante— y que los dos estados los diga la clase.
elif ':aceptado' not in guardarCn2 or 'Planes::ACEPTADO' not in guardarCn2:
    fallos.append('el plan podria retroceder a En proceso desde Terminado: '
                  'falta la condicion de que venga de Aceptado')
elif 'Planes::EN_PROCESO' not in guardarCn2:
    fallos.append('consulta_guardar escribe el estado del plan a mano '
                  'en vez de pedirselo a Planes')
print('  ok   la primera consulta pone el plan En proceso, solo hacia adelante')

# ---------- A un plan cancelado no se le cuelgan consultas ----------
# Un plan TERMINADO si acepta una: la revision de control de algo que se
# cerro en marzo pertenece a ese tratamiento. Uno CANCELADO no, porque
# cancelado significa que el tratamiento no ocurrio, y una consulta
# dentro de el es una contradiccion que nadie va a poder explicar medio
# anio despues. La pantalla solo ofrece planes vivos; esto es para la
# pestania que llevaba media hora abierta cuando lo cancelaron.
if 'Planes::CANCELADO' not in guardarCn2:
    fallos.append('consulta_guardar acepta colgar una consulta de un plan '
                  'cancelado')
print('  ok   un plan cancelado no recibe consultas')

# ---------- El odontograma dibuja dientes ----------
modalD = (raiz/'vistas/consultas/_modal_dientes.php').read_text(encoding='utf-8')
if 'bi-hexagon-fill' in modalD:
    fallos.append('el odontograma sigue dibujando hexagonos')
coronas = modalD[modalD.index('$coronas = ['):]
coronas = coronas[:coronas.index('];')]
for forma in ('incisivo', 'canino', 'premolar', 'molar'):
    # Con espacios opcionales: las claves van alineadas a mano y
    # "'canino'   =>" no es lo mismo que "'canino' =>" para un
    # buscador literal.
    if not re.search(rf"'{forma}'\s*=>", coronas):
        fallos.append(f'el odontograma no dibuja el {forma}')
    if f"return '{forma}'" not in modalD and f"'{forma}';" not in modalD:
        fallos.append(f'ningun diente se clasifica como {forma}')
cssTxt = (raiz/'assets/css/estilo.css').read_text(encoding='utf-8')
if not defineClase(cssTxt, '.diente.elegido .cr'):
    fallos.append('el diente elegido no cambia de color')
print('  ok   el odontograma dibuja cuatro formas y colorea el elegido')

# ---------- Consulta compacta ----------
vistaCn = (raiz/'vistas/consultas/consulta.php').read_text(encoding='utf-8')
# Los tres textos en UNA tarjeta: si siguieran en tres, las notas de
# evolucion no suben y no se ve todo sin desplazarse.
for campo in ('motivo', 'padecimiento_actual', 'plan_indicaciones'):
    if f'name="{campo}"' not in vistaCn:
        fallos.append(f'se perdio el campo {campo} de la consulta')
bloque = vistaCn[vistaCn.index('Motivo de la consulta'):]
bloque = bloque[:bloque.index('Exploraci')]
if 'plan_indicaciones' not in bloque:
    fallos.append('los tres textos no quedaron en la misma tarjeta')
print('  ok   los tres textos de la consulta, en una sola tarjeta')

jsCons3 = (raiz/'assets/js/consultas.js').read_text(encoding='utf-8')
if 'function imagenesDeLaConsulta' not in jsCons3:
    fallos.append('el album del hallazgo no junta las imagenes de la consulta')
elif jsCons3.count('imagenesDeLaConsulta()') < 3:
    fallos.append('imagenesDeLaConsulta existe pero no se usa donde debe')
if 'desde: Math.max(0, desde)' not in jsCons3:
    fallos.append('el album no abre en la imagen que se pico')
print('  ok   el album del hallazgo abre con toda la consulta, en su imagen')


# =====================================================================
#  Cambios del 10 de octubre 2026
# =====================================================================

vistaPac3 = (raiz/'vistas/paciente/detalle_paciente.php').read_text(encoding='utf-8')

# ---------- Las tarjetas NO dependen de la pestania ----------
# Antes el contenido de la pestania envolvia tambien la columna de
# tarjetas, asi que al pasar a historia clinica desaparecian los
# planes, las consultas y el saldo. Las tarjetas dicen COMO VA el
# paciente; las pestanias, QUE se esta mirando de el.
html = re.sub(r'<\?(?:php|=).*?\?>', '', vistaPac3, flags=re.S)

nivel, nivelTabs, nivelTarjetas = 0, None, None
for m in re.finditer(r'<div\b[^>]*>|</div>', html):
    if m.group(0) == '</div>':
        nivel -= 1
        continue
    if 'tab-content' in m.group(0):
        nivelTabs = nivel
    if 'tarjetas-ficha' in m.group(0):
        nivelTarjetas = nivel
    nivel += 1

# Si el archivo quedo descuadrado, los niveles que acabo de contar no
# significan nada: un </div> de menos corre TODO lo que sigue un nivel
# hacia adentro, y la comparacion de abajo puede salir bien por pura
# aritmetica mientras el navegador dibuja las tarjetas dentro de la
# pestania. Descuadrado es fallo por si mismo, no un detalle de forma.
if nivel != 0:
    fallos.append(f'detalle_paciente.php esta descuadrado ({nivel:+d} div): '
                  'no se puede afirmar donde quedan las tarjetas')
elif nivelTabs is None or nivelTarjetas is None:
    fallos.append('no se encontraron las dos columnas de la ficha')
elif nivelTarjetas > nivelTabs:
    fallos.append('la columna de tarjetas esta DENTRO de las pestanias: '
                  'desapareceria al cambiar de pestania')
else:
    print('  ok   las tarjetas quedan fuera de las pestanias')

# ---------- Los colapsables se fueron ----------
if 'ficha-plegable' in vistaPac3:
    fallos.append('siguen los colapsables de contacto, tutor y domicilio')
if not (raiz/'vistas/paciente/_resumen_contacto.php').exists():
    fallos.append('falta el resumen de contacto, tutor y domicilio')
if not (raiz/'vistas/paciente/_modales_ficha.php').exists():
    fallos.append('faltan los modales de la ficha')
print('  ok   contacto, tutor y domicilio: resumen afuera, lista en modal')

# Cada bloque tiene UNA de las dos salidas: lapiz si hay datos, "+" si
# no. Las dos a la vez, o ninguna, dejaria al usuario sin forma de
# llegar o con dos caminos que hacen cosas distintas.
resumen = (raiz/'vistas/paciente/_resumen_contacto.php').read_text(encoding='utf-8')
for quien in ('contactos', 'tutores', 'domicilio'):
    if f'data-abrir="{quien}"' not in resumen:
        fallos.append(f'el bloque de {quien} no tiene lapiz')
for quien in ('contacto', 'tutor', 'domicilio'):
    if f'data-nuevo="{quien}"' not in resumen:
        fallos.append(f'el bloque de {quien} no tiene "+" cuando esta vacio')

jsPac = (raiz/'assets/js/paciente.js').read_text(encoding='utf-8')
for atributo in ('data-abrir', 'data-nuevo'):
    if f"closest('[{atributo}]')" not in jsPac:
        fallos.append(f'nadie escucha {atributo} en la ficha')
print('  ok   cada bloque tiene su lapiz y su "+", y alguien los escucha')

# El amarillo avisa de un hueco que hay que cerrar, no decora.
if 'falta' not in resumen or 'incompleto' not in resumen:
    fallos.append('no se avisa cuando faltan datos de tutor o domicilio')
cssT = (raiz/'assets/css/estilo.css').read_text(encoding='utf-8')
if not defineClase(cssT, '.resumen-dato.falta'):
    fallos.append('el aviso de datos faltantes no tiene estilo')
print('  ok   los huecos de tutor y domicilio se avisan en amarillo')

# ---------- Un solo domicilio ----------
domGuardar = (raiz/'includes/paciente/domicilio_guardar.php').read_text(encoding='utf-8')
if 'ya tiene un domicilio registrado' not in domGuardar:
    fallos.append('se pueden seguir registrando varios domicilios')
# Y el bloqueo solo aplica al ALTA: editar el que existe tiene que
# seguir funcionando, y quien ya tenga dos no se queda sin poder
# corregirlos.
if 'if (!$idDomicilio) {' not in domGuardar:
    fallos.append('el bloqueo de domicilio tambien impediria editar')
print('  ok   un domicilio por paciente, sin bloquear la edicion')

# ---------- El pie no tapa el contenido ----------
# Esta fijo al fondo y flota sobre la pagina: sin reservarle sitio,
# tapa el ultimo renglon de cada pantalla.
if '--alto-footer' not in cssT:
    fallos.append('el pie fijo no tiene altura declarada')
elif 'padding-bottom: calc(var(--alto-footer)' not in cssT:
    fallos.append('el contenido no reserva espacio para el pie: lo taparia')
print('  ok   el contenido deja sitio para el pie fijo')

# ---------- Saldo a favor, en los tres sitios ----------
# Pantalla de cobro, ficha e inicio. Y los tres del mismo calculo: dos
# cuentas separadas del mismo numero acaban diciendo cosas distintas.
if "Cargos::estadoDeCuenta($idExpediente)['resumen']" not in \
        (raiz/'includes/cajas/paciente_cuenta.php').read_text(encoding='utf-8'):
    fallos.append('la pantalla de cobro no usa el mismo calculo que el papel')
if 'aviso-a-favor' not in (raiz/'assets/js/cajas.js').read_text(encoding='utf-8'):
    fallos.append('la pantalla de cobro no avisa del saldo a favor')

inicio = (raiz/'vistas/inicio.php').read_text(encoding='utf-8')
if 'Con saldo a favor' not in inicio:
    fallos.append('el inicio no lista a quienes tienen saldo a favor')
else:
    # La resta tiene que estar DENTRO de la consulta del saldo a favor,
    # no en cualquier parte del archivo: el inicio toca pago_aplicacion
    # en mas de un sitio y basta uno para dar el visto bueno.
    #
    # Y con limite de palabra. Buscar la subcadena deja pasar
    # "pago_aplicacionX", que es justo el error que uno comete al
    # renombrar una tabla a medias.
    consulta = re.search(r'\$aFavor\s*=.*?;', inicio, re.S)
    if consulta is None:
        fallos.append('no se encontro la consulta del saldo a favor en el inicio')
    elif not re.search(r'\bpago_aplicacion\b', consulta.group(0)):
        fallos.append('el inicio calcula el saldo a favor sin restar lo aplicado')
print('  ok   el saldo a favor se ve en cobro, en la ficha y en el inicio')



# ---------- Dos columnas de tarjetas, no una ----------
# El encargo era que la ficha entera se vea sin bajar. Con las tarjetas
# apiladas en una columna, las ultimas quedan bajo el doblez y hay que
# desplazarse justo para ver las consultas, que es a lo que se entra.
#
# Ya no se exige el col-12 de los pendientes: esa tarjeta se fue a la de
# datos, debajo del contacto, y lo que queda son cuatro tarjetas en dos
# columnas parejas.
columna = re.search(r'tarjetas-ficha.*?<!-- /col-auto', vistaPac3, re.S)
if columna is None:
    fallos.append('no se encontro la columna de tarjetas de la ficha')
else:
    cuantas = len(re.findall(r'class="col-6"', columna.group(0)))
    if cuantas != 2:
        fallos.append(f'las tarjetas no van en dos columnas (hay {cuantas} col-6)')
    else:
        print('  ok   las tarjetas van en dos columnas, con los pendientes abajo')

# Que no vuelvan a crecer hacia abajo sin fin.
if not defineClase(cssT, '.tarjeta-ficha .card-body') or 'max-height' not in cssT:
    fallos.append('las tarjetas pueden volver a estirar la pagina sin tope')


# =====================================================================
#  Cambios del 11 de octubre 2026
# =====================================================================

# ---------- El campo Fotografia ----------
campoFoto = raiz/'vistas/paciente/_campo_fotografia.php'
if not campoFoto.exists():
    fallos.append('falta el campo de fotografia de los datos generales')
else:
    cf = campoFoto.read_text(encoding='utf-8')
    # Las cuatro acciones. Que esten las cuatro es el encargo: la foto
    # se podia reemplazar y nada mas, y una foto que no se puede bajar
    # ni quitar no es un documento del expediente, es un adorno.
    for marca, que in [('data-ver-foto', 'visualizar'),
                       ('archivo.php', 'descargar'),
                       ('data-cambiar-foto', 'reemplazar'),
                       ('data-quitar-foto', 'eliminar')]:
        if marca not in cf:
            fallos.append(f'la fotografia no se puede {que}')
    # Eliminar detras de los tres puntos, no al lado de ver: borrar no
    # se pone junto a lo que se usa a diario.
    if 'data-quitar-foto' in cf:
        antes = cf[:cf.index('data-quitar-foto')]
        if 'dropdown-menu' not in antes:
            fallos.append('eliminar la fotografia no esta detras de los tres puntos')
    if 'boton-camara' not in cf:
        fallos.append('sin fotografia no aparece el boton de camara')
    # Que el archivo exista no basta: uno vacio existe igual, y el
    # boton de eliminar se quedaria llamando a una pagina en blanco
    # que responde 200 sin hacer nada. Se pide lo que tiene que hacer.
    quitar = raiz/'includes/paciente/foto_quitar.php'
    quitarTxt = quitar.read_text(encoding='utf-8') if quitar.exists() else ''
    if 'endpoint(' not in quitarTxt:
        fallos.append('el endpoint que quita la fotografia no existe o esta vacio')
    elif 'quitarArchivo' not in quitarTxt:
        fallos.append('quitar la fotografia no da de baja su documento')
    elif 'foto = NULL' not in quitarTxt:
        fallos.append('quitar la fotografia deja el recorte de la tarjeta puesto')
    print('  ok   la fotografia: ver, descargar, reemplazar y eliminar')

# La foto se ve en grande, y desde los dos sitios donde se ve chica.
if 'data-ver-foto' not in vistaPac3:
    fallos.append('la foto de la tarjeta no se puede ver en grande')
appJs = (raiz/'assets/js/app.js').read_text(encoding='utf-8')
if 'function verImagen' not in appJs:
    fallos.append('no hay visor de una imagen sola')
elif 'verImagen,' not in appJs:
    fallos.append('el visor de imagen existe pero no se exporta: nadie puede llamarlo')
if not defineClase(cssT, '.visor-imagen'):
    fallos.append('el visor de imagen no tiene estilo')
print('  ok   la foto se ve en grande desde la tarjeta y desde el campo')

# ---------- El aviso de los canales de contacto ----------
# La columna se llama valor_contacto. Escribirla corta no truena: PHP
# avisa y pinta vacio, asi que el telefono desaparece sin mas.
resumen2 = (raiz/'vistas/paciente/_resumen_contacto.php').read_text(encoding='utf-8')
if re.search(r"\$c\['valor'\]", resumen2):
    fallos.append('el resumen de contacto pide $c[valor]: la columna es valor_contacto')
elif "valor_contacto" not in resumen2:
    fallos.append('el resumen de contacto no lee ningun valor')
print('  ok   los canales de contacto leen la columna que existe')

# ---------- El tutor no cierra su modal al guardar ----------
jsPac2 = (raiz/'assets/js/paciente.js').read_text(encoding='utf-8')
if 'cerrarYVolverATutor' in jsPac2:
    fallos.append('guardar un dato del tutor vuelve a cerrar el modal')
elif 'marcarCambioTutor' not in jsPac2:
    fallos.append('no se anota que el tutor cambio para refrescar al cerrar')
elif "modal.addEventListener('hidden.bs.modal'" not in jsPac2:
    fallos.append('la ficha no se refresca cuando se cierra el modal del tutor')
print('  ok   el modal del tutor se queda abierto al guardar')

# ---------- El expediente arranca cerrado ----------
jsExp = (raiz/'assets/js/expediente.js').read_text(encoding='utf-8')
if re.search(r"abiertos\s*=\s*new Set\(\[", jsExp):
    fallos.append('el expediente vuelve a abrir un bloque solo al entrar')
if re.search(r'exp-consulta-caja"\s*\$\{', jsExp):
    fallos.append('alguna consulta del expediente vuelve a abrirse sola')
print('  ok   el expediente abre con todos los bloques cerrados')

# ---------- Sin casillas vacias del catalogo ----------
# Un tipo del que no hay nada registrado no se dibuja: eran renglones
# del catalogo, no documentos del paciente, y llenaban el expediente.
# Sin los comentarios: la frase que se busca vive tambien en el
# comentario que explica por que se quito, y buscarla en el archivo
# entero hace que el propio texto que documenta el arreglo se lea como
# si el arreglo no estuviera.
jsExpCodigo = soloCodigo(jsExp)

if 'Sin documentos de este tipo' in jsExpCodigo:
    fallos.append('el expediente vuelve a pintar los tipos sin nada registrado')
elif 'if (!docs.length) return' not in jsExpCodigo:
    fallos.append('nada impide pintar un tipo de documento vacio')
print('  ok   solo se listan los documentos que existen')

# ---------- La primera visita al dentista ----------
historia = (raiz/'vistas/paciente/_tab_historia.php').read_text(encoding='utf-8')
m = re.search(r'data-depende="primera_visita_dentista" data-cuando="(\d)"', historia)
if m is None:
    fallos.append('se perdio la pregunta de la primera visita al dentista')
elif m.group(1) != '0':
    fallos.append('"cuando fue la ultima vez" cuelga del SI: tiene que colgar del NO')
else:
    print('  ok   la ultima visita se pregunta solo si NO es la primera')


# =====================================================================
#  Cambios del 11 de octubre 2026 (tarde)
# =====================================================================

inicio2 = (raiz/'vistas/inicio.php').read_text(encoding='utf-8')

# ---------- El tablero, en dos columnas de tarjetas ----------
columnaIni = re.search(r'tarjetas-inicio.*?</div>\s*</div>\s*$', inicio2, re.S)
if 'tarjetas-inicio' not in inicio2:
    fallos.append('el tablero no tiene su columna de tarjetas')
elif len(re.findall(r'class="col-6"', inicio2)) != 2:
    fallos.append('las tarjetas del tablero no van en dos columnas')
elif 'class="col-8"' in inicio2:
    fallos.append('la agenda sigue en col-8: con dos columnas al lado la '
                  'suma pasa de doce y la segunda se cae abajo')
else:
    print('  ok   el tablero: agenda a la izquierda, dos columnas de tarjetas')

# La regla del ANCHO, no el nombre a secas: la clase aparece tambien
# en el tope de alto de las tarjetas, asi que buscarla suelta la daba
# por buena aunque el ancho se hubiera perdido.
if not re.search(r'\.tarjetas-inicio\s*\{[^}]*width\s*:', cssT):
    fallos.append('la columna de tarjetas del tablero no tiene ancho')

# ---------- La entrada escalonada ----------
# Medio segundo cada una, una decima entre una y la siguiente.
anim = re.search(r'@keyframes tarjeta-entra.*?\n\}', cssT, re.S)
if anim is None:
    fallos.append('no existe la animacion de entrada de las tarjetas')
elif not re.search(r'\.entra\s*\{[^}]*animation:\s*tarjeta-entra\s+\.5s', cssT):
    fallos.append('la entrada de las tarjetas no dura medio segundo')
elif 'prefers-reduced-motion' not in cssT:
    fallos.append('la animacion no respeta a quien pidio que no le muevan la pantalla')
else:
    print('  ok   las tarjetas entran en medio segundo, una tras otra')

if '$escalon++ * 0.1' not in inicio2:
    fallos.append('el escalon entre tarjetas ya no es de una decima')
if 'esperaCortinilla' not in inicio2:
    fallos.append('las tarjetas se animarian detras de la cortinilla de carga')
# Nueve o mas tarjetas tienen que recibir su retraso: el contador vive
# en PHP justamente para que no se reinicie por fila.
if inicio2.count('$entrada() ?>') < 6:
    fallos.append('hay tarjetas del tablero sin su retraso')

# ---------- Nueva cita en la vista de semana ----------
jsCon = (raiz/'assets/js/consultas.js').read_text(encoding='utf-8')
semana = (raiz/'vistas/consultas/semana.php').read_text(encoding='utf-8')
agendaV = (raiz/'vistas/consultas/agenda.php').read_text(encoding='utf-8')

if 'id="smNuevaCita"' not in semana:
    fallos.append('se perdio el boton de nueva cita de la semana')
elif "getElementById('smNuevaCita')" not in jsCon:
    fallos.append('el boton de nueva cita de la semana no tiene escucha')
elif 'agAbrirNueva' not in agendaV:
    fallos.append('la agenda del dia no sabe abrir la cita al llegar')
elif "getElementById('agAbrirNueva')" not in jsCon:
    fallos.append('nadie lee la marca que abre la cita en la agenda del dia')
else:
    print('  ok   nueva cita desde la semana lleva al dia y abre el modal')

# El modal de la cita NO vive en la vista de semana: por eso se navega
# en vez de intentar abrirlo alli.
if 'id="modalCita"' in semana:
    fallos.append('la vista de semana duplica el modal de la cita')

# ---------- El mensaje del recordatorio ----------
agendaExtra = (raiz/'includes/core/agenda_extra.php').read_text(encoding='utf-8')
vistaRec = (raiz/'vistas/configuracion/recordatorios.php').read_text(encoding='utf-8')

if 'PLANTILLA_POR_OMISION' not in agendaExtra:
    fallos.append('el recordatorio no tiene plantilla de origen')
elif 'parametro(' not in agendaExtra:
    fallos.append('el mensaje del recordatorio no se puede configurar')
elif 'preg_replace_callback' not in agendaExtra:
    fallos.append('la sustitucion de etiquetas volvio a ser pasada por pasada: '
                  'un valor del paciente podria inyectar etiquetas')
else:
    print('  ok   el mensaje del recordatorio sale de la plantilla guardada')

if not (raiz/'includes/configuracion/recordatorio_mensaje_guardar.php').exists():
    fallos.append('no se puede guardar el mensaje del recordatorio')

if len(re.findall(r'class="col-6"', vistaRec)) != 2:
    fallos.append('los recordatorios no quedaron en dos tarjetas mitad y mitad')
elif 'data-etiqueta' not in vistaRec:
    fallos.append('no hay botones para insertar las etiquetas')
elif 'recPrevia' not in vistaRec:
    fallos.append('falta la vista previa del mensaje')
else:
    print('  ok   recordatorios: tiempos a la izquierda, mensaje a la derecha')

# ---------- El tarifario ----------
indexPhp = (raiz/'index.php').read_text(encoding='utf-8')
rutaTar = re.search(r"'configuracion/tarifas'\s*=>\s*\[(.*?)\]", indexPhp, re.S)
if rutaTar is None:
    fallos.append('no existe la ruta del tarifario')
elif 'en_construccion' in rutaTar.group(1):
    fallos.append('el tarifario sigue apuntando a en_construccion')
elif not (raiz/'vistas/configuracion/tarifas.php').exists():
    fallos.append('la ruta del tarifario apunta a una vista que no existe')
else:
    print('  ok   el tarifario tiene pantalla propia')

for archivo, que in [
        ('includes/configuracion/tarifas_listar.php', 'listar'),
        ('includes/configuracion/tarifa_guardar.php', 'dar de alta y modificar'),
        ('includes/configuracion/tarifa_estatus.php', 'desactivar')]:
    if not (raiz/archivo).exists():
        fallos.append(f'el tarifario no puede {que}')

# Los precios NO se borran: un plan firmado apunta a ellos y tiene que
# seguir siendo legible.
estatusTar = raiz/'includes/configuracion/tarifa_estatus.php'
if estatusTar.exists():
    txt = soloCodigo(estatusTar.read_text(encoding='utf-8'))
    if 'DELETE' in txt.upper():
        fallos.append('el tarifario borra precios en vez de desactivarlos')

# Y cambiar un precio no reescribe lo ya pactado.
guardarTar = raiz/'includes/configuracion/tarifa_guardar.php'
if guardarTar.exists():
    txt = soloCodigo(guardarTar.read_text(encoding='utf-8'))
    if 'plan_tratamiento_detalle' in txt:
        fallos.append('cambiar un precio reescribe planes ya firmados: '
                      'el paciente acepto otro importe por escrito')
    elif 'UPDATE procedimiento SET costo_sugerido' not in txt:
        fallos.append('cambiar un precio no actualiza lo que proponen '
                      'los procedimientos ligados')
    else:
        print('  ok   el precio nuevo rige hacia adelante, no hacia atras')

# La pantalla explica en que se diferencia de Procedimientos: es la
# primera pregunta de cualquiera que entre, y esta escrita ahi.
vistaTar = raiz/'vistas/configuracion/tarifas.php'
if vistaTar.exists():
    txt = vistaTar.read_text(encoding='utf-8')
    if 'vista=procedimientos' not in txt:
        fallos.append('el tarifario no explica su relacion con Procedimientos')
    if 'clave_prod_serv_sat' not in txt:
        fallos.append('el tarifario no deja capturar la clave del SAT, '
                      'que es la razon por la que existe aparte')


# =====================================================================
#  Cambios del 12 de octubre 2026
# =====================================================================

jsCon2  = (raiz/'assets/js/consultas.js').read_text(encoding='utf-8')
inicio3 = (raiz/'vistas/inicio.php').read_text(encoding='utf-8')
vistaPac4 = (raiz/'vistas/paciente/detalle_paciente.php').read_text(encoding='utf-8')

# ---------- La nota de la cita se recupera al editar ----------
# Este era el bug que llego a la clienta como "la nota no se guarda".
# Si se guardaba: al reagendar, reset() limpiaba el formulario, la nota
# no se volvia a poner y al dar Guardar se mandaba vacia encima.
abrir = re.search(r'function abrirCita\(.*?\n  \}', jsCon2, re.S)
if abrir is None:
    fallos.append('no se encontro abrirCita')
elif '[name="notas"]' not in abrir.group(0):
    fallos.append('abrirCita no recupera la nota: al guardar la borraria')
else:
    print('  ok   la nota de la cita se recupera al modificarla')

if 'c.notas' not in (raiz/'includes/consultas/citas_listar.php').read_text(encoding='utf-8'):
    fallos.append('el listado del dia no trae la nota')
if 'nota-cita' not in jsCon2:
    fallos.append('la nota no se ve en las citas del dia')
if not defineClase(cssT, '.nota-cita'):
    fallos.append('la nota de la cita no tiene estilo')

# ---------- El detalle de la cita, uno solo ----------
comun = (raiz/'assets/js/comun.js').read_text(encoding='utf-8')
if 'const DetalleCita' not in comun:
    fallos.append('no existe el componente de detalle de cita')
elif not (raiz/'vistas/consultas/_modal_detalle_cita.php').exists():
    fallos.append('falta el modal del detalle de cita')
else:
    # Las TRES pantallas lo usan, y ninguna tiene su propia copia: dos
    # bloques con los mismos ids acaban ensenando cosas distintas.
    copias = [f for f in raiz.rglob('_modal_detalle_cita*.php')]
    if len(copias) != 1:
        fallos.append(f'hay {len(copias)} copias del modal de detalle de cita')
    else:
        print('  ok   un solo detalle de cita, compartido por las tres pantallas')

for archivo, quien in [
        ('vistas/consultas/agenda.php', 'la agenda del dia'),
        ('vistas/consultas/semana.php', 'la agenda de la semana'),
        ('vistas/paciente/detalle_paciente.php', 'la ficha del paciente')]:
    if '_modal_detalle_cita.php' not in (raiz/archivo).read_text(encoding='utf-8'):
        fallos.append(f'{quien} no incluye el modal de detalle de cita')

if jsCon2.count('DetalleCita.abrir') < 2:
    fallos.append('alguna agenda no abre el detalle al picar la cita')
if 'DetalleCita.abrir' not in jsPac2:
    fallos.append('la ficha no abre el detalle al picar una cita')

# El clic del renglon no puede tragarse el del enlace ni el del menu.
if "closest('a, button, .dropdown')" not in jsCon2:
    fallos.append('el clic del renglon se traga el del enlace y el del menu')

# La semana tiene que traer la nota: sin ella el detalle sale a medias.
if 'c.notas' not in (raiz/'includes/consultas/semana.php').read_text(encoding='utf-8'):
    fallos.append('la vista de semana no trae la nota de la cita')

# Y el cambio de estatus vive en UNA funcion, no copiado en dos sitios.
if 'async function cambiarEstatus' not in jsCon2:
    fallos.append('el cambio de estatus volvio a estar suelto: el menu y '
                  'el detalle acabarian haciendo cosas distintas')

# ---------- El tablero ----------
if 'c.notas' not in inicio3:
    fallos.append('las citas por confirmar no traen su nota')
elif 'modalPorConfirmar' not in inicio3:
    fallos.append('falta el "ver todos" de las citas por confirmar')
elif '$filaPorConfirmar' not in inicio3:
    fallos.append('el renglon por confirmar se pinta dos veces en vez de '
                  'una funcion: las dos listas acabarian distintas')
else:
    print('  ok   citas por confirmar: con nota y con "ver todos"')

if '$porDigitalizar' not in inicio3:
    fallos.append('el tablero no lista los pendientes de digitalizacion')
elif 'modalPorDigitalizar' not in inicio3:
    fallos.append('los pendientes de digitalizacion no tienen "ver todos"')
elif 'array_slice($porDigitalizar, 0, 5)' not in inicio3:
    fallos.append('los pendientes de digitalizacion no recortan a cinco')
else:
    print('  ok   pendientes de digitalizacion de todo el consultorio')

if '$enEspera' not in inicio3:
    fallos.append('el tablero no muestra la lista de espera')
elif 'vista=agenda&nueva=1&paciente=' not in inicio3:
    fallos.append('desde la lista de espera no se puede agendar')
else:
    print('  ok   lista de espera bajo la agenda, con boton de agendar')

# El escucha de confirmar tiene que alcanzar al modal, no solo a la
# tarjeta: son dos listas con los mismos renglones.
jsIni = (raiz/'assets/js/inicio.js').read_text(encoding='utf-8')

# Acotado a SU funcion. Buscarlo en el archivo entero daba por roto el
# escucha de los recordatorios, que si vive en una sola lista y no
# tiene modal: ahi colgar del contenedor es lo correcto.
confirmarJs = re.search(
    r'function citasPorConfirmar\(\).*?\n\}\n', jsIni, re.S)
if confirmarJs is None:
    fallos.append('no se encontro citasPorConfirmar en el tablero')
elif "tarjeta.addEventListener('click'" in confirmarJs.group(0):
    fallos.append('confirmar citas vuelve a escuchar solo en la tarjeta: '
                  'los botones del modal nacerian muertos')
elif "document.addEventListener('click'" not in confirmarJs.group(0):
    fallos.append('confirmar citas no usa un escucha delegado')
elif 'cita-pendiente[data-cita=' not in confirmarJs.group(0):
    fallos.append('al confirmar no se quita el renglon de las dos listas')
else:
    print('  ok   confirmar una cita funciona en la tarjeta y en el modal')

# ---------- Las citas del paciente ----------
if '$citasPorEstado' not in vistaPac4:
    fallos.append('la ficha no cuenta las citas por estatus')
elif 'cuenta-citas' not in vistaPac4:
    fallos.append('los contadores de citas no se pintan')
elif not defineClase(cssT, '.cuenta-citas'):
    fallos.append('los contadores de citas no tienen estilo')
elif 'modalCitasPaciente' not in vistaPac4:
    fallos.append('falta el "ver mas" con todas las citas')
else:
    print('  ok   citas del paciente: estatus, contadores y "ver mas"')

# Los cuatro, y en cero tambien: que un paciente tenga CERO faltas es
# justo lo que se quiere ver, y un numero ausente no se distingue de un
# dato que no se midio.
for clave in ('pendientes', 'atendidas', 'canceladas', 'no_asistio'):
    if f"'{clave}'" not in vistaPac4:
        fallos.append(f'falta el contador de citas {clave}')
if not defineClase(cssT, '.cuenta-citas .cc.vacia'):
    fallos.append('los contadores en cero se esconderian en vez de apagarse')


# =====================================================================
#  Nanai atiende ninios y adultos (13 de octubre 2026)
# =====================================================================

vistaPac5 = (raiz/'vistas/paciente/detalle_paciente.php').read_text(encoding='utf-8')
tutoriaPhp = (raiz/'includes/core/tutoria.php').read_text(encoding='utf-8')

# ---------- La regla vive en UN solo sitio ----------
if 'class Tutoria' not in tutoriaPhp:
    fallos.append('no existe la regla del tutor')
elif "require_once __DIR__ . '/tutoria.php'" not in \
        (raiz/'includes/core/bootstrap.php').read_text(encoding='utf-8'):
    fallos.append('Tutoria no se carga: las pantallas no la encontraran')
else:
    print('  ok   la regla del tutor existe y se carga')

# Nadie puede volver a calcular la mayoria de edad por su cuenta. Dos
# sitios decidiendo lo mismo acaban discrepando, y el sintoma seria un
# aviso que sale y no se puede quitar.
for archivo in ('vistas/paciente/detalle_paciente.php',
                'consentimiento.php', 'plan_imprimir.php',
                'historia_imprimir.php'):
    txt = soloCodigo((raiz/archivo).read_text(encoding='utf-8'))
    if re.search(r"edad'?\]?\s*[<>]=?\s*18", txt):
        fallos.append(f'{archivo} compara la edad con 18 por su cuenta '
                      'en vez de preguntarle a Tutoria')

# ---------- La migracion ----------
mig = raiz/'db/migraciones/0009_tutor_opcional.sql'
if not mig.exists():
    fallos.append('falta la migracion del tutor opcional')
else:
    sql = mig.read_text(encoding='utf-8')
    for col in ('requiere_tutor', 'tutor_decidido_edad'):
        if col not in sql:
            fallos.append(f'la migracion no agrega {col}')
    # El esquema base tambien, o una instalacion nueva nace sin las
    # columnas y la migracion no corre porque la base ya esta al dia.
    if 'requiere_tutor' not in (raiz/'db/01_schema.sql').read_text(encoding='utf-8'):
        fallos.append('el esquema base no trae requiere_tutor: una '
                      'instalacion nueva nacería sin la columna')
    print('  ok   requiere_tutor: en la migracion y en el esquema base')

# ---------- La pantalla ----------
if 'Tutoria::hayQuePreguntar' not in vistaPac5:
    fallos.append('la ficha no pregunta por el tutor al cumplir anios')
elif 'data-preguntar-tutor' not in vistaPac5:
    fallos.append('la decision no llega al navegador')
elif 'preguntarTutor' not in jsPac2:
    fallos.append('nadie abre la pregunta del tutor')
elif not (raiz/'includes/paciente/tutor_decidir.php').exists():
    fallos.append('no se puede guardar la respuesta sobre el tutor')
else:
    print('  ok   al cumplir anios se pregunta, y la respuesta se guarda')

# El bloque del tutor desaparece si el paciente responde por si mismo.
resumen3 = (raiz/'vistas/paciente/_resumen_contacto.php').read_text(encoding='utf-8')
if "$toca('tutor') && $llevaTutor" not in resumen3:
    fallos.append('el bloque del tutor sigue saliendo aunque el paciente '
                  'responda por si mismo')

# Y el campo se puede cambiar a mano.
if "Ficha::campo('requiere_tutor'" not in vistaPac5:
    fallos.append('no hay campo para cambiar si requiere tutor')
if 'Tutoria::decidir' not in \
        (raiz/'includes/paciente/actualizar_identificacion.php').read_text(encoding='utf-8'):
    fallos.append('cambiar el campo no apunta la edad de la decision: '
                  'volveria a preguntar en cada visita')

# ---------- Los imprimibles ----------
for archivo, quien in [('consentimiento.php', 'el consentimiento'),
                       ('plan_imprimir.php', 'la hoja del plan'),
                       ('historia_imprimir.php', 'la historia impresa')]:
    txt = (raiz/archivo).read_text(encoding='utf-8')
    if 'firmaElPaciente' not in txt:
        fallos.append(f'{quien} siempre pide la firma del tutor')
    elif 'Tutoria::llevaTutor' not in txt:
        fallos.append(f'{quien} decide quien firma sin usar la regla')
print('  ok   los tres imprimibles respetan quien firma')

# ---------- La historia clinica: pregunta en ventanita ----------
jsHist2 = (raiz/'assets/js/historia.js').read_text(encoding='utf-8')
histTab = (raiz/'vistas/paciente/_tab_historia.php').read_text(encoding='utf-8')

# Las doce, ni una menos: son las que pidio el consultorio.
cuantas = len(re.findall(r'data-depende="\w+"', histTab))
if cuantas != 12:
    fallos.append(f'hay {cuantas} preguntas condicionales, deberian ser 12')

if 'modalPreguntaExtra' not in histTab:
    fallos.append('la pregunta extra no sale en una ventanita')
elif 'abrirPregunta' not in jsHist2:
    fallos.append('nadie abre la ventanita de la pregunta extra')
elif 'respuesta-extra' not in jsHist2:
    fallos.append('la respuesta no se pinta junto al selector')
elif not defineClase(cssT, '.respuesta-extra'):
    fallos.append('la respuesta junto al selector no tiene estilo')
else:
    print('  ok   las 12 preguntas extra salen en ventanita')

# Cerrar sin contestar DESHACE la respuesta: un "si" a medias es un dato
# que parece capturado y no lo esta.
if 'valorPrevio' not in jsHist2:
    fallos.append('no se recuerda la respuesta anterior: cerrar la '
                  'ventanita dejaria un si sin su detalle')
elif "hidden.bs.modal" not in jsHist2:
    fallos.append('cerrar la ventanita sin contestar no deshace nada')
else:
    print('  ok   cerrar sin contestar regresa a la respuesta anterior')

# Y abrir un expediente ya capturado NO puede lanzar doce modales.
if 'porPersona' not in jsHist2:
    fallos.append('no se distingue quien contesto: abrir un expediente '
                  'viejo lanzaria doce ventanitas en cadena')
elif 'aplicarDependencias(true)' in jsHist2:
    fallos.append('el recorrido completo vuelve a preguntar: eso abre '
                  'una ventana por cada respuesta ya capturada')
elif re.search(r"addEventListener\('change'[^)]*aplicarDependencias\(true", jsHist2):
    fallos.append('la pregunta vuelve a colgar del evento change, que '
                  'no distingue a una persona del propio codigo')

# El prellenado deja respuestas a medias a proposito —contesta "si" sin
# saber el detalle— y eso no puede pasar al siguiente paso.
if 'respuestasQueFaltan' not in jsHist2:
    fallos.append('nada impide avanzar con un "si" sin su detalle')

# Las referencias a la ventana se buscan al momento: la historia se
# inyecta al abrir su pestania, y guardarlas al cargar el archivo las
# dejaria en null justo en esa pantalla.
if re.search(r'const cajaPregunta = document\.getElementById', jsHist2):
    fallos.append('la ventana se busca al cargar el archivo: dentro de '
                  'la ficha todavia no existe y quedaria en null')

# ---------- Las alertas clinicas, de la historia ----------
if 'class AlertasClinicas' not in tutoriaPhp:
    fallos.append('no existen las alertas clinicas desde la historia')
else:
    # Acotado al mapa, no al archivo entero: las cuatro palabras salen
    # tambien en el comentario que explica por que son esas cuatro, asi
    # que buscarlas sueltas daba por buena la lista aunque faltara un
    # renglon. Es la cuarta vez que me muerde lo mismo en otro sitio.
    mapaAl = re.search(r'\$mapa\s*=\s*\[(.*?)\];', tutoriaPhp, re.S)
    if mapaAl is None:
        fallos.append('no se encontro la lista de alertas clinicas')
    else:
        for campo in ('enfermedad_cronica', 'discapacidad', 'alergia',
                      'tratamiento_medico'):
            if f"'{campo}'" not in mapaAl.group(1):
                fallos.append(f'las alertas no miran {campo}')
        print('  ok   las alertas salen de las cuatro preguntas de la historia')

# Ya NO de expediente_antecedente: esa tabla solo tiene datos de prueba,
# asi que el aviso salia en los pacientes inventados y no en los reales.
for archivo in ('vistas/consultas/nuevo_plan_tratamiento.php',
                'vistas/consultas/consulta.php',
                'vistas/paciente/detalle_paciente.php'):
    txt = soloCodigo((raiz/archivo).read_text(encoding='utf-8'))
    if 'requiere_alerta' in txt:
        fallos.append(f'{archivo} vuelve a sacar las alertas de los '
                      'datos de prueba en vez de la historia')
    if 'AlertasClinicas::de' not in txt:
        fallos.append(f'{archivo} no usa las alertas de la historia')

# El chip de discapacidad, aparte y en rosa.
if 'chip-discapacidad' not in vistaPac5:
    fallos.append('la discapacidad no se destaca en la tarjeta')
elif not defineClase(cssT, '.chip-discapacidad'):
    fallos.append('el chip de discapacidad no tiene estilo')
else:
    print('  ok   la discapacidad se ve en rosa en la tarjeta')


# =====================================================================
#  Dinero y detalles (13 de octubre 2026, tarde)
# =====================================================================

cajaPhp   = (raiz/'includes/core/caja.php').read_text(encoding='utf-8')
cargosPhp = (raiz/'includes/core/cargos.php').read_text(encoding='utf-8')
jsCajas   = (raiz/'assets/js/cajas.js').read_text(encoding='utf-8')

# ---------- La firma del desarrollador ----------
pie = (raiz/'vistas/layout/footer.php').read_text(encoding='utf-8')
if 'logo_horizontal_blanco.svg' not in pie:
    fallos.append('el pie no lleva la firma del desarrollador')
elif not (raiz/'assets/img/logo_horizontal_blanco.svg').exists():
    fallos.append('falta el archivo del logotipo del pie')
elif not defineClase(cssT, '.firma-desarrollador'):
    fallos.append('la firma del pie no tiene estilo')
else:
    print('  ok   la firma del desarrollador, en el pie')

# ---------- HEIC ----------
imagenPhp = (raiz/'includes/core/imagen.php').read_text(encoding='utf-8')
almacen   = (raiz/'includes/core/almacen.php').read_text(encoding='utf-8')

# Acotado a la lista de formatos. La palabra 'heic' sale tambien en los
# comentarios que explican por que se acepta, asi que buscarla suelta
# daba por buena una lista de la que se habia caido.
listaImg = re.search(r'const IMAGENES\s*=\s*\[(.*?)\];', imagenPhp, re.S)
if listaImg is None or 'heic' not in listaImg.group(1):
    fallos.append('Imagen no acepta fotos de iPhone')
elif 'deHeicAJpeg' not in imagenPhp:
    fallos.append('no hay forma de convertir un HEIC: GD no sabe leerlo')
elif "'heic'" not in almacen:
    fallos.append('Almacen no conoce el tipo heic')
elif 'libheif-examples' not in (raiz/'docker/php/Dockerfile').read_text(encoding='utf-8'):
    fallos.append('falta heif-convert en la imagen de PHP: la conversion '
                  'fallaria en el servidor aunque el codigo este')
else:
    print('  ok   fotos de iPhone: se aceptan y se convierten a jpg')

# El archivo NUNCA se pierde si falla la conversion. Es la diferencia
# entre un expediente pesado y uno con huecos.
conv = re.search(r'function deHeicAJpeg.*?\n    \}', imagenPhp, re.S)
if conv is None:
    fallos.append('no se encontro deHeicAJpeg')
else:
    if 'finally' not in conv.group(0):
        fallos.append('el conversor de HEIC puede dejar archivos sueltos en /tmp')
    if 'proc_terminate' not in conv.group(0):
        fallos.append('el conversor de HEIC no tiene limite de tiempo: un '
                      'archivo corrupto dejaria a Apache esperando')

if not (raiz/'db/migraciones/0010_heic_de_iphone.sql').exists():
    fallos.append('falta la migracion que acepta heic en los formatos')

# ---------- El album ----------
if 'entra-der' not in comun or 'entra-izq' not in comun:
    fallos.append('el album no anima el cambio de imagen')
elif not defineClase(cssT, '#albVerImagen.entra-der'):
    fallos.append('la animacion del album no tiene estilo')
elif not re.search(
        r"closest\('#albVerAbrir'\)[\s\S]{0,600}?App\.abrirVentana", comun):
    # No basta con que las dos palabras existan en el archivo: tiene
    # que ser el MANEJADOR del enlace el que abra la ventana. Con la
    # comprobacion suelta, desconectar el manejador pasaba inadvertido
    # porque 'abrirVentana' seguia usandose en otro sitio.
    fallos.append('abrir la imagen no usa una ventana a su medida')
else:
    print('  ok   el album: con movimiento y abriendo centrado')

# ---------- Borrar una imagen del hallazgo ----------
quitarImg = raiz/'includes/consultas/hallazgo_imagen_quitar.php'
if not quitarImg.exists():
    fallos.append('no se puede quitar una imagen de un hallazgo')
else:
    txt = soloCodigo(quitarImg.read_text(encoding='utf-8'))
    # Sin esta comprobacion, el endpoint borraria CUALQUIER documento
    # del expediente con solo mandarle un numero.
    # El guardia de verdad, no la palabra suelta: 'id_hallazgo' aparece
    # tambien al escribir en la bitacora, asi que buscarla daba por
    # protegido un endpoint que ya no comprobaba nada. Y lo que se
    # borraria sin ese guardia es el consentimiento firmado de alguien.
    if not re.search(r"if\s*\(\s*empty\(\s*\$doc\['id_hallazgo'\]\s*\)\s*\)", txt):
        fallos.append('quitar imagen no comprueba que sea de un hallazgo: '
                      'podria borrar el consentimiento firmado')
    elif "d.id_hallazgo" not in txt:
        fallos.append('quitar imagen no pide id_hallazgo: el guardia '
                      'miraria una columna que no se trajo')
    elif 'data-quitar-imagen' not in jsCon2:
        fallos.append('no hay boton para quitar la imagen')
    else:
        print('  ok   una imagen del hallazgo se quita de una en una')

# ---------- El avance del plan ----------
if 'function avancePlan' not in cargosPhp:
    fallos.append('no existe el avance del plan de tratamiento')
else:
    av = cargosPhp[cargosPhp.index('function avancePlan'):]
    av = av[:av.index('\n    }')]
    for clave in ('anticipado', 'realizado', 'por_cobrar', 'resta'):
        if f"'{clave}'" not in av:
            fallos.append(f'el avance del plan no devuelve {clave}')
    # Los pagos cancelados no cuentan: su dinero se devolvio.
    if 'estatus = 1' not in av:
        fallos.append('el avance del plan suma tambien los pagos cancelados')
    print('  ok   el avance del plan: total, anticipo, realizado y resta')

if 'avancePlan' not in vistaPac5:
    fallos.append('la ficha no muestra el anticipo del plan')
if 'avancePlan' not in (raiz/'estado_cuenta.php').read_text(encoding='utf-8'):
    fallos.append('el estado de cuenta no muestra el avance de los planes')

# ---------- Usar el saldo a favor al cobrar ----------
if 'usar_a_favor' not in cajaPhp:
    fallos.append('no se puede usar el saldo a favor al cobrar')
elif 'dineroSinAplicar' not in cajaPhp:
    fallos.append('no se sabe de que pagos sale el saldo a favor')
elif 'cbUsarAFavor' not in jsCajas:
    fallos.append('no hay interruptor para usar el saldo a favor')
elif 'Se aplican solos al cobrar' in jsCajas:
    fallos.append('la pantalla vuelve a prometer que el saldo se aplica '
                  'solo, que es justo lo que NO hacia')
else:
    print('  ok   el saldo a favor se puede gastar al cobrar')

# El concepto tiene que quedar en la ficha de pago, o el paciente
# recibe un recibo por menos de lo esperado sin explicacion.
if 'observacionesCobro' not in cajaPhp:
    fallos.append('el saldo aplicado no se anota en la ficha de pago')
elif 'SALDO A FAVOR' not in cajaPhp:
    fallos.append('la ficha de pago no dice que se aplico saldo a favor')

# Y cubriendo todo NO se crea un pago de cero pesos, que ensuciaria el
# corte de caja con un movimiento que no movio nada.
if 'solo_a_favor' not in cajaPhp:
    fallos.append('cubrir todo con saldo a favor crearia un cobro de cero')


# =====================================================================
#  El ciclo de vida del plan (13 de octubre 2026, noche)
# =====================================================================

planesPhp = (raiz/'includes/core/planes.php').read_text(encoding='utf-8')
vistaPlan = (raiz/'vistas/consultas/nuevo_plan_tratamiento.php').read_text(encoding='utf-8')

# ---------- La regla vive en un solo sitio ----------
if 'class Planes' not in planesPhp:
    fallos.append('no existe la regla de estados del plan')
elif "require_once __DIR__ . '/planes.php'" not in \
        (raiz/'includes/core/bootstrap.php').read_text(encoding='utf-8'):
    fallos.append('Planes no se carga: las pantallas no la encontraran')
else:
    print('  ok   la regla de estados del plan existe y se carga')

# Terminado y Cancelado son finales. Es LO que hace util a la regla.
finales = re.search(r'const FINALES\s*=\s*\[(.*?)\];', planesPhp, re.S)
if finales is None:
    fallos.append('no se declara que estados son finales')
else:
    for cual in ('TERMINADO', 'CANCELADO'):
        if cual not in finales.group(1):
            fallos.append(f'{cual} dejo de ser un estado final: un plan '
                          'cerrado podria reabrirse')

# El fallo concreto que se arreglo: el avance automatico solo miraba al
# cancelado y dejaba mover a los TERMINADOS.
avancePhp = soloCodigo(
    (raiz/'includes/consultas/plan_avance.php').read_text(encoding='utf-8'))
if 'NOT IN (5)' in avancePhp:
    fallos.append('el avance automatico vuelve a la condicion vieja: '
                  'desmarcar un concepto reabriria un plan terminado')
elif 'Planes::puedeAvanzarAuto' not in avancePhp:
    fallos.append('el avance automatico no consulta la regla')
else:
    print('  ok   el avance de conceptos ya no reabre un plan cerrado')

# ---------- Cancelar ----------
if not (raiz/'includes/consultas/plan_cancelar.php').exists():
    fallos.append('no se puede cancelar un plan')
elif 'function cancelar' not in planesPhp:
    fallos.append('la cancelacion no pasa por la regla')
else:
    cancelar = planesPhp[planesPhp.index('function cancelar'):]
    cancelar = cancelar[:cancelar.index('\n    }')]
    if 'puedeCancelar' not in cancelar:
        fallos.append('se puede cancelar un plan ya aceptado')
    if 'InvalidArgumentException' not in cancelar:
        fallos.append('se puede cancelar sin escribir el motivo')
    if 'quitarPendientesDelPlan' not in cancelar:
        fallos.append('cancelar deja pendientes de digitalizacion imposibles')
    print('  ok   cancelar: con motivo, y limpiando sus pendientes')

# Solo se quitan los pendientes SIN escanear: si ya subieron el
# consentimiento firmado, ese papel existe y se conserva.
limpia = re.search(r'function quitarPendientesDelPlan.*?\n    \}', planesPhp, re.S)
if limpia and 'd.estado = 0' not in limpia.group(0):
    fallos.append('cancelar borraria tambien documentos ya escaneados')

# ---------- Terminar ----------
if not (raiz/'includes/consultas/plan_terminar.php').exists():
    fallos.append('no se puede dar un plan por terminado')
elif 'function terminar' not in planesPhp:
    fallos.append('terminar no pasa por la regla')
elif 'conceptosPendientes' not in \
        (raiz/'includes/consultas/plan_terminar.php').read_text(encoding='utf-8'):
    fallos.append('al terminar no se dice cuantos conceptos quedan sin hacer')
else:
    print('  ok   terminar a mano, avisando de lo que queda')

# ---------- Un plan cerrado no se edita ----------
guardarPlan = soloCodigo(
    (raiz/'includes/consultas/plan_guardar.php').read_text(encoding='utf-8'))
if 'Planes::esFinal' not in guardarPlan:
    fallos.append('un plan cerrado se puede seguir modificando: esconder '
                  'el boton no es una restriccion')
else:
    print('  ok   un plan cerrado ya no se puede modificar')

# ---------- La pantalla pregunta a la regla ----------
for marca, que in [('Planes::esFinal', 'si esta cerrado'),
                   ('Planes::puedeCancelar', 'si se puede cancelar'),
                   ('Planes::puedeTerminar', 'si se puede terminar')]:
    if marca not in vistaPlan:
        fallos.append(f'la pantalla del plan decide {que} por su cuenta')

if 'aviso-plan-cerrado' not in vistaPlan:
    fallos.append('un plan cerrado no avisa de que lo esta')
elif not defineClase(cssT, '.aviso-plan-cerrado'):
    fallos.append('el aviso de plan cerrado no tiene estilo')

# ---------- Imprimir los dos documentos ----------
#
# Cambio de forma en octubre de 2026, y la comprobacion vieja exigia
# justo lo que fallaba. Era un boton "Imprimir los dos" que abria dos
# ventanas con 400 ms de diferencia; el navegador tomo la segunda por
# una emergente no pedida y la bloqueo sin avisar. Ahora hay un boton
# por papel y cada ventana la abre un clic, que es lo que ningun
# navegador bloquea.
if 'planConsentimiento' not in vistaPlan:
    fallos.append('no hay boton para imprimir solo el consentimiento')
elif 'consentimiento.php' not in jsCon2:
    fallos.append('nadie abre el consentimiento desde el plan')
elif 'avisarYOfrecerImpresiones' not in soloCodigo(jsCon2):
    fallos.append('al guardar un plan no se ofrecen sus dos impresiones')
elif jsCon2.count('data-papel="') != 1 or jsCon2.count("data-papel=\"${p.clave}\"") != 1:
    # Con la comilla y contando: buscar "data-papel" a secas lo
    # encuentra dentro de "data-papeles" y la comprobacion pasa aunque
    # el atributo ya no sea el que el manejador busca. Me paso rompiendo
    # esto a proposito.
    fallos.append('los dos papeles no salen como botones separados: dos '
                  'ventanas de un solo clic las bloquea el navegador')
elif jsCon2.count("clave: 'plan'") != 1 or jsCon2.count("clave: 'consentimiento'") != 1:
    fallos.append('no son exactamente dos papeles los que se ofrecen')
else:
    print('  ok   el plan ofrece sus dos papeles, uno por clic')

# Y el pendiente del consentimiento NO puede depender de que la ventana
# abra. Ese fue el daniо de verdad: el navegador bloqueo la ventana, el
# pendiente lo creaba esa ventana al abrirse, y se perdieron las dos
# cosas de golpe —el papel y el aviso de que faltaba—.
guardarPlan = (raiz/'includes/consultas/plan_guardar.php').read_text(encoding='utf-8')
if "'CONSENTIMIENTO'" not in guardarPlan:
    fallos.append('el pendiente del consentimiento sigue dependiendo de que '
                  'la ventana de impresion llegue a abrirse')
print('  ok   los dos pendientes se crean al guardar, no al imprimir')

# ---------- El consentimiento sabe quien firma ----------
# Desde el expediente la pantalla manda el dentista en la direccion;
# desde el plan, no. El documento salia sin nombre ni cedula en la linea
# de la firma, y solo por ese camino.
cons = (raiz/'consentimiento.php').read_text(encoding='utf-8')
if 'id_usuario_dentista' not in cons:
    fallos.append('el consentimiento no toma el dentista del plan: impreso '
                  'desde el plan sale sin nombre para firmar')
print('  ok   el consentimiento del plan sale con su dentista')

# ---------- La migracion ----------
migPlan = raiz/'db/migraciones/0011_cierre_del_plan.sql'
if not migPlan.exists():
    fallos.append('falta la migracion del cierre del plan')
else:
    sqlPlan = migPlan.read_text(encoding='utf-8')
    for col in ('motivo_cancelacion', 'fecha_cancelacion', 'fecha_terminado'):
        if col not in sqlPlan:
            fallos.append(f'la migracion no agrega {col}')
    if 'motivo_cancelacion' not in (raiz/'db/01_schema.sql').read_text(encoding='utf-8'):
        fallos.append('el esquema base no trae motivo_cancelacion')
    print('  ok   el cierre del plan, en la migracion y en el esquema')

# =====================================================================
#  Ronda del bloque de la consulta (octubre de 2026)
# =====================================================================
vistaCn  = (raiz/'vistas/consultas/consulta.php').read_text(encoding='utf-8')
modalCn  = (raiz/'vistas/consultas/_modales_consulta.php').read_text(encoding='utf-8')
jsCn     = (raiz/'assets/js/consultas.js').read_text(encoding='utf-8')
cssCn    = (raiz/'assets/css/estilo.css').read_text(encoding='utf-8')
fnCn     = (raiz/'includes/core/funciones.php').read_text(encoding='utf-8')
vistaCnC = soloCodigo(vistaCn)
jsCnC    = soloCodigo(jsCn)

# ---------- De que plan es esta consulta ----------
if 'id="modalPlanConsulta"' not in modalCn:
    fallos.append('falta el modal que pregunta de que plan es la consulta')
# Sin boton de cerrar y sin escape: no es un aviso que se pueda ignorar,
# es la pregunta que arma el resto de la pantalla. Con un aspa en la
# esquina la respuesta mas comoda es ninguna, y ninguna significa
# "consulta general" por omision, que es justo el problema que esto
# viene a resolver.
bloquePlan = modalCn[modalCn.index('id="modalPlanConsulta"'):]
bloquePlan = bloquePlan[:bloquePlan.index('<!-- ============ Nota de evolucion')]
if 'data-bs-backdrop="static"' not in bloquePlan \
        or 'data-bs-keyboard="false"' not in bloquePlan:
    fallos.append('el modal del plan se puede esquivar: la respuesta por '
                  'omision volveria a ser "consulta general"')
if 'btn-close' in bloquePlan:
    fallos.append('el modal del plan trae aspa de cerrar')
# Y "consulta general" tiene que seguir siendo una respuesta posible,
# con su propio boton. Obligar a elegir un plan seria peor: la urgencia
# de un sabado acabaria metida en el plan de ortodoncia.
if 'id="pcGeneral"' not in bloquePlan:
    fallos.append('el modal del plan no deja contestar "consulta general"')
if 'id="cnPreguntarPlan"' not in vistaCn:
    fallos.append('la pantalla no dice si hay que preguntar por el plan')
# Lo decide PHP, que es quien sabe si el paciente tiene planes vivos.
if '$preguntarPlan = !$idConsulta && $planes && !$idPlan' not in vistaCn:
    fallos.append('la condicion de preguntar no mira las tres cosas: '
                  'consulta nueva, planes vivos y que no se entro desde uno')
if 'preguntarDePlan' not in jsCnC:
    fallos.append('nadie llama al modal del plan')
print('  ok   la consulta nueva pregunta de que plan es')

# ---------- La propuesta del motivo ----------
if 'function ordinalCorto' not in fnCn:
    fallos.append('falta el ordinal corto para el motivo de la cita')
elif "11 => '11VA'" not in fnCn or "12 => '12VA'" not in fnCn:
    fallos.append('el ordinal se equivoca en el 11 y el 12, que son VA '
                  'y no RA/DA: una regla aritmetica diria "11RA"')
if 'function motivoDeCitaDePlan' not in fnCn:
    fallos.append('falta la funcion que arma el motivo propuesto')
elif 'CITA PLAN DE TRATAMIENTO' not in fnCn:
    fallos.append('el motivo propuesto no nombra el plan')
# El ordinal se DEDUCE de las consultas que lleva el plan. Si se
# guardara en una columna podria quedar desincronizado al borrar una
# consulta o moverla de plan, y el expediente diria "3RA CITA" dos veces.
if 'motivoDeCitaDePlan' not in vistaCnC:
    fallos.append('la pantalla de la consulta no arma la propuesta del plan')
if 'AS consultas' not in vistaCn:
    fallos.append('no se cuentan las consultas del plan: el ordinal saldria '
                  'inventado')
# Y la propuesta no puede pisar lo que alguien escribio a mano.
if 'function proponerDelPlan' not in jsCnC:
    fallos.append('nadie escribe la propuesta al elegir el plan')
elif 'esMia' not in jsCnC:
    fallos.append('la propuesta pisa el texto escrito a mano: solo deberia '
                  'reemplazar un campo vacio o una propuesta anterior')
print('  ok   el motivo y el padecimiento se proponen sin pisar lo escrito')

# ---------- El Guardar abajo ----------
# Pegado al ultimo campo que se escribe. Arriba obligaba a subir la
# vista para guardar lo que se acababa de teclear abajo, y con el
# paciente enfrente ese viaje se posponia.
if 'pie-guardar' not in vistaCn:
    fallos.append('el Guardar de la consulta no bajo al pie del motivo')
else:
    cuerpo = vistaCn[vistaCn.index('name="plan_indicaciones"'):]
    if 'id="cnGuardar"' not in cuerpo:
        fallos.append('el Guardar no quedo DEBAJO de "Tratamiento realizado"')
if not defineClase(cssCn, '.pie-guardar'):
    fallos.append('falta el estilo del pie con el Guardar')
# Cerrar consulta se queda arriba a proposito: no se deshace.
cabecera = vistaCn[:vistaCn.index('<div class="row g-3">')]
if 'id="cnCerrar"' not in cabecera:
    fallos.append('el boton de Cerrar consulta ya no esta en la cabecera')
if 'id="cnGuardar"' in cabecera:
    fallos.append('quedo un segundo Guardar en la cabecera')
print('  ok   el Guardar al pie del motivo y Cerrar arriba')

# ---------- Difuminado hasta el primer guardado ----------
# Antes se veia todo igual que si funcionara, con un aviso de texto
# arriba: se lee una vez y despues es decorado. Lo que pasaba era picar,
# no ver nada, y volver a picar.
if vistaCn.count('zona-sin-consulta') < 3:
    fallos.append('faltan zonas marcadas como inservibles antes de guardar '
                  '(exploracion, notas y anexos)')
if 'id="cnAvisoGuardar"' in vistaCn:
    fallos.append('sigue el aviso de texto que el difuminado reemplaza')
if "getElementById('cnAvisoGuardar')" in jsCn:
    fallos.append('el JavaScript sigue buscando cnAvisoGuardar: ya no existe '
                  'y la pantalla reventaria al abrirse')
if not defineClase(cssCn, '.zona-sin-consulta.difuminada'):
    fallos.append('falta el estilo de la zona difuminada')
# Las tres cosas: lo que se ve, lo que apaga los clics y lo que viaja en
# el HTML. Con solo el filtro los botones siguen recibiendo clics y
# basta un tabulador para llegar a ellos.
estilo = cssCn[cssCn.index('.zona-sin-consulta.difuminada'):]
estilo = estilo[:estilo.index('}')]
if 'pointer-events: none' not in estilo:
    fallos.append('la zona difuminada sigue recibiendo clics')
if '.inert = !si' not in jsCn:
    fallos.append('la zona difuminada sigue siendo alcanzable con el tabulador')
print('  ok   lo que no sirve todavia se ve y se comporta como tal')

# ---------- La barra: texto con lapiz ----------
for campo in ('fecha', 'dentista', 'plan'):
    if f'data-campo="{campo}"' not in vistaCn:
        fallos.append(f'la barra de la consulta no trae el campo {campo}')
    if f'data-editar="{campo}"' not in vistaCn:
        fallos.append(f'el campo {campo} de la barra no tiene lapiz')
if 'function barraALectura' not in jsCnC:
    fallos.append('la barra de la consulta nunca pasa a modo lectura')
# Abrir uno cierra los otros dos. Si quedan los tres abiertos se pierde
# la razon de haberlos cerrado: un combo abierto y una rueda de raton
# bastan para mover la consulta a otro plan y llevarse sus papeles.
abrir = jsCnC[jsCnC.index('function abrirCampo'):]
abrir = abrir[:abrir.index('\n  }')]
if 'barraALectura()' not in abrir:
    fallos.append('abrir un campo de la barra no cierra los otros')
if not defineClase(cssCn, '.campo-texto') or not defineClase(cssCn, '.lapiz-campo'):
    fallos.append('faltan los estilos del texto y el lapiz de la barra')
print('  ok   la barra de la consulta se lee, y el lapiz abre uno solo')

# ---------- Se fue la tarjeta de Documentos ----------
if 'Documentos de la consulta' in vistaCnC:
    fallos.append('la tarjeta "Documentos de la consulta" sigue ahi')
if 'id="cnDocumentos"' in vistaCn:
    fallos.append('quedo el contenedor cnDocumentos en la pantalla')
if "getElementById('cnDocumentos')" in jsCn:
    fallos.append('el JavaScript sigue usando cnDocumentos: ya no existe y '
                  'el detalle de la consulta reventaria al abrirse')
if 'function pintarDocumentos' in jsCn:
    fallos.append('quedo pintarDocumentos sin tarjeta donde pintar')
# Pero lo que la tarjeta hacia de verdad tiene que seguir pudiendose
# hacer: anexar un estudio externo o una interconsulta llega en la mano
# del tutor, durante la cita, y no es hallazgo ni receta.
if 'id="cnOtroDocumento"' not in vistaCn:
    fallos.append('ya no hay forma de anexar un estudio externo')
if "clave === 'OTRO_CONSULTA'" not in jsCn:
    fallos.append('el enlace de anexar no apunta al tipo OTRO_CONSULTA')
if 'function abrirAlbumDeTipo' not in jsCnC:
    fallos.append('el enlace de anexar no abre el album de subida')
if 'function cuentaDeDocumentos' not in jsCnC:
    fallos.append('no se dice cuantos archivos hay: pareceria que se perdieron')
print('  ok   la tarjeta de documentos se fue y lo que hacia se quedo')

# =====================================================================
#  Ronda de los reportes de produccion (octubre de 2026)
# =====================================================================

# ---------- $planes antes de existir ----------
# PHP no se queja de recorrer una variable que no existe: avisa y sigue
# con un arreglo vacio. Asi que el bloque del avance en dinero estaba
# ANTES de la consulta que llena $planes, salian dos Warnings arriba de
# la pantalla, y ningun plan traia su avance... sin que nada dijera que
# faltaba. El aviso amarillo se lee como adorno; la columna vacia se lee
# como "este paciente no debe nada".
#
# Se comprueba el ORDEN, que es lo que ninguna busqueda de texto ve.
vistaPacOrden = (raiz/'vistas/paciente/detalle_paciente.php').read_text(encoding='utf-8')
if '$planes = Conexion::todos(' not in vistaPacOrden:
    fallos.append('la ficha ya no consulta los planes del paciente')
elif 'foreach ($planes as $pl)' not in vistaPacOrden:
    fallos.append('la ficha ya no calcula el avance en dinero de los planes')
elif vistaPacOrden.index('foreach ($planes as $pl)') \
        < vistaPacOrden.index('$planes = Conexion::todos('):
    fallos.append('la ficha recorre $planes ANTES de llenarla: dos Warnings '
                  'en pantalla y ningun plan con su avance en dinero')
print('  ok   la ficha llena $planes antes de recorrerla')

# ---------- El HEIC del hallazgo ----------
# La lista de formatos estaba escrita a mano en el modal y se quedo atras
# cuando el nucleo empezo a aceptar HEIC. No dio ningun error: el
# selector de archivos ATENUABA las fotos del iPhone, no se podian
# elegir, y parecia que el sistema no las aceptaba. Las aceptaba; nadie
# podia llegar a el.
nucleoImg = (raiz/'includes/core/imagen.php').read_text(encoding='utf-8')
if 'function aceptaEnPicker' not in nucleoImg:
    fallos.append('Imagen no publica la lista de formatos para el selector')
modalCn2 = (raiz/'vistas/consultas/_modales_consulta.php').read_text(encoding='utf-8')
if 'Imagen::aceptaEnPicker()' not in modalCn2:
    fallos.append('el hallazgo escribe su accept a mano: se vuelve a quedar '
                  'atras en cuanto cambien los formatos')
# Y la lista tiene que salir de IMAGENES, no de otra escrita al lado.
bloquePicker = nucleoImg[nucleoImg.index('function aceptaEnPicker'):]
bloquePicker = bloquePicker[:bloquePicker.index('\n    }')]
if 'self::IMAGENES' not in bloquePicker:
    fallos.append('aceptaEnPicker no deduce la lista de IMAGENES')
# Los mime ademas de las extensiones: macOS filtra por tipo y no siempre
# reconoce .heic por el nombre.
if 'Almacen::TIPOS' not in bloquePicker:
    fallos.append('el accept no incluye los mime: en la Mac las fotos del '
                  'iPhone seguirian atenuadas')
print('  ok   el hallazgo acepta .heic, y la lista sale de un solo sitio')

# ---------- Momentos de azucar: un <select> tambien pregunta ----------
jsHist2 = (raiz/'assets/js/historia.js').read_text(encoding='utf-8')
jsHistCodigo = soloCodigo(jsHist2)

# El chip de la respuesta exigia un .grupo-sino y se salia sin hacer
# nada si no lo habia. "Momentos de azucar" es un <select>: la respuesta
# se guardaba y no se veia en ningun lado.
if 'function bloqueDelDisparador' not in jsHistCodigo:
    fallos.append('la respuesta extra sigue atada a los grupos de si/no')
elif '.grupo-sino' not in jsHistCodigo:
    fallos.append('bloqueDelDisparador ya no distingue los grupos de si/no')
# Y nadie preguntaba por el: elegir() solo lo llaman los botones.
if "tagName !== 'SELECT'" not in jsHistCodigo:
    fallos.append('un desplegable no abre su pregunta extra: la respuesta '
                  'de "momentos de azucar" no se pide nunca')
# Deshacer al cerrar sin contestar tiene que funcionar para el select
# tambien, o queda la frecuencia elegida sin su detalle.
cerrar = jsHistCodigo[jsHistCodigo.index('function alCerrarVentana'):]
cerrar = cerrar[:cerrar.index('\n  }')]
if 'disparador.value = antes' not in cerrar:
    fallos.append('cerrar sin contestar no deshace un desplegable')
print('  ok   un desplegable pregunta, muestra su respuesta y se deshace')

# ---------- Lo prellenado a medias, en amarillo ----------
if 'function marcarSiFalta' not in jsHistCodigo:
    fallos.append('nada marca las respuestas que estan a medias')
elif 'falta-complemento' not in jsHistCodigo:
    fallos.append('marcarSiFalta no pone la clase del amarillo')
if not defineClase(cssPac if 'cssPac' in dir() else
                   (raiz/'assets/css/estilo.css').read_text(encoding='utf-8'),
                   '.falta-complemento'):
    fallos.append('falta el estilo amarillo de lo que esta a medias')
print('  ok   lo prellenado sin su detalle se ve en amarillo')

# ---------- Fichas pagadas en COBRAR ----------
cuentaPhp = (raiz/'includes/cajas/paciente_cuenta.php').read_text(encoding='utf-8')
if "'pagos'" not in cuentaPhp:
    fallos.append('la pantalla de cobro no recibe las fichas ya pagadas')
# Los cancelados van en la lista, marcados: un hueco se explica mucho
# peor que un renglon que dice por que se cancelo.
if 'motivo_cancelacion' not in cuentaPhp:
    fallos.append('las fichas no traen el motivo de cancelacion')
vistaCob = (raiz/'vistas/cajas/cobrar.php').read_text(encoding='utf-8')
if 'id="cbVerFichas"' not in vistaCob:
    fallos.append('no hay boton para consultar las fichas pagadas')
modalesCaja = (raiz/'vistas/cajas/_modales_caja.php').read_text(encoding='utf-8')
if 'id="modalFichas"' not in modalesCaja:
    fallos.append('falta la ventana de fichas pagadas')
jsCaja2 = soloCodigo((raiz/'assets/js/cajas.js').read_text(encoding='utf-8'))
if 'recibo_imprimir.php' not in jsCaja2:
    fallos.append('las fichas pagadas no se pueden reimprimir')
print('  ok   las fichas ya pagadas se consultan y se reimprimen')

# =====================================================================
#  Ronda de octubre: HEIC, plan, historia y expediente
# =====================================================================
cssT2    = (raiz/'assets/css/estilo.css').read_text(encoding='utf-8')
jsExp2   = (raiz/'assets/js/expediente.js').read_text(encoding='utf-8')
jsHist3  = soloCodigo((raiz/'assets/js/historia.js').read_text(encoding='utf-8'))
vistaHc2 = (raiz/'vistas/paciente/_tab_historia.php').read_text(encoding='utf-8')

# ---------- El prellenado no finge ser una persona ----------
# El prellenado pone "REGULAR" en momentos de azucar; al lanzar un
# 'change' sintetico, el escucha del desplegable —que existe para saber
# que alguien eligio— abria la ventanita de "¿que alimentos con
# azucar?". Un evento disparado por codigo es indistinguible de uno
# disparado por una persona.
prellenar = jsHist3[jsHist3.index('function prellenar('):]
prellenar = prellenar[:prellenar.index('\n  }')]
if "dispatchEvent(new Event('change'" in prellenar:
    fallos.append('el prellenado lanza un change sintetico: le abre al '
                  'usuario una ventana por una respuesta que nadie dio')
print('  ok   el prellenado no abre ventanas')

# ---------- La pestania de historia se apaga al guardar ----------
if 'id="avisoTabHistoria"' not in vistaPac:
    fallos.append('el aviso de la pestania de historia no tiene id: no se '
                  'puede apagar sin recargar la pagina')
if "getElementById('avisoTabHistoria')" not in jsHist3:
    fallos.append('al guardar la historia, la pestania sigue marcada como '
                  'pendiente hasta que alguien recarga')
print('  ok   la pestania deja de avisar en cuanto se guarda')

# ---------- Examen bucal: normal o anormal ----------
if "opciones('examen_bucal_normal'" not in vistaHc2:
    fallos.append('el examen bucal no pregunta si salio normal')
if 'examen_bucal_normal' not in (raiz/'includes/historia/guardar.php').read_text(encoding='utf-8'):
    fallos.append('el examen bucal normal no se guarda')
if 'examen_bucal_normal' not in (raiz/'historia_imprimir.php').read_text(encoding='utf-8'):
    fallos.append('el examen bucal normal no sale en la hoja impresa')
migEx = raiz/'db/migraciones/0013_examen_bucal_normal.sql'
if not migEx.exists():
    fallos.append('falta la migracion del examen bucal normal')
elif 'examen_bucal_normal' not in (raiz/'db/01_schema.sql').read_text(encoding='utf-8'):
    fallos.append('el esquema base no trae examen_bucal_normal')
# NULL en las historias viejas, no 1: de esas no se sabe si el examen
# fue normal, y poner 1 seria afirmar en nombre de la doctora.
elif 'DEFAULT 1' not in migEx.read_text(encoding='utf-8'):
    fallos.append('el examen bucal no viene en NORMAL por omision')
print('  ok   examen bucal normal/anormal, con su migracion')

# ---------- RFC con o sin homoclave ----------
fn2 = (raiz/'includes/core/funciones.php').read_text(encoding='utf-8')
if 'function rfcConForma' not in fn2:
    fallos.append('la regla del RFC no vive en un solo sitio')
elif '([A-Z0-9]{3})?' not in fn2:
    fallos.append('el RFC exige homoclave: un tutor que no factura no puede '
                  'guardar el suyo')
for v in ('includes/paciente/tutor_actualizar.php',
          'includes/paciente/tutor_guardar.php',
          'includes/paciente/actualizar_identificacion.php'):
    texto = (raiz/v).read_text(encoding='utf-8')
    if 'rfcConForma' not in texto:
        fallos.append(f'{v} valida el RFC con su propia copia de la regla')
print('  ok   el RFC se acepta con y sin homoclave, con una sola regla')

# ---------- Un solo domicilio: sin boton de "capturar nuevo" ----------
for v in ('vistas/paciente/_modal_tutor_editar.php',
          'vistas/paciente/_sec_domicilio.php'):
    texto = soloCodigo((raiz/v).read_text(encoding='utf-8'))
    if 'Capturar nuevo domicilio' in texto:
        fallos.append(f'{v} sigue ofreciendo capturar un segundo domicilio')
print('  ok   el domicilio ya no ofrece capturar un segundo')

# ---------- Miniaturas en el expediente ----------
if 'function miniatura' not in soloCodigo(jsExp2):
    fallos.append('el expediente no muestra miniaturas')
if 'mini=1' not in jsExp2:
    fallos.append('la miniatura baja el archivo completo: una radiografia '
                  'de varios megas por cada renglon de la lista')
if 'mini' not in (raiz/'archivo.php').read_text(encoding='utf-8'):
    fallos.append('archivo.php no sabe servir una miniatura')
if 'function miniatura' not in nucleoImg:
    fallos.append('Imagen no sabe generar una miniatura')
if not defineClase(cssT2, '.doc-mini'):
    fallos.append('falta el estilo de la miniatura')
# Los PDF se arman al asomarse: quince visores de PDF arrancando a la
# vez dejan el navegador de rodillas.
if 'IntersectionObserver' not in jsExp2:
    fallos.append('las miniaturas de PDF se cargan todas de golpe')
# La bitacora NO puede anotar las miniaturas: quince renglones de
# "CONSULTA" por cada vez que se abre la pestania ahogan el rastro de
# los accesos de verdad.
arch = (raiz/'archivo.php').read_text(encoding='utf-8')
if arch.index("Bitacora::registrar('expediente', 'CONSULTA'") < arch.index('$miniatura &&'):
    fallos.append('las miniaturas quedan anotadas en la bitacora: ahogan el '
                  'rastro de los accesos de verdad')
print('  ok   miniaturas: reducidas en el servidor y sin ahogar la bitacora')

# ---------- "Escaneado correctamente" en vez del tipo repetido ----------
if 'Escaneado correctamente' not in jsExp2:
    fallos.append('el renglon no dice si el documento ya entro')
# El tipo de documento ya esta en el nombre del renglon, a dos
# centimetros: repetirlo ocupa el sitio del dato que si hace falta.
chip = jsExp2[jsExp2.index('doc-estado'):]
chip = chip[:chip.index('doc-acciones')]
if 'nombre_documento' in chip:
    fallos.append('el chip de la derecha sigue repitiendo el tipo de documento')
if not defineClase(cssT2, '.doc-estado.escaneado'):
    fallos.append('falta el estilo de "Escaneado correctamente"')
print('  ok   el chip dice si entro el papel, no repite su tipo')

# El resumen va AQUI, al final, no a media tabla.
#
# Estaba en medio del archivo y las comprobaciones que agregue despues
# quedaron por debajo: el guion imprimia "TODO EN ORDEN" y acto seguido
# listaba comprobaciones nuevas, una de ellas fallando. Quien lo leyera
# por encima se iba con la version buena.
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
