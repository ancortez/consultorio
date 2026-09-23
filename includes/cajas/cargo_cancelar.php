<?php
/**
 * Cancela un cargo. Solo si nadie le ha abonado nada.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.cargo');

$idCargo = paramInt('id_cargo');
$motivo  = limpiarEspacios(paramMayus('motivo'));

if (!$idCargo) {
    Respuesta::error('Falta el cargo.');
}
if ($motivo === null || mb_strlen($motivo) < 4) {
    Respuesta::validacion(['motivo' => 'Di por que se cancela.']);
}

try {
    Cargos::cancelar($idCargo, $motivo);
} catch (Throwable $e) {
    Respuesta::error($e->getMessage());
}

Respuesta::ok(['id_cargo' => $idCargo], 'Cargo cancelado.');
