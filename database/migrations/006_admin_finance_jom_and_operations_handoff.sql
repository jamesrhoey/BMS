-- =============================================================================
-- Migration 006: Admin Finance JOM Issuance & Operations Handoff Module
-- Workflow: MARKETING -> PROJECT MANAGEMENT -> ADMIN FINANCE -> OPERATIONS -> OPS FINANCE -> ADMIN FINANCE
-- 1. Adds Admin Finance review and Operations handoff tracking columns to `projects`
--    (including explicit association of forwarded SLD and Marketing BOM document IDs).
-- 2. Seeds Admin Finance and Operations handoff permissions.
-- Non-destructive incremental migration preserving all existing records and JOM numbers.
-- =============================================================================

USE `business_management_system`;

SET NAMES utf8mb4;

-- 1. Add Admin Finance review and Operations handoff columns to `projects` table (if not exists)
ALTER TABLE `projects`
    ADD COLUMN IF NOT EXISTS `afin_reviewed_by` INT UNSIGNED NULL AFTER `forwarded_to_afin_notes`,
    ADD COLUMN IF NOT EXISTS `afin_reviewed_at` DATETIME NULL AFTER `afin_reviewed_by`,
    ADD COLUMN IF NOT EXISTS `forwarded_to_ops_by` INT UNSIGNED NULL AFTER `afin_reviewed_at`,
    ADD COLUMN IF NOT EXISTS `forwarded_to_ops_at` DATETIME NULL AFTER `forwarded_to_ops_by`,
    ADD COLUMN IF NOT EXISTS `forwarded_to_ops_notes` TEXT NULL AFTER `forwarded_to_ops_at`,
    ADD COLUMN IF NOT EXISTS `ops_sld_document_id` INT UNSIGNED NULL AFTER `forwarded_to_ops_notes`,
    ADD COLUMN IF NOT EXISTS `ops_bom_document_id` INT UNSIGNED NULL AFTER `ops_sld_document_id`,
    ADD INDEX IF NOT EXISTS `idx_projects_afin_reviewed_by` (`afin_reviewed_by`),
    ADD INDEX IF NOT EXISTS `idx_projects_forwarded_to_ops_by` (`forwarded_to_ops_by`),
    ADD INDEX IF NOT EXISTS `idx_projects_forwarded_to_ops_at` (`forwarded_to_ops_at`);

-- 2. Add foreign key constraints safely if not exists
SET @fk_afin_rev_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'business_management_system'
      AND TABLE_NAME = 'projects'
      AND CONSTRAINT_NAME = 'fk_projects_afin_reviewed_by'
);
SET @sql_afin_rev_fk = IF(@fk_afin_rev_exists = 0,
    'ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_afin_reviewed_by` FOREIGN KEY (`afin_reviewed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_afin_rev FROM @sql_afin_rev_fk;
EXECUTE stmt_afin_rev;
DEALLOCATE PREPARE stmt_afin_rev;

SET @fk_ops_fwd_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'business_management_system'
      AND TABLE_NAME = 'projects'
      AND CONSTRAINT_NAME = 'fk_projects_forwarded_to_ops_by'
);
SET @sql_ops_fwd_fk = IF(@fk_ops_fwd_exists = 0,
    'ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_forwarded_to_ops_by` FOREIGN KEY (`forwarded_to_ops_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_ops_fwd FROM @sql_ops_fwd_fk;
EXECUTE stmt_ops_fwd;
DEALLOCATE PREPARE stmt_ops_fwd;

SET @fk_ops_sld_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'business_management_system'
      AND TABLE_NAME = 'projects'
      AND CONSTRAINT_NAME = 'fk_projects_ops_sld_doc'
);
SET @sql_ops_sld_fk = IF(@fk_ops_sld_exists = 0,
    'ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_ops_sld_doc` FOREIGN KEY (`ops_sld_document_id`) REFERENCES `project_documents` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_ops_sld FROM @sql_ops_sld_fk;
EXECUTE stmt_ops_sld;
DEALLOCATE PREPARE stmt_ops_sld;

SET @fk_ops_bom_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'business_management_system'
      AND TABLE_NAME = 'projects'
      AND CONSTRAINT_NAME = 'fk_projects_ops_bom_doc'
);
SET @sql_ops_bom_fk = IF(@fk_ops_bom_exists = 0,
    'ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_ops_bom_doc` FOREIGN KEY (`ops_bom_document_id`) REFERENCES `project_documents` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_ops_bom FROM @sql_ops_bom_fk;
EXECUTE stmt_ops_bom;
DEALLOCATE PREPARE stmt_ops_bom;

-- 3. Ensure Project Management does NOT have JOM issuance permission
DELETE rp FROM `role_permissions` rp
INNER JOIN `permissions` p ON p.`id` = rp.`permission_id`
WHERE p.`permission_key` = 'pm.projects.jom_issue';

DELETE FROM `permissions`
WHERE `permission_key` = 'pm.projects.jom_issue';

-- 4. Seed Admin Finance and Operations handoff permissions
INSERT INTO `permissions` (`permission_key`, `permission_name`, `module_group`, `description`)
VALUES
    ('afin.dashboard.view', 'View Admin Finance Dashboard', 'Admin Finance', 'Access dedicated Admin Finance incoming project dashboard and JOM issuance queue.'),
    ('afin.projects.review', 'Review Incoming Approved Projects', 'Admin Finance', 'Mark Project Management approved projects as Under Admin Finance Review.'),
    ('afin.projects.jom_issue', 'Issue Project JOM Number', 'Admin Finance', 'Authorizes Admin Finance to issue official Job Order Memorandum (JOM) numbers to projects forwarded by Project Management.'),
    ('afin.projects.forward_ops', 'Forward JOM, SLD & Marketing BOM to Operations', 'Admin Finance', 'Forward JOM-issued projects along with SLD and Marketing BOM documents to Operations.'),
    ('ops.projects.view_handoff', 'View Forwarded Operations Projects', 'Operations', 'View projects forwarded by Admin Finance and access JOM, SLD, and Marketing BOM.')
ON DUPLICATE KEY UPDATE
    `permission_name` = VALUES(`permission_name`),
    `module_group` = VALUES(`module_group`),
    `description` = VALUES(`description`);

-- 5. Grant permissions to Administrator and Department User roles
-- (Department-specific enforcement is strictly checked in AuthorizationService)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.`role_slug` IN ('administrator', 'admin', 'department_user')
  AND p.`permission_key` IN (
      'afin.dashboard.view',
      'afin.projects.review',
      'afin.projects.jom_issue',
      'afin.projects.forward_ops',
      'ops.projects.view_handoff'
  );
