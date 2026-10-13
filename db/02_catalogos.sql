-- =====================================================================
--  CATALOGOS BASE - Sistema Consultorio Dental
--  Se ejecuta despues de 01_schema.sql
-- =====================================================================
SET NAMES utf8mb4;
USE consultorio_dental;

-- ---------------------------------------------------------------------
-- Estados de la Republica Mexicana (clave INEGI como id)
-- ---------------------------------------------------------------------
INSERT INTO estado (id_estado, nombre_estado, abreviatura, clave_inegi) VALUES
(1,'AGUASCALIENTES','AGS','01'),
(2,'BAJA CALIFORNIA','BC','02'),
(3,'BAJA CALIFORNIA SUR','BCS','03'),
(4,'CAMPECHE','CAMP','04'),
(5,'COAHUILA DE ZARAGOZA','COAH','05'),
(6,'COLIMA','COL','06'),
(7,'CHIAPAS','CHIS','07'),
(8,'CHIHUAHUA','CHIH','08'),
(9,'CIUDAD DE MEXICO','CDMX','09'),
(10,'DURANGO','DGO','10'),
(11,'GUANAJUATO','GTO','11'),
(12,'GUERRERO','GRO','12'),
(13,'HIDALGO','HGO','13'),
(14,'JALISCO','JAL','14'),
(15,'MEXICO','MEX','15'),
(16,'MICHOACAN DE OCAMPO','MICH','16'),
(17,'MORELOS','MOR','17'),
(18,'NAYARIT','NAY','18'),
(19,'NUEVO LEON','NL','19'),
(20,'OAXACA','OAX','20'),
(21,'PUEBLA','PUE','21'),
(22,'QUERETARO','QRO','22'),
(23,'QUINTANA ROO','QROO','23'),
(24,'SAN LUIS POTOSI','SLP','24'),
(25,'SINALOA','SIN','25'),
(26,'SONORA','SON','26'),
(27,'TABASCO','TAB','27'),
(28,'TAMAULIPAS','TAMPS','28'),
(29,'TLAXCALA','TLAX','29'),
(30,'VERACRUZ DE IGNACIO DE LA LLAVE','VER','30'),
(31,'YUCATAN','YUC','31'),
(32,'ZACATECAS','ZAC','32');

-- ---------------------------------------------------------------------
-- Municipios de Nayarit (estado preseleccionado segun la composicion)
-- ---------------------------------------------------------------------
INSERT INTO municipio (id_estado, nombre_municipio, clave_inegi) VALUES
(18,'ACAPONETA','001'),
(18,'AHUACATLAN','002'),
(18,'AMATLAN DE CAÑAS','003'),
(18,'COMPOSTELA','004'),
(18,'HUAJICORI','005'),
(18,'IXTLAN DEL RIO','006'),
(18,'JALA','007'),
(18,'XALISCO','008'),
(18,'DEL NAYAR','009'),
(18,'ROSAMORADA','010'),
(18,'RUIZ','011'),
(18,'SAN BLAS','012'),
(18,'SAN PEDRO LAGUNILLAS','013'),
(18,'SANTA MARIA DEL ORO','014'),
(18,'SANTIAGO IXCUINTLA','015'),
(18,'TECUALA','016'),
(18,'TEPIC','017'),
(18,'TUXPAN','018'),
(18,'LA YESCA','019'),
(18,'BAHIA DE BANDERAS','020');

-- ---------------------------------------------------------------------
-- Tipos de asentamiento
-- Son los que venian en el archivo del cliente. Se vuelven catalogo para
-- que la captura sea por combo y no por texto libre: asi "FRACCIONAMIENTO"
-- no acaba escrito de cuatro maneras distintas.
-- ---------------------------------------------------------------------
INSERT INTO tipo_asenta (id_tipo_asenta, nombre_tipo_asenta) VALUES
(1, 'AEROPUERTO'),
(2, 'COLONIA'),
(3, 'EJIDO'),
(4, 'EQUIPAMIENTO'),
(5, 'FRACCIONAMIENTO'),
(6, 'HACIENDA'),
(7, 'PUEBLO'),
(8, 'RANCHERÍA'),
(9, 'RANCHO'),
(10, 'UNIDAD HABITACIONAL'),
(11, 'ZONA COMERCIAL'),
(12, 'ZONA INDUSTRIAL');

-- ---------------------------------------------------------------------
-- Colonias de Nayarit (Tepic y Xalisco)
-- Cargadas del archivo colonias_codigos.xls que proporciono el cliente.
-- Los ids de municipio del archivo coinciden con la clave INEGI, que es
-- el mismo orden en que se insertaron arriba: 8 = Xalisco, 17 = Tepic.
--
-- Los nombres van en MAYUSCULAS porque asi se capturan y se muestran
-- todos los datos del sistema.
--
-- Faltan los otros 18 municipios del estado. Cuando el consultorio los
-- necesite, se agregan desde Configuracion > Colonias o se carga el
-- catalogo completo de SEPOMEX.
-- ---------------------------------------------------------------------
INSERT INTO colonia (id_municipio, nombre_colonia, codigo_postal, id_tipo_asenta) VALUES
(8, '10 DE MAYO', '63787', 2),
(8, '14 DE FEBRERO', '63787', 2),
(8, '1RO. DE MAYO', '63783', 2),
(8, '24 DE FEBRERO', '63786', 2),
(8, '25 DE ABRIL', '63784', 2),
(8, 'ABEL ISIORDIA', '63784', 2),
(8, 'ADOLFO LÓPEZ MATEOS (LAS VEREDAS)', '63793', 8),
(8, 'ALBORADA', '63782', 5),
(8, 'AMPLIACIÓN NUEVO PROGRESO', '63782', 2),
(8, 'AMPLIACIÓN XALISCO', '63787', 2),
(8, 'AQUILES SERDÁN', '63794', 2),
(8, 'BUGAMBILIAS', '63787', 2),
(8, 'CANOAS', '63780', 5),
(8, 'COFRADÍA DE CHOCOLÓN', '63793', 8),
(8, 'COLINAS DE SAN JUAN', '63787', 2),
(8, 'COLINAS DE XALISCO', '63787', 5),
(8, 'EJIDAL', '63785', 2),
(8, 'EL CARRIZAL', '63793', 8),
(8, 'EL CUARENTEÑO', '63797', 6),
(8, 'EL GUAYABO', '63783', 2),
(8, 'EL INDIO', '63787', 2),
(8, 'EL MALINAL', '63790', 3),
(8, 'EL NAVARREÑO II', '63784', 5),
(8, 'EL TEPOZAL', '63797', 3),
(8, 'EMILIANO ZAPATA', '63787', 2),
(8, 'EMILIANO ZAPATA (MAJADAS)', '63794', 7),
(8, 'FERNANDO CARRILLO', '63786', 2),
(8, 'GUADALUPE VICTORIA', '63783', 2),
(8, 'HACIENDA REAL', '63785', 5),
(8, 'HUERTAS DE MATATIPAL', '63782', 2),
(8, 'JARDINES DE MATATIPAC', '63784', 2),
(8, 'JOSÉ MARÍA MORELOS', '63787', 2),
(8, 'JOSÉ SANTOS GODÍNEZ', '63783', 2),
(8, 'KIEKARI', '63787', 5),
(8, 'LA CRUZ', '63786', 2),
(8, 'LA CURVA', '63795', 3),
(8, 'LANDEREÑAS', '63784', 2),
(8, 'LAS ISABELES', '63787', 2),
(8, 'LAS ROSAS', '63787', 2),
(8, 'LAS ÁGUILAS', '63782', 2),
(8, 'LOMAS BONITAS', '63782', 2),
(8, 'LOMAS DEL NAYAR', '63782', 2),
(8, 'LOMAS VERDES', '63787', 2),
(8, 'LOMAS XALISCO', '63780', 5),
(8, 'LOS CORDONCILLOS II', '63784', 5),
(8, 'LOS FORTINES', '63795', 8),
(8, 'MINAS DE XALLI', '63787', 5),
(8, 'NAHIL', '63787', 5),
(8, 'NAVARREÑO', '63785', 2),
(8, 'NUEVA GALICIA', '63787', 2),
(8, 'NUEVO MÉXICO', '63787', 2),
(8, 'NUEVO PROGRESO', '63782', 2),
(8, 'PALAPITA', '63797', 8),
(8, 'PALOMAS DORADAS', '63788', 5),
(8, 'PANTANAL', '63792', 7),
(8, 'PROGRESO', '63783', 2),
(8, 'PUEBLO NUEVO', '63784', 5),
(8, 'PUERTA DEL SOL', '63783', 5),
(8, 'PUERTA LUNA', '63785', 5),
(8, 'RAMOS MILLÁN', '63784', 2),
(8, 'REAL DEL BOSQUE', '63787', 5),
(8, 'SALVADOR SÁNCHEZ VÁZQUEZ', '63782', 2),
(8, 'SAN ANTONIO', '63790', 8),
(8, 'SAN JOSÉ DE COSTILLA (ESTACIÓN COSTILLA)', '63794', 3),
(8, 'SANTA MÓNICA', '63787', 2),
(8, 'SUTSEM LANDEREÑAS', '63783', 2),
(8, 'TANUZ', '63787', 5),
(8, 'TEPIC (POETA AMADO NERVO)', '63799', 1),
(8, 'TESTERAZO', '63794', 7),
(8, 'TRIGOMIL', '63795', 3),
(8, 'VALLE DE ROMA', '63783', 5),
(8, 'VALLES DEL NAYAR', '63792', 2),
(8, 'VICENTE GUERRERO', '63787', 2),
(8, 'VILLAS DE GUADALUPE', '63787', 2),
(8, 'XALISCA RESIDENCIAL LOS LAURELES', '63785', 5),
(8, 'XALISCO CENTRO', '63780', 2),
(17, '12 DE DICIEMBRE', '63170', 2),
(17, '15 DE MAYO', '63190', 2),
(17, '18 DE AGOSTO', '63177', 2),
(17, '18 DE MARZO', '63500', 2),
(17, '2 DE AGOSTO', '63175', 2),
(17, '20 DE NOVIEMBRE', '63100', 2),
(17, '26 DE SEPTIEMBRE', '63196', 2),
(17, '3 DE JULIO', '63172', 5),
(17, '5 DE FEBRERO', '63197', 2),
(17, 'ACAYAPAN', '63081', 2),
(17, 'ADOLFO LÓPEZ MATEOS', '63021', 2),
(17, 'AGEUAN LOS LIMONES', '63177', 5),
(17, 'ALASKA', '63062', 5),
(17, 'AMADO NERVO', '63010', 2),
(17, 'AMPLIACIÓN EL PARAÍSO', '63035', 2),
(17, 'AMPLIACIÓN SANTA TERESITA', '63083', 2),
(17, 'AMPLIACIÓN TIERRA Y LIBERTAD', '63177', 2),
(17, 'AMÉRICA MANRÍQUEZ', '63068', 2),
(17, 'ANTONIO ECHEVARRÍA DOMÍNGUEZ', '63173', 2),
(17, 'ANTONIO R LAURELES', '63197', 2),
(17, 'ARAMARA', '63173', 5),
(17, 'ARBOLEDAS', '63129', 5),
(17, 'ATONALISCO', '63523', 8),
(17, 'AURORA', '63172', 5),
(17, 'AVES DEL PARAÍSO', '63129', 5),
(17, 'AVIACIÓN', '63190', 2),
(17, 'AZTLÁN EL VERDE', '63039', 2),
(17, 'AZTLÁN SOLIDARIDAD', '63114', 5),
(17, 'AZUCAREROS', '63500', 5),
(17, 'BARRANCA BLANCA', '63517', 8),
(17, 'BELLAVISTA', '63501', 7),
(17, 'BENITO JUÁREZ', '63166', 2),
(17, 'BENITO JUÁREZ (LAS CHIVAS)', '63515', 3),
(17, 'BENITO JUÁREZ ORIENTE', '63175', 2),
(17, 'BETHEL', '63180', 2),
(17, 'BIOSFERA RESIDENCIAL', '63165', 5),
(17, 'BONATERRA', '63194', 5),
(17, 'BRISAS DE SAN JUAN', '63117', 5),
(17, 'BUENOS AIRES', '63023', 2),
(17, 'BUENOS AIRES', '63524', 9),
(17, 'BUGAMBILIAS', '63035', 5),
(17, 'BURÓCRATA ESTATAL', '63180', 2),
(17, 'BURÓCRATA FEDERAL', '63156', 2),
(17, 'CAJA DE AGUA', '63158', 2),
(17, 'CAJA DE AGUA', '63500', 2),
(17, 'CALERAS DE COFRADOS', '63524', 8),
(17, 'CAMICHÍN DE JAUJA', '63508', 3),
(17, 'CAMINERA', '63196', 2),
(17, 'CAMINO REAL', '63039', 5),
(17, 'CANTERA DEL NAYAR', '63173', 5),
(17, 'CASTILLA', '63196', 5),
(17, 'CATORCE DE MARZO', '63510', 2),
(17, 'CEFERESO', '63502', 4),
(17, 'CHAPULTEPEC', '63030', 2),
(17, 'CHE GUEVARA', '63506', 2),
(17, 'CINCO DE MAYO (EL CIRUELO)', '63510', 8),
(17, 'CIUDAD DEL VALLE', '63157', 5),
(17, 'CIUDAD INDUSTRIAL', '63173', 12),
(17, 'CIUDAD INDUSTRIAL MICROINDUSTRIA', '63173', 12),
(17, 'COLINAS DEL NAYAR', '63114', 2),
(17, 'COLINAS DEL REY', '63180', 5),
(17, 'COLINAS DEL VALLE', '63173', 5),
(17, 'COLONIAL', '63176', 5),
(17, 'COMERCIANTES', '63175', 2),
(17, 'CONASUPO', '63195', 2),
(17, 'CORA', '63129', 2),
(17, 'CORRALÓN', '63163', 2),
(17, 'CUATRO MILPAS', '63174', 2),
(17, 'CUAUHTÉMOC', '63180', 2),
(17, 'CUBA', '63039', 5),
(17, 'CUITLAHUAC', '63030', 2),
(17, 'CÁP. OROZCO', '63176', 5),
(17, 'DEL BOSQUE', '63166', 2),
(17, 'DEL SOL', '63173', 2),
(17, 'DIAMANTE', '63511', 2),
(17, 'DIECISÉIS DE SEPTIEMBRE', '63114', 2),
(17, 'DR. IGNACIO CUESTA BARRIOS', '63175', 2),
(17, 'DR. LUCAS VALLARTA', '63170', 2),
(17, 'ECOLOGISTAS', '63114', 2),
(17, 'EJIDAL', '63173', 2),
(17, 'EL AHUACATE', '63507', 3),
(17, 'EL ARMADILLO', '63194', 2),
(17, 'EL CAMPO', '63500', 2),
(17, 'EL CAPOMO', '63177', 2),
(17, 'EL CERRO', '63500', 2),
(17, 'EL CHORRO', '63500', 2),
(17, 'EL COLORADO', '63502', 8),
(17, 'EL ESPINO', '63515', 3),
(17, 'EL FAISÁN', '63082', 2),
(17, 'EL FLOREÑO (EL CAPULÍN)', '63524', 8),
(17, 'EL IZOTE', '63507', 8),
(17, 'EL JICOTE', '63510', 8),
(17, 'EL LIMÓN', '63059', 2),
(17, 'EL LIMÓN', '63517', 8),
(17, 'EL MIRADOR INFONAVIT', '63038', 10),
(17, 'EL NARANJAL', '63082', 2),
(17, 'EL OCHO', '63185', 5),
(17, 'EL PALOMAR', '63062', 2),
(17, 'EL PARAÍSO', '63038', 2),
(17, 'EL PARÍAN', '63050', 2),
(17, 'EL PEDREGAL', '63164', 2),
(17, 'EL PICHÓN', '63505', 8),
(17, 'EL PUERTO', '63194', 2),
(17, 'EL PUNTO', '63018', 2),
(17, 'EL REFUGIO', '63509', 8),
(17, 'EL RINCÓN', '63502', 3),
(17, 'EL RODEO', '63060', 2),
(17, 'EL RUBÍ', '63173', 2),
(17, 'EL TECOLOTE', '63135', 2),
(17, 'EL TECOLOTE INFONAVIT', '63135', 10),
(17, 'EL TRAPICHILLO', '63517', 3),
(17, 'EL VERDE', '63511', 2),
(17, 'ELECTRICISTAS', '63160', 2),
(17, 'EMILIANO ZAPATA', '63070', 2),
(17, 'EMILIO M. GONZÁLEZ', '63038', 2),
(17, 'ESTADIOS', '63109', 5),
(17, 'ESTEBAN BACA CALDERÓN', '63000', 10),
(17, 'ESTEBAN BACA CALDERÓN', '63173', 5),
(17, 'EXTAMEX', '63195', 2),
(17, 'FERROCARRILERO 1A SECC.', '63177', 2),
(17, 'FERROCARRILERO 2A SECC.', '63170', 2),
(17, 'FLAMINGOS', '63170', 5),
(17, 'FLORENCIA', '63066', 5),
(17, 'FLORES MAGÓN', '63174', 2),
(17, 'FOVISSSTE 1A ETAPA', '63119', 10),
(17, 'FOVISSSTE 2A ETAPA', '63116', 10),
(17, 'FRANCISCO I. MADERO (PUGA) CENTRO', '63500', 2),
(17, 'FRANCISCO VELÁZQUEZ', '63500', 2),
(17, 'FRANCISCO VILLA', '63019', 2),
(17, 'FRAY JUNIPERO SERRA', '63169', 5),
(17, 'FÉLIX PEÑA', '63185', 2),
(17, 'GENARO VÁZQUEZ', '63174', 2),
(17, 'GILBERTO FLORES MUÑOZ', '63179', 2),
(17, 'GOBERNADORES', '63175', 2),
(17, 'GOBIERNO DEL CAMBIO I', '63170', 5),
(17, 'GUADALUPE', '63114', 2),
(17, 'GUSTAVO DÍAZ ORDAZ', '63176', 2),
(17, 'HEBRÓN', '63500', 2),
(17, 'HERIBERTO CASAS', '63080', 2),
(17, 'HERIBERTO JARA', '63177', 2),
(17, 'HERMOSA PROVINCIA', '63197', 2),
(17, 'HUACHINES', '63507', 8),
(17, 'IMSS', '63120', 10),
(17, 'IMSS', '63186', 5),
(17, 'INDECO', '63022', 2),
(17, 'INDEPENDENCIA', '63136', 2),
(17, 'INGENIERO AGRÓNOMO', '63037', 2),
(17, 'INGENIERO AGUAYO', '63084', 2),
(17, 'INSTITUTO TECNOLÓGICO DE TEPIC', '63175', 4),
(17, 'INSURGENTES', '63183', 2),
(17, 'ISLAS DEL PARAÍSO', '63035', 5),
(17, 'JACARANDAS', '63195', 5),
(17, 'JAGÜEY', '63129', 2),
(17, 'JARDINES DE LA CRUZ', '63168', 5),
(17, 'JARDINES DEL PARAÍSO', '63035', 5),
(17, 'JARDINES DEL PARQUE', '63173', 5),
(17, 'JARDINES DEL VALLE', '63035', 2),
(17, 'JAZMINES', '63173', 2),
(17, 'JESÚS GARCÍA', '63173', 2),
(17, 'JESÚS MARÍA CORTÉS', '63522', 8),
(17, 'JESÚS SALAS', '63018', 2),
(17, 'JOSÉ MARÍA MARTÍNEZ (EL MOLINO)', '63190', 2),
(17, 'JUAN ESCUTIA', '63190', 2),
(17, 'JUMATÁN', '63515', 2),
(17, 'JUSTINO ÁVILA ARCE', '63173', 2),
(17, 'JUVENTUD', '63177', 2),
(17, 'LA CANTERA', '63506', 3),
(17, 'LA CORREGIDORA', '63511', 2),
(17, 'LA ESCONDIDA', '63502', 3),
(17, 'LA ESPERANZA', '63174', 2),
(17, 'LA FLORESTA', '63173', 5),
(17, 'LA FORTUNA', '63505', 3),
(17, 'LA GUADALUPANA', '63511', 2),
(17, 'LA HERRADURA', '63502', 3),
(17, 'LA HUERTA', '63070', 5),
(17, 'LA JOYA', '63195', 5),
(17, 'LA LOMA', '63037', 5),
(17, 'LA LOMITA', '63037', 5),
(17, 'LA RESOLANA', '63515', 8),
(17, 'LA YERBA', '63519', 8),
(17, 'LABORES DE GODÍNEZ', '63167', 2),
(17, 'LADRILLERAS', '63194', 2),
(17, 'LAGOS DE AZTLÁN', '63170', 2),
(17, 'LAGOS DEL COUNTRY', '63175', 5),
(17, 'LAS AVES', '63170', 5),
(17, 'LAS BLANCAS (CARRETONES DE CERRITOS)', '63529', 3),
(17, 'LAS BRISAS', '63117', 5),
(17, 'LAS BRISAS FOVISSSTE', '63117', 5),
(17, 'LAS CONCHAS', '63038', 2),
(17, 'LAS CRUCES', '63500', 2),
(17, 'LAS CUMBRES', '63185', 5),
(17, 'LAS DELICIAS', '63507', 8),
(17, 'LAS FINCAS', '63174', 5),
(17, 'LAS FLORES', '63067', 2),
(17, 'LAS ISLAS', '63195', 2),
(17, 'LAS JOYAS', '63511', 2),
(17, 'LAS NORIAS DEL MANANTIAL', '63523', 8),
(17, 'LAS PALOMAS', '63195', 2),
(17, 'LAS PAREJAS', '63500', 2),
(17, 'LAS PARES', '63038', 5),
(17, 'LAS PEÑITAS (LA REPISA)', '63512', 8),
(17, 'LAS PILAS (EL TEPEGUAJE)', '63514', 3),
(17, 'LEONARDO RODRÍGUEZ ALCAINE', '63129', 5),
(17, 'LEYVA MEDINA', '63186', 5),
(17, 'LINDAVISTA', '63110', 5),
(17, 'LIRIOS', '63177', 2),
(17, 'LO DE GARCÍA', '63512', 8),
(17, 'LO DE LAMEDO', '63505', 3),
(17, 'LOMA HERMOSA', '63019', 2),
(17, 'LOMAS ALTAS', '63061', 2),
(17, 'LOMAS BONITAS', '63062', 2),
(17, 'LOMAS DE CORTEZ', '63059', 2),
(17, 'LOMAS DE LA CRUZ', '63037', 5),
(17, 'LOMAS DE LA CRUZ SUTSE', '63030', 5),
(17, 'LOMAS DE LA LAGUNA', '63059', 2),
(17, 'LOMAS DE LINDAVISTA', '63115', 5),
(17, 'LOMAS DE SAN JUAN', '63128', 2),
(17, 'LOMAS DEL ENCANTO', '63173', 5),
(17, 'LOMAS DEL VALLE', '63066', 2),
(17, 'LOS ARCOS', '63037', 5),
(17, 'LOS CIRUELOS', '63195', 5),
(17, 'LOS COLOMOS', '63177', 2),
(17, 'LOS FRESNOS', '63197', 10),
(17, 'LOS FRESNOS INFONAVIT', '63197', 10),
(17, 'LOS FRESNOS ORIENTE', '63190', 2),
(17, 'LOS FRESNOS PONIENTE', '63185', 2),
(17, 'LOS LLANITOS', '63170', 2),
(17, 'LOS MANANTIALES', '63177', 5),
(17, 'LOS PINOS', '63084', 2),
(17, 'LOS SABINOS', '63500', 8),
(17, 'LOS SALAZARES', '63514', 3),
(17, 'LOS SAUCES', '63197', 2),
(17, 'LOS SAUCES INFONAVIT', '63195', 10),
(17, 'LOS VIVEROS', '63037', 2),
(17, 'LOS ÁLAMOS', '63172', 5),
(17, 'LUIS DONALDO COLOSIO', '63178', 10),
(17, 'LUIS DONALDO COLOSIO MURRIETA', '63175', 10),
(17, 'LUIS ECHEVERRÍA A.', '63068', 2),
(17, 'LÁZARO CÁRDENAS', '63190', 2),
(17, 'LÁZARO CÁRDENAS', '63500', 2),
(17, 'MAGISTERIAL', '63040', 2),
(17, 'MARCO ANTONIO FERNÁNDEZ', '63195', 5),
(17, 'MENCHACA', '63150', 2),
(17, 'MESA DE PICACHOS (EL CASCO)', '63528', 3),
(17, 'MIGUEL HIDALGO', '63193', 2),
(17, 'MIGUEL ÁNGEL PAREDES', '63050', 2),
(17, 'MIRAVALLES', '63184', 2),
(17, 'MOCTEZUMA', '63180', 2),
(17, 'MOLINOS DEL REY', '63173', 5),
(17, 'MOLOLOA', '63050', 2),
(17, 'MORA', '63504', 3),
(17, 'MORELOS', '63160', 2),
(17, 'MORELOS I', '63507', 5),
(17, 'MÉXICO', '63170', 2),
(17, 'NAYARABASTOS', '63173', 11),
(17, 'NIÑOS HÉROES', '63197', 2),
(17, 'NOVILLERO', '63511', 2),
(17, 'NUEVA ALEMANIA', '63164', 2),
(17, 'NUEVA AVIACIÓN', '63196', 2),
(17, 'NUEVA VALEY', '63500', 2),
(17, 'NUEVAS DELICIAS', '63114', 2),
(17, 'NUEVAS PALOMAS', '63194', 2),
(17, 'NUEVO AMANECER', '63172', 2),
(17, 'NUEVO PROGRESO', '63035', 2),
(17, 'OBRERA CTM', '63120', 2),
(17, 'OJO DE AGUA', '63023', 2),
(17, 'OLIMPO', '63173', 5),
(17, 'ORIENTAL', '63175', 2),
(17, 'PAJUELAZO (EL GUANACASTE)', '63515', 3),
(17, 'PARAÍSO RESIDENCIAL', '63194', 5),
(17, 'PARQUE ECOLÓGICO', '63173', 5),
(17, 'PARQUE FUNERAL JARDINES DE SAN JUAN', '63114', 4),
(17, 'PASEO DE LA CONSTITUCIÓN', '63010', 5),
(17, 'PASEO DEL VALLE REAL', '63195', 5),
(17, 'PEDREGAL DE SAN JUAN', '63164', 2),
(17, 'PERIODISTAS', '63196', 2),
(17, 'PEÑITA', '63167', 2),
(17, 'PLAN DE AYALA', '63197', 2),
(17, 'PLATANITOS', '63507', 8),
(17, 'PORTA ALTA', '63173', 5),
(17, 'PRIETO CRISPÍN', '63174', 2),
(17, 'PRIMERO DE MAYO', '63069', 2),
(17, 'PUENTE DE SAN CAYETANO', '63194', 2),
(17, 'PUERTA DE LA LAGUNA', '63039', 2),
(17, 'PUERTA ENCANTO', '63173', 5),
(17, 'PUERTA JARDÍN', '63173', 5),
(17, 'PUERTA PARAÍSO', '63173', 5),
(17, 'PUESTA DEL SOL', '63173', 5),
(17, 'QUINTA LOS LIRIOS', '63175', 5),
(17, 'RANCHO LAS CRUCES', '63038', 5),
(17, 'REAL DE LA NORIA', '63173', 5),
(17, 'REAL DE LOZADA', '63173', 2),
(17, 'REAL MONTECARLO', '63039', 5),
(17, 'REFORMA', '63038', 2),
(17, 'RESIDENCIAL LA ESMERALDA', '63037', 5),
(17, 'RESIDENCIAL LA LOMA', '63137', 5),
(17, 'RESIDENCIAL LOS OLIVOS', '63114', 5),
(17, 'RESPONSABILIDAD SOCIAL COMUNITARIA', '63173', 5),
(17, 'REVOLUCIÓN', '63173', 5),
(17, 'REY NAYAR', '63129', 2),
(17, 'RINCONADA RESIDENCIAL', '63062', 5),
(17, 'RINCÓN DE SAN JUAN', '63138', 2),
(17, 'RIVAS ALLENDE', '63058', 2),
(17, 'RIVERAS DE LA LAGUNA', '63039', 5),
(17, 'RODEO DE LA PUNTA', '63110', 5),
(17, 'ROSETA (LA TRISTEZA)', '63513', 8),
(17, 'RUINAS DE JAUJA', '63083', 2),
(17, 'SACRISTÁN', '63010', 2),
(17, 'SAN ANDRÉS', '63502', 7),
(17, 'SAN ANTONIO', '63159', 2),
(17, 'SAN ANTONIO DE MORA', '63506', 5),
(17, 'SAN CAYETANO', '63511', 3),
(17, 'SAN FERNANDO', '63503', 3),
(17, 'SAN JOSÉ', '63030', 2),
(17, 'SAN JUAN', '63130', 2),
(17, 'SAN JUANITO', '63059', 2),
(17, 'SAN LUIS DE LOZADA', '63527', 7),
(17, 'SAN MARTÍN', '63186', 5),
(17, 'SAN ÁNGEL', '63120', 5),
(17, 'SANDINO', '63058', 2),
(17, 'SANTA CECILIA', '63089', 2),
(17, 'SANTA FE', '63088', 2),
(17, 'SANTA TERESITA', '63020', 2),
(17, 'SANTIAGO DE POCHOTITÁN', '63524', 7),
(17, 'SEIS DE ENERO', '63504', 2),
(17, 'SENDEROS DEL MONTE', '63023', 5),
(17, 'SEVERIANO OCEGUEDA', '63195', 2),
(17, 'SIMANCAS', '63177', 5),
(17, 'SINDICALISTAS', '63037', 5),
(17, 'SOLIDARIDAD INFONAVIT', '63114', 10),
(17, 'SOLIDARIDAD PRIMAVERA', '63114', 2),
(17, 'SPAUAN', '63115', 5),
(17, 'SUTSEM 21 DE MAYO', '63173', 2),
(17, 'SÁNCHEZ IBARRA', '63058', 5),
(17, 'TEPIC CENTRO', '63000', 2),
(17, 'TIERRA Y LIBERTAD', '63178', 2),
(17, 'TULIPANES', '63035', 5),
(17, 'TÍO BALTAZAR', '63129', 5),
(17, 'UNIDAD DEPORTIVA SANTA TERESITA', '63020', 2),
(17, 'UNIDAD OBRERA', '63069', 2),
(17, 'UNIDOS POR TU TRANQUILIDAD', '63172', 5),
(17, 'UNIVERSIDAD AUTÓNOMA DE NAYARIT', '63155', 4),
(17, 'UNIÓN POPULAR', '63197', 2),
(17, 'VALLE DE LA CRUZ', '63035', 2),
(17, 'VALLE DE MATATIPAC', '63195', 2),
(17, 'VALLE DE NAYARIT', '63180', 2),
(17, 'VALLE DE ZARAGOZA', '63035', 2),
(17, 'VALLE DEL COUNTRY', '63175', 2),
(17, 'VALLE DORADO', '63180', 2),
(17, 'VALLE MAGNO', '63194', 5),
(17, 'VALLE VERDE CONALEP', '63037', 2),
(17, 'VALLE VERDE DE MATATIPAC', '63035', 2),
(17, 'VENCEREMOS', '63174', 2),
(17, 'VENUSTIANO CARRANZA', '63507', 3),
(17, 'VERSALLES', '63139', 5),
(17, 'VERSALLES SUR', '63138', 5),
(17, 'VILLA LAS ROSAS', '63177', 2),
(17, 'VILLA SAN ÁNGEL', '63120', 2),
(17, 'VILLAS ARAMARA', '63173', 5),
(17, 'VILLAS ARANA', '63037', 5),
(17, 'VILLAS DE AZTLAN', '63037', 5),
(17, 'VILLAS DE LA CANTERA', '63173', 5),
(17, 'VILLAS DE LA CRUZ', '63037', 5),
(17, 'VILLAS DE LA LAGUNA', '63037', 5),
(17, 'VILLAS DE LA PAZ', '63190', 5),
(17, 'VILLAS DE MATATIPAC', '63059', 2),
(17, 'VILLAS DE SAN JUAN', '63129', 5),
(17, 'VILLAS DE ZAPOPAN', '63037', 5),
(17, 'VILLAS DEL MOLINO', '63173', 5),
(17, 'VILLAS DEL NAYAR', '63177', 5),
(17, 'VILLAS DEL PARAÍSO', '63035', 5),
(17, 'VILLAS DEL PARQUE', '63173', 5),
(17, 'VILLAS DEL PRADO', '63173', 5),
(17, 'VILLAS DEL ROBLE', '63173', 5),
(17, 'VISTAS DE LA CANTERA', '63173', 5),
(17, 'VOLCANES', '63511', 2),
(17, 'ZAPOTE DE PICACHOS', '63526', 3),
(17, 'ZITÁCUA', '63174', 2);

-- ---------------------------------------------------------------------
-- Catalogos de persona
-- ---------------------------------------------------------------------
INSERT INTO genero (id_genero, nombre_genero) VALUES
(1,'Femenino'), (2,'Masculino'), (3,'Otro'), (4,'Prefiere no decir');

INSERT INTO tipo_persona (id_tipo_persona, tipo_persona) VALUES
(1,'Paciente'), (2,'Tutor'), (3,'Empleado'), (4,'Proveedor');

INSERT INTO parentesco (id_parentesco, nombre_parentesco) VALUES
(1,'Madre'), (2,'Padre'), (3,'Abuelo(a)'), (4,'Tio(a)'),
(5,'Hermano(a)'), (6,'Conyuge'), (7,'Tutor legal'), (8,'Otro');

-- Ocupaciones mas usuales. La columna clave sirve para que el sistema
-- pueda referirse a NINGUNA y ESTUDIANTE sin depender de un id fijo:
--   menores de 3 anios      -> NINGUNA
--   de 3 a 15 anios         -> ESTUDIANTE
--   de 16 en adelante       -> se captura
INSERT INTO ocupacion (clave, nombre_ocupacion) VALUES
('NINGUNA',   'NINGUNA'),
('ESTUDIANTE','ESTUDIANTE'),
(NULL,'ABOGADO(A)'),
(NULL,'ADMINISTRADOR(A)'),
(NULL,'AGENTE DE SEGUROS'),
(NULL,'AGENTE DE VENTAS'),
(NULL,'AGRICULTOR(A)'),
(NULL,'ALBANIL'),
(NULL,'ALMACENISTA'),
(NULL,'AMA DE CASA'),
(NULL,'ARQUITECTO(A)'),
(NULL,'ASISTENTE'),
(NULL,'AUXILIAR ADMINISTRATIVO'),
(NULL,'BOMBERO(A)'),
(NULL,'CAJERO(A)'),
(NULL,'CAMPESINO(A)'),
(NULL,'CARNICERO(A)'),
(NULL,'CARPINTERO(A)'),
(NULL,'CHOFER'),
(NULL,'COCINERO(A)'),
(NULL,'COMERCIANTE'),
(NULL,'CONTADOR(A)'),
(NULL,'COSTURERO(A)'),
(NULL,'DENTISTA'),
(NULL,'DEPORTISTA'),
(NULL,'DESEMPLEADO(A)'),
(NULL,'DISENADOR(A)'),
(NULL,'ELECTRICISTA'),
(NULL,'EMPLEADO(A)'),
(NULL,'EMPLEADO(A) DE GOBIERNO'),
(NULL,'EMPLEADO(A) DOMESTICO(A)'),
(NULL,'EMPRESARIO(A)'),
(NULL,'ENFERMERO(A)'),
(NULL,'ESTILISTA'),
(NULL,'FARMACEUTICO(A)'),
(NULL,'FISIOTERAPEUTA'),
(NULL,'FOTOGRAFO(A)'),
(NULL,'GANADERO(A)'),
(NULL,'GERENTE'),
(NULL,'HERRERO(A)'),
(NULL,'HOJALATERO(A)'),
(NULL,'INGENIERO(A)'),
(NULL,'INTENDENTE'),
(NULL,'JARDINERO(A)'),
(NULL,'JUBILADO(A)'),
(NULL,'MECANICO(A)'),
(NULL,'MEDICO(A)'),
(NULL,'MESERO(A)'),
(NULL,'MILITAR'),
(NULL,'MUSICO(A)'),
(NULL,'NINERO(A)'),
(NULL,'NUTRIOLOGO(A)'),
(NULL,'OBRERO(A)'),
(NULL,'OPERADOR(A) DE MAQUINARIA'),
(NULL,'PANADERO(A)'),
(NULL,'PENSIONADO(A)'),
(NULL,'PERIODISTA'),
(NULL,'PESCADOR(A)'),
(NULL,'PINTOR(A)'),
(NULL,'PLOMERO(A)'),
(NULL,'POLICIA'),
(NULL,'PROFESOR(A)'),
(NULL,'PROGRAMADOR(A)'),
(NULL,'PSICOLOGO(A)'),
(NULL,'RECEPCIONISTA'),
(NULL,'REPARTIDOR(A)'),
(NULL,'SACERDOTE'),
(NULL,'SASTRE'),
(NULL,'SECRETARIO(A)'),
(NULL,'SOLDADOR(A)'),
(NULL,'SUPERVISOR(A)'),
(NULL,'TAXISTA'),
(NULL,'TECNICO(A)'),
(NULL,'TRABAJADOR(A) SOCIAL'),
(NULL,'TRAILERO(A)'),
(NULL,'VELADOR(A)'),
(NULL,'VENDEDOR(A)'),
(NULL,'VETERINARIO(A)'),
(NULL,'ZAPATERO(A)'),
(NULL,'OTRA');

INSERT INTO medio_contacto (id_medio_contacto, nombre_medio_contacto, descripcion, tipo_dato) VALUES
(1,'Telefono celular','Numero movil a 10 digitos','tel'),
(2,'Telefono fijo','Numero de casa u oficina','tel'),
(3,'WhatsApp','Numero con WhatsApp activo','tel'),
(4,'Correo electronico','Direccion de correo','email'),
(5,'Facebook','Perfil o pagina','texto'),
(6,'Contacto de emergencia','Nombre y telefono','texto');

-- ---------------------------------------------------------------------
-- Expediente electronico
--
-- pendiente_al_alta quedo en 0 para todos. Antes se apuntaba el
-- faltante al registrar al paciente, y el expediente nacia con avisos
-- rojos de papeles que todavia no existian. Ahora el pendiente se crea
-- cuando el papel se genera: al guardar la historia clinica, al
-- imprimir el consentimiento, al crear la receta.
-- ---------------------------------------------------------------------
-- El campo 'ambito' decide donde aparece el documento en el expediente:
--   PACIENTE = bloque de arriba, comun a todo el expediente
--   PLAN     = dentro del bloque del plan de tratamiento que lo genero
-- Y 'permite_varios' se lee dentro de ese ambito: el consentimiento es
-- unico POR PLAN, no uno solo en toda la vida del paciente.
INSERT INTO tipo_documento
  (id_tipo_documento, clave, nombre_documento, descripcion, icono,
   pendiente_al_alta, permite_varios, ambito, formatos, orden) VALUES

-- ---- Del paciente: valen para todo el expediente ----
(1,'FOTO_PACIENTE','Fotografia del paciente',
 'Foto de frente para identificarlo en el expediente',
 'bi-person-badge', 0, 0, 'PACIENTE', 'jpg,jpeg,png,heic', 1),
(5,'IDENTIFICACION_TUTOR','Credencial del tutor',
 'Credencial vigente del tutor o responsable legal. Una por cada tutor.',
 'bi-person-vcard', 0, 1, 'PACIENTE', 'pdf,jpg,jpeg,png', 2),
(2,'HISTORIAL_CLINICO','Historial clinico firmado',
 'Hoja de historia clinica firmada por el tutor y el dentista',
 'bi-clipboard2-pulse', 0, 0, 'PACIENTE', 'pdf,jpg,jpeg,png', 3),
(7,'OTRO','Otro documento',
 'Cualquier otro documento del expediente',
 'bi-paperclip', 0, 1, 'PACIENTE', 'pdf,jpg,jpeg,png,doc,docx', 9),

-- ---- Del plan de tratamiento ----
(3,'CONSENTIMIENTO','Consentimiento bajo informacion',
 'Se imprime prellenado, se firma y se escanea. Uno por plan.',
 'bi-file-earmark-check', 0, 0, 'PLAN', 'pdf,jpg,jpeg,png', 1),
(4,'PLAN_TRATAMIENTO','Hoja del plan de tratamiento',
 'Plan firmado por el tutor y el dentista',
 'bi-list-check', 0, 0, 'PLAN', 'pdf,jpg,jpeg,png', 2),

-- ---- De la consulta ----
-- Una consulta ligada a un plan arrastra sus documentos al bloque de
-- ese plan, anidados bajo la consulta. Una consulta suelta los deja en
-- el bloque general. Por eso no hace falta duplicar los tipos.
(8,'RECETA','Receta',
 'Receta entregada en la consulta, firmada y escaneada',
 'bi-prescription2', 0, 1, 'CONSULTA', 'pdf,jpg,jpeg,png', 1),
(6,'RADIOGRAFIA','Radiografia',
 'Radiografias periapicales o panoramicas',
 'bi-radioactive', 0, 1, 'CONSULTA', 'pdf,jpg,jpeg,png,bmp,heic', 2),
(9,'FOTOGRAFIA','Fotografia clinica',
 'Fotografias intraorales y de avance del tratamiento',
 'bi-camera', 0, 1, 'CONSULTA', 'jpg,jpeg,png,bmp,heic', 3),
(11,'OTRO_CONSULTA','Otro documento de la consulta',
 'Estudios externos, interconsultas, cualquier otro papel',
 'bi-paperclip', 0, 1, 'CONSULTA', 'pdf,jpg,jpeg,png,bmp,heic,doc,docx', 9);

-- NOTA_EVOLUCION ya no es un tipo de documento: las notas se escriben
-- en el sistema y viven en la tabla nota_evolucion. No hay papel que
-- escanear, asi que no tienen por que generar un pendiente.

INSERT INTO tipo_identificacion
  (id_tipo_identificacion, nombre_identificacion, tiene_vigencia) VALUES
(1,'CREDENCIAL PARA VOTAR (INE)',1),
(2,'PASAPORTE',1),
(3,'LICENCIA DE CONDUCIR',1),
(4,'CEDULA PROFESIONAL',0),
(5,'CARTILLA MILITAR',0),
(6,'DOCUMENTO MIGRATORIO',1),
(7,'CREDENCIAL DE TRABAJO',1),
(8,'OTRA',1);

-- ---------------------------------------------------------------------
-- Historia clinica
-- ---------------------------------------------------------------------
INSERT INTO tipo_antecedente (id_tipo_antecedente, nombre_tipo) VALUES
(1,'Antecedente patologico'), (2,'Alergia'), (3,'Habito'), (4,'Medicamento actual');

INSERT INTO antecedente (id_tipo_antecedente, nombre_antecedente, requiere_alerta) VALUES
(1,'Diabetes',1), (1,'Hipertension arterial',1), (1,'Cardiopatia',1),
(1,'Problemas de coagulacion',1), (1,'Hepatitis',1), (1,'VIH',1),
(1,'Epilepsia',1), (1,'Embarazo',1), (1,'Asma',0), (1,'Cancer',1),
(2,'Penicilina',1), (2,'Anestesia local (lidocaina)',1), (2,'Aspirina / AINEs',1),
(2,'Latex',1), (2,'Sulfas',1),
(3,'Tabaquismo',0), (3,'Alcoholismo',0), (3,'Bruxismo',0),
(3,'Onicofagia',0), (3,'Respirador bucal',0),
(4,'Anticoagulantes',1), (4,'Bifosfonatos',1), (4,'Corticoides',0),
(4,'Antihipertensivos',0), (4,'Insulina',1);

-- ---------------------------------------------------------------------
-- Casillas de la historia clinica
-- Son las que vienen marcadas con cuadrito en las hojas del consultorio.
-- ---------------------------------------------------------------------
INSERT INTO catalogo_clinico (seccion, clave, nombre, pide_detalle, orden) VALUES
-- Habitos
('HABITO','BIBERON',              'Biberon',                0, 1),
('HABITO','SUCCION_DEDO',         'Succion de dedo',        0, 2),
('HABITO','CHUPON',               'Chupon',                 0, 3),
('HABITO','ONICOFAGIA',           'Onicofagia',             0, 4),
('HABITO','RESPIRACION_ORAL',     'Respiracion oral',       0, 5),
('HABITO','INTERPOSICION_LINGUAL','Interposicion lingual',  0, 6),
('HABITO','BRUXISMO',             'Bruxismo',               0, 7),
('HABITO','DEGLUCION_ATIPICA',    'Deglucion atipica',      0, 8),
('HABITO','FONACION',             'Fonacion',               0, 9),
('HABITO','OTRO_HABITO',          'Otro',                   1, 10),

-- Anomalias de numero
('ANOM_NUMERO','SUPERNUMERARIOS','Supernumerarios', 1, 1),
('ANOM_NUMERO','AGENESIAS',      'Agenesias',       1, 2),

-- Anomalias de tamano
('ANOM_TAMANO','MACRODONCIA','Macrodoncia', 1, 1),
('ANOM_TAMANO','MICRODONCIA','Microdoncia', 1, 2),

-- Anomalias de estructura
('ANOM_ESTRUCTURA','HIPOPLASIA',      'Hipoplasia',            1, 1),
('ANOM_ESTRUCTURA','HIPOCALCIFICACION','Hipocalcificacion',    1, 2),
('ANOM_ESTRUCTURA','PIGM_ENDOGENA',   'Pigmentacion endogena', 1, 3),
('ANOM_ESTRUCTURA','PIGM_EXOGENA',    'Pigmentacion exogena',  1, 4),

-- Anomalias de forma
('ANOM_FORMA','FUSION',    'Fusion',     1, 1),
('ANOM_FORMA','GEMINACION','Geminacion', 1, 2),
('ANOM_FORMA','CONOIDES',  'Conoides',   1, 3);

-- ---------------------------------------------------------------------
-- Seguridad
-- ---------------------------------------------------------------------
INSERT INTO rol (id_rol, nombre_rol, descripcion) VALUES
(1,'Administrador','Acceso total al sistema, incluida la configuracion'),
(2,'Dentista','Consultas, planes de tratamiento, odontograma y agenda'),
(3,'Recepcion','Pacientes, agenda, caja y facturacion'),
(4,'Auxiliar','Consulta de informacion, sin capacidad de modificar');

INSERT INTO modulo (id_modulo, clave, nombre, icono, orden) VALUES
(1,'inicio','Inicio','bi-house',1),
(2,'paciente','Paciente','bi-person-vcard',2),
(3,'cajas','Cajas','bi-cash-coin',3),
(4,'consultas','Consultas','bi-clipboard2-pulse',4),
(5,'reportes','Reportes','bi-graph-up',5),
(6,'configuracion','Configuracion','bi-gear',6),
(7,'facturacion','Facturacion','bi-receipt',7);

INSERT INTO permiso (id_modulo, clave, nombre) VALUES
(2,'paciente.ver','Ver pacientes'),
(2,'paciente.crear','Registrar paciente'),
(2,'paciente.editar','Modificar paciente'),
(2,'paciente.baja','Dar de baja paciente'),
(3,'cajas.ver','Ver caja'),
(3,'cajas.cobrar','Registrar cobros'),
(3,'cajas.egreso','Registrar egresos'),
(3,'cajas.corte','Abrir y cerrar caja'),
(3,'cajas.cancelar','Cancelar pagos'),
(3,'cajas.descuento','Autorizar descuentos'),
(3,'cajas.cargo','Registrar cargos a pacientes'),
(3,'cajas.estado_cuenta','Ver estado de cuenta de pacientes'),
(4,'consultas.ver','Ver consultas'),
(4,'consultas.crear','Registrar consulta'),
(4,'consultas.agenda','Administrar agenda'),
(4,'consultas.plan','Planes de tratamiento'),
(4,'consultas.odontograma','Odontograma'),
-- Sin este permiso el usuario solo ve en la lista las consultas que el
-- atendio. Recepcion y administracion si necesitan verlas todas.
(4,'consultas.todas','Ver las consultas de todos los dentistas'),
(5,'reportes.ver','Ver reportes'),
(5,'reportes.exportar','Exportar reportes'),
(6,'config.usuarios','Administrar usuarios'),
(6,'config.catalogos','Administrar catalogos'),
(6,'config.tarifas','Administrar tarifas'),
(6,'config.bitacora','Consultar bitacora'),
(6,'config.colonias','Administrar colonias'),
(6,'config.ocupaciones','Administrar ocupaciones'),
(6,'config.consultorio','Datos del consultorio'),
(6,'config.procedimientos','Administrar procedimientos dentales'),
(2,'paciente.historia','Capturar historia clinica'),
(7,'factura.ver','Ver facturas'),
(7,'factura.emitir','Emitir factura'),
(7,'factura.cancelar','Cancelar factura');

-- Administrador: todos los permisos
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT 1, id_permiso FROM permiso;
-- Dentista
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT 2, id_permiso FROM permiso
WHERE clave IN ('paciente.ver','paciente.crear','paciente.editar',
                'paciente.historia',
                'consultas.ver','consultas.crear','consultas.agenda',
                'consultas.plan','consultas.odontograma','reportes.ver',
                'config.procedimientos',
                'cajas.ver','cajas.cobrar','cajas.cargo','cajas.descuento',
                'cajas.estado_cuenta');
-- Recepcion
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT 3, id_permiso FROM permiso
WHERE clave IN ('paciente.ver','paciente.crear','paciente.editar',
                'cajas.ver','cajas.cobrar','cajas.corte','cajas.egreso',
                'cajas.cargo','cajas.estado_cuenta',
                'consultas.ver','consultas.agenda','consultas.todas',
                'factura.ver','factura.emitir','reportes.ver',
                'config.colonias','config.ocupaciones','paciente.historia');
-- Auxiliar
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT 4, id_permiso FROM permiso
WHERE clave IN ('paciente.ver','consultas.ver','cajas.ver','reportes.ver');

-- Usuario inicial.  Usuario: admin   Contrasena: Admin123!
-- CAMBIAR EN EL PRIMER ACCESO (requiere_cambio_pass = 1)
INSERT INTO usuario (id_usuario, id_rol, nombre_usuario, nombre_completo,
                     contrasena, correo, es_dentista, requiere_cambio_pass)
VALUES (1, 1, 'admin', 'Administrador del sistema',
        '$2y$10$pFoUAbzNEdEVA1C1Hk7glOUyjwgv1fsd.saymN8ohPcmOsVGdb4.K',
        'admin@consultorio.local', 0, 1);

-- ---------------------------------------------------------------------
-- Agenda
-- ---------------------------------------------------------------------
-- Pendiente es el estado con el que nacen todas las citas: se agendo,
-- pero todavia no se ha hablado con el paciente para confirmarla.
INSERT INTO estado_cita (id_estado_cita, nombre_estado, color) VALUES
(1,'Pendiente','#D98B1F'),
(2,'Confirmada','#2E86C1'),
(3,'En sala','#F39C12'),
(4,'Atendida','#27AE60'),
(5,'Cancelada','#7F8C8D'),
(6,'No asistio','#C0392B'),
(7,'Reagendada','#8E44AD');

INSERT INTO consultorio_sala (id_sala, nombre_sala) VALUES
(1,'Consultorio 1'), (2,'Consultorio 2');

-- ---------------------------------------------------------------------
-- Odontograma
-- ---------------------------------------------------------------------
INSERT INTO diente (id_diente, nombre_diente, cuadrante, es_temporal) VALUES
(11, 'Incisivo central sup. derecho', 1, 0),
(12, 'Incisivo lateral sup. derecho', 1, 0),
(13, 'Canino sup. derecho', 1, 0),
(14, 'Primer premolar sup. derecho', 1, 0),
(15, 'Segundo premolar sup. derecho', 1, 0),
(16, 'Primer molar sup. derecho', 1, 0),
(17, 'Segundo molar sup. derecho', 1, 0),
(18, 'Tercer molar sup. derecho', 1, 0),
(21, 'Incisivo central sup. izquierdo', 2, 0),
(22, 'Incisivo lateral sup. izquierdo', 2, 0),
(23, 'Canino sup. izquierdo', 2, 0),
(24, 'Primer premolar sup. izquierdo', 2, 0),
(25, 'Segundo premolar sup. izquierdo', 2, 0),
(26, 'Primer molar sup. izquierdo', 2, 0),
(27, 'Segundo molar sup. izquierdo', 2, 0),
(28, 'Tercer molar sup. izquierdo', 2, 0),
(31, 'Incisivo central inf. izquierdo', 3, 0),
(32, 'Incisivo lateral inf. izquierdo', 3, 0),
(33, 'Canino inf. izquierdo', 3, 0),
(34, 'Primer premolar inf. izquierdo', 3, 0),
(35, 'Segundo premolar inf. izquierdo', 3, 0),
(36, 'Primer molar inf. izquierdo', 3, 0),
(37, 'Segundo molar inf. izquierdo', 3, 0),
(38, 'Tercer molar inf. izquierdo', 3, 0),
(41, 'Incisivo central inf. derecho', 4, 0),
(42, 'Incisivo lateral inf. derecho', 4, 0),
(43, 'Canino inf. derecho', 4, 0),
(44, 'Primer premolar inf. derecho', 4, 0),
(45, 'Segundo premolar inf. derecho', 4, 0),
(46, 'Primer molar inf. derecho', 4, 0),
(47, 'Segundo molar inf. derecho', 4, 0),
(48, 'Tercer molar inf. derecho', 4, 0),
(51, 'Incisivo central temporal sup. derecho', 5, 1),
(52, 'Incisivo lateral temporal sup. derecho', 5, 1),
(53, 'Canino temporal sup. derecho', 5, 1),
(54, 'Primer molar temporal sup. derecho', 5, 1),
(55, 'Segundo molar temporal sup. derecho', 5, 1),
(61, 'Incisivo central temporal sup. izquierdo', 6, 1),
(62, 'Incisivo lateral temporal sup. izquierdo', 6, 1),
(63, 'Canino temporal sup. izquierdo', 6, 1),
(64, 'Primer molar temporal sup. izquierdo', 6, 1),
(65, 'Segundo molar temporal sup. izquierdo', 6, 1),
(71, 'Incisivo central temporal inf. izquierdo', 7, 1),
(72, 'Incisivo lateral temporal inf. izquierdo', 7, 1),
(73, 'Canino temporal inf. izquierdo', 7, 1),
(74, 'Primer molar temporal inf. izquierdo', 7, 1),
(75, 'Segundo molar temporal inf. izquierdo', 7, 1),
(81, 'Incisivo central temporal inf. derecho', 8, 1),
(82, 'Incisivo lateral temporal inf. derecho', 8, 1),
(83, 'Canino temporal inf. derecho', 8, 1),
(84, 'Primer molar temporal inf. derecho', 8, 1),
(85, 'Segundo molar temporal inf. derecho', 8, 1);

INSERT INTO condicion_dental (id_condicion, nombre_condicion, color, es_tratamiento) VALUES
(1,'Sano','#BDC3C7',0),
(2,'Caries','#E74C3C',0),
(3,'Fractura','#E67E22',0),
(4,'Ausente','#34495E',0),
(5,'Movilidad','#D35400',0),
(6,'Resina','#3498DB',1),
(7,'Amalgama','#5D6D7E',1),
(8,'Corona','#F1C40F',1),
(9,'Endodoncia','#2980B9',1),
(10,'Implante','#16A085',1),
(11,'Protesis fija','#9B59B6',1),
(12,'Sellador','#1ABC9C',1),
(13,'Extraccion indicada','#C0392B',0);

-- ---------------------------------------------------------------------
-- Tratamientos (tarifario base - ajustar con el cliente)
-- ---------------------------------------------------------------------
INSERT INTO categoria_tratamiento (id_categoria, nombre_categoria) VALUES
(1,'Diagnostico y prevencion'),
(2,'Operatoria dental'),
(3,'Endodoncia'),
(4,'Periodoncia'),
(5,'Cirugia'),
(6,'Protesis'),
(7,'Ortodoncia'),
(8,'Estetica dental'),
(9,'Odontopediatria');

INSERT INTO tratamiento (id_categoria, clave, nombre_tratamiento, precio_lista, duracion_min, aplica_por_diente) VALUES
(1,'DX-01','Consulta y diagnostico',            300.00, 30, 0),
(1,'DX-02','Radiografia periapical',            150.00, 15, 1),
(1,'DX-03','Radiografia panoramica',            600.00, 20, 0),
(1,'PRE-01','Limpieza dental (profilaxis)',     700.00, 45, 0),
(1,'PRE-02','Aplicacion de fluor',              350.00, 20, 0),
(1,'PRE-03','Sellador de fosetas y fisuras',    400.00, 20, 1),
(2,'OP-01','Resina simple',                     750.00, 40, 1),
(2,'OP-02','Resina compuesta',                  950.00, 60, 1),
(2,'OP-03','Amalgama',                          600.00, 40, 1),
(2,'OP-04','Incrustacion',                     2500.00, 60, 1),
(3,'END-01','Endodoncia unirradicular',        2200.00, 90, 1),
(3,'END-02','Endodoncia birradicular',         2800.00,120, 1),
(3,'END-03','Endodoncia multirradicular',      3500.00,150, 1),
(3,'END-04','Pulpotomia',                      1200.00, 60, 1),
(4,'PER-01','Curetaje por cuadrante',          1200.00, 60, 0),
(4,'PER-02','Alargamiento de corona',          2000.00, 60, 1),
(5,'CIR-01','Extraccion simple',                800.00, 30, 1),
(5,'CIR-02','Extraccion quirurgica',           1800.00, 60, 1),
(5,'CIR-03','Extraccion de tercer molar',      2800.00, 90, 1),
(6,'PRO-01','Corona de porcelana',             4500.00, 90, 1),
(6,'PRO-02','Corona de zirconia',              6500.00, 90, 1),
(6,'PRO-03','Protesis removible parcial',      7500.00,120, 0),
(6,'PRO-04','Protesis total',                 12000.00,120, 0),
(7,'ORT-01','Colocacion de brackets metalicos',12000.00,120, 0),
(7,'ORT-02','Ajuste mensual de ortodoncia',     700.00, 30, 0),
(7,'ORT-03','Retiro de brackets y retenedor',  3500.00, 90, 0),
(8,'EST-01','Blanqueamiento en consultorio',   3500.00, 90, 0),
(8,'EST-02','Carilla de resina',               2200.00, 60, 1),
(9,'PED-01','Consulta odontopediatrica',        350.00, 30, 0),
(9,'PED-02','Corona de acero cromo',           1500.00, 60, 1);

-- ---------------------------------------------------------------------
-- Procedimientos dentales
-- Los mas usados en odontopediatria. Cada uno toma su precio del
-- tratamiento correspondiente del tarifario, buscado por clave para no
-- depender de los ids.
-- ---------------------------------------------------------------------
INSERT INTO procedimiento
  (id_categoria, clave, nombre_procedimiento, id_tratamiento,
   costo_sugerido, duracion_min, aplica_por_diente, requiere_anestesia, orden) VALUES
(1,'P-REV','REVISION Y DIAGNOSTICO',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='DX-01'),  300.00, 30, 0, 0, 1),
(1,'P-PROF','PROFILAXIS (LIMPIEZA DENTAL)',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='PRE-01'), 700.00, 45, 0, 0, 2),
(1,'P-FLUOR','APLICACION DE FLUOR',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='PRE-02'), 350.00, 20, 0, 0, 3),
(1,'P-SELL','SELLADOR DE FOSETAS Y FISURAS',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='PRE-03'), 400.00, 20, 1, 0, 4),
(1,'P-RXP','RADIOGRAFIA PERIAPICAL',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='DX-02'),  150.00, 15, 1, 0, 5),
(1,'P-RXPAN','RADIOGRAFIA PANORAMICA',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='DX-03'),  600.00, 20, 0, 0, 6),

(2,'P-RES','RESINA',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='OP-01'),  750.00, 40, 1, 1, 10),
(2,'P-RESC','RESINA COMPUESTA (VARIAS CARAS)',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='OP-02'),  950.00, 60, 1, 1, 11),
(2,'P-AMAL','AMALGAMA',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='OP-03'),  600.00, 40, 1, 1, 12),
(2,'P-IONO','IONOMERO DE VIDRIO',
   NULL, 550.00, 30, 1, 0, 13),
(2,'P-CURA','CURACION TEMPORAL',
   NULL, 350.00, 20, 1, 0, 14),

(3,'P-PULPOT','PULPOTOMIA',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='END-04'), 1200.00, 60, 1, 1, 20),
(3,'P-PULPEC','PULPECTOMIA',
   NULL, 1800.00, 90, 1, 1, 21),
(3,'P-END1','ENDODONCIA UNIRRADICULAR',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='END-01'), 2200.00, 90, 1, 1, 22),
(3,'P-REC','RECUBRIMIENTO PULPAR',
   NULL, 700.00, 30, 1, 1, 23),

(4,'P-PROFPER','CURETAJE POR CUADRANTE',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='PER-01'), 1200.00, 60, 0, 1, 30),

(5,'P-EXTS','EXTRACCION SIMPLE',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='CIR-01'), 800.00, 30, 1, 1, 40),
(5,'P-EXTQ','EXTRACCION QUIRURGICA',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='CIR-02'), 1800.00, 60, 1, 1, 41),
(5,'P-FREN','FRENECTOMIA',
   NULL, 2500.00, 45, 0, 1, 42),

(6,'P-CORACE','CORONA DE ACERO CROMO',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='PED-02'), 1500.00, 60, 1, 1, 50),
(6,'P-CORZIR','CORONA ESTETICA',
   NULL, 2200.00, 60, 1, 1, 51),
(6,'P-MANT','MANTENEDOR DE ESPACIO',
   NULL, 2800.00, 60, 0, 0, 52),

(7,'P-ORTINI','INICIO DE ORTOPEDIA MAXILAR',
   NULL, 12000.00, 120, 0, 0, 60),
(7,'P-ORTAJU','AJUSTE DE APARATO',
   (SELECT id_tratamiento FROM tratamiento WHERE clave='ORT-02'), 700.00, 30, 0, 0, 61),
(7,'P-ORTRET','RETIRO DE APARATO',
   NULL, 900.00, 45, 0, 0, 62),

(9,'P-ADAPT','CITA DE ADAPTACION',
   NULL, 0.00, 30, 0, 0, 70),
(9,'P-CONTROL','CITA DE CONTROL',
   NULL, 0.00, 20, 0, 0, 71),
(9,'P-URGENCIA','ATENCION DE URGENCIA',
   NULL, 500.00, 40, 0, 0, 72);

INSERT INTO estado_plan (id_estado_plan, nombre_estado) VALUES
(1,'Propuesto'), (2,'Aceptado'), (3,'En proceso'), (4,'Terminado'), (5,'Cancelado');

-- ---------------------------------------------------------------------
-- Caja
-- ---------------------------------------------------------------------
-- Varias cajas: la de recepcion y la de cada doctora, porque tambien
-- cobran por transferencia o en efectivo desde el consultorio. Cada una
-- abre y cierra su turno por separado, y su corte es el suyo.
INSERT INTO caja (id_caja, nombre_caja) VALUES
(1,'Recepcion'),
(2,'Consultorio 1'),
(3,'Consultorio 2');

INSERT INTO forma_pago (id_forma_pago, nombre_forma, clave_sat, afecta_efectivo) VALUES
(1,'Efectivo','01',1),
(2,'Transferencia electronica','03',0),
(3,'Tarjeta de credito','04',0),
(4,'Tarjeta de debito','28',0),
(5,'Cheque nominativo','02',0),
(6,'Por definir','99',0);

INSERT INTO concepto_movimiento (id_concepto, nombre_concepto, naturaleza) VALUES
(1,'Cobro a paciente','I'),
(2,'Anticipo de tratamiento','I'),
(3,'Fondo de caja','I'),
(4,'Otros ingresos','I'),
(5,'Compra de material dental','E'),
(6,'Pago a laboratorio','E'),
(7,'Servicios (luz, agua, internet)','E'),
(8,'Nomina','E'),
(9,'Retiro de efectivo','E'),
(10,'Devolucion a paciente','E'),
(11,'Otros egresos','E'),
-- Contraparte de una cancelacion: no se borra el ingreso, se registra
-- su reverso para que el corte cuadre y quede el rastro de los dos.
(12,'Cancelacion de cobro','E');

-- ---------------------------------------------------------------------
-- Recordatorios de cita
--
-- Cuantos avisos y con cuanta anticipacion. Se puede cambiar desde
-- Configuracion: son las que el consultorio pidio para empezar.
-- ---------------------------------------------------------------------
INSERT INTO recordatorio_regla (id_regla, nombre, horas_antes, orden) VALUES
(1,'Dos dias antes', 48, 1),
(2,'El dia anterior', 24, 2),
(3,'Dos horas antes', 2, 3);

-- ---------------------------------------------------------------------
-- Facturacion (subconjunto de catalogos SAT usados en el giro)
-- ---------------------------------------------------------------------
INSERT INTO regimen_fiscal (id_regimen, descripcion) VALUES
(601,'General de Ley Personas Morales'),
(603,'Personas Morales con Fines no Lucrativos'),
(605,'Sueldos y Salarios e Ingresos Asimilados a Salarios'),
(606,'Arrendamiento'),
(608,'Demas ingresos'),
(612,'Personas Fisicas con Actividades Empresariales y Profesionales'),
(614,'Ingresos por intereses'),
(616,'Sin obligaciones fiscales'),
(621,'Incorporacion Fiscal'),
(626,'Regimen Simplificado de Confianza');

INSERT INTO uso_cfdi (id_uso_cfdi, descripcion) VALUES
('G01','Adquisicion de mercancias'),
('G03','Gastos en general'),
('D01','Honorarios medicos, dentales y gastos hospitalarios'),
('D02','Gastos medicos por incapacidad o discapacidad'),
('S01','Sin efectos fiscales'),
('CP01','Pagos');

-- ---------------------------------------------------------------------
-- Parametros del sistema
-- ---------------------------------------------------------------------
-- OJO: aqui no va la version del sistema. Esa vive en config/config.php
-- (APP_VERSION) porque pertenece al codigo, no a los datos.
INSERT INTO parametro (clave, valor, descripcion) VALUES
('nombre_sistema','Nanai','Nombre del sistema. Nanai es sonrisa en wixarika.'),
('estado_default','18','Estado preseleccionado en formularios de domicilio'),
('prefijo_expediente','EXP','Prefijo del folio de expediente'),
('pacientes_lista_inicio','25','Cuantos pacientes recientes se listan'),
('minutos_sesion','60','Minutos de inactividad antes de cerrar sesion'),
('max_intentos_login','5','Intentos fallidos antes de bloquear la cuenta'),
('minutos_bloqueo','15','Minutos de bloqueo tras exceder los intentos'),
('iva_porcentaje','16','Porcentaje de IVA para facturacion'),
('moneda','MXN','Moneda del sistema');

INSERT INTO consultorio (id_consultorio, nombre_comercial, razon_social, telefono, correo)
VALUES (1,'Consultorio Dental','Por definir','311 000 0000','contacto@consultorio.local');
