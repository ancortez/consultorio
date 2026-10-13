<?php
/**
 * Corte de caja para imprimir y firmar.
 *
 * Lleva tres cosas, en este orden, porque es el orden en que se
 * revisan: el desglose por forma de pago (que dice cuanto hay que
 * encontrar en el cajon), el arqueo con la diferencia, y despues el
 * detalle movimiento por movimiento.
 *
 * Uso:  /corte_imprimir.php?id=ID_CORTE_CAJA
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

Auth::exigirLogin();
Auth::exigirPermiso('cajas.ver');

$idCorte = (int) ($_GET['id'] ?? 0);
if ($idCorte <= 0) {
    http_response_code(400);
    exit('Falta el turno.');
}

try {
    $corte = Caja::resumen($idCorte);
} catch (Throwable $e) {
    http_response_code(404);
    exit('El turno no existe.');
}

$movimientos = Caja::movimientos($idCorte);

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

$abierto = (int) $corte['abierto'] === 1;

// Con el turno abierto todavia no hay conteo: lo que se imprime es un
// parcial. Se dice claramente para que nadie lo archive como corte.
$contado    = $abierto ? null : (float) $corte['monto_contado'];
$diferencia = $abierto ? null : (float) $corte['diferencia'];

Bitacora::registrar('cajas', 'IMPRIME', 'corte_caja', (string) $idCorte);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Corte de caja &middot; <?= e($corte['nombre_caja']) ?></title>
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
        padding-bottom: .3cm; margin-bottom: .5cm;
    }
    .marca .nombre { font-size: 14pt; font-weight: 700; color: var(--morado-oscuro); }
    .marca .dir { font-size: 7pt; color: #8a7a90; }
    .titulo-doc { text-align: right; }
    .titulo-doc .t { font-size: 13pt; font-weight: 700; color: var(--morado);
                     text-transform: uppercase; letter-spacing: .04em; }
    .titulo-doc .s { font-size: 8pt; }

    .parcial {
        background: #fff6e5; border: 1px solid #f0d9a8; color: #7a5a12;
        border-radius: 6px; padding: .2cm .4cm; font-size: 8.5pt;
        margin-bottom: .4cm;
    }

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
    table tfoot td {
        font-weight: 700; border-top: 2px solid var(--morado);
        border-bottom: 0; padding-top: .18cm;
    }

    .arqueo {
        border: 2px solid var(--morado); border-radius: 8px;
        padding: .35cm .5cm; margin-top: .3cm;
    }
    .arqueo .renglon {
        display: flex; justify-content: space-between;
        padding: .1cm 0; font-size: 10pt;
    }
    .arqueo .renglon.total {
        border-top: 1px solid var(--linea); margin-top: .1cm;
        padding-top: .18cm; font-size: 12pt; font-weight: 700;
    }

    .firmas { display: flex; gap: 1.5cm; margin-top: 1.2cm; }
    .firma { flex: 1; text-align: center; }
    .firma .linea { border-top: 1px solid var(--morado); height: 1.1cm;
                    margin-bottom: .1cm; }
    .firma .nombre { font-weight: 600; font-size: 9pt; color: var(--morado-oscuro);
                     min-height: 1.1em; }
    .firma .cargo { font-size: 7pt; color: #8a7a90; }

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
        table tr, .arqueo, .firmas { page-break-inside: avoid; break-inside: avoid; }
        thead { display: table-header-group; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>

<div class="barra">
    <button class="principal" onclick="window.print()">Imprimir o guardar como PDF</button>
    <?php $volverA = URL_BASE . '/index.php?modulo=cajas&vista=corte'; ?>
    <?php require __DIR__ . '/includes/_boton_cerrar.php'; ?>
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
            <div class="t">Corte de caja</div>
            <div class="s"><?= e($corte['nombre_caja']) ?></div>
            <div class="s">Folio interno <?= (int) $idCorte ?></div>
        </div>
    </div>

    <?php if ($abierto): ?>
    <div class="parcial">
        <b>Corte parcial.</b> Este turno sigue abierto: los importes
        pueden cambiar y todav&iacute;a no hay conteo de efectivo. No lo
        archives como corte del d&iacute;a.
    </div>
    <?php endif; ?>

    <h2>Turno</h2>
    <div class="datos">
        <div class="dato">
            <span class="et">Apertura</span>
            <span class="va"><?= e(fechaHora($corte['fecha_apertura'])) ?></span>
        </div>
        <div class="dato">
            <span class="et">Abri&oacute;</span>
            <span class="va"><?= e($corte['abrio']) ?></span>
        </div>
        <div class="dato">
            <span class="et">Cierre</span>
            <span class="va">
                <?= $abierto ? 'Sigue abierto' : e(fechaHora($corte['fecha_cierre'])) ?>
            </span>
        </div>
        <div class="dato">
            <span class="et">Cerr&oacute;</span>
            <span class="va"><?= e($corte['cerro'] ?? '') ?></span>
        </div>
    </div>

    <h2>Desglose por forma de pago</h2>
    <table>
        <thead><tr>
            <th>Forma de pago</th>
            <th style="width:2cm" class="der">Entradas</th>
            <th style="width:2cm" class="der">Salidas</th>
            <th style="width:2.4cm" class="der">Neto</th>
            <th style="width:2.4cm">Afecta el caj&oacute;n</th>
        </tr></thead>
        <tbody>
        <?php if (!$corte['formas']): ?>
            <tr><td colspan="5" style="text-align:center;padding:.5cm;color:#a094a5">
                Sin movimientos en este turno.
            </td></tr>
        <?php else: foreach ($corte['formas'] as $f): ?>
            <tr>
                <td><?= e($f['nombre_forma']) ?></td>
                <td class="der"><?= e(money($f['ingresos'])) ?></td>
                <td class="der"><?= e(money($f['egresos'])) ?></td>
                <td class="der"><b><?= e(money($f['neto'])) ?></b></td>
                <td><?= (int) $f['afecta_efectivo'] === 1 ? 'S&iacute;' : 'No' ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td>Totales</td>
                <td class="der"><?= e(money($corte['total_ingresos'])) ?></td>
                <td class="der"><?= e(money($corte['total_egresos'])) ?></td>
                <td class="der">
                    <?= e(money($corte['total_ingresos'] - $corte['total_egresos'])) ?>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <h2>Arqueo de efectivo</h2>
    <div class="arqueo">
        <div class="renglon">
            <span>Efectivo que deber&iacute;a haber en el caj&oacute;n</span>
            <b><?= e(money($corte['efectivo_esperado'])) ?></b>
        </div>
        <div class="renglon">
            <span>Efectivo contado</span>
            <b><?= $contado === null ? '____________' : e(money($contado)) ?></b>
        </div>
        <div class="renglon total">
            <span>Diferencia</span>
            <span style="color:<?= $diferencia === null
                ? 'inherit'
                : (abs($diferencia) < 0.01 ? '#2e9e6b' : '#c0392b') ?>">
                <?= $diferencia === null ? '____________' : e(money($diferencia)) ?>
            </span>
        </div>
    </div>

    <?php if (!empty($corte['observaciones'])): ?>
        <h2>Observaciones</h2>
        <div style="font-size:8.5pt;line-height:1.6">
            <?= nl2br(e($corte['observaciones'])) ?>
        </div>
    <?php endif; ?>

    <h2>Movimientos del turno</h2>
    <table>
        <thead><tr>
            <th style="width:1.6cm">Hora</th>
            <th style="width:3.4cm">Concepto</th>
            <th>Descripci&oacute;n</th>
            <th style="width:2.8cm">Forma</th>
            <th style="width:2.2cm" class="der">Importe</th>
        </tr></thead>
        <tbody>
        <?php if (!$movimientos): ?>
            <tr><td colspan="5" style="text-align:center;padding:.5cm;color:#a094a5">
                Sin movimientos.
            </td></tr>
        <?php else: foreach ($movimientos as $m): ?>
            <tr>
                <td><?= e(substr((string) $m['fecha_hora'], 11, 5)) ?></td>
                <td><?= e($m['nombre_concepto']) ?></td>
                <td>
                    <?= e($m['descripcion'] ?? '') ?>
                    <?php if ($m['paciente']): ?>
                        <div style="font-size:7pt;color:#8a7a90">
                            <?= e($m['paciente']) ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td><?= e($m['nombre_forma']) ?></td>
                <td class="der">
                    <?= $m['naturaleza'] === 'I' ? '' : '&minus;' ?><?= e(money($m['monto'])) ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="firmas">
        <div class="firma">
            <div class="linea"></div>
            <div class="nombre"><?= e($corte['cerro'] ?? $corte['abrio']) ?></div>
            <div class="cargo">Entrega</div>
        </div>
        <div class="firma">
            <div class="linea"></div>
            <div class="nombre"></div>
            <div class="cargo">Recibe</div>
        </div>
    </div>

    <div class="pie">
        <span>Impreso el <?= e(fechaHora(date('Y-m-d H:i:s'))) ?>
              por <?= e(Auth::usuario()['nombre_completo'] ?? '') ?></span>
        <span><?= e(APP_NOMBRE) ?> v<?= e(APP_VERSION) ?></span>
    </div>
</div>

</body>
</html>
