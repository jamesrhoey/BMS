<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Core\Database;
use App\Core\Request;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectWorkflowHistory;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\MarketingChecklistService;
use App\Services\OperationsService;
use App\Services\ProjectService;

Bootstrap::init(dirname(__DIR__));

echo "========================================================\n";
echo "OPERATIONS (OPS) DEPARTMENT WORKFLOW TEST SUITE\n";
echo "========================================================\n\n";

$db = Database::connection();
$request = new Request();

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $description\n";
        $passed++;
    } else {
        echo "[FAIL] $description" . ($detail !== '' ? ": $detail" : '') . "\n";
        $failed++;
    }
}

// 1. Departments
$opsDept = Department::findByCode(Department::CODE_OPERATIONS);
$pmDept = Department::findByCode(Department::CODE_PROJECT_MANAGEMENT);
$mktDept = Department::findByCode(Department::CODE_MARKETING);
$afinDept = Department::findByCode(Department::CODE_ADMIN_FINANCE);
$opfinDept = Department::findByCode(Department::CODE_OPERATION_FINANCE);

// 2. Fetch Users
$adminRow = $db->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.role_slug = 'administrator' AND u.status = 'active' LIMIT 1")->fetch();
$adminUser = $adminRow ? User::findById((int) $adminRow['id']) : null;

$mktRow = $db->query("SELECT u.id FROM users u WHERE u.department_id = {$mktDept['id']} AND u.status = 'active' LIMIT 1")->fetch();
$mktUser = $mktRow ? User::findById((int) $mktRow['id']) : $adminUser;

$pmRow = $db->query("SELECT u.id FROM users u WHERE u.department_id = {$pmDept['id']} AND u.status = 'active' LIMIT 1")->fetch();
$pmUser = $pmRow ? User::findById((int) $pmRow['id']) : $adminUser;

$opsRow = $db->query("SELECT u.id FROM users u WHERE u.department_id = {$opsDept['id']} AND u.status = 'active' LIMIT 1")->fetch();
$opsUser = $opsRow ? User::findById((int) $opsRow['id']) : $adminUser;

$testProjectIds = [];

// Helper function to mock upload documents
function mockUploadDoc(int $projectId, string $type, int $userId, int $version = 1): int {
    return ProjectDocument::create([
        'project_id'        => $projectId,
        'document_type'     => $type,
        'version_number'    => $version,
        'is_current'        => 1,
        'original_filename' => strtolower(str_replace(' ', '_', $type)) . '_test.pdf',
        'stored_filename'   => uniqid('doc_', true) . '.pdf',
        'file_path'         => 'storage/documents/test_' . uniqid() . '.pdf',
        'mime_type'         => 'application/pdf',
        'file_size'         => 1024 * 100,
        'review_status'     => 'verified',
        'uploaded_by'       => $userId,
    ]);
}

try {
    // -------------------------------------------------------------------------
    // SETUP: Create and route an MRC project to OPS
    // -------------------------------------------------------------------------
    $mrcDraft = ProjectService::createDraft([
        'project_name'     => 'OPS Test MRC Project ' . uniqid(),
        'client_name'      => 'Client Alpha Corp',
        'project_category' => 'MRC',
        'workflow_type'    => 'mrc',
    ], $mktUser, $request);
    $mrcId = (int) $mrcDraft['project_id'];
    $testProjectIds[] = $mrcId;

    // Upload required Marketing documents
    $mrcDocTypes = ['SON', 'SLD', 'BOM', 'CA', 'PROP', 'PROP-SUPPLIER'];
    foreach ($mrcDocTypes as $t) {
        mockUploadDoc($mrcId, $t, (int) $mktUser['id']);
    }

    // Marketing submits
    $freshMrc = Project::findById($mrcId);
    ProjectService::submitProject($freshMrc, $mktUser, $request);

    // PM verifies all & approves
    ProjectDocument::markAllCurrentVerified($mrcId, (int) $pmUser['id']);
    ProjectService::completeVerification(Project::findById($mrcId), [], $pmUser, $request);

    // PM uploads JOM
    $mrcJomDocId = mockUploadDoc($mrcId, 'JOM — Job Order Memorandum', (int) $pmUser['id']);
    Project::markJomUploaded($mrcId, $mrcJomDocId, (int) $pmUser['id']);

    // PM forwards to OPS
    $forwardMrcResult = ProjectService::pmForwardToOperations(Project::findById($mrcId), ['forward_notes' => 'Forwarding MRC to Operations'], $pmUser, $request);
    $freshMrc = Project::findById($mrcId);

    // SCENARIO 1: Automatic Project Receipt - MRC
    assertTest(
        'Scenario 1: OPS automatically receives MRC project forwarded by Project Management',
        $forwardMrcResult['success'] === true
        && in_array($freshMrc['status'], [Project::STATUS_FORWARDED_TO_OPS, 'Forwarded to OPS', 'Forwarded to Operations'], true)
        && !empty($freshMrc['forwarded_to_ops_at'])
        && (int) $freshMrc['current_department_id'] === (int) $opsDept['id']
        && in_array(strtolower((string) $freshMrc['project_category']), ['mrc'], true),
        "Status: {$freshMrc['status']}, Dept: {$freshMrc['current_department_id']}"
    );

    // -------------------------------------------------------------------------
    // SETUP: Create and route a Special Project to OPS
    // -------------------------------------------------------------------------
    $spDraft = ProjectService::createDraft([
        'project_name'     => 'OPS Test Special Project ' . uniqid(),
        'client_name'      => 'Client Beta Systems',
        'project_category' => 'Special Project',
        'workflow_type'    => 'special_project',
    ], $mktUser, $request);
    $spId = (int) $spDraft['project_id'];
    $testProjectIds[] = $spId;

    // Upload required Marketing documents (including PO)
    $spDocTypes = ['SON', 'SLD', 'BOM', 'CA', 'PROP', 'PROP-SUPPLIER', 'PO'];
    foreach ($spDocTypes as $t) {
        mockUploadDoc($spId, $t, (int) $mktUser['id']);
    }

    // Marketing submits
    $freshSp = Project::findById($spId);
    ProjectService::submitProject($freshSp, $mktUser, $request);

    // PM verifies all & approves
    ProjectDocument::markAllCurrentVerified($spId, (int) $pmUser['id']);
    ProjectService::completeVerification(Project::findById($spId), [], $pmUser, $request);

    // PM uploads JOM
    $spJomDocId = mockUploadDoc($spId, 'JOM — Job Order Memorandum', (int) $pmUser['id']);
    Project::markJomUploaded($spId, $spJomDocId, (int) $pmUser['id']);

    // PM forwards to OPS
    $forwardSpResult = ProjectService::pmForwardToOperations(Project::findById($spId), ['forward_notes' => 'Forwarding SP to Operations'], $pmUser, $request);
    $freshSp = Project::findById($spId);

    // SCENARIO 2: Automatic Project Receipt - Special Project
    assertTest(
        'Scenario 2: OPS automatically receives Special Project forwarded by Project Management',
        $forwardSpResult['success'] === true
        && in_array($freshSp['status'], [Project::STATUS_FORWARDED_TO_OPS, 'Forwarded to OPS', 'Forwarded to Operations'], true)
        && !empty($freshSp['forwarded_to_ops_at'])
        && (int) $freshSp['current_department_id'] === (int) $opsDept['id']
        && in_array(strtolower((string) $freshSp['project_category']), ['special_project', 'special project'], true),
        "Status: {$freshSp['status']}, Dept: {$freshSp['current_department_id']}"
    );

    // SCENARIO 3: Category-based handoff display for MRC (JOM, SON, BOM, SLD)
    $mrcHandoffJson = json_decode((string) ($freshMrc['ops_handoff_documents'] ?? '[]'), true);
    $mrcHandoffTypes = array_column($mrcHandoffJson, 'type');
    sort($mrcHandoffTypes);
    $expectedMrcTypes = ['BOM', 'JOM', 'SLD', 'SON'];
    sort($expectedMrcTypes);

    assertTest(
        'Scenario 3: Category-based handoff package for MRC contains exactly JOM, SON, BOM, SLD (4 docs)',
        $mrcHandoffTypes === $expectedMrcTypes
        && !empty($freshMrc['ops_jom_document_id'])
        && !empty($freshMrc['ops_son_document_id'])
        && !empty($freshMrc['ops_bom_document_id'])
        && !empty($freshMrc['ops_sld_document_id'])
        && empty($freshMrc['ops_po_document_id']),
        'Handoff types: ' . json_encode($mrcHandoffTypes)
    );

    // SCENARIO 4: Category-based handoff display for Special Project (JOM, PO, SON, BOM, SLD)
    $spHandoffJson = json_decode((string) ($freshSp['ops_handoff_documents'] ?? '[]'), true);
    $spHandoffTypes = array_column($spHandoffJson, 'type');
    sort($spHandoffTypes);
    $expectedSpTypes = ['BOM', 'JOM', 'PO', 'SLD', 'SON'];
    sort($expectedSpTypes);

    assertTest(
        'Scenario 4: Category-based handoff package for Special Project contains exactly JOM, PO, SON, BOM, SLD (5 docs)',
        $spHandoffTypes === $expectedSpTypes
        && !empty($freshSp['ops_jom_document_id'])
        && !empty($freshSp['ops_po_document_id'])
        && !empty($freshSp['ops_son_document_id'])
        && !empty($freshSp['ops_bom_document_id'])
        && !empty($freshSp['ops_sld_document_id']),
        'Handoff types: ' . json_encode($spHandoffTypes)
    );

    // SCENARIO 5: Received handoff documents are strictly read-only for OPS
    $canOpsUploadMktBom = AuthorizationService::canUploadDocument($opsUser, $freshMrc);
    assertTest(
        'Scenario 5: Marketing documents and PM JOM are read-only and cannot be altered by Operations',
        $canOpsUploadMktBom === false,
        'OPS user canUploadDocument returned: ' . ($canOpsUploadMktBom ? 'true' : 'false')
    );

    // SCENARIO 6: Marketing BOM vs Final BOM separation
    $mktBomDoc = ProjectDocument::findCurrentByType($mrcId, 'BOM');
    $opsFinalBomBefore = ProjectDocument::findCurrentFinalBom($mrcId);
    assertTest(
        'Scenario 6: Marketing BOM and Final BOM are maintained as separate records and columns',
        $mktBomDoc !== null
        && $opsFinalBomBefore === null
        && !empty($freshMrc['ops_bom_document_id'])
        && empty($freshMrc['ops_final_bom_document_id']),
        'Marketing BOM id: ' . ($mktBomDoc['id'] ?? 'null')
    );

    // SCENARIO 7: Marketing SLD vs Final SLD separation
    $mktSldDoc = ProjectDocument::findCurrentByType($mrcId, 'SLD');
    $opsFinalSldBefore = ProjectDocument::findCurrentFinalSld($mrcId);
    assertTest(
        'Scenario 7: Marketing SLD and Final SLD are maintained as separate records and columns',
        $mktSldDoc !== null
        && $opsFinalSldBefore === null
        && !empty($freshMrc['ops_sld_document_id'])
        && empty($freshMrc['ops_final_sld_document_id']),
        'Marketing SLD id: ' . ($mktSldDoc['id'] ?? 'null')
    );

    // SCENARIO 8: OPS can upload Final SLD
    $finalSldUpload = mockUploadDoc($mrcId, 'Final SLD — Single Line Diagram', (int) $opsUser['id'], 1);
    Project::markOpsDocumentUploaded($mrcId, 'ops_final_sld_document_id', $finalSldUpload, (int) $opsUser['id']);
    $freshMrc = Project::findById($mrcId);
    $currentFinalSld = ProjectDocument::findCurrentFinalSld($mrcId);

    assertTest(
        'Scenario 8: Operations can upload Final SLD with separate column and version tracking',
        $currentFinalSld !== null
        && (int) $freshMrc['ops_final_sld_document_id'] === $finalSldUpload
        && (int) $currentFinalSld['id'] === $finalSldUpload
        && !empty($freshMrc['ops_processed_at']),
        'Final SLD Doc ID: ' . ($currentFinalSld['id'] ?? 'null')
    );

    // SCENARIO 9: OPS can upload Final BOM
    $finalBomUpload = mockUploadDoc($mrcId, 'Final BOM — Bill of Materials', (int) $opsUser['id'], 1);
    Project::markOpsDocumentUploaded($mrcId, 'ops_final_bom_document_id', $finalBomUpload, (int) $opsUser['id']);
    $freshMrc = Project::findById($mrcId);
    $currentFinalBom = ProjectDocument::findCurrentFinalBom($mrcId);

    assertTest(
        'Scenario 9: Operations can upload Final BOM with separate column and version tracking',
        $currentFinalBom !== null
        && (int) $freshMrc['ops_final_bom_document_id'] === $finalBomUpload
        && (int) $currentFinalBom['id'] === $finalBomUpload,
        'Final BOM Doc ID: ' . ($currentFinalBom['id'] ?? 'null')
    );

    // SCENARIO 10: OPS can upload Request of PR
    $requestPrUpload = mockUploadDoc($mrcId, 'Request of PR — Purchase Requisition Request', (int) $opsUser['id'], 1);
    Project::markOpsDocumentUploaded($mrcId, 'ops_request_pr_document_id', $requestPrUpload, (int) $opsUser['id']);
    $freshMrc = Project::findById($mrcId);
    $currentRequestPr = ProjectDocument::findCurrentRequestPr($mrcId);

    assertTest(
        'Scenario 10: Operations can upload Request of PR and status advances to OPS Documents Complete',
        $currentRequestPr !== null
        && (int) $freshMrc['ops_request_pr_document_id'] === $requestPrUpload
        && (int) $currentRequestPr['id'] === $requestPrUpload
        && in_array($freshMrc['status'], [Project::STATUS_OPS_DOCUMENTS_COMPLETE, 'OPS Documents Complete'], true),
        "Status: {$freshMrc['status']}"
    );

    // SCENARIO 11: Document Replacement / Versioning for OPS uploads
    $finalBomV2 = ProjectDocument::create([
        'project_id'        => $mrcId,
        'document_type'     => 'Final BOM — Bill of Materials',
        'version_number'    => 2,
        'is_current'        => 1,
        'original_filename' => 'final_bom_v2.xlsx',
        'stored_filename'   => uniqid('doc_', true) . '.xlsx',
        'file_path'         => 'storage/documents/test_' . uniqid() . '.xlsx',
        'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'file_size'         => 1024 * 150,
        'review_status'     => 'uploaded',
        'uploaded_by'       => (int) $opsUser['id'],
    ]);
    // Archive V1
    $db->prepare("UPDATE project_documents SET is_current = 0 WHERE id = :id")->execute(['id' => $finalBomUpload]);
    Project::markOpsDocumentUploaded($mrcId, 'ops_final_bom_document_id', $finalBomV2, (int) $opsUser['id']);
    $currentFinalBom = ProjectDocument::findCurrentFinalBom($mrcId);

    assertTest(
        'Scenario 11: Operations document replacement increments version and preserves previous version history',
        (int) $currentFinalBom['id'] === $finalBomV2
        && (int) $currentFinalBom['version_number'] === 2,
        'Current BOM v: ' . ($currentFinalBom['version_number'] ?? 'null')
    );

    // SCENARIO 12: Block Submit to OPS Finance if Final SLD missing
    // Test on Special Project where none of the 3 OPS docs are uploaded yet
    $canSubmitSpBefore = AuthorizationService::canSubmitToOpsFinance($opsUser, $freshSp);
    assertTest(
        'Scenario 12: Submission to OPS Finance blocked when Final SLD is missing',
        $canSubmitSpBefore === false,
        'canSubmitToOpsFinance: ' . ($canSubmitSpBefore ? 'true' : 'false')
    );

    // SCENARIO 13: Block Submit to OPS Finance if Final BOM missing
    $spSld = mockUploadDoc($spId, 'Final SLD — Single Line Diagram', (int) $opsUser['id']);
    Project::markOpsDocumentUploaded($spId, 'ops_final_sld_document_id', $spSld, (int) $opsUser['id']);
    $canSubmitSpOnlySld = AuthorizationService::canSubmitToOpsFinance($opsUser, Project::findById($spId));
    assertTest(
        'Scenario 13: Submission to OPS Finance blocked when Final BOM is missing',
        $canSubmitSpOnlySld === false,
        'canSubmitToOpsFinance: ' . ($canSubmitSpOnlySld ? 'true' : 'false')
    );

    // SCENARIO 14: Block Submit to OPS Finance if Request of PR missing
    $spBom = mockUploadDoc($spId, 'Final BOM — Bill of Materials', (int) $opsUser['id']);
    Project::markOpsDocumentUploaded($spId, 'ops_final_bom_document_id', $spBom, (int) $opsUser['id']);
    $canSubmitSpNoPr = AuthorizationService::canSubmitToOpsFinance($opsUser, Project::findById($spId));
    assertTest(
        'Scenario 14: Submission to OPS Finance blocked when Request of PR is missing',
        $canSubmitSpNoPr === false,
        'canSubmitToOpsFinance: ' . ($canSubmitSpNoPr ? 'true' : 'false')
    );

    // SCENARIO 15: Successful submission to OPS Finance when all 3 docs present
    $spPr = mockUploadDoc($spId, 'Request of PR — Purchase Requisition Request', (int) $opsUser['id']);
    Project::markOpsDocumentUploaded($spId, 'ops_request_pr_document_id', $spPr, (int) $opsUser['id']);
    $freshSpReady = Project::findById($spId);

    $submitResult = OperationsService::submitToOpsFinance($freshSpReady, $opsUser, $request, [
        'notes' => 'Official engineering handoff to OPS Finance for actual costing and supplier PR.',
    ]);
    $freshSpSubmitted = Project::findById($spId);

    assertTest(
        'Scenario 15: Successful submission to OPS Finance records submission timestamp, submitter, notes, and changes status',
        $submitResult['success'] === true
        && in_array($freshSpSubmitted['status'], [Project::STATUS_SUBMITTED_TO_OPS_FINANCE, 'Submitted to OPS Finance'], true)
        && !empty($freshSpSubmitted['submitted_to_opfin_at'])
        && (int) $freshSpSubmitted['submitted_to_opfin_by'] === (int) $opsUser['id']
        && !empty($freshSpSubmitted['opfin_handoff_documents'])
        && (int) $freshSpSubmitted['current_department_id'] === (int) $opfinDept['id'],
        "Status: {$freshSpSubmitted['status']}, Dept: {$freshSpSubmitted['current_department_id']}"
    );

    // SCENARIO 16: Read-only lock after submission to OPS Finance
    $canUploadAfterSubmit = AuthorizationService::canUploadOpsDocument($opsUser, $freshSpSubmitted);
    $canReSubmit = AuthorizationService::canSubmitToOpsFinance($opsUser, $freshSpSubmitted);

    assertTest(
        'Scenario 16: Project is locked in read-only state for Operations after submission to OPS Finance',
        $canUploadAfterSubmit === false
        && $canReSubmit === false,
        "canUpload: " . ($canUploadAfterSubmit ? 'true' : 'false') . ", canReSubmit: " . ($canReSubmit ? 'true' : 'false')
    );

    // SCENARIO 17: Unauthorized users (Marketing) blocked from OPS operations
    $canMktUploadOps = AuthorizationService::canUploadOpsDocument($mktUser, $freshMrc);
    $canMktSubmitOpfin = AuthorizationService::canSubmitToOpsFinance($mktUser, $freshMrc);

    assertTest(
        'Scenario 17: Unauthorized users (Marketing) are strictly blocked from Operations uploads and OPS Finance submission',
        $canMktUploadOps === false
        && $canMktSubmitOpfin === false,
        "canMktUpload: " . ($canMktUploadOps ? 'true' : 'false') . ", canMktSubmit: " . ($canMktSubmitOpfin ? 'true' : 'false')
    );

} finally {
    echo "\nCleaning up test project records...\n";
    foreach ($testProjectIds as $pid) {
        $db->prepare("DELETE FROM project_documents WHERE project_id = :id")->execute(['id' => $pid]);
        $db->prepare("DELETE FROM project_workflow_history WHERE project_id = :id")->execute(['id' => $pid]);
        $db->prepare("DELETE FROM projects WHERE id = :id")->execute(['id' => $pid]);
    }
}

echo "\n========================================================\n";
echo "TEST RESULTS: Passed: $passed, Failed: $failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
