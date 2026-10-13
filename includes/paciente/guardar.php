<?php
/**
 * Alta de paciente.
 *
 * Crea persona + registro_paciente (+ telefono si se capturo) dentro de
 * una transaccion: o se crean las tres cosas, o no se crea ninguna. En
 * el modelo original persona exigia id_domicilio NOT NULL, lo que hacia
 * imposible dar de alta a alguien sin capturar antes su domicilio.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.crear');

// Todo lo que teclea el usuario entra en MAYUSCULAS.
$nombres = nombreLimpio('nombres');
$paterno = nombreLimpio('ap_paterno');
$materno = nombreLimpio('ap_materno');
$nacim   = trim((string) param('fecha_nacim', '')) ?: null;
$genero  = paramInt('id_genero');
$tel     = preg_replace('/\D/', '', (string) param('telefono', ''));

// ---------- Validacion ----------
$errores = [];
if ($nombres === null) { $errores['nombres']    = 'Captura el nombre.'; }
if ($paterno === null) { $errores['ap_paterno'] = 'Captura el apellido paterno.'; }

if ($nacim !== null) {
    $d = DateTime::createFromFormat('Y-m-d', $nacim);
    if (!$d || $d->format('Y-m-d') !== $nacim) {
        $errores['fecha_nacim'] = 'Fecha invalida.';
    } elseif ($d > new DateTime('today')) {
        $errores['fecha_nacim'] = 'La fecha no puede ser futura.';
    }
}

if ($tel !== '' && strlen($tel) < 10) {
    $errores['telefono'] = 'El telefono debe tener 10 digitos.';
}

if ($errores) {
    Respuesta::validacion($errores);
}

// ---------- Alta ----------
$pdo = bd();
try {
    $pdo->beginTransaction();

    // Los menores traen su ocupacion resuelta sola: NINGUNA antes de los
    // 3 anios y ESTUDIANTE de los 3 a los 15. De 16 en adelante se deja
    // vacia para que la capturen en el detalle del paciente.
    $idOcupacion = ocupacionPorEdad($nacim);

    Conexion::consulta(
        'INSERT INTO persona
            (nombres, ap_paterno, ap_materno, fecha_nacim, id_genero,
             id_tipo_persona, id_ocupacion)
         VALUES (:n, :ap, :am, :fn, :g, 1, :ocu)',
        [':n' => $nombres, ':ap' => $paterno, ':am' => $materno,
         ':fn' => $nacim, ':g' => $genero, ':ocu' => $idOcupacion]
    );
    $idPersona = Conexion::ultimoId();

    $folio = siguienteFolio('registro_paciente', 'num_expediente',
        (string) parametro('prefijo_expediente', 'EXP'));

    /*
       Si el paciente nace en el sistema siendo mayor de edad, la
       pregunta se contesta en el alta.

       Un MENOR entra con requiere_tutor = 1 directamente, sin
       preguntar: no hay nada que decidir, lo dice la ley. Y entra ya
       decidido, con su edad apuntada, para que al cumplir 18 la regla
       no lo trate como "nunca se pregunto" sino como lo que es: un
       expediente cuya situacion toca revisar.
    */
    $edadAlta = $nacim !== null
        ? (new DateTime($nacim))->diff(new DateTime('today'))->y
        : null;

    $tutorAlta = param('requiere_tutor');
    if ($edadAlta !== null && $edadAlta < Tutoria::MAYORIA_EDAD) {
        $tutorAlta = 1;          // menor: lleva tutor y punto
    } elseif ($tutorAlta === '' ) {
        $tutorAlta = null;       // mayor y no contestaron: se preguntara
    } elseif ($tutorAlta !== null) {
        $tutorAlta = (int) $tutorAlta;
    }

    Conexion::consulta(
        'INSERT INTO registro_paciente
            (num_expediente, id_persona, fecha_apertura,
             requiere_tutor, tutor_decidido_edad)
         VALUES (:f, :p, CURDATE(), :rt, :te)',
        [':f' => $folio, ':p' => $idPersona,
         ':rt' => $tutorAlta,
         // Sin decision no hay edad de decision: guardar una seria
         // decir que se decidio algo que nadie decidio.
         ':te' => $tutorAlta === null ? null : $edadAlta]
    );
    $idExpediente = Conexion::ultimoId();

    if ($tel !== '') {
        Conexion::consulta(
            'INSERT INTO contacto_persona
                (id_persona, id_medio_contacto, valor_contacto, es_principal)
             VALUES (:p, 1, :v, 1)',
            [':p' => $idPersona, ':v' => $tel]
        );
    }

    // El expediente nace con sus pendientes de digitalizacion: la
    // fotografia, la historia clinica y el consentimiento. Que el
    // sistema los apunte solo evita depender de que alguien se acuerde.
    $pendientes = Documentos::pendientesDeAlta($idExpediente);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[PACIENTE/GUARDAR] ' . $e->getMessage());
    Respuesta::error('No se pudo guardar el paciente. Intenta de nuevo.', 500);
}

Bitacora::registrar('paciente', 'ALTA', 'registro_paciente', (string) $idExpediente, [
    'num_expediente' => $folio,
    'nombre'         => nombreCompleto($nombres, $paterno, $materno),
]);

Respuesta::ok([
    'id_registro_paciente' => $idExpediente,
    'id_persona'           => $idPersona,
    'num_expediente'       => $folio,
    'pendientes'           => $pendientes,
], 'Paciente registrado correctamente.');
