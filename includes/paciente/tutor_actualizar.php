<?php
/**
 * Guarda los datos de identificacion de un tutor ya registrado.
 * Los contactos y domicilios se guardan con los endpoints que ya
 * existen, porque son exactamente los mismos que los del paciente.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.editar');

$idVinculo    = paramInt('id_expediente_tutor');
$idPersona    = paramInt('id_persona');
$nombres      = nombreLimpio('nombres');
$paterno      = nombreLimpio('ap_paterno');
$materno      = nombreLimpio('ap_materno');
$nacim        = trim((string) param('fecha_nacim', '')) ?: null;
$genero       = paramInt('id_genero');
$rfc          = paramMayus('rfc');
$idOcupacion  = paramInt('id_ocupacion');
$idParentesco = paramInt('id_parentesco');
$responsable  = param('es_responsable_pago') ? 1 : 0;

// Identificacion oficial
$idTipoIdent  = paramInt('id_tipo_identificacion');
$numIdent     = limpiarEspacios(paramMayus('numero_identificacion'));
$emision      = trim((string) param('fecha_emision', '')) ?: null;
$vigencia     = trim((string) param('fecha_vigencia', '')) ?: null;

$errores = [];
if (!$idPersona)        { Respuesta::error('Falta el identificador del tutor.'); }
if ($nombres === null)  { $errores['nombres']       = 'Captura el nombre.'; }
if ($paterno === null)  { $errores['ap_paterno']    = 'Captura el apellido paterno.'; }
if (!$idParentesco)     { $errores['id_parentesco'] = 'Selecciona el parentesco.'; }
if ($rfc !== null && !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc)) {
    $errores['rfc'] = 'El RFC no tiene el formato correcto.';
}
if ($nacim !== null) {
    $d = DateTime::createFromFormat('Y-m-d', $nacim);
    if (!$d || $d > new DateTime('today')) {
        $errores['fecha_nacim'] = 'Fecha invalida.';
    }
}

// La identificacion es opcional, pero si eligen tipo hace falta el numero.
if ($idTipoIdent && $numIdent === null) {
    $errores['numero_identificacion'] = 'Captura el numero de la identificacion.';
}
if ($emision !== null && $vigencia !== null && $vigencia < $emision) {
    $errores['fecha_vigencia'] = 'La vigencia no puede ser anterior a la emision.';
}

if ($errores) { Respuesta::validacion($errores); }

$pdo = bd();
try {
    $pdo->beginTransaction();

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

    if ($idVinculo) {
        Conexion::consulta(
            'UPDATE expediente_tutor
                SET id_parentesco = :pa, es_responsable_pago = :resp
              WHERE id_expediente_tutor = :id',
            [':pa' => $idParentesco, ':resp' => $responsable, ':id' => $idVinculo]
        );
    }

    // ---------- Identificacion oficial ----------
    $identNueva = false;

    if ($idTipoIdent && $numIdent !== null) {
        $actual = Conexion::uno(
            'SELECT id_persona_identificacion, id_tipo_identificacion,
                    numero_identificacion
               FROM persona_identificacion
              WHERE id_persona = :p AND estatus = 1
              ORDER BY id_persona_identificacion DESC LIMIT 1',
            [':p' => $idPersona]
        );

        $cambio = $actual === null
            || (int) $actual['id_tipo_identificacion'] !== $idTipoIdent
            || $actual['numero_identificacion'] !== $numIdent;

        if ($actual === null) {
            Conexion::consulta(
                'INSERT INTO persona_identificacion
                    (id_persona, id_tipo_identificacion, numero_identificacion,
                     fecha_emision, fecha_vigencia)
                 VALUES (:p, :t, :n, :em, :vi)',
                [':p' => $idPersona, ':t' => $idTipoIdent, ':n' => $numIdent,
                 ':em' => $emision, ':vi' => $vigencia]
            );
        } else {
            Conexion::consulta(
                'UPDATE persona_identificacion
                    SET id_tipo_identificacion = :t, numero_identificacion = :n,
                        fecha_emision = :em, fecha_vigencia = :vi
                  WHERE id_persona_identificacion = :id',
                [':t' => $idTipoIdent, ':n' => $numIdent,
                 ':em' => $emision, ':vi' => $vigencia,
                 ':id' => $actual['id_persona_identificacion']]
            );
        }

        // Solo se pide escanear cuando la credencial cambio de verdad.
        // Corregir una fecha mal tecleada no deberia generar papeleo.
        $identNueva = $cambio;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[TUTOR/ACTUALIZAR] ' . $e->getMessage());
    Respuesta::error('No se pudieron guardar los cambios del tutor.', 500);
}

// El pendiente se crea fuera de la transaccion: si algo falla al
// crearlo, los datos del tutor ya quedaron guardados igual.
$pendienteCreado = false;
if ($identNueva && $idExpediente = paramInt('id_registro_paciente')) {
    $pendienteCreado = Documentos::crearPendiente(
        $idExpediente,
        'IDENTIFICACION_TUTOR',
        'IDENTIFICACION DE ' . $nombres . ' ' . $paterno,
        'persona',
        $idPersona
    ) !== null;
}

Bitacora::registrar('paciente', 'CAMBIO', 'persona', (string) $idPersona,
    ['tipo' => 'tutor', 'identificacion' => $identNueva]);

Respuesta::ok(
    ['pendiente_identificacion' => $pendienteCreado],
    $pendienteCreado
        ? 'Datos guardados. Queda pendiente escanear la identificacion.'
        : 'Datos del tutor actualizados.'
);
