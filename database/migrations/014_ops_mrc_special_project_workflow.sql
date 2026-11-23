-- =============================================================================
-- Migration 014: Operations (OPS) Department MRC & Special Project Workflow
-- Adds OPS receipt, document references (Final SLD, Final BOM, Request of PR),
-- and OPS Finance handoff columns to projects table.
-- =============================================================================

ALTER TABLE `projects`
    ADD COLUMN IF NOT EXISTS `ops_received_at` DATETIME NULL AFTER `ops_handoff_documents`,
    ADD COLUMN IF NOT EXISTS `ops_received_by` INT UNSIGNED NULL AFTER `ops_received_at`,
    ADD COLUMN IF NOT EXISTS `ops_processed_at` DATETIME NULL AFTER `ops_received_by`,
    ADD COLUMN IF NOT EXISTS `ops_final_sld_document_id` INT UNSIGNED NULL AFTER `ops_processed_at`,
    ADD COLUMN IF NOT EXISTS `ops_final_bom_document_id` INT UNSIGNED NULL AFTER `ops_final_sld_document_id`,
    ADD COLUMN IF NOT EXISTS `ops_request_pr_document_id` INT UNSIGNED NULL AFTER `ops_final_bom_document_id`,
    ADD COLUMN IF NOT EXISTS `submitted_to_opfin_at` DATETIME NULL AFTER `ops_request_pr_document_id`,
    ADD COLUMN IF NOT EXISTS `submitted_to_opfin_by` INT UNSIGNED NULL AFTER `submitted_to_opfin_at`,
    ADD COLUMN IF NOT EXISTS `opfin_submission_notes` TEXT NULL AFTER `submitted_to_opfin_by`,
    ADD COLUMN IF NOT EXISTS `opfin_handoff_documents` TEXT NULL AFTER `opfin_submission_notes`;

ALTER TABLE `projects`
    ADD INDEX IF NOT EXISTS `idx_projects_ops_received_at` (`ops_received_at`),
    ADD INDEX IF NOT EXISTS `idx_projects_ops_final_sld` (`ops_final_sld_document_id`),
    ADD INDEX IF NOT EXISTS `idx_projects_ops_final_bom` (`ops_final_bom_document_id`),
    ADD INDEX IF NOT EXISTS `idx_projects_ops_request_pr` (`ops_request_pr_document_id`),
    ADD INDEX IF NOT EXISTS `idx_projects_submitted_to_opfin_at` (`submitted_to_opfin_at`);
