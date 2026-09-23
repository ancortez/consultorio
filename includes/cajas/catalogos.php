<?php
/**
 * Catalogos que necesita la pantalla de caja.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.ver');

Respuesta::ok([
    'cajas'     => Conexion::todos(
        'SELECT id_caja, nombre_caja FROM caja WHERE estatus = 1 ORDER BY id_caja'),
    'formas'    => Conexion::todos(
        'SELECT id_forma_pago, nombre_forma, afecta_efectivo
           FROM forma_pago WHERE estatus = 1 ORDER BY id_forma_pago'),
    'conceptos' => Conexion::todos(
        'SELECT id_concepto, nombre_concepto, naturaleza
           FROM concepto_movimiento WHERE estatus = 1
          ORDER BY naturaleza DESC, nombre_concepto'),
    'permisos'  => [
        'cobrar'    => Auth::puede('cajas.cobrar'),
        'egreso'    => Auth::puede('cajas.egreso'),
        'corte'     => Auth::puede('cajas.corte'),
        'cancelar'  => Auth::puede('cajas.cancelar'),
        'descuento' => Auth::puede('cajas.descuento'),
    ],
]);
