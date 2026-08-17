<?php
/**
 * Baja logica de un canal de contacto.
 * IMPORTANTE: no se borra el renglon, solo se cambia estatus a 0, tal
 * como pide la composicion. Los datos clinicos y de contacto nunca se
 * borran fisicamente.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$id = paramInt('id_contacto_persona');
if (!$id) { Respuesta::error('Falta el identificador del contacto.'); }

Conexion::consulta(
    'UPDATE contacto_persona SET estatus = 0 WHERE id_contacto_persona = :id',
    [':id' => $id]
);

Bitacora::registrar('paciente', 'BAJA', 'contacto_persona', (string) $id);
Respuesta::ok(null, 'Contacto eliminado.');
