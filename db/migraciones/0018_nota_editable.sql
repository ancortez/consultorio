-- =====================================================================
--  Notas de evolucion: se pueden corregir, y se nota
-- =====================================================================
--  La doctora pidio poder editar una nota ya guardada: el texto y la
--  fecha. Hasta ahora no se podia a proposito —"es parte del registro
--  clinico y se le agrega, no se le corrige"— y el argumento era bueno
--  para el texto y no resolvia lo que ella necesita: una nota escrita
--  el martes puede referirse a lo que paso el viernes anterior, y la
--  unica forma de arreglar un dedazo era escribir otra nota diciendo
--  "donde dice X, lease Y".
--
--  Lo que se agrega son TRES columnas, y la clave esta en separar dos
--  fechas que hasta hoy eran una:
--
--    fecha_nota            A QUE DIA se refiere la nota. Editable: es
--                          el dato clinico, y es lo que se pidio.
--
--    fecha_registro        CUANDO se teclo. Ya existia y NO se toca
--                          nunca: es el rastro de cuando entro al
--                          sistema.
--
--    fecha_modificacion    cuando se corrigio por ultima vez, y
--    id_usuario_modifica   quien la corrigio.
--
--  Si la fecha editable fuera fecha_registro, corregir una nota
--  borraria la unica prueba de cuando se escribio de verdad. En un
--  expediente clinico eso es justo lo que no se puede perder: la
--  diferencia entre "lo anoto ese dia" y "lo anoto tres meses despues"
--  es la diferencia entre un registro y una reconstruccion.
--
--  Con las dos fechas separadas, la nota puede decir "del 10 de
--  octubre" y el sistema sigue sabiendo que se teclo el 13 y se
--  corrigio el 17. Las tres cosas son ciertas a la vez.
--
--  Las notas que ya existen se rellenan con fecha_nota =
--  fecha_registro, que es lo que eran antes de que hubiera dos fechas.
-- =====================================================================

SET @hay_fecha := (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name   = 'nota_evolucion'
       AND column_name  = 'fecha_nota');

SET @sql := IF(@hay_fecha = 0,
    'ALTER TABLE nota_evolucion
        ADD COLUMN fecha_nota DATETIME NULL
            COMMENT ''A que dia se refiere la nota. Editable.''
            AFTER texto',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- Las que ya existen: su fecha de nota es la de registro.
UPDATE nota_evolucion SET fecha_nota = fecha_registro
 WHERE fecha_nota IS NULL;

-- Una columna por ALTER, cada una con su propia comprobacion.
--
-- Juntas en un solo ALTER es mas corto y deja de ser repetible a medias:
-- si la migracion se corta entre las dos —el servidor se reinicia, la
-- conexion se cae—, la comprobacion mira solo la primera, la encuentra
-- puesta y se salta las dos. La segunda columna no existiria y nadie lo
-- sabria hasta que el codigo la pidiera.
SET @hay_mod := (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name   = 'nota_evolucion'
       AND column_name  = 'fecha_modificacion');

SET @sql := IF(@hay_mod = 0,
    'ALTER TABLE nota_evolucion
        ADD COLUMN fecha_modificacion DATETIME NULL
            COMMENT ''Ultima correccion. NULL = nunca se corrigio.''
            AFTER fecha_registro',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @hay_quien := (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name   = 'nota_evolucion'
       AND column_name  = 'id_usuario_modifica');

SET @sql := IF(@hay_quien = 0,
    'ALTER TABLE nota_evolucion
        ADD COLUMN id_usuario_modifica INT UNSIGNED NULL
            COMMENT ''Quien la corrigio.''
            AFTER fecha_modificacion',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- La llave foranea aparte: si las columnas ya estaban, la llave pudo
-- quedarse sin poner.
SET @hay_fk := (
    SELECT COUNT(*) FROM information_schema.table_constraints
     WHERE table_schema   = DATABASE()
       AND table_name     = 'nota_evolucion'
       AND constraint_name = 'fk_nota_modifica');

SET @sql := IF(@hay_fk = 0,
    'ALTER TABLE nota_evolucion
        ADD CONSTRAINT fk_nota_modifica FOREIGN KEY (id_usuario_modifica)
            REFERENCES usuario (id_usuario)',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- El indice que ordena las notas pasa a mirar la fecha de la nota, que
-- es por la que se leen. Dejandolo en fecha_registro, la consulta que
-- las ordena por fecha_nota haria un ordenamiento en memoria cada vez.
SET @hay_ix := (
    SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name   = 'nota_evolucion'
       AND index_name   = 'ix_nota_consulta_fecha');

SET @sql := IF(@hay_ix = 0,
    'ALTER TABLE nota_evolucion
        ADD KEY ix_nota_consulta_fecha (id_consulta, fecha_nota)',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
