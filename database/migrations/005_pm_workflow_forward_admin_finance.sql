-- =============================================================================
-- Migration 005: Project Management Workflow Update & Forwarding to Admin Finance
-- New Official Workflow:
-- MARKETING -> PROJECT MANAGEMENT -> ADMIN FINANCE -> OPERATIONS -> OPS FINANCE -> ADMIN FINANCE
-- Project Management verifies documents and forwards approved projects to Admin Finance.
-- Admin Finance (not Project Management) is responsible for issuing JOM numbers.
-- Non-destructive incremental migration preserving all existing data and JOM numbers.
-- =============================================================================

USE `business_management_system`;

SET NAMES utf8mb4;

-- 1. Add Admin Finance forwarding columns to `projects` table (if not exists)
ALTER TABLE `projects`
    ADD COLUMN IF NOT EXISTS `forwarded_to_afin_by` INT UNSIGNED NULL AFTER `verification_notes`,
    ADD COLUMN IF NOT EXISTS `forwarded_to_afin_at` DATETIME NULL AFTER `forwarded_to_afin_by`,
    ADD COLUMN IF NOT EXISTS `forwarded_to_afin_notes` TEXT NULL AFTER `forwarded_to_afin_at`,
    ADD INDEX IF NOT EXISTS `idx_projects_forwarded_to_afin_by` (`forwarded_to_afin_by`);

-- 2. Add foreign key constraint for `forwarded_to_afin_by` safely if not exists
SET @fk_afin_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'business_management_system'
      AND TABLE_NAME = 'projects'
      AND CONSTRAINT_NAME = 'fk_projects_forwarded_to_afin_by'
);

SET @sql_afin_fk = IF(@fk_afin_exists = 0,
    'ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_forwarded_to_afin_by` FOREIGN KEY (`forwarded_to_afin_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_afin FROM @sql_afin_fk;
EXECUTE stmt_afin;
DEALLOCATE PREPARE stmt_afin;

-- 3. Remove JOM generation permission from Project Management (`pm.projects.jom_issue`)
DELETE rp FROM `role_permissions` rp
INNER JOIN `permissions` p ON p.`id` = rp.`permission_id`
WHERE p.`permission_key` = 'pm.projects.jom_issue';

DELETE FROM `permissions`
WHERE `permission_key` = 'pm.projects.jom_issue';

-- 4. Seed PM forwarding permission and refactored Admin Finance JOM issuance permission
INSERT INTO `permissions` (`permission_key`, `permission_name`, `module_group`, `description`)
VALUES
    ('pm.projects.forward_afin', 'Forward Approved Project to Admin Finance', 'Project Management', 'Forward Project Management approved projects to Admin Finance incoming queue.'),
    ('afin.projects.jom_issue', 'Issue Project JOM Number', 'Admin Finance', 'Authorizes Admin Finance to issue official Job Order Memorandum (JOM) numbers to projects forwarded by Project Management.')
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
      'pm.dashboard.view',
      'pm.projects.review',
      'pm.projects.verify',
      'pm.projects.forward_afin',
      'afin.projects.jom_issue'
  );
