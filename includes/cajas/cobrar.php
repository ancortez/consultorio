<?php
/**
 * Registra un cobro.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.cobrar');

$idExpediente = paramInt('id_registro_paciente');
$idCorte      = paramInt('id_corte_caja');
$idPlan       = paramInt('id_plan_tratamiento') ?: null;

// Las formas de pago y las aplicaciones viajan como JSON: son listas
// de largo variable y mandarlas como campos sueltos obligaria a
// inventar nombres numerados.
$formas = json_decode((string) param('formas', '[]'), true);
$aplic  = json_decode((string) param('aplicaciones', '[]'), true);

if (!$idExpediente) {
    Respuesta::error('Selecciona al paciente.');
}
if (!is_array($formas)) { $formas = []; }
if (!is_array($aplic))  { $aplic  = []; }

try {
    $r = Caja::cobrar([
        'id_registro_paciente' => $idExpediente,
        'id_corte_caja'        => $idCorte,
        'id_plan_tratamiento'  => $idPlan,
        'formas'               => $formas,
        'aplicaciones'         => $aplic,
        'observaciones'        => param('observaciones'),
    ]);
} catch (InvalidArgumentException $e) {
    Respuesta::validacion(['monto' => $e->getMessage()]);
} catch (Throwable $e) {
    Respuesta::error($e->getMessage());
}

// El sobrante se avisa. Un anticipo que nadie menciona se olvida, y
// despues nadie entiende por que el saldo no cuadra.
$mensaje = $r['a_favor'] > 0.009
    ? 'Cobro ' . $r['folio'] . ' registrado. Quedan '
      . money($r['a_favor']) . ' a favor del paciente.'
    : 'Cobro ' . $r['folio'] . ' registrado.';

Respuesta::ok($r, $mensaje);
