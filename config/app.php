<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => (string) Env::get('APP_NAME', 'SYB Business Management System'),
    'env' => (string) Env::get('APP_ENV', 'local'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost/BMS'), '/'),
    'timezone' => (string) Env::get('APP_TIMEZONE', 'Asia/Manila'),

    'session' => [
        'name' => (string) Env::get('SESSION_NAME', 'SYB_BMS_SESSION'),
        'lifetime' => max(300, Env::int('SESSION_LIFETIME', 3600)),
        'absolute_lifetime' => max(900, Env::int('SESSION_ABSOLUTE_LIFETIME', 28800)),
        'secure_cookie' => (string) Env::get('SESSION_SECURE_COOKIE', 'auto'),
    ],

    'paths' => [
        'storage' => BASE_PATH . '/storage',
        'documents' => BASE_PATH . '/storage/documents',
        'logs' => BASE_PATH . '/storage/logs',
        'views' => BASE_PATH . '/app/Views',
    ],

    'uploads' => [
        'max_size_bytes' => max(1, Env::int('MAX_UPLOAD_SIZE_MB', 10)) * 1024 * 1024,
        // Extension => Permitted server-detected MIME types
        'allowed_types' => [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'doc' => ['application/msword', 'application/CDFV2', 'application/octet-stream'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
            ],
            'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/octet-stream'],
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
            ],
        ],
        'document_categories' => [
            'SON' => 'SON — Service On Notice',
            'SLD' => 'SLD — Single Line Diagram',
            'BOM' => 'BOM — Marketing Bill of Materials',
            'CA' => 'CA — Marketing Cost Analysis',
            'Proposal to Client' => 'Proposal to Client',
            'Proposal from Supplier' => 'Proposal from Supplier',
            'General Attachment' => 'General Attachment (Supporting File)',
        ],
    ],
];

