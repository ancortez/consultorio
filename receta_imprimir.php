<?php
/**
 * Receta lista para imprimir sobre el recetario del consultorio.
 *
 * El recetario ya existe impreso: trae el membrete de la doctora, la
 * cedula, los recuadros de dia/mes/anio, las lineas de PACIENTE y EDAD
 * y el pie con domicilio y telefono. Lo unico que falta es el texto.
 *
 * Por eso esta hoja tiene dos caras:
 *
 *   En pantalla  - se dibuja la plantilla completa en gris claro, para
 *                  que se vea como va a quedar y se pueda revisar antes
 *                  de gastar papel.
 *
 *   Al imprimir  - la plantilla desaparece y solo salen los datos, en
 *                  las mismas coordenadas. Se imprime sobre la hoja
 *                  preimpresa y cada dato cae en su lugar.
 *
 * Las medidas estan tomadas del recetario fisico (media carta apaisada,
 * 21.6 x 14 cm). Si el consultorio manda a hacer papel nuevo con otro
 * acomodo, se ajustan las variables de abajo y nada mas.
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

Auth::exigirLogin();
Auth::exigirPermiso('consultas.ver');

$idReceta = (int) ($_GET['id'] ?? 0);
if ($idReceta <= 0) {
    http_response_code(400);
    exit('Falta la receta.');
}

$rx = Conexion::uno(
    'SELECT r.*, c.folio AS consulta_folio, c.id_registro_paciente,
            vp.nombre_completo AS paciente, vp.edad, vp.num_expediente,
            u.nombre_completo AS dentista, u.cedula_prof
       FROM receta r
       JOIN consulta c ON c.id_consulta = r.id_consulta
       JOIN v_paciente vp ON vp.id_registro_paciente = c.id_registro_paciente
       JOIN usuario u ON u.id_usuario = r.id_usuario_dentista
      WHERE r.id_receta = :r AND r.estatus = 1',
    [':r' => $idReceta]
);

if ($rx === null) {
    http_response_code(404);
    exit('La receta no existe.');
}

$negocio = Conexion::uno(
    'SELECT c.nombre_comercial, c.telefono,
            CONCAT_WS(". ", CONCAT_WS(" ", d.calle, CONCAT("#", d.num_ext)),
                      col.nombre_colonia, CONCAT("CP ", col.codigo_postal),
                      CONCAT_WS(", ", mun.nombre_municipio, est.nombre_estado)) AS domicilio
       FROM consultorio c
       LEFT JOIN domicilio d   ON d.id_domicilio   = c.id_domicilio
       LEFT JOIN colonia   col ON col.id_colonia   = d.id_colonia
       LEFT JOIN municipio mun ON mun.id_municipio = col.id_municipio
       LEFT JOIN estado    est ON est.id_estado    = mun.id_estado
      WHERE c.id_consultorio = 1'
);

$fecha = $rx['fecha'];
$dia   = date('d', strtotime($fecha));
$mes   = date('m', strtotime($fecha));
$anio  = date('Y', strtotime($fecha));

Bitacora::registrar('consultas', 'IMPRIME', 'receta', (string) $idReceta);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Receta <?= e($rx['folio']) ?> &middot; <?= e($rx['paciente']) ?></title>
<style>
    /* ---------------------------------------------------------------
       Medidas del recetario. Todo lo demas se calcula a partir de aqui,
       asi que un recetario nuevo se ajusta cambiando estos numeros.
       --------------------------------------------------------------- */
    :root {
        --hoja-ancho: 21.6cm;
        --hoja-alto:  14cm;

        /* La franja decorada de la izquierda: no se imprime, pero el
           texto no debe invadirla. */
        --franja: 3.2cm;

        --margen-der: 1.2cm;

        /* Donde caen los datos en la hoja preimpresa */
        --y-fecha:    1.55cm;   /* recuadros DIA / MES / ANIO */
        --y-paciente: 3.35cm;   /* linea PACIENTE y EDAD */
        --y-cuerpo:   4.4cm;    /* inicio del recuadro grande */

        --x-dia:  14.6cm;
        --x-mes:  16.4cm;
        --x-anio: 18.2cm;
        --x-edad: 18.3cm;

        --morado: #9a5a9c;
        --tinta:  #1a1a1a;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0; background: #e9e4ec;
        font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        color: var(--tinta);
    }

    .hoja {
        position: relative;
        width: var(--hoja-ancho);
        height: var(--hoja-alto);
        margin: 1rem auto;
        background: #fff;
        box-shadow: 0 6px 30px rgba(0,0,0,.18);
        overflow: hidden;
    }

    /* ---------- La plantilla: solo para ver en pantalla ----------
       Es una guia, no el documento. Va en gris para que se distinga de
       lo que si se va a imprimir. */
    .plantilla { position: absolute; inset: 0; color: #c9b9cd; }

    .plantilla .franja {
        position: absolute; left: 0; top: 0; bottom: 0;
        width: var(--franja);
        background: repeating-linear-gradient(
            45deg, #f0e4f2, #f0e4f2 10px, #e8d8ec 10px, #e8d8ec 20px);
    }
    .plantilla .membrete {
        position: absolute; left: calc(var(--franja) + .8cm); top: .7cm;
        color: #c9a9cd;
    }
    .plantilla .membrete .dra { font-size: 15pt; font-weight: 700; }
    .plantilla .membrete .esp {
        font-size: 9pt; font-weight: 700; letter-spacing: .02em;
        text-transform: uppercase;
    }
    .plantilla .membrete .ced { font-size: 9pt; margin-top: .35cm; }

    .plantilla .rotulo {
        position: absolute; font-size: 7.5pt; letter-spacing: .1em;
        text-transform: uppercase;
    }
    .plantilla .caja {
        position: absolute; border-radius: 10px; background: #f6eff8;
    }
    .plantilla .pie {
        position: absolute; left: calc(var(--franja) + .8cm);
        right: var(--margen-der); bottom: .6cm;
        font-size: 8.5pt; display: flex; justify-content: space-between;
        color: #c9a9cd;
    }

    /* ---------- Los datos: esto SI se imprime ---------- */
    .dato { position: absolute; color: var(--tinta); }

    .d-dia  { left: var(--x-dia);  top: var(--y-fecha); }
    .d-mes  { left: var(--x-mes);  top: var(--y-fecha); }
    .d-anio { left: var(--x-anio); top: var(--y-fecha); }
    .d-dia, .d-mes, .d-anio {
        width: 1.5cm; text-align: center;
        font-size: 12pt; font-weight: 600;
    }

    .d-paciente {
        left: calc(var(--franja) + 1.5cm); top: var(--y-paciente);
        width: 10.5cm; font-size: 11pt; font-weight: 600;
    }
    .d-edad {
        left: var(--x-edad); top: var(--y-paciente);
        width: 2cm; text-align: center; font-size: 11pt; font-weight: 600;
    }

    .d-cuerpo {
        left: calc(var(--franja) + 1cm); top: var(--y-cuerpo);
        right: var(--margen-der);
        height: 7.2cm;
        font-size: 11pt; line-height: 1.55;
        white-space: pre-wrap;
        overflow: hidden;
    }

    .d-folio {
        right: var(--margen-der); bottom: 1.5cm;
        font-size: 7pt; color: #8a7a90;
    }

    /* ---------- Barra de control ---------- */
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
    .barra .principal  { background: #7b2d8e; color: #fff; }
    .barra .secundario { background: #f0ecf2; color: #5a3d63; }
    .barra .nota { color: #6c6577; font-size: 12px; }

    .aviso-largo {
        display: none;
        background: #fff6e5; border: 1px solid #f0d9a8; color: #7a5a12;
        border-radius: 8px; padding: .4rem .7rem; font-size: 12px;
    }

    /* ---------------------------------------------------------------
       Impresion: se va la plantilla y queda solo la tinta.
       --------------------------------------------------------------- */
    @page { size: 21.6cm 14cm; margin: 0; }

    @media print {
        body { background: #fff; }
        .barra, .plantilla { display: none !important; }
        .hoja { margin: 0; box-shadow: none; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>

<div class="barra">
    <button class="principal" onclick="window.print()">
        Imprimir sobre el recetario
    </button>
    <?php $volverA = URL_BASE . '/index.php?modulo=consultas&vista=consulta&id='
                     . (int) $rx['id_consulta']; ?>
    <?php require __DIR__ . '/includes/_boton_cerrar.php'; ?>
    <span class="nota">
        Coloca una hoja del recetario en la impresora. Lo gris es la gu&iacute;a
        de pantalla: no se imprime.
    </span>
    <span class="aviso-largo" id="avisoLargo">
        El texto no cabe en la hoja. Recorta las indicaciones o usa dos recetas.
    </span>
</div>

<div class="hoja">

    <!-- ====== Guia de pantalla: replica del papel preimpreso ====== -->
    <div class="plantilla" aria-hidden="true">
        <div class="franja"></div>

        <div class="membrete">
            <div class="dra"><?= e($rx['dentista']) ?></div>
            <div class="esp">Odontopediatr&iacute;a y ortopedia maxilar</div>
            <div class="ced">
                UAN-UDG<br>
                C&eacute;dula Profesional <?= e($rx['cedula_prof'] ?? '') ?>
            </div>
        </div>

        <div class="rotulo" style="left:var(--x-dia);top:.95cm;width:1.5cm;text-align:center">D&iacute;a</div>
        <div class="rotulo" style="left:var(--x-mes);top:.95cm;width:1.5cm;text-align:center">Mes</div>
        <div class="rotulo" style="left:var(--x-anio);top:.95cm;width:1.5cm;text-align:center">A&ntilde;o</div>
        <div class="caja" style="left:var(--x-dia);top:1.35cm;width:1.5cm;height:.75cm"></div>
        <div class="caja" style="left:var(--x-mes);top:1.35cm;width:1.5cm;height:.75cm"></div>
        <div class="caja" style="left:var(--x-anio);top:1.35cm;width:1.5cm;height:.75cm"></div>

        <div class="caja" style="left:calc(var(--franja) + .8cm);top:3.1cm;
                                 width:13.4cm;height:.95cm"></div>
        <div class="rotulo" style="left:calc(var(--franja) + 1.05cm);top:3.55cm">Paciente</div>

        <div class="caja" style="left:17.6cm;top:3.1cm;width:2.8cm;height:.95cm"></div>
        <div class="rotulo" style="left:17.85cm;top:3.55cm">Edad</div>

        <div class="caja" style="left:calc(var(--franja) + .8cm);top:4.25cm;
                                 right:var(--margen-der);height:7.6cm"></div>

        <div class="pie">
            <span><b>DOMICILIO:</b> <?= e($negocio['domicilio'] ?? '') ?></span>
            <span><b>TEL&Eacute;FONO:</b> <?= e($negocio['telefono'] ?? '') ?></span>
        </div>
    </div>

    <!-- ====== Lo que se imprime ====== -->
    <div class="dato d-dia"><?= e($dia) ?></div>
    <div class="dato d-mes"><?= e($mes) ?></div>
    <div class="dato d-anio"><?= e($anio) ?></div>

    <div class="dato d-paciente"><?= e($rx['paciente']) ?></div>
    <div class="dato d-edad"><?= $rx['edad'] !== null ? (int) $rx['edad'] : '' ?></div>

    <div class="dato d-cuerpo" id="cuerpo"><?= e($rx['indicaciones']) ?></div>

    <div class="dato d-folio">
        <?= e($rx['folio']) ?> &middot; <?= e($rx['consulta_folio']) ?>
    </div>
</div>

<script>
/*
 * Aviso de desbordamiento.
 *
 * El recuadro del recetario tiene una altura fija. Si el texto pasa de
 * ahi, en pantalla se corta y en papel simplemente no se imprime: es el
 * peor error posible en una receta, porque nadie lo nota.
 */
window.addEventListener('load', () => {
    const cuerpo = document.getElementById('cuerpo');
    if (cuerpo.scrollHeight > cuerpo.clientHeight + 2) {
        document.getElementById('avisoLargo').style.display = 'inline-block';
    }
});
</script>
</body>
</html>
