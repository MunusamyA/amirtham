<?php
declare(strict_types=1);

final class Database
{
    /** @var PDO|null */
    private static $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = (string) env_value('DB_HOST', '127.0.0.1');
        $port = (string) env_value('DB_PORT', '3306');
        $name = (string) env_value('DB_NAME', '');
        $user = (string) env_value('DB_USERNAME', '');
        $pass = (string) env_value('DB_PASSWORD', '');
        $charset = (string) env_value('DB_CHARSET', 'utf8mb4');

        if ($name === '' || $user === '') {
            throw new RuntimeException('DB_NAME or DB_USERNAME is missing in .env.');
        }

        $dsn = 'mysql:host=' . $host . ';port=' . $port .
            ';dbname=' . $name . ';charset=' . $charset;

        try {
            self::$connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_PERSISTENT => false,
            ]);
        } catch (PDOException $exception) {
            error_log('Database connection error: ' . $exception->getMessage());
            throw new RuntimeException('Database connection failed.');
        }

        return self::$connection;
    }

    private function __construct()
    {
    }
}

function db(): PDO
{
    return Database::connection();
}
