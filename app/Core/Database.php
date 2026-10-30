<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) Config::get('database.host', '127.0.0.1');
        $port = (int) Config::get('database.port', 3306);
        $database = (string) Config::get('database.database', '');
        $username = (string) Config::get('database.username', '');
        $password = (string) Config::get('database.password', '');
        $charset = (string) Config::get('database.charset', 'utf8mb4');

        if ($database === '' || $username === '') {
            throw new RuntimeException('Database configuration is incomplete. Please verify your .env file.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        try {
            self::$pdo = new PDO($dsn, $username, $password, self::pdoOptions());
        } catch (PDOException $e) {
            ErrorHandler::logThrowable($e);
            throw new RuntimeException('Unable to establish a database connection.');
        }

        return self::$pdo;
    }

    public static function connectServerOnly(): PDO
    {
        $host = (string) Config::get('database.host', '127.0.0.1');
        $port = (int) Config::get('database.port', 3306);
        $username = (string) Config::get('database.username', '');
        $password = (string) Config::get('database.password', '');
        $charset = (string) Config::get('database.charset', 'utf8mb4');

        if ($username === '') {
            throw new RuntimeException('Database username is not configured in .env.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);

        return new PDO($dsn, $username, $password, self::pdoOptions());
    }

    /**
     * Execute a callback atomically within a database transaction.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $startedHere = false;

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedHere = true;
        }

        try {
            $result = $callback($pdo);
            if ($startedHere && $pdo->inTransaction()) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($startedHere && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    /**
     * @return array<int, mixed>
     */
    private static function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
    }
}

