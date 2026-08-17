<?php
/**
 * Configuracion general del sistema.
 * Los valores sensibles se leen de variables de entorno (docker-compose / .env).
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Entorno
// ---------------------------------------------------------------------
define('APP_ENV',    getenv('APP_ENV') ?: 'desarrollo');
define('APP_NOMBRE', 'Sistema Consultorio Dental');
define('APP_VERSION', '1.5.0');
define('APP_TZ',     getenv('APP_TZ') ?: 'America/Mazatlan');

date_default_timezone_set(APP_TZ);
setlocale(LC_TIME, 'es_MX.UTF-8', 'es_MX', 'spanish');

// En produccion los errores se registran, nunca se muestran al usuario.
if (APP_ENV === 'produccion') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// ---------------------------------------------------------------------
// Rutas
// ---------------------------------------------------------------------
define('RUTA_BASE',    dirname(__DIR__));
define('RUTA_VISTAS',  RUTA_BASE . '/vistas');
define('RUTA_INCLUDES', RUTA_BASE . '/includes');
define('RUTA_STORAGE', RUTA_BASE . '/storage');

// El DocumentRoot es la raiz del proyecto, asi que la URL base es vacia.
// Si el sistema se publica en un subdirectorio, cambiar aqui (ej. '/consultorio').
define('URL_BASE', getenv('URL_BASE') !== false ? getenv('URL_BASE') : '');

// ---------------------------------------------------------------------
// Base de datos
// ---------------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'consultorio_dental');
define('DB_USER', getenv('DB_USER') ?: 'consultorio');
define('DB_PASS', getenv('DB_PASS') ?: 'consultorio');

// ---------------------------------------------------------------------
// Reglas de negocio (respaldo si la tabla parametro no responde)
// ---------------------------------------------------------------------
define('MAX_INTENTOS_LOGIN', 5);
define('MINUTOS_BLOQUEO',    15);
define('MINUTOS_SESION',     60);
define('ESTADO_DEFAULT',     18);   // Nayarit
define('PACIENTES_INICIO',   25);
