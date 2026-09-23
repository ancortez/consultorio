<?php
/**
 * Bloquea un rango de la agenda.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.agenda');

$idUsuario = paramInt('id_usuario') ?: null;
$inicio    = trim((string) param('fecha_inicio', ''));
$fin       = trim((string) param('fecha_fin', ''));
$motivo    = limpiarEspacios(paramMayus('motivo'));
$forzar    = param('forzar') === '1';

$inicio = str_replace('T', ' ', $inicio);
$fin    = str_replace('T', ' ', $fin);
if (strlen($inicio) === 16) { $inicio .= ':00'; }
if (strlen($fin) === 16)    { $fin .= ':00'; }

$errores = [];
if (!strtotime($inicio)) { $errores['fecha_inicio'] = 'Fecha de inicio invalida.'; }
if (!strtotime($fin))    { $errores['fecha_fin']    = 'Fecha de fin invalida.'; }
if ($motivo === null || $motivo === '') {
    $errores['motivo'] = 'Di por que se bloquea.';
}
if ($errores) { Respuesta::validacion($errores); }

// Un bloqueo encima de citas vivas no se impide, pero se avisa antes:
// a veces es lo que se quiere (cerrar el dia y reagendar), y otras es
// un error de fechas que habria dejado pacientes en el aire.
$choques = AgendaExtra::citasEnBloqueo($inicio, $fin, $idUsuario);

if ($choques && !$forzar) {
    Respuesta::ok(
        ['confirmar' => true, 'citas' => $choques],
        'Hay ' . count($choques) . ' cita(s) dentro de ese rango.'
    );
}

try {
    $id = AgendaExtra::guardarBloqueo([
        'id_usuario'   => $idUsuario,
        'fecha_inicio' => $inicio,
        'fecha_fin'    => $fin,
        'motivo'       => $motivo,
    ]);
} catch (Throwable $e) {
    Respuesta::error($e->getMessage());
}

Respuesta::ok(
    ['id_bloqueo' => $id, 'citas' => $choques],
    $choques
        ? 'Bloqueo guardado. Quedaron ' . count($choques) . ' cita(s) dentro: reagendalas.'
        : 'Bloqueo guardado.'
);
