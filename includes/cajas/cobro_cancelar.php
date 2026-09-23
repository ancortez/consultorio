<?php
/**
 * Cancela un cobro del mismo dia, con el turno todavia abierto.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.cancelar');

$idPago = paramInt('id_pago');
$motivo = limpiarEspacios(paramMayus('motivo'));

if (!$idPago) {
    Respuesta::error('Falta el cobro.');
}
if ($motivo === null || mb_strlen($motivo) < 4) {
    Respuesta::validacion(['motivo' => 'Di por que se cancela.']);
}

try {
    Caja::cancelarPago($idPago, $motivo);
} catch (Throwable $e) {
    Respuesta::error($e->getMessage());
}

Respuesta::ok(['id_pago' => $idPago], 'Cobro cancelado.');
