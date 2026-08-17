-- =====================================================================
--  DATOS DE DEMOSTRACION - Sistema Consultorio Dental
--  SOLO PARA LA DEMO CON EL CLIENTE. NO CARGAR EN PRODUCCION.
--  Se ejecuta despues de 02_catalogos.sql
-- =====================================================================
SET NAMES utf8mb4;
USE consultorio_dental;

-- ---------- Domicilios ----------
INSERT INTO domicilio (id_domicilio, id_colonia, calle, num_ext, num_int, entre_calle_1, entre_calle_2, referencia) VALUES
(1, 6, 'AV. INSURGENTES', '212', NULL, 'CALLE ZARAGOZA', 'AV. AGUAMILPA', 'CASA DE DOS PISOS, CANCEL BLANCO'),
(2, 6, 'CALLE JUAREZ', '39', NULL, 'CALLE ALLENDE', 'AV. MEXICO', 'CASA DE DOS PISOS, CANCEL BLANCO'),
(3, 7, 'AV. TECNOLOGICO', '45', '9', 'AV. TECNOLOGICO', 'AV. MEXICO', 'CASA COLOR BEIGE CON REJA VERDE'),
(4, 2, 'CALLE ALLENDE', '332', NULL, 'AV. MEXICO', 'CALLE JUAREZ', 'CASA COLOR BEIGE CON REJA VERDE'),
(5, 7, 'AV. MEXICO', '123', '3', 'AV. COLOSIO', 'AV. TECNOLOGICO', 'PORTON NEGRO'),
(6, 9, 'CALLE ZARAGOZA', '302', NULL, 'AV. INSURGENTES', 'CALLE ZARAGOZA', 'CASA COLOR BEIGE CON REJA VERDE'),
(7, 10, 'CALLE ALLENDE', '200', '12', 'CALLE ZARAGOZA', 'CALLE JUAREZ', 'CASA DE DOS PISOS, CANCEL BLANCO'),
(8, 10, 'CALLE ALLENDE', '264', NULL, 'AV. TECNOLOGICO', 'CALLE MORELOS', 'A UN COSTADO DE LA FARMACIA'),
(9, 10, 'CALLE HIDALGO', '195', NULL, 'AV. INSURGENTES', 'CALLE ALLENDE', 'CASA DE DOS PISOS, CANCEL BLANCO'),
(10, 10, 'AV. COLOSIO', '278', NULL, 'CALLE MORELOS', 'CALLE HIDALGO', 'FRENTE AL PARQUE'),
(11, 10, 'CALLE ZARAGOZA', '70', NULL, 'AV. INSURGENTES', 'CALLE MORELOS', 'PORTON NEGRO'),
(12, 8, 'AV. TECNOLOGICO', '30', NULL, 'CALLE ZARAGOZA', 'AV. AGUAMILPA', 'CASA COLOR BEIGE CON REJA VERDE'),
(13, 6, 'CALLE MORELOS', '365', NULL, 'CALLE HIDALGO', 'CALLE JUAREZ', 'A UN COSTADO DE LA FARMACIA'),
(14, 2, 'CALLE ZARAGOZA', '493', NULL, 'CALLE ZARAGOZA', 'AV. MEXICO', 'FRENTE AL PARQUE'),
(15, 4, 'AV. INDEPENDENCIA', '77', NULL, 'CALLE SONORA', 'CALLE SINALOA', 'CONSULTORIO DEL TRABAJO');

-- ---------- Personas: pacientes ----------
INSERT INTO persona (id_persona, nombres, ap_paterno, ap_materno, fecha_nacim, id_genero, id_tipo_persona) VALUES
(1, 'MARIA FERNANDA', 'GUTIERREZ', 'LOPEZ', '1988-03-14', 1, 1),
(2, 'JOSE ANTONIO', 'RAMIREZ', 'SOLIS', '1975-11-02', 2, 1),
(3, 'ANA SOFIA', 'DELGADO', 'MENDOZA', '2015-06-21', 1, 1),
(4, 'LUIS ENRIQUE', 'NAVARRO', 'CASTILLO', '1993-01-30', 2, 1),
(5, 'GABRIELA', 'TORRES', 'AGUILAR', '1982-09-08', 1, 1),
(6, 'RICARDO', 'HERRERA', 'IBARRA', '2000-04-17', 2, 1),
(7, 'KARLA PATRICIA', 'SANDOVAL', 'RIOS', '1997-12-05', 1, 1),
(8, 'MIGUEL ANGEL', 'ORTEGA', 'VAZQUEZ', '1968-07-23', 2, 1),
(9, 'DIANA LAURA', 'CEBALLOS', 'PARRA', '2012-02-11', 1, 1),
(10, 'FERNANDO', 'ZEPEDA', 'MOLINA', '1990-08-19', 2, 1),
(11, 'ALEJANDRA', 'VILLALOBOS', 'NUÑEZ', '1985-05-27', 1, 1),
(12, 'HECTOR MANUEL', 'CARDENAS', 'RANGEL', '1979-10-09', 2, 1);

-- ---------- Personas: tutores ----------
INSERT INTO persona (id_persona, nombres, ap_paterno, ap_materno, fecha_nacim, id_genero, id_tipo_persona, rfc) VALUES
(13, 'ROSA ELENA', 'MENDOZA', 'FUENTES', '1986-04-02', 1, 2, 'MEFR860402HN1'),
(14, 'SERGIO', 'CEBALLOS', 'DURAN', '1983-08-15', 2, 2, 'CEDS830815KL2');

-- ---------- Vinculo persona - domicilio ----------
INSERT INTO persona_domicilio (id_persona, id_domicilio, es_principal) VALUES
(1, 1, 1),
(2, 2, 1),
(3, 3, 1),
(4, 4, 1),
(5, 5, 1),
(6, 6, 1),
(7, 7, 1),
(8, 8, 1),
(9, 9, 1),
(10, 10, 1),
(11, 11, 1),
(12, 12, 1),
(13, 13, 1),
(14, 14, 1),
(1, 15, 0);

-- ---------- Canales de contacto ----------
INSERT INTO contacto_persona (id_persona, id_medio_contacto, valor_contacto, es_principal) VALUES
(1, 1, '3118476611', 1),
(1, 3, '3118476611', 0),
(1, 4, 'maria.gutierrez@correo.com', 1),
(2, 1, '3116821782', 1),
(2, 3, '3116821782', 0),
(2, 4, 'jose.ramirez@correo.com', 1),
(3, 1, '3113819383', 1),
(3, 3, '3113819383', 0),
(3, 4, 'ana.delgado@correo.com', 1),
(4, 1, '3114660918', 1),
(4, 4, 'luis.navarro@correo.com', 1),
(5, 1, '3115154287', 1),
(5, 3, '3115154287', 0),
(6, 1, '3119330000', 1),
(6, 3, '3119330000', 0),
(6, 4, 'ricardo.herrera@correo.com', 1),
(7, 1, '3115661367', 1),
(8, 1, '3115671130', 1),
(9, 1, '3117382745', 1),
(9, 4, 'diana.ceballos@correo.com', 1),
(10, 1, '3113956442', 1),
(10, 3, '3113956442', 0),
(10, 4, 'fernando.zepeda@correo.com', 1),
(11, 1, '3111202384', 1),
(11, 3, '3111202384', 0),
(11, 4, 'alejandra.villalobos@correo.com', 1),
(12, 1, '3115408156', 1),
(12, 3, '3115408156', 0),
(12, 4, 'hector.cardenas@correo.com', 1),
(13, 1, '3119968948', 1),
(13, 3, '3119968948', 0),
(13, 4, 'rosa.mendoza@correo.com', 1),
(14, 1, '3113105398', 1),
(14, 3, '3113105398', 0),
(14, 4, 'sergio.ceballos@correo.com', 1);

-- ---------- Ocupacion de las personas de demo ----------
-- Se resuelve por nombre para no depender de los ids del catalogo.
SET @ocu_estudiante = (SELECT id_ocupacion FROM ocupacion WHERE clave = 'ESTUDIANTE');
SET @ocu_ninguna    = (SELECT id_ocupacion FROM ocupacion WHERE clave = 'NINGUNA');

UPDATE persona p SET p.id_ocupacion = @ocu_estudiante
 WHERE TIMESTAMPDIFF(YEAR, p.fecha_nacim, CURDATE()) BETWEEN 3 AND 15;
UPDATE persona p SET p.id_ocupacion = @ocu_ninguna
 WHERE TIMESTAMPDIFF(YEAR, p.fecha_nacim, CURDATE()) < 3;

UPDATE persona SET id_ocupacion = (SELECT id_ocupacion FROM ocupacion WHERE nombre_ocupacion='PROFESOR(A)')       WHERE id_persona = 1;
UPDATE persona SET id_ocupacion = (SELECT id_ocupacion FROM ocupacion WHERE nombre_ocupacion='COMERCIANTE')       WHERE id_persona = 2;
UPDATE persona SET id_ocupacion = (SELECT id_ocupacion FROM ocupacion WHERE nombre_ocupacion='INGENIERO(A)')      WHERE id_persona = 4;
UPDATE persona SET id_ocupacion = (SELECT id_ocupacion FROM ocupacion WHERE nombre_ocupacion='AMA DE CASA')       WHERE id_persona = 5;
UPDATE persona SET id_ocupacion = (SELECT id_ocupacion FROM ocupacion WHERE nombre_ocupacion='EMPLEADO(A)')       WHERE id_persona = 6;
UPDATE persona SET id_ocupacion = (SELECT id_ocupacion FROM ocupacion WHERE nombre_ocupacion='ENFERMERO(A)')      WHERE id_persona = 7;
UPDATE persona SET id_ocupacion = (SELECT id_ocupacion FROM ocupacion WHERE nombre_ocupacion='JUBILADO(A)')       WHERE id_persona = 8;
UPDATE persona SET id_ocupacion = (SELECT id_ocupacion FROM ocupacion WHERE nombre_ocupacion='CHOFER')            WHERE id_persona = 10;
UPDATE persona SET id_ocupacion = (SELECT id_ocupacion FROM ocupacion WHERE nombre_ocupacion='CONTADOR(A)')       WHERE id_persona = 11;
UPDATE persona SET id_ocupacion = (SELECT id_ocupacion FROM ocupacion WHERE nombre_ocupacion='ALBANIL')           WHERE id_persona = 12;
UPDATE persona SET id_ocupacion = (SELECT id_ocupacion FROM ocupacion WHERE nombre_ocupacion='AMA DE CASA')       WHERE id_persona = 13;
UPDATE persona SET id_ocupacion = (SELECT id_ocupacion FROM ocupacion WHERE nombre_ocupacion='EMPLEADO(A)')       WHERE id_persona = 14;

-- ---------- Expedientes ----------
INSERT INTO registro_paciente (id_registro_paciente, num_expediente, id_persona, fecha_apertura) VALUES
(1, 'EXP-0001', 1, '2024-02-07'),
(2, 'EXP-0002', 2, '2024-03-01'),
(3, 'EXP-0003', 3, '2024-03-24'),
(4, 'EXP-0004', 4, '2024-04-16'),
(5, 'EXP-0005', 5, '2024-05-09'),
(6, 'EXP-0006', 6, '2024-06-01'),
(7, 'EXP-0007', 7, '2024-06-24'),
(8, 'EXP-0008', 8, '2024-07-17'),
(9, 'EXP-0009', 9, '2024-08-09'),
(10, 'EXP-0010', 10, '2024-09-01'),
(11, 'EXP-0011', 11, '2024-09-24'),
(12, 'EXP-0012', 12, '2024-10-17');

-- ---------- Tutores de menores ----------
INSERT INTO expediente_tutor (id_registro_paciente, id_persona, id_parentesco, es_responsable_pago) VALUES
(3, 13, 1, 1),   -- Rosa Elena es madre de Ana Sofia
(9, 14, 2, 1);   -- Sergio es padre de Diana Laura

-- ---------- Antecedentes clinicos ----------
INSERT INTO expediente_antecedente (id_registro_paciente, id_antecedente, detalle) VALUES
(2, 1, 'Diagnosticada hace 8 anios, controlada con metformina'),
(2, 2, 'Toma losartan 50mg'),
(5, 11, 'Reaccion cutanea severa'),
(8, 2, NULL),
(8, 21, 'Warfarina - requiere valoracion previa a cirugia'),
(10, 18, 'Bruxismo nocturno, se indica guarda oclusal');

-- ---------- Usuarios de demo (contrasena: Admin123!) ----------
INSERT INTO usuario (id_usuario, id_rol, nombre_usuario, nombre_completo, contrasena, correo, cedula_prof, es_dentista) VALUES
(2, 2, 'dr.mendoza', 'Dr. Carlos Mendoza Rivera', '$2y$10$pFoUAbzNEdEVA1C1Hk7glOUyjwgv1fsd.saymN8ohPcmOsVGdb4.K', 'dr.mendoza@consultorio.local', '1234567', 1),
(3, 2, 'dra.lopez',  'Dra. Paola Lopez Guerrero',  '$2y$10$pFoUAbzNEdEVA1C1Hk7glOUyjwgv1fsd.saymN8ohPcmOsVGdb4.K', 'dra.lopez@consultorio.local',  '7654321', 1),
(4, 3, 'recepcion',  'Lucia Barajas Ruiz',         '$2y$10$pFoUAbzNEdEVA1C1Hk7glOUyjwgv1fsd.saymN8ohPcmOsVGdb4.K', 'recepcion@consultorio.local',  NULL, 0);

-- ---------- Agenda ----------
SET @hoy = CURDATE();
INSERT INTO cita (id_cita, id_registro_paciente, id_usuario_dentista, id_sala, id_estado_cita, fecha_inicio, fecha_fin, motivo) VALUES
(1, 11, 3, 2, 4, DATE_ADD(@hoy, INTERVAL -3 DAY) + INTERVAL 13 HOUR, DATE_ADD(@hoy, INTERVAL -3 DAY) + INTERVAL 825 MINUTE, 'Limpieza dental'),
(2, 7, 2, 1, 4, DATE_ADD(@hoy, INTERVAL -3 DAY) + INTERVAL 16 HOUR, DATE_ADD(@hoy, INTERVAL -3 DAY) + INTERVAL 1005 MINUTE, 'Dolor en molar inferior'),
(3, 6, 2, 1, 6, DATE_ADD(@hoy, INTERVAL -2 DAY) + INTERVAL 9 HOUR, DATE_ADD(@hoy, INTERVAL -2 DAY) + INTERVAL 585 MINUTE, 'Ajuste de ortodoncia'),
(4, 6, 2, 1, 6, DATE_ADD(@hoy, INTERVAL -2 DAY) + INTERVAL 11 HOUR, DATE_ADD(@hoy, INTERVAL -2 DAY) + INTERVAL 705 MINUTE, 'Ajuste de ortodoncia'),
(5, 11, 3, 2, 4, DATE_ADD(@hoy, INTERVAL -2 DAY) + INTERVAL 13 HOUR, DATE_ADD(@hoy, INTERVAL -2 DAY) + INTERVAL 825 MINUTE, 'Dolor en molar inferior'),
(6, 8, 3, 2, 4, DATE_ADD(@hoy, INTERVAL -2 DAY) + INTERVAL 16 HOUR, DATE_ADD(@hoy, INTERVAL -2 DAY) + INTERVAL 1005 MINUTE, 'Limpieza dental'),
(7, 12, 3, 2, 4, DATE_ADD(@hoy, INTERVAL -1 DAY) + INTERVAL 9 HOUR, DATE_ADD(@hoy, INTERVAL -1 DAY) + INTERVAL 585 MINUTE, 'Valoracion'),
(8, 1, 2, 2, 4, DATE_ADD(@hoy, INTERVAL -1 DAY) + INTERVAL 11 HOUR, DATE_ADD(@hoy, INTERVAL -1 DAY) + INTERVAL 705 MINUTE, 'Revision general'),
(9, 11, 2, 2, 2, DATE_ADD(@hoy, INTERVAL 0 DAY) + INTERVAL 11 HOUR, DATE_ADD(@hoy, INTERVAL 0 DAY) + INTERVAL 705 MINUTE, 'Ajuste de ortodoncia'),
(10, 3, 3, 1, 2, DATE_ADD(@hoy, INTERVAL 0 DAY) + INTERVAL 13 HOUR, DATE_ADD(@hoy, INTERVAL 0 DAY) + INTERVAL 825 MINUTE, 'Ajuste de ortodoncia'),
(11, 9, 3, 1, 2, DATE_ADD(@hoy, INTERVAL 0 DAY) + INTERVAL 16 HOUR, DATE_ADD(@hoy, INTERVAL 0 DAY) + INTERVAL 1005 MINUTE, 'Ajuste de ortodoncia'),
(12, 9, 3, 2, 1, DATE_ADD(@hoy, INTERVAL 2 DAY) + INTERVAL 13 HOUR, DATE_ADD(@hoy, INTERVAL 2 DAY) + INTERVAL 825 MINUTE, 'Valoracion'),
(13, 1, 3, 2, 1, DATE_ADD(@hoy, INTERVAL 2 DAY) + INTERVAL 16 HOUR, DATE_ADD(@hoy, INTERVAL 2 DAY) + INTERVAL 1005 MINUTE, 'Continuacion de tratamiento'),
(14, 10, 3, 2, 1, DATE_ADD(@hoy, INTERVAL 3 DAY) + INTERVAL 9 HOUR, DATE_ADD(@hoy, INTERVAL 3 DAY) + INTERVAL 585 MINUTE, 'Valoracion'),
(15, 4, 2, 1, 1, DATE_ADD(@hoy, INTERVAL 3 DAY) + INTERVAL 16 HOUR, DATE_ADD(@hoy, INTERVAL 3 DAY) + INTERVAL 1005 MINUTE, 'Dolor en molar inferior'),
(16, 4, 3, 1, 1, DATE_ADD(@hoy, INTERVAL 4 DAY) + INTERVAL 9 HOUR, DATE_ADD(@hoy, INTERVAL 4 DAY) + INTERVAL 585 MINUTE, 'Dolor en molar inferior'),
(17, 11, 2, 1, 1, DATE_ADD(@hoy, INTERVAL 4 DAY) + INTERVAL 13 HOUR, DATE_ADD(@hoy, INTERVAL 4 DAY) + INTERVAL 825 MINUTE, 'Dolor en molar inferior'),
(18, 3, 3, 2, 1, DATE_ADD(@hoy, INTERVAL 5 DAY) + INTERVAL 11 HOUR, DATE_ADD(@hoy, INTERVAL 5 DAY) + INTERVAL 705 MINUTE, 'Limpieza dental'),
(19, 7, 2, 1, 1, DATE_ADD(@hoy, INTERVAL 6 DAY) + INTERVAL 9 HOUR, DATE_ADD(@hoy, INTERVAL 6 DAY) + INTERVAL 585 MINUTE, 'Revision general'),
(20, 10, 3, 1, 1, DATE_ADD(@hoy, INTERVAL 6 DAY) + INTERVAL 13 HOUR, DATE_ADD(@hoy, INTERVAL 6 DAY) + INTERVAL 825 MINUTE, 'Ajuste de ortodoncia'),
(21, 9, 2, 1, 1, DATE_ADD(@hoy, INTERVAL 7 DAY) + INTERVAL 13 HOUR, DATE_ADD(@hoy, INTERVAL 7 DAY) + INTERVAL 825 MINUTE, 'Limpieza dental');

-- ---------- Consultas realizadas ----------
INSERT INTO consulta (id_consulta, id_registro_paciente, id_usuario_dentista, fecha_hora, motivo, diagnostico, cerrada, fecha_cierre) VALUES
(1, 12, 2, DATE_SUB(NOW(), INTERVAL 139 DAY), 'Revision de rutina', 'Caries interproximal 24-25', 1, NOW()),
(2, 4, 2, DATE_SUB(NOW(), INTERVAL 12 DAY), 'Limpieza', 'Gingivitis leve generalizada', 1, NOW()),
(3, 5, 2, DATE_SUB(NOW(), INTERVAL 155 DAY), 'Limpieza', 'Sin hallazgos patologicos', 1, NOW()),
(4, 9, 3, DATE_SUB(NOW(), INTERVAL 38 DAY), 'Dolor dental', 'Sin hallazgos patologicos', 1, NOW()),
(5, 8, 3, DATE_SUB(NOW(), INTERVAL 133 DAY), 'Revision de rutina', 'Periodontitis cronica localizada', 1, NOW()),
(6, 3, 2, DATE_SUB(NOW(), INTERVAL 117 DAY), 'Revision de rutina', 'Periodontitis cronica localizada', 1, NOW()),
(7, 1, 2, DATE_SUB(NOW(), INTERVAL 49 DAY), 'Revision de rutina', 'Caries interproximal 24-25', 1, NOW()),
(8, 10, 2, DATE_SUB(NOW(), INTERVAL 147 DAY), 'Dolor dental', 'Sin hallazgos patologicos', 1, NOW()),
(9, 11, 3, DATE_SUB(NOW(), INTERVAL 32 DAY), 'Dolor dental', 'Gingivitis leve generalizada', 1, NOW()),
(10, 4, 3, DATE_SUB(NOW(), INTERVAL 15 DAY), 'Dolor dental', 'Periodontitis cronica localizada', 1, NOW()),
(11, 8, 2, DATE_SUB(NOW(), INTERVAL 21 DAY), 'Control de tratamiento', 'Sin hallazgos patologicos', 1, NOW()),
(12, 10, 2, DATE_SUB(NOW(), INTERVAL 75 DAY), 'Control de tratamiento', 'Periodontitis cronica localizada', 1, NOW()),
(13, 9, 3, DATE_SUB(NOW(), INTERVAL 134 DAY), 'Revision de rutina', 'Periodontitis cronica localizada', 1, NOW()),
(14, 5, 2, DATE_SUB(NOW(), INTERVAL 119 DAY), 'Revision de rutina', 'Caries interproximal 24-25', 1, NOW()),
(15, 2, 3, DATE_SUB(NOW(), INTERVAL 118 DAY), 'Limpieza', 'Caries oclusal en organo dentario 36', 1, NOW());

-- ---------- Planes de tratamiento ----------
INSERT INTO plan_tratamiento (id_plan_tratamiento, id_registro_paciente, id_usuario_dentista, folio, fecha, id_estado_plan, subtotal, total, saldo) VALUES
(1, 1, 2, 'PT-0001', DATE_SUB(CURDATE(), INTERVAL 40 DAY), 3, 4700.00, 4700.00, 1700.00),
(2, 4, 2, 'PT-0002', DATE_SUB(CURDATE(), INTERVAL 25 DAY), 2, 3500.00, 3500.00, 3500.00),
(3, 8, 3, 'PT-0003', DATE_SUB(CURDATE(), INTERVAL 12 DAY), 1, 12000.00, 12000.00, 12000.00);

INSERT INTO plan_tratamiento_detalle (id_plan_tratamiento, id_tratamiento, id_diente, cantidad, precio_unitario, importe, orden, estado_avance) VALUES
(1, 4, NULL, 1, 700.00, 700.00, 1, 2),
(1, 11, 36, 1, 2200.00, 2200.00, 2, 2),
(1, 20, 36, 1, 4500.00, 4500.00, 3, 1),
(2, 7, 24, 1, 750.00, 750.00, 1, 0),
(2, 7, 25, 1, 750.00, 750.00, 2, 0),
(2, 27, NULL, 1, 3500.00, 3500.00, 3, 0),
(3, 24, NULL, 1, 12000.00, 12000.00, 1, 0);

-- ---------- Caja abierta y pagos ----------
INSERT INTO corte_caja (id_corte_caja, id_caja, id_usuario_apertura, fecha_apertura, monto_inicial, abierto) VALUES
(1, 1, 4, DATE_SUB(NOW(), INTERVAL 30 DAY), 1000.00, 0),
(2, 1, 4, CONCAT(CURDATE(), ' 08:30:00'), 1500.00, 1);

INSERT INTO pago (id_pago, folio, id_registro_paciente, id_plan_tratamiento, id_corte_caja, id_usuario, fecha_hora, monto, observaciones) VALUES
(1, 'PG-000001', 1, 1, 1, 4, DATE_SUB(NOW(), INTERVAL 30 DAY), 2000.00, 'Anticipo de tratamiento'),
(2, 'PG-000002', 1, 1, 2, 4, NOW(), 1000.00, 'Abono'),
(3, 'PG-000003', 5, NULL, 2, 4, NOW(), 700.00, 'Limpieza dental');

INSERT INTO pago_forma (id_pago, id_forma_pago, monto) VALUES
(1, 1, 2000.00), (2, 3, 1000.00), (3, 1, 700.00);

INSERT INTO movimiento_caja (id_corte_caja, id_concepto, id_forma_pago, id_pago, id_usuario, naturaleza, monto, descripcion) VALUES
(1, 2, 1, 1, 4, 'I', 2000.00, 'Anticipo plan PT-0001'),
(2, 1, 3, 2, 4, 'I', 1000.00, 'Abono plan PT-0001'),
(2, 1, 1, 3, 4, 'I', 700.00, 'Limpieza dental'),
(2, 5, 1, NULL, 4, 'E', 450.00, 'Compra de guantes y gasas');

-- ---------- Odontograma inicial de un paciente ----------
INSERT INTO odontograma (id_odontograma, id_registro_paciente, fecha, tipo) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'inicial');
INSERT INTO odontograma_detalle (id_odontograma, id_diente, cara, id_condicion, nota) VALUES
(1, 36, 'O', 2, 'Caries profunda'),
(1, 24, 'M', 2, NULL),
(1, 16, 'O', 6, 'Resina previa en buen estado'),
(1, 18, NULL, 4, 'Extraido hace 3 anios'),
(1, 46, 'D', 2, NULL);

