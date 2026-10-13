-- =====================================================================
--  0007_nanai.sql
--
--  El sistema pasa a llamarse Nanai: sonrisa en wixarika.
--
--  El nombre vive en la tabla parametro, no en el codigo, porque la
--  barra y el pie lo leen de ahi. Si solo se cambiara APP_NOMBRE en
--  config.php, la pantalla seguiria diciendo lo de antes: el parametro
--  gana sobre la constante, que es solo el respaldo para cuando la
--  tabla no responde.
--
--  Se cambia unicamente si todavia tiene el valor viejo. Si alguien ya
--  lo personalizo desde Configuracion, se respeta: un nombre que el
--  usuario eligio no se pisa en una actualizacion.
-- =====================================================================

UPDATE parametro
   SET valor = 'Nanai',
       descripcion = 'Nombre del sistema. Nanai es sonrisa en wixarika.'
 WHERE clave = 'nombre_sistema'
   AND valor IN ('Sistema Consultorio Dental', 'Consultorio Dental');
