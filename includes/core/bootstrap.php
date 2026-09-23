<?php
/**
 * Punto de entrada comun. Todo archivo PHP del sistema empieza con:
 *     require_once __DIR__ . '/../includes/core/bootstrap.php';
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/respuesta.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bitacora.php';
require_once __DIR__ . '/almacen.php';
require_once __DIR__ . '/imagen.php';
require_once __DIR__ . '/documentos.php';
require_once __DIR__ . '/agenda.php';
require_once __DIR__ . '/tema.php';
require_once __DIR__ . '/cargos.php';
require_once __DIR__ . '/caja.php';
require_once __DIR__ . '/agenda_extra.php';

Sesion::iniciar();
