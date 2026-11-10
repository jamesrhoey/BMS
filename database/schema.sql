-- =============================================================================
-- SYB Business Management System - Unified Database Schema
-- Database: business_management_system
-- Engine: InnoDB | Charset: utf8mb4_unicode_ci
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `business_management_system`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `business_management_system`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- A. departments
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_name` VARCHAR(100) NOT NULL,
    `department_code` VARCHAR(20) NOT NULL,
    `description` TEXT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_departments_code` (`department_code`),
    UNIQUE KEY `uq_departments_name` (`department_name`),
    KEY `idx_departments_status` (`status`),
    CONSTRAINT `chk_departments_status` CHECK (`status` IN ('active', 'inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- B. roles & extensible granular permissions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_name` VARCHAR(80) NOT NULL,
    `role_slug` VARCHAR(50) NOT NULL,
    `description` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_name` (`role_name`),
    UNIQUE KEY `uq_roles_slug` (`role_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `permission_key` VARCHAR(100) NOT NULL,
    `permission_name` VARCHAR(120) NOT NULL,
    `module_group` VARCHAR(60) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permissions_key` (`permission_key`),
    KEY `idx_permissions_group` (`module_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_role_permissions_role`
        FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_role_permissions_permission`
        FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- C. users
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_id` INT UNSIGNED NULL,
    `role_id` INT UNSIGNED NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(190) NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_department` (`department_id`),
    KEY `idx_users_role` (`role_id`),
    KEY `idx_users_status` (`status`),
    CONSTRAINT `chk_users_status` CHECK (`status` IN ('active', 'inactive')),
    CONSTRAINT `fk_users_department`
        FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_users_role`
        FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- D. projects (Shared cross-department project registry)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_code` VARCHAR(40) NOT NULL,
    `jom_number` VARCHAR(50) NULL,
    `jom_issued_by` INT UNSIGNED NULL,
    `jom_issued_at` DATETIME NULL,
    `project_name` VARCHAR(200) NOT NULL,
    `client_name` VARCHAR(150) NOT NULL,
    `client_contact` VARCHAR(150) NULL,
    `project_location` VARCHAR(200) NULL,
    `project_description` TEXT NULL,
    `workflow_type` VARCHAR(40) NOT NULL DEFAULT 'standard',
    `origin_department_id` INT UNSIGNED NULL,
    `current_department_id` INT UNSIGNED NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `status` VARCHAR(60) NOT NULL DEFAULT 'Draft',
    `submitted_by` INT UNSIGNED NULL,
    `submitted_at` DATETIME NULL,
    `verification_status` VARCHAR(40) NOT NULL DEFAULT 'not_submitted',
    `returned_by` INT UNSIGNED NULL,
    `returned_at` DATETIME NULL,
    `return_reason` TEXT NULL,
    `attention_documents` TEXT NULL,
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `verified_by` INT UNSIGNED NULL,
    `verified_at` DATETIME NULL,
    `verification_notes` TEXT NULL,
    `forwarded_to_afin_by` INT UNSIGNED NULL,
    `forwarded_to_afin_at` DATETIME NULL,
    `forwarded_to_afin_notes` TEXT NULL,
    `afin_reviewed_by` INT UNSIGNED NULL,
    `afin_reviewed_at` DATETIME NULL,
    `forwarded_to_ops_by` INT UNSIGNED NULL,
    `forwarded_to_ops_at` DATETIME NULL,
    `forwarded_to_ops_notes` TEXT NULL,
    `ops_sld_document_id` INT UNSIGNED NULL,
    `ops_bom_document_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_projects_code` (`project_code`),
    UNIQUE KEY `uq_projects_jom_number` (`jom_number`),
    KEY `idx_projects_status` (`status`),
    KEY `idx_projects_origin_dept` (`origin_department_id`),
    KEY `idx_projects_current_dept` (`current_department_id`),
    KEY `idx_projects_created_by` (`created_by`),
    KEY `idx_projects_verification_status` (`verification_status`),
    KEY `idx_projects_verified_by` (`verified_by`),
    KEY `idx_projects_reviewed_by` (`reviewed_by`),
    KEY `idx_projects_forwarded_to_afin_by` (`forwarded_to_afin_by`),
    KEY `idx_projects_afin_reviewed_by` (`afin_reviewed_by`),
    KEY `idx_projects_forwarded_to_ops_by` (`forwarded_to_ops_by`),
    KEY `idx_projects_forwarded_to_ops_at` (`forwarded_to_ops_at`),
    KEY `idx_projects_jom_issued_by` (`jom_issued_by`),
    KEY `idx_projects_created_at` (`created_at`),
    CONSTRAINT `fk_projects_origin_department`
        FOREIGN KEY (`origin_department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_projects_current_department`
        FOREIGN KEY (`current_department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_projects_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_projects_submitted_by`
        FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_projects_returned_by`
        FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_projects_verified_by`
        FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_projects_forwarded_to_afin_by`
        FOREIGN KEY (`forwarded_to_afin_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_projects_afin_reviewed_by`
        FOREIGN KEY (`afin_reviewed_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_projects_forwarded_to_ops_by`
        FOREIGN KEY (`forwarded_to_ops_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_projects_jom_issued_by`
        FOREIGN KEY (`jom_issued_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- E. project_documents (Versioned document storage)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `project_documents` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT UNSIGNED NOT NULL,
    `document_type` VARCHAR(100) NOT NULL,
    `version_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `is_current` TINYINT(1) NOT NULL DEFAULT 1,
    `original_filename` VARCHAR(255) NOT NULL,
    `stored_filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(120) NOT NULL,
    `file_size` BIGINT UNSIGNED NOT NULL,
    `review_status` VARCHAR(30) NOT NULL DEFAULT 'uploaded',
    `upload_notes` VARCHAR(255) NULL,
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `uploaded_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_project_documents_stored` (`stored_filename`),
    KEY `idx_project_documents_project` (`project_id`),
    KEY `idx_project_documents_type` (`document_type`),
    KEY `idx_project_documents_current` (`project_id`, `document_type`, `is_current`),
    KEY `idx_project_documents_uploaded_by` (`uploaded_by`),
    CONSTRAINT `fk_project_documents_project`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_project_documents_uploaded_by`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_project_documents_reviewed_by`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- F. project_workflow_histories (Shared cross-department workflow transitions)
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- G. notifications (In-application cross-department notifications)
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- H. activity_logs (Immutable audit trail)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(80) NOT NULL,
    `entity_type` VARCHAR(80) NOT NULL,
    `entity_id` INT UNSIGNED NULL,
    `description` TEXT NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_logs_user` (`user_id`),
    KEY `idx_activity_logs_action` (`action`),
    KEY `idx_activity_logs_entity` (`entity_type`, `entity_id`),
    KEY `idx_activity_logs_created_at` (`created_at`),
    CONSTRAINT `fk_activity_logs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- I. purchase_requests (Operations Purchase Request linked to Project & JOM)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pr_number` VARCHAR(60) NOT NULL,
    `project_id` INT UNSIGNED NOT NULL,
    `jom_number` VARCHAR(50) NOT NULL,
    `requesting_department_id` INT UNSIGNED NULL,
    `receiving_department_id` INT UNSIGNED NULL,
    `requested_by` INT UNSIGNED NOT NULL,
    `pr_date` DATE NOT NULL,
    `purpose` TEXT NULL,
    `required_delivery_date` DATE NULL,
    `remarks` TEXT NULL,
    `attachment_document_id` INT UNSIGNED NULL,
    `sld_document_id` INT UNSIGNED NULL,
    `marketing_bom_document_id` INT UNSIGNED NULL,
    `actual_bom_id` INT UNSIGNED NULL,
    `status` VARCHAR(60) NOT NULL DEFAULT 'Draft',
    `submitted_by` INT UNSIGNED NULL,
    `submitted_at` DATETIME NULL,
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `returned_by` INT UNSIGNED NULL,
    `returned_at` DATETIME NULL,
    `return_reason` TEXT NULL,
    `approved_by` INT UNSIGNED NULL,
    `approved_at` DATETIME NULL,
    `approval_notes` TEXT NULL,
    `rejected_by` INT UNSIGNED NULL,
    `rejected_at` DATETIME NULL,
    `rejection_reason` TEXT NULL,
    `ordered_quantity_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `ordered_status` VARCHAR(40) NOT NULL DEFAULT 'unconverted',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_purchase_requests_pr_number` (`pr_number`),
    KEY `idx_purchase_requests_project` (`project_id`),
    KEY `idx_purchase_requests_jom` (`jom_number`),
    KEY `idx_purchase_requests_status` (`status`),
    KEY `idx_purchase_requests_submitted_at` (`submitted_at`),
    CONSTRAINT `fk_pr_project`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_pr_req_dept`
        FOREIGN KEY (`requesting_department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_rec_dept`
        FOREIGN KEY (`receiving_department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_requested_by`
        FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_pr_attachment_doc`
        FOREIGN KEY (`attachment_document_id`) REFERENCES `project_documents` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_sld_doc`
        FOREIGN KEY (`sld_document_id`) REFERENCES `project_documents` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_mbom_doc`
        FOREIGN KEY (`marketing_bom_document_id`) REFERENCES `project_documents` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_submitted_by`
        FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_reviewed_by`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_returned_by`
        FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_pr_rejected_by`
        FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- J. purchase_request_items (Multiple PR line items per PR)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_request_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_request_id` INT UNSIGNED NOT NULL,
    `line_no` INT UNSIGNED NOT NULL DEFAULT 1,
    `item_description` VARCHAR(255) NOT NULL,
    `model_part_number` VARCHAR(150) NULL,
    `item_specification` TEXT NOT NULL,
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    `unit_of_measure` VARCHAR(50) NOT NULL DEFAULT 'pcs',
    `purpose` VARCHAR(255) NULL,
    `approved_quantity` DECIMAL(12,2) NULL,
    `ordered_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `remarks` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pr_items_pr_id` (`purchase_request_id`, `line_no`),
    CONSTRAINT `fk_pr_items_pr`
        FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- J2. purchase_request_histories (PR lifecycle review, approval & revision audit)
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- K. actual_boms (Separate from Marketing BOM; linked to Project, JOM, and PR)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `actual_boms` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actual_bom_number` VARCHAR(60) NOT NULL,
    `project_id` INT UNSIGNED NOT NULL,
    `jom_number` VARCHAR(50) NOT NULL,
    `purchase_request_id` INT UNSIGNED NOT NULL,
    `version_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `remarks` TEXT NULL,
    `document_id` INT UNSIGNED NULL,
    `supporting_document_id` INT UNSIGNED NULL,
    `status` VARCHAR(60) NOT NULL DEFAULT 'Draft',
    `uploaded_by` INT UNSIGNED NOT NULL,
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `submitted_by` INT UNSIGNED NULL,
    `submitted_at` DATETIME NULL,
    `received_by` INT UNSIGNED NULL,
    `received_at` DATETIME NULL,
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `returned_by` INT UNSIGNED NULL,
    `returned_at` DATETIME NULL,
    `return_reason` TEXT NULL,
    `accepted_by` INT UNSIGNED NULL,
    `accepted_at` DATETIME NULL,
    `acceptance_notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_actual_boms_number` (`actual_bom_number`),
    KEY `idx_actual_boms_project` (`project_id`),
    KEY `idx_actual_boms_pr` (`purchase_request_id`),
    KEY `idx_actual_boms_jom` (`jom_number`),
    KEY `idx_actual_boms_status` (`status`),
    CONSTRAINT `fk_actual_boms_project`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_actual_boms_pr`
        FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_actual_boms_doc`
        FOREIGN KEY (`document_id`) REFERENCES `project_documents` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_actual_boms_supp_doc`
        FOREIGN KEY (`supporting_document_id`) REFERENCES `project_documents` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_actual_boms_uploaded_by`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_actual_boms_submitted_by`
        FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_actual_boms_received_by`
        FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_actual_boms_reviewed_by`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_actual_boms_returned_by`
        FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_actual_boms_accepted_by`
        FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- L. actual_bom_items (Multiple Actual BOM line items with actual specs)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `actual_bom_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actual_bom_id` INT UNSIGNED NOT NULL,
    `pr_item_id` INT UNSIGNED NULL,
    `line_no` INT UNSIGNED NOT NULL DEFAULT 1,
    `item_description` VARCHAR(255) NOT NULL,
    `actual_specification` TEXT NOT NULL,
    `quantity` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    `unit_of_measure` VARCHAR(50) NOT NULL DEFAULT 'pcs',
    `remarks` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_actual_bom_items_bom_id` (`actual_bom_id`, `line_no`),
    CONSTRAINT `fk_actual_bom_items_bom`
        FOREIGN KEY (`actual_bom_id`) REFERENCES `actual_boms` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_actual_bom_items_pr_item`
        FOREIGN KEY (`pr_item_id`) REFERENCES `purchase_request_items` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: suppliers
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_code` VARCHAR(50) NOT NULL,
    `supplier_name` VARCHAR(200) NOT NULL,
    `contact_person` VARCHAR(150) NULL,
    `email` VARCHAR(190) NULL,
    `phone` VARCHAR(50) NULL,
    `address` TEXT NULL,
    `tax_identification_number` VARCHAR(50) NULL,
    `default_payment_terms` VARCHAR(100) NULL DEFAULT 'Net 30',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_suppliers_code` (`supplier_code`),
    KEY `idx_suppliers_status` (`status`),
    CONSTRAINT `fk_suppliers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: purchase_orders
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `po_number` VARCHAR(60) NOT NULL,
    `purchase_request_id` INT UNSIGNED NOT NULL,
    `project_id` INT UNSIGNED NOT NULL,
    `jom_number` VARCHAR(50) NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `po_date` DATE NOT NULL,
    `delivery_terms` VARCHAR(255) NULL,
    `expected_delivery_date` DATE NULL,
    `payment_terms_summary` VARCHAR(255) NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'PHP',
    `subtotal_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `remarks` TEXT NULL,
    `supporting_document_id` INT UNSIGNED NULL,
    `status` VARCHAR(60) NOT NULL DEFAULT 'Draft',
    `created_by` INT UNSIGNED NOT NULL,
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `review_notes` TEXT NULL,
    `authorized_by` INT UNSIGNED NULL,
    `authorized_at` DATETIME NULL,
    `authorization_notes` TEXT NULL,
    `sent_to_supplier_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_po_number` (`po_number`),
    KEY `idx_po_project` (`project_id`),
    KEY `idx_po_pr` (`purchase_request_id`),
    KEY `idx_po_supplier` (`supplier_id`),
    KEY `idx_po_status` (`status`),
    CONSTRAINT `fk_po_pr` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`),
    CONSTRAINT `fk_po_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
    CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
    CONSTRAINT `fk_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_po_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_po_authorized_by` FOREIGN KEY (`authorized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_po_doc` FOREIGN KEY (`supporting_document_id`) REFERENCES `project_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: purchase_order_items
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_order_id` INT UNSIGNED NOT NULL,
    `pr_item_id` INT UNSIGNED NULL,
    `line_no` INT UNSIGNED NOT NULL DEFAULT 1,
    `item_description` VARCHAR(255) NOT NULL,
    `model_part_number` VARCHAR(100) NULL,
    `item_specification` TEXT NOT NULL,
    `quantity_ordered` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    `unit_of_measure` VARCHAR(50) NOT NULL DEFAULT 'pcs',
    `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `quantity_received` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `remarks` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_poi_po_id` (`purchase_order_id`, `line_no`),
    KEY `idx_poi_pr_item` (`pr_item_id`),
    CONSTRAINT `fk_poi_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_poi_pr_item` FOREIGN KEY (`pr_item_id`) REFERENCES `purchase_request_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: payment_terms_schedules
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_terms_schedules` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_order_id` INT UNSIGNED NOT NULL,
    `payment_term` VARCHAR(100) NOT NULL,
    `percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `due_date` DATE NULL,
    `status` VARCHAR(40) NOT NULL DEFAULT 'Scheduled',
    `payment_reference` VARCHAR(100) NULL,
    `paid_by` INT UNSIGNED NULL,
    `paid_at` DATETIME NULL,
    `remarks` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pts_po` (`purchase_order_id`),
    CONSTRAINT `fk_pts_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pts_paid_by` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: purchase_order_histories
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_order_histories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_order_id` INT UNSIGNED NOT NULL,
    `previous_status` VARCHAR(60) NOT NULL,
    `new_status` VARCHAR(60) NOT NULL,
    `action` VARCHAR(60) NOT NULL,
    `actor_id` INT UNSIGNED NOT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_poh_po_id` (`purchase_order_id`),
    CONSTRAINT `fk_poh_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_poh_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: supplier_deliveries
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supplier_deliveries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `delivery_receipt_no` VARCHAR(100) NOT NULL,
    `purchase_order_id` INT UNSIGNED NOT NULL,
    `project_id` INT UNSIGNED NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `delivery_date` DATE NOT NULL,
    `carrier_name` VARCHAR(150) NULL,
    `tracking_or_plate_no` VARCHAR(100) NULL,
    `dr_document_id` INT UNSIGNED NULL,
    `received_by` INT UNSIGNED NOT NULL,
    `receiving_notes` TEXT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Pending Inspection',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sd_po` (`purchase_order_id`),
    KEY `idx_sd_project` (`project_id`),
    KEY `idx_sd_dr_no` (`delivery_receipt_no`),
    CONSTRAINT `fk_sd_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
    CONSTRAINT `fk_sd_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
    CONSTRAINT `fk_sd_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
    CONSTRAINT `fk_sd_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_sd_doc` FOREIGN KEY (`dr_document_id`) REFERENCES `project_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: supplier_delivery_items
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supplier_delivery_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `delivery_id` INT UNSIGNED NOT NULL,
    `po_item_id` INT UNSIGNED NOT NULL,
    `quantity_delivered` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `quantity_accepted` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `quantity_rejected` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `rejection_reason` VARCHAR(500) NULL,
    `remarks` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sdi_delivery` (`delivery_id`),
    KEY `idx_sdi_po_item` (`po_item_id`),
    CONSTRAINT `fk_sdi_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `supplier_deliveries` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sdi_po_item` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: inventory_items
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_code` VARCHAR(50) NOT NULL,
    `item_name` VARCHAR(200) NOT NULL,
    `model_part_number` VARCHAR(100) NULL,
    `category` VARCHAR(60) NOT NULL DEFAULT 'Serialized Equipment',
    `unit_of_measure` VARCHAR(50) NOT NULL DEFAULT 'pcs',
    `is_serialized` TINYINT(1) NOT NULL DEFAULT 1,
    `current_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `description` TEXT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_inv_item_code` (`item_code`),
    KEY `idx_inv_item_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: inventory_transactions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_number` VARCHAR(60) NOT NULL,
    `transaction_type` VARCHAR(40) NOT NULL,
    `inventory_item_id` INT UNSIGNED NOT NULL,
    `quantity` DECIMAL(12,2) NOT NULL,
    `unit_of_measure` VARCHAR(50) NOT NULL DEFAULT 'pcs',
    `warehouse_location` VARCHAR(100) NOT NULL DEFAULT 'PCT Warehouse',
    `project_id` INT UNSIGNED NULL,
    `jom_number` VARCHAR(50) NULL,
    `reference_type` VARCHAR(60) NOT NULL,
    `reference_id` INT UNSIGNED NOT NULL,
    `balance_before` DECIMAL(12,2) NOT NULL,
    `balance_after` DECIMAL(12,2) NOT NULL,
    `performed_by` INT UNSIGNED NOT NULL,
    `remarks` VARCHAR(500) NULL,
    `transaction_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_inv_txn_number` (`transaction_number`),
    KEY `idx_it_item` (`inventory_item_id`, `created_at`),
    KEY `idx_it_project` (`project_id`),
    KEY `idx_it_ref` (`reference_type`, `reference_id`),
    CONSTRAINT `fk_it_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
    CONSTRAINT `fk_it_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_it_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: inventory_units
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_units` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `po_number` VARCHAR(60) NOT NULL,
    `po_date` DATE NOT NULL,
    `model` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `warranty_duration` VARCHAR(100) NOT NULL DEFAULT '1 Year',
    `serial_number` VARCHAR(150) NOT NULL,
    `in_qty` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    `inventory_date` DATE NOT NULL,
    `inventory_item_id` INT UNSIGNED NOT NULL,
    `purchase_order_id` INT UNSIGNED NOT NULL,
    `delivery_id` INT UNSIGNED NOT NULL,
    `stock_in_transaction_id` BIGINT UNSIGNED NOT NULL,
    `project_id` INT UNSIGNED NULL,
    `jom_number` VARCHAR(50) NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'In Stock',
    `current_location` VARCHAR(150) NOT NULL DEFAULT 'PCT Warehouse',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_iu_serial` (`serial_number`),
    KEY `idx_iu_item` (`inventory_item_id`),
    KEY `idx_iu_po` (`purchase_order_id`),
    KEY `idx_iu_delivery` (`delivery_id`),
    KEY `idx_iu_status` (`status`),
    KEY `idx_iu_project` (`project_id`),
    CONSTRAINT `fk_iu_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
    CONSTRAINT `fk_iu_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
    CONSTRAINT `fk_iu_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `supplier_deliveries` (`id`),
    CONSTRAINT `fk_iu_txn` FOREIGN KEY (`stock_in_transaction_id`) REFERENCES `inventory_transactions` (`id`),
    CONSTRAINT `fk_iu_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_iu_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Initial Seed Data (Idempotent)
-- =============================================================================

INSERT INTO `departments` (`department_name`, `department_code`, `description`, `status`)
VALUES
    ('Marketing', 'MKT', 'Handles client inquiries, initial project registration, and pre-project coordination.', 'active'),
    ('Project Management', 'PM', 'Oversees project planning, JOM coordination (future module), and inter-departmental execution.', 'active'),
    ('Operations', 'OPS', 'Executes technical field services, engineering tasks, and project deliverables.', 'active'),
    ('Logistics', 'LOG', 'Manages materials handling, procurement support, warehousing, and equipment dispatch.', 'active'),
    ('Operation Finance', 'OPFIN', 'Controls operational project budgets, cost monitoring, and project disbursements.', 'active'),
    ('Admin Finance', 'ADMFIN', 'Manages corporate accounting, billing, invoicing, collections, and financial reporting.', 'active')
ON DUPLICATE KEY UPDATE
    `department_name` = VALUES(`department_name`),
    `description` = VALUES(`description`);

INSERT INTO `roles` (`role_name`, `role_slug`, `description`)
VALUES
    ('Administrator', 'administrator', 'Full administrative access across users, departments, projects, and system audit logs.'),
    ('Department User', 'department_user', 'Authenticated access scoped to assigned department dashboard, profile, and authorized projects.')
ON DUPLICATE KEY UPDATE
    `description` = VALUES(`description`);

INSERT INTO `permissions` (`permission_key`, `permission_name`, `module_group`, `description`)
VALUES
    ('dashboard.admin.view', 'View Administrator Dashboard', 'Dashboard', 'Access system-wide statistics and administrative overview.'),
    ('dashboard.department.view', 'View Department Dashboard', 'Dashboard', 'Access assigned department workspace and summary.'),
    ('profile.view', 'View Own Profile', 'Profile', 'View own user account and department details.'),
    ('users.view', 'View Users', 'User Management', 'View registered user accounts across departments.'),
    ('users.create', 'Create Users', 'User Management', 'Register new user accounts and assign roles/departments.'),
    ('users.update', 'Edit Users', 'User Management', 'Modify user account details, roles, and department assignments.'),
    ('users.status', 'Activate/Deactivate Users', 'User Management', 'Enable or disable user login access.'),
    ('departments.view', 'View Departments', 'Departments', 'View all company departments and assigned user counts.'),
    ('projects.view_all', 'View All Projects', 'Projects', 'View projects across all six company departments.'),
    ('projects.view_department', 'View Department Projects', 'Projects', 'View projects assigned to the user''s department or created by the user.'),
    ('projects.create', 'Register Draft Projects', 'Projects', 'Create new draft project registrations.'),
    ('projects.update', 'Update Draft Projects', 'Projects', 'Edit permitted project registration details.'),
    ('projects.submit', 'Submit Draft Projects', 'Projects', 'Submit draft projects into active registered status.'),
    ('documents.upload', 'Upload Project Documents', 'Documents', 'Upload supporting documents to authorized projects.'),
    ('documents.download', 'Download Project Documents', 'Documents', 'Download documents attached to authorized projects.'),
    ('activity_logs.view', 'View Activity Logs', 'Audit', 'Review system-wide audit trail and security events.'),
    ('marketing.dashboard.view', 'View Marketing Dashboard', 'Marketing', 'Access dedicated Marketing dashboard and statistics.'),
    ('marketing.projects.manage', 'Manage Marketing Projects', 'Marketing', 'Create, update, and prepare Marketing checklist documents.'),
    ('marketing.projects.submit', 'Submit Marketing Projects to PM', 'Marketing', 'Submit complete Marketing projects to Project Management for verification.'),
    ('pm.documents.return', 'Return Projects to Marketing for Corrections', 'Project Management', 'Return incomplete or defective project document packages to Marketing.'),
    ('pm.dashboard.view', 'View Project Management Dashboard', 'Project Management', 'Access dedicated Project Management dashboard, queues, and verification metrics.'),
    ('pm.projects.review', 'Review Project Documents', 'Project Management', 'Inspect submitted Marketing documents and mark projects under review.'),
    ('pm.projects.verify', 'Complete Document Verification', 'Project Management', 'Approve the document verification stage when all required documents meet requirements.'),
    ('pm.projects.forward_afin', 'Forward Approved Project to Admin Finance', 'Project Management', 'Forward Project Management approved projects to Admin Finance incoming queue.'),
    ('afin.dashboard.view', 'View Admin Finance Dashboard', 'Admin Finance', 'Access dedicated Admin Finance incoming project dashboard and JOM issuance queue.'),
    ('afin.projects.review', 'Review Incoming Approved Projects', 'Admin Finance', 'Mark Project Management approved projects as Under Admin Finance Review.'),
    ('afin.projects.jom_issue', 'Issue Project JOM Number', 'Admin Finance', 'Authorizes Admin Finance to issue official Job Order Memorandum (JOM) numbers to projects forwarded by Project Management.'),
    ('afin.projects.forward_ops', 'Forward JOM, SLD & Marketing BOM to Operations', 'Admin Finance', 'Forward JOM-issued projects along with SLD and Marketing BOM documents to Operations.'),
    ('ops.projects.view_handoff', 'View Forwarded Operations Projects', 'Operations', 'View projects forwarded by Admin Finance and access JOM, SLD, and Marketing BOM.'),
    ('ops.dashboard.view', 'View Operations Dashboard', 'Operations', 'Access dedicated Operations incoming project queue and PR metrics.'),
    ('ops.pr.manage', 'Prepare and Submit Purchase Requests', 'Operations', 'Create, edit draft, and submit Purchase Requests (PR) with line items to OPS Finance.'),
    ('opfin.dashboard.view', 'View OPS Finance Dashboard', 'Operation Finance', 'Access dedicated OPS Finance incoming PR queue and Actual BOM metrics.'),
    ('opfin.pr.review', 'Review Operations Purchase Requests', 'Operation Finance', 'Inspect submitted Purchase Requests and return for corrections if needed.'),
    ('opfin.actual_bom.manage', 'Upload and Submit Actual BOM', 'Operation Finance', 'Upload separate Actual BOM documents and specifications and submit to Admin Finance.'),
    ('afin.actual_bom.review', 'Receive and Review Actual BOM', 'Admin Finance', 'Mark Actual BOM submissions as Received, Under Review, Returned for Corrections, or Accepted.'),
    ('ops.pr.view_all', 'View All Purchase Requests', 'Operations', 'View Purchase Requests across all projects and departments.'),
    ('opfin.pr.approve', 'Approve Purchase Requests', 'Operation Finance', 'Formally review and approve Purchase Requests for PO eligibility.'),
    ('opfin.pr.reject', 'Reject Purchase Requests', 'Operation Finance', 'Reject Purchase Requests with documented reason.'),
    ('afin.pr.approve', 'Admin Finance PR Approval', 'Admin Finance', 'Financial authorization and approval of Purchase Requests.'),
    ('afin.pr.reject', 'Admin Finance PR Rejection', 'Admin Finance', 'Financial rejection of Purchase Requests.'),
    ('afin.po.manage', 'Create and Manage Purchase Orders', 'Purchase Orders', 'Prepare, edit drafts, and configure Purchase Orders from approved PRs.'),
    ('afin.po.authorize', 'Authorize Purchase Orders', 'Purchase Orders', 'Financial approval and authorization of Purchase Orders prior to supplier issuance.'),
    ('afin.po.issue', 'Issue PO to Supplier', 'Purchase Orders', 'Officially transmit authorized Purchase Order to external supplier.'),
    ('afin.payment_terms.manage', 'Manage Terms of Payment', 'Purchase Orders', 'Define milestone payment terms and record financial confirmations.'),
    ('log.delivery.receive', 'Log Supplier Delivery', 'Receiving & Inventory', 'Log supplier physical delivery shipments with delivery receipt (DR).'),
    ('ops.delivery.inspect', 'Perform Technical Receiving Inspection', 'Receiving & Inventory', 'Inspect delivered equipment against PO specifications and certify acceptance.'),
    ('log.stock_in.manage', 'Execute Stock In Transaction', 'Receiving & Inventory', 'Create Stock In transaction, record physical serial numbers, and increment inventory balance.'),
    ('log.inventory.view', 'View Inventory Ledger and Units', 'Receiving & Inventory', 'View perpetual inventory ledger, balances, and individual serialized units.')
ON DUPLICATE KEY UPDATE
    `permission_name` = VALUES(`permission_name`),
    `module_group` = VALUES(`module_group`),
    `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.`role_slug` = 'administrator';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
INNER JOIN `permissions` p ON p.`permission_key` IN (
    'dashboard.department.view',
    'profile.view',
    'projects.view_department',
    'documents.download',
    'afin.dashboard.view',
    'afin.projects.review',
    'afin.projects.jom_issue',
    'afin.projects.forward_ops',
    'ops.projects.view_handoff',
    'ops.dashboard.view',
    'ops.pr.manage',
    'ops.pr.view_all',
    'opfin.dashboard.view',
    'opfin.pr.review',
    'opfin.pr.approve',
    'opfin.pr.reject',
    'opfin.actual_bom.manage',
    'afin.actual_bom.review',
    'afin.pr.approve',
    'afin.pr.reject',
    'afin.po.manage',
    'afin.po.authorize',
    'afin.po.issue',
    'afin.payment_terms.manage',
    'log.delivery.receive',
    'ops.delivery.inspect',
    'log.stock_in.manage',
    'log.inventory.view'
)
WHERE r.`role_slug` = 'department_user';
