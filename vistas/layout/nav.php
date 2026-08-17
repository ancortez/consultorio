<?php
/**
 * Menu superior con submenus.
 * Las opciones se filtran por permiso: el usuario solo ve lo que puede usar.
 */

$menu = [
    ['clave' => 'inicio', 'texto' => 'Inicio', 'icono' => 'bi-house',
     'url' => '?modulo=inicio&vista=inicio', 'permiso' => null],

    ['clave' => 'paciente', 'texto' => 'Paciente', 'icono' => 'bi-person-vcard',
     'permiso' => 'paciente.ver', 'submenu' => [
        ['texto' => 'Buscar paciente', 'icono' => 'bi-search',
         'url' => '?modulo=paciente&vista=buscar_paciente', 'permiso' => 'paciente.ver'],
        ['texto' => 'Nuevo paciente', 'icono' => 'bi-person-plus',
         'url' => '#', 'permiso' => 'paciente.crear', 'accion' => 'nuevo-paciente'],
     ]],

    ['clave' => 'cajas', 'texto' => 'Cajas', 'icono' => 'bi-cash-coin',
     'permiso' => 'cajas.ver', 'submenu' => [
        ['texto' => 'Registrar cobro', 'icono' => 'bi-receipt-cutoff',
         'url' => '?modulo=cajas&vista=cobrar', 'permiso' => 'cajas.cobrar'],
        ['texto' => 'Corte de caja', 'icono' => 'bi-calculator',
         'url' => '?modulo=cajas&vista=corte', 'permiso' => 'cajas.ver'],
     ]],

    ['clave' => 'consultas', 'texto' => 'Consultas', 'icono' => 'bi-clipboard2-pulse',
     'permiso' => 'consultas.ver', 'submenu' => [
        ['texto' => 'Agenda', 'icono' => 'bi-calendar3',
         'url' => '?modulo=consultas&vista=agenda', 'permiso' => 'consultas.ver'],
        ['texto' => 'Nueva consulta', 'icono' => 'bi-clipboard2-plus',
         'url' => '?modulo=consultas&vista=nueva_consulta', 'permiso' => 'consultas.crear'],
        ['texto' => 'Plan de tratamiento', 'icono' => 'bi-list-check',
         'url' => '?modulo=consultas&vista=nuevo_plan_tratamiento', 'permiso' => 'consultas.plan'],
     ]],

    ['clave' => 'reportes', 'texto' => 'Reportes', 'icono' => 'bi-graph-up',
     'url' => '?modulo=reportes&vista=general', 'permiso' => 'reportes.ver'],

    ['clave' => 'configuracion', 'texto' => 'Configuraci&oacute;n', 'icono' => 'bi-gear',
     'permiso' => null, 'submenu' => [
        ['texto' => 'Usuarios y roles', 'icono' => 'bi-people',
         'url' => '?modulo=configuracion&vista=usuarios', 'permiso' => 'config.usuarios'],
        ['texto' => 'Colonias', 'icono' => 'bi-geo-alt',
         'url' => '?modulo=configuracion&vista=colonias', 'permiso' => 'config.colonias'],
        ['texto' => 'Tarifario', 'icono' => 'bi-tags',
         'url' => '?modulo=configuracion&vista=tarifas', 'permiso' => 'config.tarifas'],
     ]],

    ['clave' => 'facturacion', 'texto' => 'Facturaci&oacute;n', 'icono' => 'bi-receipt',
     'url' => '?modulo=facturacion&vista=emitir', 'permiso' => 'factura.emitir'],
];

/** Deja solo lo que el usuario tiene permitido ver. */
$visible = static function (array $item): bool {
    return $item['permiso'] === null || Auth::puede($item['permiso']);
};
?>
<nav class="navbar navbar-expand navbar-sistema sticky-top px-3">
    <a class="navbar-brand d-flex align-items-center gap-2 me-4"
       href="<?= URL_BASE ?>/index.php">
        <i class="bi bi-heart-pulse fs-5"></i>
        <span>Consultorio Dental</span>
    </a>

    <ul class="navbar-nav align-items-center gap-1">
    <?php foreach ($menu as $item): ?>
        <?php if (!$visible($item)) { continue; } ?>

        <?php if (empty($item['submenu'])): ?>
            <li class="nav-item">
                <a class="nav-link <?= $modulo === $item['clave'] ? 'activo' : '' ?>"
                   href="<?= URL_BASE ?>/index.php<?= $item['url'] ?>">
                    <i class="bi <?= $item['icono'] ?> me-1"></i><?= $item['texto'] ?>
                </a>
            </li>
        <?php else: ?>
            <?php
            $hijos = array_filter($item['submenu'], $visible);
            if (!$hijos) { continue; }
            ?>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle <?= $modulo === $item['clave'] ? 'activo' : '' ?>"
                   href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi <?= $item['icono'] ?> me-1"></i><?= $item['texto'] ?>
                </a>
                <ul class="dropdown-menu">
                    <?php foreach ($hijos as $hijo): ?>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2"
                           href="<?= $hijo['url'] === '#' ? '#' : URL_BASE . '/index.php' . $hijo['url'] ?>"
                           <?= isset($hijo['accion']) ? 'data-accion="' . e($hijo['accion']) . '"' : '' ?>>
                            <i class="bi <?= $hijo['icono'] ?>"></i>
                            <span><?= $hijo['texto'] ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </li>
        <?php endif; ?>
    <?php endforeach; ?>
    </ul>

    <!-- Usuario, al extremo derecho -->
    <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2"
               href="#" role="button" data-bs-toggle="dropdown">
                <span class="avatar-usuario">
                    <?= e(iniciales($usuarioSesion['nombre_completo'])) ?>
                </span>
                <span class="d-flex flex-column lh-1 text-start">
                    <span class="fw-semibold" style="font-size:13px">
                        <?= e($usuarioSesion['nombre_completo']) ?>
                    </span>
                    <span style="font-size:11px;opacity:.8">
                        <?= e($usuarioSesion['nombre_rol']) ?>
                    </span>
                </span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="#">
                    <i class="bi bi-person-gear"></i> Mi perfil</a></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="#">
                    <i class="bi bi-key"></i> Cambiar contrase&ntilde;a</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2 text-danger"
                       href="<?= URL_BASE ?>/logout.php">
                    <i class="bi bi-box-arrow-right"></i> Cerrar sesi&oacute;n</a></li>
            </ul>
        </li>
    </ul>
</nav>
