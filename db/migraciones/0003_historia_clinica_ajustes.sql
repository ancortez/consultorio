-- =====================================================================
--  0003_historia_clinica_ajustes.sql
--
--  Dos cambios en la historia clinica, pedidos por la doctora tras
--  usarla con pacientes reales.
--
--  1. "Numero de hijo" y "Estatura" salen del formulario. No los usaba.
--
--  2. "Momentos de azucar al dia" pedia un numero y nadie sabe
--     contestarlo. Un tutor no tiene idea de si su hijo consume azucar
--     "cuatro" o "siete" veces al dia, asi que o lo dejaban vacio o
--     inventaban una cifra. Pasa a ser una frecuencia, que es como la
--     gente si piensa el asunto.
--
--  NINGUNA COLUMNA SE BORRA, y eso es a proposito.
--
--  Lo capturado en esas columnas es parte del expediente clinico de
--  pacientes concretos. Que el formulario haya cambiado no borra lo que
--  se registro el dia que se registro. Un expediente tiene que poder
--  demostrar que habia y cuando, no solo que hay hoy. Las columnas
--  viejas quedan ahi, sin escribirse mas, y si algun dia hace falta
--  consultar un dato de 2026 va a seguir estando.
-- =====================================================================

-- MySQL 8 no admite IF NOT EXISTS en ADD COLUMN (eso es MariaDB), asi
-- que se le pregunta al catalogo para poder correr esto dos veces.
SET @hay_azucar := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE()
     AND table_name   = 'historia_clinica'
     AND column_name  = 'momentos_azucar'
);

SET @sql_azucar := IF(@hay_azucar = 0,
  'ALTER TABLE historia_clinica ADD COLUMN momentos_azucar VARCHAR(20) NULL AFTER momentos_azucar_dia',
  'SELECT ''momentos_azucar ya existia'' AS nota');

PREPARE p_azucar FROM @sql_azucar;
EXECUTE p_azucar;
DEALLOCATE PREPARE p_azucar;

-- Traduccion de lo ya capturado, para no perder la informacion que si
-- existe. El corte en 3 no es arbitrario: en odontopediatria el riesgo
-- de caries sube marcadamente a partir de tres o mas exposiciones
-- diarias a azucar libre, asi que de ahi para arriba es "diario" en el
-- sentido que le importa a la doctora.
--
-- "REGULAR" no aparece aqui: es lo que el prellenado propone cuando no
-- se sabe nada, no algo que se pueda deducir de un numero.
UPDATE historia_clinica
   SET momentos_azucar = CASE
         WHEN momentos_azucar_dia = 0             THEN 'NO CONSUME'
         WHEN momentos_azucar_dia BETWEEN 1 AND 2 THEN 'EVENTUALMENTE'
         WHEN momentos_azucar_dia >= 3            THEN 'DIARIO'
       END
 WHERE momentos_azucar IS NULL
   AND momentos_azucar_dia IS NOT NULL;
