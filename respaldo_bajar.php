<?php
/**
 * Entrega un respaldo al navegador para descargarlo.
 *
 * Es un archivo suelto en una carpeta del servidor y no un registro de
 * la base, asi que va por su propio punto de entrada —como archivo.php
 * con los documentos del expediente— y no por un endpoint de JSON: lo
 * que sale de aqui son megabytes de datos binarios, no una respuesta.
 *
 * Uso:  /respaldo_bajar.php?nombre=base-20261017-0230.sql
 *
 * Lo que se comprueba, en orden:
 *
 *   1. que haya sesion
 *   2. que el usuario tenga config.respaldos, que solo tiene el
 *      administrador. Esto es lo mas delicado del sistema: el archivo
 *      trae el expediente clinico completo de todos los pacientes, en
 *      texto plano y en un solo lugar.
 *   3. que el nombre encaje en el patron de Respaldos. Sin eso, un
 *      "nombre=../../etc/passwd" convertiria esta pantalla en una
 *      descarga de lo que sea del servidor.
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

Auth::exigirLogin();
Auth::exigirPermiso('config.respaldos');

$nombre = (string) ($_GET['nombre'] ?? '');
$ruta   = Respaldos::ruta($nombre);

if ($ruta === null) {
    http_response_code(404);
    exit('Ese respaldo no existe.');
}

$bytes = (int) filesize($ruta);

/*
   La marca de "ya lo baje" se pone ANTES de mandar el archivo.

   Despues no se puede: en cuanto empieza a salir el contenido ya no hay
   forma de hacer nada mas con seguridad, y si la descarga se corta a la
   mitad el navegador no avisa a nadie. Marcarlo antes puede dejar un
   respaldo marcado que no termino de bajar —molesto— frente a no
   marcarlo nunca, que es lo que hace inservible la columna.
*/
Respaldos::marcarBajado($nombre);

Bitacora::registrar('configuracion', 'DESCARGA', 'respaldo', $nombre,
    ['bytes' => $bytes]);

/*
   Se limpia lo que haya en el buffer antes de mandar el archivo.

   Un aviso de PHP o un salto de linea de mas se pegarian al principio
   del .sql, y el archivo dejaria de restaurarse: MySQL se queja de una
   sintaxis que nadie escribio. Es de los fallos que se descubren el dia
   que hace falta el respaldo.
*/
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($nombre) . '"');
header('Content-Length: ' . $bytes);
header('X-Content-Type-Options: nosniff');
// Un respaldo no se guarda en ninguna cache: ni en el navegador ni en
// nada que haya en medio.
header('Cache-Control: private, no-store');

// readfile y no file_get_contents: un respaldo de la base puede pesar
// cientos de megabytes y cargarlo entero en memoria reventaria el
// limite de PHP justo con los expedientes mas grandes.
readfile($ruta);
