-- =====================================================================
--  Nanai tambien atiende adultos: el tutor deja de ser obligatorio
-- =====================================================================
--  Hasta ahora el sistema daba por hecho que todo paciente es un menor
--  y por tanto tiene tutor. Para atender adultos hace falta poder
--  decir que un paciente responde por si mismo.
--
--  Dos columnas y no una, porque hay DOS preguntas distintas:
--
--    requiere_tutor        ¿necesita tutor?  NULL = nadie lo ha dicho
--    tutor_decidido_edad   ¿a que edad se decidio?
--
--  La segunda es la que permite volver a preguntar a los 24 sin
--  preguntar todos los dias. Si solo guardaramos "requiere_tutor", al
--  cumplir 24 el sistema volveria a preguntar en cada visita hasta que
--  alguien cambiara la respuesta, y la recepcionista aprenderia a
--  darle a "sigue igual" sin leer.
--
--  Por que la edad y no la fecha de la decision: la regla se enuncia
--  en edades ("al llegar a 24") y guardarla en edades deja la
--  comprobacion legible sin tener que restar fechas para entenderla.
--
--  No borra nada. Los expedientes que ya existen quedan en NULL, que
--  es lo honesto: de ellos no sabemos la respuesta porque nunca se
--  pregunto. Los menores no se ven afectados, porque para ellos la
--  regla no mira esta columna.
-- =====================================================================

-- MySQL 8 no admite ADD COLUMN IF NOT EXISTS: eso es de MariaDB. Hay
-- que preguntarle a information_schema y armar la sentencia.
SET @hay_requiere := (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name   = 'registro_paciente'
       AND column_name  = 'requiere_tutor');

SET @sql := IF(@hay_requiere = 0,
    'ALTER TABLE registro_paciente
        ADD COLUMN requiere_tutor TINYINT UNSIGNED NULL
            COMMENT ''NULL sin decidir | 1 lleva tutor | 0 responde por si mismo''
            AFTER motivo_primera_visita',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @hay_edad := (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name   = 'registro_paciente'
       AND column_name  = 'tutor_decidido_edad');

SET @sql := IF(@hay_edad = 0,
    'ALTER TABLE registro_paciente
        ADD COLUMN tutor_decidido_edad TINYINT UNSIGNED NULL
            COMMENT ''Edad que tenia el paciente cuando se decidio''
            AFTER requiere_tutor',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- ---------------------------------------------------------------------
--  Los expedientes que YA tienen tutor registrado
-- ---------------------------------------------------------------------
--  Se dan por resueltos: alguien capturo un tutor, asi que la respuesta
--  ya se dio de hecho aunque nadie la escribiera. Preguntar por ellos
--  seria preguntar algo que el expediente ya contesta.
--
--  La edad de la decision se deja en NULL a proposito: no sabemos a que
--  edad se decidio. La regla trata ese NULL como "hace mucho", que es
--  lo que hace que a los 24 se vuelva a preguntar, que es justo lo que
--  se pide.
UPDATE registro_paciente rp
   SET rp.requiere_tutor = 1
 WHERE rp.requiere_tutor IS NULL
   AND EXISTS (SELECT 1 FROM expediente_tutor et
                WHERE et.id_registro_paciente = rp.id_registro_paciente
                  AND et.estatus = 1);
