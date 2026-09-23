<?php
/**
 * Alta y modificacion de una consulta.
 *
 * Una consulta cerrada ya no se edita: es un registro clinico y
 * cambiarlo despues quitaria el sentido de haberlo firmado. Lo que si
 * se puede seguir haciendo es agregarle notas de evolucion y subir sus
 * documentos, que son anexos y no correcciones.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.crear');

$idConsulta   = paramInt('id_consulta');
$idExpediente = paramInt('id_registro_paciente');
$idPlan       = paramInt('id_plan_tratamiento') ?: null;
$idCita       = paramInt('id_cita') ?: null;
$idDentista   = paramInt('id_usuario_dentista');
$fechaHora    = trim((string) param('fecha_hora', date('Y-m-d H:i')));

$motivo       = limpiarEspacios(paramMayus('motivo'));
$padecimiento = limpiarEspacios(paramMayus('padecimiento_actual'));
$exploracion  = limpiarEspacios(paramMayus('exploracion'));
$diagnostico  = limpiarEspacios(paramMayus('diagnostico'));
$indicaciones = limpiarEspacios(paramMayus('plan_indicaciones'));

$presion     = limpiarEspacios(paramMayus('presion_arterial'));
$frecuencia  = paramInt('frecuencia_cardiaca') ?: null;
$temperatura = param('temperatura');
$temperatura = ($temperatura === null || $temperatura === '')
    ? null : round((float) $temperatura, 1);

$errores = [];
if (!$idExpediente) { $errores['id_registro_paciente'] = 'Falta el paciente.'; }
if (!$idDentista)   { $errores['id_usuario_dentista']  = 'Selecciona el dentista.'; }
if ($motivo === null || $motivo === '') {
    $errores['motivo'] = 'Captura el motivo de la consulta.';
}

// La fecha llega del campo datetime-local: "2026-09-19T10:30".
$fechaHora = str_replace('T', ' ', $fechaHora);
if (strlen($fechaHora) === 16) { $fechaHora .= ':00'; }
$f = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHora);
if (!$f || $f->format('Y-m-d H:i:s') !== $fechaHora) {
    $errores['fecha_hora'] = 'La fecha y hora no son validas.';
}

// Un signo vital fuera de rango casi siempre es un dedazo, y en un
// expediente clinico un dedazo se lee como un dato real.
if ($frecuencia !== null && ($frecuencia < 20 || $frecuencia > 250)) {
    $errores['frecuencia_cardiaca'] = 'Revisa la frecuencia cardiaca.';
}
if ($temperatura !== null && ($temperatura < 30 || $temperatura > 45)) {
    $errores['temperatura'] = 'Revisa la temperatura.';
}

if ($errores) { Respuesta::validacion($errores); }

// El plan tiene que ser de este paciente.
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

// La cita tiene que ser de este paciente y no estar ya usada por otra
// consulta: una cita produce una consulta, no dos.
if ($idCita !== null) {
    $c = Conexion::uno(
        'SELECT c.id_cita,
                (SELECT COUNT(*) FROM consulta x
                  WHERE x.id_cita = c.id_cita AND x.estatus = 1
                    AND x.id_consulta <> :actual) AS usada
           FROM cita c
          WHERE c.id_cita = :c AND c.id_registro_paciente = :r',
        [':c' => $idCita, ':r' => $idExpediente, ':actual' => $idConsulta ?: 0]
    );

    if ($c === null || (int) $c['usada'] > 0) {
        $idCita = null;
    }
}

$campos = [
    ':cita' => $idCita,
    ':d'    => $idDentista,   ':f'    => $fechaHora,
    ':mo'   => $motivo,       ':pa'   => $padecimiento,
    ':ex'   => $exploracion,  ':di'   => $diagnostico,
    ':pl'   => $indicaciones,
    ':pre'  => $presion,      ':fc'   => $frecuencia,
    ':temp' => $temperatura,  ':plan' => $idPlan,
];

if ($idConsulta) {
    $actual = Conexion::uno(
        'SELECT cerrada, id_registro_paciente FROM consulta
          WHERE id_consulta = :c AND estatus = 1',
        [':c' => $idConsulta]
    );

    if ($actual === null) {
        Respuesta::error('La consulta no existe.', 404);
    }
    if ((int) $actual['cerrada'] === 1) {
        Respuesta::error(
            'Esta consulta ya esta cerrada y no se puede modificar. '
            . 'Agrega una nota de evolucion si necesitas aclarar algo.');
    }

    Conexion::consulta(
        'UPDATE consulta
            SET id_usuario_dentista = :d, fecha_hora = :f,
                id_plan_tratamiento = :plan, id_cita = :cita,
                motivo = :mo, padecimiento_actual = :pa, exploracion = :ex,
                diagnostico = :di, plan_indicaciones = :pl,
                presion_arterial = :pre, frecuencia_cardiaca = :fc,
                temperatura = :temp
          WHERE id_consulta = :c',
        $campos + [':c' => $idConsulta]
    );

    $folio  = (string) Conexion::valor(
        'SELECT folio FROM consulta WHERE id_consulta = :c', [':c' => $idConsulta]);
    $accion = 'CAMBIO';
} else {
    $folio = siguienteFolio('consulta', 'folio', 'CN');

    Conexion::consulta(
        'INSERT INTO consulta
            (id_registro_paciente, folio, id_plan_tratamiento, id_cita,
             id_usuario_dentista, fecha_hora, motivo, padecimiento_actual,
             exploracion, diagnostico, plan_indicaciones,
             presion_arterial, frecuencia_cardiaca, temperatura)
         VALUES (:r, :fo, :plan, :cita, :d, :f, :mo, :pa, :ex, :di, :pl,
                 :pre, :fc, :temp)',
        $campos + [':r' => $idExpediente, ':fo' => $folio]
    );

    $idConsulta = Conexion::ultimoId();
    $accion     = 'ALTA';
}

// Los documentos siguen a su consulta: si se cambia el plan al que
// pertenece, sus papeles se mueven con ella dentro del expediente.
Conexion::consulta(
    'UPDATE documento SET id_plan_tratamiento = :p WHERE id_consulta = :c',
    [':p' => $idPlan, ':c' => $idConsulta]
);

Bitacora::registrar('consultas', $accion, 'consulta', (string) $idConsulta, [
    'expediente' => $idExpediente,
    'plan'       => $idPlan,
]);

Respuesta::ok(
    ['id_consulta' => $idConsulta, 'folio' => $folio],
    $accion === 'ALTA' ? 'Consulta registrada.' : 'Consulta actualizada.'
);
