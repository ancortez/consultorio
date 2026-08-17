<?php
/**
 * Desvincula al tutor del expediente (estatus = 0).
 * El registro de la persona se conserva intacto.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$id = paramInt('id_expediente_tutor');
if (!$id) { Respuesta::error('Falta el identificador del tutor.'); }

Conexion::consulta(
    'UPDATE expediente_tutor SET estatus = 0 WHERE id_expediente_tutor = :id',
    [':id' => $id]
);

Bitacora::registrar('paciente', 'BAJA', 'expediente_tutor', (string) $id);
Respuesta::ok(null, 'Tutor desvinculado.');
