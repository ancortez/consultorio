<?php
/**
 * Decide si un paciente lleva tutor o responde por si mismo.
 *
 * Es su propio endpoint y no parte de actualizar_identificacion.php
 * porque se llama desde otro sitio y en otro momento: al ABRIR la ficha
 * de alguien que acaba de cumplir anios, sin que nadie haya tocado el
 * formulario. Meterlo alla obligaria a mandar todos los datos
 * generales para contestar una pregunta de si o no, y cualquier campo
 * que llegara vacio se guardaria vacio.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idExpediente  = paramInt('id_registro_paciente');
$requiereTutor = param('requiere_tutor');

if (!$idExpediente) { Respuesta::error('Falta el expediente.'); }

if ($requiereTutor === null || !in_array((string) $requiereTutor, ['0', '1'], true)) {
    Respuesta::error('Hay que decir si lleva tutor o no.');
}

// La edad se calcula aqui, del dato guardado. Mandarla desde el
// navegador seria dejar que el cliente decida a que edad se tomo la
// decision, y de esa edad depende cuando se vuelve a preguntar.
$edad = Conexion::valor(
    'SELECT TIMESTAMPDIFF(YEAR, p.fecha_nacim, CURDATE())
       FROM registro_paciente rp
       JOIN persona p ON p.id_persona = rp.id_persona
      WHERE rp.id_registro_paciente = :r',
    [':r' => $idExpediente]
);

Tutoria::decidir($idExpediente, (int) $requiereTutor === 1,
    $edad !== null ? (int) $edad : null);

Respuesta::ok(
    ['requiere_tutor' => (int) $requiereTutor],
    (int) $requiereTutor === 1
        ? 'El paciente sigue con tutor.'
        : 'El paciente responde por si mismo.'
);
