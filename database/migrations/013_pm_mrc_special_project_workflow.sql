-- =============================================================================
-- Migration 013: Project Management Module (MRC & Special Project Official Workflow)
-- Non-destructive migration extending projects and project_documents for PM verification,
-- JOM file upload by Project Management, and category-based Operations handoff.
-- Database: business_management_system
-- =============================================================================

USE `business_management_system`;

SET NAMES utf8mb4;

-- 1. Extend project_documents for per-document verification notes and reviewer tracking
ALTER TABLE `project_documents`
    ADD COLUMN IF NOT EXISTS `correction_notes` TEXT NULL AFTER `review_status`,
    ADD COLUMN IF NOT EXISTS `verified_by` INT UNSIGNED NULL AFTER `reviewed_by`,
    ADD COLUMN IF NOT EXISTS `verified_at` DATETIME NULL AFTER `reviewed_at`,
    ADD INDEX IF NOT EXISTS `idx_proj_docs_review_status` (`review_status`),
    ADD INDEX IF NOT EXISTS `idx_proj_docs_verified_by` (`verified_by`);

-- 2. Extend projects for PM JOM upload and category-specific OPS handoff document references
ALTER TABLE `projects`
    ADD COLUMN IF NOT EXISTS `jom_document_id` INT UNSIGNED NULL AFTER `jom_issued_at`,
    ADD COLUMN IF NOT EXISTS `jom_uploaded_by` INT UNSIGNED NULL AFTER `jom_document_id`,
    ADD COLUMN IF NOT EXISTS `jom_uploaded_at` DATETIME NULL AFTER `jom_uploaded_by`,
    ADD COLUMN IF NOT EXISTS `ops_jom_document_id` INT UNSIGNED NULL AFTER `ops_bom_document_id`,
    ADD COLUMN IF NOT EXISTS `ops_son_document_id` INT UNSIGNED NULL AFTER `ops_jom_document_id`,
    ADD COLUMN IF NOT EXISTS `ops_po_document_id` INT UNSIGNED NULL AFTER `ops_son_document_id`,
    ADD COLUMN IF NOT EXISTS `ops_handoff_documents` TEXT NULL AFTER `ops_po_document_id`,
    ADD INDEX IF NOT EXISTS `idx_projects_jom_doc` (`jom_document_id`),
    ADD INDEX IF NOT EXISTS `idx_projects_jom_uploader` (`jom_uploaded_by`),
    ADD INDEX IF NOT EXISTS `idx_projects_ops_jom_doc` (`ops_jom_document_id`),
    ADD INDEX IF NOT EXISTS `idx_projects_ops_son_doc` (`ops_son_document_id`),
    ADD INDEX IF NOT EXISTS `idx_projects_ops_po_doc` (`ops_po_document_id`);

-- 3. Foreign key constraints safely
SET @fk_jom_doc_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'business_management_system'
      AND TABLE_NAME = 'projects'
      AND CONSTRAINT_NAME = 'fk_projects_jom_document'
);
SET @sql_jom_doc = IF(@fk_jom_doc_exists = 0,
    'ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_jom_document` FOREIGN KEY (`jom_document_id`) REFERENCES `project_documents` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_jom_doc FROM @sql_jom_doc;
EXECUTE stmt_jom_doc;
DEALLOCATE PREPARE stmt_jom_doc;

SET @fk_jom_up_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'business_management_system'
      AND TABLE_NAME = 'projects'
      AND CONSTRAINT_NAME = 'fk_projects_jom_uploaded_by'
);
SET @sql_jom_up = IF(@fk_jom_up_exists = 0,
    'ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_jom_uploaded_by` FOREIGN KEY (`jom_uploaded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_jom_up FROM @sql_jom_up;
EXECUTE stmt_jom_up;
DEALLOCATE PREPARE stmt_jom_up;

-- 4. Seed PM official workflow permissions
INSERT INTO `permissions` (`permission_key`, `permission_name`, `module_group`, `description`)
VALUES
    ('pm.projects.verify_doc', 'Verify Project Document', 'Project Management', 'Inspect and mark individual Marketing checklist documents as Verified or Needs Correction.'),
    ('pm.projects.jom_upload', 'Upload JOM File', 'Project Management', 'Upload and manage official Job Order Memorandum (JOM) file for approved projects.'),
    ('pm.projects.forward_ops', 'Forward Project to Operations', 'Project Management', 'Forward verified and JOM-uploaded project with category-specific documents to Operations.')
ON DUPLICATE KEY UPDATE
    `permission_name` = VALUES(`permission_name`),
    `module_group` = VALUES(`module_group`),
    `description` = VALUES(`description`);

-- 5. Map permissions to Administrator and Department User roles
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.`role_slug` IN ('administrator', 'admin', 'department_user')
  AND p.`permission_key` IN (
      'pm.dashboard.view',
      'pm.projects.review',
      'pm.projects.verify',
      'pm.projects.verify_doc',
      'pm.projects.jom_upload',
      'pm.projects.forward_ops'
  );
