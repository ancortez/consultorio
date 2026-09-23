<?php
/**
 * Registra un cargo a un paciente.
 *
 * Es lo que se usa desde la consulta cuando sale algo que no estaba
 * planeado: una extraccion de urgencia, una resina que aparecio al
 * limpiar. Tambien sirve para un cargo suelto (un material, una
 * reposicion) desde el estado de cuenta.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.cargo');

$idExpediente = paramInt('id_registro_paciente');
$idConsulta   = paramInt('id_consulta') ?: null;
$idProc       = paramInt('id_procedimiento') ?: null;
$descripcion  = limpiarEspacios(paramMayus('descripcion'));
$cantidad     = max(1, paramInt('cantidad') ?: 1);
$precio       = round((float) str_replace(',', '', (string) param('precio_unitario', '0')), 2);
$descuento    = round((float) str_replace(',', '', (string) param('descuento', '0')), 2);
$motivoDesc   = limpiarEspacios(paramMayus('motivo_descuento'));

if (!$idExpediente) {
    Respuesta::error('Falta el paciente.');
}

// Con procedimiento del catalogo, la descripcion y el precio salen de
// ahi si no se capturaron: es lo normal y ahorra teclear.
$proc = null;
if ($idProc !== null) {
    $proc = Conexion::uno(
        'SELECT id_procedimiento, nombre_procedimiento, costo_sugerido
           FROM procedimiento WHERE id_procedimiento = :p AND estatus = 1',
        [':p' => $idProc]
    );
    if ($proc === null) {
        Respuesta::error('Ese procedimiento no existe.');
    }
    if ($descripcion === null || $descripcion === '') {
        $descripcion = mb_strtoupper($proc['nombre_procedimiento'], 'UTF-8');
    }
    if ($precio <= 0) {
        $precio = round((float) $proc['costo_sugerido'], 2);
    }
}

$errores = [];
if ($descripcion === null || $descripcion === '') {
    $errores['descripcion'] = 'Escribe de que es el cargo.';
}
if ($precio <= 0) {
    $errores['precio_unitario'] = 'Captura el importe.';
}
if ($descuento < 0) {
    $errores['descuento'] = 'El descuento no puede ser negativo.';
}
if ($descuento > $precio * $cantidad) {
    $errores['descuento'] = 'El descuento no puede pasar del importe.';
}

// El descuento necesita permiso propio. Sin esto, cualquiera que pueda
// registrar un cargo podria regalar el tratamiento.
if ($descuento > 0 && !Auth::puede('cajas.descuento')) {
    $errores['descuento'] =
        'No tienes permiso para autorizar descuentos. Pidelo a quien si lo tiene.';
}
if ($descuento > 0 && ($motivoDesc === null || $motivoDesc === '')) {
    $errores['motivo_descuento'] = 'Di por que se hace el descuento.';
}

if ($errores) { Respuesta::validacion($errores); }

// La consulta tiene que ser de este paciente.
$idPlan = null;
if ($idConsulta !== null) {
    $c = Conexion::uno(
        'SELECT id_consulta, id_plan_tratamiento, id_registro_paciente
           FROM consulta WHERE id_consulta = :c AND estatus = 1',
        [':c' => $idConsulta]
    );
    if ($c === null || (int) $c['id_registro_paciente'] !== $idExpediente) {
        Respuesta::error('Esa consulta no es de este paciente.');
    }
    // Si la consulta pertenece a un plan, el cargo tambien: asi el
    // estado de cuenta del plan sale completo.
    $idPlan = $c['id_plan_tratamiento'] !== null
        ? (int) $c['id_plan_tratamiento'] : null;
}

$idCargo = Cargos::crear([
    'id_registro_paciente' => $idExpediente,
    'id_consulta'          => $idConsulta,
    'id_plan_tratamiento'  => $idPlan,
    'id_procedimiento'     => $idProc,
    'descripcion'          => $descripcion,
    'cantidad'             => $cantidad,
    'precio_unitario'      => $precio,
    'descuento'            => $descuento,
    'motivo_descuento'     => $motivoDesc,
]);

$saldo = Conexion::uno(
    'SELECT * FROM v_cargo_saldo WHERE id_cargo = :c', [':c' => $idCargo]);

Respuesta::ok($saldo, 'Cargo registrado. Ya aparece como pendiente de pago.');
