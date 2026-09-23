<?php
/**
 * Turnos ya cerrados, para volver a imprimir un corte.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.ver');

$idCaja = paramInt('id_caja');
$desde  = trim((string) param('desde', date('Y-m-d', strtotime('-30 days'))));
$hasta  = trim((string) param('hasta', date('Y-m-d')));

$where  = ['DATE(cc.fecha_apertura) BETWEEN :d AND :h'];
$params = [':d' => $desde, ':h' => $hasta];

if ($idCaja) {
    $where[]      = 'cc.id_caja = :c';
    $params[':c'] = $idCaja;
}

Respuesta::ok(Conexion::todos(
    'SELECT cc.*, c.nombre_caja,
            ua.nombre_completo AS abrio, uc.nombre_completo AS cerro
       FROM corte_caja cc
       JOIN caja c ON c.id_caja = cc.id_caja
       JOIN usuario ua ON ua.id_usuario = cc.id_usuario_apertura
       LEFT JOIN usuario uc ON uc.id_usuario = cc.id_usuario_cierre
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY cc.fecha_apertura DESC
      LIMIT 100',
    $params
));
