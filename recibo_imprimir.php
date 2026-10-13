<?php
/**
 * Recibo de cobro.
 *
 * Sale en media carta apaisada y por duplicado en la misma hoja: una
 * mitad para el tutor y otra para el expediente. Es mas barato en
 * papel y evita la escena de "es que no me dieron copia".
 *
 * El folio es RC-####, una serie interna del consultorio. Cuando entre
 * la facturacion, el CFDI llevara su propia serie y su UUID: no se
 * mezclan a proposito, porque un recibo interno y un comprobante
 * fiscal no son lo mismo y confundir los folios se paga caro.
 *
 * Uso:  /recibo_imprimir.php?id=ID_PAGO
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

Auth::exigirLogin();
Auth::exigirPermiso('cajas.ver');

$idPago = (int) ($_GET['id'] ?? 0);
if ($idPago <= 0) {
    http_response_code(400);
    exit('Falta el cobro.');
}

$pago = Conexion::uno(
    'SELECT p.*, vp.nombre_completo AS paciente, vp.num_expediente,
            u.nombre_completo AS cobro, c.nombre_caja,
            pt.folio AS plan_folio, pt.descripcion AS plan_descripcion
       FROM pago p
       JOIN v_paciente vp ON vp.id_registro_paciente = p.id_registro_paciente
       JOIN usuario u ON u.id_usuario = p.id_usuario
       JOIN corte_caja cc ON cc.id_corte_caja = p.id_corte_caja
       JOIN caja c ON c.id_caja = cc.id_caja
       LEFT JOIN plan_tratamiento pt
              ON pt.id_plan_tratamiento = p.id_plan_tratamiento
      WHERE p.id_pago = :p',
    [':p' => $idPago]
);

if ($pago === null) {
    http_response_code(404);
    exit('El cobro no existe.');
}

$formas = Conexion::todos(
    'SELECT pf.monto, pf.referencia, fp.nombre_forma
       FROM pago_forma pf
       JOIN forma_pago fp ON fp.id_forma_pago = pf.id_forma_pago
      WHERE pf.id_pago = :p ORDER BY pf.id_pago_forma',
    [':p' => $idPago]
);

/*
   A que cargos se aplico este cobro.

   Son DOS cosas que caen en la misma tabla:

     - lo que cubrio el dinero que entro hoy  (pa.id_pago = este)
     - lo que cubrio su saldo a favor         (pa.aplicada_en_pago = este)

   Las segundas estan a nombre del pago viejo que trajo ese dinero, y
   por eso antes no salian en la ficha: el paciente recibia un recibo
   por 600 habiendo una cuenta de 1,000 y el papel no decia de donde
   salieron los otros 400.

   Van juntas y marcadas, porque para quien lee la ficha son el mismo
   hecho —"esto es lo que quedo cubierto hoy"— y lo que cambia es de
   donde salio el dinero.
*/
$aplicaciones = Conexion::todos(
    'SELECT pa.monto, c.descripcion, c.fecha,
            (pa.aplicada_en_pago IS NOT NULL) AS de_saldo_a_favor
       FROM pago_aplicacion pa
       JOIN cargo c ON c.id_cargo = pa.id_cargo
      WHERE pa.id_pago = :p OR pa.aplicada_en_pago = :en
      ORDER BY c.fecha, c.id_cargo',
    [':p' => $idPago, ':en' => $idPago]
);

// Cuanto de lo cubierto hoy salio del saldo a favor. Es el renglon
// negativo de la ficha y lo que hace que los numeros cuadren: lo
// cubierto menos el saldo aplicado tiene que dar lo recibido.
$aFavorAplicado = 0.0;
$totalCubierto  = 0.0;
foreach ($aplicaciones as $a) {
    $totalCubierto += (float) $a['monto'];
    if ((int) $a['de_saldo_a_favor'] === 1) {
        $aFavorAplicado += (float) $a['monto'];
    }
}
$totalCubierto  = round($totalCubierto, 2);
$aFavorAplicado = round($aFavorAplicado, 2);

// Saldo despues del cobro: es la pregunta que hace el tutor al recibir
// el papel, asi que viene contestada de fabrica.
$saldo = Conexion::uno(
    'SELECT saldo FROM v_paciente_saldo WHERE id_registro_paciente = :r',
    [':r' => $pago['id_registro_paciente']]
);

$negocio = Conexion::uno(
    'SELECT c.nombre_comercial, c.telefono, c.rfc,
            CONCAT_WS(", ", CONCAT_WS(" ", d.calle, d.num_ext),
                      col.nombre_colonia, CONCAT("CP ", col.codigo_postal),
                      mun.nombre_municipio, est.nombre_estado) AS domicilio
       FROM consultorio c
       LEFT JOIN domicilio d   ON d.id_domicilio   = c.id_domicilio
       LEFT JOIN colonia   col ON col.id_colonia   = d.id_colonia
       LEFT JOIN municipio mun ON mun.id_municipio = col.id_municipio
       LEFT JOIN estado    est ON est.id_estado    = mun.id_estado
      WHERE c.id_consultorio = 1'
);

Bitacora::registrar('cajas', 'IMPRIME', 'pago', (string) $idPago);

$cancelado = (int) $pago['estatus'] === 0;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recibo <?= e($pago['folio']) ?> &middot; <?= e($pago['paciente']) ?></title>
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
        width: 21.6cm; height: 27.9cm;
        margin: 1rem auto; background: #fff;
        box-shadow: 0 6px 30px rgba(0,0,0,.18);
        display: flex; flex-direction: column;
    }

    /* Dos tantos del mismo recibo, separados por una linea de corte. */
    .tanto { height: 50%; padding: 1cm 1.2cm; position: relative; }
    .tanto + .tanto { border-top: 1px dashed var(--linea); }

    .tijera {
        position: absolute; top: -.32cm; left: 50%;
        transform: translateX(-50%);
        background: #fff; padding: 0 .3cm;
        font-size: 7pt; color: #b0a0b5;
    }

    .cinta {
        display: flex; align-items: flex-start; justify-content: space-between;
        border-bottom: 2px solid var(--morado); padding-bottom: .25cm;
        margin-bottom: .35cm;
    }
    .marca .nombre { font-size: 13pt; font-weight: 700; color: var(--morado-oscuro); }
    .marca .esp { font-size: 6.5pt; letter-spacing: .08em; color: var(--morado);
                  text-transform: uppercase; }
    .marca .dir { font-size: 6.5pt; color: #8a7a90; margin-top: .1cm; }

    .folio { text-align: right; }
    .folio .et { font-size: 7pt; text-transform: uppercase; letter-spacing: .06em;
                 color: #8a7a90; }
    .folio .nu { font-size: 14pt; font-weight: 700; color: var(--morado); }
    .folio .fe { font-size: 8pt; }
    .folio .copia { font-size: 6.5pt; color: #a094a5; text-transform: uppercase; }

    .fila { display: flex; gap: .5cm; margin-bottom: .15cm; }
    .dato { flex: 1; display: flex; gap: .2cm; align-items: baseline;
            border-bottom: 1px dotted var(--linea); padding-bottom: .06cm; }
    .dato .et { font-size: 7pt; color: #8a7a90; white-space: nowrap; }
    .dato .va { font-weight: 600; color: var(--morado-oscuro); font-size: 9pt; }

    table { width: 100%; border-collapse: collapse; font-size: 8pt;
            margin-top: .3cm; }
    table th {
        background: var(--morado); color: #fff; text-align: left;
        font-size: 6.5pt; text-transform: uppercase; letter-spacing: .04em;
        padding: .1cm .2cm;
    }
    table td { border-bottom: 1px solid var(--linea); padding: .12cm .2cm; }
    table .der { text-align: right; }

    .importe {
        display: flex; justify-content: space-between; align-items: center;
        background: var(--morado); color: #fff;
        border-radius: 5px; padding: .18cm .4cm; margin-top: .3cm;
    }
    .importe .et { font-size: 8pt; text-transform: uppercase; letter-spacing: .06em; }
    .importe .va { font-size: 14pt; font-weight: 700; }

    .letra { font-size: 7.5pt; color: #8a7a90; margin-top: .12cm;
             text-transform: uppercase; }

    /* El renglon que salio del saldo a favor va marcado en el propio
       concepto: sin la marca, la ficha dice que se cubrieron 1,000 y
       que se recibieron 600, y el hueco de 400 queda sin explicar. */
    .desde-favor {
        font-size: 6.5pt; text-transform: uppercase; letter-spacing: .04em;
        background: #eaf6ee; color: #1d6b48;
        border-radius: 3px; padding: .02cm .15cm; margin-left: .15cm;
        white-space: nowrap;
    }

    /* El cuadre: lo cubierto, menos el saldo aplicado. Lo que queda es
       lo que de verdad entro hoy, que es el total de abajo. */
    .cuadre { margin-top: .25cm; font-size: 8.5pt; }
    .cuadre > div {
        display: flex; justify-content: space-between;
        border-bottom: 1px dotted var(--linea); padding: .05cm 0;
    }
    .cuadre .menos b { color: #1d6b48; }

    .saldo {
        display: flex; justify-content: space-between;
        font-size: 8.5pt; margin-top: .25cm;
        border: 1px solid var(--linea); border-radius: 5px; padding: .15cm .4cm;
    }

    .firma {
        margin-top: .5cm; text-align: center; width: 7cm;
        margin-left: auto; margin-right: auto;
    }
    .firma .linea { border-top: 1px solid var(--morado); margin-bottom: .1cm; }
    .firma .cargo { font-size: 7pt; color: #8a7a90; }

    .pie { position: absolute; bottom: .5cm; left: 1.2cm; right: 1.2cm;
           font-size: 6pt; color: #a094a5;
           display: flex; justify-content: space-between; }

    /* Un recibo cancelado se imprime, pero que no se confunda con uno
       vigente: el sello atraviesa la hoja. */
    .sello-cancelado {
        position: absolute; top: 45%; left: 50%;
        transform: translate(-50%, -50%) rotate(-18deg);
        font-size: 34pt; font-weight: 800; letter-spacing: .1em;
        color: rgba(192, 57, 43, .18);
        border: 6px solid rgba(192, 57, 43, .18);
        padding: .1cm .6cm; border-radius: 10px;
        pointer-events: none;
    }

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
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>

<div class="barra">
    <button class="principal" onclick="window.print()">Imprimir o guardar como PDF</button>
    <?php $volverA = URL_BASE . '/index.php?modulo=cajas&vista=cobrar'; ?>
    <?php require __DIR__ . '/includes/_boton_cerrar.php'; ?>
    <?php if ($cancelado): ?>
        <span style="color:#c0392b;font-weight:600">
            Este cobro est&aacute; cancelado.
        </span>
    <?php endif; ?>
</div>

<div class="hoja">
<?php foreach (['Original — paciente', 'Copia — consultorio'] as $copia): ?>
    <div class="tanto">
        <?php if ($copia !== 'Original — paciente'): ?>
            <span class="tijera">&#9986; corte aqu&iacute;</span>
        <?php endif; ?>

        <?php if ($cancelado): ?>
            <div class="sello-cancelado">CANCELADO</div>
        <?php endif; ?>

        <div class="cinta">
            <div class="marca">
                <div class="nombre"><?= e($negocio['nombre_comercial'] ?? 'Consultorio Dental') ?></div>
                <div class="esp">Odontopediatr&iacute;a y ortopedia maxilar</div>
                <div class="dir">
                    <?= e($negocio['domicilio'] ?? '') ?>
                    <?= $negocio['telefono'] ? ' &middot; Tel. ' . e($negocio['telefono']) : '' ?>
                </div>
            </div>
            <div class="folio">
                <div class="et">Recibo</div>
                <div class="nu"><?= e($pago['folio']) ?></div>
                <div class="fe"><?= e(fechaHora($pago['fecha_hora'])) ?></div>
                <div class="copia"><?= e($copia) ?></div>
            </div>
        </div>

        <div class="fila">
            <div class="dato" style="flex:2">
                <span class="et">Recib&iacute; de</span>
                <span class="va"><?= e($pago['paciente']) ?></span>
            </div>
            <div class="dato" style="flex:0 0 3.5cm">
                <span class="et">Expediente</span>
                <span class="va"><?= e($pago['num_expediente']) ?></span>
            </div>
        </div>

        <?php if ($pago['plan_folio']): ?>
        <div class="fila">
            <div class="dato">
                <span class="et">Plan</span>
                <span class="va">
                    <?= e($pago['plan_folio']) ?>
                    <?= $pago['plan_descripcion'] ? ' — ' . e($pago['plan_descripcion']) : '' ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($aplicaciones): ?>
        <table>
            <thead><tr>
                <th>Se aplic&oacute; a</th>
                <th style="width:2.4cm">Fecha</th>
                <th style="width:2.6cm" class="der">Importe</th>
            </tr></thead>
            <tbody>
            <?php foreach ($aplicaciones as $a): ?>
                <tr>
                    <td>
                        <?= e($a['descripcion']) ?>
                        <?php if ((int) $a['de_saldo_a_favor'] === 1): ?>
                            <span class="desde-favor">con saldo a favor</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(fecha($a['fecha'])) ?></td>
                    <td class="der"><?= e(money($a['monto'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php /* El cuadre, solo cuando hubo saldo a favor de por medio.

                 Sin esto, la ficha de un cobro con anticipo aplicado no
                 se sostiene sola: dice que se cubrieron 1,000 y que se
                 recibieron 600, y entre las dos cifras hay un hueco de
                 400 que el papel no explica. Puesto como renglon
                 NEGATIVO, el recibo se lee de arriba abajo y cierra;
                 es la diferencia entre un comprobante y un volante. */ ?>
        <?php if ($aFavorAplicado > 0.009): ?>
        <div class="cuadre">
            <div>
                <span>Total cubierto</span>
                <b><?= e(money($totalCubierto)) ?></b>
            </div>
            <div class="menos">
                <span>Saldo a favor aplicado</span>
                <b>&minus;<?= e(money($aFavorAplicado)) ?></b>
            </div>
        </div>
        <?php endif; ?>
        <?php else: ?>
            <div class="letra" style="margin-top:.3cm">
                Anticipo. Se aplicar&aacute; a los pr&oacute;ximos cargos.
            </div>
        <?php endif; ?>

        <table>
            <thead><tr>
                <th>Forma de pago</th>
                <th style="width:5cm">Referencia</th>
                <th style="width:2.6cm" class="der">Importe</th>
            </tr></thead>
            <tbody>
            <?php foreach ($formas as $f): ?>
                <tr>
                    <td><?= e($f['nombre_forma']) ?></td>
                    <td><?= e($f['referencia'] ?? '') ?></td>
                    <td class="der"><?= e(money($f['monto'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="importe">
            <span class="et">Total recibido</span>
            <span class="va"><?= e(money($pago['monto'])) ?></span>
        </div>
        <div class="letra">
            (<?= e(montoEnLetra((float) $pago['monto'])) ?>)
        </div>

        <div class="saldo">
            <span>Saldo del paciente despu&eacute;s de este pago</span>
            <b><?= e(money($saldo['saldo'] ?? 0)) ?></b>
        </div>

        <div class="firma">
            <div class="linea"></div>
            <div class="cargo">Recibi&oacute;: <?= e($pago['cobro']) ?></div>
        </div>

        <div class="pie">
            <span><?= e($pago['nombre_caja']) ?></span>
            <span>
                Documento interno, no es comprobante fiscal
                &middot; <?= e(APP_NOMBRE) ?> v<?= e(APP_VERSION) ?>
            </span>
        </div>
    </div>
<?php endforeach; ?>
</div>

<script>
window.addEventListener('load', () => setTimeout(() => window.print(), 700));
</script>
</body>
</html>
