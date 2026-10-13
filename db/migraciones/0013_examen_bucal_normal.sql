-- =====================================================================
--  Examen bucal: normal o anormal
-- =====================================================================
--  El examen bucal se habia reducido a tres hallazgos escritos
--  —frenillo, mucosa, lengua— y eso funciona para describir lo que se
--  ve. Lo que no contesta es la pregunta previa: ¿habia algo que
--  describir?
--
--  Un examen normal deja los tres campos en blanco, y un examen que no
--  se hizo tambien. En una hoja firmada son dos cosas muy distintas y
--  se veian igual.
--
--  Con esto, "NORMAL" es una afirmacion: se revisó y no habia nada. El
--  valor por omision es NORMAL porque es lo que ocurre en la mayoria de
--  los pacientes y porque el que captura ya tiene doce preguntas que
--  contestar; lo que se aparta de lo normal es lo que merece un clic.
-- =====================================================================

SET @hay_col := (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name   = 'historia_clinica'
       AND column_name  = 'examen_bucal_normal');

-- TINYINT y no un ENUM de texto: es la misma forma que tienen las demas
-- respuestas de si/no de esta tabla, y el formulario ya sabe pintarlas.
--
-- DEFAULT 1 vale para las filas nuevas. Las que ya existen se quedan en
-- NULL a proposito: de esas historias no se sabe si el examen fue
-- normal, y poner 1 seria afirmar en nombre de la doctora algo que
-- nadie dijo. La hoja impresa de una historia vieja deja ese renglon
-- en blanco, que es la verdad.
SET @sql := IF(@hay_col = 0,
    'ALTER TABLE historia_clinica
        ADD COLUMN examen_bucal_normal TINYINT UNSIGNED NULL DEFAULT 1
            COMMENT ''1 normal | 0 anormal. NULL en las historias capturadas antes de que existiera.''
            AFTER mucosa',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
