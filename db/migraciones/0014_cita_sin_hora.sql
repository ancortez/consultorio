-- =====================================================================
--  Citas apartadas para un dia, sin hora todavia
-- =====================================================================
--  Un paciente de ortodoncia se cita a seis semanas. En ese momento
--  nadie sabe como va a quedar la agenda de ese dia, y elegir una hora
--  solo para poder guardar la cita produce dos cosas malas: aparta un
--  sillon que no hace falta apartar, y le promete al paciente un
--  horario que casi seguro va a cambiar.
--
--  Lo que hace el consultorio de verdad es apuntar "este nino viene el
--  jueves 27" y repartir las horas cuando la fecha se acerca. Esto
--  permite registrar eso tal cual.
--
--  Como se guarda: fecha_inicio y fecha_fin quedan las dos a las 00:00
--  de ese dia. No es un apanio, es lo que corresponde —la cita no
--  ocupa ningun rango de tiempo— y hace que no choque con nada sin
--  necesidad de meter excepciones en la comprobacion de empalmes.
--
--  El dato que manda es esta columna, no la hora. Deducirlo de "las dos
--  fechas son iguales" funcionaria hoy y se romperia el dia que alguien
--  guarde una cita de cero minutos por otro motivo.
-- =====================================================================

SET @hay_col := (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name   = 'cita'
       AND column_name  = 'sin_hora');

SET @sql := IF(@hay_col = 0,
    'ALTER TABLE cita
        ADD COLUMN sin_hora TINYINT UNSIGNED NOT NULL DEFAULT 0
            COMMENT ''1 = apartada para el dia, sin hora todavia. No ocupa sillon.''
            AFTER fecha_fin',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
