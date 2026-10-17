<?php
declare(strict_types=1);

/**
 * Cargos: lo que el paciente debe.
 *
 * Un cargo puede nacer de tres lados y los tres terminan igual:
 *
 *   - Del plan de tratamiento, en cuanto el plan se guarda.
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
    /*
       Un plan genera cargos desde que se guarda. Solo el cancelado no.

       Hasta el 17 de octubre de 2026 esto empezaba en Aceptado, con el
       argumento de que un plan propuesto es una cotizacion y cobrarla
       seria adelantarse. Suena bien y no era lo que pasaba en el
       consultorio: la doctora captura el plan, lo imprime, lo explica, y
       el tratamiento arranca ese mismo dia. El cargo no aparecia hasta
       que alguien escaneaba la hoja firmada y volvia a guardar el plan
       —tres pasos despues y a veces otro dia—, asi que en el mostrador
       el paciente no debia nada por un trabajo que ya se le estaba
       haciendo.

       Angel lo reporto justo asi: "no me los pone pendientes hasta que
       escaneo, me meto al plan y guardo otra vez".

       Que un plan propuesto ya genere pendientes es lo que se pidio y
       tiene su contrapeso: un plan que al final no se acepta se
       cancela, y cancelarlo retira sus cargos sin pagos (ver la rama de
       !$cobrable mas abajo). Asi que el plan que no llega a nada
       limpia lo suyo, y mientras tanto el mostrador ve lo que de
       verdad se esta haciendo.

       El 5 —cancelado— se queda fuera a proposito: ahi el plan dejo de
       existir para el consultorio.
    */
    public const ESTADOS_COBRABLES = [1, 2, 3, 4];  // todos menos Cancelado

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
     * Se llama al guardar el plan, y genera cargos para cualquier
     * estado menos el cancelado: ver ESTADOS_COBRABLES.
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
            // El plan se cancelo: se retiran los cargos que nadie ha
            // pagado. Es la contrapartida de que un plan recien
            // capturado ya genere pendientes —el que no llega a nada
            // limpia lo suyo—.
            foreach ($actuales as $a) {
                if ((float) $a['aplicado'] > 0) { $intocables++; continue; }
                self::cancelar((int) $a['id_cargo'],
                    'Se cancelo el plan de tratamiento.');
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

        /*
           El saldo A FAVOR se calcula aqui, porque la vista no lo tiene.

           v_paciente_saldo suma CARGOS: lo cargado, lo que de eso se
           aplico, y lo que falta. El dinero que entro y todavia no
           encontro cargo donde caer —un anticipo, un cobro de mas— no
           aparece en ninguna de esas tres columnas.
           
           El resultado era que un paciente con dinero a favor veia su
           estado de cuenta en ceros: ni debe ni le deben, segun el
           papel. Y a favor del paciente es justo el saldo que nadie
           debe poder "perder de vista".

           Sale de restar lo aplicado a lo cobrado. Los pagos
           cancelados no cuentan: su dinero se devolvio.
        */
        $cobrado = 0.0;
        foreach ($pagos as $pg) {
            if ((int) $pg['estatus'] === 1) {
                $cobrado += (float) $pg['monto'];
            }
        }

        $resumen['cobrado']  = round($cobrado, 2);
        $resumen['a_favor']  = round(
            max(0, $cobrado - (float) ($resumen['pagado'] ?? 0)), 2);

        return ['cargos' => $cargos, 'pagos' => $pagos, 'resumen' => $resumen];
    }

    /**
     * Como va un plan de tratamiento: lo pactado, lo hecho y lo pagado.
     *
     * Lo pidio la doctora con un caso concreto: el paciente adelanta un
     * porcentaje al aceptar el plan, vuelve tres semanas despues, y en
     * el mostrador nadie se acuerda de cuanto dejo. Se discute, se
     * revisa a mano, y a veces se le cobra de mas.
     *
     * Son CUATRO numeros y cada uno contesta una pregunta distinta. Que
     * parezcan parecidos es justo lo que hace que se confundan:
     *
     *   total       lo que se pacto por todo el tratamiento
     *   anticipado  dinero de este plan que todavia no se aplico a nada
     *   realizado   lo que ya se hizo, en dinero (la suma de sus cargos)
     *   cubierto    de lo realizado, cuanto ya quedo pagado
     *
     * Y de ahi salen los dos que se usan en el mostrador:
     *
     *   por_cobrar  lo que hay que cobrar HOY, ya descontando el
     *               anticipo disponible
     *   resta       lo que falta por hacer del tratamiento, que se
     *               cobrara mas adelante
     *
     * Por que el anticipo no es simplemente "saldo a favor": el saldo a
     * favor es del PACIENTE y puede venir de cualquier lado —un cobro
     * de mas, una cita cancelada—. El anticipo esta ligado a un plan
     * concreto, y eso cambia la conversacion: "dejo dos mil a cuenta de
     * su ortodoncia" no es lo mismo que "le sobran dos mil".
     */
    public static function avancePlan(int $idPlan): array
    {
        $plan = Conexion::uno(
            'SELECT id_plan_tratamiento, total, id_registro_paciente
               FROM plan_tratamiento
              WHERE id_plan_tratamiento = :p',
            [':p' => $idPlan]
        );

        if ($plan === null) {
            return [];
        }

        // Lo que ya se hizo y lo que de eso se cubrio. Sale de la vista
        // de saldos, que es la misma que usa el estado de cuenta: dos
        // cuentas separadas del mismo numero acaban diciendo cosas
        // distintas.
        $cargos = Conexion::uno(
            'SELECT COALESCE(SUM(importe), 0)  AS realizado,
                    COALESCE(SUM(aplicado), 0) AS cubierto
               FROM v_cargo_saldo
              WHERE id_plan_tratamiento = :p',
            [':p' => $idPlan]
        ) ?? ['realizado' => 0, 'cubierto' => 0];

        // Todo el dinero que entro a cuenta de ESTE plan.
        $cobrado = (float) Conexion::valor(
            'SELECT COALESCE(SUM(monto), 0) FROM pago
              WHERE id_plan_tratamiento = :p AND estatus = 1',
            [':p' => $idPlan]
        );

        $realizado = (float) $cargos['realizado'];
        $cubierto  = (float) $cargos['cubierto'];
        $total     = (float) $plan['total'];

        /*
           El anticipo es lo cobrado del plan que todavia no se aplico.

           No se guarda en ninguna columna a proposito: se deduce. Una
           columna "anticipo" habria que mantenerla al cobrar, al
           cancelar un pago y al aplicar dinero a un cargo, y a la
           tercera se desincroniza con los pagos de verdad. Deducirlo no
           se puede desincronizar.
        */
        $anticipo = round(max(0, $cobrado - $cubierto), 2);

        // Lo que falta cobrar de lo YA HECHO, despues de echarle mano
        // al anticipo. Es el numero del mostrador.
        $pendienteDeLoHecho = max(0, $realizado - $cubierto);
        $porCobrar = round(max(0, $pendienteDeLoHecho - $anticipo), 2);

        return [
            'id_plan_tratamiento' => (int) $plan['id_plan_tratamiento'],
            'total'      => round($total, 2),
            'anticipado' => $anticipo,
            'realizado'  => round($realizado, 2),
            'cubierto'   => round($cubierto, 2),
            'por_cobrar' => $porCobrar,
            // Lo que falta por HACER del tratamiento. Puede salir
            // negativo si se agregaron procedimientos despues de
            // firmar: en ese caso no hay nada "por hacer" pendiente del
            // pacto original, y decir -500 confundiria.
            'resta'      => round(max(0, $total - $realizado), 2),
            // Un plan donde ya se hizo mas de lo pactado merece decirlo
            // en vez de esconderlo detras de un cero.
            'excedido'   => $realizado > $total + 0.009,
        ];
    }
}
