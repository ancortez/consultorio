<?php
/**
 * Catalogo de procedimientos dentales.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.procedimientos');

$q          = mayus((string) param('q', '')) ?? '';
$idCategoria = paramInt('id_categoria');
$estatus    = param('estatus', '1');

$where  = [];
$params = [];

if ($estatus === '0' || $estatus === '1') {
    $where[]            = 'p.estatus = :estatus';
    $params[':estatus'] = (int) $estatus;
}
if ($idCategoria) {
    $where[]         = 'p.id_categoria = :cat';
    $params[':cat']  = $idCategoria;
}
if ($q !== '') {
    $where[] = '(UPPER(p.nombre_procedimiento) LIKE :q1 OR UPPER(p.clave) LIKE :q2)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
}

$sql = 'SELECT p.*, ct.nombre_categoria, t.nombre_tratamiento,
               (SELECT COUNT(*) FROM plan_tratamiento_detalle ptd
                 WHERE ptd.id_procedimiento = p.id_procedimiento) AS usos
          FROM procedimiento p
          LEFT JOIN categoria_tratamiento ct ON ct.id_categoria = p.id_categoria
          LEFT JOIN tratamiento t ON t.id_tratamiento = p.id_tratamiento';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY ct.nombre_categoria, p.orden, p.nombre_procedimiento';

Respuesta::ok(Conexion::todos($sql, $params));
