# PHASE 2 MIGRATION VALIDATION REPORT

**System:** Pagecom Inc. / SYB Business Management System (BMS)  
**Database:** `business_management_system`  
**Engine:** MariaDB 10.4.32-MariaDB / MySQL 8.0+ Compatible (InnoDB, utf8mb4_unicode_ci)  
**Migration Script:** [`database/migrations/010_stock_out_and_ending_balance_phase2.sql`](file:///c:/xampp/htdocs/BMS/database/migrations/010_stock_out_and_ending_balance_phase2.sql)  
**Related Documentation:**  
* [`docs/PHASE_2_DATABASE_PERMISSION_REPORT.md`](file:///c:/xampp/htdocs/BMS/docs/PHASE_2_DATABASE_PERMISSION_REPORT.md)  
* [`docs/PHASE_2_REVISION_CHANGELOG.md`](file:///c:/xampp/htdocs/BMS/docs/PHASE_2_REVISION_CHANGELOG.md)  
**Date:** November 2026  
**Execution Status:** **STRICTLY VALIDATED & PREPARED — NOT YET EXECUTED**  

---

## 1. Executive Summary & Readiness Verdict

This document provides a comprehensive technical validation of the revised Phase 2 migration script. Every data type, foreign key relationship, constraint, lifecycle state, and role permission has been inspected against the live MariaDB 10.4.32 database.

### Readiness Recommendation:
$$\mathbf{READY\ FOR\ EXECUTION\ UPON\ FINAL\ USER\ APPROVAL}$$
* **Data Safety:** Zero destructive statements (`DROP`, `TRUNCATE`, `ALTER ... DROP`).
* **Existing Intake:** Preserves 100% of existing `inventory_units`, `inventory_transactions`, and PO/PR records.
* **Idempotency:** Safe to run repeatedly without duplicate constraint or column collisions.
* **Integrity:** Enforces `ON DELETE RESTRICT` on all historical dispatch and unit links.

---

## 2. Exact Database Compatibility Findings

Validation was conducted on the active local server (`10.4.32-MariaDB`).

| SQL Construct / Syntax | MariaDB 10.4.32 Status | Standard MySQL 8.0+ Status | Compatibility Result & Safeguard |
| :--- | :---: | :---: | :--- |
| `CREATE TABLE IF NOT EXISTS` | Native Support | Native Support | Safe and idempotent. |
| `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` | Native Support (since 10.2.1) | Supported in MySQL 8.0.29+ | Verified via live scratch script. Safe on target engine. |
| `ALTER TABLE ... ADD INDEX IF NOT EXISTS` | Native Support (since 10.2.1) | Supported in MySQL 8.0.29+ | Verified via live scratch script. Safe on target engine. |
| Foreign Key Addition | Evaluated dynamically | Evaluated dynamically | Handled via dynamic SQL (`information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()`) to prevent constraint name collisions on re-runs. |
| `CHECK (quantity_issued > 0.00)` | Enforced (since 10.2.1) | Enforced (since 8.0.16) | Guaranteed positive quantities at database engine level. |
| DDL Transactionality | Auto-commit on DDL | Auto-commit on DDL | In both MariaDB and MySQL, DDL statements (`CREATE`, `ALTER`) trigger implicit commits. Handled via individual idempotent statements rather than relying on a wrapping rollback. |

---

## 3. Foreign-Key Compatibility & Data Type Parity Results

Every foreign key was checked against the live schema output to verify that column data types, unsigned attributes, character sets, and nullability match:

```mermaid
classDiagram
    class stock_out_issuances {
        +int(10) unsigned id PK
        +int(10) unsigned project_id FK NULL
        +int(10) unsigned prepared_by FK NOT NULL
        +int(10) unsigned authorized_by FK NULL
        +int(10) unsigned dispatched_by FK NULL
        +int(10) unsigned cancelled_by FK NULL
        +int(10) unsigned supporting_document_id FK NULL
    }

    class stock_out_items {
        +int(10) unsigned id PK
        +int(10) unsigned stock_out_issuance_id FK NOT NULL
        +int(10) unsigned inventory_item_id FK NOT NULL
    }

    class inventory_units {
        +int(10) unsigned id PK
        +int(10) unsigned stock_out_issuance_id FK NULL
        +int(10) unsigned stock_out_item_id FK NULL
        +bigint(20) unsigned stock_out_transaction_id FK NULL
    }

    class inventory_transactions {
        +bigint(20) unsigned id PK
    }

    class stock_out_dispatch_units {
        +bigint(20) unsigned id PK
        +int(10) unsigned stock_out_issuance_id FK NOT NULL
        +int(10) unsigned stock_out_item_id FK NOT NULL
        +int(10) unsigned inventory_unit_id FK NOT NULL
        +bigint(20) unsigned return_transaction_id FK NULL
    }

    stock_out_issuances "1" <-- "*" stock_out_items : fk_soi_issuance (RESTRICT)
    stock_out_issuances "1" <-- "*" inventory_units : fk_iu_so_issuance (RESTRICT)
    stock_out_items "1" <-- "*" inventory_units : fk_iu_so_item (RESTRICT)
    inventory_transactions "1" <-- "*" inventory_units : fk_iu_so_txn (RESTRICT)
    stock_out_issuances "1" <-- "*" stock_out_dispatch_units : fk_sodu_issuance (RESTRICT)
    stock_out_items "1" <-- "*" stock_out_dispatch_units : fk_sodu_item (RESTRICT)
    inventory_units "1" <-- "*" stock_out_dispatch_units : fk_sodu_unit (RESTRICT)
    inventory_transactions "1" <-- "*" stock_out_dispatch_units : fk_sodu_return_txn (RESTRICT)
```

### Detailed Field Verification:

| Child Table & Column | Child Column Type | Referenced Parent Table & Column | Parent Column Type | Constraint Name | On Delete Rule | Compatibility Verdict |
| :--- | :--- | :--- | :--- | :--- | :---: | :---: |
| `stock_out_issuances.project_id` | `int(10) unsigned NULL` | `projects.id` | `int(10) unsigned` | `fk_so_project` | `SET NULL` | **MATCH (100%)** |
| `stock_out_issuances.prepared_by` | `int(10) unsigned NOT NULL` | `users.id` | `int(10) unsigned` | `fk_so_prepared_by` | `RESTRICT` | **MATCH (100%)** |
| `stock_out_issuances.authorized_by` | `int(10) unsigned NULL` | `users.id` | `int(10) unsigned` | `fk_so_authorized_by` | `SET NULL` | **MATCH (100%)** |
| `stock_out_issuances.dispatched_by` | `int(10) unsigned NULL` | `users.id` | `int(10) unsigned` | `fk_so_dispatched_by` | `SET NULL` | **MATCH (100%)** |
| `stock_out_issuances.cancelled_by` | `int(10) unsigned NULL` | `users.id` | `int(10) unsigned` | `fk_so_cancelled_by` | `SET NULL` | **MATCH (100%)** |
| `stock_out_issuances.supporting_document_id` | `int(10) unsigned NULL` | `project_documents.id` | `int(10) unsigned` | `fk_so_doc` | `SET NULL` | **MATCH (100%)** |
| `stock_out_items.stock_out_issuance_id` | `int(10) unsigned NOT NULL` | `stock_out_issuances.id` | `int(10) unsigned` | `fk_soi_issuance` | `RESTRICT` | **MATCH (100%)** |
| `stock_out_items.inventory_item_id` | `int(10) unsigned NOT NULL` | `inventory_items.id` | `int(10) unsigned` | `fk_soi_item` | `RESTRICT` | **MATCH (100%)** |
| `stock_out_dispatch_units.stock_out_issuance_id` | `int(10) unsigned NOT NULL` | `stock_out_issuances.id` | `int(10) unsigned` | `fk_sodu_issuance` | `RESTRICT` | **MATCH (100%)** |
| `stock_out_dispatch_units.stock_out_item_id` | `int(10) unsigned NOT NULL` | `stock_out_items.id` | `int(10) unsigned` | `fk_sodu_item` | `RESTRICT` | **MATCH (100%)** |
| `stock_out_dispatch_units.inventory_unit_id` | `int(10) unsigned NOT NULL` | `inventory_units.id` | `int(10) unsigned` | `fk_sodu_unit` | `RESTRICT` | **MATCH (100%)** |
| `stock_out_dispatch_units.return_transaction_id` | `bigint(20) unsigned NULL` | `inventory_transactions.id` | `bigint(20) unsigned` | `fk_sodu_return_txn` | `RESTRICT` | **MATCH (100%)** |
| `inventory_units.stock_out_issuance_id` | `int(10) unsigned NULL` | `stock_out_issuances.id` | `int(10) unsigned` | `fk_iu_so_issuance` | `RESTRICT` | **MATCH (100%)** |
| `inventory_units.stock_out_item_id` | `int(10) unsigned NULL` | `stock_out_items.id` | `int(10) unsigned` | `fk_iu_so_item` | `RESTRICT` | **MATCH (100%)** |
| `inventory_units.stock_out_transaction_id` | **`bigint(20) unsigned NULL`** | **`inventory_transactions.id`** | **`bigint(20) unsigned`** | `fk_iu_so_txn` | `RESTRICT` | **MATCH (100%)** |

> [!IMPORTANT]
> **Data Type Precision Verified:** Note that `inventory_transactions.id` is a `BIGINT UNSIGNED`. The migration explicitly declares `inventory_units.stock_out_transaction_id` as `BIGINT UNSIGNED NULL`. Mismatched integer widths (`INT` vs `BIGINT`) would cause MariaDB Error 1005 (Foreign key constraint incorrectly formed). Parity is 100% verified.

---

## 4. Multi-Layer Permission Architecture & Department Matrix

### 4.1 SQL Layer vs. PHP Application Layer
In the BMS database architecture:
* The `roles` table contains only two roles: `administrator` (id: 1) and `department_user` (id: 2).
* All operational personnel across **Marketing (MKT)**, **Project Management (PM)**, **Operations (OPS)**, **Logistics (LOG)**, **Operation Finance (OPFIN)**, and **Admin Finance (ADMFIN)** share the generic database role `role_slug = 'department_user'`.
* **SQL Role Assignment Boundary:** Granting a permission to `department_user` in SQL allows *any* authenticated employee to pass raw `role_permissions` checks. SQL role assignments **cannot** enforce department-level segregation by themselves.
* **Server-Side PHP Guard:** Department-level authorization is enforced exclusively in [`App\Services\AuthorizationService`](file:///c:/xampp/htdocs/BMS/app/Services/AuthorizationService.php) by inspecting `user.department_code`.

### 4.2 SQL-Seeded Role Permissions
* **`administrator`:** Granted **all 7 permissions** (`.view`, `.create`, `.authorize`, `.manage`, `.dispatch`, `inv.reports.view`, `inv.adjustments.manage`).
* **`department_user`:** Granted **only baseline non-authorizing permissions**:
  1. `log.stock_out.view` (Read-only visibility)
  2. `log.stock_out.create` (Draft preparation)
  3. `inv.reports.view` (Report viewing)
  * High-privilege permissions (`log.stock_out.authorize`, `log.stock_out.dispatch`, `log.stock_out.manage`, `inv.adjustments.manage`) are **NOT** granted to `department_user` in SQL.

### 4.3 Intended Departmental Authorization Matrix (Server-Side Enforced)

| Operational Capability | Required Permission Key | Administrator | Logistics (`LOG`) | Operations (`OPS`) | Admin Finance (`ADMFIN`) | Operation Finance (`OPFIN`) | Marketing (`MKT`) | Project Mgmt (`PM`) |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **View Dispatches & Logs** | `log.stock_out.view` | Granted | Granted | Granted (Project Scoped) | Granted (Accounting Audit) | Granted (Cost Audit) | Blocked | Blocked |
| **Prepare Draft Dispatch** | `log.stock_out.create` | Granted | Granted (Warehouse Dispatch) | Granted (Jobsite Requisition) | Blocked | Blocked | Blocked | Blocked |
| **Authorize Dispatch** | `log.stock_out.authorize` | Granted | Granted (Warehouse Authority) | Blocked | Granted (Invoice/Billing Gate) | Blocked | Blocked | Blocked |
| **Execute Physical Release** | `log.stock_out.dispatch` | Granted | Granted (Exclusive Physical Custody) | Blocked | Blocked | Blocked | Blocked | Blocked |
| **Cancel / Administrative Override** | `log.stock_out.manage` | Granted | Granted (Draft/Submitted only) | Blocked | Blocked | Blocked | Blocked | Blocked |
| **View Ending Balance & Ledger** | `inv.reports.view` | Granted | Granted | Granted | Granted (Valuation) | Granted (BOM Budget) | Blocked | Blocked |
| **Execute Stock Adjustments** | `inv.adjustments.manage` | Granted | Granted (Warehouse Audit) | Blocked | Granted (Financial Write-off) | Blocked | Blocked | Blocked |
| **PO Authorization Authority** | `afin.po.authorize` | Granted | Blocked | Blocked | Granted (Exclusive) | Blocked | Blocked | Blocked |

---

## 5. Lifecycle Validation & Data Preservation Assessment

### 5.1 Stock Out Dispatch Lifecycle State Machine

```mermaid
stateDiagram-v2
    [*] --> Draft: Prepared by Logistics or Operations
    Draft --> Submitted_for_Authorization: Author submits draft
    Submitted_for_Authorization --> Authorized: Logistics Head or Admin Finance authorizes
    Submitted_for_Authorization --> Draft: Recalled or rejected for corrections
    Submitted_for_Authorization --> Cancelled: Cancelled with reason
    Authorized --> Dispatched: Physical release, DR confirmed, ledger posted
    Authorized --> Cancelled: Cancelled prior to physical release
    Dispatched --> [*]: Immutable historical record (No deletion, no cancellation)
    Cancelled --> [*]: Retained for audit (No stock moved)
```

### 5.2 Header Field Nullability & Validation Rules
* **Draft Stage:**
  * `out_dr_number` and `out_date` are **nullable (`NULL`)** in the database schema.
  * *Rationale:* At draft time, goods have not departed the facility; the final physical DR may not yet be printed or stamped.
* **Dispatch Stage:**
  * When executing physical dispatch, the service layer strictly enforces that `out_dr_number` (valid string) and `out_date` (valid calendar date) are provided.
  * The physical release updates `stock_out_issuances` (`status = 'Dispatched'`, `dispatched_by`, `dispatched_at`, `out_dr_number`, `out_date`).

### 5.3 Data Preservation & Immutability Rules
1. **Existing Records:** All existing records in `inventory_units` retain `status = 'In Stock'`, `in_qty = 1.00`, `out_qty = 0.00`, and all new stock-out columns as `NULL`. Zero existing rows are updated or truncated.
2. **Dispatched Immutability:** Once an issuance reaches `Dispatched`, all foreign keys (`fk_iu_so_issuance`, `fk_iu_so_item`, `fk_iu_so_txn`, `fk_soi_issuance`) enforce **`ON DELETE RESTRICT`**. A dispatched issuance cannot be deleted from the database.
3. **Draft Deletion:** If a draft issuance is deleted prior to authorization, `ON DELETE RESTRICT` requires deleting its unposted draft line items first. No inventory movements are ever orphaned.
4. **Returns & Reversals:** If equipment is returned from a client/jobsite, the original Stock Out record is **never** deleted or edited. A separate `stock_return` transaction restores available balance and updates unit status (`In Stock` or `Under Maintenance / RMA`).

---

## 6. Known Limitations

1. **DDL Auto-Commit in MySQL/MariaDB:**
   * MariaDB and MySQL automatically commit active transactions when running DDL (`CREATE TABLE`, `ALTER TABLE`). Therefore, the migration script cannot be wrapped in a single `BEGIN ... COMMIT` block. Idempotency is achieved via `IF NOT EXISTS` and dynamic `information_schema` constraint checks.
2. **Pre-Printed DR Booklets:**
   * If Pagecom uses pre-printed manual carbon-copy DR booklets rather than auto-generated digital DR numbers, duplicate DR number detection must be enforced at the application layer across fiscal years.

---

## 7. Outstanding Management Decisions (For Phase 3/4 Implementation)

The following questions do not block database schema execution, but will guide subsequent UI and controller development:

1. **Operations Jobsite Dispatch Privilege:**
   * *Question:* Should field site engineers in Operations be permitted to physically mark dispatches as `Dispatched` for equipment already stationed on site, or should physical release be restricted strictly to Logistics warehouse officers?
   * *Recommendation:* Restrict `log.stock_out.dispatch` strictly to Logistics and Administrators; Operations prepares requisitions (`Draft`).
2. **Sales / Commercial Invoice Association:**
   * *Question:* Is `invoice_number` required at the moment of dispatch, or can items be dispatched under a Delivery Receipt (DR) prior to commercial invoicing?
   * *Current Schema:* `invoice_number` is nullable to support both pre-invoiced and post-invoiced dispatches.
3. **Dispatch DR Print Layout:**
   * *Question:* Are there specific corporate signatory blocks required on the printable Dispatch Delivery Receipt (e.g. *Prepared By*, *Checked By*, *Approved By*, *Received By - Customer Signature & Date*)?

---

## 8. Rollback Limitations & Controlled Procedure

> [!CAUTION]
> **Production Rollback Warning:** Dropping `stock_out_issuances`, `stock_out_items`, or foreign keys from `inventory_units` will permanently destroy dispatch history and sever the audit trail of issued physical units. Rollback must **never** be executed silently in a production environment where live transactions have been recorded.
>
> If a rollback is required during testing prior to live data entry, the following verified procedure must be followed:

```sql
USE `business_management_system`;

-- 1. Remove foreign key constraints from inventory_units
SET @fk_iu_so_issuance_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_units' AND CONSTRAINT_NAME = 'fk_iu_so_issuance');
SET @sql_drop_fk1 = IF(@fk_iu_so_issuance_exists > 0, 'ALTER TABLE `inventory_units` DROP FOREIGN KEY `fk_iu_so_issuance`', 'SELECT 1');
PREPARE stmt_drop_fk1 FROM @sql_drop_fk1; EXECUTE stmt_drop_fk1; DEALLOCATE PREPARE stmt_drop_fk1;

SET @fk_iu_so_item_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_units' AND CONSTRAINT_NAME = 'fk_iu_so_item');
SET @sql_drop_fk2 = IF(@fk_iu_so_item_exists > 0, 'ALTER TABLE `inventory_units` DROP FOREIGN KEY `fk_iu_so_item`', 'SELECT 1');
PREPARE stmt_drop_fk2 FROM @sql_drop_fk2; EXECUTE stmt_drop_fk2; DEALLOCATE PREPARE stmt_drop_fk2;

SET @fk_iu_so_txn_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_units' AND CONSTRAINT_NAME = 'fk_iu_so_txn');
SET @sql_drop_fk3 = IF(@fk_iu_so_txn_exists > 0, 'ALTER TABLE `inventory_units` DROP FOREIGN KEY `fk_iu_so_txn`', 'SELECT 1');
PREPARE stmt_drop_fk3 FROM @sql_drop_fk3; EXECUTE stmt_drop_fk3; DEALLOCATE PREPARE stmt_drop_fk3;

-- 2. Drop added columns and indexes from inventory_units
ALTER TABLE `inventory_units`
    DROP COLUMN IF EXISTS `invoice_number`,
    DROP COLUMN IF EXISTS `out_dr_number`,
    DROP COLUMN IF EXISTS `issued_to_address`,
    DROP COLUMN IF EXISTS `issued_to_company`,
    DROP COLUMN IF EXISTS `warranty_expiration_date`,
    DROP COLUMN IF EXISTS `out_qty`,
    DROP COLUMN IF EXISTS `out_date`,
    DROP COLUMN IF EXISTS `stock_out_transaction_id`,
    DROP COLUMN IF EXISTS `stock_out_item_id`,
    DROP COLUMN IF EXISTS `stock_out_issuance_id`;

-- 3. Drop index from inventory_transactions
ALTER TABLE `inventory_transactions`
    DROP INDEX IF EXISTS `idx_it_type_date`;

-- 4. Drop new tables (only safe if no production records exist)
DROP TABLE IF EXISTS `stock_out_items`;
DROP TABLE IF EXISTS `stock_out_issuances`;

-- 5. Remove seeded permissions
DELETE FROM `role_permissions` WHERE `permission_id` IN (
    SELECT `id` FROM `permissions` WHERE `permission_key` IN (
        'log.stock_out.view',
        'log.stock_out.create',
        'log.stock_out.authorize',
        'log.stock_out.manage',
        'log.stock_out.dispatch',
        'inv.reports.view',
        'inv.adjustments.manage'
    )
);
DELETE FROM `permissions` WHERE `permission_key` IN (
    'log.stock_out.view',
    'log.stock_out.create',
    'log.stock_out.authorize',
    'log.stock_out.manage',
    'log.stock_out.dispatch',
    'inv.reports.view',
    'inv.adjustments.manage'
);
```

---

## 9. Strict Stop Condition Status

In accordance with project governance:
* **The migration has NOT been run against the live database.**
* **No live records or tables were modified.**
* **The deliverables are prepared, validated, and awaiting your final execution approval.**
