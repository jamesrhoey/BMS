# SYB Business Management System

Integrated web-based **Business Management System (BMS)** built for **SYB** using native PHP 8.2 (MVC architecture), MariaDB/MySQL (PDO prepared statements), HTML5, CSS3, Vanilla JavaScript, and Bootstrap 5.

This single-application, shared-database platform supports all six SYB company departments:

1. **Marketing** (`MKT`) — **Implemented**
2. **Project Management** (`PM`) — *Minimal incoming verification queue & return-for-corrections handoff active; full module & JOM issuance pending*
3. **Operations** (`OPS`) — *Foundation workspace active; workflow module pending*
4. **Logistics** (`LOG`) — *Foundation workspace active; workflow module pending*
5. **Operation Finance** (`OPFIN`) — *Foundation workspace active; workflow module pending*
6. **Admin Finance** (`ADMFIN`) — *Foundation workspace active; workflow module pending*

---

## 1. Technology Stack

- **Backend:** Native PHP 8.2+ (Custom MVC, zero full-stack framework dependencies)
- **Database:** MySQL / MariaDB (`business_management_system`, `utf8mb4_unicode_ci`, InnoDB)
- **Database Access:** PDO with emulated prepares disabled (`PDO::ATTR_EMULATE_PREPARES = false`) and explicit transaction management
- **Authentication & Security:** PHP sessions (`HttpOnly`, `SameSite=Lax`, auto `Secure` on HTTPS, session ID regeneration, idle & absolute timeouts), `password_hash()` / `password_verify()`, global POST CSRF protection, server-side RBAC middleware & policies, `finfo` file-content verification, `htmlspecialchars()` XSS output escaping
- **Frontend:** HTML5, CSS3, Vanilla JavaScript, Bootstrap 5.3
- **Local Environment:** XAMPP on Windows (`Apache` + `MySQL/MariaDB` + `phpMyAdmin`)

---

## 2. Project Directory Structure

```text
BMS/
├── app/
│   ├── Controllers/
│   │   ├── ActivityLogController.php
│   │   ├── AuthController.php
│   │   ├── Controller.php
│   │   ├── DashboardController.php
│   │   ├── DepartmentController.php
│   │   ├── MarketingController.php
│   │   ├── NotificationController.php
│   │   ├── ProfileController.php
│   │   ├── ProjectController.php
│   │   ├── ProjectDocumentController.php
│   │   └── UserController.php
│   ├── Core/
│   │   ├── Autoloader.php
│   │   ├── Bootstrap.php
│   │   ├── Config.php
│   │   ├── Csrf.php
│   │   ├── Database.php
│   │   ├── Env.php
│   │   ├── ErrorHandler.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Router.php
│   │   ├── Session.php
│   │   ├── Validator.php
│   │   ├── View.php
│   │   └── helpers.php
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   ├── CsrfMiddleware.php
│   │   ├── GuestMiddleware.php
│   │   └── RoleMiddleware.php
│   ├── Models/
│   │   ├── ActivityLog.php
│   │   ├── Department.php
│   │   ├── Notification.php
│   │   ├── Project.php
│   │   ├── ProjectDocument.php
│   │   ├── ProjectWorkflowHistory.php
│   │   ├── Role.php
│   │   └── User.php
│   ├── Services/
│   │   ├── ActivityLogger.php
│   │   ├── AuthorizationService.php
│   │   ├── AuthService.php
│   │   ├── DocumentStorageService.php
│   │   ├── MarketingChecklistService.php
│   │   ├── ProjectService.php
│   │   └── UserService.php
│   └── Views/
│       ├── activity_logs/
│       ├── auth/
│       ├── dashboard/
│       ├── departments/
│       ├── errors/
│       ├── layouts/
│       ├── marketing/
│       │   ├── dashboard.php
│       │   └── projects.php
│       ├── notifications/
│       │   └── index.php
│       ├── profile/
│       ├── projects/
│       └── users/
├── config/
│   ├── app.php
│   ├── database.php
│   └── marketing.php
├── database/
│   ├── migrations/
│   │   └── 002_marketing_module.sql
│   └── schema.sql
├── public/
│   ├── .htaccess
│   ├── index.php
│   └── assets/
│       ├── css/app.css
│       ├── images/logo.svg
│       └── js/app.js
├── routes/
│   └── web.php
├── scripts/
│   ├── manage-admin.php
│   └── setup-database.php
├── storage/
│   ├── .htaccess
│   ├── documents/
│   └── logs/
├── tests/
│   └── run_tests.php
├── .env.example
├── .gitignore
├── .htaccess
└── README.md
```

---

## 3. Local XAMPP Setup & Migration Instructions

### Prerequisites

1. Install **XAMPP for Windows** (PHP 8.1+ / 8.2+ and MariaDB/MySQL).
2. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
3. Ensure Apache `mod_rewrite` is enabled (enabled by default in XAMPP).
4. Place this project in `C:\xampp\htdocs\BMS`.

### Step 1: Configure `.env`

Copy `.env.example` to `.env` (never commit `.env` to Git):

```powershell
Copy-Item .env.example .env
```

Verify the database settings in `.env` match your local XAMPP MySQL/MariaDB instance:

```ini
APP_NAME="SYB Business Management System"
APP_ENV=local
APP_DEBUG=false
APP_URL=http://localhost/BMS

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=business_management_system
DB_USERNAME=root
DB_PASSWORD=
```

### Step 2: Initialize or Upgrade the Database

#### Option A — Using the CLI Setup & Migration Script (Recommended for Both Clean Install & Upgrade)

`scripts/setup-database.php` idempotently creates `business_management_system`, runs `database/schema.sql`, applies all incremental migrations in `database/migrations/` (including `002_marketing_module.sql`), ensures foreign keys exist, and optionally seeds the local Administrator account:

```powershell
C:\xampp\php\php.exe scripts/setup-database.php --seed-admin
```

- **Default Local Administrator Username:** `admin`
- **Default Local Administrator Password:** `Admin@SYB2026!` *(Hashed via `password_hash()`; change immediately in non-local environments)*

#### Option B — Using phpMyAdmin

- **Clean Install:** Open `http://localhost/phpmyadmin` -> **Import** -> select `database/schema.sql` -> click **Import**, then create an admin via `C:\xampp\php\php.exe scripts/manage-admin.php create --username=admin --name="System Administrator" --email=admin@syb.local --password="YourStrongPassword123!"`.
- **Upgrading an Existing Foundation Database:** Select database `business_management_system` in phpMyAdmin -> **Import** -> select `database/migrations/002_marketing_module.sql` -> click **Import** (or run `C:\xampp\php\php.exe scripts/setup-database.php`).

### Step 3: Access the Application

Open your browser and navigate to:

```text
http://localhost/BMS/
```

---

## 4. Marketing Module Design, Access Rules & Flowchart Assumptions

### A. Configurable Standard Marketing Document Checklist (`config/marketing.php`)
Following `FLOWCHART REPORT - SYB.PPTX` (Slide 5 — Standard Project Workflow), every standard Marketing project tracks six required documents:

1. **`SON`** — Service Order Notice / Service On Notice
2. **`SLD`** — Single Line Diagram *(Flowchart Slide 7; configurable subtitle in `config/marketing.php`)*
3. **`BOM`** — Bill of Materials *(Flowchart Slide 9; configurable subtitle in `config/marketing.php`)*
4. **`CA`** — Cost Analysis *(Flowchart Slide 10; configurable subtitle in `config/marketing.php`)*
5. **`Proposal to Client`** — Commercial & Technical Proposal to Client
6. **`Proposal from Supplier`** — Supplier Quotation / Proposal from Supplier

- **Special Project Workflows:** Extra documents appearing only in special workflows in the presentation (such as client Purchase Orders in Slides 13/21) are **not** silently forced onto standard projects. Additional workflow definitions can be added in `config/marketing.php` under `workflows` without schema changes.
- **Upload Completeness vs PM Approval:** Uploading a checklist file sets its checklist status to **`Uploaded`** (`v1`) or **`Replaced`** (`v2+`, with all prior versions preserved in `project_documents` and `storage/documents/`). It never marks a document as `"Approved"` because document verification belongs to **Project Management**.
- **No JOM Assignment in Marketing:** Marketing projects use internal registry codes (`PRJ-YYYYMMDD-XXXXXX`). JOM (Job Order Memorandum) number issuance is deferred strictly to the Project Management module after verification.

### B. Workflow Stages & Handoff Rules
- **`Draft`** -> Initial registration saved by Marketing (or Admin).
- **`Preparing Documents`** -> Triggered explicitly via **Start Document Preparation** or automatically on first Marketing checklist upload.
- **`Submitted for Document Verification`** -> Enabled only when all 6 required Marketing documents are uploaded (enforced on the server). Routes `current_department_id` to `PM`, locks Marketing edits/uploads while under PM review, records `project_workflow_histories` + `activity_logs`, and sends in-app notifications to active `PM` users.
- **`Returned for Corrections`** -> Minimal shared PM handoff integration (`POST /projects/{id}/return-to-marketing`) restricted strictly to `PM` users and `Administrator` (Marketing users receive `403`). Stores `return_reason` and optional `attention_documents`, routes custody back to `MKT`, notifies active `MKT` users, and unlocks Marketing edits/replacements.
- **`Resubmitted for Verification`** -> Recorded when Marketing replaces/completes documents on a returned project and resubmits to `PM`.

### C. Shared Marketing Team Access Policy
- All active **Marketing (`MKT`)** users share team-wide visibility and collaboration rights on Marketing-originated projects (`origin_department_id = MKT`), allowing any authorized Marketing specialist to upload checklist items, review PM return notes, upload `v2+` replacements, and resubmit.
- **Project Management (`PM`)** users can view projects routed to `PM` (`current_department_id = PM`), download checklist documents for review, and return incomplete packages to Marketing.
- Unrelated departments (`OPS`, `LOG`, `OPFIN`, `ADMFIN`) are blocked (`403`) from `/marketing/*` routes, Marketing project views, and Marketing document downloads.
- **Administrators** retain full cross-department visibility and audit access.

---

## 5. Administrator Lockout Prevention & Recovery Procedure

If all administrator accounts are ever accidentally deactivated directly in the database or if an administrator password is lost, run the server-side CLI recovery tool from `C:\xampp\htdocs\BMS`:

```powershell
# 1. List existing Administrator accounts and their status
C:\xampp\php\php.exe scripts/manage-admin.php list

# 2. Reactivate an Administrator account (and optionally reset its password)
C:\xampp\php\php.exe scripts/manage-admin.php recover --username=admin --password="NewStrongPassword2026!"

# 3. Or create a brand-new active Administrator account
C:\xampp\php\php.exe scripts/manage-admin.php create --username=recovery_admin --name="Recovery Admin" --password="NewStrongPassword2026!"
```

---

## 6. Automated Verification Suite

With XAMPP **Apache** and **MySQL** running, execute the automated integration and security test suite:

```powershell
C:\xampp\php\php.exe tests/run_tests.php
```

---

## 7. Manual Testing Checklist

### System Foundation Checks
- [ ] **1. Database & Seed Verification:** Confirm `business_management_system` contains `departments`, `roles`, `permissions`, `role_permissions`, `users`, `projects`, `project_documents`, `project_workflow_histories`, `notifications`, and `activity_logs`, with all 6 departments (`MKT`, `PM`, `OPS`, `LOG`, `OPFIN`, `ADMFIN`).
- [ ] **2. Protected File Blocking:** Visit `http://localhost/BMS/.env`, `/BMS/database/schema.sql`, and `/BMS/storage/documents/` and verify HTTP **403 Forbidden**.
- [ ] **3. Authentication & User Management:** Verify invalid logins show generic errors, valid login regenerates session ID, `admin` can create/edit/deactivate `MKT`, `PM`, and `OPS` users, and inactive users cannot log in.

### Marketing Module & Handoff Checks
- [ ] **4. Dedicated Marketing Dashboard:** Sign in as a `MKT` user -> confirm `/BMS/dashboard` and `/BMS/marketing/dashboard` display live database counts (Total Marketing Projects, Draft, Awaiting Doc Prep, Submitted to PM, Returned, Missing Documents) and paginated projects.
- [ ] **5. Draft Registration & Validation:** Submit `/BMS/marketing/projects/create` with blank required fields -> confirm validation errors and preserved input. Submit valid details -> confirm unique `PRJ-*` code, `Draft` status, and no JOM number issued.
- [ ] **6. Document Checklist & Upload Security:** On `/BMS/projects/{id}`, attempt uploading a `.php` file renamed to `.pdf` -> confirm rejection. Upload 5 of the 6 required documents (`SON`, `SLD`, `BOM`, `CA`, `Proposal to Client`) -> confirm auto-transition to `Preparing Documents`, `5/6` progress, `Uploaded` status (not `Approved`), and `Proposal from Supplier` marked `Missing`.
- [ ] **7. Server-Side Completeness Gate:** Attempt submitting to PM while `Proposal from Supplier` is missing -> confirm server blocks submission. Upload `Proposal from Supplier` (`6/6`) and submit -> confirm transition to `Submitted for Document Verification` and custody transfer to `PM`.
- [ ] **8. PM Verification Queue & Return for Corrections:** Sign in as a `PM` user -> confirm unread notification alert and project visibility in **Marketing Document Verification Queue** on `/BMS/dashboard`. Open the project, flag `CA`, enter a return reason, and click **Return Project to Marketing for Corrections** -> confirm transition to `Returned for Corrections` (also verify `MKT` users get `403` if attempting to call `/return-to-marketing`).
- [ ] **9. Document Version Replacement & Resubmission:** Sign back in as a `MKT` user -> confirm return alert banner, reason, and `Needs Attention` badge on `CA`. Click **Replace** on `CA` and upload a revised PDF -> confirm status becomes `Replaced` (`v2`), **History (1)** shows `v1` still preserved and downloadable, and clicking **Resubmit for Verification** transitions status to `Resubmitted for Verification`.
- [ ] **10. Department Isolation, CSRF & Audit Trail:** Sign in as an `OPS` user -> confirm `403` on `/BMS/marketing/dashboard`, `/BMS/projects/{id}`, and document download URLs. Sign in as `admin` -> verify full `Workflow Stage History` and `Activity Logs`.
