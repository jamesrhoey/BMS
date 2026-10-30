-- =============================================================================
-- Migration 003: Project Management Department Module
-- Database: business_management_system (MariaDB 10.4+ / MySQL 8+)
-- Non-destructive incremental migration preserving all existing records.
-- =============================================================================

USE `business_management_system`;

SET NAMES utf8mb4;

-- 1. Extend `projects` table for Project Management verification records
ALTER TABLE `projects`
    ADD COLUMN IF NOT EXISTS `reviewed_by` INT UNSIGNED NULL AFTER `attention_documents`,
    ADD COLUMN IF NOT EXISTS `reviewed_at` DATETIME NULL AFTER `reviewed_by`,
    ADD COLUMN IF NOT EXISTS `verified_by` INT UNSIGNED NULL AFTER `reviewed_at`,
    ADD COLUMN IF NOT EXISTS `verified_at` DATETIME NULL AFTER `verified_by`,
    ADD COLUMN IF NOT EXISTS `verification_notes` TEXT NULL AFTER `verified_at`,
    ADD INDEX IF NOT EXISTS `idx_projects_verified_by` (`verified_by`),
    ADD INDEX IF NOT EXISTS `idx_projects_reviewed_by` (`reviewed_by`);

-- Add foreign key constraint safely if not exists
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'business_management_system'
      AND TABLE_NAME = 'projects'
      AND CONSTRAINT_NAME = 'fk_projects_verified_by'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Seed Project Management module permissions
INSERT INTO `permissions` (`permission_key`, `permission_name`, `module_group`, `description`)
VALUES
    ('pm.dashboard.view', 'View Project Management Dashboard', 'Project Management', 'Access dedicated Project Management dashboard, queues, and verification metrics.'),
    ('pm.projects.review', 'Review Project Documents', 'Project Management', 'Inspect submitted Marketing documents and mark projects under review.'),
    ('pm.projects.verify', 'Complete Document Verification', 'Project Management', 'Approve the document verification stage when all required documents meet requirements.')
ON DUPLICATE KEY UPDATE
    `permission_name` = VALUES(`permission_name`),
    `module_group` = VALUES(`module_group`),
    `description` = VALUES(`description`);

