-- =============================================================================
-- Migration 002: Marketing Department Module & Minimal PM Verification Handoff
-- Database: business_management_system (MariaDB 10.4+ / MySQL 8+)
-- Non-destructive incremental migration preserving all existing foundation data.
-- =============================================================================

USE `business_management_system`;

SET NAMES utf8mb4;

-- 1. Extend `projects` table for Marketing workflow states, team origin, and PM return notes
ALTER TABLE `projects`
    MODIFY COLUMN `status` VARCHAR(60) NOT NULL DEFAULT 'Draft',
    ADD COLUMN IF NOT EXISTS `workflow_type` VARCHAR(40) NOT NULL DEFAULT 'standard' AFTER `project_description`,
    ADD COLUMN IF NOT EXISTS `origin_department_id` INT UNSIGNED NULL AFTER `workflow_type`,
    ADD COLUMN IF NOT EXISTS `submitted_by` INT UNSIGNED NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `verification_status` VARCHAR(40) NOT NULL DEFAULT 'not_submitted' AFTER `submitted_at`,
    ADD COLUMN IF NOT EXISTS `returned_by` INT UNSIGNED NULL AFTER `verification_status`,
    ADD COLUMN IF NOT EXISTS `returned_at` DATETIME NULL AFTER `returned_by`,
    ADD COLUMN IF NOT EXISTS `return_reason` TEXT NULL AFTER `returned_at`,
    ADD COLUMN IF NOT EXISTS `attention_documents` TEXT NULL AFTER `return_reason`,
    ADD INDEX IF NOT EXISTS `idx_projects_origin_dept` (`origin_department_id`),
    ADD INDEX IF NOT EXISTS `idx_projects_verification_status` (`verification_status`);

-- Backfill origin_department_id on any pre-existing projects
UPDATE `projects` p
LEFT JOIN `users` u ON u.`id` = p.`created_by`
SET p.`origin_department_id` = COALESCE(p.`current_department_id`, u.`department_id`)
WHERE p.`origin_department_id` IS NULL;

-- 2. Extend `project_documents` for active-version tracking while preserving prior versions
ALTER TABLE `project_documents`
    ADD COLUMN IF NOT EXISTS `is_current` TINYINT(1) NOT NULL DEFAULT 1 AFTER `version_number`,
    ADD COLUMN IF NOT EXISTS `upload_notes` VARCHAR(255) NULL AFTER `review_status`,
    ADD INDEX IF NOT EXISTS `idx_project_documents_current` (`project_id`, `document_type`, `is_current`);

-- 3. Shared workflow transition & return-for-corrections history table
CREATE TABLE IF NOT EXISTS `project_workflow_histories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT UNSIGNED NOT NULL,
    `from_status` VARCHAR(60) NULL,
    `to_status` VARCHAR(60) NOT NULL,
    `action` VARCHAR(60) NOT NULL,
    `from_department_id` INT UNSIGNED NULL,
    `to_department_id` INT UNSIGNED NULL,
    `acted_by` INT UNSIGNED NOT NULL,
    `notes` TEXT NULL,
    `flagged_documents` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pwh_project_created` (`project_id`, `created_at`),
    KEY `idx_pwh_to_status` (`to_status`),
    CONSTRAINT `fk_pwh_project`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_pwh_from_dept`
        FOREIGN KEY (`from_department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pwh_to_dept`
        FOREIGN KEY (`to_department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pwh_acted_by`
        FOREIGN KEY (`acted_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Shared extensible in-application notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recipient_user_id` INT UNSIGNED NOT NULL,
    `project_id` INT UNSIGNED NULL,
    `type` VARCHAR(60) NOT NULL,
    `title` VARCHAR(180) NOT NULL,
    `message` TEXT NOT NULL,
    `action_url` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_recipient_unread` (`recipient_user_id`, `is_read`, `created_at`),
    KEY `idx_notifications_project` (`project_id`),
    CONSTRAINT `fk_notifications_recipient`
        FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_notifications_project`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Seed Marketing & PM handoff permissions
INSERT INTO `permissions` (`permission_key`, `permission_name`, `module_group`, `description`)
VALUES
    ('marketing.dashboard.view', 'View Marketing Dashboard', 'Marketing', 'Access dedicated Marketing dashboard and statistics.'),
    ('marketing.projects.manage', 'Manage Marketing Projects', 'Marketing', 'Create, update, and prepare Marketing checklist documents.'),
    ('marketing.projects.submit', 'Submit Marketing Projects to PM', 'Marketing', 'Submit complete Marketing projects to Project Management for verification.'),
    ('pm.documents.return', 'Return Projects to Marketing for Corrections', 'Project Management', 'Return incomplete or defective project document packages to Marketing.')
ON DUPLICATE KEY UPDATE
    `permission_name` = VALUES(`permission_name`),
    `module_group` = VALUES(`module_group`),
    `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.`role_slug` = 'administrator';

