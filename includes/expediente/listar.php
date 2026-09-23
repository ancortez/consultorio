<?php
/**
 * Expediente electronico de un paciente, ya agrupado.
 *
 * Devuelve el bloque general (foto, credenciales, historial, consultas
 * sueltas) y despues un bloque por cada plan de tratamiento.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.ver');

$idExpediente = paramInt('id_registro_paciente');
if (!$idExpediente) {
    Respuesta::error('Falta el expediente.');
}

Respuesta::ok(Documentos::arbol($idExpediente) + [
    'pendientes' => Documentos::cuantosPendientes($idExpediente),
    'almacen'    => Almacen::revisar(),
]);
