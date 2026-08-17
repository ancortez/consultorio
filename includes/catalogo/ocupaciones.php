<?php
/**
 * Lista de ocupaciones para el modal selector.
 * Acepta un filtro de texto; sin filtro devuelve todo el catalogo, que
 * son unas 80 opciones y caben de sobra en un modal con scroll.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint();

$q = mayus((string) param('q', '')) ?? '';

$sql    = 'SELECT id_ocupacion, clave, nombre_ocupacion
             FROM ocupacion
            WHERE estatus = 1';
$params = [];

if ($q !== '') {
    $sql .= ' AND nombre_ocupacion LIKE :q';
    $params[':q'] = '%' . $q . '%';
}

// NINGUNA y ESTUDIANTE primero: son las mas usadas con diferencia.
$sql .= " ORDER BY CASE WHEN clave IS NULL THEN 1 ELSE 0 END,
                   nombre_ocupacion
          LIMIT 300";

Respuesta::ok(Conexion::todos($sql, $params));
