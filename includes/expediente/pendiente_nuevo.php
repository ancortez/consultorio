<?php
/**
 * Agrega un pendiente a mano, para documentos que no nacen solos
 * (una radiografia, una receta, un estudio externo).
 *
 * Si viene id_plan_tratamiento, el documento cuelga de ese plan y ahi
 * aparece en el expediente. Sin plan queda en el bloque general.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idExpediente = paramInt('id_registro_paciente');
$clave        = (string) param('clave', '');
$descripcion  = limpiarEspacios(paramMayus('descripcion'));
$idPlan       = paramInt('id_plan_tratamiento') ?: null;

if (!$idExpediente || $clave === '') {
    Respuesta::error('Faltan datos para crear el pendiente.');
}

// El plan tiene que ser de este paciente. Sin esta revision, cambiando
// el numero a mano se podrian colgar documentos del expediente de otro.
if ($idPlan !== null) {
    $suyo = (int) Conexion::valor(
        'SELECT COUNT(*) FROM plan_tratamiento
          WHERE id_plan_tratamiento = :p AND id_registro_paciente = :r',
        [':p' => $idPlan, ':r' => $idExpediente]
    );
    if ($suyo === 0) {
        Respuesta::error('Ese plan de tratamiento no es de este paciente.');
    }
}

$id = Documentos::crearPendiente(
    $idExpediente, $clave, $descripcion, null, null, $idPlan);

if ($id === null) {
    Respuesta::error('Ese documento ya est&aacute; en el expediente.');
}

Respuesta::ok(['id_documento' => $id], 'Pendiente agregado.');
