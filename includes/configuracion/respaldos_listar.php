<?php
/**
 * Los respaldos que hay, con su resumen.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.respaldos');

Respuesta::ok([
    'respaldos' => Respaldos::listar(),
    'resumen'   => Respaldos::resumen(),
]);
