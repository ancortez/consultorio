<?php
/**
 * Da un plan de tratamiento por terminado.
 *
 * Se permite aunque queden conceptos sin marcar. La realidad es que
 * muchos tratamientos se cierran con algo pendiente —el paciente dejo
 * de venir, se cambio de ciudad— y obligar a marcar uno por uno lo que
 * NO se hizo lleva a marcarlos como hechos, que ensucia el expediente
 * mucho mas que un plan cerrado con dos conceptos sin tocar.
 *
 * Quien cierra se entera de cuantos quedan ANTES de confirmar: eso lo
 * hace la pantalla, que pregunta con el numero delante.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.plan');

$idPlan = paramInt('id_plan_tratamiento');
if (!$idPlan) { Respuesta::error('Falta el plan.'); }

$pendientes = Planes::conceptosPendientes($idPlan);

try {
    Planes::terminar($idPlan);
} catch (Throwable $e) {
    Respuesta::error($e->getMessage());
}

Respuesta::ok(
    ['id_estado_plan' => Planes::TERMINADO, 'conceptos_pendientes' => $pendientes],
    $pendientes > 0
        ? "Plan terminado. Quedaron $pendientes concepto(s) sin realizar."
        : 'Plan terminado.'
);
