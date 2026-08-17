<?php
/**
 * Vincula un tutor ya existente al expediente actual.
 * Este caso no era posible en el modelo original, donde persona apuntaba
 * a un unico id_registro_paciente.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idExpediente = paramInt('id_registro_paciente');
$idPersona    = paramInt('id_persona');
$idParentesco = paramInt('id_parentesco');

if (!$idExpediente || !$idPersona) {
    Respuesta::error('Faltan datos para vincular al tutor.');
}

$yaExiste = (int) Conexion::valor(
    'SELECT COUNT(*) FROM expediente_tutor
      WHERE id_registro_paciente = :r AND id_persona = :p',
    [':r' => $idExpediente, ':p' => $idPersona]
);

if ($yaExiste > 0) {
    // Si estaba dado de baja se reactiva; si ya estaba activo no pasa nada.
    Conexion::consulta(
        'UPDATE expediente_tutor
            SET estatus = 1, id_parentesco = :pa
          WHERE id_registro_paciente = :r AND id_persona = :p',
        [':pa' => $idParentesco, ':r' => $idExpediente, ':p' => $idPersona]
    );
} else {
    Conexion::consulta(
        'INSERT INTO expediente_tutor
            (id_registro_paciente, id_persona, id_parentesco)
         VALUES (:r, :p, :pa)',
        [':r' => $idExpediente, ':p' => $idPersona, ':pa' => $idParentesco]
    );
}

Bitacora::registrar('paciente', 'ALTA', 'expediente_tutor', (string) $idExpediente,
    ['id_persona_tutor' => $idPersona]);

Respuesta::ok(null, 'Tutor vinculado al expediente.');
