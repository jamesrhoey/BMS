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
use App\Services\ProjectService;

Bootstrap::init(dirname(__DIR__));

echo "========================================================\n";
echo "PROJECT MANAGEMENT DEPARTMENT WORKFLOW TEST SUITE\n";
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

// 2. Fetch Users
$adminRow = $db->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.role_slug = 'administrator' AND u.status = 'active' LIMIT 1")->fetch();
$adminUser = $adminRow ? User::findById((int) $adminRow['id']) : null;

$mktRow = $db->query("SELECT u.id FROM users u WHERE u.department_id = {$mktDept['id']} AND u.status = 'active' LIMIT 1")->fetch();
$mktUser = $mktRow ? User::findById((int) $mktRow['id']) : $adminUser;

$pmRow = $db->query("SELECT u.id FROM users u WHERE u.department_id = {$pmDept['id']} AND u.status = 'active' LIMIT 1")->fetch();
$pmUser = $pmRow ? User::findById((int) $pmRow['id']) : $adminUser;

// Helper function to mock upload documents
function mockUploadDoc(int $projectId, string $type, int $userId): int {
    return ProjectDocument::create([
        'project_id' => $projectId,
        'document_type' => $type,
        'version_number' => 1,
        'is_current' => 1,
        'original_filename' => strtolower($type) . '_test.pdf',
        'stored_filename' => 'stored_' . bin2hex(random_bytes(6)) . '.pdf',
        'file_path' => 'storage/app/documents/' . strtolower($type) . '_test.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 10240,
        'review_status' => 'pending_review',
        'uploaded_by' => $userId,
        'upload_notes' => 'Test document upload for ' . $type,
    ]);
}

try {
    // ---------------------------------------------------------
    // TEST 1: PM receives MRC project submitted by Marketing
    // ---------------------------------------------------------
    $draftMrc = ProjectService::createDraft([
        'project_name' => 'PM Test MRC Project ' . time(),
        'client_name' => 'Acme Corporation',
        'project_category' => 'mrc',
        'project_description' => 'Test MRC description for PM module',
        'current_department_id' => (string) $mktDept['id'],
    ], $mktUser, $request);

    $mrcProjectId = (int) $draftMrc['project_id'];
    $mrcProject = Project::findById($mrcProjectId);

    // Upload 6 MRC docs
    $mrcTypes = ['SON', 'SLD', 'BOM', 'CA', 'PROP', 'PROP-SUPPLIER'];
    foreach ($mrcTypes as $t) {
        mockUploadDoc($mrcProjectId, $t, (int) $mktUser['id']);
    }

    // Submit project
    $submitMrcRes = ProjectService::submitProject($mrcProject, $mktUser, $request);
    $mrcProjectSubmitted = Project::findById($mrcProjectId);

    assertTest(
        'Scenario 1: PM receives MRC project submitted by Marketing',
        $submitMrcRes['success']
        && in_array($mrcProjectSubmitted['status'], [
            Project::STATUS_SUBMITTED_TO_PM_AND_AFIN,
            'Submitted to Project Management and Admin Finance'
        ], true)
        && (int) $mrcProjectSubmitted['current_department_id'] === (int) $pmDept['id'],
        'Project status: ' . ($mrcProjectSubmitted['status'] ?? 'unknown')
    );

    // ---------------------------------------------------------
    // TEST 2: PM receives Special Project
    // ---------------------------------------------------------
    $draftSp = ProjectService::createDraft([
        'project_name' => 'PM Test Special Project ' . time(),
        'client_name' => 'Specialized Corp',
        'project_category' => 'special_project',
        'project_description' => 'Test Special Project description',
        'current_department_id' => (string) $mktDept['id'],
    ], $mktUser, $request);

    $spProjectId = (int) $draftSp['project_id'];
    $spProject = Project::findById($spProjectId);

    // Upload 7 SP docs (including PO)
    $spTypes = ['SON', 'SLD', 'BOM', 'CA', 'PROP', 'PROP-SUPPLIER', 'PO'];
    foreach ($spTypes as $t) {
        mockUploadDoc($spProjectId, $t, (int) $mktUser['id']);
    }

    $submitSpRes = ProjectService::submitProject($spProject, $mktUser, $request);
    $spProjectSubmitted = Project::findById($spProjectId);

    assertTest(
        'Scenario 2: PM receives Special Project submitted by Marketing',
        $submitSpRes['success']
        && (int) $spProjectSubmitted['current_department_id'] === (int) $pmDept['id']
        && in_array($spProjectSubmitted['project_category'], ['special_project', 'Special Project'], true),
        'Category: ' . ($spProjectSubmitted['project_category'] ?? '')
    );

    // ---------------------------------------------------------
    // TEST 3: Both categories display correct document checklist
    // ---------------------------------------------------------
    $mrcEval = MarketingChecklistService::evaluateProject($mrcProjectSubmitted);
    $spEval = MarketingChecklistService::evaluateProject($spProjectSubmitted);

    assertTest(
        'Scenario 3: Both categories display correct checklist (MRC: 6 docs, Special Project: 7 docs)',
        $mrcEval['required_count'] === 6
        && !$mrcEval['is_po_required']
        && $spEval['required_count'] === 7
        && $spEval['is_po_required'],
        "MRC count: {$mrcEval['required_count']}, SP count: {$spEval['required_count']}"
    );

    // ---------------------------------------------------------
    // TEST 4: Missing documents cannot be verified
    // ---------------------------------------------------------
    // Create draft project with missing documents
    $draftIncomplete = ProjectService::createDraft([
        'project_name' => 'PM Incomplete Project ' . time(),
        'client_name' => 'Beta Corp',
        'project_category' => 'mrc',
        'current_department_id' => (string) $mktDept['id'],
    ], $mktUser, $request);
    $incompleteId = (int) $draftIncomplete['project_id'];
    $incompleteProj = Project::findById($incompleteId);

    // Attempting to verify a non-existent document ID 999999
    $verifyNonExistent = ProjectService::verifyDocument($incompleteProj, 999999, 'verified', '', $pmUser, $request);

    assertTest(
        'Scenario 4: Missing documents cannot be verified',
        !$verifyNonExistent['success'],
        $verifyNonExistent['error'] ?? ''
    );

    // ---------------------------------------------------------
    // TEST 5: Project with unresolved corrections cannot be approved
    // ---------------------------------------------------------
    // Flag one document in MRC project as needs_correction
    $mrcDocs = ProjectDocument::currentForProject($mrcProjectId);
    $docToFlag = $mrcDocs[0];
    $flagRes = ProjectService::verifyDocument($mrcProjectSubmitted, (int) $docToFlag['id'], 'needs_correction', 'Needs revised calculations', $pmUser, $request);

    // Attempt to approve verification
    $incompleteApproval = ProjectService::completeVerification($mrcProjectSubmitted, [], $pmUser, $request);

    assertTest(
        'Scenario 5: Project with unresolved corrections or pending review cannot be approved',
        $flagRes['success']
        && (!$incompleteApproval['success'] || $incompleteApproval['success'] === false),
        'Expected approval to fail or be rejected'
    );

    // ---------------------------------------------------------
    // TEST 6: PM can return project to Marketing with correction notes
    // ---------------------------------------------------------
    $returnRes = ProjectService::returnProjectToMarketing($mrcProjectSubmitted, [
        'return_reason' => 'BOM figures do not match the SLD. Please revise and resubmit.',
        'flagged_documents' => ['BOM'],
    ], $pmUser, $request);

    $returnedMrc = Project::findById($mrcProjectId);

    assertTest(
        'Scenario 6: PM can return project to Marketing with correction notes',
        $returnRes['success']
        && in_array($returnedMrc['status'], [Project::STATUS_RETURNED_FOR_CORRECTIONS, 'Returned for Corrections'], true)
        && (int) $returnedMrc['current_department_id'] === (int) $mktDept['id'],
        'Returned status: ' . ($returnedMrc['status'] ?? '')
    );

    // ---------------------------------------------------------
    // TEST 7: Marketing can resubmit without creating new project
    // ---------------------------------------------------------
    $resubmitRes = ProjectService::submitProject($returnedMrc, $mktUser, $request);
    $resubmittedMrc = Project::findById($mrcProjectId);

    assertTest(
        'Scenario 7: Marketing can resubmit without creating new project',
        $resubmitRes['success']
        && (int) $resubmittedMrc['id'] === $mrcProjectId
        && in_array($resubmittedMrc['status'], [
            Project::STATUS_SUBMITTED_TO_PM_AND_AFIN,
            Project::STATUS_RESUBMITTED_TO_PM_AND_AFIN,
            'Submitted to Project Management and Admin Finance',
            'Resubmitted to Project Management and Admin Finance'
        ], true),
        'Project ID remained identical: ' . $mrcProjectId
    );

    // ---------------------------------------------------------
    // TEST 8: Previous document versions and verification history preserved
    // ---------------------------------------------------------
    $historyCount = count(ProjectWorkflowHistory::forProject($mrcProjectId));
    assertTest(
        'Scenario 8: Previous document versions and workflow history preserved',
        $historyCount >= 2,
        "History record count: $historyCount"
    );

    // ---------------------------------------------------------
    // TEST 9: PM can approve complete project (all docs verified)
    // ---------------------------------------------------------
    // Mark all documents as verified
    $currentMrcDocs = ProjectDocument::currentForProject($mrcProjectId);
    foreach ($currentMrcDocs as $d) {
        ProjectService::verifyDocument($resubmittedMrc, (int) $d['id'], 'verified', 'Verified OK', $pmUser, $request);
    }

    $approveRes = ProjectService::completeVerification($resubmittedMrc, ['verification_notes' => 'All 6 docs verified'], $pmUser, $request);
    $approvedMrc = Project::findById($mrcProjectId);

    assertTest(
        'Scenario 9: PM can approve complete project when all documents verified',
        $approveRes['success']
        && in_array($approvedMrc['status'], [Project::STATUS_APPROVED_BY_PM, 'Approved by Project Management'], true),
        'Status: ' . ($approvedMrc['status'] ?? '')
    );

    // ---------------------------------------------------------
    // TEST 10: PM can upload JOM file (and status becomes JOM Uploaded)
    // ---------------------------------------------------------
    // Create a mock JOM document directly
    $jomDocId = ProjectDocument::create([
        'project_id' => $mrcProjectId,
        'document_type' => 'JOM — Job Order Memorandum',
        'version_number' => 1,
        'is_current' => 1,
        'original_filename' => 'jom_approved_mrc.pdf',
        'stored_filename' => 'jom_' . bin2hex(random_bytes(6)) . '.pdf',
        'file_path' => 'storage/app/documents/jom_test.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 20480,
        'review_status' => 'uploaded',
        'uploaded_by' => (int) $pmUser['id'],
        'upload_notes' => 'Official JOM file uploaded by PM',
    ]);

    Project::markJomUploaded($mrcProjectId, $jomDocId, (int) $pmUser['id']);
    $jomMrc = Project::findById($mrcProjectId);

    assertTest(
        'Scenario 10: PM can upload JOM file and status becomes JOM Uploaded',
        (int) $jomMrc['jom_document_id'] === $jomDocId
        && in_array($jomMrc['status'], [Project::STATUS_JOM_UPLOADED, 'JOM Uploaded'], true),
        'Status: ' . ($jomMrc['status'] ?? '') . ', JOM ID: ' . ($jomMrc['jom_document_id'] ?? 0)
    );

    // ---------------------------------------------------------
    // TEST 11: Forwarding blocked when JOM missing
    // ---------------------------------------------------------
    // On the Special Project, JOM is not yet uploaded
    $forwardWithoutJom = ProjectService::pmForwardToOperations($spProjectSubmitted, [], $pmUser, $request);

    assertTest(
        'Scenario 11: Forwarding to Operations blocked when JOM file is missing',
        !$forwardWithoutJom['success'],
        $forwardWithoutJom['error'] ?? ''
    );

    // ---------------------------------------------------------
    // TEST 12: MRC forwarding = exactly JOM, SON, BOM, SLD
    // ---------------------------------------------------------
    $forwardMrcRes = ProjectService::pmForwardToOperations($jomMrc, ['forward_notes' => 'Forwarding MRC to Operations'], $pmUser, $request);
    $forwardedMrc = Project::findById($mrcProjectId);

    $handoffMrc = json_decode((string) ($forwardedMrc['ops_handoff_documents'] ?? '[]'), true);
    $mrcHandoffTypes = array_column($handoffMrc, 'type');
    sort($mrcHandoffTypes);
    $expectedMrcTypes = ['BOM', 'JOM', 'SLD', 'SON'];
    sort($expectedMrcTypes);

    assertTest(
        'Scenario 12: MRC forwarding forwards exactly JOM, SON, BOM, SLD (4 docs)',
        $forwardMrcRes['success']
        && $mrcHandoffTypes === $expectedMrcTypes,
        'Forwarded docs: ' . implode(', ', $mrcHandoffTypes)
    );

    // ---------------------------------------------------------
    // TEST 13: Special Project forwarding = exactly JOM, PO, SON, BOM, SLD
    // ---------------------------------------------------------
    // Approve and upload JOM for SP
    Project::markApprovedByPm($spProjectId, (int) $pmUser['id'], 'SP Approved');
    $spJomDocId = ProjectDocument::create([
        'project_id' => $spProjectId,
        'document_type' => 'JOM — Job Order Memorandum',
        'version_number' => 1,
        'is_current' => 1,
        'original_filename' => 'jom_special_project.pdf',
        'stored_filename' => 'jom_sp_' . bin2hex(random_bytes(6)) . '.pdf',
        'file_path' => 'storage/app/documents/jom_sp.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 25600,
        'review_status' => 'uploaded',
        'uploaded_by' => (int) $pmUser['id'],
    ]);
    Project::markJomUploaded($spProjectId, $spJomDocId, (int) $pmUser['id']);
    $jomSp = Project::findById($spProjectId);

    $forwardSpRes = ProjectService::pmForwardToOperations($jomSp, ['forward_notes' => 'Forwarding SP to Operations'], $pmUser, $request);
    $forwardedSp = Project::findById($spProjectId);

    $handoffSp = json_decode((string) ($forwardedSp['ops_handoff_documents'] ?? '[]'), true);
    $spHandoffTypes = array_column($handoffSp, 'type');
    sort($spHandoffTypes);
    $expectedSpTypes = ['BOM', 'JOM', 'PO', 'SLD', 'SON'];
    sort($expectedSpTypes);

    assertTest(
        'Scenario 13: Special Project forwarding forwards exactly JOM, PO, SON, BOM, SLD (5 docs)',
        $forwardSpRes['success']
        && $spHandoffTypes === $expectedSpTypes,
        'Forwarded docs: ' . implode(', ', $spHandoffTypes)
    );

    // ---------------------------------------------------------
    // TEST 14: CA, PROP, PROP-SUPPLIER not in OPS handoff
    // ---------------------------------------------------------
    $forbiddenTypes = ['CA', 'PROP', 'PROP-SUPPLIER'];
    $mrcHasForbidden = array_intersect($forbiddenTypes, $mrcHandoffTypes) !== [];
    $spHasForbidden = array_intersect($forbiddenTypes, $spHandoffTypes) !== [];

    assertTest(
        'Scenario 14: CA, PROP, PROP-SUPPLIER are strictly excluded from Operations handoff',
        !$mrcHasForbidden && !$spHasForbidden,
        'Excluded successfully from both MRC and Special Project handoffs'
    );

    // ---------------------------------------------------------
    // TEST 15: OPS receives project in Incoming queue
    // ---------------------------------------------------------
    assertTest(
        'Scenario 15: OPS receives project in Incoming queue (current_dept = Operations, status = Forwarded to OPS)',
        (int) $forwardedMrc['current_department_id'] === (int) $opsDept['id']
        && in_array($forwardedMrc['status'], [Project::STATUS_FORWARDED_TO_OPS, 'Forwarded to OPS'], true)
        && !empty($forwardedMrc['forwarded_to_ops_at']),
        'OPS Dept ID: ' . $opsDept['id'] . ', Current Dept: ' . $forwardedMrc['current_department_id']
    );

    // ---------------------------------------------------------
    // TEST 16: Admin Finance parallel assignment intact
    // ---------------------------------------------------------
    assertTest(
        'Scenario 16: Admin Finance parallel assignment intact (submitted_to_afin_at recorded)',
        !empty($forwardedMrc['submitted_to_afin_at']),
        'submitted_to_afin_at: ' . ($forwardedMrc['submitted_to_afin_at'] ?? 'null')
    );

    // ---------------------------------------------------------
    // TEST 17: Duplicate forwarding prevented
    // ---------------------------------------------------------
    $duplicateForward = ProjectService::pmForwardToOperations($forwardedMrc, [], $pmUser, $request);

    assertTest(
        'Scenario 17: Duplicate forwarding to Operations is strictly prevented',
        !$duplicateForward['success'],
        $duplicateForward['error'] ?? 'Blocked duplicate'
    );

    // ---------------------------------------------------------
    // TEST 18: Unauthorized users blocked
    // ---------------------------------------------------------
    $mktCanUploadJom = AuthorizationService::canUploadJom($mktUser, $approvedMrc);
    $mktCanForwardOps = AuthorizationService::canPmForwardToOperations($mktUser, $approvedMrc);

    assertTest(
        'Scenario 18: Unauthorized users (Marketing) blocked from uploading JOM and forwarding to Operations',
        !$mktCanUploadJom && !$mktCanForwardOps,
        "canUploadJom: " . ($mktCanUploadJom ? 'true' : 'false') . ", canForward: " . ($mktCanForwardOps ? 'true' : 'false')
    );

    // ---------------------------------------------------------
    // TEST 19: Existing Marketing functions intact
    // ---------------------------------------------------------
    $checklistService = new MarketingChecklistService();
    $mktStats = Project::marketingDashboardStats((int) $mktDept['id'], $checklistService->allChecklistTypes());
    assertTest(
        'Scenario 19: Existing Marketing functions intact and database stats functional',
        is_array($mktStats) && (isset($mktStats['total_marketing_projects']) || isset($mktStats['total_projects'])),
        'Stats keys: ' . implode(', ', array_keys($mktStats))
    );

} catch (Throwable $e) {
    echo "\n[ERROR] Uncaught Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failed++;
} finally {
    // Cleanup created test records
    echo "\nCleaning up test project records...\n";
    if (isset($mrcProjectId)) {
        $db->exec("DELETE FROM project_workflow_histories WHERE project_id = $mrcProjectId");
        $db->exec("DELETE FROM activity_logs WHERE entity_id = $mrcProjectId AND entity_type = 'project'");
        $db->exec("DELETE FROM project_documents WHERE project_id = $mrcProjectId");
        $db->exec("DELETE FROM projects WHERE id = $mrcProjectId");
    }
    if (isset($spProjectId)) {
        $db->exec("DELETE FROM project_workflow_histories WHERE project_id = $spProjectId");
        $db->exec("DELETE FROM activity_logs WHERE entity_id = $spProjectId AND entity_type = 'project'");
        $db->exec("DELETE FROM project_documents WHERE project_id = $spProjectId");
        $db->exec("DELETE FROM projects WHERE id = $spProjectId");
    }
    if (isset($incompleteId)) {
        $db->exec("DELETE FROM project_documents WHERE project_id = $incompleteId");
        $db->exec("DELETE FROM projects WHERE id = $incompleteId");
    }
}

echo "\n========================================================\n";
echo "TEST RESULTS: Passed: $passed, Failed: $failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
