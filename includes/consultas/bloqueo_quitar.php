<?php
/**
 * Quita un bloqueo de agenda.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.agenda');

$id = paramInt('id_bloqueo');
if (!$id) {
    Respuesta::error('Falta el bloqueo.');
}

Conexion::consulta(
    'UPDATE agenda_bloqueo SET estatus = 0 WHERE id_bloqueo = :b', [':b' => $id]);

Bitacora::registrar('consultas', 'BAJA', 'agenda_bloqueo', (string) $id);

Respuesta::ok(['id_bloqueo' => $id], 'Bloqueo quitado.');
