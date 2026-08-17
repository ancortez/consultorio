<?php
/**
 * Documentos del expediente electronico de un paciente.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.ver');

$idExpediente = paramInt('id_registro_paciente');
if (!$idExpediente) {
    Respuesta::error('Falta el expediente.');
}

Respuesta::ok([
    'documentos' => Documentos::listar($idExpediente),
    'pendientes' => Documentos::cuantosPendientes($idExpediente),
    'almacen'    => Almacen::revisar(),
]);
