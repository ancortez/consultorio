<?php
/**
 * Lo que debe un paciente: cargos abiertos y planes con saldo.
 *
 * Es lo que la pantalla de cobro necesita para armar el reparto.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('cajas.ver');

$idExpediente = paramInt('id_registro_paciente');
if (!$idExpediente) {
    Respuesta::error('Falta el paciente.');
}

$paciente = Conexion::uno(
    'SELECT id_registro_paciente, nombre_completo, num_expediente, edad,
            id_persona, foto, telefono
       FROM v_paciente WHERE id_registro_paciente = :r',
    [':r' => $idExpediente]
);
if ($paciente === null) {
    Respuesta::error('El paciente no existe.', 404);
}

Respuesta::ok([
    'paciente' => $paciente,
    'cargos'   => Cargos::pendientes($idExpediente),
    'planes'   => Conexion::todos(
        'SELECT vps.*, pt.descripcion, ep.nombre_estado
           FROM v_plan_saldo vps
           JOIN plan_tratamiento pt
                ON pt.id_plan_tratamiento = vps.id_plan_tratamiento
           JOIN estado_plan ep ON ep.id_estado_plan = pt.id_estado_plan
          WHERE vps.id_registro_paciente = :r
          ORDER BY pt.fecha DESC',
        [':r' => $idExpediente]),
    'resumen'  => Conexion::uno(
        'SELECT * FROM v_paciente_saldo WHERE id_registro_paciente = :r',
        [':r' => $idExpediente])
        ?? ['cargado' => 0, 'pagado' => 0, 'saldo' => 0, 'cargos_abiertos' => 0],
]);
