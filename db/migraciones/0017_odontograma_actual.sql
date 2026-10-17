-- =====================================================================
--  Odontograma: un solo "actual" por paciente
-- =====================================================================
--  Las tablas odontograma y odontograma_detalle ya existian en el
--  esquema desde el principio y no se usaban. Ahora si: guardan el
--  estado de cada diente del paciente —sano, amalgama, ausente— que es
--  lo que la doctora pidio poder ver de un golpe.
--
--  Se usa UN SOLO odontograma por paciente, con tipo = 'actual'. Lo que
--  esta migracion agrega es la llave que lo garantiza: sin ella, dos
--  pestanias abiertas a la vez pueden crear dos odontogramas del mismo
--  paciente —las dos consultan, las dos no encuentran, las dos
--  insertan— y a partir de ahi la mitad de las marcas se guardarian en
--  uno y la otra mitad en el otro. El sintoma seria dientes que se
--  despintan solos, y encontrarlo asi, meses despues, es muy caro.
--
--  Una historia de odontogramas por fecha SI cabe en estas tablas, y es
--  otra funcion que nadie ha pedido: los cambios de cada dia ya quedan
--  registrados en los hallazgos de la consulta y en los procedimientos
--  del plan.
--
--  Por eso la llave es sobre una EXPRESION y no sobre las dos columnas.
--  Una llave unica en (id_registro_paciente, tipo) habria limitado
--  tambien los otros tipos a uno por paciente, y entonces el dia que se
--  quiera guardar la historia por fechas habria que empezar quitando
--  esta llave. La expresion da el id del paciente solo cuando el tipo
--  es 'actual' y NULL en los demas, y en MySQL los NULL no chocan
--  entre si: el 'actual' queda acotado a uno y lo demas queda libre.
--
--  Las llaves por expresion existen desde MySQL 8.0.13 y el proyecto
--  corre 8.0, asi que estan disponibles.
-- =====================================================================

-- Por si quedaron duplicados de pruebas anteriores: se deja el primero
-- de cada paciente y se borran los demas. El detalle se va con ellos
-- por la cascada de la llave foranea.
DELETE o FROM odontograma o
  JOIN (
      SELECT id_registro_paciente, MIN(id_odontograma) AS primero
        FROM odontograma
       WHERE tipo = 'actual'
       GROUP BY id_registro_paciente
  ) q ON q.id_registro_paciente = o.id_registro_paciente
   AND o.id_odontograma > q.primero
 WHERE o.tipo = 'actual';

SET @hay_llave := (
    SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name   = 'odontograma'
       AND index_name   = 'uq_odo_actual');

SET @sql := IF(@hay_llave = 0,
    'ALTER TABLE odontograma
        ADD UNIQUE KEY uq_odo_actual
            ((IF(tipo = ''actual'', id_registro_paciente, NULL)))',
    'SELECT 1');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
