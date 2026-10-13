-- =====================================================================
--  Aceptar las fotos de iPhone (.heic)
-- =====================================================================
--  Desde iOS 11 la camara guarda en HEIC. Al subir desde el navegador
--  del telefono, iOS suele convertir a JPEG solo y esto no se nota;
--  pero una foto que llega por AirDrop, exportada desde Fotos en la
--  Mac, o arrastrada desde una carpeta, llega en HEIC tal cual. Y
--  entonces el sistema la rechazaba sin explicar por que.
--
--  El archivo NO se guarda en HEIC: Imagen::optimizar lo pasa a JPEG al
--  subirlo, con heif-convert. Esto solo abre la puerta de entrada.
--
--  Igual que con BMP en la migracion 0001: se agrega donde tiene
--  sentido —fotos y radiografias— y no en los tipos que esperan un
--  papel escaneado.
-- =====================================================================

UPDATE tipo_documento
   SET formatos = CONCAT(formatos, ',heic')
 WHERE clave IN ('FOTO_PACIENTE', 'RADIOGRAFIA', 'FOTOGRAFIA', 'OTRO_CONSULTA')
   AND formatos NOT LIKE '%heic%';
