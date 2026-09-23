<?php
/**
 * Cierra una consulta.
 *
 * Cerrar es lo que convierte la captura en un registro clinico: a
 * partir de ahi no se edita. Por eso se exige que tenga lo minimo para
 * valer como tal.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.crear');

$idConsulta = paramInt('id_consulta');
if (!$idConsulta) {
    Respuesta::error('Falta la consulta.');
}

$c = Conexion::uno(
    'SELECT id_consulta, cerrada, motivo, diagnostico
       FROM consulta WHERE id_consulta = :c AND estatus = 1',
    [':c' => $idConsulta]
);

if ($c === null) {
    Respuesta::error('La consulta no existe.', 404);
}
if ((int) $c['cerrada'] === 1) {
    Respuesta::error('Esta consulta ya estaba cerrada.');
}

if (empty($c['diagnostico'])) {
    Respuesta::error(
        'Captura el diagnostico antes de cerrar la consulta. '
        . 'Una consulta sin diagnostico no sirve como registro clinico.');
}

Conexion::consulta(
    'UPDATE consulta SET cerrada = 1, fecha_cierre = NOW()
      WHERE id_consulta = :c',
    [':c' => $idConsulta]
);

Bitacora::registrar('consultas', 'CIERRA', 'consulta', (string) $idConsulta);

Respuesta::ok(['id_consulta' => $idConsulta], 'Consulta cerrada.');
