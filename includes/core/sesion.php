<?php
declare(strict_types=1);

/**
 * Manejo de la sesion: cookie, caducidad y regeneracion de id.
 *
 * La caducidad por inactividad se apaga con MINUTOS_SESION = 0, que es
 * como esta hoy: lo pidio la doctora. Ver el comentario del parametro en
 * config.php, que explica lo que cuesta y como se vuelve atras.
 */
final class Sesion
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $minutos = (int) (defined('MINUTOS_SESION') ? MINUTOS_SESION : 60);
        $limite  = $minutos * 60;

        /*
           La cookie: hasta cerrar el navegador, o un anio.

           Con 'lifetime' => 0 la cookie se borra al cerrar el navegador,
           y eso bastaba mientras la sesion caducaba de todas formas. Sin
           caducidad no basta: la doctora cierra Chrome al terminar el
           dia, lo abre al siguiente y tendria que volver a entrar, que
           es exactamente lo que se pidio quitar.

           Un anio y no "para siempre": las cookies no tienen un valor
           infinito, y una fecha absurdamente lejana la rechazan algunos
           navegadores. Un anio es mas de lo que vive una instalacion sin
           que alguien cambie su contrasenia.
        */
        $vidaCookie = $limite > 0 ? 0 : 31536000;   // 365 dias

        session_name('CONSULTORIO_SID');
        session_set_cookie_params([
            'lifetime' => $vidaCookie,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => self::esHttps(),
        ]);
        session_start();

        /*
           Caducidad por inactividad, solo si se pidio.

           Con MINUTOS_SESION = 0 no se mira el reloj. La comprobacion se
           salta entera en vez de poner un limite enorme: un limite de
           cien anios funcionaria igual y dejaria a quien lea esto
           calculando si cien anios son suficientes.
        */
        if ($limite > 0
            && isset($_SESSION['ultima_actividad'])
            && (time() - (int) $_SESSION['ultima_actividad']) > $limite) {
            self::destruir();
            session_start();
            $_SESSION['aviso_sesion'] = 'La sesion expiro por inactividad.';
        }

        $_SESSION['ultima_actividad'] = time();

        /*
           La cookie se reenvia en cada vuelta.

           Sin esto, la cookie de un anio se pone UNA vez —al entrar— y
           desde ahi va contando: la doctora que entro hace once meses se
           quedaria sin sesion un dia cualquiera, sin motivo aparente.
           Reenviandola, el anio se cuenta siempre desde la ultima vez
           que se uso el sistema.

           Solo cuando la sesion no caduca: con caducidad, la cookie es
           de las que mueren al cerrar el navegador y reenviarla no
           aniade nada.
        */
        if ($limite <= 0 && !headers_sent()) {
            $p = session_get_cookie_params();
            setcookie(session_name(), session_id(), [
                'expires'  => time() + $vidaCookie,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
        }
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
