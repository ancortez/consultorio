<?php
/**
 * Crea un respaldo de la base de datos, ahora.
 *
 * Solo la base: los archivos del expediente pesan gigabytes y viven en
 * el volumen de MinIO, al que este contenedor no tiene acceso —ni debe
 * tenerlo—. Los sigue respaldando respaldar.sh los domingos.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.respaldos');

try {
    $hecho = Respaldos::crearDeLaBase();
} catch (Throwable $e) {
    error_log('[RESPALDO/CREAR] ' . $e->getMessage());
    Respuesta::error($e->getMessage());
}

Respuesta::ok(
    [
        'nombre'    => $hecho['nombre'],
        'respaldos' => Respaldos::listar(),
        'resumen'   => Respaldos::resumen(),
    ],
    'Respaldo creado: ' . $hecho['nombre']
    . ' (' . tamanoLegible($hecho['bytes']) . ').'
);
