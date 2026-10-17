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
/*
   Octubre de 2026: dos campos mas dejan de llegar de la pantalla.

     padecimiento_actual        se repetia con el motivo. En la
                                practica se escribia lo mismo dos
                                veces, o se dejaba vacio.
     plan_indicaciones          lo que se hizo quedo repartido donde
                                de verdad vive: los hallazgos de la
                                exploracion y las notas de evolucion,
                                que ademas se pueden leer una por una
                                en la siguiente cita.

   Las columnas siguen en la base con lo ya capturado y se siguen
   leyendo e imprimiendo; simplemente ya no se escriben desde aqui.
   Es el mismo trato que se les dio a exploracion y diagnostico.
*/

// Octubre 2026. Tres campos dejaron de llegar de la pantalla:
//
//   exploracion  paso a ser la tabla hallazgo, uno por renglon, para
//                poder colgarle su radiografia a cada observacion.
//   diagnostico  vive en el plan de tratamiento, que es donde se razona.
//   signos vitales  en odontopediatria no se tomaban.
//
// Las columnas siguen en la base con lo ya capturado y se siguen
// leyendo e imprimiendo; simplemente ya no se escriben desde aqui.

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


if ($errores) { Respuesta::validacion($errores); }

// El plan tiene que ser de este paciente.
if ($idPlan !== null) {
    $plan = Conexion::uno(
        'SELECT folio, id_estado_plan FROM plan_tratamiento
          WHERE id_plan_tratamiento = :p AND id_registro_paciente = :r',
        [':p' => $idPlan, ':r' => $idExpediente]
    );
    if ($plan === null) {
        Respuesta::error('Ese plan de tratamiento no es de este paciente.');
    }

    /*
       A un plan cancelado no se le cuelgan consultas.

       Un plan terminado si: la revision de control de un tratamiento
       que se cerro en marzo es una consulta de ESE plan, y mandarla al
       bloque general partiria el expediente del tratamiento en dos.
       El avance automatico ya no lo saca de Terminado.

       Pero cancelado significa que el tratamiento no ocurrio. Una
       consulta dentro de un plan cancelado es una contradiccion en el
       expediente, y pasa por una razon concreta: la pantalla solo
       ofrece planes vivos, asi que si llega un cancelado es porque la
       pestania llevaba un rato abierta cuando alguien lo cancelo
       desde otra. Se avisa con nombre y apellido en vez de guardar
       algo que nadie va a poder explicar.
    */
    if ((int) $plan['id_estado_plan'] === Planes::CANCELADO) {
        Respuesta::error(
            'El plan ' . $plan['folio'] . ' esta cancelado y no puede '
            . 'recibir consultas. Recarga la pantalla y registrala como '
            . 'consulta general o dentro de otro plan.');
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
    ':d'    => $idDentista, ':f'  => $fechaHora,
    ':mo'   => $motivo,
    ':plan' => $idPlan,
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
                motivo = :mo
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
             id_usuario_dentista, fecha_hora, motivo)
         VALUES (:r, :fo, :plan, :cita, :d, :f, :mo)',
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

/*
   El plan arranca con su primera consulta.

   Aceptado significa "el tutor firmo"; En proceso significa "ya se
   esta trabajando". Lo segundo lo prueba un hecho: existe una consulta
   ligada a ese plan.

   Como con la aceptacion, el estado lo mueve algo que ocurrio y no una
   opinion de quien captura. Y solo AVANZA: un plan Terminado no
   retrocede a En proceso porque alguien agregue una consulta tardia
   —una revision de control, por ejemplo—, ni uno Cancelado revive.
*/
if ($idPlan !== null) {
    // Los numeros salen de Planes, no escritos a mano aqui: el 2 y el 3
    // repartidos por los archivos fue exactamente como nacio el fallo
    // de que un plan terminado volviera a En proceso.
    $filas = Conexion::consulta(
        'UPDATE plan_tratamiento
            SET id_estado_plan = :proceso
          WHERE id_plan_tratamiento = :p
            AND id_estado_plan = :aceptado',
        [':p' => $idPlan,
         ':proceso'  => Planes::EN_PROCESO,
         ':aceptado' => Planes::ACEPTADO]
    )->rowCount();

    if ($filas > 0) {
        Bitacora::registrar('consultas', 'PLAN_EN_PROCESO',
            'plan_tratamiento', (string) $idPlan,
            ['motivo' => 'se registro su primera consulta',
             'consulta' => $idConsulta]);
    }
}

Bitacora::registrar('consultas', $accion, 'consulta', (string) $idConsulta, [
    'expediente' => $idExpediente,
    'plan'       => $idPlan,
]);

Respuesta::ok(
    ['id_consulta' => $idConsulta, 'folio' => $folio],
    $accion === 'ALTA' ? 'Consulta registrada.' : 'Consulta actualizada.'
);
