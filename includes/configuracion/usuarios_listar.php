<?php
/**
 * Lista de usuarios del sistema con su rol y estado de acceso.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.usuarios');

$q       = mayus((string) param('q', '')) ?? '';
$estatus = param('estatus', '');

$where  = [];
$params = [];

if ($estatus === '0' || $estatus === '1') {
    $where[]            = 'u.estatus = :estatus';
    $params[':estatus'] = (int) $estatus;
}
if ($q !== '') {
    $where[] = '(UPPER(u.nombre_completo) LIKE :q1 OR UPPER(u.nombre_usuario) LIKE :q2)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
}

$sql = 'SELECT u.id_usuario, u.nombre_usuario, u.nombre_completo, u.correo,
               u.cedula_prof, u.es_dentista, u.estatus, u.ultimo_acceso,
               u.intentos_fallidos, u.bloqueado_hasta, u.requiere_cambio_pass,
               u.id_rol, r.nombre_rol,
               (u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW()) AS bloqueado
          FROM usuario u
          JOIN rol r ON r.id_rol = u.id_rol';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY u.estatus DESC, u.nombre_completo';

Respuesta::ok(Conexion::todos($sql, $params));
