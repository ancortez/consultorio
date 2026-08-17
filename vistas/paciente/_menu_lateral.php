<?php
/**
 * Menu lateral que se ve en toda la navegacion del modulo Paciente.
 * $vista viene del router (index.php).
 */
$opciones = [
    ['texto' => 'Buscar paciente', 'icono' => 'bi-search',
     'url' => URL_BASE . '/index.php?modulo=paciente&vista=buscar_paciente',
     'vista' => 'buscar_paciente', 'permiso' => 'paciente.ver'],
    ['texto' => 'Nuevo paciente', 'icono' => 'bi-person-plus',
     'url' => '#', 'accion' => 'nuevo-paciente',
     'vista' => null, 'permiso' => 'paciente.crear'],
];
?>
<aside class="menu-lateral">
    <div class="titulo">M&oacute;dulo Paciente</div>
    <?php foreach ($opciones as $o): ?>
        <?php if (!Auth::puede($o['permiso'])) { continue; } ?>
        <a class="item <?= (isset($vista) && $vista === $o['vista']) ? 'activo' : '' ?>"
           href="<?= e($o['url']) ?>"
           <?= isset($o['accion']) ? 'data-accion="' . e($o['accion']) . '"' : '' ?>>
            <i class="bi <?= $o['icono'] ?>"></i>
            <span><?= e($o['texto']) ?></span>
        </a>
    <?php endforeach; ?>

    <hr class="my-2">
    <div class="titulo">Accesos r&aacute;pidos</div>
    <a class="item" href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=agenda">
        <i class="bi bi-calendar3"></i><span>Agenda del d&iacute;a</span>
    </a>
    <a class="item" href="<?= URL_BASE ?>/index.php?modulo=cajas&vista=cobrar">
        <i class="bi bi-receipt-cutoff"></i><span>Registrar cobro</span>
    </a>
</aside>
