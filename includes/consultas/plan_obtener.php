<?php
/**
 * Un plan con todos sus renglones.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.ver');

$idPlan = paramInt('id_plan_tratamiento');
if (!$idPlan) {
    Respuesta::error('Falta el plan.');
}

$plan = Conexion::uno(
    'SELECT pt.*, ep.nombre_estado, u.nombre_completo AS dentista,
            vp.nombre_completo AS paciente, vp.num_expediente, vp.edad
       FROM plan_tratamiento pt
       JOIN estado_plan ep ON ep.id_estado_plan = pt.id_estado_plan
       JOIN usuario u ON u.id_usuario = pt.id_usuario_dentista
       JOIN v_paciente vp ON vp.id_registro_paciente = pt.id_registro_paciente
      WHERE pt.id_plan_tratamiento = :p',
    [':p' => $idPlan]
);

if ($plan === null) {
    Respuesta::error('El plan no existe.', 404);
}

$plan['renglones'] = Conexion::todos(
    'SELECT d.*, pr.nombre_procedimiento, pr.aplica_por_diente,
            pr.requiere_anestesia, di.nombre_diente
       FROM plan_tratamiento_detalle d
       LEFT JOIN procedimiento pr ON pr.id_procedimiento = d.id_procedimiento
       LEFT JOIN diente di ON di.id_diente = d.id_diente
      WHERE d.id_plan_tratamiento = :p
      ORDER BY d.orden, d.id_plan_detalle',
    [':p' => $idPlan]
);

Respuesta::ok($plan);
