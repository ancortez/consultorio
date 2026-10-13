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
        // Si se marco, el dinero que el paciente ya tenia a favor se
        // gasta antes de pedirle nada. Lo decide quien cobra, no el
        // sistema: hay pacientes que prefieren dejar su anticipo quieto
        // para el tratamiento largo y pagar aparte la limpieza de hoy.
        'usar_a_favor'         => param('usar_a_favor') ? true : false,
        // La pantalla la genera al abrir el formulario. Dos envios del
        // mismo formulario traen la misma y solo se cobra una vez.
        'huella_envio'         => param('huella_envio'),
    ]);
} catch (InvalidArgumentException $e) {
    Respuesta::validacion(['monto' => $e->getMessage()]);
} catch (Throwable $e) {
    Respuesta::error($e->getMessage());
}

// El sobrante se avisa. Un anticipo que nadie menciona se olvida, y
// despues nadie entiende por que el saldo no cuadra.
/*
   Lo que se dice al terminar depende de que paso, y los tres casos son
   distintos de verdad:

     - el saldo a favor lo cubrio todo: no hay recibo que imprimir
     - se cobro algo y ademas se uso saldo: hay que decir cuanto salio
       de donde, o el recibo por menos de lo esperado genera la duda
     - cobro normal
*/
if (!empty($r['solo_a_favor'])) {
    $mensaje = 'Se aplicaron ' . money($r['a_favor_aplicado'])
             . ' del saldo a favor. No hubo nada que cobrar.';
} elseif (($r['a_favor_aplicado'] ?? 0) > 0.009) {
    $mensaje = 'Cobro ' . $r['folio'] . ' registrado por ' . money($r['monto'])
             . ', mas ' . money($r['a_favor_aplicado']) . ' de su saldo a favor.';
} elseif ($r['a_favor'] > 0.009) {
    $mensaje = 'Cobro ' . $r['folio'] . ' registrado. Quedan '
             . money($r['a_favor']) . ' a favor del paciente.';
} else {
    $mensaje = 'Cobro ' . $r['folio'] . ' registrado.';
}

Respuesta::ok($r, $mensaje);
