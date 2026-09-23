<?php
/**
 * Registra una salida de dinero.
 *
 * Sin egresos el corte nunca cuadra y se acaba llevando el control en
 * un cuaderno, que es justo lo que se quiere evitar.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.egreso');

$idCorte    = paramInt('id_corte_caja');
$idConcepto = paramInt('id_concepto');
$idForma    = paramInt('id_forma_pago') ?: 1;
$monto      = round((float) str_replace(',', '', (string) param('monto', '0')), 2);
$desc       = limpiarEspacios(paramMayus('descripcion'));

$errores = [];
if (!$idCorte)    { $errores['id_corte_caja'] = 'Falta el turno.'; }
if (!$idConcepto) { $errores['id_concepto']   = 'Elige el concepto.'; }
if ($monto <= 0)  { $errores['monto']         = 'Captura el importe.'; }
if ($desc === null || $desc === '') {
    $errores['descripcion'] = 'Escribe de que fue el gasto.';
}
if ($errores) { Respuesta::validacion($errores); }

$turno = Conexion::uno(
    'SELECT abierto FROM corte_caja WHERE id_corte_caja = :c',
    [':c' => $idCorte]
);
if ($turno === null || (int) $turno['abierto'] === 0) {
    Respuesta::error('Ese turno ya esta cerrado.');
}

$concepto = Conexion::uno(
    'SELECT naturaleza FROM concepto_movimiento
      WHERE id_concepto = :c AND estatus = 1',
    [':c' => $idConcepto]
);
if ($concepto === null) {
    Respuesta::error('Ese concepto no existe.');
}

// El concepto manda sobre la naturaleza: "Compra de material" nunca
// puede registrarse como ingreso por un descuido de la pantalla.
$id = Caja::movimiento($idCorte, $idConcepto, $idForma,
    $concepto['naturaleza'], $monto, $desc);

Bitacora::registrar('cajas',
    $concepto['naturaleza'] === 'E' ? 'EGRESO' : 'INGRESO',
    'movimiento_caja', (string) $id,
    ['corte' => $idCorte, 'monto' => $monto]);

Respuesta::ok(['id_movimiento' => $id],
    $concepto['naturaleza'] === 'E' ? 'Egreso registrado.' : 'Ingreso registrado.');
