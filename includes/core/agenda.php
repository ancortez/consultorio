<?php
declare(strict_types=1);

/**
 * Reglas de la agenda.
 *
 * El consultorio trabaja en bloques de media hora. Aqui vive todo lo que
 * decide si un espacio esta libre, para que la pantalla y el guardado
 * usen exactamente el mismo criterio: si la validacion viviera solo en
 * el JavaScript, dos personas agendando al mismo tiempo terminarian
 * empalmando citas.
 */
final class Agenda
{
    /** Tamano del bloque en minutos. */
    public const BLOQUE = 30;

    /** Horario de atencion, en horas de 24. */
    public const HORA_INICIO = 9;
    public const HORA_FIN    = 20;

    /**
     * Dias que abre el consultorio (1 lunes ... 7 domingo, formato ISO).
     * El domingo queda fuera.
     */
    public const DIAS_HABILES = [1, 2, 3, 4, 5, 6];

    /** El sabado se cierra mas temprano. */
    public const HORA_FIN_SABADO = 14;

    /**
     * Estados que ocupan el espacio. Una cita cancelada o a la que no
     * asistieron libera su lugar; una pendiente no, aunque no este
     * confirmada: el paciente ya tiene ese horario apartado.
     */
    public const ESTADOS_OCUPAN = [1, 2, 3, 4];   // pendiente, confirmada, en sala, atendida

    /**
     * Devuelve los espacios de un dia para un dentista, marcando cuales
     * estan libres y cuales no.
     *
     * @return array<int, array{hora:string, libre:bool, motivo:?string}>
     */
    public static function espaciosDelDia(
        string $fecha,
        int    $idDentista,
        int    $duracionMin = self::BLOQUE,
        ?int   $idCitaIgnorar = null
    ): array {
        $dia = (int) date('N', strtotime($fecha));

        if (!in_array($dia, self::DIAS_HABILES, true)) {
            return [];
        }

        $horaFin = $dia === 6 ? self::HORA_FIN_SABADO : self::HORA_FIN;

        // Citas ya agendadas ese dia para ese dentista.
        $sql = 'SELECT c.id_cita, c.fecha_inicio, c.fecha_fin,
                       vp.nombre_completo, ec.nombre_estado
                  FROM cita c
                  JOIN v_paciente vp ON vp.id_registro_paciente = c.id_registro_paciente
                  JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
                 WHERE DATE(c.fecha_inicio) = :f
                   AND c.id_usuario_dentista = :d
                   AND c.id_estado_cita IN (' . implode(',', self::ESTADOS_OCUPAN) . ')';
        $params = [':f' => $fecha, ':d' => $idDentista];

        // Al reagendar, la propia cita no se cuenta como estorbo.
        if ($idCitaIgnorar !== null) {
            $sql .= ' AND c.id_cita <> :ignorar';
            $params[':ignorar'] = $idCitaIgnorar;
        }

        $ocupadas = Conexion::todos($sql, $params);

        // Bloqueos que caen ese dia: vacaciones, comida, un congreso.
        // Se leen una sola vez y se comparan contra cada hueco.
        $bloqueos = AgendaExtra::bloqueos($fecha, $fecha, $idDentista);

        $espacios = [];
        $ahora    = time();

        $inicioDia = strtotime("$fecha " . str_pad((string) self::HORA_INICIO, 2, '0', STR_PAD_LEFT) . ':00');
        $finDia    = strtotime("$fecha " . str_pad((string) $horaFin, 2, '0', STR_PAD_LEFT) . ':00');

        for ($t = $inicioDia; $t < $finDia; $t += self::BLOQUE * 60) {
            $inicio = $t;
            $fin    = $t + $duracionMin * 60;

            // Una cita larga no puede rebasar el cierre.
            if ($fin > $finDia) {
                continue;
            }

            $motivo = null;

            if ($inicio < $ahora) {
                $motivo = 'Ya paso';
            } else {
                // El bloqueo gana sobre todo lo demas: si el consultorio
                // esta cerrado, da igual que el hueco este libre.
                foreach ($bloqueos as $b) {
                    if ($inicio < strtotime($b['fecha_fin'])
                        && $fin > strtotime($b['fecha_inicio'])) {
                        $motivo = $b['motivo'];
                        break;
                    }
                }
            }

            if ($motivo === null) {
                foreach ($ocupadas as $o) {
                    $oi = strtotime($o['fecha_inicio']);
                    $of = strtotime($o['fecha_fin']);

                    // Se empalman si uno empieza antes de que el otro
                    // termine, en cualquiera de los dos sentidos.
                    if ($inicio < $of && $fin > $oi) {
                        $motivo = $o['nombre_completo'];
                        break;
                    }
                }
            }

            $espacios[] = [
                'hora'   => date('H:i', $inicio),
                'fin'    => date('H:i', $fin),
                'libre'  => $motivo === null,
                'motivo' => $motivo,
            ];
        }

        return $espacios;
    }

    /**
     * Comprueba que un horario siga libre justo antes de guardar.
     *
     * @return string|null mensaje de error, o null si esta libre
     */
    public static function validarEspacio(
        string $inicio,
        string $fin,
        int    $idDentista,
        ?int   $idCitaIgnorar = null
    ): ?string {
        $ini = strtotime($inicio);
        $end = strtotime($fin);

        if ($ini === false || $end === false || $end <= $ini) {
            return 'El horario de la cita no es valido.';
        }

        $dia = (int) date('N', $ini);
        if (!in_array($dia, self::DIAS_HABILES, true)) {
            return 'El consultorio no abre ese dia.';
        }

        $horaFin = $dia === 6 ? self::HORA_FIN_SABADO : self::HORA_FIN;
        $hIni = (int) date('G', $ini);

        if ($hIni < self::HORA_INICIO || (int) date('G', $end) > $horaFin
            || ((int) date('G', $end) === $horaFin && (int) date('i', $end) > 0)) {
            return sprintf('El horario de atencion es de %02d:00 a %02d:00.',
                self::HORA_INICIO, $horaFin);
        }

        // Los minutos deben caer en punto o a la media.
        if (!in_array((int) date('i', $ini), [0, 30], true)) {
            return 'Las citas empiezan en punto o a la media hora.';
        }

        // Un bloqueo cierra el horario aunque no haya ninguna cita.
        $bloqueo = AgendaExtra::estaBloqueado($inicio, $fin, $idDentista);
        if ($bloqueo !== null) {
            return 'Ese horario esta bloqueado: ' . $bloqueo['motivo'] . '.';
        }

        $sql = 'SELECT vp.nombre_completo, c.fecha_inicio
                  FROM cita c
                  JOIN v_paciente vp ON vp.id_registro_paciente = c.id_registro_paciente
                 WHERE c.id_usuario_dentista = :d
                   AND c.id_estado_cita IN (' . implode(',', self::ESTADOS_OCUPAN) . ')
                   AND c.sin_hora = 0
                   AND c.fecha_inicio < :fin
                   AND c.fecha_fin    > :ini';
        $params = [':d' => $idDentista, ':fin' => $fin, ':ini' => $inicio];

        if ($idCitaIgnorar !== null) {
            $sql .= ' AND c.id_cita <> :ignorar';
            $params[':ignorar'] = $idCitaIgnorar;
        }

        $choque = Conexion::uno($sql . ' LIMIT 1', $params);

        if ($choque !== null) {
            return 'Ese horario ya lo tiene ' . $choque['nombre_completo']
                 . ' (' . date('H:i', strtotime($choque['fecha_inicio'])) . ').';
        }

        return null;
    }

    /**
     * Que dentistas puede agendar el usuario actual.
     *
     * Un dentista solo agenda consigo mismo. El administrador y quien
     * tenga permiso de agenda pueden hacerlo para cualquiera.
     */
    public static function dentistasPermitidos(): array
    {
        $usuario = Auth::usuario();

        $puedeTodos = (int) $usuario['id_rol'] === 1
            || Auth::puede('consultas.agenda');

        if ($puedeTodos) {
            return Conexion::todos(
                'SELECT id_usuario, nombre_completo, cedula_prof
                   FROM usuario
                  WHERE es_dentista = 1 AND estatus = 1
                  ORDER BY nombre_completo'
            );
        }

        if ($usuario['es_dentista']) {
            return Conexion::todos(
                'SELECT id_usuario, nombre_completo, cedula_prof
                   FROM usuario WHERE id_usuario = :u',
                [':u' => $usuario['id_usuario']]
            );
        }

        return [];
    }

    /** true si el usuario puede agendar para ese dentista. */
    public static function puedeAgendarPara(int $idDentista): bool
    {
        foreach (self::dentistasPermitidos() as $d) {
            if ((int) $d['id_usuario'] === $idDentista) {
                return true;
            }
        }
        return false;
    }
}
