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
$duracion     = paramInt('duracion_min') ?? Agenda::BLOQUE;
$motivo       = limpiarEspacios(paramMayus('motivo'));
$notas        = limpiarEspacios(paramMayus('notas'));

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
                    id_sala = :s, fecha_inicio = :ini, fecha_fin = :fin,
                    motivo = :m, notas = :n, id_estado_cita = :e,
                    recordatorio_enviado = 0
              WHERE id_cita = :id',
            [':r' => $idExpediente, ':d' => $idDentista, ':s' => $idSala ?: null,
             ':ini' => $inicio, ':fin' => $fin, ':m' => $motivo, ':n' => $notas,
             ':e' => ESTADO_PENDIENTE, ':id' => $idCita]
        );
        $accion = 'REAGENDA';
    } else {
        Conexion::consulta(
            'INSERT INTO cita
                (id_registro_paciente, id_usuario_dentista, id_sala,
                 id_estado_cita, fecha_inicio, fecha_fin, motivo, notas,
                 id_usuario_registro)
             VALUES (:r, :d, :s, :e, :ini, :fin, :m, :n, :u)',
            [':r' => $idExpediente, ':d' => $idDentista, ':s' => $idSala ?: null,
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
