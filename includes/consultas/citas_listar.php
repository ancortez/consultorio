<?php
/**
 * Citas de un rango de fechas.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.ver');

$desde      = trim((string) param('desde', date('Y-m-d')));
$hasta      = trim((string) param('hasta', $desde));
$idDentista = paramInt('id_usuario_dentista');
$idEstado   = paramInt('id_estado_cita');

$where  = ['DATE(c.fecha_inicio) BETWEEN :desde AND :hasta'];
$params = [':desde' => $desde, ':hasta' => $hasta];

// Un dentista sin permiso de agenda solo ve las suyas.
$usuario = Auth::usuario();
if ($usuario['es_dentista'] && (int) $usuario['id_rol'] !== 1
    && !Auth::puede('consultas.agenda')) {
    $where[]      = 'c.id_usuario_dentista = :yo';
    $params[':yo'] = Auth::id();
} elseif ($idDentista) {
    $where[]        = 'c.id_usuario_dentista = :den';
    $params[':den'] = $idDentista;
}

if ($idEstado) {
    $where[]        = 'c.id_estado_cita = :est';
    $params[':est'] = $idEstado;
}

Respuesta::ok(Conexion::todos(
    'SELECT c.id_cita, c.fecha_inicio, c.fecha_fin, c.sin_hora,
            c.motivo, c.notas,
            c.id_estado_cita, c.id_usuario_dentista, c.id_registro_paciente,
            ec.nombre_estado, ec.color,
            vp.nombre_completo, vp.num_expediente, vp.telefono, vp.edad,
            u.nombre_completo AS dentista,
            s.nombre_sala
       FROM cita c
       JOIN v_paciente vp  ON vp.id_registro_paciente = c.id_registro_paciente
       JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
       JOIN usuario u      ON u.id_usuario = c.id_usuario_dentista
       LEFT JOIN consultorio_sala s ON s.id_sala = c.id_sala
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY DATE(c.fecha_inicio), c.sin_hora DESC, c.fecha_inicio',
    $params
));
