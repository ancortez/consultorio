<?php
/**
 * Alta rapida de una ocupacion que no esta en el catalogo.
 * Se permite desde el mismo modal selector para que quien captura no
 * tenga que salirse a Configuracion y perder lo que llevaba escrito.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$nombre = limpiarEspacios(paramMayus('nombre_ocupacion'));

if ($nombre === null || mb_strlen($nombre) < 3) {
    Respuesta::validacion(['nombre_ocupacion' => 'Captura al menos 3 letras.']);
}

$existente = Conexion::uno(
    'SELECT id_ocupacion, nombre_ocupacion FROM ocupacion
      WHERE nombre_ocupacion = :n LIMIT 1',
    [':n' => $nombre]
);

if ($existente !== null) {
    // Ya existia: se devuelve esa en lugar de duplicarla.
    Respuesta::ok($existente, 'Esa ocupacion ya estaba registrada.');
}

Conexion::consulta(
    'INSERT INTO ocupacion (clave, nombre_ocupacion) VALUES (NULL, :n)',
    [':n' => $nombre]
);
$id = Conexion::ultimoId();

Bitacora::registrar('configuracion', 'ALTA', 'ocupacion', (string) $id,
    ['nombre' => $nombre]);

Respuesta::ok(
    ['id_ocupacion' => $id, 'nombre_ocupacion' => $nombre],
    'Ocupacion agregada.'
);
