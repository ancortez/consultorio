<?php
/**
 * Plantilla general del sistema.
 *
 * Se compone de tres partes, tal como pide la composicion:
 *   1. nav.php    - menu superior con submenus
 *   2. contenido  - aqui se carga la vista solicitada
 *   3. footer.php - franja inmovil al pie
 *
 * MEJORA: en lugar de que cada vista sea un archivo suelto accesible por
 * URL, todas pasan por este unico punto de entrada. Asi la validacion de
 * sesion y de permisos se hace en un solo lugar y no se puede saltar
 * escribiendo la ruta directa en el navegador.
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

Auth::exigirLogin();

// ---------------------------------------------------------------------
// Router de vistas: modulo/vista
// ---------------------------------------------------------------------
$modulo = preg_replace('/[^a-z_]/', '', strtolower((string) param('modulo', 'inicio')));
$vista  = preg_replace('/[^a-z_]/', '', strtolower((string) param('vista',  'inicio')));

// Mapa blanco de vistas: si no esta aqui, no existe.
// Formato: 'modulo/vista' => ['archivo' => ruta relativa, 'permiso' => clave]
$rutas = [
    'inicio/inicio' => [
        'archivo' => 'inicio.php',
        'permiso' => null,
        'titulo'  => 'Inicio',
    ],
    'paciente/buscar_paciente' => [
        'archivo' => 'paciente/buscar_paciente.php',
        'permiso' => 'paciente.ver',
        'titulo'  => 'Buscar paciente',
    ],
    'paciente/detalle_paciente' => [
        'archivo' => 'paciente/detalle_paciente.php',
        'permiso' => 'paciente.ver',
        'titulo'  => 'Detalle del paciente',
    ],
    'consultas/agenda' => [
        'archivo' => 'consultas/agenda.php',
        'permiso' => 'consultas.ver',
        'titulo'  => 'Agenda',
    ],
    'consultas/semana' => [
        'archivo' => 'consultas/semana.php',
        'permiso' => 'consultas.ver',
        'titulo'  => 'Agenda de la semana',
    ],
    'consultas/consultas' => [
        'archivo' => 'consultas/consultas.php',
        'permiso' => 'consultas.ver',
        'titulo'  => 'Lista de consultas',
    ],
    'consultas/consulta' => [
        'archivo' => 'consultas/consulta.php',
        'permiso' => 'consultas.ver',
        'titulo'  => 'Detalle de consulta',
    ],
    'consultas/planes' => [
        'archivo' => 'consultas/planes.php',
        'permiso' => 'consultas.plan',
        'titulo'  => 'Planes de tratamiento',
    ],
    'consultas/nuevo_plan_tratamiento' => [
        'archivo' => 'consultas/nuevo_plan_tratamiento.php',
        'permiso' => 'consultas.plan',
        'titulo'  => 'Nuevo plan de tratamiento',
    ],
    'cajas/cobrar' => [
        'archivo' => 'cajas/cobrar.php',
        'permiso' => 'cajas.cobrar',
        'titulo'  => 'Cobrar',
    ],
    'cajas/corte' => [
        'archivo' => 'cajas/corte.php',
        'permiso' => 'cajas.ver',
        'titulo'  => 'Turnos y corte',
    ],
    'cajas/pendientes' => [
        'archivo' => 'cajas/pendientes.php',
        'permiso' => 'cajas.ver',
        'titulo'  => 'Pendientes de pago',
    ],
    'reportes/general' => [
        'archivo' => 'en_construccion.php',
        'permiso' => 'reportes.ver',
        'titulo'  => 'Reportes',
    ],
    'configuracion/usuarios' => [
        'archivo' => 'configuracion/usuarios.php',
        'permiso' => 'config.usuarios',
        'titulo'  => 'Usuarios y roles',
    ],
    'configuracion/colonias' => [
        'archivo' => 'configuracion/colonias.php',
        'permiso' => 'config.colonias',
        'titulo'  => 'Colonias',
    ],
    'configuracion/procedimientos' => [
        'archivo' => 'configuracion/procedimientos.php',
        'permiso' => 'config.procedimientos',
        'titulo'  => 'Procedimientos dentales',
    ],
    'configuracion/consultorio' => [
        'archivo' => 'configuracion/consultorio.php',
        'permiso' => 'config.consultorio',
        'titulo'  => 'Datos del consultorio',
    ],
    'configuracion/recordatorios' => [
        'archivo' => 'configuracion/recordatorios.php',
        'permiso' => 'config.consultorio',
        'titulo'  => 'Recordatorios de cita',
    ],
    'configuracion/apariencia' => [
        'archivo' => 'configuracion/apariencia.php',
        'permiso' => 'config.consultorio',
        'titulo'  => 'Apariencia',
    ],
    'configuracion/tarifas' => [
        'archivo' => 'en_construccion.php',
        'permiso' => 'config.tarifas',
        'titulo'  => 'Tarifario',
    ],
    'facturacion/emitir' => [
        'archivo' => 'en_construccion.php',
        'permiso' => 'factura.emitir',
        'titulo'  => 'Emitir factura',
    ],
];

$clave = $modulo . '/' . $vista;
if (!isset($rutas[$clave])) {
    $clave  = 'inicio/inicio';
    $modulo = 'inicio';
    $vista  = 'inicio';
}

$ruta = $rutas[$clave];
if ($ruta['permiso'] !== null) {
    Auth::exigirPermiso($ruta['permiso']);
}

$archivoVista = RUTA_VISTAS . '/' . $ruta['archivo'];
$usuarioSesion = Auth::usuario();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=1280">
<meta name="csrf-token" content="<?= Csrf::token() ?>">
<meta name="url-base" content="<?= URL_BASE ?>">
<title><?= e($ruta['titulo']) ?> &middot; <?= e(APP_NOMBRE) ?></title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= URL_BASE ?>/assets/css/estilo.css?v=<?= e(APP_VERSION) ?>">
<?php
/* El color del sistema se elige en Configuracion. Va despues de la
   hoja de estilo para que gane por orden, sin necesidad de !important.
   Con el tema de fabrica esto no imprime nada. */
echo Tema::estilos();
?>
</head>
<body>

<!-- Cortinilla: se quita sola en cuanto la vista termina de acomodarse -->
<div id="cortinilla">
    <div class="marca"><i class="bi bi-heart-pulse"></i></div>
    <div class="texto">Cargando <?= e(mb_strtolower($ruta['titulo'], 'UTF-8')) ?>...</div>
    <div class="barra"><span></span></div>
</div>

<div class="aviso-pantalla-chica">
    <i class="bi bi-display"></i>
    Este sistema est&aacute; dise&ntilde;ado para pantallas de escritorio de 1280&nbsp;px o m&aacute;s.
</div>

<?php require RUTA_VISTAS . '/layout/nav.php'; ?>

<main class="container-fluid px-4 py-4">
    <?php
    if (is_file($archivoVista)) {
        require $archivoVista;
    } else {
        echo '<div class="alert alert-danger">La vista solicitada no existe.</div>';
    }
    ?>
</main>

<?php
/* Modales compartidos: los usan varias pantallas, asi que viven en el
   layout y no en cada vista. Antes estaban dentro del detalle del
   paciente y por eso no se podian abrir desde Configuracion. */
require RUTA_VISTAS . '/layout/_modales_comunes.php';
?>

<?php require RUTA_VISTAS . '/layout/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="<?= URL_BASE ?>/assets/js/app.js?v=<?= e(APP_VERSION) ?>"></script>
<script src="<?= URL_BASE ?>/assets/js/comun.js?v=<?= e(APP_VERSION) ?>"></script>
<?php
// Cada modulo carga solo su archivo de JavaScript.
$jsModulo = RUTA_BASE . '/assets/js/' . $modulo . '.js';
if (is_file($jsModulo)):
?>
<script src="<?= URL_BASE ?>/assets/js/<?= e($modulo) ?>.js?v=<?= e(APP_VERSION) ?>"></script>
<?php endif; ?>
<?php if ($modulo === 'paciente' && $vista === 'detalle_paciente'): ?>
<script src="<?= URL_BASE ?>/assets/js/expediente.js?v=<?= e(APP_VERSION) ?>"></script>
<script src="<?= URL_BASE ?>/assets/js/historia.js?v=<?= e(APP_VERSION) ?>"></script>
<?php endif; ?>
</body>
</html>
