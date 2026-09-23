<?php
/**
 * Estado de cuenta del paciente.
 *
 * Es lo primero que pide un tutor cuando hay dudas, y hoy la respuesta
 * salia de la memoria de recepcion. Va en dos bloques: los cargos con
 * lo que se le abono a cada uno, y despues los pagos con la forma en
 * que se hicieron. Asi se puede seguir el hilo en los dos sentidos.
 *
 * Uso:  /estado_cuenta.php?id=EXPEDIENTE
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

Auth::exigirLogin();
Auth::exigirPermiso('cajas.estado_cuenta');

$idExpediente = (int) ($_GET['id'] ?? 0);
if ($idExpediente <= 0) {
    http_response_code(400);
    exit('Falta el paciente.');
}

$paciente = Conexion::uno(
    'SELECT * FROM v_paciente WHERE id_registro_paciente = :r',
    [':r' => $idExpediente]
);

if ($paciente === null) {
    http_response_code(404);
    exit('El paciente no existe.');
}

$cuenta  = Cargos::estadoDeCuenta($idExpediente);
$resumen = $cuenta['resumen'];

$tutor = Conexion::uno(
    'SELECT CONCAT_WS(" ", p.nombres, p.ap_paterno, p.ap_materno) AS nombre
       FROM expediente_tutor et
       JOIN persona p ON p.id_persona = et.id_persona
      WHERE et.id_registro_paciente = :r AND et.estatus = 1
      ORDER BY et.es_responsable_pago DESC LIMIT 1',
    [':r' => $idExpediente]
);

$negocio = Conexion::uno(
    'SELECT c.nombre_comercial, c.telefono,
            CONCAT_WS(", ", CONCAT_WS(" ", d.calle, d.num_ext),
                      col.nombre_colonia, mun.nombre_municipio) AS domicilio
       FROM consultorio c
       LEFT JOIN domicilio d   ON d.id_domicilio   = c.id_domicilio
       LEFT JOIN colonia   col ON col.id_colonia   = d.id_colonia
       LEFT JOIN municipio mun ON mun.id_municipio = col.id_municipio
      WHERE c.id_consultorio = 1'
);

Bitacora::registrar('cajas', 'IMPRIME', 'estado_cuenta', (string) $idExpediente);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Estado de cuenta &middot; <?= e($paciente['nombre_completo']) ?></title>
<style>
    :root {
        --morado: #8b5a96;
        --morado-oscuro: #6b4574;
        --texto: #5a3d63;
        --linea: #d9cddd;
    }
    * { box-sizing: border-box; }

    body {
        margin: 0; background: #e9e4ec;
        font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        color: var(--texto); font-size: 9pt;
    }

    .hoja {
        width: 21.6cm; min-height: 27.9cm;
        margin: 1rem auto; background: #fff;
        box-shadow: 0 6px 30px rgba(0,0,0,.18);
        padding: 1.2cm; position: relative;
    }

    .cinta {
        display: flex; align-items: flex-start; justify-content: space-between;
        border-bottom: 2px solid var(--morado);
        padding-bottom: .3cm; margin-bottom: .45cm;
    }
    .marca .nombre { font-size: 14pt; font-weight: 700; color: var(--morado-oscuro); }
    .marca .dir { font-size: 7pt; color: #8a7a90; }
    .titulo-doc { text-align: right; }
    .titulo-doc .t { font-size: 13pt; font-weight: 700; color: var(--morado);
                     text-transform: uppercase; letter-spacing: .04em; }
    .titulo-doc .s { font-size: 8pt; }

    h2 {
        background: var(--morado); color: #fff; font-size: 8pt;
        text-transform: uppercase; letter-spacing: .06em;
        margin: .5cm 0 .2cm; padding: .12cm .35cm; border-radius: 3px;
        font-weight: 700;
    }

    .datos { display: flex; flex-wrap: wrap; gap: .1cm .6cm; }
    .dato { flex: 1 1 45%; display: flex; gap: .2cm; align-items: baseline;
            border-bottom: 1px dotted var(--linea); padding: .07cm 0; }
    .dato .et { font-size: 7pt; color: #8a7a90; white-space: nowrap; }
    .dato .va { font-weight: 600; color: var(--morado-oscuro); }

    /* El resumen va arriba porque es la pregunta de verdad; el detalle
       de abajo es para quien quiera revisar como se llego ahi. */
    .resumen {
        display: flex; gap: .4cm; margin: .35cm 0;
    }
    .resumen .caja {
        flex: 1; border: 1px solid var(--linea); border-radius: 7px;
        padding: .25cm .4cm; text-align: center;
    }
    .resumen .caja.saldo { border-color: var(--morado); border-width: 2px; }
    .resumen .et { display: block; font-size: 7pt; text-transform: uppercase;
                   letter-spacing: .05em; color: #8a7a90; }
    .resumen .va { display: block; font-size: 13pt; font-weight: 700;
                   color: var(--morado-oscuro); }

    table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
    table th {
        background: var(--morado); color: #fff; text-align: left;
        font-size: 6.5pt; text-transform: uppercase; letter-spacing: .04em;
        padding: .12cm .2cm;
    }
    table td { border-bottom: 1px solid var(--linea); padding: .14cm .2cm;
               vertical-align: top; }
    table tbody tr:nth-child(even) { background: #faf6fb; }
    table .der { text-align: right; }
    table tfoot td { font-weight: 700; border-top: 2px solid var(--morado);
                     border-bottom: 0; padding-top: .18cm; }

    .cancelado { color: #a094a5; text-decoration: line-through; }
    .chip { font-size: 6.5pt; text-transform: uppercase; letter-spacing: .04em;
            border-radius: 999px; padding: .02cm .25cm; }
    .chip.debe   { background: #fdecea; color: #c0392b; }
    .chip.pagado { background: #e6f5ee; color: #2e9e6b; }
    .chip.canc   { background: #f0ecf2; color: #8a7a90; }

    .nota {
        margin-top: .5cm; font-size: 7.5pt; line-height: 1.5; color: #7a6a80;
        border: 1px solid var(--linea); border-radius: 5px; padding: .25cm .35cm;
    }

    .pie { margin-top: .6cm; padding-top: .15cm; font-size: 6.5pt;
           color: #a094a5; border-top: 1px solid #eee4ec;
           display: flex; justify-content: space-between; }

    .barra {
        position: sticky; top: 0; background: #fff;
        border-bottom: 1px solid #ddd; padding: .7rem 1.2rem;
        display: flex; align-items: center; gap: .8rem;
        box-shadow: 0 2px 8px rgba(0,0,0,.08); z-index: 10; font-size: 13px;
    }
    .barra button, .barra a {
        border: 0; border-radius: 8px; padding: .5rem .9rem;
        font-size: 13px; font-weight: 500; cursor: pointer; text-decoration: none;
    }
    .barra .principal { background: #7b2d8e; color: #fff; }
    .barra .secundario { background: #f0ecf2; color: #5a3d63; }

    @page { size: letter; margin: 0; }

    @media print {
        body { background: #fff; }
        .barra { display: none !important; }
        .hoja { margin: 0; box-shadow: none; }
        table tr, .resumen { page-break-inside: avoid; break-inside: avoid; }
        thead { display: table-header-group; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>

<div class="barra">
    <button class="principal" onclick="window.print()">Imprimir o guardar como PDF</button>
    <a class="secundario"
       href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= $idExpediente ?>">
        Volver al expediente
    </a>
</div>

<div class="hoja">
    <div class="cinta">
        <div class="marca">
            <div class="nombre"><?= e($negocio['nombre_comercial'] ?? 'Consultorio Dental') ?></div>
            <div class="dir">
                <?= e($negocio['domicilio'] ?? '') ?>
                <?= $negocio['telefono'] ? ' &middot; Tel. ' . e($negocio['telefono']) : '' ?>
            </div>
        </div>
        <div class="titulo-doc">
            <div class="t">Estado de cuenta</div>
            <div class="s">Al <?= e(fechaLarga()) ?></div>
        </div>
    </div>

    <div class="datos">
        <div class="dato" style="flex:1 1 60%">
            <span class="et">Paciente</span>
            <span class="va"><?= e($paciente['nombre_completo']) ?></span>
        </div>
        <div class="dato" style="flex:0 0 5cm">
            <span class="et">Expediente</span>
            <span class="va"><?= e($paciente['num_expediente']) ?></span>
        </div>
        <div class="dato" style="flex:1 1 60%">
            <span class="et">Responsable</span>
            <span class="va"><?= e($tutor['nombre'] ?? '') ?></span>
        </div>
        <div class="dato" style="flex:0 0 5cm">
            <span class="et">Tel&eacute;fono</span>
            <span class="va"><?= e($paciente['telefono'] ?? '') ?></span>
        </div>
    </div>

    <div class="resumen">
        <div class="caja">
            <span class="et">Cargado</span>
            <span class="va"><?= e(money($resumen['cargado'] ?? 0)) ?></span>
        </div>
        <div class="caja">
            <span class="et">Pagado</span>
            <span class="va" style="color:#2e9e6b">
                <?= e(money($resumen['pagado'] ?? 0)) ?>
            </span>
        </div>
        <div class="caja saldo">
            <span class="et">Saldo</span>
            <span class="va" style="color:<?= (float) ($resumen['saldo'] ?? 0) > 0
                ? '#c0392b' : '#2e9e6b' ?>">
                <?= e(money($resumen['saldo'] ?? 0)) ?>
            </span>
        </div>
    </div>

    <h2>Cargos</h2>
    <table>
        <thead><tr>
            <th style="width:2.2cm">Fecha</th>
            <th>Concepto</th>
            <th style="width:2.4cm">Origen</th>
            <th style="width:2cm" class="der">Importe</th>
            <th style="width:2cm" class="der">Abonado</th>
            <th style="width:2.2cm" class="der">Saldo</th>
        </tr></thead>
        <tbody>
        <?php if (!$cuenta['cargos']): ?>
            <tr><td colspan="6" style="text-align:center;padding:.5cm;color:#a094a5">
                Sin cargos registrados.
            </td></tr>
        <?php else: foreach ($cuenta['cargos'] as $c): ?>
            <tr>
                <td><?= e(fecha($c['fecha'])) ?></td>
                <td>
                    <?= e($c['descripcion']) ?>
                    <?php if ((float) $c['descuento'] > 0): ?>
                        <div style="font-size:7pt;color:#8a7a90">
                            Descuento <?= e(money($c['descuento'])) ?>
                            <?= $c['autorizo_descuento']
                                ? ' &middot; autoriz&oacute; ' . e($c['autorizo_descuento']) : '' ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="font-size:7.5pt">
                    <?= e($c['plan_folio'] ?? '') ?>
                    <?= $c['consulta_folio']
                        ? '<div style="color:#8a7a90">' . e($c['consulta_folio']) . '</div>' : '' ?>
                </td>
                <td class="der"><?= e(money($c['importe'])) ?></td>
                <td class="der"><?= e(money($c['aplicado'])) ?></td>
                <td class="der">
                    <b><?= e(money($c['saldo'])) ?></b>
                    <?php if ((float) $c['saldo'] <= 0): ?>
                        <div><span class="chip pagado">Pagado</span></div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Totales</td>
                <td class="der"><?= e(money($resumen['cargado'] ?? 0)) ?></td>
                <td class="der"><?= e(money($resumen['pagado'] ?? 0)) ?></td>
                <td class="der"><?= e(money($resumen['saldo'] ?? 0)) ?></td>
            </tr>
        </tfoot>
    </table>

    <h2>Pagos</h2>
    <table>
        <thead><tr>
            <th style="width:2.6cm">Fecha</th>
            <th style="width:2.2cm">Recibo</th>
            <th>Formas de pago</th>
            <th style="width:3.2cm">Recibi&oacute;</th>
            <th style="width:2.2cm" class="der">Importe</th>
        </tr></thead>
        <tbody>
        <?php if (!$cuenta['pagos']): ?>
            <tr><td colspan="5" style="text-align:center;padding:.5cm;color:#a094a5">
                Sin pagos registrados.
            </td></tr>
        <?php else: foreach ($cuenta['pagos'] as $p):
            $canc = (int) $p['estatus'] === 0; ?>
            <tr class="<?= $canc ? 'cancelado' : '' ?>">
                <td><?= e(fechaHora($p['fecha_hora'])) ?></td>
                <td>
                    <?= e($p['folio']) ?>
                    <?php if ($canc): ?>
                        <div><span class="chip canc">Cancelado</span></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:7.5pt">
                    <?= e($p['formas'] ?? '') ?>
                    <?php if ($canc && $p['motivo_cancelacion']): ?>
                        <div style="color:#8a7a90">
                            <?= e($p['motivo_cancelacion']) ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="font-size:8pt"><?= e($p['cobro']) ?></td>
                <td class="der"><b><?= e(money($p['monto'])) ?></b></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php /* Un estado de cuenta sin fecha de corte se presta a que
             alguien lo guarde y lo presente meses despues como si
             siguiera vigente. */ ?>
    <div class="nota">
        Documento informativo, no es comprobante fiscal. Los importes
        est&aacute;n al
        <?= e(fechaHora(date('Y-m-d H:i:s'))) ?> y pueden cambiar con
        cualquier movimiento posterior. Los cobros cancelados se
        conservan tachados para dejar el rastro completo.
    </div>

    <div class="pie">
        <span>Expediente <?= e($paciente['num_expediente']) ?>
              &middot; impreso por <?= e(Auth::usuario()['nombre_completo'] ?? '') ?></span>
        <span><?= e(APP_NOMBRE) ?> v<?= e(APP_VERSION) ?></span>
    </div>
</div>

</body>
</html>
