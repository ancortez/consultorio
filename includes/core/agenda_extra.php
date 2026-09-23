<?php
declare(strict_types=1);

/**
 * Lo que rodea a la agenda: bloqueos, lista de espera y recordatorios.
 *
 * Vive aparte de Agenda (que arma los espacios de media hora) porque
 * son tres cosas con vida propia y meterlas ahi convertiria esa clase
 * en un cajon de sastre.
 */
final class AgendaExtra
{
    /* =================================================================
       Bloqueos
       ================================================================= */

    /**
     * Bloqueos que caen dentro de un rango.
     *
     * Un bloqueo sin dentista cierra el consultorio completo, asi que
     * aplica a todos. Se devuelven los dos casos juntos porque la
     * agenda tiene que tapar los dos igual.
     */
    public static function bloqueos(string $desde, string $hasta, ?int $idDentista = null): array
    {
        $sql = 'SELECT b.*, u.nombre_completo AS dentista,
                       ur.nombre_completo AS registro
                  FROM agenda_bloqueo b
                  LEFT JOIN usuario u  ON u.id_usuario = b.id_usuario
                  JOIN usuario ur ON ur.id_usuario = b.id_usuario_registro
                 WHERE b.estatus = 1
                   AND b.fecha_inicio < :hasta
                   AND b.fecha_fin    > :desde';

        $params = [':desde' => $desde . ' 00:00:00', ':hasta' => $hasta . ' 23:59:59'];

        if ($idDentista !== null) {
            $sql .= ' AND (b.id_usuario = :d OR b.id_usuario IS NULL)';
            $params[':d'] = $idDentista;
        }

        return Conexion::todos($sql . ' ORDER BY b.fecha_inicio', $params);
    }

    /** ¿Ese hueco choca con un bloqueo? */
    public static function estaBloqueado(string $inicio, string $fin, int $idDentista): ?array
    {
        return Conexion::uno(
            'SELECT motivo, fecha_inicio, fecha_fin
               FROM agenda_bloqueo
              WHERE estatus = 1
                AND (id_usuario = :d OR id_usuario IS NULL)
                AND fecha_inicio < :fin
                AND fecha_fin    > :ini
              LIMIT 1',
            [':d' => $idDentista, ':ini' => $inicio, ':fin' => $fin]
        );
    }

    public static function guardarBloqueo(array $datos): int
    {
        $inicio = (string) $datos['fecha_inicio'];
        $fin    = (string) $datos['fecha_fin'];

        if (strtotime($fin) <= strtotime($inicio)) {
            throw new InvalidArgumentException(
                'La hora de fin tiene que ser despues de la de inicio.');
        }

        // Un bloqueo encima de citas ya agendadas no se impide, pero se
        // avisa: a veces es justo lo que se quiere (se cierra el dia y
        // luego se reagenda), y otras es un error de fechas.
        $idDentista = $datos['id_usuario'] ?? null;

        Conexion::consulta(
            'INSERT INTO agenda_bloqueo
                (id_usuario, fecha_inicio, fecha_fin, motivo, id_usuario_registro)
             VALUES (:u, :ini, :fin, :m, :ur)',
            [':u' => $idDentista, ':ini' => $inicio, ':fin' => $fin,
             ':m' => mb_substr(mayus((string) $datos['motivo']), 0, 150),
             ':ur' => Auth::id()]
        );

        $id = Conexion::ultimoId();

        Bitacora::registrar('consultas', 'ALTA', 'agenda_bloqueo', (string) $id,
            ['desde' => $inicio, 'hasta' => $fin]);

        return $id;
    }

    /** Citas vivas que quedarian dentro de un bloqueo. */
    public static function citasEnBloqueo(string $inicio, string $fin, ?int $idDentista): array
    {
        $sql = 'SELECT c.id_cita, c.fecha_inicio, c.fecha_fin,
                       vp.nombre_completo AS paciente, vp.telefono,
                       u.nombre_completo AS dentista
                  FROM cita c
                  JOIN v_paciente vp ON vp.id_registro_paciente = c.id_registro_paciente
                  JOIN usuario u ON u.id_usuario = c.id_usuario_dentista
                 WHERE c.id_estado_cita IN (1,2,3)
                   AND c.fecha_inicio < :fin AND c.fecha_fin > :ini';

        $params = [':ini' => $inicio, ':fin' => $fin];

        if ($idDentista !== null) {
            $sql .= ' AND c.id_usuario_dentista = :d';
            $params[':d'] = $idDentista;
        }

        return Conexion::todos($sql . ' ORDER BY c.fecha_inicio', $params);
    }

    /* =================================================================
       Lista de espera
       ================================================================= */

    public static function espera(?int $idDentista = null): array
    {
        $sql = 'SELECT le.*, vp.nombre_completo AS paciente, vp.num_expediente,
                       vp.telefono, vp.edad,
                       u.nombre_completo AS dentista,
                       ur.nombre_completo AS registro
                  FROM lista_espera le
                  JOIN v_paciente vp ON vp.id_registro_paciente = le.id_registro_paciente
                  LEFT JOIN usuario u ON u.id_usuario = le.id_usuario_dentista
                  JOIN usuario ur ON ur.id_usuario = le.id_usuario_registro
                 WHERE le.estado = 1';

        $params = [];
        if ($idDentista !== null) {
            $sql .= ' AND (le.id_usuario_dentista = :d OR le.id_usuario_dentista IS NULL)';
            $params[':d'] = $idDentista;
        }

        // Los que llevan mas tiempo esperando salen primero: es lo
        // justo y es lo que la recepcionista espera ver arriba.
        return Conexion::todos($sql . ' ORDER BY le.fecha_registro', $params);
    }

    /**
     * A quien le sirve el hueco que se acaba de liberar.
     *
     * Se filtra por dentista y por franja horaria. Sin este filtro la
     * lista devuelve a todo el mundo y deja de ser util.
     */
    public static function candidatos(string $inicio, int $idDentista): array
    {
        $hora  = (int) date('G', strtotime($inicio));
        $franja = $hora < 14 ? 1 : 2;   // 1 maniana | 2 tarde

        return Conexion::todos(
            'SELECT le.*, vp.nombre_completo AS paciente, vp.telefono,
                    vp.num_expediente
               FROM lista_espera le
               JOIN v_paciente vp ON vp.id_registro_paciente = le.id_registro_paciente
              WHERE le.estado = 1
                AND (le.id_usuario_dentista = :d OR le.id_usuario_dentista IS NULL)
                AND (le.preferencia_horario = 3 OR le.preferencia_horario = :f)
              ORDER BY le.fecha_registro
              LIMIT 10',
            [':d' => $idDentista, ':f' => $franja]
        );
    }

    /* =================================================================
       Recordatorios
       ================================================================= */

    /** Reglas activas, de la mas anticipada a la mas cercana. */
    public static function reglas(): array
    {
        return Conexion::todos(
            'SELECT * FROM recordatorio_regla WHERE estatus = 1
              ORDER BY horas_antes DESC'
        );
    }

    /**
     * Recordatorios que toca hacer ahora.
     *
     * Para cada cita viva se revisa cada regla: si ya se entro en su
     * ventana y todavia no se registro el aviso, sale en la lista.
     *
     * La ventana tiene piso y techo. Sin techo, un recordatorio de 48
     * horas seguiria apareciendo el dia de la cita, cuando ya no tiene
     * ningun sentido hacerlo.
     */
    public static function pendientes(?int $idDentista = null): array
    {
        $reglas = self::reglas();
        if (!$reglas) { return []; }

        $sql = 'SELECT c.id_cita, c.fecha_inicio, c.motivo, c.id_estado_cita,
                       ec.nombre_estado,
                       vp.id_registro_paciente, vp.nombre_completo AS paciente,
                       vp.telefono, vp.edad,
                       u.nombre_completo AS dentista,
                       (SELECT GROUP_CONCAT(cr.id_regla)
                          FROM cita_recordatorio cr
                         WHERE cr.id_cita = c.id_cita) AS hechos
                  FROM cita c
                  JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
                  JOIN v_paciente vp ON vp.id_registro_paciente = c.id_registro_paciente
                  JOIN usuario u ON u.id_usuario = c.id_usuario_dentista
                 WHERE c.id_estado_cita IN (1, 2)
                   AND c.fecha_inicio > NOW()';

        $params = [];
        if ($idDentista !== null) {
            $sql .= ' AND c.id_usuario_dentista = :d';
            $params[':d'] = $idDentista;
        }

        $citas = Conexion::todos($sql . ' ORDER BY c.fecha_inicio', $params);

        $ahora = time();
        $lista = [];

        foreach ($citas as $c) {
            $hechos = array_filter(explode(',', (string) ($c['hechos'] ?? '')));
            $faltan = strtotime((string) $c['fecha_inicio']) - $ahora;
            $horas  = $faltan / 3600;

            foreach ($reglas as $i => $r) {
                if (in_array((string) $r['id_regla'], $hechos, true)) { continue; }

                $techo = (float) $r['horas_antes'];
                // El piso es la siguiente regla: un aviso de 48 horas
                // deja de pedirse en cuanto entra el de 24.
                $piso = isset($reglas[$i + 1]) ? (float) $reglas[$i + 1]['horas_antes'] : 0.0;

                if ($horas <= $techo && $horas > $piso) {
                    $lista[] = $c + [
                        'id_regla'     => (int) $r['id_regla'],
                        'regla'        => $r['nombre'],
                        'horas_antes'  => (int) $r['horas_antes'],
                        'horas_faltan' => round($horas, 1),
                    ];
                    break;   // un solo aviso pendiente por cita a la vez
                }
            }
        }

        return $lista;
    }

    /** Deja constancia de que el recordatorio se hizo. */
    public static function registrarRecordatorio(
        int $idCita, int $idRegla, int $resultado, ?string $nota
    ): void {
        Conexion::consulta(
            'INSERT INTO cita_recordatorio (id_cita, id_regla, resultado, nota, id_usuario)
                  VALUES (:c, :r, :res, :n, :u)
             ON DUPLICATE KEY UPDATE resultado = :res2, nota = :n2,
                                     id_usuario = :u2, fecha_registro = NOW()',
            [':c' => $idCita, ':r' => $idRegla, ':res' => $resultado,
             ':n' => $nota, ':u' => Auth::id(),
             ':res2' => $resultado, ':n2' => $nota, ':u2' => Auth::id()]
        );

        // Se conserva la marca vieja para no romper lo que ya la usa.
        Conexion::consulta(
            'UPDATE cita SET recordatorio_enviado = 1 WHERE id_cita = :c',
            [':c' => $idCita]
        );

        Bitacora::registrar('consultas', 'RECUERDA', 'cita', (string) $idCita,
            ['regla' => $idRegla, 'resultado' => $resultado]);
    }

    /**
     * Arma el mensaje de WhatsApp.
     *
     * Se manda a mano a proposito: recepcion lo revisa antes de
     * enviarlo. Mandar solo es facil de programar y dificil de
     * arreglar cuando sale mal.
     */
    public static function mensaje(array $cita, string $consultorio): string
    {
        $cuando = fechaLarga((string) $cita['fecha_inicio']);
        $hora   = date('g:i a', strtotime((string) $cita['fecha_inicio']));

        $nombre = trim(explode(' ', (string) $cita['paciente'])[0] ?? '');

        return "Hola, le escribimos de $consultorio.\n\n"
             . "Le recordamos la cita de $nombre el $cuando a las $hora"
             . " con " . $cita['dentista'] . ".\n\n"
             . "Si necesita cambiarla, responda a este mensaje. Gracias.";
    }
}
