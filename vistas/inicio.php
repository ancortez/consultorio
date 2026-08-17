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

$porCobrar = Conexion::todos(
    'SELECT vp.nombre_completo, vp.id_registro_paciente,
            ps.folio, ps.total, ps.pagado, ps.saldo_actual
       FROM v_plan_saldo ps
       JOIN v_paciente vp ON vp.id_registro_paciente = ps.id_registro_paciente
      WHERE ps.saldo_actual > 0
      ORDER BY ps.saldo_actual DESC
      LIMIT 6'
);

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

        <div class="card">
            <div class="card-header"><i class="bi bi-exclamation-circle me-2"></i>Saldos pendientes</div>
            <div class="list-group list-group-flush">
                <?php if (!$porCobrar): ?>
                    <div class="p-4 text-center text-secondary small">Sin saldos pendientes.</div>
                <?php else: foreach ($porCobrar as $p): ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                       href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= (int) $p['id_registro_paciente'] ?>">
                        <div>
                            <div class="fw-semibold" style="font-size:13px"><?= e($p['nombre_completo']) ?></div>
                            <div class="text-secondary" style="font-size:11px"><?= e($p['folio']) ?></div>
                        </div>
                        <span class="fw-semibold" style="color:var(--error)">
                            <?= e(money($p['saldo_actual'])) ?>
                        </span>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>
