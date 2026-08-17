<?php
/**
 * Marcador para las vistas que aun no se desarrollan.
 * Sirve para que la demo se pueda navegar completa sin errores 404 y
 * para que el cliente vea el alcance planeado del sistema.
 */
$alcance = [
    'consultas' => [
        'Agenda con vista de dia, semana y por dentista',
        'Registro de consulta con signos vitales y diagnostico',
        'Odontograma interactivo (hallazgos y tratamientos)',
        'Planes de tratamiento con avance por concepto',
    ],
    'cajas' => [
        'Apertura y cierre de caja con arqueo',
        'Cobros con pago mixto (efectivo, tarjeta, transferencia)',
        'Registro de egresos operativos',
        'Corte de caja imprimible',
    ],
    'reportes' => [
        'Ingresos por periodo, dentista y forma de pago',
        'Tratamientos mas realizados',
        'Pacientes nuevos vs. recurrentes',
        'Cuentas por cobrar y antiguedad de saldos',
    ],
    'configuracion' => [
        'Usuarios, roles y permisos',
        'Tarifario de tratamientos',
        'Datos del consultorio y catalogos',
        'Consulta de bitacora de auditoria',
    ],
    'facturacion' => [
        'Datos fiscales del paciente (CFDI 4.0)',
        'Emision y timbrado de facturas',
        'Cancelacion y descarga de XML/PDF',
        'Relacion de facturas por periodo',
    ],
];
$lista = $alcance[$modulo] ?? [];
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="titulo-vista"><?= e($ruta['titulo']) ?></h1>
        <p class="subtitulo-vista">M&oacute;dulo <?= e(ucfirst($modulo)) ?></p>
    </div>
</div>

<div class="card">
    <div class="card-body text-center py-5">
        <div style="width:72px;height:72px;margin:0 auto 1.2rem;border-radius:20px;
                    background:var(--rosa-100);color:var(--morado-600);
                    display:flex;align-items:center;justify-content:center;font-size:2rem">
            <i class="bi bi-cone-striped"></i>
        </div>
        <h2 class="h5 fw-semibold" style="color:var(--morado-800)">Pendiente de desarrollo</h2>
        <p class="text-secondary mb-4" style="max-width:520px;margin:0 auto">
            Esta secci&oacute;n forma parte del alcance del sistema y se construir&aacute;
            en una fase posterior. Aqu&iacute; se muestra lo que incluir&aacute;.
        </p>

        <?php if ($lista): ?>
        <div class="d-inline-block text-start">
            <?php foreach ($lista as $l): ?>
                <div class="d-flex align-items-center gap-2 py-1">
                    <i class="bi bi-check-circle" style="color:var(--morado-500)"></i>
                    <span><?= e($l) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
