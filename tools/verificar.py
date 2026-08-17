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
print()
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
