<?php
/**
 * Planes de tratamiento con su avance.
 *
 * Sirve a dos pantallas:
 *   - con id_registro_paciente: el historial completo de ese paciente
 *   - con abiertos=1: los planes vivos de todo el consultorio
 *     (propuestos, aceptados y en proceso), que es lo que le interesa
 *     ver a la doctora al entrar al modulo. Los terminados y cancelados
 *     ya no son trabajo pendiente y solo estorbarian la lista.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.ver');

$idExpediente = paramInt('id_registro_paciente');
$abiertos     = param('abiertos') === '1';
$estado       = paramInt('estado');
$q            = trim((string) param('q', ''));

if (!$idExpediente && !$abiertos) {
    Respuesta::error('Falta el expediente.');
}

$where  = [];
$params = [];

if ($idExpediente) {
    $where[]          = 'pt.id_registro_paciente = :r';
    $params[':r']     = $idExpediente;
}

/*
   El estatus pedido manda sobre "los vigentes".

   La pantalla trae por omision los planes vivos —propuestos, aceptados
   y en proceso—, que es la lista de trabajo. Pero pidiendo uno
   concreto se trae ese, aunque no este vivo: los CANCELADOS se
   consultan justo cuando alguien pregunta por que un paciente no
   siguio, y mandarlo al expediente de cada paciente a buscarlos uno
   por uno no contesta esa pregunta.

   El filtro se hace aqui y no en la pantalla. Antes el estatus se
   aplicaba sobre lo que ya habia llegado, asi que no podia ensenar
   nada que la consulta no hubiera traido: un filtro que solo puede
   quitar de la lista no puede ofrecer los cancelados.
*/
if ($estado) {
    $where[]      = 'pt.id_estado_plan = :e';
    $params[':e'] = $estado;
} elseif ($abiertos) {
    // 1 Propuesto | 2 Aceptado | 3 En proceso
    $where[] = 'pt.id_estado_plan IN (1, 2, 3)';
}

if ($q !== '') {
    // Cada LIKE lleva su propio parametro: con EMULATE_PREPARES en false
    // MySQL no acepta el mismo nombre repetido en una consulta.
    $where[] = '(pt.folio LIKE :q1 OR vp.nombre_completo LIKE :q2
                 OR pt.diagnostico LIKE :q3 OR vp.num_expediente LIKE :q4)';
    $comodin = '%' . $q . '%';
    $params[':q1'] = $comodin;
    $params[':q2'] = $comodin;
    $params[':q3'] = $comodin;
    $params[':q4'] = $comodin;
}

$sql = 'SELECT pt.*, ep.nombre_estado, u.nombre_completo AS dentista,
               vp.nombre_completo AS paciente, vp.num_expediente,
               ps.pagado, ps.saldo_actual,
               (SELECT COUNT(*) FROM plan_tratamiento_detalle d
                 WHERE d.id_plan_tratamiento = pt.id_plan_tratamiento) AS conceptos,
               (SELECT COUNT(*) FROM plan_tratamiento_detalle d
                 WHERE d.id_plan_tratamiento = pt.id_plan_tratamiento
                   AND d.estado_avance = 2) AS terminados
          FROM plan_tratamiento pt
          JOIN estado_plan ep ON ep.id_estado_plan = pt.id_estado_plan
          JOIN usuario u ON u.id_usuario = pt.id_usuario_dentista
          JOIN v_paciente vp ON vp.id_registro_paciente = pt.id_registro_paciente
          LEFT JOIN v_plan_saldo ps
                 ON ps.id_plan_tratamiento = pt.id_plan_tratamiento
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY pt.fecha DESC, pt.id_plan_tratamiento DESC';

Respuesta::ok(Conexion::todos($sql, $params));
