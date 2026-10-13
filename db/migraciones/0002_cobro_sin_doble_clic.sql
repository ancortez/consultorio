-- =====================================================================
--  0002_cobro_sin_doble_clic.sql
--
--  Ya paso en produccion: se presiono "Procesar pago" varias veces y
--  quedaron varios cobros registrados por el mismo dinero.
--
--  Deshabilitar el boton en la pantalla no alcanza. No cubre el
--  navegador que reintenta la peticion por su cuenta, la conexion que
--  se corta a medio envio, ni al usuario que da Enter dos veces antes
--  de que el JavaScript alcance a correr. Con dinero, quien decide
--  tiene que ser la base.
--
--  La pantalla manda una huella distinta cada vez que se ABRE el
--  formulario de cobro (no cada vez que se envia). Dos envios del mismo
--  formulario traen la misma huella; el indice unico rechaza el
--  segundo y el sistema devuelve el cobro que ya existia, como si
--  hubiera funcionado a la primera. Para el usuario no hay error que
--  entender: simplemente no se cobro dos veces.
--
--  Queda NULL en los cobros viejos: NULL no choca con NULL en un
--  indice unico de MySQL, asi que los anteriores conviven sin
--  problema.
-- =====================================================================

-- MySQL 8 no admite IF NOT EXISTS ni en ADD COLUMN ni en ADD UNIQUE:
-- eso es de MariaDB. Para que la migracion se pueda correr dos veces
-- sin fallar hay que preguntarle al catalogo primero y armar el ALTER
-- solo si hace falta. Es feo, pero es la unica forma en MySQL 8.
SET @hay_huella := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE()
     AND table_name   = 'pago'
     AND column_name  = 'huella_envio'
);

SET @sql_huella := IF(@hay_huella = 0,
  'ALTER TABLE pago ADD COLUMN huella_envio CHAR(36) NULL AFTER observaciones',
  'SELECT ''huella_envio ya existia'' AS nota');

PREPARE p_huella FROM @sql_huella;
EXECUTE p_huella;
DEALLOCATE PREPARE p_huella;

-- Lo mismo para el indice.
SET @ya := (
  SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = DATABASE()
     AND table_name   = 'pago'
     AND index_name   = 'uq_pago_huella'
);

SET @sql := IF(@ya = 0,
  'ALTER TABLE pago ADD UNIQUE KEY uq_pago_huella (huella_envio)',
  'SELECT ''uq_pago_huella ya existia'' AS nota');

PREPARE armar FROM @sql;
EXECUTE armar;
DEALLOCATE PREPARE armar;
