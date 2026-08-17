<?php
/**
 * Agrega un pendiente a mano, para documentos que no nacen solos
 * (una radiografia, un estudio externo).
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idExpediente = paramInt('id_registro_paciente');
$clave        = (string) param('clave', '');
$descripcion  = limpiarEspacios(paramMayus('descripcion'));

if (!$idExpediente || $clave === '') {
    Respuesta::error('Faltan datos para crear el pendiente.');
}

$id = Documentos::crearPendiente($idExpediente, $clave, $descripcion);

if ($id === null) {
    Respuesta::error('Ese documento ya esta pendiente de digitalizar.');
}

Respuesta::ok(['id_documento' => $id], 'Pendiente agregado.');
