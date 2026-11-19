-- =============================================================================
-- Migration 010: Stock Out Issuances, Multi-Item Dispatch Detail,
-- Serialized Unit Dispatch Mapping, Inventory Unit Extensions & Permissions
-- 
-- BMS Business Management System (Pagecom Inc. / SYB)
-- Target Database Engine: MariaDB 10.4.32 (InnoDB, utf8mb4_unicode_ci)
-- Module: Receiving, Stock Out & Ending Balance Perpetual Inventory
-- 
-- SUMMARY OF ARCHITECTURAL SAFEGUARDS:
-- 1. `stock_out_issuances`: Default status 'Draft'; out_dr_number and out_date
--    are NULLABLE during Draft (enforced at physical dispatch stage in PHP);
--    segregated audit columns (prepared_by, authorized_by, dispatched_by, cancelled_by).
-- 2. `stock_out_items`: Unique line numbers within issuance (uq_soi_line),
--    positive quantity check constraint (chk_soi_qty > 0.00), ON DELETE RESTRICT.
-- 3. `stock_out_dispatch_units`: Dedicated physical unit dispatch history table.
--    Permanently preserves the immutable record of every serial number dispatched,
--    enabling returned units to be re-issued without overwriting or losing past dispatches.
-- 4. `inventory_units`: Extended with current Stock Out traceability columns;
--    exact data types matching referenced tables (stock_out_transaction_id is BIGINT UNSIGNED);
--    ON DELETE RESTRICT on all foreign keys to protect historical inventory records.
-- 5. `inventory_transactions`: Composite index (idx_it_type_date) for ledger reporting.
-- 6. `permissions` & `role_permissions`: Administrator receives all permissions;
--    `department_user` receives ONLY baseline safe permissions (view, create draft, view reports);
--    High-privilege actions (authorize, dispatch, adjustments) are NOT broadly granted in SQL,
--    and true departmental segregation is enforced server-side in PHP AuthorizationService.
-- 
-- Non-destructive incremental migration. Safe to run repeatedly on MariaDB 10.4.32.
-- =============================================================================

USE `business_management_system`;

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. Table: stock_out_issuances (Header for Outgoing Equipment Dispatches)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_out_issuances` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dispatch_number` VARCHAR(60) NOT NULL,
    `out_dr_number` VARCHAR(100) NULL,
    `invoice_number` VARCHAR(100) NULL,
    `out_date` DATE NULL,
    `project_id` INT UNSIGNED NULL,
    `jom_number` VARCHAR(50) NULL,
    `recipient_company` VARCHAR(200) NOT NULL,
    `recipient_address` TEXT NOT NULL,
    `recipient_contact` VARCHAR(150) NULL,
    `purpose_of_issuance` VARCHAR(255) NOT NULL DEFAULT 'Project Jobsite Installation',
    `carrier_name` VARCHAR(150) NULL,
    `vehicle_plate_no` VARCHAR(50) NULL,
    `supporting_document_id` INT UNSIGNED NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Draft',
    `prepared_by` INT UNSIGNED NOT NULL,
    `prepared_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `authorized_by` INT UNSIGNED NULL,
    `authorized_at` DATETIME NULL,
    `dispatched_by` INT UNSIGNED NULL,
    `dispatched_at` DATETIME NULL,
    `cancelled_by` INT UNSIGNED NULL,
    `cancelled_at` DATETIME NULL,
    `cancellation_reason` TEXT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_so_dispatch_no` (`dispatch_number`),
    KEY `idx_so_dr_no` (`out_dr_number`),
    KEY `idx_so_invoice_no` (`invoice_number`),
    KEY `idx_so_project` (`project_id`),
    KEY `idx_so_out_date` (`out_date`),
    KEY `idx_so_status` (`status`),
    KEY `idx_so_prepared_by` (`prepared_by`),
    KEY `idx_so_authorized_by` (`authorized_by`),
    KEY `idx_so_dispatched_by` (`dispatched_by`),
    CONSTRAINT `fk_so_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_so_prepared_by` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_so_authorized_by` FOREIGN KEY (`authorized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_so_dispatched_by` FOREIGN KEY (`dispatched_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_so_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_so_doc` FOREIGN KEY (`supporting_document_id`) REFERENCES `project_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Table: stock_out_items (Detail Lines for Multi-Item / Bulk / Serialized Issuances)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_out_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `stock_out_issuance_id` INT UNSIGNED NOT NULL,
    `inventory_item_id` INT UNSIGNED NOT NULL,
    `line_no` INT UNSIGNED NOT NULL DEFAULT 1,
    `item_description` VARCHAR(255) NOT NULL,
    `model_part_number` VARCHAR(100) NULL,
    `quantity_issued` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    `unit_of_measure` VARCHAR(50) NOT NULL DEFAULT 'pcs',
    `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `remarks` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_soi_line` (`stock_out_issuance_id`, `line_no`),
    KEY `idx_soi_item` (`inventory_item_id`),
    CONSTRAINT `fk_soi_issuance` FOREIGN KEY (`stock_out_issuance_id`) REFERENCES `stock_out_issuances` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_soi_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `chk_soi_quantity` CHECK (`quantity_issued` > 0.00),
    CONSTRAINT `chk_soi_unit_price` CHECK (`unit_price` >= 0.00),
    CONSTRAINT `chk_soi_total_amount` CHECK (`total_amount` >= 0.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Table: stock_out_dispatch_units (Immutable Serialized Unit Dispatch & Return Log)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_out_dispatch_units` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `stock_out_issuance_id` INT UNSIGNED NOT NULL,
    `stock_out_item_id` INT UNSIGNED NOT NULL,
    `inventory_unit_id` INT UNSIGNED NOT NULL,
    `serial_number` VARCHAR(150) NOT NULL,
    `dispatched_at` DATETIME NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Dispatched',
    `returned_at` DATETIME NULL,
    `return_reference_no` VARCHAR(100) NULL,
    `return_transaction_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sodu_issuance` (`stock_out_issuance_id`),
    KEY `idx_sodu_item` (`stock_out_item_id`),
    KEY `idx_sodu_unit` (`inventory_unit_id`),
    KEY `idx_sodu_serial` (`serial_number`),
    KEY `idx_sodu_status` (`status`),
    CONSTRAINT `fk_sodu_issuance` FOREIGN KEY (`stock_out_issuance_id`) REFERENCES `stock_out_issuances` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sodu_item` FOREIGN KEY (`stock_out_item_id`) REFERENCES `stock_out_items` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sodu_unit` FOREIGN KEY (`inventory_unit_id`) REFERENCES `inventory_units` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sodu_return_txn` FOREIGN KEY (`return_transaction_id`) REFERENCES `inventory_transactions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Extend `inventory_units` with Current Stock Out Traceability Columns
-- -----------------------------------------------------------------------------
ALTER TABLE `inventory_units`
    ADD COLUMN IF NOT EXISTS `stock_out_issuance_id` INT UNSIGNED NULL AFTER `stock_in_transaction_id`,
    ADD COLUMN IF NOT EXISTS `stock_out_item_id` INT UNSIGNED NULL AFTER `stock_out_issuance_id`,
    ADD COLUMN IF NOT EXISTS `stock_out_transaction_id` BIGINT UNSIGNED NULL AFTER `stock_out_item_id`,
    ADD COLUMN IF NOT EXISTS `out_date` DATE NULL AFTER `inventory_date`,
    ADD COLUMN IF NOT EXISTS `out_qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `in_qty`,
    ADD COLUMN IF NOT EXISTS `warranty_expiration_date` DATE NULL AFTER `warranty_duration`,
    ADD COLUMN IF NOT EXISTS `issued_to_company` VARCHAR(200) NULL AFTER `current_location`,
    ADD COLUMN IF NOT EXISTS `issued_to_address` TEXT NULL AFTER `issued_to_company`,
    ADD COLUMN IF NOT EXISTS `out_dr_number` VARCHAR(100) NULL AFTER `issued_to_address`,
    ADD COLUMN IF NOT EXISTS `invoice_number` VARCHAR(100) NULL AFTER `out_dr_number`,
    ADD INDEX IF NOT EXISTS `idx_iu_so_issuance` (`stock_out_issuance_id`),
    ADD INDEX IF NOT EXISTS `idx_iu_so_item` (`stock_out_item_id`),
    ADD INDEX IF NOT EXISTS `idx_iu_so_txn` (`stock_out_transaction_id`),
    ADD INDEX IF NOT EXISTS `idx_iu_out_date` (`out_date`),
    ADD INDEX IF NOT EXISTS `idx_iu_out_dr_no` (`out_dr_number`),
    ADD INDEX IF NOT EXISTS `idx_iu_invoice_no` (`invoice_number`);

-- Safely add foreign key constraints to `inventory_units` using RESTRICT deletion rules
SET @fk_iu_so_issuance_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_units'
      AND CONSTRAINT_NAME = 'fk_iu_so_issuance'
);
SET @sql_iu_so_issuance_fk = IF(@fk_iu_so_issuance_exists = 0,
    'ALTER TABLE `inventory_units` ADD CONSTRAINT `fk_iu_so_issuance` FOREIGN KEY (`stock_out_issuance_id`) REFERENCES `stock_out_issuances` (`id`) ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt_iu_so_issuance FROM @sql_iu_so_issuance_fk;
EXECUTE stmt_iu_so_issuance;
DEALLOCATE PREPARE stmt_iu_so_issuance;

SET @fk_iu_so_item_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_units'
      AND CONSTRAINT_NAME = 'fk_iu_so_item'
);
SET @sql_iu_so_item_fk = IF(@fk_iu_so_item_exists = 0,
    'ALTER TABLE `inventory_units` ADD CONSTRAINT `fk_iu_so_item` FOREIGN KEY (`stock_out_item_id`) REFERENCES `stock_out_items` (`id`) ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt_iu_so_item FROM @sql_iu_so_item_fk;
EXECUTE stmt_iu_so_item;
DEALLOCATE PREPARE stmt_iu_so_item;

SET @fk_iu_so_txn_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_units'
      AND CONSTRAINT_NAME = 'fk_iu_so_txn'
);
SET @sql_iu_so_txn_fk = IF(@fk_iu_so_txn_exists = 0,
    'ALTER TABLE `inventory_units` ADD CONSTRAINT `fk_iu_so_txn` FOREIGN KEY (`stock_out_transaction_id`) REFERENCES `inventory_transactions` (`id`) ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt_iu_so_txn FROM @sql_iu_so_txn_fk;
EXECUTE stmt_iu_so_txn;
DEALLOCATE PREPARE stmt_iu_so_txn;

-- -----------------------------------------------------------------------------
-- 5. Optimize `inventory_transactions` for Ending Balance Ledger Queries
-- -----------------------------------------------------------------------------
ALTER TABLE `inventory_transactions`
    ADD INDEX IF NOT EXISTS `idx_it_type_date` (`transaction_type`, `transaction_date`);

-- -----------------------------------------------------------------------------
-- 6. Seed Permissions for Stock Out, Inventory Reports & Balance Adjustments
-- -----------------------------------------------------------------------------
INSERT INTO `permissions` (`permission_key`, `permission_name`, `module_group`, `description`) VALUES
    ('log.stock_out.view', 'View Stock Out Dispatches', 'Receiving & Inventory', 'View equipment stock out issuances, dispatch receipts, and deployed unit logs.'),
    ('log.stock_out.create', 'Create Stock Out Dispatch', 'Receiving & Inventory', 'Prepare draft stock out dispatches and record requested equipment lines.'),
    ('log.stock_out.authorize', 'Authorize Stock Out Dispatch', 'Receiving & Inventory', 'Formally authorize submitted equipment dispatches prior to physical release.'),
    ('log.stock_out.manage', 'Manage and Authorize Stock Out', 'Receiving & Inventory', 'Administrative control to manage, authorize, or cancel stock out dispatches.'),
    ('log.stock_out.dispatch', 'Execute Physical Dispatch & Post Ledger', 'Receiving & Inventory', 'Confirm physical release, assign serial numbers, and post stock out inventory movements.'),
    ('inv.reports.view', 'View Inventory Reports & Balances', 'Receiving & Inventory', 'Access ending balance calculations, perpetual stock ledger, and discrepancy reports.'),
    ('inv.adjustments.manage', 'Manage Inventory Adjustments', 'Receiving & Inventory', 'Perform authorized manual stock balance adjustments with documented audit reasons.')
ON DUPLICATE KEY UPDATE
    `permission_name` = VALUES(`permission_name`),
    `module_group` = VALUES(`module_group`),
    `description` = VALUES(`description`);

-- -----------------------------------------------------------------------------
-- 7. Assign Permissions to Roles (Separating Global Admin from Department User)
-- -----------------------------------------------------------------------------
-- Grant ALL permissions to Administrator role
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.`role_slug` = 'administrator'
  AND p.`permission_key` IN (
      'log.stock_out.view',
      'log.stock_out.create',
      'log.stock_out.authorize',
      'log.stock_out.manage',
      'log.stock_out.dispatch',
      'inv.reports.view',
      'inv.adjustments.manage'
  );

-- Grant ONLY baseline, non-authorizing permissions to generic Department User role in SQL.
-- Critical Note: In BMS, all non-admin users share `role_slug = 'department_user'`.
-- High-privilege permissions (authorize, dispatch, adjustments) MUST NOT be granted
-- broadly to `department_user` in SQL.
-- Department-specific restrictions (LOG vs OPS vs ADMFIN vs OPFIN vs MKT vs PM)
-- MUST be enforced server-side in PHP `AuthorizationService`.
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
INNER JOIN `permissions` p ON p.`permission_key` IN (
    'log.stock_out.view',
    'log.stock_out.create',
    'inv.reports.view'
)
WHERE r.`role_slug` = 'department_user';
