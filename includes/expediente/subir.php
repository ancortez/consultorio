<?php
/**
 * Sube el archivo escaneado de un documento pendiente.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idDocumento = paramInt('id_documento');
if (!$idDocumento) {
    Respuesta::error('Falta el documento.');
}

if (!isset($_FILES['archivo'])) {
    Respuesta::validacion(['archivo' => 'Selecciona el archivo escaneado.']);
}

try {
    $r = Documentos::digitalizar($idDocumento, $_FILES['archivo']);
} catch (InvalidArgumentException $e) {
    // Error de validacion: es culpa del archivo, no del sistema.
    Respuesta::validacion(['archivo' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[EXPEDIENTE/SUBIR] ' . $e->getMessage());
    Respuesta::error(
        'No se pudo guardar el archivo. ' . $e->getMessage(), 500);
}

Respuesta::ok($r, 'Documento digitalizado.');
