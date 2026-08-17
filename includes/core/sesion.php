<?php
declare(strict_types=1);

/**
 * Manejo de la sesion con caducidad por inactividad y regeneracion de id.
 */
final class Sesion
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('CONSULTORIO_SID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();

        // Caducidad por inactividad
        $limite = ((int) (defined('MINUTOS_SESION') ? MINUTOS_SESION : 60)) * 60;
        if (isset($_SESSION['ultima_actividad'])
            && (time() - (int) $_SESSION['ultima_actividad']) > $limite) {
            self::destruir();
            session_start();
            $_SESSION['aviso_sesion'] = 'La sesion expiro por inactividad.';
        }
        $_SESSION['ultima_actividad'] = time();
    }

    public static function set(string $clave, $valor): void
    {
        $_SESSION[$clave] = $valor;
    }

    public static function get(string $clave, $default = null)
    {
        return $_SESSION[$clave] ?? $default;
    }

    public static function quitar(string $clave): void
    {
        unset($_SESSION[$clave]);
    }

    /** Lee y borra (mensajes de un solo uso). */
    public static function flash(string $clave, $default = null)
    {
        $v = $_SESSION[$clave] ?? $default;
        unset($_SESSION[$clave]);
        return $v;
    }

    public static function regenerar(): void
    {
        session_regenerate_id(true);
    }

    public static function destruir(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
