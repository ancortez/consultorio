<?php
declare(strict_types=1);

/**
 * Cargos: lo que el paciente debe.
 *
 * Un cargo puede nacer de tres lados y los tres terminan igual:
 *
 *   - Del plan de tratamiento, cuando el tutor lo acepta. Antes de eso
 *     el plan es una cotizacion y cobrarla seria adelantarse.
 *   - De la consulta, cuando sale algo que no estaba planeado. Es la
 *     mitad del trabajo real de un consultorio.
 *   - A mano, para lo que no cabe en ninguno de los dos.
 *
 * Todo lo demas del sistema pregunta "cuanto debe" a la vista
 * v_cargo_saldo y no a una columna guardada, porque una columna se
 * desincroniza en cuanto se cancela un pago.
 */
final class Cargos
{
    /** El plan empieza a cobrarse a partir de Aceptado. */
    public const ESTADOS_COBRABLES = [2, 3, 4];   // Aceptado, En proceso, Terminado

    /**
     * Crea un cargo.
     *
     * @param array $datos id_registro_paciente, descripcion, importe y
     *                     los amarres opcionales (plan, consulta...).
     */
    public static function crear(array $datos): int
    {
        $cantidad = max(1, (int) ($datos['cantidad'] ?? 1));
        $precio   = round((float) ($datos['precio_unitario'] ?? 0), 2);
        $desc     = round((float) ($datos['descuento'] ?? 0), 2);

        if ($precio < 0) { $precio = 0; }
        if ($desc < 0)   { $desc = 0; }

        $importe = round($precio * $cantidad - $desc, 2);
        if ($importe < 0) { $importe = 0; }

        // El descuento deja rastro de quien lo autorizo. En dinero,
        // "quien lo permitio" pesa tanto como "cuanto fue".
        $idDescuento = $desc > 0
            ? ($datos['id_usuario_descuento'] ?? Auth::id())
            : null;

        Conexion::consulta(
            'INSERT INTO cargo
                (id_registro_paciente, id_plan_tratamiento, id_plan_detalle,
                 id_consulta, id_procedimiento, descripcion, cantidad,
                 precio_unitario, descuento, importe,
                 id_usuario_descuento, motivo_descuento, fecha, id_usuario)
             VALUES (:r, :plan, :det, :cons, :proc, :d, :cant,
                     :pre, :desc, :imp, :ud, :md, :f, :u)',
            [
                ':r'    => $datos['id_registro_paciente'],
                ':plan' => $datos['id_plan_tratamiento'] ?? null,
                ':det'  => $datos['id_plan_detalle'] ?? null,
                ':cons' => $datos['id_consulta'] ?? null,
                ':proc' => $datos['id_procedimiento'] ?? null,
                ':d'    => mb_substr((string) $datos['descripcion'], 0, 200),
                ':cant' => $cantidad,
                ':pre'  => $precio,
                ':desc' => $desc,
                ':imp'  => $importe,
                ':ud'   => $idDescuento,
                ':md'   => $datos['motivo_descuento'] ?? null,
                ':f'    => $datos['fecha'] ?? date('Y-m-d'),
                ':u'    => Auth::id(),
            ]
        );

        $id = Conexion::ultimoId();

        Bitacora::registrar('cajas', 'ALTA', 'cargo', (string) $id, [
            'expediente' => $datos['id_registro_paciente'],
            'importe'    => $importe,
            'descuento'  => $desc,
        ]);

        return $id;
    }

    /**
     * Pone al dia los cargos de un plan de tratamiento.
     *
     * Se llama al guardar el plan. Solo genera cargos si el plan ya
     * esta aceptado: un plan propuesto todavia es una cotizacion.
     *
     * Los renglones ya pagados no se tocan aunque el plan cambie. Si
     * un concepto desaparece del plan y no se le habia abonado nada,
     * su cargo se cancela; si ya tenia abonos, se deja y se avisa.
     *
     * @return array{creados:int,cancelados:int,intocables:int}
     */
    public static function sincronizarPlan(int $idPlan): array
    {
        $plan = Conexion::uno(
            'SELECT id_plan_tratamiento, id_registro_paciente, id_estado_plan,
                    folio, descripcion, fecha
               FROM plan_tratamiento WHERE id_plan_tratamiento = :p',
            [':p' => $idPlan]
        );

        if ($plan === null) {
            return ['creados' => 0, 'cancelados' => 0, 'intocables' => 0];
        }

        $cobrable = in_array((int) $plan['id_estado_plan'], self::ESTADOS_COBRABLES, true);

        // Lo que ya esta cargado de este plan, con lo que se le aplico.
        $actuales = Conexion::todos(
            'SELECT vcs.id_cargo, vcs.aplicado, c.id_plan_detalle
               FROM v_cargo_saldo vcs
               JOIN cargo c ON c.id_cargo = vcs.id_cargo
              WHERE vcs.id_plan_tratamiento = :p',
            [':p' => $idPlan]
        );

        $porDetalle = [];
        foreach ($actuales as $a) {
            if ($a['id_plan_detalle'] !== null) {
                $porDetalle[(int) $a['id_plan_detalle']] = $a;
            }
        }

        $creados = $cancelados = $intocables = 0;

        if (!$cobrable) {
            // El plan volvio a propuesto o se cancelo: se retiran los
            // cargos que nadie ha pagado.
            foreach ($actuales as $a) {
                if ((float) $a['aplicado'] > 0) { $intocables++; continue; }
                self::cancelar((int) $a['id_cargo'],
                    'El plan dejo de estar aceptado.');
                $cancelados++;
            }
            return compact('creados', 'cancelados', 'intocables');
        }

        $renglones = Conexion::todos(
            'SELECT d.id_plan_detalle, d.id_procedimiento, d.cantidad,
                    d.precio_unitario, d.descuento, d.importe, d.dientes,
                    pr.nombre_procedimiento
               FROM plan_tratamiento_detalle d
               LEFT JOIN procedimiento pr ON pr.id_procedimiento = d.id_procedimiento
              WHERE d.id_plan_tratamiento = :p
              ORDER BY d.orden, d.id_plan_detalle',
            [':p' => $idPlan]
        );

        $vistos = [];

        foreach ($renglones as $r) {
            $idDetalle = (int) $r['id_plan_detalle'];
            $vistos[$idDetalle] = true;

            $texto = mb_strtoupper((string) ($r['nombre_procedimiento'] ?? 'PROCEDIMIENTO'), 'UTF-8')
                . ($r['dientes'] ? ' · OD ' . $r['dientes'] : '');

            if (isset($porDetalle[$idDetalle])) {
                $cargo = $porDetalle[$idDetalle];

                // Con abonos encima no se reescribe el importe: seria
                // mover el piso bajo un pago ya hecho.
                if ((float) $cargo['aplicado'] > 0) { $intocables++; continue; }

                Conexion::consulta(
                    'UPDATE cargo
                        SET descripcion = :d, cantidad = :c,
                            precio_unitario = :pre, descuento = :desc,
                            importe = :imp, id_procedimiento = :proc
                      WHERE id_cargo = :id',
                    [':d' => $texto, ':c' => (int) $r['cantidad'],
                     ':pre' => $r['precio_unitario'], ':desc' => $r['descuento'],
                     ':imp' => $r['importe'], ':proc' => $r['id_procedimiento'],
                     ':id' => $cargo['id_cargo']]
                );
                continue;
            }

            self::crear([
                'id_registro_paciente' => $plan['id_registro_paciente'],
                'id_plan_tratamiento'  => $idPlan,
                'id_plan_detalle'      => $idDetalle,
                'id_procedimiento'     => $r['id_procedimiento'],
                'descripcion'          => $texto,
                'cantidad'             => $r['cantidad'],
                'precio_unitario'      => $r['precio_unitario'],
                'descuento'            => $r['descuento'],
                'fecha'                => $plan['fecha'],
            ]);
            $creados++;
        }

        // Renglones que ya no estan en el plan.
        foreach ($porDetalle as $idDetalle => $cargo) {
            if (isset($vistos[$idDetalle])) { continue; }
            if ((float) $cargo['aplicado'] > 0) { $intocables++; continue; }

            self::cancelar((int) $cargo['id_cargo'],
                'El concepto se quito del plan.');
            $cancelados++;
        }

        return compact('creados', 'cancelados', 'intocables');
    }

    /** Cancela un cargo. No se borra: se marca y se deja el rastro. */
    public static function cancelar(int $idCargo, string $motivo): void
    {
        $saldo = Conexion::uno(
            'SELECT importe, aplicado FROM v_cargo_saldo WHERE id_cargo = :c',
            [':c' => $idCargo]
        );

        if ($saldo === null) {
            throw new RuntimeException('El cargo no existe o ya estaba cancelado.');
        }

        if ((float) $saldo['aplicado'] > 0) {
            throw new RuntimeException(
                'Este cargo ya tiene pagos aplicados. Cancela primero el cobro.');
        }

        Conexion::consulta(
            'UPDATE cargo
                SET estatus = 0, id_usuario_cancela = :u,
                    fecha_cancelacion = NOW(), motivo_cancelacion = :m
              WHERE id_cargo = :c',
            [':u' => Auth::id(), ':m' => mb_substr($motivo, 0, 255), ':c' => $idCargo]
        );

        Bitacora::registrar('cajas', 'BAJA', 'cargo', (string) $idCargo,
            ['motivo' => $motivo]);
    }

    /**
     * Cargos con saldo de un paciente, del mas viejo al mas nuevo.
     *
     * Ese orden no es casual: es el que usa la aplicacion automatica de
     * un abono. Lo que se debe desde hace mas tiempo se salda primero.
     */
    public static function pendientes(int $idExpediente, ?int $idPlan = null): array
    {
        $sql = 'SELECT vcs.*, pt.folio AS plan_folio, pt.descripcion AS plan_descripcion,
                       cn.folio AS consulta_folio
                  FROM v_cargo_saldo vcs
                  LEFT JOIN plan_tratamiento pt
                         ON pt.id_plan_tratamiento = vcs.id_plan_tratamiento
                  LEFT JOIN consulta cn ON cn.id_consulta = vcs.id_consulta
                 WHERE vcs.id_registro_paciente = :r AND vcs.saldo > 0';

        $params = [':r' => $idExpediente];

        if ($idPlan !== null) {
            $sql .= ' AND vcs.id_plan_tratamiento = :p';
            $params[':p'] = $idPlan;
        }

        $sql .= ' ORDER BY vcs.fecha, vcs.id_cargo';

        return Conexion::todos($sql, $params);
    }

    /** Todo el movimiento del paciente: cargos y pagos, para el estado de cuenta. */
    public static function estadoDeCuenta(int $idExpediente): array
    {
        $cargos = Conexion::todos(
            'SELECT vcs.*, pt.folio AS plan_folio, cn.folio AS consulta_folio,
                    c.cantidad, c.precio_unitario, c.descuento,
                    ud.nombre_completo AS autorizo_descuento
               FROM v_cargo_saldo vcs
               JOIN cargo c ON c.id_cargo = vcs.id_cargo
               LEFT JOIN plan_tratamiento pt
                      ON pt.id_plan_tratamiento = vcs.id_plan_tratamiento
               LEFT JOIN consulta cn ON cn.id_consulta = vcs.id_consulta
               LEFT JOIN usuario ud ON ud.id_usuario = c.id_usuario_descuento
              WHERE vcs.id_registro_paciente = :r
              ORDER BY vcs.fecha, vcs.id_cargo',
            [':r' => $idExpediente]
        );

        $pagos = Conexion::todos(
            'SELECT p.*, u.nombre_completo AS cobro,
                    uc.nombre_completo AS cancelo,
                    pt.folio AS plan_folio,
                    (SELECT GROUP_CONCAT(CONCAT(fp.nombre_forma, ": ", pf.monto)
                              ORDER BY pf.id_pago_forma SEPARATOR " · ")
                       FROM pago_forma pf
                       JOIN forma_pago fp ON fp.id_forma_pago = pf.id_forma_pago
                      WHERE pf.id_pago = p.id_pago) AS formas
               FROM pago p
               JOIN usuario u ON u.id_usuario = p.id_usuario
               LEFT JOIN usuario uc ON uc.id_usuario = p.id_usuario_cancela
               LEFT JOIN plan_tratamiento pt
                      ON pt.id_plan_tratamiento = p.id_plan_tratamiento
              WHERE p.id_registro_paciente = :r
              ORDER BY p.fecha_hora, p.id_pago',
            [':r' => $idExpediente]
        );

        $resumen = Conexion::uno(
            'SELECT * FROM v_paciente_saldo WHERE id_registro_paciente = :r',
            [':r' => $idExpediente]
        ) ?? ['cargos' => 0, 'cargado' => 0, 'pagado' => 0, 'saldo' => 0,
              'cargos_abiertos' => 0, 'adeudo_mas_antiguo' => null];

        return ['cargos' => $cargos, 'pagos' => $pagos, 'resumen' => $resumen];
    }
}
