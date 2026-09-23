<?php
/**
 * Abre el turno de una caja.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.corte');

$idCaja = paramInt('id_caja');
$fondo  = (float) str_replace(',', '', (string) param('monto_inicial', '0'));
$obs    = limpiarEspacios(paramMayus('observaciones'));

if (!$idCaja) {
    Respuesta::error('Selecciona la caja.');
}
if ($fondo < 0) {
    Respuesta::validacion(['monto_inicial' => 'El fondo no puede ser negativo.']);
}

try {
    $idCorte = Caja::abrir($idCaja, $fondo, $obs);
} catch (Throwable $e) {
    Respuesta::error($e->getMessage());
}

Respuesta::ok(['id_corte_caja' => $idCorte], 'Turno abierto.');
