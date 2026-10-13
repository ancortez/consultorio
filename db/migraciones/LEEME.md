# Migraciones

Los archivos de `db/` (`01_schema.sql`, `02_catalogos.sql`,
`03_datos_demo.sql`) **solo corren una vez**: cuando MySQL arranca con
su carpeta de datos vacía. Sirven para armar una base nueva y nada más.

A partir del momento en que hay datos reales, cualquier cambio de
estructura tiene que llegar por aquí.

---

## Cómo se usa

Desde el servidor:

```bash
cd /opt/consultorio/infra/produccion
./migrar.sh --ver      # qué falta por aplicar, sin aplicar nada
./migrar.sh            # aplica lo que falte
```

En tu computadora es el mismo guion; detecta solo si está en local o en
el servidor.

---

## Cómo se escribe una

Un archivo por cambio, numerado y con nombre que diga qué hace:

```
db/migraciones/0001_bmp_en_formatos.sql
db/migraciones/0002_hallazgos_de_consulta.sql
```

El número manda el orden y no se reutiliza nunca. Dos personas que
numeren `0007` a la vez tienen un conflicto que resolver antes de
subir, y es mejor que descubrirlo cuando la base ya corrió una de las
dos.

### Tres reglas

**Que se pueda correr dos veces sin romper.** `INSERT ... ON DUPLICATE
KEY UPDATE`, un `WHERE NOT EXISTS`, un `UPDATE` con `WHERE` que ya no
alcance nada la segunda vez. Si una migración falla a la mitad, el
guion para; vas a querer arreglarla y volver a correrla sin tener que
deshacer lo que ya pasó.

#### Cuidado: esto es MySQL, no MariaDB

`CREATE TABLE IF NOT EXISTS` sí existe en MySQL 8. **`ALTER TABLE ...
ADD COLUMN IF NOT EXISTS` no.** Eso es de MariaDB, lo usa medio
internet, se lee perfectamente bien, y revienta con un error de
sintaxis a mitad de la migración — con las anteriores ya aplicadas y la
base a medio camino. Lo mismo con `DROP COLUMN IF EXISTS`, `ADD INDEX
IF NOT EXISTS` y `ALTER TABLE IF EXISTS`.

Para tocar la estructura hay que preguntarle al catálogo primero:

```sql
SET @hay_x := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE()
     AND table_name   = 'mi_tabla'
     AND column_name  = 'mi_columna'
);

SET @sql_x := IF(@hay_x = 0,
  'ALTER TABLE mi_tabla ADD COLUMN mi_columna VARCHAR(20) NULL',
  'SELECT ''mi_columna ya existia'' AS nota');

PREPARE p_x FROM @sql_x;
EXECUTE p_x;
DEALLOCATE PREPARE p_x;
```

Para un índice es igual, cambiando `information_schema.columns` por
`information_schema.statistics` y `column_name` por `index_name`.

Dos detalles de ese bloque:

- Los nombres de variable llevan sufijo (`@hay_x`, `@sql_x`, `p_x`)
  porque un mismo archivo puede agregar varias columnas y reutilizarlos
  mezclaría los bloques.
- El texto del `SELECT` usa **apóstrofes duplicados**, no comillas
  dobles. Con `sql_mode = ANSI_QUOTES`, las comillas dobles dejan de ser
  texto y pasan a ser nombres de columna.

`tools/verificar_despliegue.py` revisa las dos cosas, así que si se te
pasa, salta antes de llegar a la base.

**Nunca se edita una migración ya aplicada.** Si quedó mal, se escribe
otra que corrija. La aplicada ya está en la base de alguien.

**Los datos nunca se borran, se marcan.** Un campo que deja de usarse
no se elimina de la tabla: queda ahí. En un expediente clínico el dato
viejo es prueba de lo que se registró ese día, y la ley de protección
de datos pide poder demostrar qué había y cuándo cambió.

### Esqueleto

```sql
-- =====================================================================
--  0003_lo_que_hace.sql
--
--  Qué cambia y POR QUÉ. El "qué" se lee en el SQL de abajo; el "por
--  qué" solo está aquí, y es lo que vas a necesitar dentro de un año.
-- =====================================================================

ALTER TABLE consulta
  ADD COLUMN IF NOT EXISTS algo VARCHAR(50) NULL AFTER motivo;
```

---

## Qué pasa por dentro

El guion crea una tabla `migracion` la primera vez y ahí anota cada
archivo que aplicó, con su fecha y la huella del contenido.

Esa huella detecta el error más silencioso de todos: que alguien
modifique una migración **después** de haberla aplicado. En su servidor
ya corrió la versión vieja; en uno nuevo correría la nueva. Dos bases
que deberían ser idénticas dejan de serlo, y nadie se entera hasta que
una consulta falla en un solo lugar. Si eso pasa, `migrar.sh` lo dice y
se detiene.
