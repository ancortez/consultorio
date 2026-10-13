<?php
/**
 * Plan de tratamiento listo para imprimir y firmar.
 *
 * Una hoja carta con los datos del paciente arriba y el desglose de
 * procedimientos abajo, igual que el formato de papel del consultorio.
 * Si el plan trae muchos conceptos, la tabla continua en una segunda
 * hoja sin cortar renglones a la mitad.
 *
 * Uso:  /plan_imprimir.php?id=ID_PLAN
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

Auth::exigirLogin();
Auth::exigirPermiso('consultas.ver');

$idPlan = (int) ($_GET['id'] ?? 0);
if ($idPlan <= 0) {
    http_response_code(400);
    exit('Falta el plan.');
}

$plan = Conexion::uno(
    'SELECT pt.*, ep.nombre_estado, u.nombre_completo AS dentista, u.cedula_prof,
            vp.nombre_completo AS paciente, vp.num_expediente, vp.edad,
            vp.id_registro_paciente, rp.requiere_tutor
       FROM plan_tratamiento pt
       JOIN estado_plan ep ON ep.id_estado_plan = pt.id_estado_plan
       JOIN usuario u ON u.id_usuario = pt.id_usuario_dentista
       JOIN v_paciente vp ON vp.id_registro_paciente = pt.id_registro_paciente
       JOIN registro_paciente rp
         ON rp.id_registro_paciente = pt.id_registro_paciente
      WHERE pt.id_plan_tratamiento = :p',
    [':p' => $idPlan]
);

if ($plan === null) {
    http_response_code(404);
    exit('El plan no existe.');
}

$renglones = Conexion::todos(
    'SELECT d.*, pr.nombre_procedimiento
       FROM plan_tratamiento_detalle d
       LEFT JOIN procedimiento pr ON pr.id_procedimiento = d.id_procedimiento
      WHERE d.id_plan_tratamiento = :p
      ORDER BY d.orden, d.id_plan_detalle',
    [':p' => $idPlan]
);

/*
   ¿Firma el tutor o el propio paciente?

   Misma regla que la ficha y que el consentimiento: sale de Tutoria,
   nunca de mirar la edad aqui. Un adulto que respondio que actua por su
   cuenta recibe una hoja que le pide SU firma; pedirle la de su padre
   convierte el papel en algo que no puede firmar.
*/
$firmaElPaciente = !Tutoria::llevaTutor(
    $plan['edad'] !== null ? (int) $plan['edad'] : null,
    $plan['requiere_tutor'] !== null ? (int) $plan['requiere_tutor'] : null
);

$tutor = Conexion::uno(
    'SELECT CONCAT_WS(" ", p.nombres, p.ap_paterno, p.ap_materno) AS nombre
       FROM expediente_tutor et
       JOIN persona p ON p.id_persona = et.id_persona
      WHERE et.id_registro_paciente = :r AND et.estatus = 1
      ORDER BY et.es_responsable_pago DESC LIMIT 1',
    [':r' => $plan['id_registro_paciente']]
);

$negocio = Conexion::uno(
    'SELECT c.nombre_comercial, c.telefono,
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

Bitacora::registrar('consultas', 'IMPRIME', 'plan_tratamiento', (string) $idPlan);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Plan de tratamiento <?= e($plan['folio']) ?> &middot; <?= e($plan['paciente']) ?></title>
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
        color: var(--texto); font-size: 9.5pt;
    }

    .hoja {
        width: 21.6cm; min-height: 27.9cm;
        margin: 1rem auto; background: #fff;
        box-shadow: 0 6px 30px rgba(0,0,0,.18);
        display: flex; flex-direction: column;
        position: relative;
    }

    .cinta {
        background: var(--morado); height: 2cm;
        display: flex; align-items: center; justify-content: space-between;
        padding-right: 1.2cm; flex: 0 0 auto;
    }
    .marca { background: #fff; border-radius: 0 12px 12px 0;
             padding: .32cm .7cm .32cm 1cm; }
    .marca .nombre { font-size: 13pt; font-weight: 700; color: var(--morado-oscuro); }
    .marca .esp { font-size: 6.5pt; letter-spacing: .08em; color: var(--morado);
                  text-transform: uppercase; }
    .titulo-doc { color: #fff; font-size: 12.5pt; font-weight: 700;
                  text-transform: uppercase; letter-spacing: .04em; }

    .datos-clinica {
        font-size: 6.5pt; padding: .12cm 1.2cm;
        display: flex; justify-content: space-between;
        border-bottom: 1px solid var(--linea); flex: 0 0 auto;
    }

    .cuerpo { padding: .5cm 1.2cm; flex: 1; }

    .seccion { margin-bottom: .35cm; }
    .seccion > h2 {
        background: var(--morado); color: #fff; font-size: 8pt;
        text-transform: uppercase; letter-spacing: .06em;
        margin: 0 0 .2cm; padding: .1cm .35cm; border-radius: 3px; font-weight: 700;
    }

    .fila { display: flex; flex-wrap: wrap; gap: .1cm .5cm; }
    .dato {
        flex: 1 1 200px; display: flex; align-items: baseline; gap: .18cm;
        border-bottom: 1px dotted var(--linea); padding: .08cm 0; min-height: .5cm;
    }
    .dato.ancho { flex: 1 1 100%; }
    .dato .et { color: #8a7a90; font-size: 7pt; white-space: nowrap; }
    .dato .va { font-weight: 600; color: var(--morado-oscuro); font-size: 9pt; }

    /* ---------- Tabla de procedimientos ---------- */
    table.proc { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
    table.proc thead th {
        background: var(--morado); color: #fff;
        font-size: 7pt; text-transform: uppercase; letter-spacing: .04em;
        padding: .14cm .2cm; text-align: left; font-weight: 700;
    }
    table.proc tbody td {
        border-bottom: 1px solid var(--linea);
        padding: .18cm .2cm; vertical-align: top;
    }
    table.proc tbody tr:nth-child(even) { background: #faf6fb; }
    table.proc .num  { text-align: center; color: #8a7a90; }
    table.proc .der  { text-align: right; }
    table.proc .nota { font-size: 7pt; color: #8a7a90; }

    .totales {
        margin-top: .35cm; margin-left: auto; width: 7.5cm;
        font-size: 9pt;
    }
    .totales .renglon {
        display: flex; justify-content: space-between;
        padding: .12cm .3cm; border-bottom: 1px solid var(--linea);
    }
    .totales .renglon.total {
        background: var(--morado); color: #fff; font-weight: 700;
        border-radius: 4px; border-bottom: 0; margin-top: .1cm; font-size: 10.5pt;
    }

    .leyenda {
        margin-top: .5cm; font-size: 7.5pt; line-height: 1.5;
        color: #7a6a80; border: 1px solid var(--linea);
        border-radius: 5px; padding: .25cm .35cm;
    }

    .firmas {
        display: flex; justify-content: space-around; gap: 1.5cm;
        padding: 0 1.2cm 1cm; margin-top: auto; flex: 0 0 auto;
    }
    .firma { flex: 1; text-align: center; }
    .firma .linea { border-top: 1px solid var(--morado); height: 1.2cm; margin-bottom: .12cm; }
    .firma .nombre { font-weight: 600; color: var(--morado-oscuro); font-size: 9pt;
                     min-height: 1.1em; }
    .firma .cargo { font-size: 7pt; color: #8a7a90; }
    .firma .cedula { font-size: 6.5pt; color: #a094a5; }

    .pie {
        position: absolute; bottom: .35cm; left: 1.2cm; right: 1.2cm;
        font-size: 6pt; color: #a094a5;
        display: flex; justify-content: space-between;
        border-top: 1px solid #eee4ec; padding-top: .1cm;
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
        /* Los renglones no se parten a la mitad entre hojas */
        table.proc tr, .firmas, .totales, .seccion { page-break-inside: avoid;
                                                     break-inside: avoid; }
        thead { display: table-header-group; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>

<div class="barra">
    <button class="principal" onclick="window.print()">Imprimir o guardar como PDF</button>
    <?php $volverA = URL_BASE . '/index.php?modulo=paciente&vista=detalle_paciente&id='
                     . (int) $plan['id_registro_paciente']; ?>
    <?php require __DIR__ . '/includes/_boton_cerrar.php'; ?>
</div>

<div class="hoja">
    <div class="cinta">
        <div class="marca">
            <div class="nombre"><?= e($negocio['nombre_comercial'] ?? 'Consultorio Dental') ?></div>
            <div class="esp">Odontopediatr&iacute;a y ortopedia maxilar</div>
        </div>
        <div class="titulo-doc">Plan de tratamiento</div>
    </div>

    <div class="datos-clinica">
        <span>DOMICILIO: <?= e($negocio['domicilio'] ?? '') ?></span>
        <span>TEL&Eacute;FONO: <?= e($negocio['telefono'] ?? '') ?></span>
    </div>

    <div class="cuerpo">

        <div class="seccion">
            <div class="fila">
                <div class="dato" style="flex:1 1 55%">
                    <span class="et">Nombre del paciente</span>
                    <span class="va"><?= e($plan['paciente']) ?></span>
                </div>
                <div class="dato" style="flex:0 0 100px">
                    <span class="et">Edad</span>
                    <span class="va"><?= $plan['edad'] !== null ? (int) $plan['edad'] : '' ?></span>
                </div>
                <div class="dato" style="flex:0 0 150px">
                    <span class="et">Folio</span>
                    <span class="va"><?= e($plan['folio']) ?></span>
                </div>
                <div class="dato" style="flex:0 0 140px">
                    <span class="et">Fecha</span>
                    <span class="va"><?= e(fecha($plan['fecha'])) ?></span>
                </div>
                <div class="dato ancho">
                    <span class="et">Descripci&oacute;n</span>
                    <span class="va"><?= e($plan['descripcion'] ?? '') ?></span>
                </div>
            </div>
        </div>

        <div class="seccion">
            <h2>Procedimientos</h2>
            <table class="proc">
                <?php /* Sin columnas de fecha: el plan es la cotizacion de lo
                         que se va a hacer, no la agenda. Las fechas reales
                         salen de las consultas, que se registran aparte. */ ?>
                <thead>
                    <tr>
                        <th style="width:.9cm">#</th>
                        <th>Procedimiento</th>
                        <th style="width:2.6cm">&Oacute;rgano dentario</th>
                        <th style="width:1.4cm" class="num">Cant.</th>
                        <th style="width:2.4cm" class="der">Costo</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$renglones): ?>
                    <tr><td colspan="5" style="text-align:center;padding:.6cm;color:#a094a5">
                        Sin procedimientos registrados.
                    </td></tr>
                <?php else: foreach ($renglones as $i => $r): ?>
                    <tr>
                        <td class="num"><?= $i + 1 ?></td>
                        <td>
                            <?= e($r['nombre_procedimiento'] ?? 'PROCEDIMIENTO') ?>
                            <?php if (!empty($r['notas'])): ?>
                                <div class="nota"><?= e($r['notas']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= e($r['dientes'] ?: '—') ?></td>
                        <td class="num"><?= (int) $r['cantidad'] ?></td>
                        <td class="der"><?= e(money($r['importe'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <div class="totales">
                <div class="renglon">
                    <span>Subtotal</span>
                    <span><?= e(money($plan['subtotal'])) ?></span>
                </div>
                <?php if ((float) $plan['descuento'] > 0): ?>
                <div class="renglon">
                    <span>Descuento</span>
                    <span>&minus; <?= e(money($plan['descuento'])) ?></span>
                </div>
                <?php endif; ?>
                <div class="renglon total">
                    <span>TOTAL</span>
                    <span><?= e(money($plan['total'])) ?></span>
                </div>
            </div>
        </div>

        <div class="leyenda">
            Declaro que se me explic&oacute; el plan de tratamiento propuesto, los
            procedimientos que incluye, sus alternativas y su costo. Entiendo que el
            plan puede ajustarse si durante el tratamiento aparecen hallazgos que hoy
            no son visibles, y que cualquier cambio en el costo me ser&aacute;
            informado antes de realizarse.
        </div>
    </div>

    <div class="firmas">
        <div class="firma">
            <div class="linea"></div>
            <div class="nombre"><?= e($firmaElPaciente
                ? $plan['paciente'] : ($tutor['nombre'] ?? '')) ?></div>
            <div class="cargo"><?= $firmaElPaciente
                ? 'Nombre y firma del paciente'
                : 'Nombre y firma de padre o tutor' ?></div>
        </div>
        <div class="firma">
            <div class="linea"></div>
            <div class="nombre"><?= e($plan['dentista']) ?></div>
            <div class="cargo">Nombre y firma del doctor/a</div>
            <?php if (!empty($plan['cedula_prof'])): ?>
                <div class="cedula">C&eacute;d. Prof. <?= e($plan['cedula_prof']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="pie">
        <span>Expediente <?= e($plan['num_expediente']) ?> &middot; Plan <?= e($plan['folio']) ?></span>
        <span><?= e(APP_NOMBRE) ?> v<?= e(APP_VERSION) ?></span>
    </div>
</div>

<script>
window.addEventListener('load', () => setTimeout(() => window.print(), 700));
</script>
</body>
</html>
