<?php
declare(strict_types=1);

/**
 * Formato unico de respuesta JSON para todos los endpoints.
 * El front (assets/js/app.js) siempre espera:
 *     { ok: bool, mensaje: string, datos: mixed, errores: object }
 */
final class Respuesta
{
    public static function json(array $cuerpo, int $codigo = 200): void
    {
        // Se tira cualquier cosa que se haya impreso antes (un warning,
        // un espacio suelto al final de un include). Si eso llega junto
        // al JSON, el navegador ya no puede interpretarlo.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($codigo);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok($datos = null, string $mensaje = 'Operacion realizada.'): void
    {
        self::json(['ok' => true, 'mensaje' => $mensaje, 'datos' => $datos]);
    }

    public static function error(string $mensaje, int $codigo = 400, array $errores = []): void
    {
        self::json([
            'ok'      => false,
            'mensaje' => $mensaje,
            'errores' => $errores,
        ], $codigo);
    }

    /** Errores de validacion campo por campo. */
    public static function validacion(array $errores): void
    {
        self::error('Revisa los datos marcados.', 422, $errores);
    }
}
