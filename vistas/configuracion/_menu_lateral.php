<?php
/**
 * Menu lateral del modulo Configuracion.
 */
$opciones = [
    ['texto' => 'Usuarios y roles', 'icono' => 'bi-people',
     'vista' => 'usuarios',  'permiso' => 'config.usuarios'],
    ['texto' => 'Colonias', 'icono' => 'bi-geo-alt',
     'vista' => 'colonias',  'permiso' => 'config.colonias'],
    ['texto' => 'Procedimientos dentales', 'icono' => 'bi-clipboard2-plus',
     'vista' => 'procedimientos', 'permiso' => 'config.procedimientos'],
    ['texto' => 'Tarifario', 'icono' => 'bi-tags',
     'vista' => 'tarifas',   'permiso' => 'config.tarifas'],
    ['texto' => 'Datos del consultorio', 'icono' => 'bi-shop',
     'vista' => 'consultorio', 'permiso' => 'config.consultorio'],
    ['texto' => 'Recordatorios de cita', 'icono' => 'bi-whatsapp',
     'vista' => 'recordatorios', 'permiso' => 'config.consultorio'],
    ['texto' => 'Apariencia', 'icono' => 'bi-palette',
     'vista' => 'apariencia', 'permiso' => 'config.consultorio'],
    // Al final y con su propio permiso: solo lo ve el administrador.
    // Un respaldo trae el expediente completo de todos los pacientes y
    // restaurar reemplaza todo, asi que no cuelga de config.consultorio
    // como las de arriba.
    ['texto' => 'Respaldos', 'icono' => 'bi-shield-check',
     'vista' => 'respaldos', 'permiso' => 'config.respaldos'],
];
?>
<aside class="menu-lateral">
    <div class="titulo">Configuraci&oacute;n</div>
    <?php foreach ($opciones as $o): ?>
        <?php if (!Auth::puede($o['permiso'])) { continue; } ?>
        <a class="item <?= (isset($vista) && $vista === $o['vista']) ? 'activo' : '' ?>"
           href="<?= URL_BASE ?>/index.php?modulo=configuracion&vista=<?= e($o['vista']) ?>">
            <i class="bi <?= $o['icono'] ?>"></i>
            <span><?= e($o['texto']) ?></span>
        </a>
    <?php endforeach; ?>
</aside>
