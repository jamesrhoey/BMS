<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        error_reporting(E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handleException']);
    }

    public static function handleException(Throwable $e): void
    {
        self::logThrowable($e);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(500);
            Response::sendSecurityHeaders();
        }

        $errorView = BASE_PATH . '/app/Views/errors/500.php';
        if (is_file($errorView)) {
            require $errorView;
        } else {
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Server Error</title></head><body><h1>500 - Unexpected Application Error</h1><p>An unexpected error occurred. Please try again later or contact the system administrator.</p></body></html>';
        }
        exit;
    }

    public static function logThrowable(Throwable $e): void
    {
        $logDir = (string) Config::get('app.paths.logs', BASE_PATH . '/storage/logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $logFile = rtrim($logDir, '/\\') . '/app.log';
        $entry = sprintf(
            "[%s] %s: %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}

