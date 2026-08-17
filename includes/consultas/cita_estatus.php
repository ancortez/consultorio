<?php
/**
 * Cambia el estatus de una cita: confirmar, cancelar, marcar asistencia.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.ver');

$idCita   = paramInt('id_cita');
$idEstado = paramInt('id_estado_cita');
$nota     = limpiarEspacios(paramMayus('notas'));

if (!$idCita || !$idEstado) {
    Respuesta::error('Faltan datos para cambiar el estatus.');
}

$cita = Conexion::uno(
    'SELECT id_usuario_dentista, id_estado_cita FROM cita WHERE id_cita = :c',
    [':c' => $idCita]
);
if ($cita === null) {
    Respuesta::error('La cita no existe.', 404);
}

// Un dentista no puede tocar las citas de otro.
if (!Agenda::puedeAgendarPara((int) $cita['id_usuario_dentista'])) {
    Respuesta::error('No puedes modificar citas de otro dentista.', 403);
}

$sql = 'UPDATE cita SET id_estado_cita = :e';
$params = [':e' => $idEstado, ':id' => $idCita];

if ($nota !== null) {
    $sql .= ', notas = CONCAT(COALESCE(notas, ""), :n)';
    $params[':n'] = "\n" . date('d/m/Y H:i') . ' - ' . $nota;
}

Conexion::consulta($sql . ' WHERE id_cita = :id', $params);

Bitacora::registrar('consultas', 'CAMBIO', 'cita', (string) $idCita,
    ['de' => $cita['id_estado_cita'], 'a' => $idEstado]);

Respuesta::ok(null, 'Estatus de la cita actualizado.');
