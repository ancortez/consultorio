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
            'secure'   => self::esHttps(),
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

    /**
     * ¿La conexion del usuario viene por HTTPS?
     *
     * En produccion el certificado lo maneja Caddy, que le habla a PHP
     * por HTTP dentro del servidor. Desde aqui $_SERVER['HTTPS'] llega
     * vacio y la cookie de sesion se marcaria como insegura: el
     * navegador la mandaria tambien por HTTP y bastaria con una red
     * wifi ajena para robar la sesion de la doctora.
     *
     * Por eso se revisa tambien la cabecera que pone el proxy. Solo se
     * le hace caso cuando el arranque dice que hay uno enfrente: si se
     * creyera siempre, cualquiera podria mandar esa cabecera desde
     * fuera y enganar al sistema.
     */
    private static function esHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        $detrasDeProxy = (string) (getenv('HTTPS_DETRAS_DE_PROXY') ?: '0');
        if ($detrasDeProxy !== '1') {
            return false;
        }

        return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
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
