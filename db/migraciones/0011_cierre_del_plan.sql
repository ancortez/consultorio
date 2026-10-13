-- =====================================================================
--  Cerrar un plan de tratamiento: cancelarlo o darlo por terminado
-- =====================================================================
--  Hasta ahora un plan solo cambiaba de estado solo: pasaba a Aceptado
--  al escanear la hoja firmada, y a En proceso o Terminado segun se
--  marcaban sus conceptos. No habia forma de decir "este plan se
--  cancelo" ni de cerrarlo a mano cuando el paciente deja de venir.
--
--  Y lo que no se podia decir, no se decia: los planes abandonados se
--  quedaban en "En proceso" para siempre, ensuciando el listado y las
--  cuentas del paciente.
--
--  El motivo de la cancelacion NO es decorativo. Dentro de seis meses,
--  cuando alguien pregunte por que ese paciente no siguio, el
--  expediente tiene que poder contestar.
-- =====================================================================

SET @hay_motivo := (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name   = 'plan_tratamiento'
       AND column_name  = 'motivo_cancelacion');

SET @sql := IF(@hay_motivo = 0,
    'ALTER TABLE plan_tratamiento
        ADD COLUMN motivo_cancelacion VARCHAR(255) NULL
            COMMENT ''Por que se cancelo. Obligatorio al cancelar.''
            AFTER id_estado_plan',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @hay_fcan := (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name   = 'plan_tratamiento'
       AND column_name  = 'fecha_cancelacion');

SET @sql := IF(@hay_fcan = 0,
    'ALTER TABLE plan_tratamiento
        ADD COLUMN fecha_cancelacion DATETIME NULL
            AFTER motivo_cancelacion',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @hay_fter := (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name   = 'plan_tratamiento'
       AND column_name  = 'fecha_terminado');

SET @sql := IF(@hay_fter = 0,
    'ALTER TABLE plan_tratamiento
        ADD COLUMN fecha_terminado DATETIME NULL
            AFTER fecha_cancelacion',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
