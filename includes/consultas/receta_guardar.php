<?php
/**
 * Receta de una consulta.
 *
 * Al crearla se genera el pendiente de digitalizacion: la receta se
 * imprime sobre el recetario, se firma y la copia firmada regresa al
 * expediente escaneada.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.crear');

$idReceta     = paramInt('id_receta');
$idConsulta   = paramInt('id_consulta');
$indicaciones = paramTexto('indicaciones');
$idDentista   = paramInt('id_usuario_dentista');
$fecha        = trim((string) param('fecha', date('Y-m-d')));

if (!$idConsulta) {
    Respuesta::error('Falta la consulta.');
}
if ($indicaciones === null || mb_strlen($indicaciones) < 3) {
    Respuesta::validacion(['indicaciones' => 'Escribe lo que se receta.']);
}

$consulta = Conexion::uno(
    'SELECT id_consulta, id_registro_paciente, id_usuario_dentista, folio
       FROM consulta WHERE id_consulta = :c AND estatus = 1',
    [':c' => $idConsulta]
);
if ($consulta === null) {
    Respuesta::error('La consulta no existe.', 404);
}

$idDentista = $idDentista ?: (int) $consulta['id_usuario_dentista'];

$d = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$d || $d->format('Y-m-d') !== $fecha) {
    $fecha = date('Y-m-d');
}

if ($idReceta) {
    Conexion::consulta(
        'UPDATE receta
            SET indicaciones = :i, fecha = :f, id_usuario_dentista = :d
          WHERE id_receta = :r AND id_consulta = :c',
        [':i' => $indicaciones, ':f' => $fecha, ':d' => $idDentista,
         ':r' => $idReceta, ':c' => $idConsulta]
    );

    $folio  = (string) Conexion::valor(
        'SELECT folio FROM receta WHERE id_receta = :r', [':r' => $idReceta]);
    $accion = 'CAMBIO';
    $pendiente = null;
} else {
    $folio = siguienteFolio('receta', 'folio', 'RX');

    Conexion::consulta(
        'INSERT INTO receta
            (id_consulta, folio, fecha, id_usuario_dentista, indicaciones)
         VALUES (:c, :fo, :f, :d, :i)',
        [':c' => $idConsulta, ':fo' => $folio, ':f' => $fecha,
         ':d' => $idDentista, ':i' => $indicaciones]
    );

    $idReceta = Conexion::ultimoId();
    $accion   = 'ALTA';

    // El papel ya existe en cuanto se crea la receta: hay algo que
    // imprimir, firmar y escanear de regreso.
    $pendiente = Documentos::crearPendiente(
        (int) $consulta['id_registro_paciente'],
        'RECETA',
        'RECETA ' . $folio,
        'receta',
        $idReceta,
        null,
        $idConsulta
    );

    if ($pendiente !== null) {
        Conexion::consulta(
            'UPDATE receta SET id_documento = :d WHERE id_receta = :r',
            [':d' => $pendiente, ':r' => $idReceta]
        );
    }
}

Bitacora::registrar('consultas', $accion, 'receta', (string) $idReceta,
    ['consulta' => $idConsulta]);

Respuesta::ok(
    ['id_receta' => $idReceta, 'folio' => $folio, 'pendiente' => $pendiente],
    $accion === 'ALTA' ? 'Receta guardada.' : 'Receta actualizada.'
);
