<?php
require_once __DIR__ . '/../_endpoint.php';
endpoint();

$idMunicipio = paramInt('id_municipio');
if (!$idMunicipio) {
    Respuesta::ok([]);
}

Respuesta::ok(Conexion::todos(
    'SELECT c.id_colonia, c.nombre_colonia, c.codigo_postal,
            c.id_tipo_asenta, ta.nombre_tipo_asenta AS tipo_asenta
       FROM colonia c
       LEFT JOIN tipo_asenta ta ON ta.id_tipo_asenta = c.id_tipo_asenta
      WHERE c.id_municipio = :m
      ORDER BY c.nombre_colonia',
    [':m' => $idMunicipio]
));
