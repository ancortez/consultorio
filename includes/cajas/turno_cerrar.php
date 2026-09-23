<?php
/**
 * Cierra el turno y deja registrada la diferencia.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.corte');

$idCorte = paramInt('id_corte_caja');
$contado = param('monto_contado');
$obs     = limpiarEspacios(paramMayus('observaciones'));

if (!$idCorte) {
    Respuesta::error('Falta el turno.');
}
if ($contado === null || $contado === '') {
    Respuesta::validacion([
        'monto_contado' => 'Captura cuanto efectivo contaste.',
    ]);
}

try {
    $r = Caja::cerrar($idCorte, (float) str_replace(',', '', (string) $contado), $obs);
} catch (Throwable $e) {
    Respuesta::error($e->getMessage());
}

// Una diferencia se avisa en el mensaje, no se esconde en un campo.
$mensaje = abs($r['diferencia']) < 0.01
    ? 'Turno cerrado. El efectivo cuadro exacto.'
    : ($r['diferencia'] > 0
        ? 'Turno cerrado. Sobran ' . money($r['diferencia']) . ' en efectivo.'
        : 'Turno cerrado. Faltan ' . money(abs($r['diferencia'])) . ' en efectivo.');

Respuesta::ok($r + ['id_corte_caja' => $idCorte], $mensaje);
