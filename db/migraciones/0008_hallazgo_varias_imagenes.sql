-- =====================================================================
--  0008_hallazgo_varias_imagenes.sql
--
--  Un hallazgo podia traer UNA imagen. En la practica no alcanza:
--  "fractura del 11" se documenta con la foto intraoral y con dos
--  periapicales desde angulos distintos, y las tres son del mismo
--  hallazgo.
--
--  El enlace se invierte. Antes hallazgo.id_documento apuntaba a la
--  imagen; ahora documento.id_hallazgo apunta al hallazgo, que es lo
--  que permite que sean varias.
--
--  hallazgo.id_documento se conserva pero deja de usarse. Tener las dos
--  columnas vivas seria tener dos fuentes de verdad sobre lo mismo, y
--  tarde o temprano dirian cosas distintas.
-- =====================================================================

SET @hay_doc_hz := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE()
     AND table_name   = 'documento'
     AND column_name  = 'id_hallazgo'
);

SET @sql_doc_hz := IF(@hay_doc_hz = 0,
  'ALTER TABLE documento ADD COLUMN id_hallazgo INT UNSIGNED NULL AFTER id_consulta',
  'SELECT ''documento.id_hallazgo ya existia'' AS nota');

PREPARE p_doc_hz FROM @sql_doc_hz;
EXECUTE p_doc_hz;
DEALLOCATE PREPARE p_doc_hz;

-- El indice, aparte: se consulta por hallazgo cada vez que se dibuja
-- la exploracion de una consulta.
SET @hay_ix_hz := (
  SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = DATABASE()
     AND table_name   = 'documento'
     AND index_name   = 'ix_doc_hallazgo'
);

SET @sql_ix_hz := IF(@hay_ix_hz = 0,
  'ALTER TABLE documento ADD KEY ix_doc_hallazgo (id_hallazgo)',
  'SELECT ''ix_doc_hallazgo ya existia'' AS nota');

PREPARE p_ix_hz FROM @sql_ix_hz;
EXECUTE p_ix_hz;
DEALLOCATE PREPARE p_ix_hz;

-- SET NULL y no CASCADE: borrar el hallazgo escrito no debe llevarse la
-- radiografia. La imagen es un documento del expediente por derecho
-- propio, y lo que se observo no deja de haber ocurrido porque alguien
-- deshaga un renglon de texto.
SET @hay_fk_hz := (
  SELECT COUNT(*) FROM information_schema.table_constraints
   WHERE table_schema    = DATABASE()
     AND table_name      = 'documento'
     AND constraint_name = 'fk_doc_hallazgo'
);

SET @sql_fk_hz := IF(@hay_fk_hz = 0,
  'ALTER TABLE documento ADD CONSTRAINT fk_doc_hallazgo
     FOREIGN KEY (id_hallazgo) REFERENCES hallazgo (id_hallazgo)
     ON DELETE SET NULL',
  'SELECT ''fk_doc_hallazgo ya existia'' AS nota');

PREPARE p_fk_hz FROM @sql_fk_hz;
EXECUTE p_fk_hz;
DEALLOCATE PREPARE p_fk_hz;

-- Lo que ya estaba ligado por la columna vieja se pasa a la nueva, para
-- que ninguna imagen se quede huerfana de su hallazgo.
UPDATE documento d
  JOIN hallazgo h ON h.id_documento = d.id_documento
   SET d.id_hallazgo = h.id_hallazgo
 WHERE d.id_hallazgo IS NULL;
