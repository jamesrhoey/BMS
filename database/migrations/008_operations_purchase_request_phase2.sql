-- =============================================================================
-- Migration 008: Operations Purchase Request (PR) Phase 2 Module
-- Workflows: Approval, Rejection, Revision, Model/Part Number, Multi-Item & Document Links
-- =============================================================================

USE `business_management_system`;

-- 1. Add Phase 2 columns to purchase_requests if not already present
SET @dbname = DATABASE();

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_requests'
      AND COLUMN_NAME = 'sld_document_id'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_requests` ADD COLUMN `sld_document_id` INT UNSIGNED NULL AFTER `attachment_document_id`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_requests'
      AND COLUMN_NAME = 'marketing_bom_document_id'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_requests` ADD COLUMN `marketing_bom_document_id` INT UNSIGNED NULL AFTER `sld_document_id`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_requests'
      AND COLUMN_NAME = 'actual_bom_id'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_requests` ADD COLUMN `actual_bom_id` INT UNSIGNED NULL AFTER `marketing_bom_document_id`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_requests'
      AND COLUMN_NAME = 'purpose'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_requests` ADD COLUMN `purpose` TEXT NULL AFTER `pr_date`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_requests'
      AND COLUMN_NAME = 'required_delivery_date'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_requests` ADD COLUMN `required_delivery_date` DATE NULL AFTER `purpose`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_requests'
      AND COLUMN_NAME = 'approved_by'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_requests` ADD COLUMN `approved_by` INT UNSIGNED NULL AFTER `return_reason`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_requests'
      AND COLUMN_NAME = 'approved_at'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_requests` ADD COLUMN `approved_at` DATETIME NULL AFTER `approved_by`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_requests'
      AND COLUMN_NAME = 'approval_notes'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_requests` ADD COLUMN `approval_notes` TEXT NULL AFTER `approved_at`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_requests'
      AND COLUMN_NAME = 'rejected_by'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_requests` ADD COLUMN `rejected_by` INT UNSIGNED NULL AFTER `approval_notes`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_requests'
      AND COLUMN_NAME = 'rejected_at'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_requests` ADD COLUMN `rejected_at` DATETIME NULL AFTER `rejected_by`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_requests'
      AND COLUMN_NAME = 'rejection_reason'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_requests` ADD COLUMN `rejection_reason` TEXT NULL AFTER `rejected_at`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_requests'
      AND COLUMN_NAME = 'ordered_quantity_total'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_requests` ADD COLUMN `ordered_quantity_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `rejection_reason`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_requests'
      AND COLUMN_NAME = 'ordered_status'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_requests` ADD COLUMN `ordered_status` VARCHAR(40) NOT NULL DEFAULT \'unconverted\' AFTER `ordered_quantity_total`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. Add Phase 2 columns to purchase_request_items
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_request_items'
      AND COLUMN_NAME = 'model_part_number'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_request_items` ADD COLUMN `model_part_number` VARCHAR(150) NULL AFTER `item_description`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_request_items'
      AND COLUMN_NAME = 'purpose'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_request_items` ADD COLUMN `purpose` VARCHAR(255) NULL AFTER `unit_of_measure`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_request_items'
      AND COLUMN_NAME = 'approved_quantity'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_request_items` ADD COLUMN `approved_quantity` DECIMAL(12,2) NULL AFTER `purpose`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = 'purchase_request_items'
      AND COLUMN_NAME = 'ordered_quantity'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `purchase_request_items` ADD COLUMN `ordered_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `approved_quantity`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3. Create purchase_request_histories table (Tracks PR revision, review, approval & rejection lifecycle)
CREATE TABLE IF NOT EXISTS `purchase_request_histories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_request_id` INT UNSIGNED NOT NULL,
    `from_status` VARCHAR(60) NULL,
    `to_status` VARCHAR(60) NOT NULL,
    `action` VARCHAR(60) NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `remarks` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_prh_pr_created` (`purchase_request_id`, `created_at`),
    CONSTRAINT `fk_prh_pr`
        FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_prh_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Seed PR Approver and Lifecycle permissions
INSERT INTO `permissions` (`permission_key`, `permission_name`, `module_group`, `description`)
VALUES
    ('ops.pr.view_all', 'View All Purchase Requests', 'Operations', 'View Purchase Requests across all projects and departments.'),
    ('opfin.pr.approve', 'Approve Purchase Requests', 'Operation Finance', 'Formally review and approve Purchase Requests for PO eligibility.'),
    ('opfin.pr.reject', 'Reject Purchase Requests', 'Operation Finance', 'Reject Purchase Requests with documented reason.'),
    ('afin.pr.approve', 'Admin Finance PR Approval', 'Admin Finance', 'Financial authorization and approval of Purchase Requests.'),
    ('afin.pr.reject', 'Admin Finance PR Rejection', 'Admin Finance', 'Financial rejection of Purchase Requests.')
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

-- Grant department-scoped permissions to Department User role
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
INNER JOIN `permissions` p ON p.permission_key IN (
    'ops.pr.view_all',
    'opfin.pr.approve',
    'opfin.pr.reject',
    'afin.pr.approve',
    'afin.pr.reject'
)
WHERE r.role_slug = 'department_user';
