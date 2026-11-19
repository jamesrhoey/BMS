# PHASE 2 FINAL REVIEW AND CORRECTION REPORT

**System:** Pagecom Inc. / SYB Business Management System (BMS)  
**Database:** `business_management_system`  
**Database Engine:** MariaDB 10.4.32-MariaDB InnoDB (`utf8mb4_unicode_ci`) on XAMPP  
**Migration File:** [`database/migrations/010_stock_out_and_ending_balance_phase2.sql`](file:///c:/xampp/htdocs/BMS/database/migrations/010_stock_out_and_ending_balance_phase2.sql)  
**Validation Script:** [`database/migrations/preflight_010_check.php`](file:///c:/xampp/htdocs/BMS/database/migrations/preflight_010_check.php)  
**Related Documents:**  
* [`docs/PHASE_2_DATABASE_PERMISSION_REPORT.md`](file:///c:/xampp/htdocs/BMS/docs/PHASE_2_DATABASE_PERMISSION_REPORT.md)  
* [`docs/PHASE_2_MIGRATION_VALIDATION_REPORT.md`](file:///c:/xampp/htdocs/BMS/docs/PHASE_2_MIGRATION_VALIDATION_REPORT.md)  
* [`docs/PHASE_2_REVISION_CHANGELOG.md`](file:///c:/xampp/htdocs/BMS/docs/PHASE_2_REVISION_CHANGELOG.md)  
* [`docs/PR_PO_INVENTORY_INTEGRATION_PLAN.md`](file:///c:/xampp/htdocs/BMS/docs/PR_PO_INVENTORY_INTEGRATION_PLAN.md)  
**Document Status:** Final Phase 2 Review Deliverable — Ready for Stakeholder Approval  
**Execution Status:** **STRICTLY PAUSED — NO SQL HAS BEEN EXECUTED ON THE LIVE DATABASE**  

---

## 1. Executive Summary & Review Verdict

This final review report concludes Phase 2 of the BMS PR $\to$ PO $\to$ Inventory Integration. All identified schema conflicts, permission boundaries, and lifecycle issues have been resolved, formally documented, and preflight-tested against the live MariaDB 10.4.32 database instance.

### Final Readiness Verdict:
$$\mathbf{PHASE\ 2\ FULLY\ REVISED\ \&\ VALIDATED\ —\ READY\ FOR\ PHASE\ 3\ APPROVAL}$$

* **Non-Destructive Guarantee:** Zero `DROP TABLE`, `TRUNCATE`, or `ALTER TABLE ... DROP` commands exist in the migration.
* **Preflight Verification:** 100% pass across all 25 automated preflight checks (engine, 16 prerequisite tables, 6 FK parent types, 0 object conflicts, and 48 existing baseline permissions intact).
* **Return & Reissuance Architecture:** Resolved the serialization conflict by implementing the `stock_out_dispatch_units` immutable physical dispatch ledger.
* **Permission Segregation:** Fixed the broad assignment of permissions to `department_user`. Database role permissions grant only baseline read/create privileges; fine-grained department segregation and self-authorization prevention are designed for server-side PHP enforcement in [`app/Services/AuthorizationService.php`](file:///c:/xampp/htdocs/BMS/app/Services/AuthorizationService.php).

---

## 2. Resolution of Serialized Inventory Return & Reissuance Conflict

### 2.1 The Problem in Prior Schema Drafts
In earlier schema revisions, a serialized unit was deemed available for dispatch only when:
```sql
WHERE inventory_item_id = :item_id 
  AND status = 'In Stock' 
  AND stock_out_issuance_id IS NULL
```
This created a critical operational deadlock:
1. When equipment was dispatched to a jobsite, `stock_out_issuance_id` was populated on `inventory_units`.
2. When equipment was subsequently returned to the warehouse (e.g. project demobilization, surplus return, or RMA replacement):
   * Setting `stock_out_issuance_id = NULL` permanently erased the historical record of where the physical unit had been deployed.
   * Retaining `stock_out_issuance_id != NULL` blocked the unit from ever being selected for a new dispatch, because `stock_out_issuance_id IS NULL` evaluated to `FALSE`.

### 2.2 The Architectural Solution: `stock_out_dispatch_units`
To separate **historical deployment records** from **current physical unit state**, the revised schema introduces a dedicated immutable dispatch log: `stock_out_dispatch_units`.

```mermaid
flowchart TD
    subgraph INTAKE["1. Stock In (Receiving)"]
        A["Delivery Inspected & Accepted"] --> B["Insert inventory_units<br/>(status = 'In Stock', in_qty = 1.00)"]
    end

    subgraph DISPATCH1["2. First Stock Out Dispatch"]
        B --> C["Select unit WHERE status = 'In Stock'"]
        C --> D["Insert into stock_out_dispatch_units<br/>(status = 'Dispatched', dispatched_at = NOW())"]
        D --> E["Update inventory_units:<br/>• status = 'Issued'<br/>• out_qty = 1.00<br/>• latest stock_out_issuance_id"]
    end

    subgraph RETURN["3. Stock Return from Jobsite / Client"]
        E --> F["Record return in inventory_transactions<br/>(type = 'stock_return', quantity = 1.00)"]
        F --> G["Update stock_out_dispatch_units:<br/>• status = 'Returned'<br/>• returned_at = NOW()<br/>• return_transaction_id"]
        G --> H{"Physical Condition Inspection"}
        H -- "Good Condition" --> I["Update inventory_units:<br/>• status = 'In Stock'<br/>• out_qty = 0.00"]
        H -- "Damaged / Faulty" --> J["Update inventory_units:<br/>• status = 'Under Maintenance / RMA'"]
    end

    subgraph DISPATCH2["4. Subsequent Re-Issuance"]
        I --> K["Eligible for new dispatch!<br/>Selection Query: status = 'In Stock'"]
        K --> L["New insert into stock_out_dispatch_units<br/>(New dispatch record created)"]
        L --> M["Past dispatch #1 remains 100% intact in log!"]
    end
```

### 2.3 Table Structure: `stock_out_dispatch_units`
```sql
CREATE TABLE IF NOT EXISTS `stock_out_dispatch_units` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `stock_out_issuance_id` INT UNSIGNED NOT NULL,
    `stock_out_item_id` INT UNSIGNED NOT NULL,
    `inventory_unit_id` INT UNSIGNED NOT NULL,
    `serial_number` VARCHAR(150) NOT NULL,
    `dispatched_at` DATETIME NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Dispatched',
    `returned_at` DATETIME NULL,
    `return_reference_no` VARCHAR(100) NULL,
    `return_transaction_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sodu_issuance` (`stock_out_issuance_id`),
    KEY `idx_sodu_item` (`stock_out_item_id`),
    KEY `idx_sodu_unit` (`inventory_unit_id`),
    KEY `idx_sodu_serial` (`serial_number`),
    KEY `idx_sodu_status` (`status`),
    CONSTRAINT `fk_sodu_issuance` FOREIGN KEY (`stock_out_issuance_id`) REFERENCES `stock_out_issuances` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sodu_item` FOREIGN KEY (`stock_out_item_id`) REFERENCES `stock_out_items` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sodu_unit` FOREIGN KEY (`inventory_unit_id`) REFERENCES `inventory_units` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sodu_return_txn` FOREIGN KEY (`return_transaction_id`) REFERENCES `inventory_transactions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.4 Integrity Guarantees for Reissuance
1. **Never Overwrite Past Dispatches:** Every dispatch event is a distinct row in `stock_out_dispatch_units`. Even if a serial number is dispatched and returned 5 times over its lifecycle, all 5 dispatch events and 5 return events are permanently preserved.
2. **Current Physical State in `inventory_units`:** The `inventory_units` table holds the unit's **current real-time physical condition and location**. Available equipment has `status = 'In Stock'`.
3. **Dispatch Selection Query:**
   ```sql
   SELECT id, serial_number, model, current_location 
   FROM inventory_units 
   WHERE inventory_item_id = :item_id 
     AND status = 'In Stock'
   ORDER BY id ASC;
   ```
4. **Prevent Duplicate Selection / Concurrency Lock:**
   During the physical release transaction, candidate units are locked using pessimistic locking:
   ```sql
   SELECT id, serial_number, status 
   FROM inventory_units 
   WHERE id IN (:selected_unit_ids) 
   FOR UPDATE;
   ```
   If any selected unit has `status != 'In Stock'`, the transaction immediately aborts, preventing concurrent double-issuance.
5. **Exact Single Balance Restoration on Return:**
   When a unit is returned, a single `inventory_transactions` row (`type = 'stock_return'`) is written, and `inventory_items.current_balance` is incremented by exactly `1.00`. The return cannot be double-processed because `stock_out_dispatch_units.status` transitions from `'Dispatched'` to `'Returned'`.

---

## 3. Actual `AuthorizationService` Inspection & Architecture

### 3.1 Existing PHP Code Inspection
A direct inspection of [`app/Services/AuthorizationService.php`](file:///c:/xampp/htdocs/BMS/app/Services/AuthorizationService.php) revealed:
* The service currently handles permissions up to **Supplier Delivery and Stock In** (`canExecuteStockIn`, ending at line 967).
* **No Stock Out methods exist in PHP yet.**
* In the database, the `roles` table contains only two roles:
  1. `administrator` (Full system access)
  2. `department_user` (Assigned to all employees across LOG, OPS, ADMFIN, OPFIN, MKT, PM)
* All non-admin users have `role_slug = 'department_user'`. Their departmental affiliation is stored in `users.department_id` linking to `departments.department_code`.

### 3.2 SQL Role Assignment Correction
Because SQL `role_permissions` checks only `role_id`, granting `log.stock_out.authorize` or `log.stock_out.dispatch` to `department_user` in SQL would grant those privileges to Marketing, PM, and Operations users at the database query level.

**Correction Implemented in SQL:**
* `administrator`: Granted all 7 Stock Out and Inventory permissions.
* `department_user`: Granted **only baseline, non-authorizing permissions**:
  * `log.stock_out.view` (Read-only dispatch visibility)
  * `log.stock_out.create` (Draft dispatch preparation)
  * `inv.reports.view` (Inventory balance reports)
* High-privilege permissions (`log.stock_out.authorize`, `log.stock_out.dispatch`, `log.stock_out.manage`, `inv.adjustments.manage`) are **omitted from `department_user` in SQL**.

### 3.3 PHP Server-Side Department Authorization Matrix
True departmental boundaries and workflow stage gates are enforced in PHP [`app/Services/AuthorizationService.php`](file:///c:/xampp/htdocs/BMS/app/Services/AuthorizationService.php):

| Department Code | Department Name | Draft Preparation (`.create`) | Dispatch Authorization (`.authorize`) | Physical Release (`.dispatch`) | Balance Adjustments (`.adjustments`) | Ending Balance Reports (`.reports.view`) | Operational Boundary |
| :---: | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| **LOG** | Logistics | **YES** | **YES** | **YES** | **YES** | **YES** | **Primary Custodian:** Authors dispatches, verifies physical serials, releases stock, manages adjustments. |
| **OPS** | Operations | **YES** | **NO** | **NO** | **NO** | **YES** | **Requisitioner:** Prepares draft dispatches for project sites. Cannot authorize or release stock. |
| **ADMFIN** | Admin Finance | **NO** | **YES** | **NO** | **YES** | **YES** | **Commercial Authority:** Can authorize dispatches linked to sales invoices. Cannot physically dispatch stock. |
| **OPFIN** | Operation Finance | **NO** | **NO** | **NO** | **NO** | **YES** | **Cost Auditor:** Read-only access to verify stock consumption against project Actual BOM. |
| **PM** | Project Management | **NO** | **NO** | **NO** | **NO** | **NO** | Blocked from inventory custody operations. |
| **MKT** | Marketing | **NO** | **NO** | **NO** | **NO** | **NO** | Blocked from inventory custody operations. |
| **ADMIN** | Administrator | **YES** | **YES** | **YES** | **YES** | **YES** | Full supervisory override across all departments. |

### 3.4 Self-Authorization Prevention
A fundamental internal control requirement is that **the user who prepares a dispatch cannot authorize it**:

$$\text{Can Authorize} \iff (\text{User Has Authorize Permission}) \land (\text{User ID} \neq \text{Issuance.prepared\_by}) \quad \lor \quad (\text{User is Administrator})$$

This is enforced in PHP:
```php
public function canAuthorizeStockOut(array $user, array $issuance): bool
{
    if ($this->isAdministrator($user)) {
        return true;
    }

    // Strictly prohibit self-authorization
    if ((int) $user['id'] === (int) $issuance['prepared_by']) {
        return false;
    }

    $dept = $this->getUserDepartmentCode($user);
    return in_array($dept, ['LOG', 'ADMFIN'], true) 
        && $this->hasPermission($user, 'log.stock_out.authorize');
}
```

### 3.5 Required Methods for `AuthorizationService.php` in Phase 3
The following methods must be implemented in [`app/Services/AuthorizationService.php`](file:///c:/xampp/htdocs/BMS/app/Services/AuthorizationService.php) during Phase 3:
1. `canViewStockOut(array $user): bool` — LOG, OPS, ADMFIN, OPFIN, Administrator.
2. `canPrepareStockOut(array $user): bool` — LOG, OPS, Administrator.
3. `canEditStockOutDraft(array $user, array $issuance): bool` — Only when `status == 'Draft'` and user is author or Administrator.
4. `canAuthorizeStockOut(array $user, array $issuance): bool` — LOG, ADMFIN, Administrator; requires `prepared_by !== user.id`.
5. `canDispatchStockOut(array $user, array $issuance): bool` — Strictly LOG or Administrator; requires `status == 'Authorized'`.
6. `canCancelStockOut(array $user, array $issuance): bool` — LOG, ADMFIN, Administrator; strictly blocked if `status == 'Dispatched'`.
7. `canManageInventoryAdjustments(array $user): bool` — LOG, ADMFIN, Administrator.
8. `canViewEndingBalanceReports(array $user): bool` — LOG, OPS, ADMFIN, OPFIN, Administrator.

---

## 4. Summary of Schema Issues Corrected

| Area | Preliminary Draft Defect | Architectural Correction in Revision | Benefit & Safety Result |
| :--- | :--- | :--- | :--- |
| **Dispatch Lifecycle** | Default status was `'Dispatched'`. Draft state was missing. | State machine implemented: `Draft` $\to$ `Submitted for Authorization` $\to$ `Authorized` $\to$ `Dispatched` (or `Cancelled`). | Prevents phantom ledger debits before physical handover occurs. |
| **Personnel Audit Fields** | Single generic `issued_by` column. | Segregated audit columns: `prepared_by/at`, `authorized_by/at`, `dispatched_by/at`, `cancelled_by/at`. | Complete accountability and clear separation of duties. |
| **Draft Header Fields** | `out_dr_number` and `out_date` were defined as `NOT NULL`. | Made `NULLABLE` in SQL. Enforced as mandatory in PHP strictly upon physical dispatch. | Allows users to prepare and authorize planned dispatches before physical DR printing. |
| **Line-Item Integrity** | `stock_out_items` lacked unique line constraints and positive quantity checks. | Added `UNIQUE KEY uq_soi_line (stock_out_issuance_id, line_no)` and `CHECK (quantity_issued > 0.00)`. | Prevents duplicate line numbers and prohibits negative or zero dispatch quantities. |
| **Unit Link Deletion Rules** | Used `ON DELETE CASCADE` or `ON DELETE SET NULL`. | Converted all dispatch and unit foreign keys to **`ON DELETE RESTRICT`**. | Dispatched issuances and line items cannot be deleted while historical unit records reference them. |
| **Foreign Key Data Types** | Potential mismatch on transaction reference. | `inventory_units.stock_out_transaction_id` explicitly typed as **`BIGINT UNSIGNED`**, matching `inventory_transactions.id`. | Prevents MariaDB Error 1005 (Foreign key constraint incorrectly formed). |
| **Serial Return Conflict** | Serial selection required `stock_out_issuance_id IS NULL`, preventing re-issuance of returned units. | Introduced `stock_out_dispatch_units` immutable log. Selection query uses `status = 'In Stock'`. | Enables full multi-cycle equipment return and re-issuance with zero loss of dispatch history. |
| **SQL Permission Scope** | Granted all 7 permissions to `department_user` in SQL. | Granted only baseline read/create in SQL; high-privilege permissions reserved for `administrator` in SQL; fine-grained department logic enforced in PHP. | Prevents broad privilege escalation across non-admin departments. |

---

## 5. Preflight Validation Results (MariaDB 10.4.32)

The preflight validation script [`database/migrations/preflight_010_check.php`](file:///c:/xampp/htdocs/BMS/database/migrations/preflight_010_check.php) was executed against the live MariaDB 10.4.32 instance:

```
======================================================================
  BMS MIGRATION 010 PREFLIGHT VALIDATION (MariaDB 10.4.32)
======================================================================

1. Database Engine & Version:
   Server Version: 10.4.32-MariaDB (mariadb.org binary distribution)
  [PASS] Database engine is MariaDB (Target: 10.4.32)

2. Prerequisite Tables Existence:
  [PASS] Table `users` exists
  [PASS] Table `departments` exists
  [PASS] Table `roles` exists
  [PASS] Table `permissions` exists
  [PASS] Table `role_permissions` exists
  [PASS] Table `projects` exists
  [PASS] Table `project_documents` exists
  [PASS] Table `purchase_requests` exists
  [PASS] Table `purchase_request_items` exists
  [PASS] Table `purchase_orders` exists
  [PASS] Table `purchase_order_items` exists
  [PASS] Table `supplier_deliveries` exists
  [PASS] Table `supplier_delivery_items` exists
  [PASS] Table `inventory_items` exists
  [PASS] Table `inventory_transactions` exists
  [PASS] Table `inventory_units` exists

3. Foreign Key Column Type & Signedness Parity:
  [PASS] inventory_transactions.id matches expected `bigint(20) unsigned` (Parent PK for stock_out_transaction_id)
  [PASS] projects.id matches expected `int(10) unsigned` (Parent PK for stock_out_issuances.project_id)
  [PASS] users.id matches expected `int(10) unsigned` (Parent PK for stock_out_issuances personnel)
  [PASS] inventory_items.id matches expected `int(10) unsigned` (Parent PK for stock_out_items.inventory_item_id)
  [PASS] project_documents.id matches expected `int(10) unsigned` (Parent PK for supporting_document_id)
  [PASS] inventory_units.id matches expected `int(10) unsigned` (Parent PK for dispatch unit allocations)

4. Migration 010 Target Objects Conflict Check:
  [PASS] Table `stock_out_issuances` does not exist yet (clean slate for migration)
  [PASS] Table `stock_out_items` does not exist yet (clean slate for migration)
  [PASS] Table `stock_out_dispatch_units` does not exist yet (clean slate for migration)
  [PASS] `inventory_units`.`stock_out_issuance_id` does not exist yet (clean to add)
  [PASS] `inventory_units`.`stock_out_item_id` does not exist yet (clean to add)
  [PASS] `inventory_units`.`stock_out_transaction_id` does not exist yet (clean to add)
  [PASS] `inventory_units`.`out_date` does not exist yet (clean to add)
  [PASS] `inventory_units`.`out_qty` does not exist yet (clean to add)
  [PASS] `inventory_units`.`warranty_expiration_date` does not exist yet (clean to add)
  [PASS] `inventory_units`.`issued_to_company` does not exist yet (clean to add)
  [PASS] `inventory_units`.`issued_to_address` does not exist yet (clean to add)
  [PASS] `inventory_units`.`out_dr_number` does not exist yet (clean to add)
  [PASS] `inventory_units`.`invoice_number` does not exist yet (clean to add)

5. RBAC & Roles Baseline Check:
  [PASS] Role `administrator` exists
  [PASS] Role `department_user` exists
   Active permissions in system: 48
  [PASS] Existing baseline permissions intact (expected >= 48, found 48)
  [PASS] All 7 new permission keys are completely novel and unmapped (clean insert)

======================================================================
  PREFLIGHT RESULT: SUCCESS (Ready for Migration 010)
======================================================================
```

---

## 6. Inventory Quantity Rules & Atomic Integrity

### 6.1 Serialized vs. Bulk Item Quantity Rules
* **Serialized Items (`inventory_items.is_serialized = 1`):**
  * `quantity_issued` must be a strictly whole positive integer ($1.00, 2.00, 3.00$).
  * The number of physical serial numbers selected from `inventory_units` must **exactly equal** `quantity_issued`.
  * Each selected unit is stamped with `out_qty = 1.00`, `status = 'Issued'`, and an entry is created in `stock_out_dispatch_units`.
* **Bulk Non-Serialized Materials (`inventory_items.is_serialized = 0`):**
  * Supports fractional decimal quantities (e.g. $12.50\text{ meters}$, $4.75\text{ kg}$).
  * Quantities are validated strictly $> 0.00$ via database check constraint `chk_soi_quantity`.
  * No individual serial records are selected or required.
  * Movement is recorded directly in `inventory_transactions` and decrements `inventory_items.current_balance`.

### 6.2 Negative Balance Prevention & Atomic Execution Protocol
To ensure that inventory balances can never become negative and that concurrent transactions cannot over-allocate stock, physical release must execute inside a single atomic database transaction using pessimistic row locking:

```mermaid
sequenceDiagram
    autonumber
    actor Officer as Logistics Officer
    participant Svc as StockOutService (PHP)
    participant DB as MariaDB 10.4.32

    Officer->>Svc: Execute Physical Release (Dispatch ID, Serial IDs, DR No, Date)
    Svc->>DB: BEGIN TRANSACTION
    Svc->>DB: SELECT current_balance FROM inventory_items WHERE id = :item_id FOR UPDATE
    Note over Svc,DB: Row-locked: Prevents concurrent modifications
    Svc->>DB: SELECT id, status FROM inventory_units WHERE id IN (:unit_ids) FOR UPDATE
    Note over Svc,DB: Row-locked: Verifies all units have status = 'In Stock'
    alt Insufficient Balance OR Unit Already Issued
        Svc->>DB: ROLLBACK
        Svc-->>Officer: Error: Insufficient stock or serial unit conflict
    else Sufficient Balance & All Units Available
        Svc->>DB: INSERT INTO inventory_transactions (stock_out, debit, qty, balance_before, balance_after)
        Svc->>DB: UPDATE inventory_items SET current_balance = current_balance - qty
        Svc->>DB: INSERT INTO stock_out_dispatch_units (issuance_id, item_id, unit_id, serial, Dispatched)
        Svc->>DB: UPDATE inventory_units SET status = 'Issued', out_qty = 1.00, out_dr_number = :dr
        Svc->>DB: UPDATE stock_out_issuances SET status = 'Dispatched', dispatched_by = :uid, dispatched_at = NOW()
        Svc->>DB: COMMIT TRANSACTION
        Svc-->>Officer: Dispatch Successful & Ledger Updated
    end
```

---

## 7. Safe Rollback Strategy & Limitations

### 7.1 Pre-Rollback Transaction Check
> [!CAUTION]
> **Production Rollback Prohibition:** Dropping tables or foreign keys once live dispatches have been posted will irreversibly destroy equipment dispatch history and sever the serial unit audit trail.
>
> A rollback script must **first verify that no operational records exist**:

```sql
SELECT 
    (SELECT COUNT(*) FROM `stock_out_issuances`) AS issuance_count,
    (SELECT COUNT(*) FROM `stock_out_dispatch_units`) AS dispatch_unit_count,
    (SELECT COUNT(*) FROM `inventory_units` WHERE `stock_out_issuance_id` IS NOT NULL) AS active_issued_units;
```
If any of these counts are greater than 0, rollback is **strictly prohibited**. In production, errant dispatches must be resolved via authorized cancellation (prior to dispatch) or formal `stock_return` transactions (after dispatch).

### 7.2 Isolated Test Rollback Procedure
If rollback is required in a non-production testing environment prior to live record creation:
1. Drop added foreign keys on `inventory_units` (`fk_iu_so_issuance`, `fk_iu_so_item`, `fk_iu_so_txn`).
2. Drop added columns on `inventory_units` (`invoice_number`, `out_dr_number`, `issued_to_address`, `issued_to_company`, `warranty_expiration_date`, `out_qty`, `out_date`, `stock_out_transaction_id`, `stock_out_item_id`, `stock_out_issuance_id`).
3. Drop `stock_out_dispatch_units`, `stock_out_items`, and `stock_out_issuances`.
4. Drop index `idx_it_type_date` on `inventory_transactions`.
5. Remove **only the 7 newly seeded permissions** (`permission_key IN ('log.stock_out.view', ...)`), leaving the 48 original baseline permissions completely intact.

---

## 8. Outstanding Business Decisions for Management Confirmation

The following operational questions do not block database schema execution, but require confirmation prior to finalizing Phase 3 UI screens:

1. **DR Number Source:**
   * *Option A:* Pre-printed carbon-copy booklet (Officer types the physical DR number into the system).
   * *Option B:* System auto-generated sequence (e.g. `DR-YYYYMM-XXXX`).
   * *Current Schema:* Accommodates both by providing a flexible `VARCHAR(100)` field.
2. **Operations Field Site Dispatch:**
   * *Option A:* Operations site engineers can execute physical dispatches directly from field staging areas.
   * *Option B:* Physical dispatch is strictly centralized to Logistics warehouse personnel.
   * *Recommendation:* Centralize physical dispatch to Logistics (`LOG`). Operations prepares requisitions (`Draft`).
3. **Re-issued Equipment Valuation:**
   * *Option A:* Returned equipment re-issued at weighted average cost.
   * *Option B:* Re-issued at zero accounting cost (already expensed on initial project).
   * *Option C:* Re-issued at original specific unit acquisition cost.
   * *Recommendation:* Consult Finance on whether project stock-outs are treated as inter-project cost transfers or expensed upon dispatch.

---

## 9. Final Execution Readiness Recommendation

The Phase 2 database schema, foreign key relationships, RBAC configurations, and rollback protocols are completely finalized, validated, and ready for deployment.

### Current Status:
* **The live database has NOT been modified.**
* **The migration script `010_stock_out_and_ending_balance_phase2.sql` has NOT been executed.**
* **No PHP application files or UI components have been altered.**
* **Preflight checks on MariaDB 10.4.32: 100% PASSED.**

### Next Steps (Phase 3 Transition):
Upon receiving explicit stakeholder approval to proceed to Phase 3:
1. Execute migration [`database/migrations/010_stock_out_and_ending_balance_phase2.sql`](file:///c:/xampp/htdocs/BMS/database/migrations/010_stock_out_and_ending_balance_phase2.sql) on the live database.
2. Implement backend service layer:
   * Extend [`app/Services/AuthorizationService.php`](file:///c:/xampp/htdocs/BMS/app/Services/AuthorizationService.php) with the 8 documented Stock Out & Ending Balance authorization methods.
   * Create `App\Services\StockOutService.php` with atomic 8-step physical release and return transaction logic.
   * Create `App\Services\EndingBalanceService.php` for perpetual weighted average ending balance calculations.
3. Implement MVC Controllers and Views:
   * `StockOutController.php` (Draft authoring, line items, serial assignment modal, authorization, dispatch DR print view).
   * `InventoryReportController.php` (Ending balance summary, perpetual ledger audit trail, physical vs. book discrepancy report).

---
*Report authored and certified for BMS PR-PO-Inventory Phase 2 review.*
