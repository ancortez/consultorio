<?php
/**
 * Historia clinica, a pantalla completa.
 *
 * Vivia en una pestania del detalle del paciente. Octubre 2026: el
 * detalle paso a ser una ficha de tres columnas y la historia no cabia
 * en un tercio de pantalla sin apretarse. Son cinco pasos con decenas
 * de preguntas: necesita el ancho entero.
 *
 * El contenido es el mismo archivo de antes; aqui solo se le pone
 * encabezado y camino de regreso.
 */
$idExpediente = paramInt('id');

$pac = $idExpediente
    ? Conexion::uno('SELECT * FROM v_paciente WHERE id_registro_paciente = :r',
                    [':r' => $idExpediente])
    : null;

if ($pac === null) {
    echo '<div class="alert alert-warning d-flex align-items-center gap-2">'
       . '<i class="bi bi-exclamation-triangle"></i>'
       . 'No se encontr&oacute; el expediente. '
       . '<a href="' . URL_BASE . '/index.php?modulo=paciente&vista=buscar_paciente">'
       . 'Buscar paciente</a></div>';
    return;
}
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <nav style="font-size:12px" class="mb-1">
            <a href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=detalle_paciente&id=<?= $idExpediente ?>"
               class="text-decoration-none">
                <i class="bi bi-arrow-left"></i> <?= e($pac['nombre_completo']) ?>
            </a>
        </nav>
        <h1 class="titulo-vista">Historia cl&iacute;nica</h1>
        <p class="subtitulo-vista">
            <?= e($pac['num_expediente']) ?>
            <?= $pac['edad'] !== null ? ' &middot; ' . (int) $pac['edad'] . ' a&ntilde;os' : '' ?>
        </p>
    </div>
</div>

<div id="pacienteContexto" class="d-none"
     data-id-expediente="<?= $idExpediente ?>"></div>

<?php require __DIR__ . '/_tab_historia.php'; ?>
