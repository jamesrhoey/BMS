-- =============================================================================
-- Migration 011: PAGECOM IATMS Complete Integration into BMS
-- 
-- Host System: Pagecom Inc. / SYB Business Management System (BMS)
-- Target Database: business_management_system (MariaDB 10.4.32 InnoDB)
-- Charset: utf8mb4 / utf8mb4_unicode_ci
--
-- NON-DESTRUCTIVE ADDITIVE MIGRATION:
-- - Zero DROP TABLE, zero TRUNCATE, zero data loss.
-- - Reuses and extends existing master tables: users, roles, permissions,
--   suppliers, inventory_items, inventory_units, purchase_requests,
--   purchase_orders, activity_logs.
-- - Creates normalized IATMS operational tables: clients, payment_terms,
--   categories, equipment_models, inventory_locations, inventory_stock,
--   inventory_ledger, stock_transactions (+ items/serials),
--   supplier_receipts (+ items/serials), customer_deliveries (+ items/serials/updates),
--   invoices (+ items), collections, warranties (+ claims), settings, daily_status_history.
-- =============================================================================

USE `business_management_system`;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. SYSTEM SETTINGS & CONFIGURATION
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NULL,
    `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
    `description` VARCHAR(255) NULL,
    `is_public` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_settings_key` (`setting_key`),
    KEY `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `description`, `is_public`) VALUES
    ('default_warranty_months', '24', 'warranty', 'Default warranty duration for serialized hardware (24 months default).', 1),
    ('warranty_start_policy', 'actual_delivery_date', 'warranty', 'Approved company policy for warranty start (actual_delivery_date, delivery_date, or custom).', 1),
    ('warranty_expiring_soon_days', '30', 'warranty', 'Threshold in days to alert for expiring soon warranties.', 1),
    ('pr_allow_self_approval', '0', 'procurement', 'Whether a user can approve their own Purchase Request (0 = prohibited, 1 = allowed).', 0),
    ('company_name', 'PAGECOM INC.', 'general', 'Official company name for digitized form printouts and letterheads.', 1),
    ('default_tax_rate', '12.00', 'finance', 'Default VAT / tax rate percentage for invoicing.', 1)
ON DUPLICATE KEY UPDATE
    `description` = VALUES(`description`);

-- -----------------------------------------------------------------------------
-- 2. MASTER DICTIONARIES: PAYMENT TERMS, CLIENTS, CATEGORIES, MODELS, LOCATIONS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_terms` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `days` INT UNSIGNED NOT NULL DEFAULT 0,
    `term_type` ENUM('cod', 'advance', 'net', 'milestone', 'other') NOT NULL DEFAULT 'net',
    `default_reference_date` ENUM('po_date', 'delivery_date', 'invoice_date') NOT NULL DEFAULT 'invoice_date',
    `milestone_breakdown` TEXT NULL,
    `description` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_payment_terms_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payment_terms` (`code`, `name`, `days`, `term_type`, `default_reference_date`, `milestone_breakdown`, `description`, `is_active`) VALUES
    ('COD', 'Cash On Delivery', 0, 'cod', 'delivery_date', NULL, 'Full payment upon physical delivery and inspection of goods.', 1),
    ('ADVANCE', '100% Advance Payment', 0, 'advance', 'po_date', NULL, 'Prepayment required upon issuance of Purchase Order prior to dispatch.', 1),
    ('NET15', 'Net 15 Days', 15, 'net', 'invoice_date', NULL, 'Payment due within 15 calendar days from invoice date.', 1),
    ('NET30', 'Net 30 Days', 30, 'net', 'invoice_date', NULL, 'Payment due within 30 calendar days from invoice date.', 1),
    ('NET60', 'Net 60 Days', 60, 'net', 'invoice_date', NULL, 'Payment due within 60 calendar days from invoice date.', 1),
    ('MILESTONE-30-50-20', '30% Advance, 50% Delivery, 20% Net 30', 30, 'milestone', 'po_date', '[{"name":"Downpayment upon PO","percent":30},{"name":"Upon Delivery","percent":50},{"name":"Retention Net 30","percent":20}]', 'Milestone payment schedule: 30% advance, 50% upon receipt, 20% after 30 days.', 1),
    ('50-50', '50% Advance, 50% Upon Delivery', 0, 'milestone', 'po_date', '[{"name":"50% Advance","percent":50},{"name":"50% Upon Delivery","percent":50}]', '50% upon PO confirmation, balance upon delivery.', 1)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `days` = VALUES(`days`),
    `term_type` = VALUES(`term_type`);

CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_code` VARCHAR(50) NOT NULL,
    `company_name` VARCHAR(255) NOT NULL,
    `contact_person` VARCHAR(100) NULL,
    `email` VARCHAR(255) NULL,
    `phone` VARCHAR(50) NULL,
    `billing_address` TEXT NULL,
    `shipping_address` TEXT NULL,
    `tax_id` VARCHAR(50) NULL,
    `payment_term_id` INT UNSIGNED NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_clients_code` (`client_code`),
    KEY `idx_clients_company` (`company_name`),
    CONSTRAINT `fk_clients_payment_term` FOREIGN KEY (`payment_term_id`) REFERENCES `payment_terms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_categories_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`code`, `name`, `description`, `is_active`) VALUES
    ('SERIAL-EQ', 'Serialized Equipment', 'Tracked hardware assets with unique serial numbers.', 1),
    ('POWER-SOLAR', 'Solar & Power Systems', 'Inverters, solar panels, batteries, and power equipment.', 1),
    ('NETWORKING', 'Networking & Communications', 'Switches, routers, antennas, and telecom modules.', 1),
    ('CONSUMABLES', 'Bulk Materials & Consumables', 'Cables, connectors, mounting hardware, and bulk supplies.', 1)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`);

CREATE TABLE IF NOT EXISTS `equipment_models` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `brand` VARCHAR(100) NOT NULL,
    `model_name` VARCHAR(100) NOT NULL,
    `model_number` VARCHAR(100) NOT NULL,
    `specifications` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_brand_model` (`brand`, `model_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_locations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_inv_loc_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `inventory_locations` (`id`, `code`, `name`, `description`, `is_active`) VALUES
    (1, 'MAIN-WH', 'PCT Warehouse', 'Primary central inventory storage and receiving dock.', 1),
    (2, 'JOBSITE', 'Jobsite Staging', 'Temporary staging for project field deployment.', 1);

-- -----------------------------------------------------------------------------
-- 3. EXTEND EXISTING BMS MASTER TABLES (ADDITIVE ONLY, ZERO DATA LOSS)
-- -----------------------------------------------------------------------------

-- 3.1 Extend `suppliers`
ALTER TABLE `suppliers`
    ADD COLUMN IF NOT EXISTS `payment_term_id` INT UNSIGNED NULL AFTER `default_payment_terms`,
    ADD INDEX IF NOT EXISTS `idx_suppliers_payment_term` (`payment_term_id`);

-- 3.2 Extend `inventory_items` (Unified Product Catalog)
ALTER TABLE `inventory_items`
    ADD COLUMN IF NOT EXISTS `sku` VARCHAR(100) NULL AFTER `item_code`,
    ADD COLUMN IF NOT EXISTS `category_id` INT UNSIGNED NULL AFTER `category`,
    ADD COLUMN IF NOT EXISTS `model_id` INT UNSIGNED NULL AFTER `model_part_number`,
    ADD COLUMN IF NOT EXISTS `default_warranty_months` INT UNSIGNED NOT NULL DEFAULT 24 AFTER `is_serialized`,
    ADD COLUMN IF NOT EXISTS `reorder_level` INT UNSIGNED NOT NULL DEFAULT 5 AFTER `default_warranty_months`,
    ADD COLUMN IF NOT EXISTS `reorder_quantity` INT UNSIGNED NOT NULL DEFAULT 10 AFTER `reorder_level`,
    ADD COLUMN IF NOT EXISTS `unit_cost` DECIMAL(14, 2) NOT NULL DEFAULT 0.00 AFTER `reorder_quantity`,
    ADD COLUMN IF NOT EXISTS `unit_price` DECIMAL(14, 2) NOT NULL DEFAULT 0.00 AFTER `unit_cost`,
    ADD COLUMN IF NOT EXISTS `remarks` TEXT NULL AFTER `description`,
    ADD INDEX IF NOT EXISTS `idx_ii_sku` (`sku`),
    ADD INDEX IF NOT EXISTS `idx_ii_category_id` (`category_id`),
    ADD INDEX IF NOT EXISTS `idx_ii_model_id` (`model_id`);

UPDATE `inventory_items` SET `sku` = `item_code` WHERE `sku` IS NULL OR `sku` = '';

-- 3.3 Extend `purchase_orders` & `purchase_order_items` & `purchase_request_items`
ALTER TABLE `purchase_orders`
    ADD COLUMN IF NOT EXISTS `payment_term_id` INT UNSIGNED NULL AFTER `payment_terms_summary`,
    ADD COLUMN IF NOT EXISTS `payment_reference_date_type` VARCHAR(30) NOT NULL DEFAULT 'po_date' AFTER `payment_term_id`,
    ADD COLUMN IF NOT EXISTS `payment_due_date` DATE NULL AFTER `payment_reference_date_type`,
    ADD INDEX IF NOT EXISTS `idx_po_payment_term` (`payment_term_id`);

ALTER TABLE `purchase_order_items`
    ADD COLUMN IF NOT EXISTS `inventory_item_id` INT UNSIGNED NULL AFTER `pr_item_id`,
    ADD INDEX IF NOT EXISTS `idx_poi_inv_item` (`inventory_item_id`);

ALTER TABLE `purchase_request_items`
    ADD COLUMN IF NOT EXISTS `inventory_item_id` INT UNSIGNED NULL AFTER `purchase_request_id`,
    ADD INDEX IF NOT EXISTS `idx_pri_inv_item` (`inventory_item_id`);

-- 3.4 Extend `activity_logs` (Immutable Audit Trail with JSON Before/After Snapshots)
ALTER TABLE `activity_logs`
    ADD COLUMN IF NOT EXISTS `old_values` LONGTEXT NULL AFTER `description`,
    ADD COLUMN IF NOT EXISTS `new_values` LONGTEXT NULL AFTER `old_values`,
    ADD COLUMN IF NOT EXISTS `user_agent` VARCHAR(255) NULL AFTER `ip_address`;

-- -----------------------------------------------------------------------------
-- 4. CACHED LOCATION STOCK & IMMUTABLE DOUBLE-ENTRY LEDGER
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_stock` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `inventory_item_id` INT UNSIGNED NOT NULL,
    `location_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `quantity_on_hand` INT NOT NULL DEFAULT 0,
    `quantity_reserved` INT NOT NULL DEFAULT 0,
    `quantity_available` INT NOT NULL DEFAULT 0,
    `last_reconciled_at` DATETIME NULL,
    `remarks` TEXT NULL COMMENT 'Persistent inline-editable remarks for Paper Form 3 Ending Balance',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_stock_item_location` (`inventory_item_id`, `location_id`),
    CONSTRAINT `fk_stock_inv_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_stock_location` FOREIGN KEY (`location_id`) REFERENCES `inventory_locations` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_ledger` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `inventory_item_id` INT UNSIGNED NOT NULL,
    `location_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `transaction_type` VARCHAR(30) NOT NULL COMMENT 'STOCK_IN, STOCK_OUT, ADJUSTMENT, RETURN, REVERSAL, MAINTENANCE_OUT, MAINTENANCE_IN',
    `reference_type` VARCHAR(60) NOT NULL COMMENT 'SUPPLIER_RECEIPT, CUSTOMER_DELIVERY, ADJUSTMENT, MAINTENANCE, REVERSAL',
    `reference_id` INT UNSIGNED NULL,
    `quantity_in` INT NOT NULL DEFAULT 0,
    `quantity_out` INT NOT NULL DEFAULT 0,
    `balance_after` INT NOT NULL,
    `unit_cost` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `performed_by_id` INT UNSIGNED NULL,
    `notes` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ledger_item_loc` (`inventory_item_id`, `location_id`),
    KEY `idx_ledger_ref` (`reference_type`, `reference_id`),
    KEY `idx_ledger_created` (`created_at`),
    CONSTRAINT `fk_ledger_inv_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_ledger_location` FOREIGN KEY (`location_id`) REFERENCES `inventory_locations` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_ledger_user` FOREIGN KEY (`performed_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_transactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_number` VARCHAR(60) NOT NULL,
    `transaction_type` VARCHAR(30) NOT NULL COMMENT 'STOCK_IN, STOCK_OUT, ADJUSTMENT, MAINTENANCE',
    `reference_type` VARCHAR(60) NOT NULL COMMENT 'SUPPLIER_RECEIPT, CUSTOMER_DELIVERY, ADJUSTMENT, CLIENT_RETURN, MAINTENANCE',
    `reference_id` INT UNSIGNED NULL,
    `source_location_id` INT UNSIGNED NULL,
    `destination_location_id` INT UNSIGNED NULL,
    `performed_by_id` INT UNSIGNED NOT NULL,
    `transaction_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_st_txn_number` (`transaction_number`),
    KEY `idx_st_type` (`transaction_type`),
    KEY `idx_st_ref` (`reference_type`, `reference_id`),
    KEY `idx_st_date` (`transaction_date`),
    CONSTRAINT `fk_st_performed_by` FOREIGN KEY (`performed_by_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_st_source_loc` FOREIGN KEY (`source_location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_st_dest_loc` FOREIGN KEY (`destination_location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_transaction_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_id` INT UNSIGNED NOT NULL,
    `inventory_item_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `unit_cost` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sti_tx` (`transaction_id`),
    CONSTRAINT `fk_sti_tx` FOREIGN KEY (`transaction_id`) REFERENCES `stock_transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sti_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_transaction_serials` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_item_id` INT UNSIGNED NOT NULL,
    `unit_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sts_item` (`transaction_item_id`),
    KEY `idx_sts_unit` (`unit_id`),
    CONSTRAINT `fk_sts_item` FOREIGN KEY (`transaction_item_id`) REFERENCES `stock_transaction_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sts_unit` FOREIGN KEY (`unit_id`) REFERENCES `inventory_units` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. EXTEND `inventory_units` FOR FULL IATMS SERIALIZED UNIT LIFECYCLE
-- -----------------------------------------------------------------------------
ALTER TABLE `inventory_units`
    MODIFY COLUMN `po_number` VARCHAR(100) NULL,
    MODIFY COLUMN `po_date` DATE NULL,
    MODIFY COLUMN `purchase_order_id` INT UNSIGNED NULL,
    MODIFY COLUMN `delivery_id` INT UNSIGNED NULL,
    MODIFY COLUMN `stock_in_transaction_id` BIGINT UNSIGNED NULL,
    MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'in_stock',
    ADD COLUMN IF NOT EXISTS `barcode` VARCHAR(100) NULL AFTER `serial_number`,
    ADD COLUMN IF NOT EXISTS `location_id` INT UNSIGNED NULL DEFAULT 1 AFTER `current_location`,
    ADD COLUMN IF NOT EXISTS `supplier_id` INT UNSIGNED NULL AFTER `location_id`,
    ADD COLUMN IF NOT EXISTS `client_id` INT UNSIGNED NULL AFTER `supplier_id`,
    ADD COLUMN IF NOT EXISTS `supplier_receipt_id` INT UNSIGNED NULL AFTER `delivery_id`,
    ADD COLUMN IF NOT EXISTS `customer_delivery_id` INT UNSIGNED NULL AFTER `supplier_receipt_id`,
    ADD COLUMN IF NOT EXISTS `po_reference` VARCHAR(100) NULL AFTER `po_number`,
    ADD COLUMN IF NOT EXISTS `dr_reference` VARCHAR(100) NULL AFTER `customer_delivery_id`,
    ADD COLUMN IF NOT EXISTS `invoice_number` VARCHAR(100) NULL AFTER `dr_reference`,
    ADD COLUMN IF NOT EXISTS `unit_cost` DECIMAL(14, 2) NOT NULL DEFAULT 0.00 AFTER `in_qty`,
    ADD COLUMN IF NOT EXISTS `stock_in_date` DATE NULL AFTER `inventory_date`,
    ADD COLUMN IF NOT EXISTS `stock_out_date` DATE NULL AFTER `stock_in_date`,
    ADD COLUMN IF NOT EXISTS `warranty_start_date` DATE NULL AFTER `warranty_duration`,
    ADD COLUMN IF NOT EXISTS `warranty_expiry_date` DATE NULL AFTER `warranty_start_date`,
    ADD COLUMN IF NOT EXISTS `notes` TEXT NULL AFTER `warranty_expiry_date`,
    ADD INDEX IF NOT EXISTS `idx_iu_item_status` (`inventory_item_id`, `status`),
    ADD INDEX IF NOT EXISTS `idx_iu_location_id` (`location_id`),
    ADD INDEX IF NOT EXISTS `idx_iu_client_id` (`client_id`),
    ADD INDEX IF NOT EXISTS `idx_iu_supplier_receipt` (`supplier_receipt_id`),
    ADD INDEX IF NOT EXISTS `idx_iu_customer_delivery` (`customer_delivery_id`),
    ADD INDEX IF NOT EXISTS `idx_iu_po_ref` (`po_reference`),
    ADD INDEX IF NOT EXISTS `idx_iu_dr_ref` (`dr_reference`),
    ADD INDEX IF NOT EXISTS `idx_iu_stock_in_date` (`stock_in_date`),
    ADD INDEX IF NOT EXISTS `idx_iu_stock_out_date` (`stock_out_date`);

-- -----------------------------------------------------------------------------
-- 6. DUAL-MODE STOCK IN / EQUIPMENT RECEIVING (DIGITIZED PAPER FORM 1)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supplier_receipts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `receipt_number` VARCHAR(60) NOT NULL,
    `po_id` INT UNSIGNED NULL COMMENT 'NULL for Mode A Direct Stock In (Paper Form 1); populated for Mode B PO Receiving',
    `po_number` VARCHAR(100) NULL COMMENT 'String preserving leading zeros e.g. 000398',
    `po_date` DATE NULL,
    `supplier_id` INT UNSIGNED NULL,
    `supplier_name` VARCHAR(255) NULL COMMENT 'Ad-hoc vendor name when supplier_id is NULL',
    `received_by_id` INT UNSIGNED NOT NULL,
    `destination_location_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `receipt_date` DATE NOT NULL,
    `delivery_to_pct_date` DATE NULL,
    `carrier` VARCHAR(100) NULL,
    `waybill_number` VARCHAR(100) NULL,
    `invoice_reference` VARCHAR(100) NULL,
    `remarks` TEXT NULL,
    `status` ENUM('received', 'inspected', 'cancelled') NOT NULL DEFAULT 'received',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sr_receipt_number` (`receipt_number`),
    KEY `idx_sr_po` (`po_id`),
    KEY `idx_sr_po_number` (`po_number`),
    KEY `idx_sr_supplier` (`supplier_id`),
    KEY `idx_sr_date` (`receipt_date`),
    KEY `idx_sr_status` (`status`),
    CONSTRAINT `fk_sr_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sr_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sr_received_by` FOREIGN KEY (`received_by_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_sr_location` FOREIGN KEY (`destination_location_id`) REFERENCES `inventory_locations` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supplier_receipt_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `receipt_id` INT UNSIGNED NOT NULL,
    `po_item_id` INT UNSIGNED NULL,
    `inventory_item_id` INT UNSIGNED NOT NULL,
    `quantity_received` INT UNSIGNED NOT NULL,
    `unit_cost` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `item_condition` VARCHAR(50) NOT NULL DEFAULT 'Brand New',
    `warranty_duration_months` INT UNSIGNED NULL DEFAULT 24,
    `warranty_start_date` DATE NULL,
    `warranty_expiry_date` DATE NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sri_receipt` (`receipt_id`),
    KEY `idx_sri_po_item` (`po_item_id`),
    KEY `idx_sri_item` (`inventory_item_id`),
    CONSTRAINT `fk_sri_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `supplier_receipts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sri_po_item` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sri_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supplier_receipt_serials` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `receipt_item_id` INT UNSIGNED NOT NULL,
    `unit_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_srs_unit` (`unit_id`),
    KEY `idx_srs_item` (`receipt_item_id`),
    CONSTRAINT `fk_srs_item` FOREIGN KEY (`receipt_item_id`) REFERENCES `supplier_receipt_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_srs_unit` FOREIGN KEY (`unit_id`) REFERENCES `inventory_units` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. OUTBOUND STOCK OUT & CUSTOMER DELIVERIES (DIGITIZED PAPER FORM 2)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_deliveries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `delivery_number` VARCHAR(60) NOT NULL,
    `stock_out_reference` VARCHAR(60) NULL,
    `dr_number` VARCHAR(100) NULL COMMENT 'Unique Delivery Receipt number preserving leading zeros',
    `invoice_number` VARCHAR(100) NULL,
    `customer_po_number` VARCHAR(100) NULL,
    `pr_reference` VARCHAR(100) NULL,
    `project_id` INT UNSIGNED NULL,
    `client_id` INT UNSIGNED NULL,
    `client_name` VARCHAR(255) NULL,
    `contact_person` VARCHAR(100) NULL,
    `contact_phone` VARCHAR(50) NULL,
    `contact_email` VARCHAR(100) NULL,
    `source_location_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `prepared_by_id` INT UNSIGNED NOT NULL,
    `sales_order_number` VARCHAR(60) NULL,
    `delivery_date` DATE NOT NULL,
    `planned_delivery_date` DATE NULL,
    `actual_delivery_date` DATE NULL,
    `carrier` VARCHAR(100) NULL,
    `tracking_number` VARCHAR(100) NULL,
    `shipping_address` TEXT NOT NULL,
    `received_by_client_name` VARCHAR(100) NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'dispatched',
    `remarks` TEXT NULL,
    `cancellation_reason` TEXT NULL,
    `delivery_confirmation_notes` TEXT NULL,
    `cancelled_at` DATETIME NULL,
    `cancelled_by_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_cd_delivery_number` (`delivery_number`),
    UNIQUE KEY `uk_cd_dr_number` (`dr_number`),
    KEY `idx_cd_stock_out_ref` (`stock_out_reference`),
    KEY `idx_cd_invoice_number` (`invoice_number`),
    KEY `idx_cd_customer_po` (`customer_po_number`),
    KEY `idx_cd_pr_ref` (`pr_reference`),
    KEY `idx_cd_client` (`client_id`),
    KEY `idx_cd_project` (`project_id`),
    KEY `idx_cd_date` (`delivery_date`),
    KEY `idx_cd_status` (`status`),
    CONSTRAINT `fk_cd_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_cd_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_cd_source_loc` FOREIGN KEY (`source_location_id`) REFERENCES `inventory_locations` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_cd_prepared_by` FOREIGN KEY (`prepared_by_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_cd_cancelled_by` FOREIGN KEY (`cancelled_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_delivery_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `delivery_id` INT UNSIGNED NOT NULL,
    `inventory_item_id` INT UNSIGNED NOT NULL,
    `quantity_delivered` INT UNSIGNED NOT NULL,
    `unit_price` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `warranty_duration_months` INT UNSIGNED NULL DEFAULT 24,
    `warranty_start_date` DATE NULL,
    `warranty_expiry_date` DATE NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cdi_delivery` (`delivery_id`),
    KEY `idx_cdi_item` (`inventory_item_id`),
    CONSTRAINT `fk_cdi_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `customer_deliveries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cdi_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_delivery_serials` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `delivery_item_id` INT UNSIGNED NOT NULL,
    `unit_id` INT UNSIGNED NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = currently issued on this delivery; 0 = returned/cancelled',
    `returned_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cds_item` (`delivery_item_id`),
    KEY `idx_cds_unit` (`unit_id`),
    KEY `idx_cds_unit_active` (`unit_id`, `is_active`),
    CONSTRAINT `fk_cds_item` FOREIGN KEY (`delivery_item_id`) REFERENCES `customer_delivery_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cds_unit` FOREIGN KEY (`unit_id`) REFERENCES `inventory_units` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_delivery_updates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `delivery_id` INT UNSIGNED NOT NULL,
    `previous_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NOT NULL,
    `remarks` TEXT NULL,
    `created_by_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cdu_delivery` (`delivery_id`),
    KEY `idx_cdu_created` (`created_at`),
    CONSTRAINT `fk_cdu_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `customer_deliveries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cdu_user` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. INVOICES & PAYMENT COLLECTIONS (ACCOUNTS RECEIVABLE & PAYABLE)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(60) NOT NULL,
    `invoice_type` VARCHAR(20) NOT NULL DEFAULT 'receivable' COMMENT 'receivable or payable',
    `reference_number` VARCHAR(100) NULL,
    `client_id` INT UNSIGNED NULL,
    `supplier_id` INT UNSIGNED NULL,
    `delivery_id` INT UNSIGNED NULL,
    `po_id` INT UNSIGNED NULL,
    `receipt_id` INT UNSIGNED NULL,
    `payment_term_id` INT UNSIGNED NULL,
    `created_by_id` INT UNSIGNED NOT NULL,
    `invoice_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `subtotal` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'PHP',
    `tax_rate` DECIMAL(5, 2) NOT NULL DEFAULT 12.00,
    `tax_amount` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `balance_due` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `status` VARCHAR(30) NOT NULL DEFAULT 'unpaid',
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_invoices_number` (`invoice_number`),
    KEY `idx_inv_type` (`invoice_type`),
    KEY `idx_inv_client` (`client_id`),
    KEY `idx_inv_supplier` (`supplier_id`),
    KEY `idx_inv_delivery` (`delivery_id`),
    KEY `idx_inv_po` (`po_id`),
    KEY `idx_inv_receipt` (`receipt_id`),
    KEY `idx_inv_status` (`status`),
    KEY `idx_inv_due` (`due_date`),
    CONSTRAINT `fk_inv_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_inv_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_inv_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `customer_deliveries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_inv_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_inv_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `supplier_receipts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_inv_payment_term` FOREIGN KEY (`payment_term_id`) REFERENCES `payment_terms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_inv_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id` INT UNSIGNED NOT NULL,
    `delivery_item_id` INT UNSIGNED NULL,
    `po_item_id` INT UNSIGNED NULL,
    `receipt_item_id` INT UNSIGNED NULL,
    `inventory_item_id` INT UNSIGNED NULL,
    `description` VARCHAR(255) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `unit_price` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `total_price` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_invi_invoice` (`invoice_id`),
    CONSTRAINT `fk_invi_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_invi_delivery_item` FOREIGN KEY (`delivery_item_id`) REFERENCES `customer_delivery_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invi_po_item` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invi_receipt_item` FOREIGN KEY (`receipt_item_id`) REFERENCES `supplier_receipt_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invi_inv_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `collections` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `collection_number` VARCHAR(60) NOT NULL,
    `payment_type` VARCHAR(20) NOT NULL DEFAULT 'collection' COMMENT 'collection or disbursement',
    `invoice_id` INT UNSIGNED NOT NULL,
    `client_id` INT UNSIGNED NULL,
    `supplier_id` INT UNSIGNED NULL,
    `recorded_by_id` INT UNSIGNED NOT NULL,
    `collection_date` DATE NOT NULL,
    `payment_method` ENUM('cash', 'check', 'bank_transfer', 'credit_card', 'other') NOT NULL DEFAULT 'bank_transfer',
    `reference_number` VARCHAR(100) NULL,
    `amount_received` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'PHP',
    `deposited_to_account` VARCHAR(100) NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'cleared' COMMENT 'cleared, pending, bounced, voided',
    `void_reason` TEXT NULL,
    `voided_by_id` INT UNSIGNED NULL,
    `voided_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_collections_number` (`collection_number`),
    KEY `idx_coll_invoice` (`invoice_id`),
    KEY `idx_coll_client` (`client_id`),
    KEY `idx_coll_supplier` (`supplier_id`),
    KEY `idx_coll_date` (`collection_date`),
    KEY `idx_coll_status` (`status`),
    CONSTRAINT `fk_coll_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_coll_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_coll_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_coll_user` FOREIGN KEY (`recorded_by_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_coll_voided_by` FOREIGN KEY (`voided_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. WARRANTY LIFECYCLE, CLAIMS & DAILY METRICS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `warranties` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `warranty_code` VARCHAR(60) NOT NULL,
    `unit_id` INT UNSIGNED NOT NULL,
    `inventory_item_id` INT UNSIGNED NOT NULL,
    `client_id` INT UNSIGNED NULL,
    `delivery_id` INT UNSIGNED NULL,
    `invoice_id` INT UNSIGNED NULL,
    `start_date` DATE NULL,
    `duration_months` INT UNSIGNED NOT NULL DEFAULT 24,
    `end_date` DATE NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'incomplete_unknown',
    `terms` TEXT NULL,
    `remarks` TEXT NULL,
    `policy_applied` VARCHAR(100) NULL,
    `last_adjusted_by_id` INT UNSIGNED NULL,
    `last_adjustment_reason` TEXT NULL,
    `last_adjusted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_warranties_code` (`warranty_code`),
    KEY `idx_war_unit` (`unit_id`),
    KEY `idx_war_item` (`inventory_item_id`),
    KEY `idx_war_client` (`client_id`),
    KEY `idx_war_status` (`status`),
    KEY `idx_war_end` (`end_date`),
    CONSTRAINT `fk_war_unit` FOREIGN KEY (`unit_id`) REFERENCES `inventory_units` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_war_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_war_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_war_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `customer_deliveries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_war_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_war_adjusted_by` FOREIGN KEY (`last_adjusted_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `warranty_claims` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `claim_number` VARCHAR(60) NOT NULL,
    `warranty_id` INT UNSIGNED NOT NULL,
    `unit_id` INT UNSIGNED NOT NULL,
    `client_id` INT UNSIGNED NULL,
    `handled_by_id` INT UNSIGNED NOT NULL,
    `replacement_unit_id` INT UNSIGNED NULL,
    `claim_date` DATE NOT NULL,
    `issue_description` TEXT NOT NULL,
    `resolution_type` ENUM('repair', 'replacement', 'refund', 'rejected', 'pending') NOT NULL DEFAULT 'pending',
    `resolution_notes` TEXT NULL,
    `status` ENUM('received', 'evaluating', 'approved', 'in_progress', 'resolved', 'rejected') NOT NULL DEFAULT 'received',
    `resolved_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_wc_claim_number` (`claim_number`),
    KEY `idx_wc_warranty` (`warranty_id`),
    KEY `idx_wc_unit` (`unit_id`),
    KEY `idx_wc_client` (`client_id`),
    KEY `idx_wc_status` (`status`),
    CONSTRAINT `fk_wc_warranty` FOREIGN KEY (`warranty_id`) REFERENCES `warranties` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_wc_unit` FOREIGN KEY (`unit_id`) REFERENCES `inventory_units` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_wc_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_wc_handled_by` FOREIGN KEY (`handled_by_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_wc_rep_unit` FOREIGN KEY (`replacement_unit_id`) REFERENCES `inventory_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `daily_status_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `snapshot_date` DATE NOT NULL,
    `metric_category` ENUM('inventory', 'procurement', 'sales', 'financial') NOT NULL,
    `metric_key` VARCHAR(100) NOT NULL,
    `metric_value_int` BIGINT NULL,
    `metric_value_decimal` DECIMAL(16, 2) NULL,
    `details_json` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_daily_snapshot` (`snapshot_date`, `metric_category`, `metric_key`),
    KEY `idx_dsh_date` (`snapshot_date`),
    KEY `idx_dsh_cat` (`metric_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. INITIALIZE `inventory_stock` FOR ANY EXISTING `inventory_items`
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `inventory_stock` (`inventory_item_id`, `location_id`, `quantity_on_hand`, `quantity_reserved`, `quantity_available`, `last_reconciled_at`)
SELECT `id`, 1, FLOOR(`current_balance`), 0, FLOOR(`current_balance`), NOW()
FROM `inventory_items`;

-- -----------------------------------------------------------------------------
-- 11. SEED IATMS PERMISSIONS INTO BMS RBAC
-- -----------------------------------------------------------------------------
INSERT INTO `permissions` (`permission_key`, `permission_name`, `module_group`, `description`) VALUES
    ('iatms.products.view', 'View Product Catalog', 'Inventory & Assets', 'View equipment models, SKUs, and product catalog.'),
    ('iatms.products.manage', 'Manage Product Catalog', 'Inventory & Assets', 'Create and update product catalog items and categories.'),
    ('iatms.stock_in.view', 'View Stock In Receipts (Form 1)', 'Inventory & Assets', 'View supplier receiving receipts and captured serials.'),
    ('iatms.stock_in.create', 'Record Stock In (Form 1)', 'Inventory & Assets', 'Record direct or PO-linked equipment receiving and serial intake.'),
    ('iatms.stock_out.view', 'View Stock Out & Deliveries (Form 2)', 'Inventory & Assets', 'View outbound dispatches, Delivery Receipts, and transit status.'),
    ('iatms.stock_out.create', 'Record Stock Out (Form 2)', 'Inventory & Assets', 'Issue in-stock serialized units to clients and generate Delivery Receipts.'),
    ('iatms.stock_out.manage', 'Manage Deliveries & Reversals', 'Inventory & Assets', 'Update delivery status, confirm receipt, or reverse/cancel dispatches.'),
    ('iatms.ending_balance.view', 'View Ending Balance (Form 3)', 'Inventory & Assets', 'View Ending Balance report and physical stock counts.'),
    ('iatms.ending_balance.manage', 'Edit Remarks & Stock Adjustments', 'Inventory & Assets', 'Edit persistent Form 3 remarks and perform authorized stock adjustments.'),
    ('iatms.units.maintenance', 'Manage Unit Maintenance / RMA', 'Inventory & Assets', 'Transition serialized hardware units to/from under_repair or scrapped.'),
    ('iatms.invoices.view', 'View Invoices', 'Billing & Collections', 'View customer receivable invoices and supplier payable bills.'),
    ('iatms.invoices.manage', 'Manage Invoices', 'Billing & Collections', 'Create, issue, and cancel receivable and payable invoices.'),
    ('iatms.collections.view', 'View Payment Collections', 'Billing & Collections', 'View payment collections and settlement history.'),
    ('iatms.collections.manage', 'Record & Void Collections', 'Billing & Collections', 'Record customer payments and void erroneous collections.'),
    ('iatms.warranties.view', 'View Warranties & Claims', 'Warranty & Assets', 'View 24-month warranty certificates, expirations, and claims.'),
    ('iatms.warranties.manage', 'Manage & Adjust Warranties', 'Warranty & Assets', 'Perform authorized warranty date adjustments and handle RMA claims.'),
    ('iatms.reports.view', 'View & Export Paper-Form Reports', 'Reports & Audits', 'Filter, export (CSV/Excel), and print digitized Form 1, 2, 3 and analytical reports.'),
    ('iatms.integrity.view', 'Run 11-Rule Data Integrity Diagnostics', 'Administration', 'Execute data integrity checks and balance reconciliation.')
ON DUPLICATE KEY UPDATE
    `permission_name` = VALUES(`permission_name`),
    `module_group` = VALUES(`module_group`),
    `description` = VALUES(`description`);

-- Grant ALL IATMS permissions to Administrator role
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.`role_slug` = 'administrator'
  AND p.`permission_key` LIKE 'iatms.%';

-- Grant ONLY baseline read/view permissions to generic `department_user` in SQL.
-- Granular departmental write/authorize/dispatch controls are strictly enforced in PHP AuthorizationService.
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
INNER JOIN `permissions` p ON p.`permission_key` IN (
    'iatms.products.view',
    'iatms.stock_in.view',
    'iatms.stock_out.view',
    'iatms.ending_balance.view',
    'iatms.warranties.view',
    'iatms.reports.view'
)
WHERE r.`role_slug` = 'department_user';

SET FOREIGN_KEY_CHECKS = 1;
