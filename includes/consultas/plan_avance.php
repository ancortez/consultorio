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
    $idPlan = (int) $plan['id_plan_tratamiento'];
    $estado = Planes::estadoDe($idPlan);

    /*
       Un plan ya cerrado NO se mueve de aqui.

       Antes la condicion era "NOT IN (5)": protegia a los cancelados y
       se olvidaba de los TERMINADOS. Entonces desmarcar un concepto de
       un tratamiento cerrado hace meses lo devolvia a En proceso, y ese
       paciente reaparecia como activo en el listado y en sus cuentas.

       Ahora la regla la decide Planes, que es donde vive: terminado y
       cancelado son finales, y lo son para todos los caminos que
       intenten moverlos, no solo para este.
    */
    if ($estado !== null && Planes::puedeAvanzarAuto($estado)) {
        $faltan = Planes::conceptosPendientes($idPlan);

        Conexion::consulta(
            'UPDATE plan_tratamiento
                SET id_estado_plan = :e,
                    fecha_terminado = CASE WHEN :e2 = :fin
                        THEN NOW() ELSE fecha_terminado END
              WHERE id_plan_tratamiento = :p',
            [':e'  => $faltan === 0 ? Planes::TERMINADO : Planes::EN_PROCESO,
             ':e2' => $faltan === 0 ? Planes::TERMINADO : Planes::EN_PROCESO,
             ':fin' => Planes::TERMINADO,
             ':p'  => $idPlan]
        );
    }
}

Bitacora::registrar('consultas', 'CAMBIO', 'plan_tratamiento_detalle',
    (string) $idDetalle, ['avance' => $avance]);

Respuesta::ok(null, 'Avance registrado.');
