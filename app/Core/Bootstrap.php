<?php

declare(strict_types=1);

namespace App\Core;

final class Bootstrap
{
    public static function init(string $basePath): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', rtrim($basePath, '/\\'));
        }

        require_once BASE_PATH . '/app/Core/Autoloader.php';
        Autoloader::register(BASE_PATH);

        Env::load(BASE_PATH . '/.env');
        Config::load(BASE_PATH . '/config');

        $timezone = (string) Config::get('app.timezone', 'Asia/Manila');
        if ($timezone !== '') {
            date_default_timezone_set($timezone);
        }

        ErrorHandler::register();
        require_once BASE_PATH . '/app/Core/helpers.php';
    }
}

