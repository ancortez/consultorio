<?php
/**
 * Tablero de inicio.
 * MEJORA propuesta: la composicion no definia que mostrar en "Inicio".
 * Un tablero con los numeros del dia es lo primero que quiere ver quien
 * abre el sistema en la manana, y es lo que mejor se ve en una demo.
 */

$hoy = date('Y-m-d');

/**
 * Quien ve que citas.
 *
 * El administrador y recepcion ven la agenda completa del consultorio:
 * son quienes coordinan. Un dentista ve solo las suyas, porque su
 * pantalla de inicio le sirve para saber a quien atiende hoy, no para
 * revisar el trabajo de los demas.
 *
 * Se decide por el rol, no por permisos sueltos, porque es una regla de
 * presentacion y no de seguridad: cualquiera con consultas.ver puede
 * entrar a la agenda completa desde el menu.
 */
$soloMisCitas = $usuarioSesion['es_dentista']
    && (int) $usuarioSesion['id_rol'] !== 1
    && !Auth::puede('consultas.agenda');

$sqlCitas = 'SELECT c.id_cita, c.fecha_inicio, c.motivo,
            ec.nombre_estado, ec.color,
            vp.nombre_completo, vp.id_registro_paciente, vp.num_expediente,
            u.nombre_completo AS dentista
       FROM cita c
       JOIN v_paciente vp ON vp.id_registro_paciente = c.id_registro_paciente
       JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
       JOIN usuario u ON u.id_usuario = c.id_usuario_dentista
      WHERE DATE(c.fecha_inicio) = :hoy';

$paramsCitas = [':hoy' => $hoy];

if ($soloMisCitas) {
    $sqlCitas .= ' AND c.id_usuario_dentista = :yo';
    $paramsCitas[':yo'] = Auth::id();
}

$sqlCitas .= ' ORDER BY c.fecha_inicio';

$citasHoy = Conexion::todos($sqlCitas, $paramsCitas);

$kpi = [
    'pacientes' => (int) Conexion::valor(
        'SELECT COUNT(*) FROM registro_paciente WHERE estatus = 1'),
    'citas_hoy' => count($citasHoy),
    'consultas_mes' => (int) Conexion::valor(
        'SELECT COUNT(*) FROM consulta
          WHERE YEAR(fecha_hora) = YEAR(CURDATE())
            AND MONTH(fecha_hora) = MONTH(CURDATE())'),
    'ingresos_hoy' => (float) Conexion::valor(
        'SELECT COALESCE(SUM(monto),0) FROM pago
          WHERE DATE(fecha_hora) = :hoy AND estatus = 1', [':hoy' => $hoy]),
];

/**
 * Pendientes de pago.
 *
 * Se agrupa por paciente y no por plan: en el mostrador se cobra a una
 * persona, no a un folio, y un paciente con dos planes abiertos
 * aparecia dos veces pidiendo dos cobros que en realidad son uno.
 *
 * Ordenado por antiguedad del adeudo: entre mas tiempo pasa, mas
 * dificil es recuperarlo.
 */
$porCobrar = Auth::puede('cajas.ver')
    ? Conexion::todos(
        'SELECT vp.nombre_completo, vp.id_registro_paciente, vp.telefono,
                vp.num_expediente, vps.saldo, vps.cargos_abiertos,
                vps.adeudo_mas_antiguo,
                DATEDIFF(CURDATE(), vps.adeudo_mas_antiguo) AS dias
           FROM v_paciente_saldo vps
           JOIN v_paciente vp ON vp.id_registro_paciente = vps.id_registro_paciente
          WHERE vps.saldo > 0
          ORDER BY vps.adeudo_mas_antiguo, vps.saldo DESC
          LIMIT 8')
    : [];

$totalPorCobrar = (float) (Conexion::valor(
    'SELECT COALESCE(SUM(saldo), 0) FROM v_paciente_saldo WHERE saldo > 0') ?? 0);

/**
 * Citas por confirmar.
 *
 * Nacen en Pendiente y se quedan asi hasta que alguien le llama al
 * paciente. Sin este tablero ese paso se olvida y el consultorio se
 * entera de que nadie va a llegar hasta que la silla esta vacia.
 *
 * Se listan desde hoy hacia adelante: confirmar una cita de ayer no
 * sirve de nada.
 */
$sqlPorConfirmar = 'SELECT c.id_cita, c.fecha_inicio, c.motivo,
            vp.nombre_completo, vp.telefono, vp.id_registro_paciente,
            u.nombre_completo AS dentista
       FROM cita c
       JOIN v_paciente vp ON vp.id_registro_paciente = c.id_registro_paciente
       JOIN usuario u ON u.id_usuario = c.id_usuario_dentista
      WHERE c.id_estado_cita = 1
        AND c.fecha_inicio >= NOW()';

$paramsPorConfirmar = [];
if ($soloMisCitas) {
    $sqlPorConfirmar .= ' AND c.id_usuario_dentista = :yo';
    $paramsPorConfirmar[':yo'] = Auth::id();
}
$sqlPorConfirmar .= ' ORDER BY c.fecha_inicio LIMIT 12';

$porConfirmar = Conexion::todos($sqlPorConfirmar, $paramsPorConfirmar);

/**
 * Recordatorios que toca hacer.
 *
 * Es el trabajo que se olvida: el paciente confirmo hace dos semanas y
 * nadie volvio a llamarle. El tablero los pone enfrente con el mensaje
 * ya armado.
 */
$recordatorios = Auth::puede('consultas.ver')
    ? AgendaExtra::pendientes($soloMisCitas ? (int) Auth::id() : null)
    : [];

$nombreConsultorio = (string) (Conexion::valor(
    'SELECT nombre_comercial FROM consultorio WHERE id_consultorio = 1'
) ?? 'el consultorio');

$cajaAbierta = Conexion::uno(
    'SELECT cc.*, u.nombre_completo AS abrio
       FROM corte_caja cc
       JOIN usuario u ON u.id_usuario = cc.id_usuario_apertura
      WHERE cc.abierto = 1
      ORDER BY cc.fecha_apertura DESC LIMIT 1'
);

$tarjetas = [
    ['titulo' => 'Pacientes activos',  'valor' => number_format($kpi['pacientes']),
     'icono' => 'bi-people-fill',        'color' => 'var(--morado-600)'],
    ['titulo' => 'Citas de hoy',       'valor' => $kpi['citas_hoy'],
     'icono' => 'bi-calendar-check-fill','color' => 'var(--rosa-400)'],
    ['titulo' => 'Consultas del mes',  'valor' => number_format($kpi['consultas_mes']),
     'icono' => 'bi-clipboard2-pulse-fill', 'color' => 'var(--morado-800)'],
    ['titulo' => 'Ingresos de hoy',    'valor' => money($kpi['ingresos_hoy']),
     'icono' => 'bi-cash-stack',        'color' => 'var(--exito)'],
];
?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="titulo-vista">Buen d&iacute;a, <?= e(explode(' ', $usuarioSesion['nombre_completo'])[0]) ?></h1>
        <p class="subtitulo-vista"><?= e(fechaLarga()) ?></p>
    </div>
    <?php if (Auth::puede('paciente.crear')): ?>
    <button class="btn btn-morado" data-accion="nuevo-paciente">
        <i class="bi bi-person-plus me-1"></i> Nuevo paciente
    </button>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($tarjetas as $t): ?>
    <div class="col-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:48px;height:48px;border-radius:12px;
                            background:<?= $t['color'] ?>;color:#fff;
                            display:flex;align-items:center;justify-content:center;font-size:1.35rem">
                    <i class="bi <?= $t['icono'] ?>"></i>
                </div>
                <div>
                    <div class="text-secondary" style="font-size:12px"><?= e($t['titulo']) ?></div>
                    <div class="fs-4 fw-semibold" style="color:var(--morado-900)"><?= e((string) $t['valor']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-calendar3 me-2"></i>Agenda de hoy
                    <span class="badge badge-suave ms-1">
                        <?= $soloMisCitas ? 'Mis citas' : 'Todo el consultorio' ?>
                    </span>
                </span>
                <a class="btn btn-sm btn-outline-morado"
                   href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=agenda">Ver agenda completa</a>
            </div>
            <div class="table-responsive">
                <table class="table tabla-sistema">
                    <thead>
                        <tr>
                            <th style="width:90px">Hora</th>
                            <th>Paciente</th>
                            <th>Motivo</th>
                            <th>Dentista</th>
                            <th style="width:120px">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$citasHoy): ?>
                        <tr class="fila-vacia">
                            <td colspan="5">
                                <i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-50"></i>
                                No hay citas agendadas para hoy.
                            </td>
                        </tr>
                    <?php else: foreach ($citasHoy as $c): ?>
                        <tr>
                            <td class="fw-semibold"><?= e(fecha($c['fecha_inicio'], 'H:i')) ?></td>
                            <td>
                                <a href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= (int) $c['id_registro_paciente'] ?>"
                                   class="text-decoration-none fw-semibold">
                                    <?= e($c['nombre_completo']) ?>
                                </a>
                                <div class="text-secondary" style="font-size:11px"><?= e($c['num_expediente']) ?></div>
                            </td>
                            <td class="text-secondary"><?= e($c['motivo']) ?></td>
                            <td class="text-secondary"><?= e($c['dentista']) ?></td>
                            <td>
                                <span class="chip-estado"
                                      style="background:<?= e($c['color']) ?>1f;color:<?= e($c['color']) ?>">
                                    <?= e($c['nombre_estado']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-4">
        <?php if (Auth::puede('cajas.ver')): ?>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-cash-coin me-2"></i>Estado de caja</div>
            <div class="card-body">
                <?php if ($cajaAbierta): ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="chip-estado" style="background:#e6f5ee;color:var(--exito)">ABIERTA</span>
                        <span class="text-secondary small">
                            desde <?= e(fecha($cajaAbierta['fecha_apertura'], 'H:i')) ?>
                        </span>
                    </div>
                    <div class="dato-identidad">
                        <i class="bi bi-person"></i>
                        <div><div class="etiqueta">Abri&oacute;</div>
                             <div class="valor"><?= e($cajaAbierta['abrio']) ?></div></div>
                    </div>
                    <div class="dato-identidad">
                        <i class="bi bi-wallet2"></i>
                        <div><div class="etiqueta">Fondo inicial</div>
                             <div class="valor"><?= e(money($cajaAbierta['monto_inicial'])) ?></div></div>
                    </div>
                <?php else: ?>
                    <p class="text-secondary small mb-2">No hay una caja abierta.</p>
                    <button class="btn btn-sm btn-morado w-100">Abrir caja</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (Auth::puede('consultas.ver')): ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-telephone me-2"></i>Citas por confirmar
                    <?php if ($porConfirmar): ?>
                        <span class="badge badge-pendiente ms-1"><?= count($porConfirmar) ?></span>
                    <?php endif; ?>
                </span>
                <a class="btn btn-sm btn-outline-morado"
                   href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=agenda">Agenda</a>
            </div>
            <div id="listaPorConfirmar">
                <?php if (!$porConfirmar): ?>
                    <div class="p-4 text-center text-secondary small">
                        <i class="bi bi-check2-circle fs-4 d-block mb-1" style="color:var(--exito)"></i>
                        Todas las citas est&aacute;n confirmadas.
                    </div>
                <?php else: foreach ($porConfirmar as $pc): ?>
                    <div class="cita-pendiente" data-cita="<?= (int) $pc['id_cita'] ?>">
                        <div class="cuando">
                            <b><?= e(fecha($pc['fecha_inicio'], 'd/m')) ?></b>
                            <?= e(fecha($pc['fecha_inicio'], 'H:i')) ?>
                        </div>
                        <div class="flex-grow-1" style="min-width:0">
                            <a class="fw-semibold text-decoration-none d-block text-truncate"
                               style="font-size:13px"
                               href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= (int) $pc['id_registro_paciente'] ?>">
                                <?= e($pc['nombre_completo']) ?>
                            </a>
                            <div class="text-secondary text-truncate" style="font-size:11px">
                                <?php if (!empty($pc['telefono'])): ?>
                                    <i class="bi bi-telephone"></i> <?= e($pc['telefono']) ?> &middot;
                                <?php endif; ?>
                                <?= e($pc['dentista']) ?>
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-morado"
                                    data-confirmar="<?= (int) $pc['id_cita'] ?>"
                                    title="Confirmar cita">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-light text-danger"
                                    data-cancelar="<?= (int) $pc['id_cita'] ?>"
                                    title="Cancelar cita">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (Auth::puede('cajas.ver')): ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-cash-coin me-2"></i>Pendientes de pago
                    <?php if ($porCobrar): ?>
                        <span class="badge badge-pendiente ms-1"><?= count($porCobrar) ?></span>
                    <?php endif; ?>
                </span>
                <a class="btn btn-sm btn-outline-morado"
                   href="<?= URL_BASE ?>/index.php?modulo=cajas&vista=pendientes">
                    Ver todos
                </a>
            </div>

            <?php if ($totalPorCobrar > 0): ?>
            <div class="px-3 pt-2">
                <div class="resumen-saldo">
                    <div>
                        <span class="et">Total por cobrar</span>
                        <span class="va fs-5" style="color:var(--error)">
                            <?= e(money($totalPorCobrar)) ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="list-group list-group-flush">
                <?php if (!$porCobrar): ?>
                    <div class="p-4 text-center text-secondary small">
                        <i class="bi bi-check2-circle fs-3 d-block mb-2"
                           style="color:var(--exito)"></i>
                        Nadie debe nada.
                    </div>
                <?php else: foreach ($porCobrar as $p): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div style="min-width:0">
                            <a class="enlace-paciente" style="font-size:13px"
                               href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= (int) $p['id_registro_paciente'] ?>">
                                <?= e($p['nombre_completo']) ?>
                            </a>
                            <div class="text-secondary" style="font-size:11px">
                                <?= (int) $p['cargos_abiertos'] ?> concepto(s)
                                <?php /* Un adeudo de mas de un mes se marca:
                                         es el que hay que atender primero. */ ?>
                                <?php if ((int) $p['dias'] > 30): ?>
                                    &middot; <span style="color:var(--error)">
                                        hace <?= (int) $p['dias'] ?> d&iacute;as</span>
                                <?php else: ?>
                                    &middot; hace <?= (int) $p['dias'] ?> d&iacute;a(s)
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold" style="color:var(--error)">
                                <?= e(money($p['saldo'])) ?>
                            </div>
                            <?php if (Auth::puede('cajas.cobrar')): ?>
                            <a class="btn btn-sm btn-morado mt-1"
                               href="<?= URL_BASE ?>/index.php?modulo=cajas&vista=cobrar&paciente=<?= (int) $p['id_registro_paciente'] ?>">
                                Cobrar
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php /* ============ Recordatorios ============ */ ?>
        <?php if (Auth::puede('consultas.ver')): ?>
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-whatsapp me-2"></i>Recordatorios de hoy
                    <?php if ($recordatorios): ?>
                        <span class="badge badge-pendiente ms-1"><?= count($recordatorios) ?></span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="list-group list-group-flush" id="listaRecordatorios">
                <?php if (!$recordatorios): ?>
                    <div class="p-4 text-center text-secondary small">
                        <i class="bi bi-check2-circle fs-3 d-block mb-2"
                           style="color:var(--exito)"></i>
                        No hay recordatorios pendientes.
                    </div>
                <?php else: foreach ($recordatorios as $r):
                    $tel = preg_replace('/\D+/', '', (string) ($r['telefono'] ?? ''));
                    if (strlen($tel) === 10) { $tel = '52' . $tel; }
                    $mensaje = AgendaExtra::mensaje($r, $nombreConsultorio);
                ?>
                    <div class="list-group-item recordatorio" data-cita="<?= (int) $r['id_cita'] ?>"
                         data-regla="<?= (int) $r['id_regla'] ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div style="min-width:0">
                                <a class="enlace-paciente" style="font-size:13px"
                                   href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= (int) $r['id_registro_paciente'] ?>">
                                    <?= e($r['paciente']) ?>
                                </a>
                                <div class="text-secondary" style="font-size:11px">
                                    <?= e(fechaHora($r['fecha_inicio'])) ?>
                                    &middot; <?= e($r['dentista']) ?>
                                </div>
                                <div style="font-size:10.5px">
                                    <span class="chip-estado badge-pendiente">
                                        <?= e($r['regla']) ?>
                                    </span>
                                    <span class="text-secondary">
                                        faltan <?= (int) round($r['horas_faltan']) ?> h
                                    </span>
                                </div>
                            </div>

                            <div class="d-flex flex-column gap-1 align-items-end">
                                <?php if ($tel !== ''): ?>
                                <a class="btn btn-sm btn-whatsapp" target="_blank"
                                   rel="noopener"
                                   href="https://wa.me/<?= e($tel) ?>?text=<?= rawurlencode($mensaje) ?>"
                                   data-accion="rec-enviar">
                                    <i class="bi bi-whatsapp"></i> Enviar
                                </a>
                                <?php else: ?>
                                <span class="text-secondary" style="font-size:10.5px">
                                    Sin tel&eacute;fono
                                </span>
                                <?php endif; ?>

                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-light"
                                            data-accion="rec-hecho" title="Ya le avis&eacute;">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light"
                                            data-accion="rec-nocontesto" title="No contest&oacute;">
                                        <i class="bi bi-telephone-x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
