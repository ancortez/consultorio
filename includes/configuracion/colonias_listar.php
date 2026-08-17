<?php
/**
 * Colonias de un municipio.
 * No devuelve nada si no viene municipio: son mas de 450 colonias solo
 * entre Tepic y Xalisco, y listarlas todas no le sirve a nadie.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.colonias');

$idMunicipio = paramInt('id_municipio');
if (!$idMunicipio) {
    Respuesta::ok([]);
}

$q      = mayus((string) param('q', '')) ?? '';
$where  = ['c.id_municipio = :m'];
$params = [':m' => $idMunicipio];

if ($q !== '') {
    $where[] = '(c.nombre_colonia LIKE :q1 OR c.codigo_postal LIKE :q2)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
}

Respuesta::ok(Conexion::todos(
    'SELECT c.id_colonia, c.nombre_colonia, c.codigo_postal,
            c.id_tipo_asenta, ta.nombre_tipo_asenta AS tipo_asenta,
            c.id_municipio, m.nombre_municipio, e.id_estado, e.nombre_estado,
            (SELECT COUNT(*) FROM domicilio d
              WHERE d.id_colonia = c.id_colonia) AS domicilios
       FROM colonia c
       JOIN municipio m ON m.id_municipio = c.id_municipio
       JOIN estado    e ON e.id_estado    = m.id_estado
       LEFT JOIN tipo_asenta ta ON ta.id_tipo_asenta = c.id_tipo_asenta
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY c.nombre_colonia',
    $params
));
