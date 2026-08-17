<?php
/**
 * Preambulo comun de todos los endpoints AJAX.
 * Cada endpoint empieza con:
 *     require_once __DIR__ . '/../_endpoint.php';
 *     endpoint('paciente.ver');
 */
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

/**
 * Valida metodo, sesion, token CSRF y permiso, en ese orden.
 * Si algo falla corta la ejecucion con la respuesta JSON correspondiente.
 */
function endpoint(?string $permiso = null): void
{
    blindarSalidaJson();

    soloPost();
    Auth::exigirLogin();
    Csrf::verificar();
    if ($permiso !== null) {
        Auth::exigirPermiso($permiso);
    }
}

/**
 * Garantiza que un endpoint SIEMPRE responda JSON, pase lo que pase.
 *
 * Sin esto, cualquier error de PHP (una consulta mal armada, una tabla
 * que no existe) imprime HTML antes del JSON. El navegador entonces no
 * puede interpretar la respuesta y el usuario solo ve "El servidor
 * respondio algo inesperado", sin pista de que fallo realmente.
 *
 * Con esto, el error viaja como JSON: en desarrollo con el detalle
 * completo, en produccion con un mensaje generico y el detalle al log.
 */
function blindarSalidaJson(): void
{
    // Los warnings y notices se convierten en excepciones para que no
    // se cuelen impresos en medio de la respuesta.
    set_error_handler(static function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
        if ((error_reporting() & $nivel) === 0) {
            return false;
        }
        throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
    });

    set_exception_handler(static function (Throwable $e): void {
        error_log(sprintf('[ENDPOINT] %s en %s:%d — %s',
            get_class($e), $e->getFile(), $e->getLine(), $e->getMessage()));

        // Se descarta lo que se haya impreso antes del error.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (APP_ENV === 'produccion') {
            Respuesta::error('Ocurrio un error en el servidor. Intenta de nuevo.', 500);
        }

        Respuesta::json([
            'ok'      => false,
            'mensaje' => 'Error del servidor: ' . $e->getMessage(),
            'errores' => [],
            'debug'   => [
                'tipo'    => get_class($e),
                'archivo' => basename($e->getFile()),
                'linea'   => $e->getLine(),
            ],
        ], 500);
    });

    // Errores fatales que no pasan por el manejador de excepciones.
    register_shutdown_function(static function (): void {
        $ultimo = error_get_last();
        if ($ultimo === null
            || !in_array($ultimo['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        error_log('[ENDPOINT FATAL] ' . $ultimo['message']);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        Respuesta::json([
            'ok'      => false,
            'mensaje' => APP_ENV === 'produccion'
                ? 'Ocurrio un error en el servidor.'
                : 'Error fatal: ' . $ultimo['message'],
            'errores' => [],
        ], 500);
    });

    ob_start();
}
