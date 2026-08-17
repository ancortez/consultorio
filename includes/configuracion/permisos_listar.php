<?php
/**
 * Matriz de permisos de un rol: todos los permisos del sistema con una
 * marca de si el rol los tiene.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.usuarios');

$idRol = paramInt('id_rol');
if (!$idRol) { Respuesta::error('Falta el rol.'); }

$rol = Conexion::uno('SELECT * FROM rol WHERE id_rol = :r', [':r' => $idRol]);
if ($rol === null) { Respuesta::error('El rol no existe.', 404); }

$permisos = Conexion::todos(
    'SELECT p.id_permiso, p.clave, p.nombre,
            m.id_modulo, m.nombre AS modulo, m.icono,
            (rp.id_rol IS NOT NULL) AS asignado
       FROM permiso p
       JOIN modulo m ON m.id_modulo = p.id_modulo
       LEFT JOIN rol_permiso rp
              ON rp.id_permiso = p.id_permiso AND rp.id_rol = :r
      ORDER BY m.orden, p.id_permiso',
    [':r' => $idRol]
);

// Se agrupan por modulo para pintarlos por bloques.
$porModulo = [];
foreach ($permisos as $p) {
    $porModulo[$p['modulo']]['icono']       = $p['icono'];
    $porModulo[$p['modulo']]['permisos'][]  = $p;
}

Respuesta::ok([
    'rol'        => $rol,
    'es_admin'   => $idRol === 1,
    'por_modulo' => $porModulo,
]);
