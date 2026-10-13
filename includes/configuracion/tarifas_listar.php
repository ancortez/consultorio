<?php
/**
 * El tarifario: precios de lista del consultorio.
 *
 * Trae ademas "usos": cuantos procedimientos cuelgan de cada precio.
 * Es lo que permite avisar, al desactivar uno, que hay procedimientos
 * que se quedarian sin precio heredado.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.tarifas');

$q           = mayus((string) param('q', '')) ?? '';
$idCategoria = paramInt('id_categoria');
$estatus     = param('estatus', '1');

$where  = [];
$params = [];

if ($estatus === '0' || $estatus === '1') {
    $where[]            = 't.estatus = :estatus';
    $params[':estatus'] = (int) $estatus;
}
if ($idCategoria) {
    $where[]        = 't.id_categoria = :cat';
    $params[':cat'] = $idCategoria;
}
if ($q !== '') {
    $where[] = '(UPPER(t.nombre_tratamiento) LIKE :q1 OR UPPER(t.clave) LIKE :q2)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
}

$sql = 'SELECT t.*, ct.nombre_categoria,
               (SELECT COUNT(*) FROM procedimiento p
                 WHERE p.id_tratamiento = t.id_tratamiento
                   AND p.estatus = 1) AS usos
          FROM tratamiento t
          LEFT JOIN categoria_tratamiento ct ON ct.id_categoria = t.id_categoria';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY ct.nombre_categoria, t.nombre_tratamiento';

Respuesta::ok(Conexion::todos($sql, $params));
