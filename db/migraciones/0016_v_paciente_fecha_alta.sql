-- =====================================================================
--  v_paciente: la fecha de alta del expediente
-- =====================================================================
--  El buscador de pacientes gana dos columnas, "Alta" y "Ultima
--  consulta", y se puede ordenar por cualquiera de las dos. La segunda
--  ya estaba en la vista; la primera no.
--
--  Es fecha_apertura y NO fecha_registro, que son dos cosas distintas:
--
--    fecha_apertura   el dia en que se abrio el expediente. La captura
--                     el consultorio y puede ser de hace anios.
--    fecha_registro   el instante en que se creo el renglon en Nanai.
--
--  Un paciente de 2019 que se paso a Nanai la semana pasada tiene
--  fecha_registro de la semana pasada, y ordenando por ella saldria
--  como el mas nuevo del consultorio. La pregunta que se contesta
--  mirando esta columna es "¿quien es paciente nuevo?", y eso lo dice
--  la apertura del expediente.
--
--  Una vista se reemplaza entera y no tiene estado, asi que esta
--  migracion es repetible sin condiciones: correrla dos veces deja lo
--  mismo que correrla una.
-- =====================================================================

CREATE OR REPLACE VIEW v_paciente AS
SELECT
  rp.id_registro_paciente,
  rp.num_expediente,
  p.id_persona,
  p.nombres,
  p.ap_paterno,
  p.ap_materno,
  CONCAT_WS(' ', p.nombres, p.ap_paterno, p.ap_materno) AS nombre_completo,
  p.fecha_nacim,
  TIMESTAMPDIFF(YEAR, p.fecha_nacim, CURDATE()) AS edad,
  g.nombre_genero,
  p.id_ocupacion,
  o.nombre_ocupacion,
  p.rfc,
  p.foto,
  p.estatus,
  rp.fecha_apertura AS fecha_alta,
  (SELECT cp.valor_contacto FROM contacto_persona cp
     JOIN medio_contacto mc ON mc.id_medio_contacto = cp.id_medio_contacto
    WHERE cp.id_persona = p.id_persona AND cp.estatus = 1
      AND mc.tipo_dato = 'tel'
    ORDER BY cp.es_principal DESC, cp.id_contacto_persona LIMIT 1) AS telefono,
  (SELECT cp.valor_contacto FROM contacto_persona cp
     JOIN medio_contacto mc ON mc.id_medio_contacto = cp.id_medio_contacto
    WHERE cp.id_persona = p.id_persona AND cp.estatus = 1
      AND mc.tipo_dato = 'email'
    ORDER BY cp.es_principal DESC, cp.id_contacto_persona LIMIT 1) AS correo,
  (SELECT MAX(c.fecha_hora) FROM consulta c
    WHERE c.id_registro_paciente = rp.id_registro_paciente) AS ultima_consulta
FROM registro_paciente rp
JOIN persona p ON p.id_persona = rp.id_persona
LEFT JOIN genero g ON g.id_genero = p.id_genero
LEFT JOIN ocupacion o ON o.id_ocupacion = p.id_ocupacion;
