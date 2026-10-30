-- =============================================================================
-- Migration 004: Project Management JOM (Job Order Memorandum) Issuance
-- Authorizes Project Management to issue unique JOM numbers upon verified document completion
-- =============================================================================

-- 1. Add JOM tracking columns and unique constraint to projects table (if not exists)
ALTER TABLE `projects`
    ADD COLUMN IF NOT EXISTS `jom_number` VARCHAR(50) NULL AFTER `project_code`,
    ADD COLUMN IF NOT EXISTS `jom_issued_by` INT UNSIGNED NULL AFTER `jom_number`,
    ADD COLUMN IF NOT EXISTS `jom_issued_at` DATETIME NULL AFTER `jom_issued_by`,
    ADD UNIQUE KEY IF NOT EXISTS `uq_projects_jom_number` (`jom_number`),
    ADD KEY IF NOT EXISTS `idx_projects_jom_issued_by` (`jom_issued_by`);

-- 2. Add foreign key constraint for JOM issuer safely if not exists
SET @fk_jom_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'business_management_system'
      AND TABLE_NAME = 'projects'
      AND CONSTRAINT_NAME = 'fk_projects_jom_issued_by'
);

SET @sql_jom_fk = IF(@fk_jom_exists = 0,
    'ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_jom_issued_by` FOREIGN KEY (`jom_issued_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_jom FROM @sql_jom_fk;
EXECUTE stmt_jom;
DEALLOCATE PREPARE stmt_jom;

-- 3. Seed PM JOM issuance permission
INSERT INTO `permissions` (`permission_key`, `permission_name`, `module_group`, `description`)
VALUES
    ('pm.projects.jom_issue', 'Issue Project JOM Number', 'Project Management', 'Authorizes issuing official Job Order Memorandum (JOM) numbers to verified projects.')
ON DUPLICATE KEY UPDATE
    `permission_name` = VALUES(`permission_name`),
    `module_group` = VALUES(`module_group`),
    `description` = VALUES(`description`);

-- 4. Map permission to Administrator and Department User roles
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.role_slug IN ('admin', 'department_user')
  AND p.permission_key = 'pm.projects.jom_issue';
