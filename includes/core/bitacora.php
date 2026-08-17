<?php
declare(strict_types=1);

/**
 * Registro de auditoria.
 *
 * En un sistema que guarda historia clinica y movimientos de dinero, saber
 * quien hizo que y cuando no es un lujo: es la unica forma de aclarar una
 * diferencia de caja o un cambio en un expediente.
 */
final class Bitacora
{
    public static function registrar(
        string  $modulo,
        string  $accion,
        ?string $tabla = null,
        ?string $idRegistro = null,
        ?array  $detalle = null,
        ?int    $idUsuario = null
    ): void {
        try {
            Conexion::consulta(
                'INSERT INTO bitacora
                    (id_usuario, modulo, accion, tabla_afectada, id_registro, detalle, ip)
                 VALUES (:u, :m, :a, :t, :r, :d, :ip)',
                [
                    ':u'  => $idUsuario ?? (class_exists('Auth') ? Auth::id() : null),
                    ':m'  => $modulo,
                    ':a'  => $accion,
                    ':t'  => $tabla,
                    ':r'  => $idRegistro,
                    ':d'  => $detalle !== null
                        ? json_encode($detalle, JSON_UNESCAPED_UNICODE)
                        : null,
                    ':ip' => self::ip(),
                ]
            );
        } catch (Throwable $e) {
            // La bitacora nunca debe tumbar la operacion principal.
            error_log('[BITACORA] ' . $e->getMessage());
        }
    }

    private static function ip(): string
    {
        return substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 45);
    }
}
