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
 *       /archivo.php?id=123&mini=1 una miniatura, si es imagen
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

Auth::exigirLogin();
Auth::exigirPermiso('paciente.ver');

$idDocumento = (int) ($_GET['id'] ?? 0);
$descargar   = isset($_GET['d']);
$miniatura   = isset($_GET['mini']);

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

/*
   La miniatura.

   El expediente muestra la foto o la radiografia en un cuadro de unos
   50 px. Servir ahi el archivo completo significa bajar una radiografia
   de 2400 px —a veces varios megas— por cada renglon de la lista: con
   quince documentos, la pantalla tarda en dibujarse y el consultorio
   paga ese ancho de banda cada vez que alguien abre un expediente.

   Se reduce aqui, al servirla. No se guarda una copia reducida en el
   almacen a proposito: seria un segundo archivo que mantener
   sincronizado con el primero, y la pregunta "¿cual de los dos es el
   bueno?" ya me ha costado caro en otras partes de este sistema.

   La bitacora NO anota las miniaturas. Una lista de quince documentos
   generaria quince renglones de "CONSULTA" por cada vez que alguien
   abre la pestania, y eso ahoga el rastro de los accesos de verdad,
   que es para lo que existe la bitacora en un expediente clinico.
*/
if ($miniatura && str_starts_with((string) $doc['tipo_mime'], 'image/')) {
    $mini = Imagen::miniatura($contenido, 160);

    if ($mini !== null) {
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . strlen($mini));
        header('Content-Disposition: inline; filename="mini.jpg"');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $mini;
        exit;
    }
    // Si no se pudo reducir se entrega el original: vale mas una
    // pantalla lenta que un hueco donde deberia haber una radiografia.
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
