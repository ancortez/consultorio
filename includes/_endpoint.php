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
    revisarEnvioDemasiadoGrande();
    Auth::exigirLogin();
    Csrf::verificar();
    if ($permiso !== null) {
        Auth::exigirPermiso($permiso);
    }
}

/**
 * Detecta el envio que PHP tiro antes de que llegara.
 *
 * Cuando lo que se manda pesa mas que post_max_size, PHP NO da un
 * error: descarta el cuerpo entero y sigue como si no hubiera llegado
 * nada. $_POST queda vacio, $_FILES tambien, y hasta el token CSRF
 * desaparece.
 *
 * Desde el otro lado eso se ve asi: la doctora arrastra ocho
 * radiografias, le da guardar, y el sistema contesta "Selecciona al
 * menos un archivo" o "La sesion del formulario expiro". Dos mensajes
 * que no tienen nada que ver con lo que paso y que la mandan a buscar
 * el problema donde no esta. Es exactamente el tipo de falla que
 * convence a alguien de que el sistema "a veces no guarda".
 *
 * La pista esta en que el navegador SI anuncio el tamano, en
 * CONTENT_LENGTH, aunque PHP haya tirado el contenido.
 */
function revisarEnvioDemasiadoGrande(): void
{
    $anunciado = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($anunciado <= 0) {
        return;
    }

    $limite = enBytes((string) ini_get('post_max_size'));
    if ($limite <= 0 || $anunciado <= $limite) {
        return;
    }

    Respuesta::error(sprintf(
        'Lo que intentas subir pesa %s y el limite por envio es %s. '
        . 'Sube los archivos en dos tandas.',
        formatearBytes($anunciado), formatearBytes($limite)
    ), 413);
}

/** Convierte "20M", "2G" o "512K" a bytes. */
function enBytes(string $valor): int
{
    $valor = trim($valor);
    if ($valor === '') {
        return 0;
    }

    $numero = (int) $valor;
    return match (strtolower(substr($valor, -1))) {
        'g'     => $numero * 1024 * 1024 * 1024,
        'm'     => $numero * 1024 * 1024,
        'k'     => $numero * 1024,
        default => $numero,
    };
}

/** 1572864 -> "1.5 MB". Para mensajes que lee una persona. */
function formatearBytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return rtrim(rtrim(number_format($bytes / 1024 / 1024, 1), '0'), '.') . ' MB';
    }
    return max(1, (int) round($bytes / 1024)) . ' KB';
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

        // "Falta migrar" se dice con su nombre incluso en produccion.
        // Es lo contrario de filtrar un detalle interno: sin esto el
        // usuario lee "intenta de nuevo", lo intenta, vuelve a fallar
        // igual, y nadie se entera de que lo que falta es una columna.
        $aviso = Esquema::avisoDeFaltaMigrar($e);
        if ($aviso !== null) {
            Respuesta::error(
                strip_tags(html_entity_decode($aviso, ENT_QUOTES, 'UTF-8'))
                . ' Corre: ' . Esquema::comoArreglarlo(), 500);
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
