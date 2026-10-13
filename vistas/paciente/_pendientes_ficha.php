<?php
/**
 * Los pendientes de digitalizar del paciente.
 *
 * Esta en su propio archivo porque se movio de sitio y ya se habia
 * movido una vez: vivio en la quinta tarjeta de la rejilla de la
 * derecha, a todo lo ancho, y ahora va dentro de la tarjeta de datos,
 * debajo de los canales de contacto. Teniendolo aparte, el siguiente
 * cambio de sitio es una linea y no un corta y pega de cuarenta.
 *
 * Espera dos variables ya cargadas por la vista que lo incluye:
 *   $porEscanear  los documentos sin archivo
 *   $pendientes   cuantos son (puede ser mas que los que se listan)
 */
?>
<div class="ficha-seccion mt-3">
    <i class="bi bi-hourglass-split"></i>
    <span>Pendientes de digitalizar</span>
    <?php if ($pendientes > 0): ?>
    <span class="badge badge-pendiente ms-2"><?= (int) $pendientes ?></span>
    <?php endif; ?>
</div>

<div class="pendientes-ficha">
    <?php if (!$porEscanear): ?>
        <div class="exp-vacio">Nada por escanear.</div>
    <?php else: foreach ($porEscanear as $d): ?>
    <?php /* Se sube desde aqui mismo. Mandar a la persona al expediente
             a buscar el documento es el paso que hace que los pendientes
             se queden pendientes para siempre. */ ?>
    <button type="button" class="renglon-ficha pendiente"
            data-subir-doc="<?= (int) $d['id_documento'] ?>"
            data-formatos="<?= e($d['formatos']) ?>">
        <i class="bi <?= e($d['icono'] ?: 'bi-file-earmark') ?>"></i>
        <div style="min-width:0">
            <div class="rf-titulo"><?= e($d['nombre_documento']) ?></div>
            <?php if ($d['descripcion']): ?>
            <div class="rf-sub"><?= e($d['descripcion']) ?></div>
            <?php endif; ?>
        </div>
        <i class="bi bi-cloud-arrow-up subir"></i>
    </button>
    <?php endforeach; endif; ?>
</div>
