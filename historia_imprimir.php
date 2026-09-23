<?php
/**
 * Historia clinica lista para imprimir y firmar.
 *
 * Sale en dos hojas carta, siguiendo el formato de papel del
 * consultorio. Al final lleva las lineas de firma del tutor y del
 * doctor, que es lo que le da valor al expediente.
 *
 * Uso:  /historia_imprimir.php?id=EXPEDIENTE
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

Auth::exigirLogin();
Auth::exigirPermiso('paciente.ver');

$idExpediente = (int) ($_GET['id'] ?? 0);

// Quien firma como doctor. No tiene por que ser quien capturo: la
// historia la escribe muchas veces recepcion y la firma quien atiende.
$idDentista = (int) ($_GET['dentista'] ?? 0);
if ($idExpediente <= 0) {
    http_response_code(400);
    exit('Falta el expediente.');
}

$paciente = Conexion::uno(
    'SELECT vp.*, rp.fecha_apertura
       FROM v_paciente vp
       JOIN registro_paciente rp ON rp.id_registro_paciente = vp.id_registro_paciente
      WHERE vp.id_registro_paciente = :r',
    [':r' => $idExpediente]
);

if ($paciente === null) {
    http_response_code(404);
    exit('El paciente no existe.');
}

$hc = Conexion::uno(
    'SELECT hc.*, u.nombre_completo AS capturo
       FROM historia_clinica hc
       LEFT JOIN usuario u ON u.id_usuario = hc.id_usuario_captura
      WHERE hc.id_registro_paciente = :r',
    [':r' => $idExpediente]
);

if ($hc === null) {
    http_response_code(404);
    exit('Este paciente todavia no tiene historia clinica capturada.');
}

$marcas = Conexion::todos(
    'SELECT cc.seccion, cc.nombre, hm.detalle
       FROM historia_marca hm
       JOIN catalogo_clinico cc ON cc.id_catalogo_clinico = hm.id_catalogo_clinico
      WHERE hm.id_historia_clinica = :h
      ORDER BY cc.seccion, cc.orden',
    [':h' => $hc['id_historia_clinica']]
);

$porSeccion = [];
foreach ($marcas as $m) {
    $porSeccion[$m['seccion']][] = $m['nombre']
        . ($m['detalle'] ? ' (' . $m['detalle'] . ')' : '');
}

$tutores = Conexion::todos(
    'SELECT CONCAT_WS(" ", p.nombres, p.ap_paterno, p.ap_materno) AS nombre,
            pa.nombre_parentesco
       FROM expediente_tutor et
       JOIN persona p ON p.id_persona = et.id_persona
       LEFT JOIN parentesco pa ON pa.id_parentesco = et.id_parentesco
      WHERE et.id_registro_paciente = :r AND et.estatus = 1
      ORDER BY et.es_responsable_pago DESC LIMIT 1',
    [':r' => $idExpediente]
);
$tutor = $tutores[0] ?? null;

$domicilio = Conexion::uno(
    'SELECT CONCAT_WS(", ", vd.calle_numero, vd.colonia,
                      CONCAT("C.P. ", vd.codigo_postal),
                      vd.nombre_municipio, vd.nombre_estado) AS linea
       FROM v_domicilio vd
      WHERE vd.id_persona = :p AND vd.estatus = 1
      ORDER BY vd.es_principal DESC LIMIT 1',
    [':p' => $paciente['id_persona']]
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

$dentista = $idDentista > 0
    ? Conexion::uno(
        'SELECT nombre_completo, cedula_prof FROM usuario
          WHERE id_usuario = :u AND es_dentista = 1 AND estatus = 1',
        [':u' => $idDentista])
    : null;

// Sin eleccion explicita, el unico dentista del consultorio; si hay
// varios, la linea se deja en blanco para que firme quien atienda.
if ($dentista === null) {
    $todos = Conexion::todos(
        'SELECT nombre_completo, cedula_prof FROM usuario
          WHERE es_dentista = 1 AND estatus = 1');
    $dentista = count($todos) === 1 ? $todos[0] : null;
}

Bitacora::registrar('paciente', 'IMPRIME', 'historia_clinica',
    (string) $hc['id_historia_clinica'], ['dentista' => $idDentista ?: null]);

// ---------------------------------------------------------------------
// Ayudas de impresion
// ---------------------------------------------------------------------

/** Un dato con su etiqueta. Si esta vacio deja la linea para escribir. */
function dato(string $etiqueta, $valor, int $ancho = 0): string
{
    $texto = ($valor === null || $valor === '') ? '' : (string) $valor;
    $estilo = $ancho ? ' style="flex:0 0 ' . $ancho . 'px"' : '';
    return '<div class="dato"' . $estilo . '>'
        . '<span class="et">' . $etiqueta . '</span>'
        . '<span class="va">' . e($texto) . '</span></div>';
}

/** Traduce 1/0/null a Si / No / linea en blanco. */
function siNoTexto($valor): string
{
    if ($valor === null || $valor === '') return '';
    return (int) $valor === 1 ? 'Sí' : 'No';
}

/** Traduce una opcion numerada a su texto. */
function opcionTexto($valor, array $mapa): string
{
    return $valor !== null && isset($mapa[(int) $valor]) ? $mapa[(int) $valor] : '';
}

$lista = static fn (string $sec): string =>
    isset($porSeccion[$sec]) ? implode(' · ', $porSeccion[$sec]) : '';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Historia cl&iacute;nica &middot; <?= e($paciente['nombre_completo']) ?></title>
<style>
    :root {
        --morado: #8b5a96;
        --morado-oscuro: #6b4574;
        --texto: #5a3d63;
        --linea: #d9cddd;
    }
    * { box-sizing: border-box; }

    body {
        margin: 0;
        background: #e9e4ec;
        font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        color: var(--texto);
        font-size: 9pt;
    }

    .hoja {
        width: 21.6cm;
        height: 27.9cm;
        margin: 1rem auto;
        background: #fff;
        box-shadow: 0 6px 30px rgba(0,0,0,.18);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    /* ---------- Encabezado ---------- */
    .cinta {
        background: var(--morado);
        height: 1.9cm;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-right: 1.2cm;
        flex: 0 0 auto;
    }
    .marca {
        background: #fff;
        border-radius: 0 12px 12px 0;
        padding: .3cm .7cm .3cm 1cm;
    }
    .marca .nombre { font-size: 12.5pt; font-weight: 700; color: var(--morado-oscuro); }
    .marca .esp { font-size: 6.5pt; letter-spacing: .08em; color: var(--morado);
                  text-transform: uppercase; }
    .titulo-doc { color: #fff; font-size: 12pt; font-weight: 700;
                  text-transform: uppercase; letter-spacing: .04em; }

    .datos-clinica {
        font-size: 6.5pt; letter-spacing: .03em;
        padding: .12cm 1.2cm;
        display: flex; justify-content: space-between;
        border-bottom: 1px solid var(--linea);
        flex: 0 0 auto;
    }

    .cuerpo { padding: .45cm 1.2cm; flex: 1; overflow: hidden; }

    /* ---------- Secciones ---------- */
    .seccion { margin-bottom: .32cm; }
    .seccion > h2 {
        background: var(--morado);
        color: #fff;
        font-size: 8pt;
        text-transform: uppercase;
        letter-spacing: .06em;
        margin: 0 0 .18cm;
        padding: .09cm .35cm;
        border-radius: 3px;
        font-weight: 700;
    }

    .fila { display: flex; flex-wrap: wrap; gap: .1cm .5cm; }

    .dato {
        flex: 1 1 200px;
        display: flex;
        align-items: baseline;
        gap: .18cm;
        border-bottom: 1px dotted var(--linea);
        padding: .07cm 0;
        min-height: .48cm;
    }
    .dato .et { color: #8a7a90; font-size: 7pt; white-space: nowrap; }
    .dato .va { font-weight: 600; color: var(--morado-oscuro); font-size: 8.5pt; }

    .dato.ancho { flex: 1 1 100%; }

    /* ---------- Firmas ---------- */
    .firmas {
        display: flex; justify-content: space-around; gap: 1.5cm;
        padding: 0 1.2cm 1cm;
        margin-top: auto;
        flex: 0 0 auto;
    }
    .firma { flex: 1; text-align: center; }
    .firma .linea { border-top: 1px solid var(--morado); height: 1.1cm; margin-bottom: .12cm; }
    .firma .nombre { font-weight: 600; color: var(--morado-oscuro); font-size: 8.5pt;
                     min-height: 1.1em; }
    .firma .cargo { font-size: 7pt; color: #8a7a90; }

    .pie {
        position: absolute; bottom: .35cm; left: 1.2cm; right: 1.2cm;
        font-size: 6pt; color: #a094a5;
        display: flex; justify-content: space-between;
        border-top: 1px solid #eee4ec; padding-top: .1cm;
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
    .barra .principal { background: #7b2d8e; color: #fff; }
    .barra .secundario { background: #f0ecf2; color: #5a3d63; }
    .barra .aviso { color: #7a5a12; background: #fff6e5; border: 1px solid #f0d9a8;
                    border-radius: 8px; padding: .4rem .7rem; font-size: 12px; }

    @page { size: letter; margin: 0; }

    @media print {
        body { background: #fff; }
        .barra { display: none !important; }
        .hoja { margin: 0; box-shadow: none; page-break-after: always; }
        .hoja:last-of-type { page-break-after: auto; }
        .seccion, .firmas { page-break-inside: avoid; break-inside: avoid; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>

<div class="barra">
    <button class="principal" onclick="window.print()">Imprimir o guardar como PDF</button>
    <a class="secundario"
       href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= $idExpediente ?>#tab-historia">
        Volver
    </a>
    <span class="aviso">Dos hojas carta. Imprime a doble cara si quieres una sola.</span>
</div>

<?php
$encabezado = static function (string $titulo) use ($negocio, $paciente) { ?>
    <div class="cinta">
        <div class="marca">
            <div class="nombre"><?= e($negocio['nombre_comercial'] ?? 'Consultorio Dental') ?></div>
            <div class="esp">Odontopediatr&iacute;a y ortopedia maxilar</div>
        </div>
        <div class="titulo-doc"><?= $titulo ?></div>
    </div>
    <div class="datos-clinica">
        <span>DOMICILIO: <?= e($negocio['domicilio'] ?? '') ?></span>
        <span>TEL&Eacute;FONO: <?= e($negocio['telefono'] ?? '') ?></span>
    </div>
<?php };
?>

<!-- ================= HOJA 1 ================= -->
<div class="hoja">
    <?php $encabezado('Historial cl&iacute;nico'); ?>

    <div class="cuerpo">

        <div class="seccion">
            <h2>Ficha de identificaci&oacute;n</h2>
            <div class="fila">
                <?= dato('Nombre', $paciente['nombre_completo']) ?>
                <?= dato('Le gusta que le llamen', $hc['apodo'], 200) ?>
            </div>
            <div class="fila">
                <?= dato('Fecha de nacimiento', fecha($paciente['fecha_nacim']), 180) ?>
                <?= dato('Edad', $paciente['edad'] !== null ? $paciente['edad'] . ' años' : '', 110) ?>
                <?= dato('N&uacute;mero de hijo', $hc['numero_hijo'], 120) ?>
                <?= dato('Expediente', $paciente['num_expediente'], 140) ?>
            </div>
            <div class="fila">
                <?= dato('Estatura', $hc['estatura_cm'] ? $hc['estatura_cm'] . ' cm' : '', 130) ?>
                <?= dato('Peso', $hc['peso_kg'] ? $hc['peso_kg'] . ' kg' : '', 130) ?>
                <?= dato('Tel&eacute;fono', $paciente['telefono'], 180) ?>
                <?= dato('Correo', $paciente['correo']) ?>
            </div>
            <div class="fila">
                <div class="dato ancho">
                    <span class="et">Domicilio</span>
                    <span class="va"><?= e($domicilio['linea'] ?? '') ?></span>
                </div>
            </div>
            <div class="fila">
                <?= dato('Escuela', $hc['escuela']) ?>
                <?= dato('Grado escolar', $hc['grado_escolar'], 160) ?>
                <?= dato('Personaje favorito', $hc['personaje_favorito'], 190) ?>
            </div>
            <div class="fila">
                <?= dato('Nombre del tutor', $tutor['nombre'] ?? '') ?>
                <?= dato('Parentesco', $tutor['nombre_parentesco'] ?? '', 160) ?>
            </div>
            <div class="fila">
                <?= dato('&iquest;Qui&eacute;n nos recomend&oacute;?', $hc['quien_recomendo']) ?>
                <?= dato('&iquest;Primera visita?', siNoTexto($hc['primera_visita_dentista']), 130) ?>
                <?= dato('&Uacute;ltima visita', $hc['ultima_visita_dentista'], 200) ?>
            </div>
            <div class="fila">
                <div class="dato ancho">
                    <span class="et">Motivo de la consulta</span>
                    <span class="va"><?= e($hc['motivo_consulta'] ?? '') ?></span>
                </div>
            </div>
        </div>

        <div class="seccion">
            <h2>Antecedentes no patol&oacute;gicos</h2>
            <div class="fila">
                <?= dato('Embarazo normal', siNoTexto($hc['embarazo_normal']), 160) ?>
                <?= dato('Nacimiento', opcionTexto($hc['tipo_nacimiento'],
                    [1 => 'A término', 2 => 'Prematuro', 3 => 'Post-término']), 170) ?>
                <?= dato('Parto', opcionTexto($hc['tipo_parto'],
                    [1 => 'Natural', 2 => 'Cesárea']), 150) ?>
                <?= dato('Complicaciones', siNoTexto($hc['complicaciones_parto']), 150) ?>
            </div>
            <?php if (!empty($hc['complicaciones_detalle'])): ?>
            <div class="fila">
                <div class="dato ancho">
                    <span class="et">Complicaciones</span>
                    <span class="va"><?= e($hc['complicaciones_detalle']) ?></span>
                </div>
            </div>
            <?php endif; ?>
            <div class="fila">
                <?= dato('Lactancia materna', siNoTexto($hc['lactancia_materna']), 170) ?>
                <?= dato('Hasta', $hc['lactancia_hasta'], 150) ?>
                <?= dato('F&oacute;rmula', siNoTexto($hc['formula']), 130) ?>
                <?= dato('Hasta', $hc['formula_hasta'], 150) ?>
            </div>
            <div class="fila">
                <?= dato('Alimentaci&oacute;n complementaria', $hc['inicio_alimentacion_complementaria']) ?>
                <?= dato('Dieta actual', opcionTexto($hc['dieta_completa'],
                    [1 => 'Completa', 0 => 'Incompleta']), 160) ?>
                <?= dato('Momentos de az&uacute;car al d&iacute;a', $hc['momentos_azucar_dia'], 190) ?>
            </div>
            <?php if (!empty($hc['alimentos_azucar'])): ?>
            <div class="fila">
                <div class="dato ancho">
                    <span class="et">Alimentos con az&uacute;car</span>
                    <span class="va"><?= e($hc['alimentos_azucar']) ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="seccion">
            <h2>Antecedentes patol&oacute;gicos</h2>
            <div class="fila">
                <?= dato('Estado de salud', opcionTexto($hc['estado_salud'],
                    [1 => 'Bueno', 2 => 'Regular', 3 => 'Malo']), 170) ?>
                <?= dato('Enfermedad cr&oacute;nica', siNoTexto($hc['enfermedad_cronica']), 170) ?>
                <?= dato('Especifique', $hc['enfermedad_detalle']) ?>
            </div>
            <div class="fila">
                <?= dato('Discapacidad', siNoTexto($hc['discapacidad']), 150) ?>
                <?= dato('Especifique', $hc['discapacidad_detalle']) ?>
            </div>
            <div class="fila">
                <?= dato('Alergias', siNoTexto($hc['alergia']), 130) ?>
                <?= dato('Especifique', $hc['alergia_detalle']) ?>
            </div>
            <div class="fila">
                <?= dato('Tratamiento m&eacute;dico', siNoTexto($hc['tratamiento_medico']), 170) ?>
                <?= dato('Especifique', $hc['tratamiento_detalle']) ?>
            </div>
            <div class="fila">
                <?= dato('Cirug&iacute;as', siNoTexto($hc['cirugias']), 130) ?>
                <?= dato('Especifique', $hc['cirugias_detalle']) ?>
            </div>
            <div class="fila">
                <?= dato('M&eacute;dico tratante', $hc['medico_nombre']) ?>
                <?= dato('Tel&eacute;fono', $hc['medico_telefono'], 180) ?>
            </div>
        </div>
    </div>

    <div class="pie">
        <span>Expediente <?= e($paciente['num_expediente']) ?> &middot; <?= e($paciente['nombre_completo']) ?></span>
        <span>Hoja 1 de 2</span>
    </div>
</div>

<!-- ================= HOJA 2 ================= -->
<div class="hoja">
    <?php $encabezado('Historial cl&iacute;nico'); ?>

    <div class="cuerpo">

        <div class="seccion">
            <h2>Antecedentes odontol&oacute;gicos</h2>
            <div class="fila">
                <?= dato('Experiencia previa', siNoTexto($hc['experiencia_previa']), 170) ?>
                <?= dato('Recibi&oacute; prevenci&oacute;n', siNoTexto($hc['recibio_prevencion']), 180) ?>
                <?= dato('Usa pasta dental', siNoTexto($hc['usa_pasta_dental']), 160) ?>
                <?= dato('Cepillados al d&iacute;a', $hc['cepillados_dia'], 150) ?>
            </div>
            <div class="fila">
                <?= dato('Otro elemento de higiene', siNoTexto($hc['otro_higiene']), 200) ?>
                <?= dato('Especifique', $hc['otro_higiene_detalle']) ?>
            </div>
        </div>

        <div class="seccion">
            <h2>H&aacute;bitos</h2>
            <div class="fila">
                <div class="dato ancho">
                    <span class="et">Presentes</span>
                    <span class="va"><?= e($lista('HABITO')) ?></span>
                </div>
            </div>
        </div>

        <div class="seccion">
            <h2>Examen bucal &middot; Tejidos blandos</h2>
            <div class="fila">
                <?= dato('Labios', opcionTexto($hc['labios'],
                    [1 => 'Competentes', 2 => 'Incompetentes']), 170) ?>
                <?= dato('Lesiones en labios', $hc['labios_lesiones']) ?>
            </div>
            <div class="fila">
                <?= dato('Frenillos', opcionTexto($hc['frenillos'],
                    [1 => 'Normales', 2 => 'Anormales']), 160) ?>
                <?= dato('Lengua', opcionTexto($hc['lengua'],
                    [1 => 'Normal', 2 => 'Macroglosia', 3 => 'Geográfica', 4 => 'Escrotal']), 170) ?>
                <?= dato('Mucosa', $hc['mucosa']) ?>
            </div>
            <div class="fila">
                <?= dato('Lesiones en tejido gingival', $hc['lesiones_gingival']) ?>
            </div>
            <?php if (!empty($hc['observaciones_blandos'])): ?>
            <div class="fila">
                <div class="dato ancho">
                    <span class="et">Observaciones</span>
                    <span class="va"><?= e($hc['observaciones_blandos']) ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="seccion">
            <h2>Examen bucal &middot; Tejidos duros</h2>
            <div class="fila">
                <?= dato('Tipo de dentici&oacute;n', opcionTexto($hc['tipo_denticion'],
                    [1 => 'Temporal', 2 => 'Mixta', 3 => 'Permanente']), 190) ?>
            </div>
            <div class="fila">
                <?= dato('Anomal&iacute;as de n&uacute;mero', $lista('ANOM_NUMERO')) ?>
                <?= dato('Anomal&iacute;as de tama&ntilde;o', $lista('ANOM_TAMANO')) ?>
            </div>
            <div class="fila">
                <?= dato('Anomal&iacute;as de estructura', $lista('ANOM_ESTRUCTURA')) ?>
                <?= dato('Anomal&iacute;as de forma', $lista('ANOM_FORMA')) ?>
            </div>
            <?php if (!empty($hc['observaciones_duros'])): ?>
            <div class="fila">
                <div class="dato ancho">
                    <span class="et">Observaciones</span>
                    <span class="va"><?= e($hc['observaciones_duros']) ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="seccion" style="margin-top:.6cm">
            <div class="fila">
                <div class="dato ancho" style="border-bottom:0">
                    <span class="et" style="font-size:7.5pt;line-height:1.5">
                        Declaro que la informaci&oacute;n proporcionada es veraz y completa,
                        y me comprometo a informar al profesional de cualquier cambio en el
                        estado de salud de mi representado.
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="firmas">
        <div class="firma">
            <div class="linea"></div>
            <div class="nombre"><?= e($tutor['nombre'] ?? '') ?></div>
            <div class="cargo">Nombre y firma de padre o tutor</div>
        </div>
        <div class="firma">
            <div class="linea"></div>
            <div class="nombre"><?= e($dentista['nombre_completo'] ?? '') ?></div>
            <div class="cargo">Nombre y firma del doctor/a</div>
            <?php if (!empty($dentista['cedula_prof'])): ?>
                <div class="cargo">C&eacute;d. Prof. <?= e($dentista['cedula_prof']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="pie">
        <span>Capturado el <?= e(fechaHora($hc['fecha_captura'])) ?></span>
        <span>Hoja 2 de 2 &middot; <?= e(APP_NOMBRE) ?> v<?= e(APP_VERSION) ?></span>
    </div>
</div>

<script>
window.addEventListener('load', () => {
    // Si alguna hoja se desbordo, es mejor avisarlo aqui que descubrirlo
    // con la impresora andando.
    document.querySelectorAll('.cuerpo').forEach((c, i) => {
        if (c.scrollHeight > c.clientHeight) {
            const aviso = document.createElement('span');
            aviso.className = 'aviso';
            aviso.textContent = `El contenido de la hoja ${i + 1} no cabe completo.`;
            document.querySelector('.barra').appendChild(aviso);
        }
    });

    setTimeout(() => window.print(), 700);
});
</script>
</body>
</html>
