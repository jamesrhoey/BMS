<?php

declare(strict_types=1);

/**
 * Marketing Department Module Configuration
 *
 * Official Project Categories:
 * 1. MRC: Requires 6 documents (SON, SLD, Marketing BOM, Marketing CA, Proposal to Client, Proposal from Supplier).
 *    PO is NOT required.
 * 2. Special Project: Requires 7 documents (the 6 MRC documents + Marketing Purchase Order [PO]).
 *    PO is strictly required.
 *
 * Note:
 * - Marketing BOM is stored separately from Final BOM uploaded later by OPS.
 * - Marketing SLD is stored separately from Final SLD uploaded later by OPS.
 * - Marketing PO is client Purchase Order, stored separately from supplier Purchase Order issued by Admin Finance.
 */

return [
    'default_workflow' => 'mrc',

    'categories' => [
        'mrc' => [
            'key' => 'mrc',
            'code' => 'MRC',
            'label' => 'MRC',
            'name' => 'MRC Project',
            'description' => 'Standard MRC Project (Requires 6 Marketing Documents; PO Not Required)',
            'required_count' => 6,
        ],
        'special_project' => [
            'key' => 'special_project',
            'code' => 'Special Project',
            'label' => 'Special Project',
            'name' => 'Special Project',
            'description' => 'Special Project (Requires 7 Marketing Documents including Marketing PO)',
            'required_count' => 7,
        ],
    ],

    'workflows' => [
        'mrc' => [
            'name' => 'MRC Project',
            'category_code' => 'MRC',
            'category_label' => 'MRC',
            'documents' => [
                'SON' => [
                    'type' => 'SON',
                    'code' => 'SON',
                    'label' => 'SON',
                    'subtitle' => 'Service On Notice / Service Order',
                    'required' => true,
                    'order' => 1,
                ],
                'SLD' => [
                    'type' => 'SLD',
                    'code' => 'SLD',
                    'label' => 'SLD',
                    'subtitle' => 'Single Line Diagram',
                    'required' => true,
                    'order' => 2,
                ],
                'BOM' => [
                    'type' => 'BOM',
                    'code' => 'BOM',
                    'label' => 'BOM',
                    'subtitle' => 'Marketing Bill of Materials',
                    'required' => true,
                    'order' => 3,
                ],
                'CA' => [
                    'type' => 'CA',
                    'code' => 'CA',
                    'label' => 'CA',
                    'subtitle' => 'Marketing Cost Analysis',
                    'required' => true,
                    'order' => 4,
                ],
                'Proposal to Client' => [
                    'type' => 'Proposal to Client',
                    'code' => 'PROP',
                    'label' => 'Proposal to Client',
                    'subtitle' => 'Commercial Proposal Issued to Client',
                    'required' => true,
                    'order' => 5,
                ],
                'Proposal from Supplier' => [
                    'type' => 'Proposal from Supplier',
                    'code' => 'PROP-SUPPLIER',
                    'label' => 'Proposal from Supplier',
                    'subtitle' => 'Vendor / Supplier Quotation & Proposal',
                    'required' => true,
                    'order' => 6,
                ],
            ],
        ],

        'special_project' => [
            'name' => 'Special Project',
            'category_code' => 'Special Project',
            'category_label' => 'Special Project',
            'documents' => [
                'SON' => [
                    'type' => 'SON',
                    'code' => 'SON',
                    'label' => 'SON',
                    'subtitle' => 'Service On Notice / Service Order',
                    'required' => true,
                    'order' => 1,
                ],
                'SLD' => [
                    'type' => 'SLD',
                    'code' => 'SLD',
                    'label' => 'SLD',
                    'subtitle' => 'Single Line Diagram',
                    'required' => true,
                    'order' => 2,
                ],
                'BOM' => [
                    'type' => 'BOM',
                    'code' => 'BOM',
                    'label' => 'BOM',
                    'subtitle' => 'Marketing Bill of Materials',
                    'required' => true,
                    'order' => 3,
                ],
                'CA' => [
                    'type' => 'CA',
                    'code' => 'CA',
                    'label' => 'CA',
                    'subtitle' => 'Marketing Cost Analysis',
                    'required' => true,
                    'order' => 4,
                ],
                'Proposal to Client' => [
                    'type' => 'Proposal to Client',
                    'code' => 'PROP',
                    'label' => 'Proposal to Client',
                    'subtitle' => 'Commercial Proposal Issued to Client',
                    'required' => true,
                    'order' => 5,
                ],
                'Proposal from Supplier' => [
                    'type' => 'Proposal from Supplier',
                    'code' => 'PROP-SUPPLIER',
                    'label' => 'Proposal from Supplier',
                    'subtitle' => 'Vendor / Supplier Quotation & Proposal',
                    'required' => true,
                    'order' => 6,
                ],
                'Marketing PO' => [
                    'type' => 'Marketing PO',
                    'code' => 'PO',
                    'label' => 'Marketing PO',
                    'subtitle' => 'Purchase Order',
                    'required' => true,
                    'order' => 7,
                ],
            ],
        ],

        // Backward compatibility for pre-existing foundation records
        'standard' => [
            'name' => 'Standard MRC Workflow',
            'category_code' => 'MRC',
            'category_label' => 'MRC',
            'documents' => [
                'SON' => [
                    'type' => 'SON',
                    'code' => 'SON',
                    'label' => 'SON',
                    'subtitle' => 'Service On Notice / Service Order',
                    'required' => true,
                    'order' => 1,
                ],
                'SLD' => [
                    'type' => 'SLD',
                    'code' => 'SLD',
                    'label' => 'SLD',
                    'subtitle' => 'Single Line Diagram',
                    'required' => true,
                    'order' => 2,
                ],
                'BOM' => [
                    'type' => 'BOM',
                    'code' => 'BOM',
                    'label' => 'BOM',
                    'subtitle' => 'Marketing Bill of Materials',
                    'required' => true,
                    'order' => 3,
                ],
                'CA' => [
                    'type' => 'CA',
                    'code' => 'CA',
                    'label' => 'CA',
                    'subtitle' => 'Marketing Cost Analysis',
                    'required' => true,
                    'order' => 4,
                ],
                'Proposal to Client' => [
                    'type' => 'Proposal to Client',
                    'code' => 'PROP',
                    'label' => 'Proposal to Client',
                    'subtitle' => 'Commercial Proposal Issued to Client',
                    'required' => true,
                    'order' => 5,
                ],
                'Proposal from Supplier' => [
                    'type' => 'Proposal from Supplier',
                    'code' => 'PROP-SUPPLIER',
                    'label' => 'Proposal from Supplier',
                    'subtitle' => 'Vendor / Supplier Quotation & Proposal',
                    'required' => true,
                    'order' => 6,
                ],
            ],
        ],
    ],

    'pagination' => [
        'dashboard_per_page' => 10,
        'projects_per_page' => 12,
    ],
];
