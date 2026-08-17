<?php
/**
 * Entrega la fotografia recortada de una persona.
 *
 * Separado de archivo.php porque esta se pide en cada listado y en cada
 * tarjeta: conviene que sea barata y que el navegador la pueda guardar
 * un rato en cache, cosa que los documentos del expediente no deben.
 *
 * Uso:  /avatar.php?p=ID_PERSONA
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

Auth::exigirLogin();

$idPersona = (int) ($_GET['p'] ?? 0);
if ($idPersona <= 0) {
    http_response_code(400);
    exit;
}

$foto = Conexion::valor(
    'SELECT foto FROM persona WHERE id_persona = :p', [':p' => $idPersona]
);

if (empty($foto)) {
    http_response_code(404);
    exit;
}

try {
    $contenido = Almacen::obtener($foto);
} catch (Throwable $e) {
    error_log('[AVATAR] ' . $e->getMessage());
    http_response_code(502);
    exit;
}

if ($contenido === null) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . strlen($contenido));
// Cache corto: si cambian la foto, se ve la nueva en menos de un minuto.
// El front ademas le agrega ?v= para forzar la actualizacion inmediata.
header('Cache-Control: private, max-age=60');
header('X-Content-Type-Options: nosniff');

echo $contenido;
