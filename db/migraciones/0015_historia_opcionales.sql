-- =====================================================================
--  Historia clinica: cepillados en palabras y complementos opcionales
-- =====================================================================
--  CEPILLADOS AL DIA deja de ser un numero.
--
--  Pedir un entero obliga a inventar. Lo que el tutor contesta es "en la
--  manana y en la noche", "casi siempre uno", "solo cuando se acuerda",
--  "tres si va a la escuela". Nada de eso cabe en un TINYINT, asi que
--  quien capturaba elegia un numero y el dato quedaba mas limpio y menos
--  cierto: un "1" no distingue "uno al dia, siempre" de "uno, cuando se
--  acuerda", y para decidir si hay que insistir con la higiene esa es
--  justamente la diferencia.
--
--  Lo ya capturado se conserva: un TINYINT que pasa a VARCHAR se
--  convierte solo, "3" sigue siendo "3". Al reves no se podria, asi que
--  este cambio no tiene vuelta sin perder los textos.
--
--  Los complementos opcionales no tocan la base: eran NULL y siguen
--  siendo NULL. Lo que cambia es que un complemento vacio deja de
--  frenar el guardado y se imprime como "NO ESPECIFICA". Se menciona
--  aqui porque las dos cosas son la misma peticion y quien lea las
--  migraciones va a buscar la segunda.
-- =====================================================================

SET @tipo := (
    SELECT COLUMN_TYPE FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name   = 'historia_clinica'
       AND column_name  = 'cepillados_dia');

SET @sql := IF(@tipo IS NULL OR @tipo LIKE 'varchar%',
    'SELECT 1',
    'ALTER TABLE historia_clinica
        MODIFY cepillados_dia VARCHAR(100) NULL
            COMMENT ''En palabras: "en la maniana y en la noche", "cuando se acuerda"''');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
