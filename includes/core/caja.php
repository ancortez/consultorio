<?php
declare(strict_types=1);

/**
 * Caja: turnos, cobros y corte.
 *
 * Reglas que se decidieron con el consultorio y que este archivo hace
 * cumplir, porque son las que evitan que el dinero se descuadre:
 *
 *   1. Sin turno abierto no se cobra. Un cobro fuera de turno no cae en
 *      ningun corte y desaparece de la cuenta del dia.
 *   2. Cada caja tiene su propio turno. Recepcion y cada consultorio
 *      cobran por su lado, asi que cortan por su lado.
 *   3. Nada se borra. Cancelar un cobro lo marca y registra el reverso.
 *   4. Solo se cancela el mismo dia y con el turno todavia abierto. Un
 *      corte ya cerrado es un documento firmado: si se pudiera tocar
 *      hacia atras, no serviria de nada haberlo cerrado.
 */
final class Caja
{
    public const CONCEPTO_COBRO      = 1;
    public const CONCEPTO_FONDO      = 3;
    public const CONCEPTO_CANCELA    = 12;

    /* -----------------------------------------------------------------
       Turnos
       ----------------------------------------------------------------- */

    /** El turno abierto de una caja, o null si no hay. */
    public static function turnoAbierto(int $idCaja): ?array
    {
        return Conexion::uno(
            'SELECT cc.*, c.nombre_caja, u.nombre_completo AS abrio
               FROM corte_caja cc
               JOIN caja c ON c.id_caja = cc.id_caja
               JOIN usuario u ON u.id_usuario = cc.id_usuario_apertura
              WHERE cc.id_caja = :c AND cc.abierto = 1
              ORDER BY cc.fecha_apertura DESC LIMIT 1',
            [':c' => $idCaja]
        );
    }

    /** Todos los turnos abiertos ahora mismo, de todas las cajas. */
    public static function turnosAbiertos(): array
    {
        return Conexion::todos(
            'SELECT cc.id_corte_caja, cc.id_caja, cc.fecha_apertura,
                    cc.monto_inicial, c.nombre_caja,
                    u.nombre_completo AS abrio
               FROM corte_caja cc
               JOIN caja c ON c.id_caja = cc.id_caja
               JOIN usuario u ON u.id_usuario = cc.id_usuario_apertura
              WHERE cc.abierto = 1
              ORDER BY c.nombre_caja'
        );
    }

    /** Abre turno. El fondo inicial entra como movimiento, no por fuera. */
    public static function abrir(int $idCaja, float $fondo, ?string $obs): int
    {
        if (self::turnoAbierto($idCaja) !== null) {
            throw new RuntimeException('Esta caja ya tiene un turno abierto.');
        }

        $existe = (int) Conexion::valor(
            'SELECT COUNT(*) FROM caja WHERE id_caja = :c AND estatus = 1',
            [':c' => $idCaja]
        );
        if ($existe === 0) {
            throw new RuntimeException('Esa caja no existe.');
        }

        $fondo = round(max(0, $fondo), 2);

        Conexion::consulta(
            'INSERT INTO corte_caja
                (id_caja, id_usuario_apertura, fecha_apertura,
                 monto_inicial, observaciones)
             VALUES (:c, :u, NOW(), :m, :o)',
            [':c' => $idCaja, ':u' => Auth::id(), ':m' => $fondo, ':o' => $obs]
        );

        $idCorte = Conexion::ultimoId();

        // El fondo se registra como ingreso en efectivo. Asi el corte
        // suma solo movimientos y no tiene que acordarse de sumar
        // aparte un numero que vive en otra columna.
        if ($fondo > 0) {
            self::movimiento($idCorte, self::CONCEPTO_FONDO, 1, 'I', $fondo,
                'FONDO INICIAL DEL TURNO');
        }

        Bitacora::registrar('cajas', 'ABRE', 'corte_caja', (string) $idCorte,
            ['caja' => $idCaja, 'fondo' => $fondo]);

        return $idCorte;
    }

    /**
     * Cierra turno.
     *
     * El sistema dice cuanto deberia haber en efectivo; quien cierra
     * captura lo que conto. La diferencia se guarda tal cual, sin
     * redondear ni esconder: un faltante que no se anota se repite.
     */
    public static function cerrar(int $idCorte, float $contado, ?string $obs): array
    {
        $corte = self::resumen($idCorte);

        if ((int) $corte['abierto'] === 0) {
            throw new RuntimeException('Ese turno ya estaba cerrado.');
        }

        $contado    = round($contado, 2);
        $esperado   = round((float) $corte['efectivo_esperado'], 2);
        $diferencia = round($contado - $esperado, 2);

        Conexion::consulta(
            'UPDATE corte_caja
                SET abierto = 0, id_usuario_cierre = :u, fecha_cierre = NOW(),
                    total_ingresos = :ing, total_egresos = :egr,
                    monto_esperado = :esp, monto_contado = :con,
                    diferencia = :dif,
                    observaciones = TRIM(CONCAT_WS(" | ", observaciones, :o))
              WHERE id_corte_caja = :c',
            [':u' => Auth::id(),
             ':ing' => $corte['total_ingresos'], ':egr' => $corte['total_egresos'],
             ':esp' => $esperado, ':con' => $contado, ':dif' => $diferencia,
             ':o' => $obs, ':c' => $idCorte]
        );

        Bitacora::registrar('cajas', 'CIERRA', 'corte_caja', (string) $idCorte, [
            'esperado' => $esperado, 'contado' => $contado,
            'diferencia' => $diferencia,
        ]);

        return ['esperado' => $esperado, 'contado' => $contado,
                'diferencia' => $diferencia];
    }

    /**
     * Resumen de un turno: totales y desglose por forma de pago.
     *
     * El efectivo esperado se calcula aparte del total de ingresos,
     * porque una transferencia suma al dia pero no al cajon.
     */
    public static function resumen(int $idCorte): array
    {
        $corte = Conexion::uno(
            'SELECT cc.*, c.nombre_caja,
                    ua.nombre_completo AS abrio,
                    uc.nombre_completo AS cerro
               FROM corte_caja cc
               JOIN caja c ON c.id_caja = cc.id_caja
               JOIN usuario ua ON ua.id_usuario = cc.id_usuario_apertura
               LEFT JOIN usuario uc ON uc.id_usuario = cc.id_usuario_cierre
              WHERE cc.id_corte_caja = :c',
            [':c' => $idCorte]
        );

        if ($corte === null) {
            throw new RuntimeException('El turno no existe.');
        }

        $formas = Conexion::todos(
            'SELECT * FROM v_corte_resumen WHERE id_corte_caja = :c
              ORDER BY nombre_forma',
            [':c' => $idCorte]
        );

        $ingresos = $egresos = $efectivo = 0.0;
        foreach ($formas as $f) {
            $ingresos += (float) $f['ingresos'];
            $egresos  += (float) $f['egresos'];
            if ((int) $f['afecta_efectivo'] === 1) {
                $efectivo += (float) $f['neto'];
            }
        }

        $corte['formas']            = $formas;
        $corte['total_ingresos']    = round($ingresos, 2);
        $corte['total_egresos']     = round($egresos, 2);
        // El fondo ya viene dentro de los movimientos en efectivo.
        $corte['efectivo_esperado'] = round($efectivo, 2);

        return $corte;
    }

    /** Movimientos de un turno, para la hoja del corte. */
    public static function movimientos(int $idCorte): array
    {
        return Conexion::todos(
            'SELECT mc.*, cm.nombre_concepto, fp.nombre_forma,
                    u.nombre_completo AS registro,
                    p.folio AS pago_folio,
                    vp.nombre_completo AS paciente
               FROM movimiento_caja mc
               JOIN concepto_movimiento cm ON cm.id_concepto = mc.id_concepto
               JOIN forma_pago fp ON fp.id_forma_pago = mc.id_forma_pago
               JOIN usuario u ON u.id_usuario = mc.id_usuario
               LEFT JOIN pago p ON p.id_pago = mc.id_pago
               LEFT JOIN v_paciente vp
                      ON vp.id_registro_paciente = p.id_registro_paciente
              WHERE mc.id_corte_caja = :c AND mc.estatus = 1
              ORDER BY mc.fecha_hora, mc.id_movimiento',
            [':c' => $idCorte]
        );
    }

    /** Registra un movimiento suelto (fondo, egreso, reverso). */
    public static function movimiento(
        int $idCorte, int $idConcepto, int $idForma, string $naturaleza,
        float $monto, ?string $descripcion, ?int $idPago = null
    ): int {
        Conexion::consulta(
            'INSERT INTO movimiento_caja
                (id_corte_caja, id_concepto, id_forma_pago, id_pago,
                 id_usuario, naturaleza, monto, descripcion)
             VALUES (:c, :co, :f, :p, :u, :n, :m, :d)',
            [':c' => $idCorte, ':co' => $idConcepto, ':f' => $idForma,
             ':p' => $idPago, ':u' => Auth::id(), ':n' => $naturaleza,
             ':m' => round($monto, 2), ':d' => $descripcion]
        );

        return Conexion::ultimoId();
    }

    /* -----------------------------------------------------------------
       Cobros
       ----------------------------------------------------------------- */

    /**
     * Registra un cobro.
     *
     * @param array $datos
     *    id_registro_paciente, id_corte_caja, id_plan_tratamiento?,
     *    formas: [['id_forma_pago'=>1,'monto'=>500,'referencia'=>null], ...]
     *    aplicaciones: [['id_cargo'=>7,'monto'=>500], ...]  (opcional)
     *
     * Si no se manda 'aplicaciones', el abono se reparte solo contra
     * los cargos abiertos mas antiguos. Es lo que se espera cuando el
     * tutor llega y dice "le abono mil": nadie quiere elegir renglon
     * por renglon, y dejar el pago sin aplicar deja el saldo mintiendo.
     */
    public static function cobrar(array $datos): array
    {
        $idExpediente = (int) $datos['id_registro_paciente'];
        $idCorte      = (int) $datos['id_corte_caja'];
        $idPlan       = $datos['id_plan_tratamiento'] ?? null;

        $corte = Conexion::uno(
            'SELECT id_corte_caja, abierto FROM corte_caja WHERE id_corte_caja = :c',
            [':c' => $idCorte]
        );
        if ($corte === null || (int) $corte['abierto'] === 0) {
            throw new RuntimeException(
                'No hay un turno de caja abierto. Abre la caja antes de cobrar.');
        }

        // ---------- Formas de pago ----------
        $formas = [];
        $total  = 0.0;

        foreach ((array) ($datos['formas'] ?? []) as $f) {
            $monto = round((float) ($f['monto'] ?? 0), 2);
            if ($monto <= 0) { continue; }

            $idForma = (int) ($f['id_forma_pago'] ?? 0);
            $valida  = (int) Conexion::valor(
                'SELECT COUNT(*) FROM forma_pago
                  WHERE id_forma_pago = :f AND estatus = 1',
                [':f' => $idForma]
            );
            if ($valida === 0) { continue; }

            $formas[] = [
                'id_forma_pago' => $idForma,
                'monto'         => $monto,
                'referencia'    => !empty($f['referencia'])
                    ? mb_substr(mayus((string) $f['referencia']), 0, 60) : null,
            ];
            $total += $monto;
        }

        $total = round($total, 2);
        if ($total <= 0) {
            throw new InvalidArgumentException('Captura el monto del cobro.');
        }

        // ---------- Reparto contra los cargos ----------
        $pendientes = Cargos::pendientes($idExpediente, $idPlan !== null ? (int) $idPlan : null);
        $porCargo   = [];
        foreach ($pendientes as $p) {
            $porCargo[(int) $p['id_cargo']] = (float) $p['saldo'];
        }

        $aplicaciones = [];
        $porAplicar   = $total;

        if (!empty($datos['aplicaciones'])) {
            foreach ($datos['aplicaciones'] as $a) {
                $idCargo = (int) ($a['id_cargo'] ?? 0);
                $monto   = round((float) ($a['monto'] ?? 0), 2);

                if ($monto <= 0 || !isset($porCargo[$idCargo])) { continue; }

                // Nunca se aplica mas de lo que el cargo debe: el
                // sobrante se queda a favor, no infla el renglon.
                $monto = min($monto, $porCargo[$idCargo], $porAplicar);
                if ($monto <= 0) { continue; }

                $aplicaciones[] = ['id_cargo' => $idCargo, 'monto' => $monto];
                $porCargo[$idCargo] -= $monto;
                $porAplicar = round($porAplicar - $monto, 2);
            }
        }

        // Lo que sobre se reparte solo, del cargo mas viejo al mas nuevo.
        foreach ($pendientes as $p) {
            if ($porAplicar <= 0) { break; }

            $idCargo = (int) $p['id_cargo'];
            $libre   = $porCargo[$idCargo] ?? 0;
            if ($libre <= 0) { continue; }

            $monto = round(min($libre, $porAplicar), 2);
            $aplicaciones[] = ['id_cargo' => $idCargo, 'monto' => $monto];
            $porCargo[$idCargo] -= $monto;
            $porAplicar = round($porAplicar - $monto, 2);
        }

        // ---------- Guardado ----------
        $folio = siguienteFolio('pago', 'folio', 'RC');

        Conexion::consulta(
            'INSERT INTO pago
                (folio, id_registro_paciente, id_plan_tratamiento,
                 id_corte_caja, id_usuario, monto, observaciones)
             VALUES (:fo, :r, :plan, :c, :u, :m, :o)',
            [':fo' => $folio, ':r' => $idExpediente, ':plan' => $idPlan,
             ':c' => $idCorte, ':u' => Auth::id(), ':m' => $total,
             ':o' => !empty($datos['observaciones'])
                 ? mb_substr(mayus((string) $datos['observaciones']), 0, 255) : null]
        );

        $idPago = Conexion::ultimoId();

        foreach ($formas as $f) {
            Conexion::consulta(
                'INSERT INTO pago_forma (id_pago, id_forma_pago, monto, referencia)
                      VALUES (:p, :f, :m, :r)',
                [':p' => $idPago, ':f' => $f['id_forma_pago'],
                 ':m' => $f['monto'], ':r' => $f['referencia']]
            );

            // Un movimiento por forma: asi el corte puede decir cuanto
            // entro en efectivo y cuanto por transferencia sin abrir
            // cada cobro.
            self::movimiento($idCorte, self::CONCEPTO_COBRO,
                $f['id_forma_pago'], 'I', $f['monto'],
                'COBRO ' . $folio, $idPago);
        }

        foreach ($aplicaciones as $a) {
            Conexion::consulta(
                'INSERT INTO pago_aplicacion (id_pago, id_cargo, monto)
                      VALUES (:p, :c, :m)',
                [':p' => $idPago, ':c' => $a['id_cargo'], ':m' => $a['monto']]
            );
        }

        Bitacora::registrar('cajas', 'COBRA', 'pago', (string) $idPago, [
            'expediente'   => $idExpediente,
            'monto'        => $total,
            'aplicado'     => round($total - $porAplicar, 2),
            'a_favor'      => $porAplicar,
        ]);

        return [
            'id_pago'  => $idPago,
            'folio'    => $folio,
            'monto'    => $total,
            'aplicado' => round($total - $porAplicar, 2),
            // Lo que no encontro cargo donde caer. Queda como saldo a
            // favor y se avisa: un anticipo sin avisar se olvida.
            'a_favor'  => $porAplicar,
        ];
    }

    /**
     * Cancela un cobro.
     *
     * No se borra: el pago cambia de estatus y se registra el reverso
     * en la caja, de modo que el corte cuadre y queden a la vista los
     * dos movimientos.
     *
     * Solo el mismo dia y con el turno abierto. Un corte ya cerrado se
     * imprimio y se firmo; tocarlo hacia atras lo volveria papel sin
     * valor.
     */
    public static function cancelarPago(int $idPago, string $motivo): void
    {
        $pago = Conexion::uno(
            'SELECT p.*, cc.abierto, cc.id_corte_caja
               FROM pago p
               JOIN corte_caja cc ON cc.id_corte_caja = p.id_corte_caja
              WHERE p.id_pago = :p',
            [':p' => $idPago]
        );

        if ($pago === null) {
            throw new RuntimeException('El cobro no existe.');
        }
        if ((int) $pago['estatus'] === 0) {
            throw new RuntimeException('Este cobro ya estaba cancelado.');
        }
        if (substr((string) $pago['fecha_hora'], 0, 10) !== date('Y-m-d')) {
            throw new RuntimeException(
                'Solo se puede cancelar un cobro el mismo dia en que se hizo. '
                . 'Para uno de otro dia, registra una devolucion.');
        }
        if ((int) $pago['abierto'] === 0) {
            throw new RuntimeException(
                'El turno de caja de ese cobro ya se cerro. El corte impreso '
                . 'ya no puede cambiar: registra una devolucion.');
        }

        Conexion::consulta(
            'UPDATE pago
                SET estatus = 0, id_usuario_cancela = :u,
                    fecha_cancelacion = NOW(), motivo_cancelacion = :m
              WHERE id_pago = :p',
            [':u' => Auth::id(), ':m' => mb_substr($motivo, 0, 255), ':p' => $idPago]
        );

        // Los movimientos originales se apagan y se registra el reverso
        // por cada forma de pago, para que el desglose siga cuadrando.
        $formas = Conexion::todos(
            'SELECT id_forma_pago, monto FROM pago_forma WHERE id_pago = :p',
            [':p' => $idPago]
        );

        Conexion::consulta(
            'UPDATE movimiento_caja SET estatus = 0 WHERE id_pago = :p',
            [':p' => $idPago]
        );

        foreach ($formas as $f) {
            $id = self::movimiento(
                (int) $pago['id_corte_caja'], self::CONCEPTO_CANCELA,
                (int) $f['id_forma_pago'], 'E', (float) $f['monto'],
                'CANCELACION DEL COBRO ' . $pago['folio'], $idPago);

            // El reverso tambien se apaga: los dos quedan guardados
            // para la auditoria, pero el corte los ignora a los dos.
            // Si se dejaran vivos, el dia sumaria el ingreso y el
            // egreso, y el total en efectivo saldria bien pero el
            // desglose diria que hubo un egreso que nunca ocurrio.
            Conexion::consulta(
                'UPDATE movimiento_caja SET estatus = 0 WHERE id_movimiento = :m',
                [':m' => $id]
            );
        }

        Bitacora::registrar('cajas', 'CANCELA', 'pago', (string) $idPago, [
            'folio' => $pago['folio'], 'monto' => $pago['monto'],
            'motivo' => $motivo,
        ]);
    }
}
