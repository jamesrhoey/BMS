-- =============================================================================
-- Migration 012: Marketing Department Module — MRC & Special Project Workflow
-- Adds project category (MRC / Special Project) and simultaneous Admin Finance submission tracking
-- =============================================================================

USE `business_management_system`;

SET NAMES utf8mb4;

-- 1. Add project_category, submitted_to_afin_at, and submitted_to_afin_by columns to projects table
ALTER TABLE `projects`
    ADD COLUMN IF NOT EXISTS `project_category` VARCHAR(40) NOT NULL DEFAULT 'MRC' AFTER `workflow_type`,
    ADD COLUMN IF NOT EXISTS `submitted_to_afin_at` DATETIME NULL AFTER `submitted_at`,
    ADD COLUMN IF NOT EXISTS `submitted_to_afin_by` INT UNSIGNED NULL AFTER `submitted_to_afin_at`,
    ADD INDEX IF NOT EXISTS `idx_projects_category` (`project_category`),
    ADD INDEX IF NOT EXISTS `idx_projects_submitted_afin` (`submitted_to_afin_at`);

-- 2. Safely add foreign key for submitted_to_afin_by if not exists
SET @fk_afin_sub_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'projects'
      AND CONSTRAINT_NAME = 'fk_projects_submitted_to_afin_by'
);

SET @sql_afin_sub_fk = IF(@fk_afin_sub_exists = 0,
    'ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_submitted_to_afin_by` FOREIGN KEY (`submitted_to_afin_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_afin_sub FROM @sql_afin_sub_fk;
EXECUTE stmt_afin_sub;
DEALLOCATE PREPARE stmt_afin_sub;

-- 3. Backfill existing records: standard -> mrc, workflow_type -> mrc, project_category -> MRC
UPDATE `projects`
SET `workflow_type` = 'mrc',
    `project_category` = 'MRC'
WHERE `workflow_type` = 'standard' OR `project_category` IS NULL OR `project_category` = '';

-- 4. Seed Marketing Category and Simultaneous Submission permissions
INSERT INTO `permissions` (`permission_key`, `permission_name`, `module_group`, `description`)
VALUES
    ('marketing.projects.category_filter', 'Filter Marketing Projects by Category', 'Marketing', 'Filter and view projects categorized as MRC or Special Project.'),
    ('marketing.projects.submit_simultaneous', 'Submit Projects Simultaneously to PM & Admin Finance', 'Marketing', 'Submit verified Marketing project packages simultaneously to Project Management and Admin Finance.')
ON DUPLICATE KEY UPDATE
    `permission_name` = VALUES(`permission_name`),
    `module_group` = VALUES(`module_group`),
    `description` = VALUES(`description`);

-- 5. Map new permissions to Administrator and Department User roles
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.role_slug IN ('admin', 'department_user')
  AND p.permission_key IN ('marketing.projects.category_filter', 'marketing.projects.submit_simultaneous');
