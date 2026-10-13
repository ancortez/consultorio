<?php
/**
 * Convertir a JPEG las fotos .heic que ya quedaron en el expediente.
 *
 * Para que sirve
 * --------------
 * Durante unos dias se aceptaron fotos de iPhone y la conversion fallaba
 * en silencio: el archivo se guardaba tal cual. El resultado es un
 * expediente que PARECE completo y no lo es —el album sale vacio, no hay
 * miniaturas, y al descargar una foto viene un .heic que el navegador no
 * abre—. Los documentos estan, con su descripcion y su fecha; lo que no
 * se puede es verlos.
 *
 * Este guion los busca, los convierte y los deja como si se hubieran
 * subido bien. Es de una sola vez: despues de correrlo, el sistema ya
 * rechaza el HEIC que no pueda convertir, asi que no vuelven a entrar.
 *
 * Como se corre
 * -------------
 *     docker compose exec app php tools/reparar_heic.php --ver
 *     docker compose exec app php tools/reparar_heic.php
 *
 * Con --ver solo dice que encontro, sin tocar nada. Es lo primero que
 * conviene hacer.
 *
 * Que NO hace
 * -----------
 * No borra nada hasta tener el JPEG en la mano, y si una foto no se
 * puede convertir la deja como esta y sigue con las demas. Un guion de
 * reparacion que se detiene a la mitad y deja medio expediente
 * convertido es peor que no correrlo.
 */

require_once dirname(__DIR__) . '/includes/core/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este guion solo se corre desde la linea de comandos.\n");
}

$soloVer = in_array('--ver', $argv, true);

$VERDE = "\033[0;32m"; $ROJO = "\033[0;31m";
$AMAR  = "\033[1;33m"; $NEG  = "\033[1m"; $FIN = "\033[0m";

echo "\n{$NEG}  Reparar las fotos .heic del expediente{$FIN}\n";
echo "  -------------------------------------\n";
if ($soloVer) {
    echo "  {$AMAR}Solo mirar: no se va a cambiar nada.{$FIN}\n";
}
echo "\n";

// ---------------------------------------------------------------------
// Primero: ¿se puede convertir en esta instalacion?
// ---------------------------------------------------------------------
// Sin esto, el guion recorreria cien documentos para fallar cien veces,
// y el resumen diria "0 convertidas" sin explicar por que.
$porQue = Imagen::porQueNoConvierteHeic();
if ($porQue !== null) {
    echo "  {$ROJO}!!{$FIN}   Aqui no se puede convertir HEIC: $porQue\n\n";
    echo "       Arregla eso primero; si no, este guion no puede hacer nada.\n";
    echo "       Casi siempre es reconstruir la imagen de PHP:\n";
    echo "           docker compose build app && docker compose up -d\n\n";
    exit(1);
}
echo "  {$VERDE}ok{$FIN}   heif-convert responde\n\n";

// ---------------------------------------------------------------------
// Que hay que reparar
// ---------------------------------------------------------------------
// Se busca por la extension de la ruta Y por el mime: si alguno de los
// dos quedo mal, el documento no se ve igual.
$pendientes = Conexion::todos(
    "SELECT d.id_documento, d.ruta_archivo, d.nombre_original, d.descripcion,
            d.id_registro_paciente, td.clave, vp.nombre_completo AS paciente
       FROM documento d
       JOIN tipo_documento td ON td.id_tipo_documento = d.id_tipo_documento
       LEFT JOIN v_paciente vp
              ON vp.id_registro_paciente = d.id_registro_paciente
      WHERE d.estado = 1
        AND d.ruta_archivo IS NOT NULL
        AND (LOWER(d.ruta_archivo) LIKE '%.heic'
          OR LOWER(d.ruta_archivo) LIKE '%.heif'
          OR LOWER(d.tipo_mime) IN ('image/heic', 'image/heif'))
      ORDER BY d.id_documento"
);

if (!$pendientes) {
    echo "  {$VERDE}ok{$FIN}   No hay ninguna foto .heic guardada. Nada que hacer.\n\n";
    exit(0);
}

echo "  Encontradas: {$NEG}" . count($pendientes) . "{$FIN}\n\n";
foreach ($pendientes as $d) {
    printf("      #%-6d %-28s %s\n",
        $d['id_documento'],
        mb_substr((string) ($d['paciente'] ?? 'sin paciente'), 0, 28),
        (string) ($d['descripcion'] ?? $d['nombre_original'] ?? ''));
}
echo "\n";

if ($soloVer) {
    echo "  Para convertirlas:  php tools/reparar_heic.php\n\n";
    exit(0);
}

// ---------------------------------------------------------------------
// Convertir, una por una
// ---------------------------------------------------------------------
$hechas = 0;
$fallos = [];

foreach ($pendientes as $d) {
    $id = (int) $d['id_documento'];
    printf("    #%-6d ", $id);

    $heic = Almacen::obtener((string) $d['ruta_archivo']);
    if ($heic === null) {
        echo "{$ROJO}sin archivo{$FIN}\n";
        $fallos[] = "#$id: el archivo no esta en el almacen";
        continue;
    }

    // Se pasa por el mismo camino que una subida normal, asi que la foto
    // queda ademas reducida y girada igual que las demas. Reimplementar
    // la conversion aqui seria una segunda version de la misma regla.
    try {
        $optimo = Imagen::optimizar($heic, 'heic', (string) $d['clave']);
    } catch (Throwable $e) {
        echo "{$ROJO}no se pudo{$FIN}\n";
        $fallos[] = "#$id: " . $e->getMessage();
        continue;
    }

    if ($optimo['ext'] !== 'jpg') {
        echo "{$ROJO}no se convirtio{$FIN}\n";
        $fallos[] = "#$id: la conversion no devolvio un JPEG";
        continue;
    }

    $rutaVieja = (string) $d['ruta_archivo'];
    $rutaNueva = Almacen::rutaDocumento(
        (int) $d['id_registro_paciente'], (string) $d['clave'], 'jpg');

    // El orden importa: primero se sube el nuevo, luego se apunta la
    // base al nuevo, y solo al final se borra el viejo. Al reves, un
    // corte de luz a media faena dejaria la base apuntando a un archivo
    // que ya no existe, y eso si es un hueco de verdad.
    try {
        Almacen::subir($rutaNueva, $optimo['contenido'], $optimo['mime']);
    } catch (Throwable $e) {
        echo "{$ROJO}no se pudo subir{$FIN}\n";
        $fallos[] = "#$id: " . $e->getMessage();
        continue;
    }

    Conexion::consulta(
        'UPDATE documento
            SET ruta_archivo = :r, tipo_mime = :m, tamano_bytes = :t,
                nombre_original = :n
          WHERE id_documento = :d',
        [':r' => $rutaNueva, ':m' => $optimo['mime'],
         ':t' => strlen($optimo['contenido']),
         ':n' => preg_replace('/\.(heic|heif)$/i', '.jpg',
             (string) ($d['nombre_original'] ?? 'foto.jpg')),
         ':d' => $id]
    );

    if ($rutaNueva !== $rutaVieja) {
        Almacen::eliminar($rutaVieja);
    }

    // La foto del paciente lleva ademas su recorte circular, que se
    // genero del HEIC y por lo tanto salio vacio.
    if ($d['clave'] === 'FOTO_PACIENTE') {
        Documentos::generarAvatar(
            (int) $d['id_registro_paciente'], $optimo['contenido']);
    }

    Bitacora::registrar('expediente', 'REPARA_HEIC', 'documento', (string) $id,
        ['de' => $rutaVieja, 'a' => $rutaNueva]);

    echo "{$VERDE}convertida{$FIN}\n";
    $hechas++;
}

echo "\n";
echo "  {$VERDE}{$NEG}Convertidas: $hechas{$FIN}";
echo count($fallos) ? "   {$ROJO}con problema: " . count($fallos) . "{$FIN}\n" : "\n";

foreach ($fallos as $f) {
    echo "    {$ROJO}!!{$FIN}   $f\n";
}
echo "\n";

// Se sale con error si quedo algo sin convertir: asi el guion se puede
// encadenar y nadie da por terminada una reparacion a medias.
exit($fallos ? 1 : 0);
