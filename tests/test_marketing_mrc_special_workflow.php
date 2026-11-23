<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Core\Database;
use App\Core\Request;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Services\MarketingChecklistService;
use App\Services\ProjectService;

Bootstrap::init(dirname(__DIR__));

echo "========================================================\n";
echo "MARKETING MRC & SPECIAL PROJECT WORKFLOW TEST SUITE\n";
echo "========================================================\n\n";

$db = Database::connection();
$checklistService = new MarketingChecklistService();
$request = new Request();

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $description\n";
        $passed++;
    } else {
        echo "[FAIL] $description: $detail\n";
        $failed++;
    }
}

// Fetch a test user (e.g., Marketing user or Admin)
$userStmt = $db->query("SELECT u.id, u.username, u.full_name, r.role_slug, u.department_id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.role_slug IN ('marketing_specialist', 'administrator') LIMIT 1");
$testUser = $userStmt->fetch();
if (!$testUser) {
    die("Error: No test user found in database.\n");
}
$userId = (int) $testUser['id'];
$actor = User::findById($userId);
if (!$actor) {
    die("Error: User record could not be loaded via User::findById().\n");
}

echo "Running tests as User ID {$userId} ({$actor['username']} [{$actor['role_slug']}])\n\n";

// ---------------------------------------------------------
// TEST 1: Check existing projects integrity
// ---------------------------------------------------------
$stmt = $db->query("SELECT id, project_code, project_category, workflow_type FROM projects ORDER BY id ASC LIMIT 5");
$existingProjects = $stmt->fetchAll();
assertTest(
    "Existing projects are intact and have backfilled categories",
    count($existingProjects) >= 1 && !empty($existingProjects[0]['project_category']),
    "Count: " . count($existingProjects) . ", First Category: " . ($existingProjects[0]['project_category'] ?? 'none')
);

// ---------------------------------------------------------
// TEST 2: Checklist Service Category Definitions
// ---------------------------------------------------------
$mrcReqs = $checklistService->requiredDocumentTypes('mrc');
$spReqs = $checklistService->requiredDocumentTypes('special_project');

assertTest(
    "MRC requires exactly 6 documents",
    count($mrcReqs) === 6,
    "Expected 6, got " . count($mrcReqs)
);

assertTest(
    "MRC does NOT require PO",
    !$checklistService->isPoRequired('mrc') && !in_array('Purchase Order', $mrcReqs, true) && !in_array('Marketing PO', $mrcReqs, true),
    "PO found in MRC requirements"
);

assertTest(
    "Special Project requires exactly 7 documents",
    count($spReqs) === 7,
    "Expected 7, got " . count($spReqs)
);

assertTest(
    "Special Project DOES require Marketing PO",
    $checklistService->isPoRequired('special_project') && in_array('Marketing PO', $spReqs, true),
    "Marketing PO not found in Special Project requirements"
);

// ---------------------------------------------------------
// TEST 3: Create MRC Project
// ---------------------------------------------------------
$mrcPayload = [
    'project_name' => 'Automated Test MRC Project ' . time(),
    'client_name' => 'Acme Corporation',
    'project_location' => 'Makati City',
    'project_category' => 'MRC',
    'project_description' => 'Test MRC description'
];
$mrcCreateResult = ProjectService::createDraft($mrcPayload, $actor, $request);
$mrcProjectId = (int) ($mrcCreateResult['project_id'] ?? 0);
$mrcProject = Project::findById($mrcProjectId);

assertTest(
    "Created MRC Project has project_category = 'MRC' and workflow_type = 'mrc'",
    !empty($mrcProject) && $mrcProject['project_category'] === 'MRC' && $mrcProject['workflow_type'] === 'mrc',
    "Category: " . ($mrcProject['project_category'] ?? 'null') . ", Workflow: " . ($mrcProject['workflow_type'] ?? 'null')
);

// Verify evaluation before uploading documents
$mrcEvalInitial = MarketingChecklistService::evaluateProject($mrcProject);
assertTest(
    "New MRC project has 0/6 required documents and cannot be submitted",
    $mrcEvalInitial['required_count'] === 6 && $mrcEvalInitial['uploaded_required_count'] === 0 && !$mrcEvalInitial['is_complete'],
    "Uploaded: {$mrcEvalInitial['uploaded_required_count']}/{$mrcEvalInitial['required_count']}"
);

// ---------------------------------------------------------
// TEST 4: Create Special Project
// ---------------------------------------------------------
$spPayload = [
    'project_name' => 'Automated Test Special Project ' . (time() + 1),
    'client_name' => 'Stark Industries',
    'project_location' => 'BGC Taguig',
    'project_category' => 'Special Project',
    'project_description' => 'Test Special Project description'
];
$spCreateResult = ProjectService::createDraft($spPayload, $actor, $request);
$spProjectId = (int) ($spCreateResult['project_id'] ?? 0);
$spProject = Project::findById($spProjectId);

assertTest(
    "Created Special Project has project_category = 'Special Project' and workflow_type = 'special_project'",
    !empty($spProject) && $spProject['project_category'] === 'Special Project' && $spProject['workflow_type'] === 'special_project',
    "Category: " . ($spProject['project_category'] ?? 'null') . ", Workflow: " . ($spProject['workflow_type'] ?? 'null')
);

$spEvalInitial = MarketingChecklistService::evaluateProject($spProject);
assertTest(
    "New Special Project has 0/7 required documents and requires PO",
    $spEvalInitial['required_count'] === 7 && $spEvalInitial['is_po_required'] === true && in_array('Marketing PO', $spEvalInitial['missing_required_types'], true),
    "Required: {$spEvalInitial['required_count']}, PO Required: " . ($spEvalInitial['is_po_required'] ? 'true' : 'false')
);

// ---------------------------------------------------------
// Helper to mock document upload
// ---------------------------------------------------------
function mockUploadDocument(int $projectId, string $type, int $uploaderId, PDO $db): int {
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $type));
    return ProjectDocument::create([
        'project_id' => $projectId,
        'document_type' => $type,
        'version_number' => 1,
        'is_current' => 1,
        'original_filename' => "{$slug}_test.pdf",
        'stored_filename' => "test_{$slug}_" . uniqid() . ".pdf",
        'file_path' => "documents/projects/{$projectId}/{$slug}.pdf",
        'file_size' => 10240,
        'mime_type' => 'application/pdf',
        'review_status' => 'pending',
        'upload_notes' => 'Test mock upload',
        'uploaded_by' => $uploaderId
    ]);
}

// ---------------------------------------------------------
// TEST 5: Upload 6 MRC documents to MRC project and Submit
// ---------------------------------------------------------
$sixDocs = [
    'Service On Notice (SON)',
    'Single Line Diagram (SLD)',
    'Bill of Materials (BOM)',
    'Cost Analysis (CA)',
    'Proposal to Client',
    'Proposal from Supplier'
];
foreach ($sixDocs as $docType) {
    mockUploadDocument($mrcProjectId, $docType, $userId, $db);
}

$mrcEvalAfter = MarketingChecklistService::evaluateProject($mrcProject);
assertTest(
    "MRC project has all 6/6 required documents uploaded without PO",
    $mrcEvalAfter['uploaded_required_count'] === 6 && $mrcEvalAfter['is_complete'] === true,
    "Uploaded: {$mrcEvalAfter['uploaded_required_count']}/6, Complete: " . ($mrcEvalAfter['is_complete'] ? 'true' : 'false')
);

// Submit MRC Project
$mrcProjectFresh = Project::findById($mrcProjectId);
$mrcSubmitResult = ProjectService::submitProject($mrcProjectFresh, $actor, $request, 'MRC submission notes for PM and Admin Finance');
assertTest(
    "MRC Project submitted successfully without PO",
    ($mrcSubmitResult['success'] ?? false) === true && ($mrcSubmitResult['new_status'] ?? '') === 'Submitted to Project Management and Admin Finance',
    "Result: " . json_encode($mrcSubmitResult)
);

// Verify simultaneous submission columns for MRC project
$stmt = $db->prepare("SELECT status, submitted_at, submitted_by, submitted_to_afin_at, submitted_to_afin_by FROM projects WHERE id = :id");
$stmt->execute(['id' => $mrcProjectId]);
$mrcDbProject = $stmt->fetch();

assertTest(
    "MRC Project has simultaneous PM & Admin Finance submission timestamps and submitter IDs",
    !empty($mrcDbProject['submitted_at']) && !empty($mrcDbProject['submitted_to_afin_at']) &&
    (int)$mrcDbProject['submitted_by'] === $userId && (int)$mrcDbProject['submitted_to_afin_by'] === $userId,
    "PM at: {$mrcDbProject['submitted_at']}, AFIN at: {$mrcDbProject['submitted_to_afin_at']}"
);

// Verify workflow histories for PM and Admin Finance
$stmt = $db->prepare("SELECT action, to_department_id, notes FROM project_workflow_histories WHERE project_id = :id ORDER BY id ASC");
$stmt->execute(['id' => $mrcProjectId]);
$mrcHistories = $stmt->fetchAll();

$hasPmWorkflow = false;
$hasAfinWorkflow = false;
foreach ($mrcHistories as $h) {
    if (in_array($h['action'], ['submitted_for_verification', 'submitted_to_project_management'], true)) {
        $hasPmWorkflow = true;
    }
    if (in_array($h['action'], ['submitted_to_admin_finance'], true)) {
        $hasAfinWorkflow = true;
    }
}
assertTest(
    "Simultaneous workflow history records created for both Project Management and Admin Finance",
    $hasPmWorkflow && $hasAfinWorkflow,
    "Histories: " . json_encode(array_column($mrcHistories, 'action'))
);

// ---------------------------------------------------------
// TEST 6: Special Project Missing PO Submission Blocker
// ---------------------------------------------------------
// Upload only the 6 standard docs (leave PO missing)
foreach ($sixDocs as $docType) {
    mockUploadDocument($spProjectId, $docType, $userId, $db);
}

$spEvalMissingPo = MarketingChecklistService::evaluateProject($spProject);
assertTest(
    "Special Project with 6 docs has PO missing (6/7 complete)",
    $spEvalMissingPo['uploaded_required_count'] === 6 && $spEvalMissingPo['is_complete'] === false && in_array('Marketing PO', $spEvalMissingPo['missing_required_types'], true),
    "Missing: " . implode(', ', $spEvalMissingPo['missing_required_types'])
);

// Attempt submission without PO - must fail
$spProjectFresh = Project::findById($spProjectId);
$spSubmitWithoutPo = ProjectService::submitProject($spProjectFresh, $actor, $request, 'Attempting submit without PO');

assertTest(
    "Special Project submission strictly BLOCKED when PO is missing",
    $spSubmitWithoutPo['success'] === false && in_array('Marketing PO', $spSubmitWithoutPo['missing_documents'] ?? [], true),
    "Result: " . json_encode($spSubmitWithoutPo)
);

// ---------------------------------------------------------
// TEST 7: Upload Marketing PO and Submit Special Project
// ---------------------------------------------------------
mockUploadDocument($spProjectId, 'Marketing PO', $userId, $db);

$spEvalWithPo = MarketingChecklistService::evaluateProject($spProject);
assertTest(
    "Special Project with Marketing PO now has 7/7 required documents uploaded",
    $spEvalWithPo['uploaded_required_count'] === 7 && $spEvalWithPo['is_complete'] === true,
    "Uploaded: {$spEvalWithPo['uploaded_required_count']}/7, Complete: " . ($spEvalWithPo['is_complete'] ? 'true' : 'false')
);

$spProjectFresh2 = Project::findById($spProjectId);
$spSubmitResult = ProjectService::submitProject($spProjectFresh2, $actor, $request, 'Special Project submission with PO');
assertTest(
    "Special Project submitted successfully after PO upload",
    ($spSubmitResult['success'] ?? false) === true && ($spSubmitResult['new_status'] ?? '') === 'Submitted to Project Management and Admin Finance',
    "Status: " . ($spSubmitResult['new_status'] ?? 'null')
);

// Verify simultaneous submission columns for Special Project
$stmt = $db->prepare("SELECT status, submitted_at, submitted_by, submitted_to_afin_at, submitted_to_afin_by FROM projects WHERE id = :id");
$stmt->execute(['id' => $spProjectId]);
$spDbProject = $stmt->fetch();

assertTest(
    "Special Project has simultaneous PM & Admin Finance submission fields set",
    !empty($spDbProject['submitted_at']) && !empty($spDbProject['submitted_to_afin_at']),
    "Status: {$spDbProject['status']}"
);

// ---------------------------------------------------------
// TEST 8: Rapid Resubmit / Duplicate Submission Prevention
// ---------------------------------------------------------
$spProjectAlreadySubmitted = Project::findById($spProjectId);
$repeatResult = ProjectService::submitProject($spProjectAlreadySubmitted, $actor, $request, 'Repeated submission attempt');

assertTest(
    "Submitting already submitted project is safely rejected (prevents duplicate transitions)",
    $repeatResult['success'] === false && strpos($repeatResult['error'] ?? '', 'cannot be submitted') !== false,
    "Error: " . ($repeatResult['error'] ?? 'none')
);

// ---------------------------------------------------------
// TEST 9: Marketing Dashboard Stats
// ---------------------------------------------------------
$marketingDeptId = 1;
$stats = Project::marketingDashboardStats($marketingDeptId, $mrcReqs);
assertTest(
    "Marketing dashboard stats include MRC and Special Project counts",
    isset($stats['mrc_projects'], $stats['special_projects'], $stats['submitted_projects']) &&
    $stats['mrc_projects'] >= 1 && $stats['special_projects'] >= 1,
    "MRC: {$stats['mrc_projects']}, Special: {$stats['special_projects']}, Submitted: {$stats['submitted_projects']}"
);

// Test category filtering in dashboard stats
$mrcStats = Project::marketingDashboardStats($marketingDeptId, $mrcReqs, 'mrc');
$spStats = Project::marketingDashboardStats($marketingDeptId, $spReqs, 'special_project');
assertTest(
    "Category-filtered stats return accurate scoped metrics",
    $mrcStats['total_marketing_projects'] === $mrcStats['mrc_projects'] &&
    $spStats['total_marketing_projects'] === $spStats['special_projects'],
    "MRC Filter Total: {$mrcStats['total_marketing_projects']} (MRC: {$mrcStats['mrc_projects']}), SP Filter Total: {$spStats['total_marketing_projects']} (SP: {$spStats['special_projects']})"
);

// ---------------------------------------------------------
// TEST 10: Marketing Pagination with Category Filter
// ---------------------------------------------------------
$mrcList = Project::paginateMarketingProjects($marketingDeptId, $mrcReqs, ['category' => 'mrc'], 1, 10);
$spList = Project::paginateMarketingProjects($marketingDeptId, $spReqs, ['category' => 'special_project'], 1, 10);

$allMrc = true;
foreach ($mrcList['items'] as $item) {
    if (strtoupper($item['project_category'] ?? '') !== 'MRC') {
        $allMrc = false;
        break;
    }
}

$allSp = true;
foreach ($spList['items'] as $item) {
    if (strcasecmp($item['project_category'] ?? '', 'Special Project') !== 0) {
        $allSp = false;
        break;
    }
}

assertTest(
    "Pagination with category=MRC returns only MRC projects",
    $allMrc && count($mrcList['items']) > 0,
    "Returned count: " . count($mrcList['items'])
);

assertTest(
    "Pagination with category='Special Project' returns only Special Projects",
    $allSp && count($spList['items']) > 0,
    "Returned count: " . count($spList['items'])
);

// ---------------------------------------------------------
// TEST 11: Document Upload Category Whitelist & Extension Validation
// ---------------------------------------------------------
$allowedTypes = (array) \App\Core\Config::get('app.uploads.allowed_types', []);
$allowedExtensions = array_keys($allowedTypes);
$disallowedFound = in_array('php', $allowedExtensions, true) || in_array('exe', $allowedExtensions, true) || in_array('sh', $allowedExtensions, true);
assertTest(
    "Upload config prevents executable file uploads (.exe, .php, .sh disallowed)",
    !$disallowedFound && in_array('pdf', $allowedExtensions, true),
    "Config extensions: " . implode(', ', $allowedExtensions)
);

// ---------------------------------------------------------
// TEST 12: Marketing PO configured in allowed categories
// ---------------------------------------------------------
$configuredCategories = (array) \App\Core\Config::get('app.uploads.document_categories', []);
assertTest(
    "Marketing PO is registered in system upload document categories",
    isset($configuredCategories['Marketing PO']) || isset($configuredCategories['PO']),
    "Registered keys: " . implode(', ', array_keys($configuredCategories))
);

// Clean up test projects
$db->prepare("DELETE FROM project_workflow_histories WHERE project_id IN (:p1, :p2)")->execute(['p1' => $mrcProjectId, 'p2' => $spProjectId]);
$db->prepare("DELETE FROM project_documents WHERE project_id IN (:p1, :p2)")->execute(['p1' => $mrcProjectId, 'p2' => $spProjectId]);
$db->prepare("DELETE FROM activity_logs WHERE entity_id IN (:p1, :p2) AND entity_type = 'project'")->execute(['p1' => $mrcProjectId, 'p2' => $spProjectId]);
$db->prepare("DELETE FROM projects WHERE id IN (:p1, :p2)")->execute(['p1' => $mrcProjectId, 'p2' => $spProjectId]);

echo "\nCleaned up automated test project records ($mrcProjectId, $spProjectId).\n\n";

echo "========================================================\n";
echo "TEST RESULTS: Passed: $passed, Failed: $failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
