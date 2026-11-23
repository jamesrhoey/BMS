-- Migration 015: OPS Finance PR and Canvassing Sheets workflow.
-- Uses the existing projects and project_documents records; no project duplication.

ALTER TABLE `projects`
    ADD COLUMN IF NOT EXISTS `opfin_received_at` DATETIME NULL AFTER `opfin_handoff_documents`,
    ADD COLUMN IF NOT EXISTS `opfin_received_by` INT UNSIGNED NULL AFTER `opfin_received_at`,
    ADD COLUMN IF NOT EXISTS `opfin_submitted_to_afin_at` DATETIME NULL AFTER `opfin_received_by`,
    ADD COLUMN IF NOT EXISTS `opfin_submitted_to_afin_by` INT UNSIGNED NULL AFTER `opfin_submitted_to_afin_at`,
    ADD COLUMN IF NOT EXISTS `opfin_afin_handoff_documents` TEXT NULL AFTER `opfin_submitted_to_afin_by`;

ALTER TABLE `projects`
    ADD INDEX IF NOT EXISTS `idx_projects_opfin_received_at` (`opfin_received_at`),
    ADD INDEX IF NOT EXISTS `idx_projects_opfin_afin_submitted_at` (`opfin_submitted_to_afin_at`);
