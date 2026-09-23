<?php
/**
 * Estado de cuenta completo de un paciente.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.estado_cuenta');

$idExpediente = paramInt('id_registro_paciente');
if (!$idExpediente) {
    Respuesta::error('Falta el paciente.');
}

Respuesta::ok(Cargos::estadoDeCuenta($idExpediente));
