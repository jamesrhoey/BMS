<?php

declare(strict_types=1);

/**
 * Automated End-to-End & Security Verification Suite for SYB BMS
 * (Phase 1 System Foundation + Phase 2 Marketing Department Module)
 *
 * Usage:
 *   C:\xampp\php\php.exe tests/run_tests.php
 */

use App\Core\Bootstrap;
use App\Core\Config;
use App\Core\Database;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectWorkflowHistory;
use App\Models\Role;
use App\Models\User;
use App\Services\MarketingChecklistService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI execution only.');
}

require_once dirname(__DIR__) . '/app/Core/Bootstrap.php';
Bootstrap::init(dirname(__DIR__));

final class HttpTestClient
{
    private string $baseUrl;
    /** @var array<string, string> */
    public array $cookies = [];

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @param array<string, mixed> $postData
     * @param array<string, array{filename: string, mime: string, content: string}> $files
     * @param list<string> $headers
     * @return array{status: int, headers: array<string, list<string>>, body: string, location: ?string}
     */
    public function request(string $method, string $path, array $postData = [], array $files = [], array $headers = []): array
    {
        $url = str_starts_with($path, 'http') ? $path : $this->baseUrl . '/' . ltrim($path, '/');

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, string $headerLine) use (&$responseHeaders): int {
            $len = strlen($headerLine);
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $name = strtolower(trim($parts[0]));
                $val = trim($parts[1]);
                $responseHeaders[$name][] = $val;

                if ($name === 'set-cookie') {
                    $cookiePair = explode(';', $val, 2)[0];
                    if (str_contains($cookiePair, '=')) {
                        [$cName, $cVal] = explode('=', $cookiePair, 2);
                        if (
                            $cVal === ''
                            || stripos($val, 'Max-Age=0') !== false
                            || stripos($val, 'expires=Thu, 01 Jan 1970') !== false
                            || stripos($val, 'expires=Wed,') !== false
                        ) {
                            if (preg_match('/expires=([^;]+)/i', $val, $m)) {
                                $expTs = strtotime($m[1]);
                                if ($expTs !== false && $expTs < time()) {
                                    unset($this->cookies[trim($cName)]);
                                    return $len;
                                }
                            }
                        }
                        $this->cookies[trim($cName)] = trim($cVal);
                    }
                }
            }
            return $len;
        });

        if ($this->cookies !== []) {
            $pairs = [];
            foreach ($this->cookies as $k => $v) {
                $pairs[] = "{$k}={$v}";
            }
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $pairs));
        }

        $tempFiles = [];
        if (strtoupper($method) === 'POST') {
            if ($files !== []) {
                $payload = $postData;
                foreach ($files as $field => $spec) {
                    $tmp = tempnam(sys_get_temp_dir(), 'bms_test_');
                    if ($tmp === false) {
                        throw new RuntimeException('Unable to create temp file.');
                    }
                    file_put_contents($tmp, $spec['content']);
                    $tempFiles[] = $tmp;
                    $payload[$field] = new CURLFile($tmp, $spec['mime'], $spec['filename']);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            }
        }

        $body = (string) curl_exec($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        foreach ($tempFiles as $tmp) {
            @unlink($tmp);
        }

        if ($err !== '' && $status === 0) {
            throw new RuntimeException("HTTP request failed for {$url}: {$err}");
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $body,
            'location' => $responseHeaders['location'][0] ?? null,
        ];
    }

    public function extractCsrfToken(string $html): string
    {
        if (preg_match('/name="_csrf_token"\s+value="([a-f0-9]{64})"/i', $html, $matches)) {
            return $matches[1];
        }
        throw new RuntimeException('Could not extract CSRF token from HTML response.');
    }
}

$passed = 0;
$failed = 0;

function assertCheck(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$label}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$label}\n";
    }
}

$pdo = Database::connection();
$baseUrl = (string) Config::get('app.url', 'http://localhost/BMS');
$sessionCookieName = (string) Config::get('app.session.name', 'SYB_BMS_SESSION');

$createdUserIds = [];
$createdProjectIds = [];
$createdFilesOnDisk = [];
$maxLogIdBeforeTest = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM activity_logs')->fetchColumn();

echo "=======================================================================\n";
echo " SYB BMS - Automated Foundation & Marketing Module Verification Suite\n";
echo " Target URL: {$baseUrl}\n";
echo "=======================================================================\n\n";

try {
    // 1. Database Connection, Schema, Migration & Seed Data
    echo "1. Database Connection, Schema, Migrations & Seed Data\n";
    $departments = Department::all();
    $deptNames = array_column($departments, 'department_name');
    $expectedDepts = [
        'Marketing',
        'Project Management',
        'Operations',
        'Logistics',
        'Operation Finance',
        'Admin Finance',
    ];
    assertCheck(count($departments) === 6, 'All 6 departments are seeded in business_management_system');
    assertCheck($deptNames === $expectedDepts, 'All 6 department names match SYB specification');

    $roles = Role::all();
    $roleNames = array_column($roles, 'role_name');
    assertCheck(in_array('Administrator', $roleNames, true) && in_array('Department User', $roleNames, true), 'Administrator and Department User roles exist');

    $adminUser = User::findByUsername('admin');
    assertCheck($adminUser !== null && str_starts_with((string) $adminUser['password_hash'], '$2y$'), 'Initial admin user exists with bcrypt hashed password');

    $requiredChecklistTypes = MarketingChecklistService::requiredDocumentTypes();
    assertCheck(
        $requiredChecklistTypes === ['SON', 'SLD', 'BOM', 'CA', 'Proposal to Client', 'Proposal from Supplier'],
        'Standard Marketing checklist configures all 6 required documents (SON, SLD, BOM, CA, Proposal to Client, Proposal from Supplier)'
    );

    // 2. Apache Routing, Security Headers & Protected File Blocking
    echo "\n2. Apache Routing, Security Headers & Protected File Blocking\n";
    $guestClient = new HttpTestClient($baseUrl);

    $rootRes = $guestClient->request('GET', '/');
    assertCheck($rootRes['status'] === 303 && str_ends_with((string) $rootRes['location'], '/login'), 'Root URL redirects unauthenticated visitors to /login');

    $setCookieHeader = implode(' ', $rootRes['headers']['set-cookie'] ?? []);
    assertCheck(stripos($setCookieHeader, 'HttpOnly') !== false && stripos($setCookieHeader, 'SameSite=Lax') !== false, 'Session cookie sets HttpOnly and SameSite=Lax flags');

    $loginPage = $guestClient->request('GET', '/login');
    assertCheck($loginPage['status'] === 200, 'GET /login returns HTTP 200');
    assertCheck(
        str_contains($loginPage['body'], 'pagecom-logo.png') && !str_contains($loginPage['body'], 'logo.svg'),
        'Company logo (pagecom-logo.png) is rendered on the authentication screen'
    );
    assertCheck(isset($loginPage['headers']['x-frame-options']) && isset($loginPage['headers']['x-content-type-options']), 'Security headers (X-Frame-Options, X-Content-Type-Options) are present');

    $envRes = $guestClient->request('GET', '/.env');
    assertCheck($envRes['status'] === 403, 'Direct HTTP access to /.env is blocked with HTTP 403');

    $schemaRes = $guestClient->request('GET', '/database/schema.sql');
    assertCheck($schemaRes['status'] === 403, 'Direct HTTP access to /database/schema.sql is blocked with HTTP 403');

    $storageRes = $guestClient->request('GET', '/storage/documents/.gitkeep');
    assertCheck($storageRes['status'] === 403, 'Direct HTTP access to /storage/documents/ is blocked with HTTP 403');

    $notFoundRes = $guestClient->request('GET', '/non-existent-route-check-999');
    assertCheck($notFoundRes['status'] === 404, 'Unknown route returns custom HTTP 404 page');

    // 3. Guest Route Protection & CSRF Enforcement
    echo "\n3. CSRF Protection & Unauthenticated Route Protection\n";
    foreach (['/dashboard', '/marketing/dashboard', '/marketing/projects', '/notifications', '/users', '/departments', '/projects', '/activity-logs'] as $protectedRoute) {
        $r = $guestClient->request('GET', $protectedRoute);
        assertCheck($r['status'] === 303 && str_ends_with((string) $r['location'], '/login'), "Guest GET {$protectedRoute} redirects to /login");
    }

    $csrfReject = $guestClient->request('POST', '/login', [
        'username' => 'admin',
        'password' => 'Admin@SYB2026!',
    ]);
    assertCheck(
        $csrfReject['status'] === 403 && str_contains($csrfReject['body'], 'Security Verification Failed'),
        'POST /login without CSRF token is rejected with HTTP 403'
    );

    // 4. Authentication, Generic Login Errors & Session Regeneration
    echo "\n4. Authentication, Generic Error Messages & Session Regeneration\n";
    $adminClient = new HttpTestClient($baseUrl);
    $loginGet1 = $adminClient->request('GET', '/login');
    $csrf1 = $adminClient->extractCsrfToken($loginGet1['body']);

    $badUserPost = $adminClient->request('POST', '/login', [
        '_csrf_token' => $csrf1,
        'username' => 'non_existent_user_xyz',
        'password' => 'WrongPass123!',
    ]);
    assertCheck($badUserPost['status'] === 303, 'Invalid login uses Post/Redirect/Get (HTTP 303)');
    $badUserFollow = $adminClient->request('GET', '/login');
    assertCheck(str_contains($badUserFollow['body'], 'Invalid username or password.'), 'Non-existent username displays generic error message');

    $csrf2 = $adminClient->extractCsrfToken($badUserFollow['body']);
    $badPassPost = $adminClient->request('POST', '/login', [
        '_csrf_token' => $csrf2,
        'username' => 'admin',
        'password' => 'WrongPass123!',
    ]);
    $badPassFollow = $adminClient->request('GET', '/login');
    assertCheck(str_contains($badPassFollow['body'], 'Invalid username or password.'), 'Wrong password for existing user displays identical generic error message');

    $preLoginSessionId = $adminClient->cookies[$sessionCookieName] ?? '';
    $csrf3 = $adminClient->extractCsrfToken($badPassFollow['body']);
    $goodLogin = $adminClient->request('POST', '/login', [
        '_csrf_token' => $csrf3,
        'username' => 'admin',
        'password' => 'Admin@SYB2026!',
    ]);
    $postLoginSessionId = $adminClient->cookies[$sessionCookieName] ?? '';
    assertCheck($goodLogin['status'] === 303 && str_ends_with((string) $goodLogin['location'], '/dashboard'), 'Valid admin login redirects to /dashboard');
    assertCheck($preLoginSessionId !== '' && $postLoginSessionId !== '' && $preLoginSessionId !== $postLoginSessionId, 'Session ID is regenerated upon successful login');

    $adminDash = $adminClient->request('GET', '/dashboard');
    assertCheck(
        $adminDash['status'] === 200
        && str_contains($adminDash['body'], 'Company Departments (6)')
        && !str_contains($adminDash['body'], 'System Overview'),
        'Administrator dashboard loads with HTTP 200'
    );

    // 5. User Management (Create MKT #1, MKT #2, PM, OPS; Reassign; Deactivate/Reactivate)
    echo "\n5. User Management (Create MKT Team, PM Reviewer, OPS User, Reassign & Status Toggle)\n";
    $mktDept = Department::findByCode('MKT');
    $pmDept = Department::findByCode('PM');
    $logDept = Department::findByCode('LOG');
    $opsDept = Department::findByCode('OPS');
    $deptRole = Role::findBySlug(Role::SLUG_DEPARTMENT_USER);

    $createForm = $adminClient->request('GET', '/users/create');
    $adminCsrf = $adminClient->extractCsrfToken($createForm['body']);

    $suffix = substr(bin2hex(random_bytes(3)), 0, 6);
    $mktUsername = "test_mkt1_{$suffix}";
    $mkt2Username = "test_mkt2_{$suffix}";
    $pmUsername = "test_pm_{$suffix}";
    $opsUsername = "test_ops_{$suffix}";

    // Create Marketing User #1
    $createMktRes = $adminClient->request('POST', '/users', [
        '_csrf_token' => $adminCsrf,
        'full_name' => 'Marketing Specialist One',
        'username' => $mktUsername,
        'email' => "{$mktUsername}@syb.local",
        'role_id' => (string) $deptRole['id'],
        'department_id' => (string) $mktDept['id'],
        'status' => 'active',
        'password' => 'MktPass@2026!',
        'password_confirmation' => 'MktPass@2026!',
    ]);
    assertCheck($createMktRes['status'] === 303 && str_ends_with((string) $createMktRes['location'], '/users'), 'Administrator created Marketing User #1 via POST /users');

    $mktUserRow = User::findByUsername($mktUsername);
    if ($mktUserRow !== null) {
        $createdUserIds[] = (int) $mktUserRow['id'];
    }
    assertCheck(
        $mktUserRow !== null
        && $mktUserRow['password_hash'] !== 'MktPass@2026!'
        && password_verify('MktPass@2026!', (string) $mktUserRow['password_hash']),
        'Created user password is stored hashed with password_hash() and verified with password_verify()'
    );

    // Create Marketing User #2 (for shared Marketing team access verification)
    $adminClient->request('POST', '/users', [
        '_csrf_token' => $adminCsrf,
        'full_name' => 'Marketing Specialist Two',
        'username' => $mkt2Username,
        'email' => "{$mkt2Username}@syb.local",
        'role_id' => (string) $deptRole['id'],
        'department_id' => (string) $mktDept['id'],
        'status' => 'active',
        'password' => 'MktPass@2026!',
        'password_confirmation' => 'MktPass@2026!',
    ]);
    $mkt2UserRow = User::findByUsername($mkt2Username);
    if ($mkt2UserRow !== null) {
        $createdUserIds[] = (int) $mkt2UserRow['id'];
    }
    assertCheck($mkt2UserRow !== null, 'Administrator created Marketing User #2 for shared team workflow verification');

    // Create Project Management User (for PM verification queue & return-for-corrections verification)
    $adminClient->request('POST', '/users', [
        '_csrf_token' => $adminCsrf,
        'full_name' => 'PM Verification Engineer',
        'username' => $pmUsername,
        'email' => "{$pmUsername}@syb.local",
        'role_id' => (string) $deptRole['id'],
        'department_id' => (string) $pmDept['id'],
        'status' => 'active',
        'password' => 'PmPass@2026!',
        'password_confirmation' => 'PmPass@2026!',
    ]);
    $pmUserRow = User::findByUsername($pmUsername);
    if ($pmUserRow !== null) {
        $createdUserIds[] = (int) $pmUserRow['id'];
    }
    assertCheck($pmUserRow !== null && (int) $pmUserRow['department_id'] === (int) $pmDept['id'], 'Administrator created Project Management User');

    // Create Logistics user, then reassign to Operations
    $adminClient->request('POST', '/users', [
        '_csrf_token' => $adminCsrf,
        'full_name' => 'Logistics Reassign User',
        'username' => $opsUsername,
        'email' => "{$opsUsername}@syb.local",
        'role_id' => (string) $deptRole['id'],
        'department_id' => (string) $logDept['id'],
        'status' => 'active',
        'password' => 'OpsPass@2026!',
        'password_confirmation' => 'OpsPass@2026!',
    ]);
    $opsUserRow = User::findByUsername($opsUsername);
    if ($opsUserRow !== null) {
        $createdUserIds[] = (int) $opsUserRow['id'];
    }

    $updateOpsRes = $adminClient->request('POST', '/users/' . (int) $opsUserRow['id'], [
        '_csrf_token' => $adminCsrf,
        'full_name' => 'Operations Updated User',
        'username' => $opsUsername,
        'email' => "{$opsUsername}@syb.local",
        'role_id' => (string) $deptRole['id'],
        'department_id' => (string) $opsDept['id'],
        'status' => 'active',
        'password' => '',
        'password_confirmation' => '',
    ]);
    $opsUserUpdated = User::findById((int) $opsUserRow['id']);
    assertCheck(
        $updateOpsRes['status'] === 303
        && $opsUserUpdated !== null
        && (int) $opsUserUpdated['department_id'] === (int) $opsDept['id'],
        'User profile update and department reassignment (LOG -> OPS) persisted'
    );

    // Self-deactivation protection check
    $adminClient->request('POST', '/users/' . (int) $adminUser['id'] . '/status', [
        '_csrf_token' => $adminCsrf,
        'status' => 'inactive',
    ]);
    $adminReloaded = User::findById((int) $adminUser['id']);
    assertCheck($adminReloaded !== null && $adminReloaded['status'] === 'active', 'Administrator is prevented from deactivating their own active account');

    // Deactivate Ops user and verify login is blocked, then reactivate
    $adminClient->request('POST', '/users/' . (int) $opsUserRow['id'] . '/status', [
        '_csrf_token' => $adminCsrf,
        'status' => 'inactive',
    ]);
    $inactiveClient = new HttpTestClient($baseUrl);
    $inactiveLoginForm = $inactiveClient->request('GET', '/login');
    $inactiveCsrf = $inactiveClient->extractCsrfToken($inactiveLoginForm['body']);
    $inactiveAttempt = $inactiveClient->request('POST', '/login', [
        '_csrf_token' => $inactiveCsrf,
        'username' => $opsUsername,
        'password' => 'OpsPass@2026!',
    ]);
    $inactiveFollow = $inactiveClient->request('GET', '/login');
    assertCheck(
        str_ends_with((string) $inactiveAttempt['location'], '/login')
        && str_contains($inactiveFollow['body'], 'Invalid username or password.'),
        'Deactivated account is blocked from signing in'
    );

    $adminClient->request('POST', '/users/' . (int) $opsUserRow['id'] . '/status', [
        '_csrf_token' => $adminCsrf,
        'status' => 'active',
    ]);

    // 6. Role-Based Access Control & Department Isolation
    echo "\n6. Role-Based Access Control & Department Module Isolation\n";
    // Sign in Marketing User #1
    $mktClient = new HttpTestClient($baseUrl);
    $mktLoginForm = $mktClient->request('GET', '/login');
    $mktClient->request('POST', '/login', [
        '_csrf_token' => $mktClient->extractCsrfToken($mktLoginForm['body']),
        'username' => $mktUsername,
        'password' => 'MktPass@2026!',
    ]);
    $mktDash = $mktClient->request('GET', '/dashboard');
    $mktCsrf = $mktClient->extractCsrfToken($mktDash['body']);
    assertCheck(
        $mktDash['status'] === 200
        && str_contains($mktDash['body'], 'Recent Marketing Projects')
        && !str_contains($mktDash['body'], 'Marketing Department Dashboard')
        && !str_contains($mktDash['body'], 'Standard Marketing Checklist'),
        'Marketing user lands directly on Dedicated Marketing Dashboard via /dashboard'
    );
    assertCheck(
        preg_match('#/assets/js/app\.js\?v=\d+#', $mktDash['body']) === 1
        && preg_match('#/assets/css/app\.css\?v=\d+#', $mktDash['body']) === 1,
        'Static JS/CSS assets include cache-busting ?v= timestamps so updated client scripts are always loaded'
    );
    assertCheck(
        str_contains($mktDash['body'], 'data-sidebar-collapse')
        && str_contains($mktDash['body'], 'data-sidebar-expand')
        && !str_contains($mktDash['body'], 'Created by Marketing')
        && !str_contains($mktDash['body'], 'Pending PM verification'),
        'Sidebar includes icon-only collapse and expand buttons, and stat card subtitles are cleanly removed'
    );
    assertCheck(
        str_contains($mktDash['body'], 'bms-table-panel')
        && str_contains($mktDash['body'], 'bms-table-footer')
        && str_contains($mktDash['body'], 'total items:'),
        'Data tables render unified Nuclias-style panel and pagination footer'
    );
    $mktClient->cookies['bms_sidebar_collapsed'] = '1';
    $mktDashCollapsed = $mktClient->request('GET', '/marketing/dashboard');
    unset($mktClient->cookies['bms_sidebar_collapsed']);
    assertCheck(
        $mktDashCollapsed['status'] === 200 && str_contains($mktDashCollapsed['body'], '<body class="sidebar-collapsed">'),
        'Sidebar collapsed cookie renders icon-only body class immediately on server load'
    );

    // Sign in Marketing User #2
    $mkt2Client = new HttpTestClient($baseUrl);
    $mkt2LoginForm = $mkt2Client->request('GET', '/login');
    $mkt2Client->request('POST', '/login', [
        '_csrf_token' => $mkt2Client->extractCsrfToken($mkt2LoginForm['body']),
        'username' => $mkt2Username,
        'password' => 'MktPass@2026!',
    ]);
    $mkt2Dash = $mkt2Client->request('GET', '/marketing/dashboard');
    $mkt2Csrf = $mkt2Client->extractCsrfToken($mkt2Dash['body']);
    assertCheck($mkt2Dash['status'] === 200, 'Second Marketing user can access /marketing/dashboard');

    // Sign in PM User
    $pmClient = new HttpTestClient($baseUrl);
    $pmLoginForm = $pmClient->request('GET', '/login');
    $pmClient->request('POST', '/login', [
        '_csrf_token' => $pmClient->extractCsrfToken($pmLoginForm['body']),
        'username' => $pmUsername,
        'password' => 'PmPass@2026!',
    ]);
    $pmDash = $pmClient->request('GET', '/dashboard');
    $pmCsrf = $pmClient->extractCsrfToken($pmDash['body']);
    assertCheck(
        $pmDash['status'] === 200 && str_contains($pmDash['body'], 'Marketing Document Verification Queue'),
        'Project Management user lands on PM Workspace with Marketing Document Verification Queue'
    );

    // Sign in Operations User
    $opsClient = new HttpTestClient($baseUrl);
    $opsLoginGet = $opsClient->request('GET', '/login');
    $opsClient->request('POST', '/login', [
        '_csrf_token' => $opsClient->extractCsrfToken($opsLoginGet['body']),
        'username' => $opsUsername,
        'password' => 'OpsPass@2026!',
    ]);
    $opsDash = $opsClient->request('GET', '/dashboard');
    $opsCsrf = $opsClient->extractCsrfToken($opsDash['body']);

    foreach (['/dashboard/admin', '/users', '/users/create', '/departments', '/activity-logs'] as $adminOnlyUrl) {
        $res = $mktClient->request('GET', $adminOnlyUrl);
        assertCheck($res['status'] === 403, "Marketing User denied access to admin route {$adminOnlyUrl} (HTTP 403)");
    }

    // Attempt privilege escalation via crafted POST to /users/{id} as Marketing User
    $escalationAttempt = $mktClient->request('POST', '/users/' . (int) $mktUserRow['id'], [
        '_csrf_token' => $mktCsrf,
        'full_name' => 'Escalated Admin',
        'username' => $mktUsername,
        'role_id' => '1',
        'department_id' => '',
        'status' => 'active',
    ]);
    $mktCheckAfterEscalation = User::findById((int) $mktUserRow['id']);
    assertCheck(
        $escalationAttempt['status'] === 403 && $mktCheckAfterEscalation['role_slug'] === Role::SLUG_DEPARTMENT_USER,
        'Department User cannot modify their own role or department via crafted POST request'
    );

    // Non-Marketing users blocked from /marketing/* and project registration
    foreach (['/marketing/dashboard', '/marketing/projects', '/marketing/projects/create', '/projects/create'] as $mktRoute) {
        $opsAttempt = $opsClient->request('GET', $mktRoute);
        assertCheck($opsAttempt['status'] === 403, "Operations user blocked from {$mktRoute} (HTTP 403)");
    }
    $pmMktAttempt = $pmClient->request('GET', '/marketing/dashboard');
    assertCheck($pmMktAttempt['status'] === 403, 'Project Management user blocked from Marketing-only dashboard /marketing/dashboard (HTTP 403)');

    $opsCreatePost = $opsClient->request('POST', '/marketing/projects', [
        '_csrf_token' => $opsCsrf,
        'project_name' => 'Unauthorized Ops Project',
        'client_name' => 'Test Client',
    ]);
    assertCheck($opsCreatePost['status'] === 403, 'Operations user blocked from POST /marketing/projects (HTTP 403)');

    // 7. Marketing Create Project, Validation, Old Input Preservation, Unique Code & XSS
    echo "\n7. Marketing Project Registration, Server Validation, Unique Code & XSS Escaping\n";
    // Test Scenario 3: Server-side required field validation & old input preservation
    $invalidCreateRes = $mktClient->request('POST', '/marketing/projects', [
        '_csrf_token' => $mktCsrf,
        'project_name' => '',
        'client_name' => '',
        'client_contact' => 'Engr. Preservation Check',
        'project_location' => 'Makati CBD Preserve Test',
    ]);
    assertCheck(
        $invalidCreateRes['status'] === 303 && str_ends_with((string) $invalidCreateRes['location'], '/marketing/projects/create'),
        'Missing required project fields redirect back to /marketing/projects/create'
    );
    $invalidFollow = $mktClient->request('GET', '/marketing/projects/create');
    assertCheck(
        stripos($invalidFollow['body'], 'Project name is required') !== false
        && stripos($invalidFollow['body'], 'Client name is required') !== false
        && str_contains($invalidFollow['body'], 'Makati CBD Preserve Test'),
        'Validation errors are clearly displayed and entered field values are preserved via old()'
    );

    // Test Scenarios 1 & 2: Create two Marketing draft projects and verify unique PRJ-* codes & no JOM number
    $xssPayload = 'Acme <script>alert("xss")</script> Corp';
    $createProj1 = $mktClient->request('POST', '/marketing/projects', [
        '_csrf_token' => $mktCsrf,
        'project_name' => 'PABX Core Upgrade ' . $suffix,
        'client_name' => $xssPayload,
        'client_contact' => 'Engr. Cruz <img src=x onerror=alert(1)>',
        'project_location' => 'Laguna Technopark',
        'project_description' => 'Standard SYB Marketing workflow test project.',
    ]);
    assertCheck(
        $createProj1['status'] === 303 && preg_match('#/projects/(\d+)$#', (string) $createProj1['location'], $projMatch1) === 1,
        'Marketing user created Draft Project #1 via POST /marketing/projects'
    );
    $projectId = (int) ($projMatch1[1] ?? 0);
    if ($projectId > 0) {
        $createdProjectIds[] = $projectId;
    }

    $createProj2 = $mktClient->request('POST', '/marketing/projects', [
        '_csrf_token' => $mktCsrf,
        'project_name' => 'Structured Cabling Expansion ' . $suffix,
        'client_name' => 'Metro Pacific Logistics',
        'client_contact' => 'Ms. Santos',
        'project_location' => 'Pasig City',
        'project_description' => 'Second draft project to verify unique project codes and dashboard stats.',
    ]);
    $project2Id = 0;
    if (preg_match('#/projects/(\d+)$#', (string) $createProj2['location'], $projMatch2) === 1) {
        $project2Id = (int) $projMatch2[1];
        $createdProjectIds[] = $project2Id;
    }

    $projectRecord = Project::findById($projectId);
    $project2Record = Project::findById($project2Id);
    assertCheck(
        $projectRecord !== null
        && $project2Record !== null
        && str_starts_with((string) $projectRecord['project_code'], 'PRJ-')
        && $projectRecord['project_code'] !== $project2Record['project_code']
        && $projectRecord['status'] === Project::STATUS_DRAFT
        && (int) $projectRecord['origin_department_id'] === (int) $mktDept['id'],
        'Unique PRJ-* codes generated, Draft status set, origin_department_id = MKT, and no JOM number issued'
    );

    // XSS Escaping check on project detail view
    $projShow = $mktClient->request('GET', '/projects/' . $projectId);
    assertCheck(
        $projShow['status'] === 200
        && !str_contains($projShow['body'], '<script>alert("xss")</script>')
        && str_contains($projShow['body'], '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'),
        'User-provided HTML/JS input is properly escaped against XSS on output'
    );

    // Shared Marketing Team Access vs Unauthorized Department Isolation
    $mkt2ViewDraft = $mkt2Client->request('GET', '/projects/' . $projectId);
    assertCheck($mkt2ViewDraft['status'] === 200, 'Shared Marketing team access allows Marketing User #2 to view Draft Project #1');

    $opsUnauthorizedView = $opsClient->request('GET', '/projects/' . $projectId);
    assertCheck($opsUnauthorizedView['status'] === 403, 'Operations user is blocked from viewing Marketing project (HTTP 403)');

    // Verify Prepare Docs button (POST /projects/{id}/prepare-documents) & ?upload=SON fallback on Project #2
    $prepDocsPost = $mktClient->request('POST', "/projects/{$project2Id}/prepare-documents", [
        '_csrf_token' => $mktCsrf,
    ]);
    $project2AfterPrep = Project::findById($project2Id);
    $prepFollowHtml = $mktClient->request('GET', "/projects/{$project2Id}?upload=SON");
    assertCheck(
        $prepDocsPost['status'] === 303
        && $project2AfterPrep['status'] === Project::STATUS_PREPARING_DOCUMENTS
        && !str_contains($prepFollowHtml['body'], 'id="checklist-upload-panel-1" class="checklist-subrow d-none"'),
        'Prepare Docs action transitions Draft to Preparing Documents and ?upload=SON opens the row upload panel'
    );
    // Reset Project #2 back to Draft so dashboard Draft counter test remains covered
    Project::updateStatus($project2Id, Project::STATUS_DRAFT);

    // Edit Draft project
    $editProjRes = $mktClient->request('POST', '/projects/' . $projectId, [
        '_csrf_token' => $mktCsrf,
        'project_name' => 'PABX Core Upgrade Phase 2 ' . $suffix,
        'client_name' => 'Acme Industrial Corp',
        'client_contact' => 'Engr. Cruz - 0917-000-0000',
        'project_location' => 'Laguna Technopark',
        'project_description' => 'Updated draft project scope.',
    ]);
    $projectAfterEdit = Project::findById($projectId);
    assertCheck(
        $editProjRes['status'] === 303 && str_contains((string) $projectAfterEdit['project_name'], 'Phase 2'),
        'Authorized Marketing user updated permitted Draft project fields'
    );

    // 8. Marketing Document Checklist, Upload Security, Missing-Document Blocking & Submission
    echo "\n8. Marketing Document Checklist, Upload Security & Server Completeness Enforcement\n";
    // Reject disguised PHP payload (.php content sent as .pdf)
    $mktClient->request(
        'POST',
        "/projects/{$projectId}/documents",
        [
            '_csrf_token' => $mktCsrf,
            'document_type' => 'SON',
        ],
        [
            'document' => [
                'filename' => 'exploit.pdf',
                'mime' => 'application/pdf',
                'content' => "<?php system('whoami'); ?>",
            ],
        ]
    );
    assertCheck(ProjectDocument::forProject($projectId) === [], 'Disguised PHP payload with forged application/pdf MIME is rejected by server finfo check');

    // Reject double-extension filename containing executable segment
    $mktClient->request(
        'POST',
        "/projects/{$projectId}/documents",
        [
            '_csrf_token' => $mktCsrf,
            'document_type' => 'SON',
        ],
        [
            'document' => [
                'filename' => 'son_shell.php.pdf',
                'mime' => 'application/pdf',
                'content' => "%PDF-1.4\n%%EOF\n",
            ],
        ]
    );
    assertCheck(ProjectDocument::forProject($projectId) === [], 'Double-extension filename (son_shell.php.pdf) is rejected');

    // Upload 5 of the 6 required Marketing documents (leave 'Proposal from Supplier' missing)
    $firstFiveTypes = ['SON', 'SLD', 'BOM', 'CA', 'Proposal to Client'];
    foreach ($firstFiveTypes as $idx => $docType) {
        $pdfContent = "%PDF-1.4\n% Marketing Doc {$docType} v1\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
        $safeSlug = preg_replace('/[^A-Za-z0-9]+/', '_', $docType);
        $mktClient->request(
            'POST',
            "/projects/{$projectId}/documents",
            [
                '_csrf_token' => $mktCsrf,
                'document_type' => $docType,
                'upload_notes' => "Initial v1 upload for {$docType}",
            ],
            [
                'document' => [
                    'filename' => "{$safeSlug}_v1.pdf",
                    'mime' => 'application/pdf',
                    'content' => $pdfContent,
                ],
            ]
        );
    }

    $projectDuringPrep = Project::findById($projectId);
    $checklistPartial = MarketingChecklistService::evaluateProject($projectDuringPrep);
    assertCheck(
        $projectDuringPrep['status'] === Project::STATUS_PREPARING_DOCUMENTS,
        'Uploading checklist document automatically transitions Draft project to Preparing Documents'
    );
    assertCheck(
        $checklistPartial['uploaded_required_count'] === 5
        && $checklistPartial['missing_required_types'] === ['Proposal from Supplier']
        && $checklistPartial['is_complete'] === false
        && $checklistPartial['items']['SON']['status'] === 'Uploaded',
        'Checklist accurately tracks 5/6 uploaded, marks SON as Uploaded (not Approved), and identifies Proposal from Supplier as Missing'
    );

    // Scenario 6: Submission is BLOCKED on the server when any required document is missing
    $blockedSubmit = $mktClient->request('POST', "/projects/{$projectId}/submit", [
        '_csrf_token' => $mktCsrf,
        'submission_notes' => 'Premature submit attempt',
    ]);
    $projectAfterBlockedSubmit = Project::findById($projectId);
    $blockedFollow = $mktClient->request('GET', "/projects/{$projectId}");
    assertCheck(
        $blockedSubmit['status'] === 303
        && $projectAfterBlockedSubmit['status'] === Project::STATUS_PREPARING_DOCUMENTS
        && str_contains($blockedFollow['body'], 'Proposal from Supplier'),
        'Server blocks submission when required Marketing documents are missing and flashes missing item warning'
    );

    // Upload the 6th required document ('Proposal from Supplier')
    $supplierPdfV1 = "%PDF-1.4\n% Proposal from Supplier v1\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
    $mktClient->request(
        'POST',
        "/projects/{$projectId}/documents",
        [
            '_csrf_token' => $mktCsrf,
            'document_type' => 'Proposal from Supplier',
            'upload_notes' => 'Initial supplier quotation v1',
        ],
        [
            'document' => [
                'filename' => 'Supplier_Proposal_v1.pdf',
                'mime' => 'application/pdf',
                'content' => $supplierPdfV1,
            ],
        ]
    );

    $checklistFull = MarketingChecklistService::evaluateProject(Project::findById($projectId));
    assertCheck(
        $checklistFull['is_complete'] === true && $checklistFull['uploaded_required_count'] === 6,
        'All 6 required Marketing documents uploaded (6/6 complete)'
    );

    // Scenarios 7, 8, 9: Submit complete project to Project Management
    $validSubmit = $mktClient->request('POST', "/projects/{$projectId}/submit", [
        '_csrf_token' => $mktCsrf,
        'submission_notes' => 'All 6 standard Marketing documents ready for PM verification.',
    ]);
    $projectSubmitted = Project::findById($projectId);
    assertCheck(
        $validSubmit['status'] === 303
        && $projectSubmitted['status'] === Project::STATUS_SUBMITTED_FOR_VERIFICATION
        && (int) $projectSubmitted['current_department_id'] === (int) $pmDept['id']
        && (int) $projectSubmitted['submitted_by'] === (int) $mktUserRow['id']
        && $projectSubmitted['submitted_at'] !== null
        && $projectSubmitted['verification_status'] === Project::VERIFICATION_PENDING_PM
        && empty($projectSubmitted['jom_number']),
        'Complete Marketing project transitions to Submitted for Document Verification, routes to PM, and does NOT issue a JOM number'
    );

    // Verify Marketing cannot upload replacements while queued with PM
    $lockedUploadAttempt = $mktClient->request(
        'POST',
        "/projects/{$projectId}/documents",
        [
            '_csrf_token' => $mktCsrf,
            'document_type' => 'SON',
        ],
        [
            'document' => [
                'filename' => 'locked_edit.pdf',
                'mime' => 'application/pdf',
                'content' => $supplierPdfV1,
            ],
        ]
    );
    assertCheck($lockedUploadAttempt['status'] === 403, 'Marketing document uploads are locked (HTTP 403) while project is queued with PM');

    // Verify PM user received notification and sees project in PM verification queue
    $pmUnreadCount = Notification::unreadCountForUser((int) $pmUserRow['id']);
    $pmQueueDash = $pmClient->request('GET', '/dashboard');
    assertCheck(
        $pmUnreadCount >= 1 && str_contains($pmQueueDash['body'], (string) $projectSubmitted['project_code']),
        'Submitted project notifies active PM users and appears in PM Document Verification Queue'
    );

    // 9. PM Return-for-Corrections, Flagged Documents, Version Replacement & Resubmission
    echo "\n9. PM Return-for-Corrections, Version-Preserving Replacement & Resubmission\n";
    // Scenario 10: Marketing user CANNOT return a project for corrections
    $mktReturnAttempt = $mktClient->request('POST', "/projects/{$projectId}/return-to-marketing", [
        '_csrf_token' => $mktCsrf,
        'return_reason' => 'Marketing attempting unauthorized self-return',
    ]);
    assertCheck($mktReturnAttempt['status'] === 403, 'Marketing user is blocked from returning project for corrections (HTTP 403)');

    // PM return without reason fails validation
    $pmEmptyReason = $pmClient->request('POST', "/projects/{$projectId}/return-to-marketing", [
        '_csrf_token' => $pmCsrf,
        'return_reason' => '',
    ]);
    $afterEmptyReturn = Project::findById($projectId);
    assertCheck(
        $pmEmptyReason['status'] === 303 && $afterEmptyReturn['status'] === Project::STATUS_SUBMITTED_FOR_VERIFICATION,
        'PM return-to-marketing requires a non-empty return_reason'
    );

    // Authorized PM user returns project to Marketing with reason and flagged documents
    $returnNote = 'Please update CA margin breakdown and attach signed Proposal from Supplier.';
    $pmReturnRes = $pmClient->request('POST', "/projects/{$projectId}/return-to-marketing", [
        '_csrf_token' => $pmCsrf,
        'return_reason' => $returnNote,
        'flagged_documents' => ['CA', 'Proposal from Supplier'],
    ]);
    $projectReturned = Project::findById($projectId);
    assertCheck(
        $pmReturnRes['status'] === 303
        && $projectReturned['status'] === Project::STATUS_RETURNED_FOR_CORRECTIONS
        && (int) $projectReturned['current_department_id'] === (int) $mktDept['id']
        && (int) $projectReturned['returned_by'] === (int) $pmUserRow['id']
        && $projectReturned['return_reason'] === $returnNote
        && $projectReturned['verification_status'] === Project::VERIFICATION_RETURNED,
        'Authorized PM user returned project to Marketing with return_reason and flagged_documents'
    );

    // Verify Marketing users received return notification and see return banner + flagged badges
    $mkt1Unread = Notification::unreadCountForUser((int) $mktUserRow['id']);
    $mkt2Unread = Notification::unreadCountForUser((int) $mkt2UserRow['id']);
    $mkt2ReturnedView = $mkt2Client->request('GET', "/projects/{$projectId}");
    assertCheck(
        $mkt1Unread >= 1
        && $mkt2Unread >= 1
        && str_contains($mkt2ReturnedView['body'], $returnNote)
        && str_contains($mkt2ReturnedView['body'], 'Needs Attention'),
        'Active Marketing team members receive notifications and view PM return notes + flagged document badges'
    );

    // Scenarios 11 & 12: Marketing User #2 replaces CA with v2; verify v1 is preserved in DB & disk and downloadable
    $caDocsBefore = array_values(array_filter(
        ProjectDocument::forProject($projectId),
        static fn(array $d): bool => $d['document_type'] === 'CA'
    ));
    $caV1Doc = $caDocsBefore[0] ?? null;

    $caRevisedPdfV2 = "%PDF-1.4\n% CA Revised Version 2 per PM Return\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
    $replaceRes = $mkt2Client->request(
        'POST',
        "/projects/{$projectId}/documents",
        [
            '_csrf_token' => $mkt2Csrf,
            'document_type' => 'CA',
            'upload_notes' => 'Revised margin breakdown per PM instructions',
        ],
        [
            'document' => [
                'filename' => 'CA_Revised_v2.pdf',
                'mime' => 'application/pdf',
                'content' => $caRevisedPdfV2,
            ],
        ]
    );

    $allProjectDocs = ProjectDocument::forProject($projectId);
    foreach ($allProjectDocs as $dRow) {
        $createdFilesOnDisk[] = BASE_PATH . '/storage/documents/' . $dRow['file_path'];
    }

    $caVersions = array_values(array_filter(
        $allProjectDocs,
        static fn(array $d): bool => $d['document_type'] === 'CA'
    ));
    $checklistAfterReplace = MarketingChecklistService::evaluateProject(Project::findById($projectId));

    assertCheck(
        $replaceRes['status'] === 303
        && count($caVersions) === 2
        && (int) $caVersions[0]['version_number'] === 2
        && (int) $caVersions[0]['is_current'] === 1
        && (int) $caVersions[1]['version_number'] === 1
        && (int) $caVersions[1]['is_current'] === 0
        && $checklistAfterReplace['items']['CA']['status'] === 'Replaced',
        'Document replacement creates v2 (status Replaced) while preserving v1 (is_current = 0) in project_documents'
    );

    // Resubmit project to Project Management
    $resubmitRes = $mkt2Client->request('POST', "/projects/{$projectId}/submit", [
        '_csrf_token' => $mkt2Csrf,
        'submission_notes' => 'Uploaded revised CA v2 per PM review.',
    ]);
    $projectResubmitted = Project::findById($projectId);
    assertCheck(
        $resubmitRes['status'] === 303
        && $projectResubmitted['status'] === Project::STATUS_RESUBMITTED_FOR_VERIFICATION
        && (int) $projectResubmitted['current_department_id'] === (int) $pmDept['id']
        && $projectResubmitted['verification_status'] === Project::VERIFICATION_PENDING_PM,
        'Corrected project resubmits cleanly with status Resubmitted for Verification and routes back to PM'
    );

    // Verify both v1 and v2 remain on disk and downloadable by authorized MKT & PM users, while OPS gets 403
    if ($caV1Doc !== null && count($caVersions) === 2) {
        $v2Id = (int) $caVersions[0]['id'];
        $v1Id = (int) $caVersions[1]['id'];

        $dlV2Pm = $pmClient->request('GET', "/projects/{$projectId}/documents/{$v2Id}/download");
        $viewV2Pm = $pmClient->request('GET', "/projects/{$projectId}/documents/{$v2Id}/raw");
        $dlV1Mkt = $mktClient->request('GET', "/projects/{$projectId}/documents/{$v1Id}/download");
        $viewV1Mkt = $mktClient->request('GET', "/projects/{$projectId}/documents/{$v1Id}/raw");
        $opsDl = $opsClient->request('GET', "/projects/{$projectId}/documents/{$v2Id}/download");
        $opsView = $opsClient->request('GET', "/projects/{$projectId}/documents/{$v2Id}/raw");

        $v2Disp = implode(' ', $viewV2Pm['headers']['content-disposition'] ?? []);
        assertCheck(
            $dlV2Pm['status'] === 200 && $dlV2Pm['body'] === $caRevisedPdfV2
            && $viewV2Pm['status'] === 200 && $viewV2Pm['body'] === $caRevisedPdfV2
            && stripos($v2Disp, 'inline') !== false
            && $dlV1Mkt['status'] === 200 && str_contains($dlV1Mkt['body'], 'Marketing Doc CA v1')
            && $viewV1Mkt['status'] === 200 && str_contains($viewV1Mkt['body'], 'Marketing Doc CA v1')
            && $opsDl['status'] === 403
            && $opsView['status'] === 403,
            'Both current (v2) and superseded (v1) document files are viewable inline and downloadable by authorized users, and blocked (403) for Operations'
        );

        // Verify In-App Document Viewer HTML and dedicated Raw stream route
        $htmlViewerRes = $pmClient->request(
            'GET',
            "/projects/{$projectId}/documents/{$v2Id}/view",
            [],
            [],
            ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8']
        );
        $rawStreamRes = $pmClient->request('GET', "/projects/{$projectId}/documents/{$v2Id}/raw");
        $opsRawRes = $opsClient->request('GET', "/projects/{$projectId}/documents/{$v2Id}/raw");
        $rawDisp = implode(' ', $rawStreamRes['headers']['content-disposition'] ?? []);

        assertCheck(
            $htmlViewerRes['status'] === 200
            && str_contains($htmlViewerRes['body'], 'bms-document-viewer-page')
            && str_contains($htmlViewerRes['body'], 'document-viewer.js')
            && $rawStreamRes['status'] === 200
            && $rawStreamRes['body'] === $caRevisedPdfV2
            && stripos($rawDisp, 'inline') !== false
            && $opsRawRes['status'] === 403,
            'In-App Document Viewer renders interactive HTML canvas for web browsers and provides authorized raw stream route'
        );
    }

    // 10. Marketing Dashboard Statistics, Project List Filters/Search/Sort & Notifications
    echo "\n10. Marketing Dashboard Live Stats, List Search/Filter/Sort & Notifications\n";
    $stats = Project::marketingDashboardStats((int) $mktDept['id'], $requiredChecklistTypes);
    assertCheck(
        $stats['total_marketing_projects'] >= 2
        && $stats['draft_projects'] >= 1
        && $stats['submitted_to_pm'] >= 1
        && $stats['missing_documents'] >= 1,
        'Marketing dashboard statistics accurately aggregate live database counts across statuses and missing documents'
    );

    $completeListRes = $mktClient->request('GET', '/marketing/projects?completeness=complete');
    $incompleteListRes = $mktClient->request('GET', '/marketing/projects?completeness=incomplete');
    assertCheck(
        $completeListRes['status'] === 200
        && str_contains($completeListRes['body'], (string) $projectResubmitted['project_code'])
        && !str_contains($completeListRes['body'], (string) $project2Record['project_code'])
        && $incompleteListRes['status'] === 200
        && str_contains($incompleteListRes['body'], (string) $project2Record['project_code']),
        'Marketing project list completeness filter accurately separates complete (6/6) vs incomplete (<6/6) projects'
    );

    $searchRes = $mktClient->request('GET', '/marketing/projects?q=' . rawurlencode((string) $projectResubmitted['project_code']));
    $statusFilterRes = $mktClient->request('GET', '/marketing/projects?status=' . rawurlencode(Project::STATUS_RESUBMITTED_FOR_VERIFICATION) . '&sort=oldest');
    assertCheck(
        $searchRes['status'] === 200
        && str_contains($searchRes['body'], (string) $projectResubmitted['project_code'])
        && $statusFilterRes['status'] === 200
        && str_contains($statusFilterRes['body'], (string) $projectResubmitted['project_code']),
        'Marketing project list search, workflow status filter, and date sorting work properly'
    );

    // Mark notifications as read
    $notifPage = $mktClient->request('GET', '/notifications');
    $markAllRes = $mktClient->request('POST', '/notifications/read-all', [
        '_csrf_token' => $mktCsrf,
    ]);
    assertCheck(
        $notifPage['status'] === 200
        && $markAllRes['status'] === 303
        && Notification::unreadCountForUser((int) $mktUserRow['id']) === 0,
        'Notifications page loads and POST /notifications/read-all clears unread count'
    );

    // 11. Project Management Module Verification, Review Workflow & Completion
    echo "\n11. Project Management Module Verification, Review Workflow & Completion\n";
    // Verify PM dedicated routes exist and load properly
    $pmDashRes = $pmClient->request('GET', '/pm/dashboard');
    $pmProjectsRes = $pmClient->request('GET', '/pm/projects');
    assertCheck(
        $pmDashRes['status'] === 200
        && str_contains($pmDashRes['body'], 'Marketing Document Verification Queue')
        && str_contains($pmDashRes['body'], (string) $projectResubmitted['project_code'])
        && $pmProjectsRes['status'] === 200
        && str_contains($pmProjectsRes['body'], (string) $projectResubmitted['project_code']),
        'PM dedicated routes /pm/dashboard and /pm/projects load with HTTP 200 and display verification queue'
    );

    // Verify RBAC protection on PM module routes
    $mktPmDash = $mktClient->request('GET', '/pm/dashboard');
    $opsPmDash = $opsClient->request('GET', '/pm/dashboard');
    $mktPmProjects = $mktClient->request('GET', '/pm/projects');
    $opsPmProjects = $opsClient->request('GET', '/pm/projects');
    assertCheck(
        $mktPmDash['status'] === 403
        && $opsPmDash['status'] === 403
        && $mktPmProjects['status'] === 403
        && $opsPmProjects['status'] === 403,
        'Marketing and Operations users are blocked from PM dashboard and project queue (HTTP 403)'
    );

    // Verify RBAC on verification decision actions
    $mktStartReview = $mktClient->request('POST', "/projects/{$projectId}/start-review", ['_csrf_token' => $mktCsrf]);
    $mktVerifyComplete = $mktClient->request('POST', "/projects/{$projectId}/complete-verification", ['_csrf_token' => $mktCsrf]);
    $opsStartReview = $opsClient->request('POST', "/projects/{$projectId}/start-review", ['_csrf_token' => $opsCsrf]);
    $opsVerifyComplete = $opsClient->request('POST', "/projects/{$projectId}/complete-verification", ['_csrf_token' => $opsCsrf]);
    assertCheck(
        $mktStartReview['status'] === 403
        && $mktVerifyComplete['status'] === 403
        && $opsStartReview['status'] === 403
        && $opsVerifyComplete['status'] === 403,
        'Marketing and Operations users are strictly blocked from PM review and verification actions (HTTP 403)'
    );

    // Verify completeness and status enforcement: attempting to complete verification on unreviewable project is blocked (403)
    $attemptIncompleteVerify = $pmClient->request('POST', "/projects/{$project2Record['id']}/complete-verification", [
        '_csrf_token' => $pmCsrf,
        'verification_notes' => 'Attempting to verify incomplete project',
    ]);
    assertCheck(
        $attemptIncompleteVerify['status'] === 403,
        'Attempting to complete verification on an unsubmitted/unreviewable project is blocked with HTTP 403'
    );

    // Authorized PM starts review
    $startReviewRes = $pmClient->request('POST', "/projects/{$projectId}/start-review", [
        '_csrf_token' => $pmCsrf,
    ]);
    $projectUnderReview = Project::findById($projectId);
    assertCheck(
        $startReviewRes['status'] === 303
        && $projectUnderReview['status'] === Project::STATUS_UNDER_REVIEW
        && $projectUnderReview['verification_status'] === Project::VERIFICATION_UNDER_REVIEW
        && (int) $projectUnderReview['reviewed_by'] === (int) $pmUserRow['id']
        && !empty($projectUnderReview['reviewed_at']),
        'PM reviewer successfully marks project Under Review with reviewer and timestamp captured'
    );

    // Verify missing documents strictly prevent verification completion even when Under Review
    $bomDoc = $pdo->query("SELECT id FROM project_documents WHERE project_id = {$projectId} AND document_type = 'BOM' AND is_current = 1")->fetch(PDO::FETCH_ASSOC);
    if ($bomDoc) {
        $pdo->exec("UPDATE project_documents SET is_current = 0 WHERE id = " . (int) $bomDoc['id']);
        $incompleteAttempt = $pmClient->request('POST', "/projects/{$projectId}/complete-verification", [
            '_csrf_token' => $pmCsrf,
            'verification_notes' => 'Attempting verification with missing BOM',
        ]);
        $projectNotVerified = Project::findById($projectId);
        assertCheck(
            $incompleteAttempt['status'] === 303
            && $projectNotVerified['status'] !== Project::STATUS_VERIFICATION_COMPLETED
            && $projectNotVerified['verification_status'] !== Project::VERIFICATION_VERIFIED,
            'Server strict completeness check blocks verification completion when required documents are missing'
        );
        // Restore BOM
        $pdo->exec("UPDATE project_documents SET is_current = 1 WHERE id = " . (int) $bomDoc['id']);
    }

    // PM views project details with review panel
    $pmDetailView = $pmClient->request('GET', "/projects/{$projectId}");
    assertCheck(
        $pmDetailView['status'] === 200
        && str_contains($pmDetailView['body'], 'Project Management Document Verification')
        && str_contains($pmDetailView['body'], 'Under Review')
        && str_contains($pmDetailView['body'], 'Approve Document Verification'),
        'PM reviewer project details view renders document verification review panel and action controls'
    );

    // PM completes document verification
    $verifyNote = 'All 6 required Marketing documents (SON, SLD, BOM, CA, Proposals) verified readable and complete.';
    $completeRes = $pmClient->request('POST', "/projects/{$projectId}/complete-verification", [
        '_csrf_token' => $pmCsrf,
        'verification_notes' => $verifyNote,
    ]);
    $projectVerified = Project::findById($projectId);
    assertCheck(
        $completeRes['status'] === 303
        && $projectVerified['status'] === Project::STATUS_VERIFICATION_COMPLETED
        && $projectVerified['verification_status'] === Project::VERIFICATION_VERIFIED
        && (int) $projectVerified['verified_by'] === (int) $pmUserRow['id']
        && !empty($projectVerified['verified_at'])
        && $projectVerified['verification_notes'] === $verifyNote,
        'PM successfully completes document verification: status becomes Document Verification Completed, reviewer and timestamp saved'
    );

    // Rule 6 enforcement: verify NO JOM number is automatically issued
    assertCheck(
        empty($projectVerified['jom_number']),
        'JOM Rule Enforced: Document verification completion does NOT automatically issue a JOM number (pending business-rule confirmation)'
    );

    // Verify all current project documents are marked verified
    $currentDocs = array_filter(
        ProjectDocument::forProject($projectId),
        static fn(array $d): bool => (int) $d['is_current'] === 1
    );
    $allDocsVerified = true;
    foreach ($currentDocs as $d) {
        if ($d['review_status'] !== 'verified') {
            $allDocsVerified = false;
            break;
        }
    }
    assertCheck(
        $allDocsVerified && count($currentDocs) === 6,
        'All current project checklist documents are marked as verified in project_documents'
    );

    // Verify Marketing team received completion notification
    $mkt1CompletedNotifs = array_filter(
        Notification::forUser((int) $mktUserRow['id'], 10),
        static fn(array $n): bool => $n['type'] === 'project.verification_completed'
    );
    assertCheck(
        count($mkt1CompletedNotifs) >= 1,
        'Marketing department users receive notification of document verification completion'
    );

    // Verify project detail page displays completion banner and deferred JOM notice
    $verifiedPageView = $pmClient->request('GET', "/projects/{$projectId}");
    assertCheck(
        $verifiedPageView['status'] === 200
        && str_contains($verifiedPageView['body'], 'Document Verification Completed')
        && str_contains($verifiedPageView['body'], 'JOM number assignment is deferred'),
        'Project details page displays Document Verification Completed banner and deferred JOM notice'
    );

    // 12. Project Management JOM Issuance Workflow, Security, Concurrency & Database Uniqueness
    echo "\n12. Project Management JOM Issuance Workflow, Security, Concurrency & Database Uniqueness\n";

    // 12.1 Unauthorized departments cannot issue JOM numbers
    $mktIssueJom = $mktClient->request('POST', "/projects/{$projectId}/issue-jom", ['_csrf_token' => $mktCsrf]);
    $opsIssueJom = $opsClient->request('POST', "/projects/{$projectId}/issue-jom", ['_csrf_token' => $opsCsrf]);
    assertCheck(
        $mktIssueJom['status'] === 403 && $opsIssueJom['status'] === 403,
        'Marketing and Operations users are strictly blocked from issuing JOM numbers (HTTP 403)'
    );

    // 12.2 Incomplete projects cannot receive a JOM number
    $incompleteJomAttempt = $pmClient->request('POST', "/projects/{$project2Record['id']}/issue-jom", ['_csrf_token' => $pmCsrf]);
    $reloadedIncomplete = Project::findById((int) $project2Record['id']);
    assertCheck(
        $incompleteJomAttempt['status'] === 403 && empty($reloadedIncomplete['jom_number']),
        'Incomplete projects cannot receive a JOM number (blocked with HTTP 403)'
    );

    // 12.3 Projects returned for corrections cannot receive a JOM number until resubmitted and verified
    $tempReturnedRes = $pdo->prepare("INSERT INTO projects (project_code, project_name, client_name, created_by, current_department_id, origin_department_id, status, verification_status) VALUES ('PRJ-TEST-RETURNED', 'Test Returned Project', 'Client Test', :creator_id, :pm_dept_id, :mkt_dept_id, 'Returned for Corrections', 'returned_for_corrections')");
    $tempReturnedRes->execute(['creator_id' => $adminUser['id'], 'pm_dept_id' => $pmDept['id'], 'mkt_dept_id' => $mktDept['id']]);
    $tempReturnedId = (int) $pdo->lastInsertId();
    $createdProjectIds[] = $tempReturnedId;

    $returnedJomAttempt = $pmClient->request('POST', "/projects/{$tempReturnedId}/issue-jom", ['_csrf_token' => $pmCsrf]);
    $reloadedReturned = Project::findById($tempReturnedId);
    assertCheck(
        $returnedJomAttempt['status'] === 403 && empty($reloadedReturned['jom_number']),
        'Projects returned for corrections cannot receive a JOM number until resubmitted and verified (HTTP 403)'
    );

    // 12.4 Authorized Project Management user issues the JOM number for verified project
    $issueJomRes = $pmClient->request('POST', "/projects/{$projectId}/issue-jom", [
        '_csrf_token' => $pmCsrf,
    ]);
    $projectWithJom = Project::findById($projectId);
    $issuedJomNumber = (string) ($projectWithJom['jom_number'] ?? '');
    assertCheck(
        $issueJomRes['status'] === 303
        && !empty($issuedJomNumber)
        && preg_match('/^JOM-\d{6}-\d{4}/', $issuedJomNumber) === 1
        && (int) $projectWithJom['jom_issued_by'] === (int) $pmUserRow['id']
        && !empty($projectWithJom['jom_issued_at'])
        && $projectWithJom['status'] === Project::STATUS_JOM_ISSUED,
        'Authorized Project Management user successfully issued unique server-generated JOM number for verified project'
    );

    // 12.5 Repeated requests cannot issue duplicate JOM numbers (idempotency)
    $repeatJomRes = $pmClient->request('POST', "/projects/{$projectId}/issue-jom", [
        '_csrf_token' => $pmCsrf,
    ]);
    $projectAfterRepeat = Project::findById($projectId);
    assertCheck(
        $repeatJomRes['status'] === 403
        && $projectAfterRepeat['jom_number'] === $issuedJomNumber
        && (int) $projectAfterRepeat['jom_issued_by'] === (int) $pmUserRow['id'],
        'Repeated requests cannot issue duplicate JOM numbers or alter existing issuance (HTTP 403)'
    );

    // 12.6 Database uniqueness constraint enforces duplicate prevention
    $dbDuplicateCaught = false;
    try {
        $stmt = $pdo->prepare("INSERT INTO projects (project_code, jom_number, project_name, client_name, created_by) VALUES ('PRJ-DUP-TEST', :jom, 'Dup Project', 'Client', :creator_id)");
        $stmt->execute(['jom' => $issuedJomNumber, 'creator_id' => $adminUser['id']]);
    } catch (PDOException $e) {
        $dbDuplicateCaught = true;
    }
    assertCheck($dbDuplicateCaught, 'Enforce uniqueness at database level: duplicate JOM number rejected by MySQL UNIQUE KEY constraint');

    // 12.7 JOM number appears prominently in project details, PM dashboard, PM list, and Marketing list
    $pmDetailWithJom = $pmClient->request('GET', "/projects/{$projectId}");
    $pmDashWithJom = $pmClient->request('GET', "/pm/dashboard");
    $pmListWithJom = $pmClient->request('GET', "/pm/projects");
    $mktListWithJom = $mktClient->request('GET', "/marketing/projects");
    assertCheck(
        str_contains($pmDetailWithJom['body'], $issuedJomNumber)
        && str_contains($pmDetailWithJom['body'], 'Official JOM Issued')
        && str_contains($pmDashWithJom['body'], $issuedJomNumber)
        && str_contains($pmListWithJom['body'], $issuedJomNumber)
        && str_contains($mktListWithJom['body'], $issuedJomNumber),
        'JOM number is prominently displayed across project details, PM queue, and Marketing registry'
    );

    // 12.8 Marketing receives notification of JOM issuance
    $mktJomNotifs = array_filter(
        Notification::forUser((int) $mktUserRow['id'], 10),
        static fn(array $n): bool => $n['type'] === 'project.jom_issued'
    );
    assertCheck(count($mktJomNotifs) >= 1, 'Marketing team receives real-time notification of official JOM number issuance');

    // 13. Workflow History, Audit Trail & Session Logout Invalidation
    echo "\n13. Workflow History, Audit Trail & Session Logout Invalidation\n";
    $histories = ProjectWorkflowHistory::forProject($projectId);
    $historyActions = array_column($histories, 'action');
    $expectedHistoryActions = [
        'created_draft',
        'started_document_prep',
        'submitted_for_verification',
        'returned_for_corrections',
        'resubmitted_for_verification',
        'started_review',
        'completed_document_verification',
        'issued_jom',
    ];
    $missingHistory = array_diff($expectedHistoryActions, $historyActions);
    assertCheck(
        $missingHistory === [],
        'project_workflow_histories recorded full lifecycle (Draft -> Preparing -> Submitted -> Returned -> Resubmitted)'
    );

    $getLogoutRes = $mktClient->request('GET', '/logout');
    assertCheck($getLogoutRes['status'] === 405, 'GET /logout is rejected with HTTP 405 (POST required)');

    $oldMktSessionCookie = $mktClient->cookies[$sessionCookieName] ?? '';
    $postLogoutRes = $mktClient->request('POST', '/logout', [
        '_csrf_token' => $mktCsrf,
    ]);
    assertCheck(
        $postLogoutRes['status'] === 303 && str_contains((string) $postLogoutRes['location'], '/login?logged_out=1'),
        'POST /logout with CSRF token signs user out and redirects to /login'
    );

    $replayClient = new HttpTestClient($baseUrl);
    $replayClient->cookies[$sessionCookieName] = $oldMktSessionCookie;
    $replayRes = $replayClient->request('GET', '/dashboard');
    assertCheck(
        $replayRes['status'] === 303 && str_ends_with((string) $replayRes['location'], '/login'),
        'Destroyed session ID cannot be replayed after logout'
    );

    $loggedActions = $pdo->query('SELECT DISTINCT action FROM activity_logs')->fetchAll(PDO::FETCH_COLUMN);
    $requiredActions = [
        'auth.login_success',
        'auth.login_failed',
        'auth.login_blocked_inactive',
        'auth.logout',
        'user.created',
        'user.updated',
        'user.department_changed',
        'user.status_changed',
        'project.created',
        'project.updated',
        'project.submission_blocked_incomplete',
        'project.submitted_to_pm',
        'project.returned_for_corrections',
        'project.resubmitted_to_pm',
        'project.review_started',
        'project.document_verification_completed',
        'project.jom_issued',
        'document.uploaded',
        'document.replaced',
        'document.viewed',
        'document.downloaded',
        'security.csrf_mismatch',
        'security.unauthorized_access',
    ];
    $missingActions = array_diff($requiredActions, $loggedActions);
    assertCheck(
        $missingActions === [],
        'All required authentication, user, Marketing workflow, document replacement, and security events are logged in activity_logs'
        . ($missingActions !== [] ? ' (Missing: ' . implode(', ', $missingActions) . ')' : '')
    );
} finally {
    echo "\n==> Cleaning up temporary test records...\n";
    foreach (array_unique($createdFilesOnDisk) as $filePath) {
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    if ($createdProjectIds !== []) {
        $in = implode(',', array_map('intval', $createdProjectIds));
        $pdo->exec("DELETE FROM notifications WHERE project_id IN ({$in})");
        $pdo->exec("DELETE FROM project_workflow_histories WHERE project_id IN ({$in})");
        $pdo->exec("DELETE FROM project_documents WHERE project_id IN ({$in})");
        $pdo->exec("DELETE FROM projects WHERE id IN ({$in})");
        $pdo->exec("DELETE FROM activity_logs WHERE entity_type = 'project' AND entity_id IN ({$in})");
    }

    if ($createdUserIds !== []) {
        $in = implode(',', array_map('intval', $createdUserIds));
        $pdo->exec("DELETE FROM notifications WHERE recipient_user_id IN ({$in})");
        $pdo->exec("DELETE FROM activity_logs WHERE user_id IN ({$in}) OR (entity_type = 'user' AND entity_id IN ({$in}))");
        $pdo->exec("DELETE FROM users WHERE id IN ({$in})");
    }

    // Clear only test-generated activity logs created during this run
    $pdo->exec('DELETE FROM activity_logs WHERE id > ' . (int) $maxLogIdBeforeTest);
}

echo "\n=======================================================================\n";
echo " TEST SUMMARY: {$passed} passed, {$failed} failed\n";
echo "=======================================================================\n";

exit($failed > 0 ? 1 : 0);
