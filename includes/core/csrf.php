<?php
declare(strict_types=1);

/**
 * Proteccion contra CSRF.
 * Todo formulario y toda peticion AJAX que modifique datos debe enviar
 * el token. El front lo toma del <meta name="csrf-token"> del index.
 */
final class Csrf
{
    private const CLAVE = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::CLAVE])) {
            $_SESSION[self::CLAVE] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CLAVE];
    }

    /** Campo oculto listo para pegar dentro de un <form>. */
    public static function campo(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::token() . '">';
    }

    public static function valido(?string $token): bool
    {
        return !empty($_SESSION[self::CLAVE])
            && is_string($token)
            && hash_equals($_SESSION[self::CLAVE], $token);
    }

    /** Corta la ejecucion si el token no es valido. */
    public static function verificar(): void
    {
        $token = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? null;

        if (!self::valido($token)) {
            Respuesta::error('Token de seguridad invalido. Recarga la pagina.', 419);
        }
    }
}
