#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Verificacion estatica del proyecto.

No sustituye a probar el sistema, pero atrapa la clase de errores que
solo se descubren en tiempo de ejecucion y despues de varios clics:
columnas que ya no existen, rutas AJAX apuntando a archivos borrados,
permisos usados en el codigo pero nunca insertados en la base.

Se ejecuta con:   python3 tools/verificar.py
"""

import re, os, sys, glob, json, collections

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
fallos, avisos = [], []


def err(m): fallos.append(m)
def avi(m): avisos.append(m)
def ok(m):  print("  ok   " + m)


def sin_comentarios_sql(t):
    return re.sub(r'--[^\n]*', '', t)


def leer(ruta):
    return open(os.path.join(BASE, ruta), encoding='utf-8').read()


print("Verificando el proyecto...\n")

# =====================================================================
# 1. ESQUEMA: tablas y columnas
# =====================================================================
schema = sin_comentarios_sql(leer('db/01_schema.sql'))

tablas = {}
for m in re.finditer(r'CREATE TABLE\s+(\w+)\s*\((.*?)\n\)\s*ENGINE', schema, re.S):
    nombre, cuerpo = m.group(1), m.group(2)
    cols = []
    for linea in cuerpo.split('\n'):
        linea = linea.strip()
        if not linea or linea.startswith((')', 'PRIMARY KEY', 'UNIQUE KEY', 'KEY',
                                          'CONSTRAINT', 'INDEX', 'FOREIGN KEY')):
            continue
        c = re.match(r'`?(\w+)`?\s+\w', linea)
        if c:
            cols.append(c.group(1))
    tablas[nombre] = cols

vistas = re.findall(r'CREATE OR REPLACE VIEW\s+(\w+)', schema)
ok(f"{len(tablas)} tablas y {len(vistas)} vistas declaradas")

# ---- llaves foraneas ----
n_fk = 0
for m in re.finditer(
        r'CONSTRAINT\s+(\w+)\s+FOREIGN KEY\s*\(([^)]+)\)\s*\n?\s*REFERENCES\s+(\w+)\s*\(([^)]+)\)',
        schema):
    n_fk += 1
    fk, cols, tref, cref = m.groups()
    if tref not in tablas:
        err(f"FK {fk}: la tabla referenciada '{tref}' no existe")
        continue
    for c in [x.strip().strip('`') for x in cref.split(',')]:
        if c not in tablas[tref]:
            err(f"FK {fk}: la columna '{c}' no existe en '{tref}'")
ok(f"{n_fk} llaves foraneas revisadas")

# =====================================================================
# 2. INSERTS: columnas y aridad
# =====================================================================
for archivo in ['db/02_catalogos.sql', 'db/03_datos_demo.sql']:
    txt = sin_comentarios_sql(leer(archivo))
    n_ins = 0

    for m in re.finditer(r'INSERT INTO\s+`?(\w+)`?\s*\(([^)]*)\)', txt):
        n_ins += 1
        t = m.group(1)
        cols = [c.strip().strip('`') for c in m.group(2).split(',')]
        if t not in tablas:
            err(f"{archivo}: INSERT a la tabla inexistente '{t}'")
            continue
        for c in cols:
            if c not in tablas[t]:
                err(f"{archivo}: la columna '{c}' no existe en '{t}'")

    # aridad de cada tupla
    for m in re.finditer(r'INSERT INTO\s+`?(\w+)`?\s*\(([^)]*)\)\s*VALUES\s*(.*?);',
                         txt, re.S):
        t = m.group(1)
        n = len(m.group(2).split(','))
        cuerpo = m.group(3)

        prof, actual, tuplas, en_cadena = 0, '', [], False
        for ch in cuerpo:
            if ch == "'":
                en_cadena = not en_cadena
            if not en_cadena:
                if ch == '(':
                    prof += 1
                    if prof == 1:
                        actual = ''
                        continue
                elif ch == ')':
                    prof -= 1
                    if prof == 0:
                        tuplas.append(actual)
                        continue
            if prof >= 1:
                actual += ch

        for i, tp in enumerate(tuplas):
            d, en_cadena, vals = 0, False, 1
            for ch in tp:
                if ch == "'":
                    en_cadena = not en_cadena
                if en_cadena:
                    continue
                if ch == '(':
                    d += 1
                elif ch == ')':
                    d -= 1
                elif ch == ',' and d == 0:
                    vals += 1
            if vals != n:
                err(f"{archivo}: {t}, tupla #{i+1} trae {vals} valores "
                    f"pero se declararon {n} columnas")

    ok(f"{os.path.basename(archivo)}: {n_ins} INSERT validados")

# =====================================================================
# 3. INTEGRIDAD DE LOS DATOS DEMO
# =====================================================================
cat  = sin_comentarios_sql(leer('db/02_catalogos.sql'))
demo = sin_comentarios_sql(leer('db/03_datos_demo.sql'))


def ids_insertados(texto, tabla, col=0):
    """Ids explicitos de la columna `col` de las tuplas de un INSERT."""
    m = re.search(r'INSERT INTO ' + tabla + r' \([^)]*\) VALUES(.*?);', texto, re.S)
    if not m:
        return set()
    return set(int(x) for x in re.findall(r'^\((\d+),', m.group(1), re.M))


def cuenta_tuplas(texto, tabla):
    m = re.search(r'INSERT INTO ' + tabla + r' \([^)]*\) VALUES(.*?);', texto, re.S)
    return len(re.findall(r'^\(', m.group(1), re.M)) if m else 0


n_colonias = cuenta_tuplas(cat, 'colonia')
n_ocupa    = cuenta_tuplas(cat, 'ocupacion')
n_dientes  = cuenta_tuplas(cat, 'diente')
ok(f"catalogos: {n_colonias} colonias, {n_ocupa} ocupaciones, {n_dientes} dientes")

if n_colonias < 400:
    err(f"solo hay {n_colonias} colonias; se esperaban las ~454 del Excel")

# claves que el codigo PHP da por hechas
for clave in ['NINGUNA', 'ESTUDIANTE']:
    if f"('{clave}'," not in cat:
        err(f"la ocupacion con clave '{clave}' no esta en el catalogo, "
            f"pero ocupacionPorEdad() la busca")

# domicilios del demo -> colonias existentes
m = re.search(r'INSERT INTO domicilio \([^)]*\) VALUES(.*?);', demo, re.S)
if m:
    usadas = set(int(x) for x in re.findall(r'^\(\d+,\s*(\d+),', m.group(1), re.M))
    if usadas and max(usadas) > n_colonias:
        err(f"el demo usa id_colonia {max(usadas)} pero solo hay {n_colonias}")
    else:
        ok(f"demo: {len(usadas)} colonias referenciadas, todas dentro del catalogo")

# personas <-> vinculos
personas = set()
for mm in re.finditer(r'INSERT INTO persona \([^)]*\) VALUES(.*?);', demo, re.S):
    personas |= set(int(x) for x in re.findall(r'^\((\d+),', mm.group(1), re.M))

dom_ids = ids_insertados(demo, 'domicilio')
m = re.search(r'INSERT INTO persona_domicilio \([^)]*\) VALUES(.*?);', demo, re.S)
if m:
    pares = re.findall(r'\((\d+),\s*(\d+),\s*\d+\)', m.group(1))
    faltan_p = set(int(a) for a, b in pares) - personas
    faltan_d = set(int(b) for a, b in pares) - dom_ids
    if faltan_p:
        err(f"persona_domicilio apunta a personas inexistentes: {sorted(faltan_p)}")
    if faltan_d:
        err(f"persona_domicilio apunta a domicilios inexistentes: {sorted(faltan_d)}")
    if not faltan_p and not faltan_d:
        ok(f"demo: {len(personas)} personas y {len(dom_ids)} domicilios, sin huerfanos")

# mayusculas en los datos capturables
for tabla, idx in [('persona', 0), ('domicilio', 0)]:
    for mm in re.finditer(r'INSERT INTO ' + tabla + r' \([^)]*\) VALUES(.*?);', demo, re.S):
        for fila in re.findall(r'\([^()]*\)', mm.group(1)):
            for cadena in re.findall(r"'([^']*)'", fila):
                # se ignoran fechas y cadenas sin letras
                if re.search(r'[a-z]', cadena) and not re.match(r'^\d{4}-\d{2}-\d{2}', cadena):
                    avi(f"demo/{tabla}: '{cadena}' no esta en mayusculas")
                    break

# =====================================================================
# 4. PERMISOS
# =====================================================================
definidos = set(re.findall(r"\(\d+,'([a-z]+\.[a-z_]+)','", cat))
usados = set()
for php in glob.glob(f'{BASE}/**/*.php', recursive=True):
    txt = open(php, encoding='utf-8').read()
    usados |= set(re.findall(r"endpoint\('([^']+)'\)", txt))
    usados |= set(re.findall(r"Auth::puede\('([^']+)'\)", txt))
    usados |= set(re.findall(r"exigirPermiso\('([^']+)'\)", txt))
    usados |= set(re.findall(r"'permiso'\s*=>\s*'([^']+)'", txt))

huerfanos = usados - definidos
if huerfanos:
    err(f"permisos usados en el codigo pero ausentes del catalogo: {sorted(huerfanos)}")
else:
    ok(f"{len(usados)} permisos usados, todos existen en el catalogo")

sin_usar = definidos - usados
if sin_usar:
    avi(f"permisos en el catalogo que nadie verifica todavia: {sorted(sin_usar)}")

# =====================================================================
# 5. RUTAS AJAX DEL JAVASCRIPT
# =====================================================================
rutas = set()
for js in glob.glob(f'{BASE}/assets/js/*.js'):
    rutas |= set(re.findall(r"App\.api\('([^']+)'", open(js, encoding='utf-8').read()))

rotas = [r for r in rutas if not os.path.isfile(os.path.join(BASE, 'includes', r))]
if rotas:
    err(f"el JavaScript llama a endpoints que no existen: {sorted(rotas)}")
else:
    ok(f"{len(rutas)} rutas AJAX, todas apuntan a un archivo real")

# endpoints sin usar
todos_ep = set()
for f in glob.glob(f'{BASE}/includes/*/*.php'):
    rel = os.path.relpath(f, os.path.join(BASE, 'includes'))
    if not rel.startswith('core'):
        todos_ep.add(rel)
sin_llamar = todos_ep - rutas
if sin_llamar:
    avi(f"endpoints que ningun JavaScript llama: {sorted(sin_llamar)}")

# =====================================================================
# 6. VISTAS DEL ROUTER
# =====================================================================
index = leer('index.php')
for m in re.finditer(r"'archivo'\s*=>\s*'([^']+)'", index):
    destino = os.path.join(BASE, 'vistas', m.group(1))
    if not os.path.isfile(destino):
        err(f"index.php enruta a vistas/{m.group(1)}, que no existe")
ok("todas las vistas del router existen")

# =====================================================================
# 7. INCLUDES DE PHP
# =====================================================================
for php in glob.glob(f'{BASE}/**/*.php', recursive=True):
    txt = re.sub(r'/\*.*?\*/', '', open(php, encoding='utf-8').read(), flags=re.S)
    rel = os.path.relpath(php, BASE)

    for m in re.finditer(r"require(?:_once)?\s+__DIR__\s*\.\s*'([^']+)'", txt):
        destino = os.path.normpath(os.path.join(os.path.dirname(php), m.group(1).lstrip('/')))
        if not os.path.isfile(destino):
            err(f"{rel}: require a '{m.group(1)}' que no existe")

    for m in re.finditer(r"require(?:_once)?\s+dirname\(__DIR__(?:,\s*(\d+))?\)\s*\.\s*'([^']+)'", txt):
        niveles = int(m.group(1) or 1)
        d = os.path.dirname(php)
        for _ in range(niveles):
            d = os.path.dirname(d)
        if not os.path.isfile(os.path.normpath(d + m.group(2))):
            err(f"{rel}: require a '{m.group(2)}' que no existe")

    for m in re.finditer(r"require(?:_once)?\s+RUTA_VISTAS\s*\.\s*'([^']+)'", txt):
        if not os.path.isfile(os.path.normpath(f"{BASE}/vistas" + m.group(1))):
            err(f"{rel}: require a vistas{m.group(1)} que no existe")
ok("todos los require apuntan a un archivo real")

# =====================================================================
# 8. BALANCE DE BLOQUES
# =====================================================================
for php in glob.glob(f'{BASE}/**/*.php', recursive=True):
    txt = open(php, encoding='utf-8').read()
    rel = os.path.relpath(php, BASE)

    limpio = re.sub(r"'(?:\\.|[^'\\])*'", "''", txt)
    limpio = re.sub(r'"(?:\\.|[^"\\])*"', '""', limpio)
    limpio = re.sub(r'/\*.*?\*/', '', limpio, flags=re.S)
    limpio = re.sub(r'//[^\n]*', '', limpio)

    codigo = ''.join(re.findall(r'<\?php(.*?)(?:\?>|$)', limpio, re.S)
                     + re.findall(r'<\?=(.*?)\?>', limpio, re.S))
    if codigo.count('{') != codigo.count('}'):
        err(f"{rel}: llaves desbalanceadas")
    if codigo.count('(') != codigo.count(')'):
        err(f"{rel}: parentesis desbalanceados")

# El JavaScript se valida con el propio interprete: contar llaves a mano
# se equivoca con las expresiones regulares y las plantillas.
import subprocess
tiene_node = subprocess.run(['which', 'node'], capture_output=True).returncode == 0
if tiene_node:
    for js in sorted(glob.glob(f'{BASE}/assets/js/*.js')):
        r = subprocess.run(['node', '--check', js], capture_output=True, text=True)
        if r.returncode != 0:
            primera = r.stderr.strip().split('\n')
            detalle = next((l for l in primera if 'Error' in l), primera[0] if primera else '')
            err(f"{os.path.basename(js)}: error de sintaxis — {detalle.strip()}")
    ok("sintaxis de JavaScript validada con node")
else:
    avi("node no esta disponible: no se pudo validar la sintaxis del JavaScript")
ok("balance de llaves en PHP")

# =====================================================================
# 9. IDS QUE EL JAVASCRIPT ESPERA ENCONTRAR EN LAS VISTAS
# =====================================================================
html = ''
for v in glob.glob(f'{BASE}/vistas/**/*.php', recursive=True):
    html += open(v, encoding='utf-8').read()

ids_html = set(re.findall(r'id="([\w-]+)"', html))

# El JavaScript tambien inyecta HTML con ids propios (los modales de
# SweetAlert, por ejemplo). Esos son igual de validos.
js_todo = ''
for js in glob.glob(f'{BASE}/assets/js/*.js'):
    js_todo += open(js, encoding='utf-8').read()
ids_html |= set(re.findall(r'id="([\w-]+)"', js_todo))
ids_html |= set(re.findall(r"id='([\w-]+)'", js_todo))

for js in ['paciente.js', 'configuracion.js', 'expediente.js']:
    ruta = os.path.join(BASE, 'assets/js', js)
    if not os.path.isfile(ruta):
        continue
    txt = open(ruta, encoding='utf-8').read()
    buscados = set(re.findall(r"getElementById\('([\w-]+)'\)", txt))
    faltan = {i for i in buscados if i not in ids_html}
    if faltan:
        err(f"{js} busca ids que ninguna vista define: {sorted(faltan)}")

# Los ids que se le pasan a ControlDomicilio van en su propio bloque
for m in re.finditer(r'new ControlDomicilio\(\{(.*?)\}\)', js_todo, re.S):
    for idjs in re.findall(r"^\s+\w+:\s*'([\w-]+)',", m.group(1), re.M):
        if idjs not in ids_html:
            err(f"ControlDomicilio recibe el id '{idjs}', que ninguna vista define")

ok("los ids que busca el JavaScript existen en las vistas")

# =====================================================================
# RESULTADO
# =====================================================================

# =====================================================================
#  Claves que la consulta no trae
# =====================================================================
# El aviso que vio la clienta en pantalla:
#
#     Undefined array key "valor" in _resumen_contacto.php on line 71
#
# La columna se llama valor_contacto. Escribirla corta no truena nada:
# PHP avisa y entrega una cadena vacia, asi que el telefono del
# paciente simplemente desaparece de la ficha. Un dato que falta sin
# que nada falle es peor que un error, porque nadie lo busca.
#
# Se revisa solo cuando la consulta lista sus columnas una por una.
# Con "SELECT algo.*" no hay forma de saber desde aqui que trae, y
# adivinarlo llenaria esto de falsos positivos que acabarian
# ensenniando a ignorar el verificador.
import re as _re

# $var = Conexion::todos('SELECT ... FROM ...', ...)  /  ::uno(...)
PEDIDO = _re.compile(
    r"\$(\w+)\s*=\s*Conexion::(?:todos|uno)\(\s*'(SELECT\s.*?)'\s*[,)]",
    _re.S | _re.I)

def _tablaDelAlias(sql: str, alias: str):
    """Las columnas de la tabla que en esta consulta se llama `alias`."""
    # FROM tabla alias  |  JOIN tabla alias  |  FROM tabla AS alias
    m = _re.search(
        r'\b(?:FROM|JOIN)\s+(\w+)\s+(?:AS\s+)?' + _re.escape(alias) + r'\b',
        sql, _re.I)
    if m is None:
        # Tambien puede ser la tabla misma, sin alias: "FROM pago" y
        # luego "pago.*".
        m = _re.search(r'\b(?:FROM|JOIN)\s+(' + _re.escape(alias) + r')\b',
                       sql, _re.I)
    if m is None:
        return None
    nombre = m.group(1)
    # Las vistas (v_paciente, v_domicilio) no estan en tablas{}: sus
    # columnas salen de un SELECT que no se analiza aqui.
    return tablas.get(nombre)


def columnasQueTrae(sql: str):
    """Los nombres con los que llega cada columna al arreglo de PHP."""
    # El FROM que cierra la lista de columnas es el que esta FUERA de
    # todo parentesis. Cortar en el primero que aparezca parte la lista
    # en el FROM de la primera subconsulta, y todo lo que viene
    # despues —incluido su AS— se pierde.
    cuerpo = _re.sub(r'^\s*SELECT\s+', '', sql.strip(), flags=_re.I)
    hondo = 0
    for mf in _re.finditer(r'\(|\)|\bFROM\b', cuerpo, _re.I):
        if mf.group(0) == '(': hondo += 1
        elif mf.group(0) == ')': hondo -= 1
        elif hondo == 0:
            cuerpo = cuerpo[:mf.start()]
            break

    # Las subconsultas y las funciones traen comas que no separan
    # columnas: CONCAT_WS(", ", a, b) es UNA columna, no tres.
    plano, hondo = [], 0
    actual = ''
    for ch in cuerpo:
        if ch == '(': hondo += 1
        elif ch == ')': hondo -= 1
        if ch == ',' and hondo == 0:
            plano.append(actual); actual = ''
        else:
            actual += ch
    plano.append(actual)

    nombres = set()
    for trozo in plano:
        trozo = trozo.strip()
        if not trozo:
            continue
        if trozo.endswith('*'):
            # "cp.*" no es un callejon sin salida: el alias se resuelve
            # contra el FROM/JOIN y las columnas salen del esquema.
            #
            # Saltarse estas consultas dejaba fuera justo la que fallo
            # en pantalla —SELECT cp.*, mc.nombre... — y una
            # comprobacion que se rinde ante el caso que la motivo no
            # sirve de nada. "SELECT *" a secas, sin alias, si se deja
            # pasar: ahi habria que adivinar la tabla.
            alias = trozo[:-1].rstrip('.').strip()
            if not alias:
                return None
            tabla = _tablaDelAlias(sql, alias)
            if tabla is None:
                return None
            nombres.update(c.lower() for c in tabla)
            continue
        alias = _re.search(r'\bAS\s+(\w+)\s*$', trozo, _re.I)
        if alias:
            nombres.add(alias.group(1).lower())
            continue
        nombres.add(trozo.split('.')[-1].strip().lower())
    return nombres

import pathlib as _pathlib
_raiz = _pathlib.Path(BASE)

revisadas = 0
for archivo in sorted(_raiz.rglob('*.php')):
    if 'db/' in str(archivo) or 'tools/' in str(archivo):
        continue
    txt = archivo.read_text(encoding='utf-8', errors='ignore')

    for m in PEDIDO.finditer(txt):
        var, sql = m.group(1), m.group(2)
        trae = columnasQueTrae(sql)
        if trae is None:
            continue

        # Solo lo que va DESPUES de esta consulta y antes de que la
        # misma variable se reasigne. En documentos.php hay tres
        # consultas distintas guardadas en $doc, y mezclarlas hacia que
        # las columnas de una se reclamaran a las otras.
        desde = m.end()
        siguiente = PEDIDO.search(txt, desde)
        while siguiente is not None and siguiente.group(1) != var:
            siguiente = PEDIDO.search(txt, siguiente.end())
        ambito = txt[desde:siguiente.start() if siguiente else len(txt)]

        # Los parciales que la vista incluye leen SUS variables: la
        # consulta de $contactos vive en detalle_paciente.php y
        # $c['valor_contacto'] se escribe en _resumen_contacto.php.
        # Mirando un archivo a la vez, el error de la clave mal escrita
        # cae justo en la rendija entre los dos.
        piezas = [(archivo, ambito, desde)]
        for inc in _re.finditer(
                r"require(?:_once)?\s+__DIR__\s*\.\s*'(/[\w./-]+\.php)'", ambito):
            hijo = (archivo.parent / inc.group(1).lstrip('/')).resolve()
            if hijo.exists():
                piezas.append((hijo, hijo.read_text(encoding='utf-8',
                                                    errors='ignore'), 0))

        # Donde se leen las claves. Dos formas, y cada una con su
        # propio alcance:
        #
        #   - directo:   $pac['nombres']        -> toda la pieza
        #   - por lote:  foreach ($lista as $c) -> SOLO dentro del bucle
        #
        # Lo segundo importa: $c es el apodo mas usado del proyecto y
        # hay media docena de bucles que lo reutilizan para cosas
        # distintas. Sin acotar, las columnas de una consulta se le
        # reclamaban al bucle de otra y salian cinco fallos inventados.
        def cuerpoDelForeach(texto: str, desdeAhi: int) -> str:
            cierre = texto.find('endforeach', desdeAhi)
            llave  = texto.find('}', desdeAhi)
            if cierre == -1: cierre = len(texto)
            if llave  == -1: llave  = len(texto)
            return texto[desdeAhi:min(cierre, llave)]

        # Las claves que el propio codigo ESCRIBE no son columnas que
        # falten: son datos que se agregan al renglon despues de
        # traerlo. El arbol del expediente cuelga casi entero de ahi.
        escritas = set()
        for _, cuerpo, _ in piezas:
            escritas |= {
                e.group(1).lower() for e in _re.finditer(
                    r"\$\w+\s*\[\s*'(\w+)'\s*\]\s*(?:=[^=]|\[)", cuerpo)
            }

        revisadas += 1
        for donde, cuerpo, base in piezas:
            tramos = [(var, cuerpo, 0)]
            for f in _re.finditer(
                    r'foreach\s*\(\s*\$' + var
                    + r'\s+as\s+(?:\$\w+\s*=>\s*)?\$(\w+)\s*\)', cuerpo):
                tramos.append((f.group(1), cuerpoDelForeach(cuerpo, f.end()), f.end()))

            for apodo, tramo, inicio in tramos:
                for uso in _re.finditer(
                        r'\$' + apodo + r"\s*\[\s*'(\w+)'\s*\]", tramo):
                    clave = uso.group(1).lower()
                    if clave in trae or clave in escritas:
                        continue
                    entero = txt if donde == archivo else cuerpo
                    linea = entero[:base + inicio + uso.start()].count('\n') + 1
                    # Sin repetir: un parcial incluido tres veces —el
                    # resumen de contacto se pide una vez por bloque—
                    # daria el mismo fallo tres veces y pareceria que
                    # hay tres errores donde hay uno.
                    aviso = (f'{donde.relative_to(_raiz)}:{linea}: se lee '
                             f"${apodo}['{uso.group(1)}'] pero la consulta de "
                             f'${var} no trae esa columna. PHP no truena: '
                             f'avisa y deja el dato vacio.')
                    if aviso not in fallos:
                        fallos.append(aviso)

print(f'  ok   {revisadas} consultas: ninguna clave leida falta en su SELECT')


print()
# ---------- PHP que se colo en un archivo .js ----------
# Un "<?php" o un "<?= ... ?>" dentro de una plantilla de JavaScript no
# es codigo: es texto, y se imprime tal cual en la pantalla del usuario.
# Ni el navegador ni "node --check" se quejan, porque para los dos es
# una cadena perfectamente valida. Solo se descubre mirando la pantalla,
# que es la peor forma de descubrir un error.
for _archivo in sorted(glob.glob('assets/js/*.js')):
    _texto = open(_archivo, encoding='utf-8').read()
    for _marca in ('<?php', '<?=', '<?/'):
        if _marca in _texto:
            _linea = _texto[:_texto.index(_marca)].count('\n') + 1
            fallos.append(f'{_archivo}:{_linea}: "{_marca}" dentro de un .js. '
                          'PHP no corre ahi: se imprimiria en pantalla.')
print('  ok   ningun .js trae PHP dentro')


# ---------- Columnas que las consultas piden y la tabla no tiene ----------
# Una columna mal escrita en un SELECT es un PDOException, y un
# PDOException sin atrapar tumba la pagina entera. Lo peor es como se
# ve: la cortinilla de carga queda ENCIMA del mensaje, asi que el
# sistema parece "cargando para siempre" sin decir que paso.
#
# Esto se escribio despues de que un "AND c.estatus = 1" sobre la tabla
# cita —que no tiene esa columna, a diferencia de casi todas las demas—
# dejara la ficha del paciente en blanco.
from collections import defaultdict as _dd

_esq = open(f'{BASE}/db/01_schema.sql', encoding='utf-8').read()

_cols = _dd(set)
for _m in re.finditer(r'CREATE TABLE (\w+) \((.*?)\n\) ENGINE', _esq, re.S):
    for _l in _m.group(2).split('\n'):
        _c = re.match(r'\s{2}(\w+)\s+[A-Z]', _l)
        if _c:
            _cols[_m.group(1)].add(_c.group(1))

# Lo que devuelve una vista no se puede saber leyendo su definicion sin
# resolver los JOIN, asi que las vistas quedan fuera.
_vistas = set(re.findall(r'CREATE OR REPLACE VIEW (\w+)', _esq))
_RESERVADAS = {'ON','WHERE','SET','GROUP','ORDER','LEFT','INNER','JOIN',
               'RIGHT','OUTER','CROSS','STRAIGHT_JOIN','USING','LIMIT'}

_malas = []
for _archivo in sorted(glob.glob(f'{BASE}/vistas/**/*.php', recursive=True)
                     + glob.glob(f'{BASE}/includes/**/*.php', recursive=True)
                     + glob.glob(f'{BASE}/*.php')):
    _txt = open(_archivo, encoding='utf-8').read()
    _rel = os.path.relpath(_archivo, BASE)

    for _sql in re.findall(r"'(SELECT .*?)'", _txt, re.S):
        _alias = {}
        for _m in re.finditer(r'\b(?:FROM|JOIN)\s+(\w+)\s+(?:AS\s+)?(\w+)',
                              _sql, re.I):
            if _m.group(2).upper() in _RESERVADAS:
                continue
            _alias[_m.group(2)] = _m.group(1)

        for _m in re.finditer(r'\b(\w+)\.(\w+)\b', _sql):
            _a, _c = _m.group(1), _m.group(2)
            _t = _alias.get(_a)
            if _t is None or _t in _vistas or _t not in _cols:
                continue
            if _c not in _cols[_t]:
                _malas.append(f'{_rel}: la consulta pide {_t}.{_c}, '
                              f'que no existe en esa tabla')

# ---------------------------------------------------------------------
#  Consultas de UNA sola tabla, sin alias
# ---------------------------------------------------------------------
# Lo de arriba solo mira "alias.columna". Una consulta de una sola tabla
# se escribe sin alias y con los nombres pelados:
#
#     SELECT enfermedad_cronica, discapacidad
#       FROM historia_clinica
#      WHERE id_registro_paciente = :r AND estatus = 1
#
# Ahi no hay ningun punto, asi que la revision anterior no veia nada que
# revisar y daba el visto bueno. Me invente "estatus" y "id_historia" en
# historia_clinica —que no tiene ninguna de las dos— y el verificador
# dijo que todo estaba en orden. El error aparecio en la pantalla de la
# clienta.
#
# Solo se revisan las consultas con UNA tabla y ningun JOIN: ahi cada
# nombre pelado tiene que ser columna de esa tabla, sin ambiguedad
# posible. Con dos tablas no se puede saber de cual es cada nombre sin
# resolver la consulta, y adivinarlo llenaria esto de falsos avisos.
_PALABRAS_SQL = {
    'select','from','where','and','or','not','in','is','null','as','on',
    'order','by','group','having','limit','offset','asc','desc','distinct',
    'case','when','then','else','end','like','between','exists','union',
    'all','join','left','right','inner','outer','set','update','insert',
    'into','values','delete','count','sum','avg','min','max','coalesce',
    'ifnull','if','concat','concat_ws','date','now','curdate','year',
    'month','day','interval','cast','signed','unsigned','char','decimal',
    'separator','true','false','default','duplicate','key','replace',
    'lower','upper','trim','substring','substring_index','ucase','lcase',
    'timestampdiff','datediff','date_format','str_to_date','greatest',
    'least','round','abs','length','locate','rand','nullif','div','mod',
}

_malasSolas = []
for _archivo in sorted(glob.glob(f'{BASE}/vistas/**/*.php', recursive=True)
                     + glob.glob(f'{BASE}/includes/**/*.php', recursive=True)
                     + glob.glob(f'{BASE}/*.php')):
    _txt = open(_archivo, encoding='utf-8').read()
    _rel = os.path.relpath(_archivo, BASE)

    for _sql in re.findall(r"'(SELECT .*?)'", _txt, re.S):
        # Una sola tabla y nada mas: ni JOIN, ni subconsulta, ni un
        # segundo FROM.
        _tablasSql = re.findall(r'\b(?:FROM|JOIN)\s+(\w+)', _sql, re.I)
        if len(_tablasSql) != 1:
            continue

        _t = _tablasSql[0]
        if _t in _vistas or _t not in _cols:
            continue

        # Una tabla puede tener alias aunque sea la unica: "FROM persona p".
        # Ese alias aparece suelto en "CONCAT_WS(..., p.nombres, ...)" y sin
        # esto se denunciaba como una columna que no existe. Un verificador
        # que inventa fallos ensenia a ignorarlo, que es peor que no tenerlo.
        _apodo = re.search(
            r'\bFROM\s+' + re.escape(_t) + r'\s+(?:AS\s+)?(\w+)', _sql, re.I)
        _apodo = _apodo.group(1).lower() if _apodo else None
        if _apodo in _RESERVADAS or (_apodo or '').upper() in _RESERVADAS:
            _apodo = None

        # Fuera lo que no son nombres de columna: los marcadores, los
        # textos entre comillas, los alias declarados con AS y lo que
        # lleva parentesis detras (que es una funcion).
        _limpio = re.sub(r':\w+', ' ', _sql)
        _limpio = re.sub(r'"[^"]*"', ' ', _limpio)
        _limpio = re.sub(r'\bAS\s+\w+', ' ', _limpio, flags=re.I)
        _limpio = re.sub(r'\b\w+\s*\(', ' ( ', _limpio)

        for _m in re.finditer(r'\b([a-z_][a-z0-9_]*)\b', _limpio):
            _c = _m.group(1)
            if _c in _PALABRAS_SQL or _c == _t or _c == _apodo:
                continue
            # Un nombre con punto delante o detras ya lo revisa el
            # bloque anterior.
            _i, _f = _m.start(), _m.end()
            if (_i > 0 and _limpio[_i - 1] == '.') or \
               (_f < len(_limpio) and _limpio[_f] == '.'):
                continue
            if _c not in _cols[_t]:
                _malasSolas.append(
                    f'{_rel}: la consulta pide {_c}, que no existe en '
                    f'{_t} (consulta de una sola tabla)')

for _x in sorted(set(_malas)) + sorted(set(_malasSolas)):
    fallos.append(_x)
print(f'  ok   {len(_cols)} tablas: ninguna consulta pide una columna inexistente')


# ---------- Dos elementos con el mismo id ----------
# getElementById devuelve SOLO el primero. El segundo boton queda
# muerto: no hace nada y no da error, que es la peor combinacion
# posible para encontrarlo.
#
# Esto se escribio despues de que el "+" de la tarjeta de consultas no
# hiciera nada, porque compartia id con el boton del encabezado.
#
# Se revisa por pantalla completa: una vista mas lo que incluye, que es
# lo que el navegador acaba viendo junto.
def _incluidos(ruta, vistos=None):
    """El texto de una vista con sus require resueltos."""
    vistos = vistos or set()
    if ruta in vistos or not os.path.isfile(ruta):
        return ''
    vistos.add(ruta)
    txt = open(ruta, encoding='utf-8').read()

    for _m in re.finditer(r"require(?:_once)?\s+__DIR__\s*\.\s*'([^']+)'", txt):
        txt += _incluidos(os.path.normpath(
            os.path.join(os.path.dirname(ruta), _m.group(1).lstrip('/'))), vistos)
    for _m in re.finditer(r"require(?:_once)?\s+RUTA_VISTAS\s*\.\s*'([^']+)'", txt):
        txt += _incluidos(os.path.normpath(
            f'{BASE}/vistas/' + _m.group(1).lstrip('/')), vistos)
    return txt

# Lo que sale en TODAS las pantallas cuenta en todas: los modales
# comunes, la barra, el pie y el propio armazon de index.php (ahi vive
# la cortinilla, entre otros). Sin esto, un modal del layout podria
# chocar con un id del armazon y nadie lo veria.
_comunes = (_incluidos(f'{BASE}/vistas/layout/_modales_comunes.php')
          + _incluidos(f'{BASE}/vistas/layout/nav.php')
          + _incluidos(f'{BASE}/vistas/layout/footer.php')
          + re.sub(r'<\?php.*?\?>', '', open(f'{BASE}/index.php', encoding='utf-8').read(), flags=re.S))

for _vista in sorted(glob.glob(f'{BASE}/vistas/**/*.php', recursive=True)):
    _rel = os.path.relpath(_vista, BASE)
    # Los parciales se revisan dentro de la vista que los incluye, no
    # sueltos: por si solos no son una pantalla.
    if os.path.basename(_vista).startswith('_'):
        continue

    _html = _incluidos(_vista) + _comunes
    _ids = re.findall(r'\bid="([A-Za-z][\w-]*)"', _html)

    _repes = sorted({x for x in _ids if _ids.count(x) > 1})
    if _repes:
        fallos.append(f'{_rel}: id repetido en la misma pantalla {_repes}. '
                      'getElementById solo encuentra el primero: el resto '
                      'queda muerto sin dar error.')
print('  ok   ningun id se repite dentro de una pantalla')


# ---------------------------------------------------------------------
#  La sesion: los dos numeros tienen que moverse juntos
# ---------------------------------------------------------------------
# MINUTOS_SESION (config.php) dice cuanto aguanta la sesion sin
# actividad, y session.gc_maxlifetime (php.ini) dice cuando PHP BORRA
# los datos de la sesion. El segundo manda: la aplicacion puede
# considerar la sesion valida y encontrarse con que los datos ya no
# estan.
#
# Esto ya paso una vez. Subir MINUTOS_SESION de 60 a 240 no sirvio de
# nada porque gc_maxlifetime seguia en 3600, y el sintoma fue "el cambio
# no funciono": la sesion seguia cayendose a la hora, sin ninguna pista
# de donde mirar. Lo caro del fallo es que los dos valores estan en
# archivos distintos, de lenguajes distintos, y nada los une.
_cfg = (_raiz / "config/config.php").read_text(encoding='utf-8')
_ini = (_raiz / "docker/php/php.ini").read_text(encoding='utf-8')

_m = re.search(r"define\('MINUTOS_SESION',\s*(\d+)\)", _cfg)
_g = re.search(r'(?m)^\s*session\.gc_maxlifetime\s*=\s*(\d+)', _ini)

if _m is None:
    fallos.append('no encuentro MINUTOS_SESION en config.php')
elif _g is None:
    fallos.append('no encuentro session.gc_maxlifetime en docker/php/php.ini')
else:
    _minutos = int(_m.group(1))
    _gc = int(_g.group(1))

    if _minutos == 0:
        # Sin caducidad en la aplicacion, el recolector no puede estar
        # borrando sesiones a la hora: la doctora se quedaria fuera sin
        # que nada lo explique. Un mes es el piso de lo razonable.
        if _gc < 2592000:
            fallos.append(
                f'MINUTOS_SESION = 0 (la sesion no caduca) pero '
                f'session.gc_maxlifetime = {_gc}s: PHP borraria los datos '
                f'de la sesion a las {_gc // 3600} horas y la sesion se '
                'caeria igual.')
        else:
            print('  ok   la sesion no caduca, y el php.ini lo respalda '
                  f'({_gc // 86400} dias)')
    else:
        # Con caducidad, el recolector tiene que aguantar MAS que la
        # aplicacion: corre cuando le toca, no al segundo, y no conviene
        # que la sesion muera justo en el limite.
        if _gc <= _minutos * 60:
            fallos.append(
                f'MINUTOS_SESION = {_minutos} min ({_minutos * 60}s) pero '
                f'session.gc_maxlifetime = {_gc}s: PHP borra la sesion '
                'antes de que la aplicacion la considere vencida, y el '
                'sintoma es "el cambio no funciono".')
        else:
            print(f'  ok   la sesion dura {_minutos} min y el php.ini '
                  f'aguanta {_gc}s')


if avisos:
    print(f"{len(avisos)} aviso(s):")
    for a in avisos[:15]:
        print("  ~    " + a)
    if len(avisos) > 15:
        print(f"  ~    ... y {len(avisos) - 15} mas")
    print()

if fallos:
    print(f"*** {len(fallos)} PROBLEMA(S) ***")
    for f in fallos:
        print("  X    " + f)
    sys.exit(1)

print("*** TODO EN ORDEN ***")
