<?php

declare(strict_types=1);

/**
 * Marketing Department Module Configuration
 *
 * Primary Reference: FLOWCHART REPORT - SYB.PPTX (Slide 5: Standard Project Workflow)
 *
 * Business Rule & Assumption Notes (for stakeholder confirmation):
 * 1. Standard projects require the six Marketing documents shown in Slide 5 before
 *    submission to Project Management for document verification and JOM issuance:
 *    SON, SLD, BOM, CA, Proposal to Client, and Proposal from Supplier.
 * 2. Special workflows in the presentation (e.g., Slides 13 & 21 "Special Project"
 *    which include Purchase Order [PO]) are NOT applied to standard projects automatically.
 * 3. Acronym subtitles below reflect standard engineering/commercial usage seen across
 *    Slides 5-10 of the SYB flowchart (SON, SLD, Marketing BOM, Marketing CA). Adjust
 *    `subtitle` or `required` flags here if company policy specifies exceptions.
 */

return [
    'default_workflow' => 'standard',

    'workflows' => [
        'standard' => [
            'name' => 'Standard Project Workflow',
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
                    'code' => 'PROP-CLIENT',
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

