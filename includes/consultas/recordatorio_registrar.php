<?php
/**
 * Deja constancia de que el recordatorio se hizo.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.agenda');

$idCita    = paramInt('id_cita');
$idRegla   = paramInt('id_regla');
$resultado = paramInt('resultado') ?: 1;
$nota      = limpiarEspacios(paramMayus('nota'));

if (!$idCita || !$idRegla) {
    Respuesta::error('Faltan datos del recordatorio.');
}
// 1 enviado | 2 no se pudo contactar | 3 omitido a proposito
if (!in_array($resultado, [1, 2, 3], true)) { $resultado = 1; }

AgendaExtra::registrarRecordatorio($idCita, $idRegla, $resultado, $nota);

Respuesta::ok(['id_cita' => $idCita], 'Recordatorio registrado.');
