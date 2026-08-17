<?php
/**
 * Espacios de media hora libres de un dentista en un dia.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.ver');

$fecha      = trim((string) param('fecha', ''));
$idDentista = paramInt('id_usuario_dentista');
$duracion   = paramInt('duracion_min') ?? Agenda::BLOQUE;
$idCita     = paramInt('id_cita');

if ($fecha === '' || !$idDentista) {
    Respuesta::error('Falta la fecha o el dentista.');
}

$d = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$d || $d->format('Y-m-d') !== $fecha) {
    Respuesta::error('La fecha no es valida.');
}

Respuesta::ok([
    'espacios' => Agenda::espaciosDelDia($fecha, $idDentista, $duracion, $idCita),
    'bloque'   => Agenda::BLOQUE,
]);
