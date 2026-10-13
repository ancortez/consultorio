<?php
/**
 * Reconocer "la base no esta al dia" cuando se ve.
 *
 * Por que existe esto
 * -------------------
 * Tres veces ya, un cambio de estructura llego a la computadora de
 * Angel sin su migracion, y lo que vio fue:
 *
 *     Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not
 *     found: 1054 Unknown column 'motivo_cancelacion' in 'field list'
 *     in /var/www/html/config/conexion.php:59 Stack trace: #0 ...
 *
 * Ese mensaje tiene dos problemas. El primero es que no dice que hacer:
 * nombra una columna, un archivo de conexion y un rastro de llamadas,
 * y ninguna de las tres cosas es la causa ni la solucion. El segundo es
 * que aparece en la pantalla del plan de tratamiento, asi que la
 * conclusion natural es "se rompio el modulo de planes" y no "falta
 * correr una migracion".
 *
 * Aqui no se arregla la migracion —eso lo hace migrar.sh— sino la
 * TRADUCCION: un error de columna o tabla ausente se convierte en una
 * frase que dice que pasa y que teclear. Es lo unico que hace falta
 * para que el problema deje de costar una ida y vuelta.
 *
 * Y se reconoce por el codigo SQLSTATE, no por el texto del mensaje:
 * el texto cambia entre versiones de MySQL y entre idiomas, el codigo
 * no.
 */
final class Esquema
{
    /** La base no tiene una columna que el codigo si usa. */
    public const COLUMNA_AUSENTE = '42S22';

    /** La base no tiene una tabla que el codigo si usa. */
    public const TABLA_AUSENTE = '42S02';

    /**
     * ¿Este error es "falta migrar"? Devuelve el aviso, o null si el
     * error es otra cosa.
     *
     * Devolver null importa tanto como devolver el texto: un error de
     * verdad —una division entre cero, un archivo que no esta— no debe
     * disfrazarse de migracion pendiente, porque entonces se corre
     * migrar.sh, no pasa nada, y el rastro se enfria.
     */
    public static function avisoDeFaltaMigrar(Throwable $e): ?string
    {
        if (!$e instanceof PDOException) {
            return null;
        }

        // PDO pone el SQLSTATE en el primer hueco de errorInfo y, con
        // ERRMODE_EXCEPTION, tambien en getCode().
        $estado = (string) ($e->errorInfo[0] ?? $e->getCode());

        if ($estado === self::COLUMNA_AUSENTE) {
            return self::texto('columna', self::nombreEnElMensaje($e->getMessage()));
        }
        if ($estado === self::TABLA_AUSENTE) {
            return self::texto('tabla', self::nombreEnElMensaje($e->getMessage()));
        }
        return null;
    }

    /**
     * El nombre entre comillas del mensaje de MySQL.
     *
     * "Unknown column 'motivo_cancelacion' in 'field list'" trae dos
     * textos entrecomillados y el que sirve es el primero. Si el
     * formato cambia se devuelve null y el aviso sale sin nombre, que
     * sigue siendo mejor que un rastro de pila.
     */
    private static function nombreEnElMensaje(string $mensaje): ?string
    {
        return preg_match("/'([^']+)'/", $mensaje, $m) ? $m[1] : null;
    }

    private static function texto(string $que, ?string $nombre): string
    {
        $cual = $nombre !== null ? " <b>$nombre</b>" : '';

        return "El c&oacute;digo de esta versi&oacute;n usa una $que$cual "
             . 'que la base todav&iacute;a no tiene. No es una falla del '
             . 'm&oacute;dulo: falta aplicar una migraci&oacute;n.';
    }

    /** Lo que hay que teclear, en una linea. */
    public static function comoArreglarlo(): string
    {
        return 'cd infra/produccion && bash migrar.sh';
    }
}
