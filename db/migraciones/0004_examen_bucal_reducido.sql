-- =====================================================================
--  0004_examen_bucal_reducido.sql
--
--  El examen bucal tenia doce campos entre opciones, casillas de
--  anomalias y tres cajas de observaciones. La doctora usaba tres.
--
--  Queda: frenillo, lesiones mucosas y lengua, los tres como texto
--  libre. El cambio de opciones a texto no es un capricho: lo que ella
--  escribia de verdad era "FRENILLO LINGUAL CORTO" o "AFTA EN CARRILLO
--  DERECHO", y eso no cabe en una lista de "Normal / Anormal". Forzarlo
--  a una opcion perdia justo el dato que servia.
--
--  Tipo de denticion se queda: el odontograma y la hoja impresa lo
--  usan.
--
--  Como en la 0003, ninguna columna se borra. Lo escrito en labios,
--  frenillos, lengua, lesiones_gingival y las observaciones es parte
--  del expediente de pacientes concretos y sigue consultable.
-- =====================================================================

-- MySQL 8 no admite IF NOT EXISTS en ADD COLUMN (eso es MariaDB), asi
-- que se le pregunta al catalogo para poder correr esto dos veces.
SET @hay_frenillo := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE()
     AND table_name   = 'historia_clinica'
     AND column_name  = 'frenillo_detalle'
);

SET @sql_frenillo := IF(@hay_frenillo = 0,
  'ALTER TABLE historia_clinica ADD COLUMN frenillo_detalle VARCHAR(255) NULL AFTER mucosa',
  'SELECT ''frenillo_detalle ya existia'' AS nota');

PREPARE p_frenillo FROM @sql_frenillo;
EXECUTE p_frenillo;
DEALLOCATE PREPARE p_frenillo;

SET @hay_lengua := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE()
     AND table_name   = 'historia_clinica'
     AND column_name  = 'lengua_detalle'
);

SET @sql_lengua := IF(@hay_lengua = 0,
  'ALTER TABLE historia_clinica ADD COLUMN lengua_detalle VARCHAR(255) NULL AFTER frenillo_detalle',
  'SELECT ''lengua_detalle ya existia'' AS nota');

PREPARE p_lengua FROM @sql_lengua;
EXECUTE p_lengua;
DEALLOCATE PREPARE p_lengua;

-- Lo que ya se habia elegido en las listas se pasa a texto, para que la
-- pantalla nueva no se vea vacia en un paciente que si tenia el dato.
-- Solo se traduce lo que NO era el valor normal: escribir "NORMAL" en
-- un campo de hallazgos es ruido, y un campo vacio ya significa que no
-- habia nada que anotar.
UPDATE historia_clinica
   SET frenillo_detalle = 'ANORMALES'
 WHERE frenillo_detalle IS NULL AND frenillos = 2;

UPDATE historia_clinica
   SET lengua_detalle = CASE lengua
         WHEN 2 THEN 'MACROGLOSIA'
         WHEN 3 THEN 'GEOGRAFICA'
         WHEN 4 THEN 'ESCROTAL'
       END
 WHERE lengua_detalle IS NULL AND lengua IN (2, 3, 4);

-- Las observaciones de tejidos blandos y las lesiones gingivales no
-- tienen a donde ir sin mezclar cosas distintas. Se arriman a mucosa
-- solo si mucosa esta vacia, separadas para que se vea que venian de
-- otro campo.
UPDATE historia_clinica
   SET mucosa = TRIM(CONCAT_WS(' / ',
         NULLIF(lesiones_gingival, ''), NULLIF(observaciones_blandos, '')))
 WHERE (mucosa IS NULL OR mucosa = '')
   AND (COALESCE(lesiones_gingival, '') <> ''
     OR COALESCE(observaciones_blandos, '') <> '');
