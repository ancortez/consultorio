<?php
/**
 * La semana completa de un dentista.
 *
 * Devuelve la rejilla ya armada: un renglon por bloque de media hora y
 * una columna por dia, con lo que hay en cada celda. Se arma aqui y no
 * en JavaScript porque las reglas de horario, bloqueos y empalmes ya
 * viven en PHP, y tenerlas en dos lados era garantia de que tarde o
 * temprano dijeran cosas distintas.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.ver');

$idDentista = paramInt('id_usuario_dentista');
$lunes      = trim((string) param('lunes', ''));

if (!$idDentista) {
    Respuesta::error('Selecciona el dentista.');
}

// Siempre se arranca en lunes, venga lo que venga.
$base = $lunes !== '' && strtotime($lunes) ? strtotime($lunes) : time();
$lunes = date('Y-m-d', strtotime('monday this week', $base));

$dias = [];
for ($i = 0; $i < 6; $i++) {          // lunes a sabado
    $dias[] = date('Y-m-d', strtotime("+$i day", strtotime($lunes)));
}
$sabado = end($dias);

// ---------------------------------------------------------------------
// Citas de la semana
// ---------------------------------------------------------------------
$citas = Conexion::todos(
    // Las notas y el dentista viajan tambien: desde octubre 2026 se
    // puede abrir el detalle de una cita desde la vista de semana, y
    // sin estos campos el detalle saldria a medias o habria que pedir
    // la cita otra vez al servidor con un viaje de ida y vuelta.
    'SELECT c.id_cita, c.fecha_inicio, c.fecha_fin, c.motivo, c.notas,
            c.id_estado_cita, ec.nombre_estado, ec.color,
            c.id_registro_paciente, c.id_usuario_dentista, c.id_sala,
            vp.nombre_completo AS paciente, vp.telefono, vp.edad,
            vp.num_expediente,
            u.nombre_completo AS dentista,
            s.nombre_sala,
            pr.nombre_procedimiento
       FROM cita c
       JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
       JOIN v_paciente vp ON vp.id_registro_paciente = c.id_registro_paciente
       JOIN usuario u ON u.id_usuario = c.id_usuario_dentista
       LEFT JOIN consultorio_sala s ON s.id_sala = c.id_sala
       LEFT JOIN procedimiento pr ON pr.id_procedimiento = c.id_procedimiento
      WHERE c.id_usuario_dentista = :d
        AND DATE(c.fecha_inicio) BETWEEN :l AND :s
      ORDER BY c.fecha_inicio',
    [':d' => $idDentista, ':l' => $lunes, ':s' => $sabado]
);

$bloqueos = AgendaExtra::bloqueos($lunes, $sabado, $idDentista);

// ---------------------------------------------------------------------
// Rejilla
// ---------------------------------------------------------------------
$bloque  = Agenda::BLOQUE;
$hIni    = Agenda::HORA_INICIO;
$hFin    = Agenda::HORA_FIN;
$ahora   = time();

$rejilla = [];

for ($min = $hIni * 60; $min < $hFin * 60; $min += $bloque) {
    $hora = sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    $fila = ['hora' => $hora, 'celdas' => []];

    foreach ($dias as $i => $dia) {
        $diaSemana = (int) date('N', strtotime($dia));
        $cierre    = $diaSemana === 6 ? Agenda::HORA_FIN_SABADO : Agenda::HORA_FIN;

        $inicio = strtotime("$dia $hora:00");
        $fin    = $inicio + $bloque * 60;

        // Fuera del horario de ese dia: el sabado cierra mas temprano.
        if ($min >= $cierre * 60) {
            $fila['celdas'][] = ['tipo' => 'cerrado'];
            continue;
        }

        // Bloqueo: gana sobre todo lo demas.
        $bloqueado = null;
        foreach ($bloqueos as $b) {
            if ($inicio < strtotime($b['fecha_fin'])
                && $fin > strtotime($b['fecha_inicio'])) {
                $bloqueado = $b;
                break;
            }
        }
        if ($bloqueado !== null) {
            $fila['celdas'][] = [
                'tipo'   => 'bloqueo',
                'motivo' => $bloqueado['motivo'],
                'id'     => (int) $bloqueado['id_bloqueo'],
            ];
            continue;
        }

        // Cita que empieza justo en este bloque. Las que vienen de un
        // bloque anterior se marcan como continuacion para que la
        // celda no se pueda usar pero tampoco repita la tarjeta.
        $aqui = null;
        $sigue = false;

        foreach ($citas as $c) {
            if (!str_starts_with((string) $c['fecha_inicio'], $dia)) { continue; }

            $ci = strtotime((string) $c['fecha_inicio']);
            $cf = strtotime((string) $c['fecha_fin']);

            if ($ci === $inicio) {
                $aqui = $c;
                break;
            }
            if ($inicio > $ci && $inicio < $cf) {
                $sigue = true;
                break;
            }
        }

        if ($aqui !== null) {
            $largo = max(1, (int) ceil(
                (strtotime((string) $aqui['fecha_fin'])
                 - strtotime((string) $aqui['fecha_inicio'])) / ($bloque * 60)));

            $fila['celdas'][] = [
                'tipo'  => 'cita',
                'largo' => $largo,
                'cita'  => $aqui,
            ];
            continue;
        }

        if ($sigue) {
            $fila['celdas'][] = ['tipo' => 'sigue'];
            continue;
        }

        $fila['celdas'][] = [
            'tipo'  => $inicio < $ahora ? 'paso' : 'libre',
            'fecha' => $dia,
            'hora'  => $hora,
        ];
    }

    $rejilla[] = $fila;
}

Respuesta::ok([
    'lunes'    => $lunes,
    'sabado'   => $sabado,
    'dias'     => array_map(static fn($d) => [
        'fecha'  => $d,
        'nombre' => ['', 'Lunes', 'Martes', 'Miercoles', 'Jueves',
                     'Viernes', 'Sabado', 'Domingo'][(int) date('N', strtotime($d))],
        'dia'    => (int) date('j', strtotime($d)),
        'hoy'    => $d === date('Y-m-d'),
    ], $dias),
    'rejilla'  => $rejilla,
    'bloqueos' => $bloqueos,
    'citas'    => count($citas),
]);
