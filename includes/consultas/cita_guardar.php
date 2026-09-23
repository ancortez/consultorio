<?php
/**
 * Alta y reagendado de citas.
 *
 * Las citas nacen con estatus Pendiente: se aparto el horario, pero
 * todavia no se ha hablado con el paciente. Confirmarlas es un paso
 * aparte que se hace desde el tablero de inicio, cuando se le llama.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.agenda');

const ESTADO_PENDIENTE  = 1;
const ESTADO_REAGENDADA = 7;

$idCita       = paramInt('id_cita');
$idExpediente = paramInt('id_registro_paciente');
$idDentista   = paramInt('id_usuario_dentista');
$idSala       = paramInt('id_sala');
$fecha        = trim((string) param('fecha', ''));
$hora         = trim((string) param('hora', ''));
$idProc       = paramInt('id_procedimiento') ?: null;
$duracion     = paramInt('duracion_min') ?? Agenda::BLOQUE;
$motivo       = limpiarEspacios(paramMayus('motivo'));
$notas        = limpiarEspacios(paramMayus('notas'));

// La duracion sale del procedimiento previsto. Apartar media hora
// para todo es lo que hace que una agenda se retrase desde la tercera
// cita: una resina y una revision no ocupan el sillon igual.
if ($idProc !== null) {
    $proc = Conexion::uno(
        'SELECT nombre_procedimiento, duracion_min FROM procedimiento
          WHERE id_procedimiento = :p AND estatus = 1',
        [':p' => $idProc]
    );

    if ($proc === null) {
        $idProc = null;
    } else {
        // Se respeta lo que haya capturado quien agenda: el catalogo
        // propone, no impone. Solo se usa cuando no vino nada.
        if (!paramInt('duracion_min') && (int) $proc['duracion_min'] > 0) {
            // Se redondea hacia arriba al bloque de media hora: la
            // agenda trabaja en bloques y una cita de 40 minutos ocupa
            // dos, no uno y un tercio.
            $duracion = (int) (ceil((int) $proc['duracion_min'] / Agenda::BLOQUE)
                * Agenda::BLOQUE);
        }
        if ($motivo === null || $motivo === '') {
            $motivo = mb_strtoupper($proc['nombre_procedimiento'], 'UTF-8');
        }
    }
}

$errores = [];
if (!$idExpediente) { $errores['id_registro_paciente'] = 'Selecciona el paciente.'; }
if (!$idDentista)   { $errores['id_usuario_dentista']  = 'Selecciona el dentista.'; }
if ($fecha === '')  { $errores['fecha'] = 'Selecciona la fecha.'; }
if ($hora === '')   { $errores['hora']  = 'Selecciona la hora.'; }
if ($duracion < 15 || $duracion > 480) {
    $errores['duracion_min'] = 'La duracion debe estar entre 15 y 480 minutos.';
}
if ($errores) { Respuesta::validacion($errores); }

// Un dentista solo agenda consigo mismo.
if (!Agenda::puedeAgendarPara($idDentista)) {
    Respuesta::error('Solo puedes agendar citas contigo mismo.', 403);
}

$inicio = "$fecha $hora:00";
$fin    = date('Y-m-d H:i:s', strtotime($inicio) + $duracion * 60);

// La validacion se repite aqui aunque la pantalla ya la hizo: entre que
// se cargaron los espacios y se dio guardar, alguien mas pudo apartar
// ese horario. Es el unico punto donde la comprobacion es confiable.
$choque = Agenda::validarEspacio($inicio, $fin, $idDentista, $idCita ?: null);
if ($choque !== null) {
    Respuesta::error($choque);
}

$pdo = bd();
try {
    $pdo->beginTransaction();

    if ($idCita) {
        // Reagendar: la cita vuelve a Pendiente porque hay que avisarle
        // al paciente del cambio de horario.
        Conexion::consulta(
            'UPDATE cita
                SET id_registro_paciente = :r, id_usuario_dentista = :d,
                    id_sala = :s, id_procedimiento = :proc,
                    fecha_inicio = :ini, fecha_fin = :fin,
                    motivo = :m, notas = :n, id_estado_cita = :e,
                    recordatorio_enviado = 0
              WHERE id_cita = :id',
            [':r' => $idExpediente, ':d' => $idDentista, ':s' => $idSala ?: null,
             ':proc' => $idProc,
             ':ini' => $inicio, ':fin' => $fin, ':m' => $motivo, ':n' => $notas,
             ':e' => ESTADO_PENDIENTE, ':id' => $idCita]
        );
        // Los recordatorios ya hechos dejan de valer: el horario que se
        // le dijo al paciente ya no es el de la cita.
        Conexion::consulta(
            'DELETE FROM cita_recordatorio WHERE id_cita = :c', [':c' => $idCita]);

        $accion = 'REAGENDA';
    } else {
        Conexion::consulta(
            'INSERT INTO cita
                (id_registro_paciente, id_usuario_dentista, id_sala,
                 id_procedimiento, id_estado_cita, fecha_inicio, fecha_fin,
                 motivo, notas, id_usuario_registro)
             VALUES (:r, :d, :s, :proc, :e, :ini, :fin, :m, :n, :u)',
            [':r' => $idExpediente, ':d' => $idDentista, ':s' => $idSala ?: null,
             ':proc' => $idProc,
             ':e' => ESTADO_PENDIENTE, ':ini' => $inicio, ':fin' => $fin,
             ':m' => $motivo, ':n' => $notas, ':u' => Auth::id()]
        );
        $idCita = Conexion::ultimoId();
        $accion = 'ALTA';
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[CITA/GUARDAR] ' . $e->getMessage());
    Respuesta::error('No se pudo guardar la cita.', 500);
}

Bitacora::registrar('consultas', $accion, 'cita', (string) $idCita, [
    'expediente' => $idExpediente,
    'inicio'     => $inicio,
    'dentista'   => $idDentista,
]);

Respuesta::ok(
    ['id_cita' => $idCita, 'fecha_inicio' => $inicio],
    'Cita agendada. Queda pendiente de confirmar con el paciente.'
);
