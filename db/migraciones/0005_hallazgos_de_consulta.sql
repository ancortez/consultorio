-- =====================================================================
--  0005_hallazgos_de_consulta.sql
--
--  La exploracion de la consulta era una caja de texto de cuatro
--  renglones. Lo que la doctora escribia ahi eran varios hallazgos
--  pegados: "CARIES OCLUSAL EN 46. GINGIVITIS LEVE. SE OBSERVA MANCHA
--  BLANCA EN 11". Tres observaciones distintas, cada una con su
--  posible radiografia, todas en el mismo parrafo.
--
--  Asi no se puede buscar, no se puede ligar una imagen a un hallazgo
--  concreto, y en la siguiente consulta nadie sabe cual de los tres se
--  resolvio.
--
--  Ahora cada hallazgo es un renglon, dice si es clinico o
--  radiografico, y puede traer su imagen colgada.
--
--  La columna consulta.exploracion NO se borra: lo escrito ahi es el
--  registro de lo que se observo ese dia. Se migra ademas a un hallazgo
--  clinico para que siga viendose en la pantalla nueva.
-- =====================================================================

CREATE TABLE IF NOT EXISTS hallazgo (
  id_hallazgo       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_consulta       INT UNSIGNED NOT NULL,
  tipo              VARCHAR(14) NOT NULL,
  descripcion       TEXT NOT NULL,
  id_documento      INT UNSIGNED NULL,
  orden             SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  id_usuario        INT UNSIGNED NOT NULL,
  fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estatus           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_hallazgo),
  KEY ix_hallazgo_consulta (id_consulta, orden),
  KEY ix_hallazgo_documento (id_documento),
  CONSTRAINT fk_hallazgo_consulta FOREIGN KEY (id_consulta)
    REFERENCES consulta (id_consulta) ON DELETE CASCADE,
  -- SET NULL y no CASCADE: si se borra la imagen, el hallazgo escrito
  -- sobrevive. Lo que se observo no deja de ser cierto porque se haya
  -- perdido la foto.
  CONSTRAINT fk_hallazgo_documento FOREIGN KEY (id_documento)
    REFERENCES documento (id_documento) ON DELETE SET NULL,
  CONSTRAINT fk_hallazgo_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lo ya escrito pasa a ser un hallazgo clinico, para que la pantalla
-- nueva no se vea vacia en una consulta vieja. El WHERE NOT EXISTS hace
-- que correr la migracion dos veces no duplique nada.
INSERT INTO hallazgo (id_consulta, tipo, descripcion, orden, id_usuario,
                      fecha_registro)
SELECT c.id_consulta, 'CLINICO', c.exploracion, 1,
       c.id_usuario_dentista, c.fecha_hora
  FROM consulta c
 WHERE c.exploracion IS NOT NULL
   AND TRIM(c.exploracion) <> ''
   AND NOT EXISTS (SELECT 1 FROM hallazgo h
                    WHERE h.id_consulta = c.id_consulta);
