-- =============================================================================
-- Migration 007: Operations Purchase Request (PR) & OPS Finance Actual BOM Workflow
-- Workflow: MARKETING -> PROJECT MANAGEMENT -> ADMIN FINANCE -> OPERATIONS -> OPS FINANCE -> ADMIN FINANCE
-- =============================================================================

USE `business_management_system`;

-- 1. Create purchase_requests table (Operations PR linked to project and JOM)
CREATE TABLE IF NOT EXISTS `purchase_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pr_number` VARCHAR(60) NOT NULL,
    `project_id` INT UNSIGNED NOT NULL,
    `jom_number` VARCHAR(50) NOT NULL,
    `requesting_department_id` INT UNSIGNED NULL,
    `receiving_department_id` INT UNSIGNED NULL,
    `requested_by` INT UNSIGNED NOT NULL,
    `pr_date` DATE NOT NULL,
    `remarks` TEXT NULL,
    `attachment_document_id` INT UNSIGNED NULL,
    `status` VARCHAR(60) NOT NULL DEFAULT 'Draft',
    `submitted_by` INT UNSIGNED NULL,
    `submitted_at` DATETIME NULL,
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `returned_by` INT UNSIGNED NULL,
    `returned_at` DATETIME NULL,
    `return_reason` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_purchase_requests_pr_number` (`pr_number`),
    KEY `idx_purchase_requests_project` (`project_id`),
    KEY `idx_purchase_requests_jom` (`jom_number`),
    KEY `idx_purchase_requests_status` (`status`),
    KEY `idx_purchase_requests_submitted_at` (`submitted_at`),
    CONSTRAINT `fk_pr_project`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_pr_req_dept`
        FOREIGN KEY (`requesting_department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_rec_dept`
        FOREIGN KEY (`receiving_department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_requested_by`
        FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_pr_attachment_doc`
        FOREIGN KEY (`attachment_document_id`) REFERENCES `project_documents` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_submitted_by`
        FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_reviewed_by`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_returned_by`
        FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create purchase_request_items table (Multiple line items per PR)
CREATE TABLE IF NOT EXISTS `purchase_request_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_request_id` INT UNSIGNED NOT NULL,
    `line_no` INT UNSIGNED NOT NULL DEFAULT 1,
    `item_description` VARCHAR(255) NOT NULL,
    `item_specification` TEXT NOT NULL,
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    `unit_of_measure` VARCHAR(50) NOT NULL DEFAULT 'pcs',
    `remarks` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pr_items_pr_id` (`purchase_request_id`, `line_no`),
    CONSTRAINT `fk_pr_items_pr`
        FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create actual_boms table (Separate from Marketing BOM; linked to project, JOM, and PR)
CREATE TABLE IF NOT EXISTS `actual_boms` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actual_bom_number` VARCHAR(60) NOT NULL,
    `project_id` INT UNSIGNED NOT NULL,
    `jom_number` VARCHAR(50) NOT NULL,
    `purchase_request_id` INT UNSIGNED NOT NULL,
    `version_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `remarks` TEXT NULL,
    `document_id` INT UNSIGNED NULL,
    `supporting_document_id` INT UNSIGNED NULL,
    `status` VARCHAR(60) NOT NULL DEFAULT 'Draft',
    `uploaded_by` INT UNSIGNED NOT NULL,
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `submitted_by` INT UNSIGNED NULL,
    `submitted_at` DATETIME NULL,
    `received_by` INT UNSIGNED NULL,
    `received_at` DATETIME NULL,
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `returned_by` INT UNSIGNED NULL,
    `returned_at` DATETIME NULL,
    `return_reason` TEXT NULL,
    `accepted_by` INT UNSIGNED NULL,
    `accepted_at` DATETIME NULL,
    `acceptance_notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_actual_boms_number` (`actual_bom_number`),
    KEY `idx_actual_boms_project` (`project_id`),
    KEY `idx_actual_boms_pr` (`purchase_request_id`),
    KEY `idx_actual_boms_jom` (`jom_number`),
    KEY `idx_actual_boms_status` (`status`),
    CONSTRAINT `fk_actual_boms_project`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_actual_boms_pr`
        FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_actual_boms_doc`
        FOREIGN KEY (`document_id`) REFERENCES `project_documents` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_actual_boms_supp_doc`
        FOREIGN KEY (`supporting_document_id`) REFERENCES `project_documents` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_actual_boms_uploaded_by`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_actual_boms_submitted_by`
        FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_actual_boms_received_by`
        FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_actual_boms_reviewed_by`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_actual_boms_returned_by`
        FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_actual_boms_accepted_by`
        FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create actual_bom_items table (Multiple Actual BOM line items with actual specifications)
CREATE TABLE IF NOT EXISTS `actual_bom_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actual_bom_id` INT UNSIGNED NOT NULL,
    `pr_item_id` INT UNSIGNED NULL,
    `line_no` INT UNSIGNED NOT NULL DEFAULT 1,
    `item_description` VARCHAR(255) NOT NULL,
    `actual_specification` TEXT NOT NULL,
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    `unit_of_measure` VARCHAR(50) NOT NULL DEFAULT 'pcs',
    `remarks` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_actual_bom_items_bom_id` (`actual_bom_id`, `line_no`),
    CONSTRAINT `fk_actual_bom_items_bom`
        FOREIGN KEY (`actual_bom_id`) REFERENCES `actual_boms` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_actual_bom_items_pr_item`
        FOREIGN KEY (`pr_item_id`) REFERENCES `purchase_request_items` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Seed Operations, OPS Finance & Admin Finance Actual BOM permissions
INSERT INTO `permissions` (`permission_key`, `permission_name`, `module_group`, `description`)
VALUES
    ('ops.dashboard.view', 'View Operations Dashboard', 'Operations', 'Access dedicated Operations incoming project queue and PR metrics.'),
    ('ops.pr.manage', 'Prepare and Submit Purchase Requests', 'Operations', 'Create, edit draft, and submit Purchase Requests (PR) with line items to OPS Finance.'),
    ('opfin.dashboard.view', 'View OPS Finance Dashboard', 'Operation Finance', 'Access dedicated OPS Finance incoming PR queue and Actual BOM metrics.'),
    ('opfin.pr.review', 'Review Operations Purchase Requests', 'Operation Finance', 'Inspect submitted Purchase Requests and return for corrections if needed.'),
    ('opfin.actual_bom.manage', 'Upload and Submit Actual BOM', 'Operation Finance', 'Upload separate Actual BOM documents and specifications and submit to Admin Finance.'),
    ('afin.actual_bom.review', 'Receive and Review Actual BOM', 'Admin Finance', 'Mark Actual BOM submissions as Received, Under Review, Returned for Corrections, or Accepted.')
ON DUPLICATE KEY UPDATE
    `permission_name` = VALUES(`permission_name`),
    `module_group` = VALUES(`module_group`),
    `description` = VALUES(`description`);

-- Grant all permissions to Administrator role
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.role_slug = 'administrator';

-- Grant department-scoped permissions to Department User role (enforced per department in AuthorizationService)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
INNER JOIN `permissions` p ON p.permission_key IN (
    'ops.dashboard.view',
    'ops.pr.manage',
    'opfin.dashboard.view',
    'opfin.pr.review',
    'opfin.actual_bom.manage',
    'afin.actual_bom.review'
)
WHERE r.role_slug = 'department_user';
