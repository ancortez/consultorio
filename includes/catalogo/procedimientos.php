<?php
/**
 * Procedimientos activos, para el selector del plan de tratamiento.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint();

$q = mayus((string) param('q', '')) ?? '';

$sql = 'SELECT p.id_procedimiento, p.clave, p.nombre_procedimiento,
               p.costo_sugerido, p.duracion_min, p.aplica_por_diente,
               p.requiere_anestesia, ct.nombre_categoria
          FROM procedimiento p
          LEFT JOIN categoria_tratamiento ct ON ct.id_categoria = p.id_categoria
         WHERE p.estatus = 1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (UPPER(p.nombre_procedimiento) LIKE :q1
                   OR UPPER(ct.nombre_categoria) LIKE :q2)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
}

$sql .= ' ORDER BY ct.nombre_categoria, p.orden, p.nombre_procedimiento LIMIT 200';

Respuesta::ok(Conexion::todos($sql, $params));
