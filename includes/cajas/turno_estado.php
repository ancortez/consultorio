<?php
/**
 * Estado actual de las cajas: cual tiene turno abierto y con cuanto.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.ver');

$idCorte = paramInt('id_corte_caja');

if ($idCorte) {
    try {
        Respuesta::ok(
            Caja::resumen($idCorte) + ['movimientos' => Caja::movimientos($idCorte)]
        );
    } catch (Throwable $e) {
        Respuesta::error($e->getMessage(), 404);
    }
}

$cajas = Conexion::todos(
    'SELECT c.id_caja, c.nombre_caja,
            cc.id_corte_caja, cc.fecha_apertura, cc.monto_inicial,
            u.nombre_completo AS abrio
       FROM caja c
       LEFT JOIN corte_caja cc
              ON cc.id_caja = c.id_caja AND cc.abierto = 1
       LEFT JOIN usuario u ON u.id_usuario = cc.id_usuario_apertura
      WHERE c.estatus = 1
      ORDER BY c.id_caja'
);

// A cada caja abierta se le suma lo que lleva del turno, para que la
// pantalla diga algo util sin tener que entrar a cada una.
foreach ($cajas as &$c) {
    if ($c['id_corte_caja'] === null) { continue; }
    $r = Caja::resumen((int) $c['id_corte_caja']);
    $c['total_ingresos']    = $r['total_ingresos'];
    $c['total_egresos']     = $r['total_egresos'];
    $c['efectivo_esperado'] = $r['efectivo_esperado'];
}
unset($c);

Respuesta::ok($cajas);
