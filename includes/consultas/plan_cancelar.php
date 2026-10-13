<?php
/**
 * Cancela un plan de tratamiento.
 *
 * La regla de cuando se puede vive en Planes, no aqui: la misma
 * pregunta se la hacen la pantalla —para ensenar o esconder el boton—
 * y este endpoint, y repartida acabarian discrepando. El sintoma seria
 * un boton que sale y al picarlo dice que no se puede.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.plan');

$idPlan = paramInt('id_plan_tratamiento');
$motivo = paramTexto('motivo');

if (!$idPlan) { Respuesta::error('Falta el plan.'); }

try {
    Planes::cancelar($idPlan, (string) $motivo);
} catch (InvalidArgumentException $e) {
    Respuesta::validacion(['motivo' => $e->getMessage()]);
} catch (Throwable $e) {
    Respuesta::error($e->getMessage());
}

Respuesta::ok(
    ['id_estado_plan' => Planes::CANCELADO],
    'Plan cancelado. Se quitaron sus pendientes de digitalizacion.'
);
