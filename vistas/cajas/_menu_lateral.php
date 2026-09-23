<?php
/**
 * Menu lateral del modulo Cajas.
 */
$opciones = [
    ['texto' => 'Cobrar', 'icono' => 'bi-cash-coin',
     'vista' => 'cobrar', 'permiso' => 'cajas.cobrar'],
    ['texto' => 'Turnos y corte', 'icono' => 'bi-calculator',
     'vista' => 'corte', 'permiso' => 'cajas.ver'],
    ['texto' => 'Pendientes de pago', 'icono' => 'bi-exclamation-circle',
     'vista' => 'pendientes', 'permiso' => 'cajas.ver'],
];
?>
<aside class="menu-lateral">
    <div class="titulo">Cajas</div>
    <?php foreach ($opciones as $o): ?>
        <?php if (!Auth::puede($o['permiso'])) { continue; } ?>
        <a class="item <?= (isset($vista) && $vista === $o['vista']) ? 'activo' : '' ?>"
           href="<?= URL_BASE ?>/index.php?modulo=cajas&vista=<?= e($o['vista']) ?>">
            <i class="bi <?= $o['icono'] ?>"></i>
            <span><?= e($o['texto']) ?></span>
        </a>
    <?php endforeach; ?>
</aside>
