-- =====================================================================
--  0006_plan_diagnostico.sql
--
--  El paso de diagnostico del plan tenia cuatro cajas de texto sueltas:
--  diagnostico, oclusion, traumatismos y secuelas.
--
--  Dos problemas con eso.
--
--  TRAUMATISMOS. Un paciente pedriatico puede llegar con varios golpes
--  en su historia, cada uno con su propia secuela o sin ninguna. En dos
--  campos de texto eso terminaba asi: "CAIDA 4 ANOS Y GOLPE BICICLETA 7
--  ANOS" en uno, "NECROSIS 51" en el otro. ¿La necrosis fue de la caida
--  o de la bicicleta? No hay forma de saberlo. Ahora cada traumatismo
--  es un renglon con SU secuela al lado.
--
--  OCLUSION. Era texto libre, asi que cada quien escribia distinto:
--  "CLASE II", "clase 2", "Cl. II division 1". No se podia filtrar ni
--  contar. Pasa a ser una eleccion entre tres opciones, que son las que
--  la doctora usa.
--
--  Las columnas viejas se conservan con lo ya capturado, y lo que
--  habia se migra a la tabla nueva para no perderlo de vista.
-- =====================================================================

CREATE TABLE IF NOT EXISTS plan_traumatismo (
  id_plan_traumatismo INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_plan_tratamiento INT UNSIGNED NOT NULL,
  descripcion         VARCHAR(255) NOT NULL,
  secuela             VARCHAR(255) NULL,
  orden               SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  estatus             TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_plan_traumatismo),
  KEY ix_traumatismo_plan (id_plan_tratamiento, orden),
  CONSTRAINT fk_traumatismo_plan FOREIGN KEY (id_plan_tratamiento)
    REFERENCES plan_tratamiento (id_plan_tratamiento) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lo ya escrito pasa tal cual a un renglon. No se intenta partirlo en
-- varios: adivinar donde termina un golpe y empieza otro a partir de
-- una frase es justo el tipo de suposicion que no se debe hacer con un
-- expediente clinico. Queda como vino, y la doctora lo separa a mano si
-- quiere.
INSERT INTO plan_traumatismo (id_plan_tratamiento, descripcion, secuela, orden)
SELECT pt.id_plan_tratamiento, pt.traumatismos, pt.secuelas_traumatismos, 1
  FROM plan_tratamiento pt
 WHERE pt.traumatismos IS NOT NULL
   AND TRIM(pt.traumatismos) <> ''
   AND NOT EXISTS (SELECT 1 FROM plan_traumatismo t
                    WHERE t.id_plan_tratamiento = pt.id_plan_tratamiento);

-- La oclusion escrita a mano se normaliza a las tres opciones nuevas
-- cuando se reconoce. Lo que no encaja se deja como esta: inventar una
-- clasificacion que nadie eligio seria peor que dejar el texto original.
UPDATE plan_tratamiento
   SET oclusion = 'CLASE III'
 WHERE oclusion REGEXP '(CLASE|CL\\.?) *(III|3)';

UPDATE plan_tratamiento
   SET oclusion = 'CLASE II'
 WHERE oclusion REGEXP '(CLASE|CL\\.?) *(II|2)([^I]|$)'
   AND oclusion <> 'CLASE III';

UPDATE plan_tratamiento
   SET oclusion = 'MORDIDA CRUZADA'
 WHERE oclusion LIKE '%CRUZADA%';
