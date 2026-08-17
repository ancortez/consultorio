<?php
/**
 * Activa o desactiva un procedimiento.
 * No se borra: los planes de tratamiento viejos lo siguen referenciando
 * y su historia tiene que quedar legible.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.procedimientos');

$id      = paramInt('id_procedimiento');
$estatus = (int) param('estatus', 1);

if (!$id) { Respuesta::error('Falta el procedimiento.'); }

Conexion::consulta(
    'UPDATE procedimiento SET estatus = :e WHERE id_procedimiento = :id',
    [':e' => $estatus, ':id' => $id]
);

Bitacora::registrar('configuracion', $estatus === 1 ? 'ALTA' : 'BAJA',
    'procedimiento', (string) $id);

Respuesta::ok(null, $estatus === 1 ? 'Procedimiento activado.' : 'Procedimiento desactivado.');
