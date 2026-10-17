<?php
/**
 * CineNova — Database connection
 * ---------------------------------------------------------------
 * A single shared PDO connection to the posterg MySQL database,
 * configured entirely from backend/.env (never hard-coded
 * credentials in source). Every Model in backend/models/ goes
 * through Database::pdo() rather than opening its own connection.
 */
declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function pdo(): PDO
    {
        if (self::$instance === null) {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $name = env('DB_DATABASE', 'posterg');
            // An empty DB_USERNAME in the local XAMPP .env should still use
            // the standard XAMPP MySQL account instead of overriding the
            // default with an empty username. Explicit non-empty credentials
            // remain unchanged.
            $user = env('DB_USERNAME', 'root');
            $user = $user === '' ? 'root' : $user;
            $pass = env('DB_PASSWORD', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements — the strongest SQL-injection defense
            ]);
        }
        return self::$instance;
    }
}
