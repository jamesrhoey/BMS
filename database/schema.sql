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
    ('pm.projects.jom_issue', 'Issue Project JOM Number', 'Project Management', 'Authorizes issuing official Job Order Memorandum (JOM) numbers to verified projects.')
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
    'documents.download'
)
WHERE r.`role_slug` = 'department_user';
