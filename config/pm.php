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
    'pr' => [
        'prefix' => (string) Env::get('PR_PREFIX', 'PR'),
        'padding' => max(3, Env::int('PR_PADDING', 4)),
        /**
         * Configurable format pattern for Operations Purchase Request (PR) numbering.
         * Default: PR-{YEAR}{MONTH}-{SEQ} (e.g., PR-202611-0001)
         */
        'format' => (string) Env::get('PR_FORMAT', '{PREFIX}-{YEAR}{MONTH}-{SEQ}'),
        'confirmation_notice' => 'PR numbering format is configurable via config/pm.php or .env (PR_FORMAT) and requires business confirmation before production cutover.',
    ],
    'actual_bom' => [
        'prefix' => (string) Env::get('ACTUAL_BOM_PREFIX', 'ABOM'),
        'padding' => max(3, Env::int('ACTUAL_BOM_PADDING', 4)),
        /**
         * Configurable format pattern for OPS Finance Actual BOM reference numbering.
         * Default: ABOM-{YEAR}{MONTH}-{SEQ} (e.g., ABOM-202611-0001)
         */
        'format' => (string) Env::get('ACTUAL_BOM_FORMAT', '{PREFIX}-{YEAR}{MONTH}-{SEQ}'),
    ],
    'pagination' => [
        'dashboard_per_page' => 10,
        'projects_per_page' => 12,
    ],

    /**
     * Official category-specific document handoff package forwarded to Operations.
     * MRC: JOM, SON, Marketing BOM, Marketing SLD (Exactly 4 docs)
     * Special Project: JOM, Marketing PO, SON, Marketing BOM, Marketing SLD (Exactly 5 docs)
     * CA, PROP, PROP-SUPPLIER are strictly excluded from Operations forwarding.
     */
    'ops_handoff' => [
        'mrc' => [
            'required_documents' => ['JOM', 'SON', 'BOM', 'SLD'],
            'labels' => [
                'JOM' => 'JOM — Job Order Memorandum',
                'SON' => 'SON — Service On Notice',
                'BOM' => 'BOM — Marketing Bill of Materials',
                'SLD' => 'SLD — Marketing Single Line Diagram',
            ],
        ],
        'special_project' => [
            'required_documents' => ['JOM', 'PO', 'SON', 'BOM', 'SLD'],
            'labels' => [
                'JOM' => 'JOM — Job Order Memorandum',
                'PO' => 'PO — Marketing-Uploaded Purchase Order',
                'SON' => 'SON — Service On Notice',
                'BOM' => 'BOM — Marketing Bill of Materials',
                'SLD' => 'SLD — Marketing Single Line Diagram',
            ],
        ],
    ],
];

