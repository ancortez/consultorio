<?php
/**
 * Roles con su cuenta de usuarios y de permisos.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.usuarios');

Respuesta::ok(Conexion::todos(
    'SELECT r.id_rol, r.nombre_rol, r.descripcion, r.estatus,
            (SELECT COUNT(*) FROM usuario u
              WHERE u.id_rol = r.id_rol AND u.estatus = 1) AS usuarios,
            (SELECT COUNT(*) FROM rol_permiso rp
              WHERE rp.id_rol = r.id_rol) AS permisos
       FROM rol r
      ORDER BY r.id_rol'
));
