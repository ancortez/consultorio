<?php
/**
 * Franja inmovil al pie: domicilio del consultorio, nombre del sistema
 * y version. Los datos salen de la tabla consultorio / parametro, no
 * estan escritos a mano, para que el cliente los pueda cambiar solo.
 */
$negocio = Conexion::uno(
    'SELECT c.nombre_comercial, c.telefono,
            CONCAT_WS(", ",
              CONCAT_WS(" ", d.calle, d.num_ext),
              col.nombre_colonia,
              mun.nombre_municipio,
              est.nombre_estado) AS domicilio
       FROM consultorio c
       LEFT JOIN domicilio d   ON d.id_domicilio   = c.id_domicilio
       LEFT JOIN colonia   col ON col.id_colonia   = d.id_colonia
       LEFT JOIN municipio mun ON mun.id_municipio = col.id_municipio
       LEFT JOIN estado    est ON est.id_estado    = mun.id_estado
      WHERE c.id_consultorio = 1'
);
?>
<footer class="footer-sistema">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-geo-alt"></i>
        <span>
            <?= e($negocio['domicilio'] ?? 'Domicilio del consultorio por definir') ?>
        </span>
        <?php if (!empty($negocio['telefono'])): ?>
            <span class="separador">|</span>
            <i class="bi bi-telephone"></i>
            <span><?= e($negocio['telefono']) ?></span>
        <?php endif; ?>
    </div>

    <div class="d-flex align-items-center gap-2">
        <span><?= e(parametro('nombre_sistema', APP_NOMBRE)) ?></span>
        <span class="separador">|</span>
        <?php /* La version sale del codigo, nunca de la base: si se
                 restaura un respaldo viejo, la base reportaria una
                 version que no corresponde a los archivos instalados. */ ?>
        <span>v<?= e(APP_VERSION) ?></span>
        <?php if (APP_ENV !== 'produccion'): ?>
            <span class="separador">|</span>
            <span class="badge bg-warning text-dark" style="font-size:10px">
                <?= e(strtoupper(APP_ENV)) ?>
            </span>
        <?php endif; ?>
    </div>
</footer>
