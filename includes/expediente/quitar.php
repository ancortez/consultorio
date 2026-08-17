<?php
/**
 * Quita el archivo de un documento y lo regresa a pendiente.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idDocumento = paramInt('id_documento');
if (!$idDocumento) {
    Respuesta::error('Falta el documento.');
}

try {
    Documentos::quitarArchivo($idDocumento);
} catch (Throwable $e) {
    error_log('[EXPEDIENTE/QUITAR] ' . $e->getMessage());
    Respuesta::error('No se pudo quitar el archivo.', 500);
}

Respuesta::ok(null, 'Archivo eliminado. El documento vuelve a estar pendiente.');
