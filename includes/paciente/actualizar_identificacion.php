<?php
/**
 * Guarda la pestania Identificacion del detalle del paciente.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idPersona    = paramInt('id_persona');
$idExpediente = paramInt('id_registro_paciente');
$nombres = nombreLimpio('nombres');
$paterno = nombreLimpio('ap_paterno');
$materno = nombreLimpio('ap_materno');
$nacim   = trim((string) param('fecha_nacim', '')) ?: null;
$genero  = paramInt('id_genero');
$rfc     = paramMayus('rfc');
$idOcupacion = paramInt('id_ocupacion');

/*
   "Requiere tutor" viaja con los demas datos generales, pero NO se
   escribe como los demas: pasa por Tutoria::decidir(), que ademas
   apunta la edad a la que se decidio. Esa edad es lo que evita volver
   a preguntar en cada visita a partir de los 24.

   Una cadena vacia significa "sin definir" y se deja tal cual: es
   distinto de "no lleva tutor", y confundirlos haria desaparecer el
   bloque del tutor de un expediente que nadie ha revisado.
*/
$tutorCrudo = param('requiere_tutor');
$requiereTutor = ($tutorCrudo === null || $tutorCrudo === '')
    ? null : (int) $tutorCrudo;

$errores = [];
if (!$idPersona)      { Respuesta::error('Falta el identificador del paciente.'); }
if ($nombres === null) { $errores['nombres']    = 'Captura el nombre.'; }
if ($paterno === null) { $errores['ap_paterno'] = 'Captura el apellido paterno.'; }

if ($nacim !== null) {
    $d = DateTime::createFromFormat('Y-m-d', $nacim);
    if (!$d || $d > new DateTime('today')) {
        $errores['fecha_nacim'] = 'Fecha invalida.';
    }
}
if ($rfc !== null && !rfcConForma($rfc)) {
    $errores['rfc'] = 'El RFC no tiene el formato correcto. Con o sin homoclave: '
                      . 'COMA861125 o COMA8611257K1.';
}
if ($errores) {
    Respuesta::validacion($errores);
}

$pdo = bd();
try {
    $pdo->beginTransaction();

    // Si no mandaron ocupacion y la edad la define sola, se pone aqui.
    if ($idOcupacion === null) {
        $idOcupacion = ocupacionPorEdad($nacim);
    }

    Conexion::consulta(
        'UPDATE persona
            SET nombres = :n, ap_paterno = :ap, ap_materno = :am,
                fecha_nacim = :fn, id_genero = :g, rfc = :rfc,
                id_ocupacion = :ocu
          WHERE id_persona = :id',
        [':n' => $nombres, ':ap' => $paterno, ':am' => $materno,
         ':fn' => $nacim, ':g' => $genero, ':rfc' => $rfc,
         ':ocu' => $idOcupacion, ':id' => $idPersona]
    );

    if ($requiereTutor !== null && $idExpediente) {
        // La edad se recalcula del dato que se acaba de guardar, no del
        // que traia la pantalla: si en el mismo guardado corrigieron la
        // fecha de nacimiento, la edad buena es la nueva.
        $edad = $nacim !== null
            ? (new DateTime($nacim))->diff(new DateTime('today'))->y
            : null;

        Tutoria::decidir($idExpediente, $requiereTutor === 1, $edad);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[PACIENTE/IDENTIFICACION] ' . $e->getMessage());
    Respuesta::error('No se pudieron guardar los cambios.', 500);
}

Bitacora::registrar('paciente', 'CAMBIO', 'persona', (string) $idPersona, [
    'nombre' => nombreCompleto($nombres, $paterno, $materno),
]);

Respuesta::ok(null, 'Datos actualizados.');
