<?php

declare(strict_types=1);

return [
    /**
     * Official 3 Required Documents that Operations (OPS) must upload
     * for both MRC and Special Projects before submission to OPS Finance.
     */
    'required_documents' => [
        'FINAL_SLD' => [
            'code' => 'FINAL_SLD',
            'type' => 'Final SLD — Single Line Diagram',
            'name' => 'Final Single Line Diagram',
            'short_name' => 'Final SLD',
            'desc' => 'Final Single Line Diagram prepared by Operations (distinct from Marketing SLD).',
            'aliases' => ['Final SLD', 'FINAL_SLD', 'Final SLD — Single Line Diagram', 'Final Single Line Diagram'],
        ],
        'FINAL_BOM' => [
            'code' => 'FINAL_BOM',
            'type' => 'Final BOM — Bill of Materials',
            'name' => 'Final Bill of Materials',
            'short_name' => 'Final BOM',
            'desc' => 'Final Bill of Materials prepared by Operations (distinct from Marketing BOM).',
            'aliases' => ['Final BOM', 'FINAL_BOM', 'Final BOM — Bill of Materials', 'Final Bill of Materials'],
        ],
        'REQUEST_OF_PR' => [
            'code' => 'REQUEST_OF_PR',
            'type' => 'Request of PR — Purchase Requisition Request',
            'name' => 'Request of PR',
            'short_name' => 'Request of PR',
            'desc' => 'Request of Purchase Requisition prepared by Operations for submission to OPS Finance.',
            'aliases' => ['Request of PR', 'REQUEST_OF_PR', 'Request of PR — Purchase Requisition Request', 'Request of Purchase Requisition'],
        ],
    ],

    'pagination' => [
        'dashboard_per_page' => 10,
        'projects_per_page' => 12,
    ],
];
