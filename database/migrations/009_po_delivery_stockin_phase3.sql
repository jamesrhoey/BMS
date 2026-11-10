-- Migration 009: Purchase Order (PO), Payment Terms, Supplier Delivery & Stock In Inventory Tracking (Phase 3)
-- BMS Business Management System

-- 1. Suppliers Table
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

-- 2. Purchase Orders Table
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

-- 3. Purchase Order Items Table
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

-- 4. Payment Terms Schedules Table
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

-- 5. Purchase Order Histories Table
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

-- 6. Supplier Deliveries Table
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

-- 7. Supplier Delivery Items Table
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

-- 8. Inventory Items (Catalog)
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

-- 9. Inventory Transactions (Double-Entry Perpetual Ledger)
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

-- 10. Inventory Units (Physical Serialized Equipment)
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

-- 11. Seed Initial Approved Suppliers
INSERT INTO `suppliers` (`supplier_code`, `supplier_name`, `contact_person`, `email`, `phone`, `address`, `tax_identification_number`, `default_payment_terms`, `status`, `created_by`)
SELECT 'SUP-HUAWEI', 'Huawei Technologies Philippines Inc.', 'Enterprise Solutions Team', 'support.ph@huawei.com', '+63 2 8856 7888', 'Taguig City, Metro Manila', '005-234-567-000', '30% DP, 70% Upon Delivery', 'active', u.`id`
FROM `users` u
WHERE u.`username` = 'admin'
ON DUPLICATE KEY UPDATE `supplier_name` = VALUES(`supplier_name`);

INSERT INTO `suppliers` (`supplier_code`, `supplier_name`, `contact_person`, `email`, `phone`, `address`, `tax_identification_number`, `default_payment_terms`, `status`, `created_by`)
SELECT 'SUP-CANADIAN', 'Canadian Solar (SEA) Pte Ltd', 'Regional Sales Desk', 'sales.sea@canadiansolar.com', '+65 6733 2190', 'Singapore / Manila Representative Office', '008-987-654-000', 'Net 30 Days', 'active', u.`id`
FROM `users` u
WHERE u.`username` = 'admin'
ON DUPLICATE KEY UPDATE `supplier_name` = VALUES(`supplier_name`);

INSERT INTO `suppliers` (`supplier_code`, `supplier_name`, `contact_person`, `email`, `phone`, `address`, `tax_identification_number`, `default_payment_terms`, `status`, `created_by`)
SELECT 'SUP-SCHNEIDER', 'Schneider Electric Philippines', 'Industrial Power Team', 'customercare.ph@se.com', '+63 2 8976 8000', 'BGC, Taguig City', '000-432-109-000', 'Net 60 Days', 'active', u.`id`
FROM `users` u
WHERE u.`username` = 'admin'
ON DUPLICATE KEY UPDATE `supplier_name` = VALUES(`supplier_name`);

-- 12. Seed Phase 3 Permissions
INSERT INTO `permissions` (`permission_key`, `permission_name`, `module_group`, `description`) VALUES
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

-- Assign all permissions to Administrator
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.`role_slug` = 'administrator'
  AND p.`permission_key` IN (
      'afin.po.manage',
      'afin.po.authorize',
      'afin.po.issue',
      'afin.payment_terms.manage',
      'log.delivery.receive',
      'ops.delivery.inspect',
      'log.stock_in.manage',
      'log.inventory.view'
  );

-- Assign department user permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
INNER JOIN `permissions` p ON p.`permission_key` IN (
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
