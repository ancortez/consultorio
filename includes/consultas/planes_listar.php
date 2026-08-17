<?php
/**
 * Historial de planes de tratamiento de un paciente, con su avance.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.ver');

$idExpediente = paramInt('id_registro_paciente');
if (!$idExpediente) {
    Respuesta::error('Falta el expediente.');
}

Respuesta::ok(Conexion::todos(
    'SELECT pt.*, ep.nombre_estado, u.nombre_completo AS dentista,
            ps.pagado, ps.saldo_actual,
            (SELECT COUNT(*) FROM plan_tratamiento_detalle d
              WHERE d.id_plan_tratamiento = pt.id_plan_tratamiento) AS conceptos,
            (SELECT COUNT(*) FROM plan_tratamiento_detalle d
              WHERE d.id_plan_tratamiento = pt.id_plan_tratamiento
                AND d.estado_avance = 2) AS terminados
       FROM plan_tratamiento pt
       JOIN estado_plan ep ON ep.id_estado_plan = pt.id_estado_plan
       JOIN usuario u ON u.id_usuario = pt.id_usuario_dentista
       LEFT JOIN v_plan_saldo ps
              ON ps.id_plan_tratamiento = pt.id_plan_tratamiento
      WHERE pt.id_registro_paciente = :r
      ORDER BY pt.fecha DESC, pt.id_plan_tratamiento DESC',
    [':r' => $idExpediente]
));
