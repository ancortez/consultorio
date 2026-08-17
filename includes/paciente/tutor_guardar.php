<?php
/**
 * Alta de un tutor nuevo y su vinculo con el expediente.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idExpediente = paramInt('id_registro_paciente');
$nombres      = nombreLimpio('nombres');
$paterno      = nombreLimpio('ap_paterno');
$materno      = nombreLimpio('ap_materno');
$rfc          = paramMayus('rfc');
$idOcupacion  = paramInt('id_ocupacion');
$idParentesco = paramInt('id_parentesco');
$tel          = preg_replace('/\D/', '', (string) param('telefono', ''));
$responsable  = param('es_responsable_pago') ? 1 : 0;

$errores = [];
if (!$idExpediente)     { Respuesta::error('Falta el expediente del paciente.'); }
if ($nombres === null)  { $errores['nombres']       = 'Captura el nombre.'; }
if ($paterno === null)  { $errores['ap_paterno']    = 'Captura el apellido paterno.'; }
if (!$idParentesco)     { $errores['id_parentesco'] = 'Selecciona el parentesco.'; }
if ($rfc !== null && !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc)) {
    $errores['rfc'] = 'El RFC no tiene el formato correcto.';
}
if ($tel !== '' && strlen($tel) < 10) {
    $errores['telefono'] = 'El telefono debe tener 10 digitos.';
}
if ($errores) { Respuesta::validacion($errores); }

$pdo = bd();
try {
    $pdo->beginTransaction();

    Conexion::consulta(
        'INSERT INTO persona
            (nombres, ap_paterno, ap_materno, id_tipo_persona, rfc, id_ocupacion)
         VALUES (:n, :ap, :am, 2, :rfc, :ocu)',
        [':n' => $nombres, ':ap' => $paterno, ':am' => $materno,
         ':rfc' => $rfc, ':ocu' => $idOcupacion]
    );
    $idPersona = Conexion::ultimoId();

    Conexion::consulta(
        'INSERT INTO expediente_tutor
            (id_registro_paciente, id_persona, id_parentesco, es_responsable_pago)
         VALUES (:r, :p, :pa, :resp)',
        [':r' => $idExpediente, ':p' => $idPersona,
         ':pa' => $idParentesco, ':resp' => $responsable]
    );

    if ($tel !== '') {
        Conexion::consulta(
            'INSERT INTO contacto_persona
                (id_persona, id_medio_contacto, valor_contacto, es_principal)
             VALUES (:p, 1, :v, 1)',
            [':p' => $idPersona, ':v' => $tel]
        );
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[TUTOR/GUARDAR] ' . $e->getMessage());
    Respuesta::error('No se pudo guardar el tutor.', 500);
}

Bitacora::registrar('paciente', 'ALTA', 'persona', (string) $idPersona, [
    'tipo'       => 'tutor',
    'expediente' => $idExpediente,
]);

Respuesta::ok(['id_persona' => $idPersona], 'Tutor registrado.');
