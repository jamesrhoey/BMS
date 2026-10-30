<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'jom' => [
        'prefix' => (string) Env::get('JOM_PREFIX', 'JOM'),
        'padding' => max(3, Env::int('JOM_PADDING', 4)),
        /**
         * Standard format pattern for JOM issuance.
         * Default: JOM-{YEAR}{MONTH}-{SEQ} (e.g., JOM-202610-0001)
         * Supported tokens: {PREFIX}, {YEAR}, {YEAR_SHORT}, {MONTH}, {SEQ}
         */
        'format' => (string) Env::get('JOM_FORMAT', '{PREFIX}-{YEAR}{MONTH}-{SEQ}'),
        'confirmation_notice' => 'JOM format defaults to standard company monthly sequential series (JOM-YYYYMM-XXXX). Format is configurable via config/pm.php or .env.',
    ],
    'pagination' => [
        'dashboard_per_page' => 10,
        'projects_per_page' => 12,
    ],
];

