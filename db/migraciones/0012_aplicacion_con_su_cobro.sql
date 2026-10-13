-- =====================================================================
--  De que cobro salio la aplicacion del saldo a favor
-- =====================================================================
--  pago_aplicacion dice "el pago P cubrio el cargo C". Con eso basta
--  para que las cuentas cuadren, y por eso se dejo asi.
--
--  Pero al gastar un saldo a favor, la aplicacion se cuelga del pago
--  VIEJO —el anticipo— porque ese dinero entro aquel dia y en aquel
--  corte. Eso es correcto y no se va a cambiar.
--
--  Lo que faltaba es la otra mitad del dato: en QUE cobro se decidio
--  gastarlo. Sin eso, la ficha del cobro de hoy no puede decir "se te
--  aplicaron 400 de tu saldo a favor", porque esos 400 estan apuntados
--  a un folio de hace tres meses. El paciente recibe un recibo por
--  menos de lo que debia y el papel no lo explica.
--
--  Lo unico que lo decia era una frase en las observaciones, que es
--  texto: no se puede sumar, no se puede cuadrar y no sirve para
--  reconstruir el cobro si alguien pregunta en seis meses.
--
--  Queda NULL en las aplicaciones normales, donde el pago que aplica y
--  el cobro que lo decide son el mismo y no hay nada que aclarar.
-- =====================================================================

SET @hay_col := (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name   = 'pago_aplicacion'
       AND column_name  = 'aplicada_en_pago');

SET @sql := IF(@hay_col = 0,
    'ALTER TABLE pago_aplicacion
        ADD COLUMN aplicada_en_pago INT UNSIGNED NULL
            COMMENT ''El cobro donde se decidio gastar este saldo a favor. NULL si el pago que aplica es el mismo que lo recibio.''
            AFTER id_cargo',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- El indice sirve a la consulta de la ficha, que pregunta justo por
-- esto: "¿que saldo a favor se aplico en el cobro tal?".
SET @hay_ix := (
    SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name   = 'pago_aplicacion'
       AND index_name   = 'ix_pa_en_pago');

SET @sql := IF(@hay_ix = 0,
    'ALTER TABLE pago_aplicacion ADD KEY ix_pa_en_pago (aplicada_en_pago)',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- ON DELETE SET NULL y no CASCADE: si alguna vez se borra el cobro de
-- hoy, la aplicacion del anticipo NO desaparece con el. Ese dinero se
-- gasto de verdad y el cargo quedo cubierto; borrar la aplicacion
-- resucitaria una deuda ya pagada.
SET @hay_fk := (
    SELECT COUNT(*) FROM information_schema.table_constraints
     WHERE table_schema     = DATABASE()
       AND table_name       = 'pago_aplicacion'
       AND constraint_name  = 'fk_pa_en_pago');

SET @sql := IF(@hay_fk = 0,
    'ALTER TABLE pago_aplicacion
        ADD CONSTRAINT fk_pa_en_pago FOREIGN KEY (aplicada_en_pago)
            REFERENCES pago (id_pago) ON DELETE SET NULL',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
