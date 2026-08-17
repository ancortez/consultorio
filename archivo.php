<?php
/**
 * Entrega un archivo del expediente al navegador.
 *
 * Los archivos viven en MinIO, que no esta expuesto a internet, y pasan
 * por aqui a proposito: este es el punto donde se comprueba la sesion y
 * el permiso. Servirlos directo desde MinIO dejaria un expediente
 * clinico accesible con solo conocer la URL.
 *
 * Uso:  /archivo.php?id=123        muestra el archivo
 *       /archivo.php?id=123&d=1    lo descarga
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

Auth::exigirLogin();
Auth::exigirPermiso('paciente.ver');

$idDocumento = (int) ($_GET['id'] ?? 0);
$descargar   = isset($_GET['d']);

if ($idDocumento <= 0) {
    http_response_code(400);
    exit('Falta el identificador del documento.');
}

$doc = Conexion::uno(
    'SELECT d.ruta_archivo, d.nombre_original, d.tipo_mime, d.hash_sha256,
            d.id_registro_paciente, td.nombre_documento
       FROM documento d
       JOIN tipo_documento td ON td.id_tipo_documento = d.id_tipo_documento
      WHERE d.id_documento = :id AND d.estatus = 1',
    [':id' => $idDocumento]
);

if ($doc === null || empty($doc['ruta_archivo'])) {
    http_response_code(404);
    exit('El archivo no existe o todavia no se ha digitalizado.');
}

try {
    $contenido = Almacen::obtener($doc['ruta_archivo']);
} catch (Throwable $e) {
    error_log('[ARCHIVO] ' . $e->getMessage());
    http_response_code(502);
    exit('No se pudo recuperar el archivo del almacenamiento.');
}

if ($contenido === null) {
    http_response_code(404);
    exit('El archivo ya no esta en el almacenamiento.');
}

// Si el contenido no coincide con la huella guardada, alguien cambio el
// archivo por fuera del sistema. Se entrega igual, pero queda anotado:
// en un expediente clinico eso no puede pasar en silencio.
if (!empty($doc['hash_sha256']) && hash('sha256', $contenido) !== $doc['hash_sha256']) {
    Bitacora::registrar('expediente', 'ALERTA', 'documento', (string) $idDocumento,
        ['motivo' => 'El contenido no coincide con la huella registrada']);
}

Bitacora::registrar('expediente', 'CONSULTA', 'documento', (string) $idDocumento);

$nombre = $doc['nombre_original'] ?: ($doc['nombre_documento'] . '.pdf');

header('Content-Type: ' . ($doc['tipo_mime'] ?: 'application/octet-stream'));
header('Content-Length: ' . strlen($contenido));
header('Content-Disposition: '
    . ($descargar ? 'attachment' : 'inline')
    . '; filename="' . rawurlencode($nombre) . '"');

// Nada de cache: son datos clinicos y la sesion puede cambiar.
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

echo $contenido;
