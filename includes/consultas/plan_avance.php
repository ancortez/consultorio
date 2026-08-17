<?php
/**
 * Marca el avance de un concepto del plan.
 *
 * 0 pendiente | 1 en proceso | 2 terminado | 3 cancelado
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.plan');

$idDetalle = paramInt('id_plan_detalle');
$avance    = paramInt('estado_avance');

if (!$idDetalle || $avance === null || $avance < 0 || $avance > 3) {
    Respuesta::error('Faltan datos para registrar el avance.');
}

Conexion::consulta(
    'UPDATE plan_tratamiento_detalle
        SET estado_avance = :a,
            fecha_ejecucion = CASE WHEN :a2 = 2 THEN NOW() ELSE fecha_ejecucion END
      WHERE id_plan_detalle = :id',
    [':a' => $avance, ':a2' => $avance, ':id' => $idDetalle]
);

// Si ya se termino todo, el plan pasa a Terminado solo.
$plan = Conexion::uno(
    'SELECT id_plan_tratamiento FROM plan_tratamiento_detalle
      WHERE id_plan_detalle = :id',
    [':id' => $idDetalle]
);

if ($plan !== null) {
    $faltan = (int) Conexion::valor(
        'SELECT COUNT(*) FROM plan_tratamiento_detalle
          WHERE id_plan_tratamiento = :p AND estado_avance IN (0, 1)',
        [':p' => $plan['id_plan_tratamiento']]
    );

    Conexion::consulta(
        'UPDATE plan_tratamiento SET id_estado_plan = :e
          WHERE id_plan_tratamiento = :p AND id_estado_plan NOT IN (5)',
        [':e' => $faltan === 0 ? 4 : 3, ':p' => $plan['id_plan_tratamiento']]
    );
}

Bitacora::registrar('consultas', 'CAMBIO', 'plan_tratamiento_detalle',
    (string) $idDetalle, ['avance' => $avance]);

Respuesta::ok(null, 'Avance registrado.');
