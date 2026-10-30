<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'driver' => (string) Env::get('DB_CONNECTION', 'mysql'),
    'host' => (string) Env::get('DB_HOST', '127.0.0.1'),
    'port' => Env::int('DB_PORT', 3306),
    'database' => (string) Env::get('DB_DATABASE', 'business_management_system'),
    'username' => (string) Env::get('DB_USERNAME', ''),
    'password' => (string) Env::get('DB_PASSWORD', ''),
    'charset' => (string) Env::get('DB_CHARSET', 'utf8mb4'),
    'collation' => 'utf8mb4_unicode_ci',
];

