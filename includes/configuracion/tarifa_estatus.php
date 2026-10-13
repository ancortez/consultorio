<?php
/**
 * Activa o desactiva un precio del tarifario.
 *
 * No se borra nunca: los planes de tratamiento ya firmados apuntan a
 * el, y un plan que pierde el nombre de lo que se cobro deja de ser
 * legible. Desactivar solo lo saca de las listas de donde se elige.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('config.tarifas');

$id      = paramInt('id_tratamiento');
$estatus = (int) param('estatus', 1);

if (!$id) { Respuesta::error('Falta el tratamiento.'); }

Conexion::consulta(
    'UPDATE tratamiento SET estatus = :e WHERE id_tratamiento = :id',
    [':e' => $estatus, ':id' => $id]
);

Bitacora::registrar('configuracion', $estatus === 1 ? 'ALTA' : 'BAJA',
    'tratamiento', (string) $id);

Respuesta::ok(null, $estatus === 1 ? 'Precio activado.' : 'Precio desactivado.');
