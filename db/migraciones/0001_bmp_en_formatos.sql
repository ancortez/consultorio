-- =====================================================================
--  0001_bmp_en_formatos.sql
--
--  Los sensores de rayos X del consultorio entregan BMP sin comprimir.
--  Hasta ahora el sistema los rechazaba y habia que convertirlos a mano
--  antes de subirlos, que es justo el tipo de paso que nadie hace a las
--  seis de la tarde con un paciente esperando.
--
--  Se acepta en los tres tipos donde entra una imagen clinica. No en la
--  credencial del tutor ni en el consentimiento firmado: ahi un BMP no
--  tiene razon de existir y aceptarlo solo abre la puerta a archivos de
--  30 MB por error.
--
--  El archivo NO se guarda en BMP: Imagen::optimizar lo convierte a
--  JPEG al subirlo. Esto solo abre la puerta de entrada.
-- =====================================================================

UPDATE tipo_documento
   SET formatos = 'pdf,jpg,jpeg,png,bmp'
 WHERE clave = 'RADIOGRAFIA'
   AND formatos NOT LIKE '%bmp%';

UPDATE tipo_documento
   SET formatos = 'jpg,jpeg,png,bmp'
 WHERE clave = 'FOTOGRAFIA'
   AND formatos NOT LIKE '%bmp%';

UPDATE tipo_documento
   SET formatos = 'pdf,jpg,jpeg,png,bmp,doc,docx'
 WHERE clave = 'OTRO_CONSULTA'
   AND formatos NOT LIKE '%bmp%';
