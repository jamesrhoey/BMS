# ANTIGRAVITY IMPLEMENTATION PROMPT — OPS FINANCE DEPARTMENT

You are continuing development of the existing Business Management System built using native PHP, MySQL/MariaDB, PDO, Bootstrap 5, and MVC architecture.

Implement the **OPS Finance Department module** according to the official project workflow.

## 1. CRITICAL INSTRUCTIONS

Before coding:

1. Inspect the existing application and completed Marketing, Project Management, and OPS modules.
2. Review the existing database schema, project records, document records, workflow assignments, authentication, permissions, routes, controllers, models, and sidebar.
3. Reuse existing functionality and database structures wherever possible.
4. Do not rebuild the application.
5. Do not create duplicate project records or department modules.
6. Do not delete or damage existing records.
7. Use safe database migrations when necessary.
8. Follow the existing PHP MVC architecture and Bootstrap 5 design.

Implement only OPS Finance functionality in this task.

Do not implement the final Admin Finance Purchase Order functionality yet.

## 2. OFFICIAL WORKFLOW

The official workflow leading to OPS Finance is:

1. Marketing creates an MRC or Special Project and uploads its required documents.
2. Marketing submits the project to Project Management and Admin Finance simultaneously.
3. Project Management verifies the Marketing documents.
4. Project Management uploads the JOM file and forwards the project to OPS with category-specific documents.
5. OPS automatically receives the project.
6. OPS uploads:
   - FINAL SLD
   - FINAL BOM
   - REQUEST OF PR
7. OPS submits the project to OPS Finance.
8. OPS Finance automatically receives the categorized project.
9. OPS Finance uploads:
   - PR
   - CANVASSING SHEETS
10. OPS Finance submits the project to Admin Finance.

IMPORTANT:
- OPS Finance must receive projects only after OPS submits them.
- Do not require OPS Finance users to manually register projects.
- Use the existing project record.
- Preserve the project category.
- Do not confuse REQUEST OF PR uploaded by OPS with PR uploaded by OPS Finance.
- Do not confuse Marketing BOM with Final BOM.

## 3. OPS FINANCE SIDEBAR

Create an expandable/collapsible sidebar dropdown named **OPS Finance**.

Submenus:

- Dashboard
- Incoming Projects
- MRC Projects
- Special Projects
- Project Documents
- Pending PR Upload
- Pending Canvassing Sheets
- Submitted to Admin Finance
- Project History

Follow the existing sidebar styling and permissions.

The dropdown must:
- Expand and collapse.
- Highlight the active submenu.
- Remain expanded when navigating within OPS Finance, if supported.
- Avoid duplicate sidebar entries.

## 4. OPS FINANCE DASHBOARD

Create a dashboard using real database records.

Display:

- Total projects received
- MRC projects
- Special Projects
- Pending processing
- Projects awaiting PR
- Projects awaiting Canvassing Sheets
- Projects with complete OPS Finance documents
- Projects submitted to Admin Finance

Provide filters for:

- Project category
- Project status
- Received date
- Project name
- Project reference number

Do not use hardcoded statistics.

## 5. AUTOMATIC PROJECT RECEIPT

When OPS submits a completed project to OPS Finance:

1. Use the existing project record.
2. Create or activate the appropriate OPS Finance department assignment.
3. Make the project automatically appear in OPS Finance's Incoming Projects queue.
4. Preserve its MRC or Special Project category.
5. Record the OPS submission timestamp and submitting user.
6. Display the documents included in the OPS handoff.
7. Record the workflow event.

Prevent duplicate assignments and duplicate receipts.

OPS Finance must not receive projects that have not been submitted by OPS.

Do not create a new project record.

## 6. CATEGORY-BASED INCOMING PROJECTS

Display the project category clearly:

- MRC
- Special Project

The project details page must display the documents received from previous departments.

### Documents from Marketing / Project Management

For MRC:
- JOM
- SON
- Marketing BOM
- Marketing SLD

For Special Project:
- JOM
- Marketing PO
- SON
- Marketing BOM
- Marketing SLD

### Documents from OPS

Both categories:
- FINAL SLD
- FINAL BOM
- REQUEST OF PR

All previously uploaded documents must be viewable or downloadable according to permissions.

These received documents must be read-only for OPS Finance.

OPS Finance must not replace, delete, or modify documents uploaded by Marketing, Project Management, or OPS.

## 7. OPS FINANCE PROJECT DETAILS PAGE

Create a detailed project page displaying:

- Project reference number
- Project name
- Client name
- Project category
- Project description or scope
- Marketing submission information
- Project Management verification and approval information
- JOM file information
- OPS receipt information
- OPS document submission information
- Current workflow status

Organize documents into distinct sections:

1. Marketing Documents
2. Project Management Documents
3. OPS Documents
4. OPS Finance Documents

Keep every document type separate.

## 8. OPS FINANCE DOCUMENT UPLOADS

OPS Finance must upload two required document types.

### A. PR — PURCHASE REQUEST

Description: Purchase Request document uploaded by OPS Finance.

IMPORTANT:
This is separate from the REQUEST OF PR document uploaded by OPS.

Use separate document type identifiers:

- REQUEST_OF_PR — uploaded by OPS
- PR — uploaded by OPS Finance

Do not overwrite or replace one with the other.

### B. CANVASSING SHEETS

Description: Canvassing Sheets associated with the project.

Allow the user to upload the required file or files according to the existing document upload architecture.

If multiple canvassing sheets are uploaded, preserve each file as a separate document record.

For each uploaded document, display:

- Document type
- Original filename
- Uploaded filename
- Uploaded by
- Upload date and time
- File size, if available
- View/download action
- Replace action, if authorized

Use the existing secure upload system.

Security requirements:
- Validate file extensions and MIME types.
- Apply reasonable file-size limits.
- Generate unique server-side filenames.
- Prevent executable file uploads.
- Prevent path traversal.
- Store files outside publicly executable directories where possible.
- Serve downloads through authenticated controllers.

## 9. DOCUMENT VALIDATION

Before allowing OPS Finance to submit the project to Admin Finance, require:

- PR
- CANVASSING SHEETS

If either required document is missing:

- Display the missing document.
- Prevent submission.
- Allow authorized OPS Finance users to upload it.

The validation must apply to both MRC and Special Project.

Do not add additional required OPS Finance documents based on assumptions.

Do not allow users to bypass validation using manually crafted HTTP requests.

## 10. SUBMIT TO ADMIN FINANCE

Once both required OPS Finance documents have been uploaded, display a **Submit to Admin Finance** action.

Before submission, show a confirmation summary containing:

- Project name
- Project reference number
- Project category
- Documents received from Marketing / Project Management
- Documents received from OPS
- PR uploaded by OPS Finance
- Canvassing Sheets uploaded by OPS Finance

When confirmed:

1. Create the Admin Finance department assignment for the existing project.
2. Record the submitting OPS Finance user.
3. Record the submission timestamp.
4. Change the project status to **Submitted to Admin Finance**.
5. Make the project appear in Admin Finance's Incoming Projects queue.
6. Record the exact document references included in the handoff.
7. Prevent duplicate submission.

Use a database transaction so the submission event and Admin Finance assignment are saved consistently.

If submission fails, roll back the transaction and show a useful error message.

IMPORTANT:
- Do not create a separate project record for Admin Finance.
- Do not issue or upload the Purchase Order to Supplier in this task.
- Admin Finance will handle that in its own implementation stage.

## 11. PROJECT STATUS MANAGEMENT

Reuse existing workflow statuses wherever possible.

Support the following relevant statuses:

- Submitted to OPS Finance
- Received by OPS Finance
- Under OPS Finance Processing
- Awaiting PR
- Awaiting Canvassing Sheets
- OPS Finance Documents Complete
- Submitted to Admin Finance

Ensure that status changes follow the official workflow.

OPS Finance users must not manually assign statuses belonging to other departments.

## 12. DATABASE AND AUDIT REQUIREMENTS

Reuse the existing database structure wherever possible.

Ensure the system can record:

- OPS Finance department assignment
- OPS Finance receipt date
- PR document record
- Canvassing Sheets document records
- Document versions
- Uploaded by
- Upload timestamps
- Submission event
- Admin Finance assignment
- Forwarding history
- Workflow history
- Audit history

Record audit events for:

- Project received by OPS Finance
- PR uploaded
- Canvassing Sheets uploaded
- Documents replaced
- Project submitted to Admin Finance

Preserve all historical documents and workflow events.

Use database transactions for submission and assignment creation.

## 13. ACCESS CONTROL AND SECURITY

OPS Finance users:
- Can view projects assigned to OPS Finance.
- Can view documents from Marketing, Project Management, and OPS.
- Can upload PR.
- Can upload Canvassing Sheets.
- Can submit completed projects to Admin Finance.
- Cannot modify Marketing documents.
- Cannot modify the JOM file.
- Cannot modify OPS documents.
- Cannot issue the Purchase Order to Supplier.
- Cannot approve or modify another department's workflow actions.

Administrators retain their existing system-wide permissions.

Enforce authorization on every route, controller action, upload, download, and submission operation.

## 14. USER INTERFACE

Use the existing Bootstrap 5 design.

Ensure:

- Responsive project lists.
- Clear MRC and Special Project labels.
- Separate document sections.
- Clear document completion indicators.
- PR upload field.
- Canvassing Sheets upload field.
- Upload progress and feedback.
- Confirmation before submission.
- Search, filters, and pagination.
- Clear success and error messages.

Do not introduce a separate design system.

## 15. TESTING REQUIREMENTS

Test these scenarios:

1. OPS Finance receives an MRC project after OPS submits it.
2. OPS Finance receives a Special Project after OPS submits it.
3. Projects do not appear in OPS Finance before OPS submission.
4. All documents from previous departments remain accessible.
5. OPS Finance cannot modify documents uploaded by previous departments.
6. OPS Finance can upload PR.
7. OPS Finance can upload Canvassing Sheets.
8. REQUEST OF PR and PR remain separate document records.
9. Multiple Canvassing Sheets can be stored without overwriting each other.
10. Submission is blocked if PR is missing.
11. Submission is blocked if Canvassing Sheets are missing.
12. A complete project can be submitted to Admin Finance.
13. Admin Finance receives the same project record automatically.
14. Duplicate submissions and assignments are prevented.
15. Unauthorized users cannot upload or submit OPS Finance documents.
16. Existing Marketing, Project Management, and OPS functionality remains intact.

## 16. FINAL DELIVERABLE

After implementation, provide:

1. Summary of files created or modified.
2. Database changes and migration details.
3. Explanation of automatic OPS Finance project receipt.
4. Explanation of category-based document visibility.
5. Explanation of PR and Canvassing Sheets uploads.
6. Explanation of submission to Admin Finance.
7. Tests performed and results.
8. Any unresolved issues or dependencies.

## STRICT SCOPE LIMIT

Implement only the OPS Finance Department module.

Do not implement the Admin Finance Purchase Order to Supplier functionality yet.

Do not rebuild the existing application.

Do not delete existing data or functionality.

Stop after OPS Finance is implemented and tested. Wait for the next department implementation prompt.