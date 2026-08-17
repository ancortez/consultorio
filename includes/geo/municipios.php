<?php
require_once __DIR__ . '/../_endpoint.php';
endpoint();

$idEstado = paramInt('id_estado');
if (!$idEstado) {
    Respuesta::ok([]);
}

Respuesta::ok(Conexion::todos(
    'SELECT id_municipio, nombre_municipio
       FROM municipio
      WHERE id_estado = :e
      ORDER BY nombre_municipio',
    [':e' => $idEstado]
));
