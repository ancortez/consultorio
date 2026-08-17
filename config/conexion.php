<?php
/**
 * Conexion unica a la base de datos mediante PDO.
 *
 * MEJORA respecto al planteamiento original: se usa PDO con consultas
 * preparadas en lugar de concatenar cadenas SQL. Esto elimina de raiz
 * la inyeccion SQL, que es el riesgo numero uno en un sistema PHP que
 * maneja datos clinicos.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class Conexion
{
    private static ?PDO $pdo = null;

    public static function obtener(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME
        );

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // false = consultas preparadas reales del lado del servidor
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::MYSQL_ATTR_INIT_COMMAND =>
                "SET NAMES utf8mb4, "
                . "sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION', "
                . "time_zone='-06:00'",
        ];

        try {
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        } catch (PDOException $e) {
            error_log('[BD] ' . $e->getMessage());
            if (APP_ENV === 'produccion') {
                http_response_code(500);
                exit('No fue posible conectar con la base de datos.');
            }
            throw $e;
        }

        return self::$pdo;
    }

    /** Atajo: ejecuta una consulta preparada y devuelve el statement. */
    public static function consulta(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::obtener()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Devuelve todos los renglones. */
    public static function todos(string $sql, array $params = []): array
    {
        return self::consulta($sql, $params)->fetchAll();
    }

    /** Devuelve un solo renglon o null. */
    public static function uno(string $sql, array $params = []): ?array
    {
        $fila = self::consulta($sql, $params)->fetch();
        return $fila === false ? null : $fila;
    }

    /** Devuelve el primer valor de la primera columna. */
    public static function valor(string $sql, array $params = [])
    {
        $v = self::consulta($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function ultimoId(): int
    {
        return (int) self::obtener()->lastInsertId();
    }
}

/** Funcion corta usada en todo el proyecto. */
function bd(): PDO
{
    return Conexion::obtener();
}
