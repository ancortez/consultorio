-- =====================================================================
--  Permiso nuevo: Configuracion > Respaldos
-- =====================================================================
--  Crear, descargar y restaurar respaldos desde el propio sistema, sin
--  entrar al servidor por ssh. Lo pidio Angel, y el motivo de fondo es
--  que un respaldo que vive en el mismo servidor no protege del caso
--  mas probable —que el servidor se pierda— asi que bajarlo tiene que
--  ser facil: lo que cuesta trabajo no se hace.
--
--  SOLO el administrador. No es una precaucion de mas:
--
--    - un respaldo de la base trae el expediente clinico completo de
--      todos los pacientes, en texto plano, en un solo archivo. Es el
--      objeto mas sensible del sistema entero.
--
--    - restaurar reemplaza TODOS los expedientes por los de otra fecha.
--      Picado por error, el trabajo de semanas desaparece de la
--      pantalla sin que nada se vea roto.
--
--  Por eso NO se agrega a ningun otro rol, ni siquiera al de la
--  doctora: ella es dentista en el sistema. Si algun dia hace falta,
--  se agrega a mano con una migracion nueva y queda escrito por que.
--
--  El permiso se asigna al rol 1 con un INSERT ... SELECT que lo busca
--  por clave, no por id: los ids de permiso dependen del orden en que
--  se cargo el catalogo y no son los mismos en dos instalaciones.
-- =====================================================================

INSERT INTO permiso (id_modulo, clave, nombre)
SELECT 6, 'config.respaldos', 'Respaldos del sistema'
 WHERE NOT EXISTS (
     SELECT 1 FROM permiso WHERE clave = 'config.respaldos');

INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT 1, p.id_permiso
  FROM permiso p
 WHERE p.clave = 'config.respaldos'
   AND NOT EXISTS (
       SELECT 1 FROM rol_permiso rp
        WHERE rp.id_rol = 1 AND rp.id_permiso = p.id_permiso);
