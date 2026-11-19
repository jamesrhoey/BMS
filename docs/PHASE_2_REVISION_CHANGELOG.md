# PHASE 2 REVISION CHANGELOG

**System:** Pagecom Inc. / SYB Business Management System (BMS)  
**Database:** MySQL / MariaDB (`business_management_system`, 10.4.32-MariaDB InnoDB)  
**Document Status:** Formal Revision Audit & Changelog (Final SQL Correction)  
**Associated Migration:** [`database/migrations/010_stock_out_and_ending_balance_phase2.sql`](file:///c:/xampp/htdocs/BMS/database/migrations/010_stock_out_and_ending_balance_phase2.sql)  
**Associated Reports:**  
* [`docs/PHASE_2_DATABASE_PERMISSION_REPORT.md`](file:///c:/xampp/htdocs/BMS/docs/PHASE_2_DATABASE_PERMISSION_REPORT.md)  
* [`docs/PHASE_2_MIGRATION_VALIDATION_REPORT.md`](file:///c:/xampp/htdocs/BMS/docs/PHASE_2_MIGRATION_VALIDATION_REPORT.md)  
**Date:** November 2026  

---

## 1. Overview of Revision

Following architectural review of the preliminary Phase 2 migration and technical report, critical corrections were incorporated into the SQL migration and design blueprints. These corrections ensure total alignment with BMS application architecture, prevent broad privilege escalation at the database role level, enforce data integrity constraints on dispatch line items, handle planned vs. actual dispatch information accurately, and protect historical inventory records.

---

## 2. Itemized Corrections & Revisions

### Correction 1: Department User Permission Architecture Correction
* **Previous Implementation:** Migration SQL granted all 7 permissions (including `log.stock_out.authorize`, `log.stock_out.dispatch`, `inv.adjustments.manage`) to the generic database role `role_slug = 'department_user'`.
* **Identified Vulnerability:** In the BMS database schema, all non-admin employees across Marketing, PM, Operations, Logistics, Operation Finance, and Admin Finance share `role_slug = 'department_user'`. Granting high-privilege authorization and dispatch permissions to `department_user` in SQL granted them broadly across all department users at the database level.
* **Correction Executed:**
  * In SQL `role_permissions`, `department_user` is granted **only baseline, non-authorizing permissions**:
    * `log.stock_out.view` (visibility)
    * `log.stock_out.create` (draft preparation)
    * `inv.reports.view` (reporting)
  * High-privilege permissions (`log.stock_out.authorize`, `log.stock_out.dispatch`, `log.stock_out.manage`, `inv.adjustments.manage`) are granted **strictly to `administrator` in SQL**.
  * Clarified and documented that **SQL role assignments alone cannot enforce department-level segregation**. True departmental authorization is enforced exclusively in PHP [`App\Services\AuthorizationService`](file:///c:/xampp/htdocs/BMS/app/Services/AuthorizationService.php) by inspecting `user.department_code` (`LOG`, `OPS`, `ADMFIN`, `OPFIN`, `MKT`, `PM`).

---

### Correction 2: Draft Header Requirements (`out_dr_number` and `out_date` Nullability)
* **Previous Implementation:** `out_dr_number` and `out_date` were defined as `NOT NULL` on `stock_out_issuances`.
* **Identified Vulnerability:** At the time a draft dispatch is prepared, goods have not physically departed the warehouse, and the official physical Delivery Receipt (DR) may not yet be printed or assigned. Forcing `NOT NULL` during draft creation blocked users from saving planned dispatches.
* **Correction Executed:**
  * `out_dr_number` is now `VARCHAR(100) NULL`.
  * `out_date` is now `DATE NULL`.
  * *Workflow Enforcement:* Both fields remain nullable during `Draft` and `Submitted for Authorization`. Upon physical handover (`status = 'Dispatched'`), the PHP service layer strictly enforces that both `out_dr_number` and `out_date` are non-empty.

---

### Correction 3: Foreign Key Exact Data Types & Parity
* **Previous Implementation:** Verified in text but required formal cross-table inspection.
* **Correction Executed:**
  * `inventory_units.stock_out_transaction_id`: Verified strictly as **`BIGINT(20) UNSIGNED NULL`** to match `inventory_transactions.id` (`BIGINT UNSIGNED`).
  * `stock_out_issuances.project_id`: Verified as `INT(10) UNSIGNED NULL` matching `projects.id`.
  * `stock_out_issuances.prepared_by`: Verified as `INT(10) UNSIGNED NOT NULL` matching `users.id`.
  * `stock_out_items.inventory_item_id`: Verified as `INT(10) UNSIGNED NOT NULL` matching `inventory_items.id`.
  * `inventory_units.stock_out_issuance_id` & `stock_out_item_id`: Verified as `INT(10) UNSIGNED NULL`.

---

### Correction 4: Detail Line-Item Integrity & Constraints
* **Previous Implementation:** `stock_out_items` lacked unique line indexing and quantity validation constraints.
* **Correction Executed:**
  * Added unique line constraint: `UNIQUE KEY uq_soi_line (stock_out_issuance_id, line_no)` to prevent duplicate line numbering within a dispatch.
  * Added check constraint: `CONSTRAINT chk_soi_quantity CHECK (quantity_issued > 0.00)` to strictly prohibit negative or zero quantities.
  * Added check constraints: `CONSTRAINT chk_soi_unit_price CHECK (unit_price >= 0.00)` and `CONSTRAINT chk_soi_total_amount CHECK (total_amount >= 0.00)`.

---

### Correction 5: Historical Protection via `ON DELETE RESTRICT`
* **Previous Implementation:** `stock_out_items` used `ON DELETE CASCADE` and `inventory_units` used `ON DELETE SET NULL`.
* **Identified Vulnerability:** Deleting a header would cascade-delete line items, or setting foreign keys to NULL would sever the audit trail of issued serialized units.
* **Correction Executed:**
  * Enforced **`ON DELETE RESTRICT`** across all dispatch detail and unit linkages:
    * `stock_out_items.stock_out_issuance_id` $\to$ `ON DELETE RESTRICT`
    * `inventory_units.stock_out_issuance_id` $\to$ `ON DELETE RESTRICT`
    * `inventory_units.stock_out_item_id` $\to$ `ON DELETE RESTRICT`
    * `inventory_units.stock_out_transaction_id` $\to$ `ON DELETE RESTRICT`
  * Dispatched issuances, line items, and ledger transactions are completely immutable and cannot be deleted while physical unit history references them.

---

### Correction 6: Serialized Return & Reissuance Architecture (`stock_out_dispatch_units`)
* **Identified Conflict:** In prior drafts, selecting units for Stock Out required `stock_out_issuance_id IS NULL`. However, when serialized equipment is returned from a jobsite/client back into stock, setting `stock_out_issuance_id = NULL` would destroy historical dispatch traceability, while leaving it populated blocked the unit from ever being re-issued.
* **Correction Executed:**
  * Created dedicated `stock_out_dispatch_units` table to record every individual serial dispatch and subsequent return event immutably.
  * In `inventory_units`, `status` represents current physical state (`'In Stock'`, `'Issued'`, `'Under Maintenance / RMA'`).
  * Unit selection query for dispatch strictly requires `inventory_item_id = :item_id AND status = 'In Stock'`.
  * Allows previously returned units to be cleanly re-issued without overwriting or losing any past Stock Out transaction records.
  * Preserves full chain of custody across multiple deployment cycles.

---

### Correction 7: Lifecycle Immutability Rules & Return Handling
* **Rules Formally Defined:**
  * `Draft`: Editable. Deletable only after deleting draft line items.
  * `Submitted for Authorization`: Locked. Cannot be deleted; author or authorizer can recall to Draft or Cancel with documented reason.
  * `Authorized`: Locked. Cannot be deleted; authorizer can Cancel with documented reason prior to physical handover.
  * `Dispatched`: **Permanently Immutable.** Cannot be edited, deleted, or cancelled.
  * `Returns / Reversals`: Dispatched goods returned from jobsites or clients are **never** deleted or edited. A dedicated `stock_return` transaction restores available balance and sets unit status to `'In Stock'` or `'Under Maintenance / RMA'`, recording the return in `stock_out_dispatch_units` while preserving 100% of the original Stock Out history.

---

### Correction 8: Atomic Ledger Protocol (8-Step Guarantee)
* **Protocol Formally Documented:**
  Every dispatch physical release must execute inside `Database::transaction()`:
  1. Row-lock inventory balance (`SELECT ... FOR UPDATE` on `inventory_items`).
  2. Row-lock serialized units (`SELECT ... FOR UPDATE` on `inventory_units WHERE status = 'In Stock'`).
  3. Validate available balance $\ge$ quantity and all serials are currently available.
  4. Insert double-entry debit row in `inventory_transactions` (`type = 'stock_out'`).
  5. Decrement `inventory_items.current_balance`.
  6. Update `inventory_units` (`status = 'Issued'`, `out_qty = 1.00`, `out_date`, `out_dr_number`).
  7. Update `stock_out_issuances` (`status = 'Dispatched'`, `dispatched_by`, `dispatched_at`, `out_dr_number`, `out_date`).
  8. Commit transaction.

---

## 3. Outstanding Management Decisions (Documented for Confirmation)

The following operational questions do not block database schema execution, but require management confirmation during Phase 3 UI development:

1. **DR Number Source:**
   * *Question:* Will Pagecom generate Delivery Receipt numbers automatically via a system sequence (e.g. `DR-YYYYMM-XXXX`), or are DR numbers manually entered from pre-printed physical carbon-copy booklets?
2. **Operations Field Site Dispatch:**
   * *Question:* Should Operations site engineers be allowed to physically execute dispatches on remote jobsites, or should physical release be strictly restricted to Logistics warehouse personnel?
   * *Recommendation:* Keep `log.stock_out.dispatch` strictly reserved for Logistics warehouse officers and Administrators. Operations prepares draft requisitions (`Draft`).
3. **Commercial Invoicing Precondition:**
   * *Question:* Can equipment be dispatched under a Delivery Receipt (DR) prior to generating a sales invoice, or is an invoice number mandatory at the time of dispatch?
   * *Current Schema:* `invoice_number` is nullable to accommodate both business practices.

---

## 4. Strict Stop Condition Status

* Migration file finalized: [`database/migrations/010_stock_out_and_ending_balance_phase2.sql`](file:///c:/xampp/htdocs/BMS/database/migrations/010_stock_out_and_ending_balance_phase2.sql)
* Validation report authored: [`docs/PHASE_2_MIGRATION_VALIDATION_REPORT.md`](file:///c:/xampp/htdocs/BMS/docs/PHASE_2_MIGRATION_VALIDATION_REPORT.md)
* Technical report updated: [`docs/PHASE_2_DATABASE_PERMISSION_REPORT.md`](file:///c:/xampp/htdocs/BMS/docs/PHASE_2_DATABASE_PERMISSION_REPORT.md)
* Revision changelog updated: [`docs/PHASE_2_REVISION_CHANGELOG.md`](file:///c:/xampp/htdocs/BMS/docs/PHASE_2_REVISION_CHANGELOG.md)
* **Execution Status: STRICTLY PAUSED. No SQL has been executed. Awaiting user review and authorization.**
