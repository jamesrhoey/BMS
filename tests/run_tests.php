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
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierDelivery;
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
                $payload = self::flattenPostData($postData);
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public static function flattenPostData(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";
            if (is_array($value)) {
                $result = array_merge($result, self::flattenPostData($value, $fullKey));
            } else {
                $result[$fullKey] = (string) $value;
            }
        }
        return $result;
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
$adminSnapshot = $pdo->query("SELECT id, password_hash, status FROM users WHERE username = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($adminSnapshot) {
    $stmtTempAdmin = $pdo->prepare("UPDATE users SET password_hash = :hash, status = 'active' WHERE id = :id");
    $stmtTempAdmin->execute([
        'hash' => password_hash('Admin@SYB2026!', PASSWORD_DEFAULT),
        'id' => (int) $adminSnapshot['id'],
    ]);
}

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

    // Marketing Department Redesigned Sidebar Navigation Verification
    assertCheck(
        str_contains($mktDash['body'], 'data-nav-group="marketing-pm"')
        && str_contains($mktDash['body'], 'data-nav-group="marketing-docs"')
        && str_contains($mktDash['body'], 'data-nav-group="marketing-tracking"')
        && str_contains($mktDash['body'], 'Project Management')
        && str_contains($mktDash['body'], 'Document Management')
        && str_contains($mktDash['body'], 'Project Tracking')
        && str_contains($mktDash['body'], '/marketing/projects/create')
        && str_contains($mktDash['body'], 'Required Project Documents')
        && str_contains($mktDash['body'], 'Submitted Documents')
        && str_contains($mktDash['body'], 'Pending Reviews')
        && str_contains($mktDash['body'], 'Returned Projects')
        && str_contains($mktDash['body'], 'Approved Projects')
        && str_contains($mktDash['body'], 'Forwarded Projects')
        && !str_contains($mktDash['body'], '>All Projects<'),
        'Marketing sidebar renders organized dropdown navigation (Project Management, Document Management, Project Tracking)'
    );

    $mktCreatePage = $mktClient->request('GET', '/marketing/projects/create');
    assertCheck(
        $mktCreatePage['status'] === 200
        && str_contains($mktCreatePage['body'], 'data-nav-group="marketing-pm"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="marketing-pm"/s', $mktCreatePage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Project Registration/s', $mktCreatePage['body']) === 1,
        'Project Registration page (/marketing/projects/create) returns HTTP 200 and auto-expands Project Management dropdown'
    );

    $mktDocsIncompletePage = $mktClient->request('GET', '/marketing/projects?completeness=incomplete');
    assertCheck(
        $mktDocsIncompletePage['status'] === 200
        && str_contains($mktDocsIncompletePage['body'], 'data-nav-group="marketing-docs"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="marketing-docs"/s', $mktDocsIncompletePage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Required Project Documents/s', $mktDocsIncompletePage['body']) === 1,
        'Required Project Documents filter returns HTTP 200 and auto-expands Document Management dropdown'
    );

    $mktTrackingPage = $mktClient->request('GET', '/marketing/projects?status=pm_queue');
    assertCheck(
        $mktTrackingPage['status'] === 200
        && str_contains($mktTrackingPage['body'], 'data-nav-group="marketing-tracking"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="marketing-tracking"/s', $mktTrackingPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Pending Reviews/s', $mktTrackingPage['body']) === 1,
        'Pending Reviews tracking filter returns HTTP 200 and auto-expands Project Tracking dropdown'
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

    // Project Management Department Redesigned Sidebar Navigation Verification
    assertCheck(
        str_contains($pmDash['body'], 'data-nav-group="pm-intake"')
        && str_contains($pmDash['body'], 'data-nav-group="pm-verification"')
        && str_contains($pmDash['body'], 'data-nav-group="pm-review"')
        && str_contains($pmDash['body'], 'data-nav-group="pm-approval"')
        && str_contains($pmDash['body'], 'data-nav-group="pm-forwarding"')
        && str_contains($pmDash['body'], 'Project Management Dashboard')
        && str_contains($pmDash['body'], 'Incoming Projects')
        && str_contains($pmDash['body'], 'All Received Projects')
        && str_contains($pmDash['body'], 'Document Verification Queue')
        && str_contains($pmDash['body'], 'Resubmitted Documents')
        && str_contains($pmDash['body'], 'Projects Under Review')
        && str_contains($pmDash['body'], 'Returned Projects')
        && str_contains($pmDash['body'], 'Approved Projects')
        && str_contains($pmDash['body'], 'Ready for Admin Finance')
        && str_contains($pmDash['body'], 'Forwarded to Admin Finance'),
        'Project Management sidebar renders organized dropdown navigation (Intake, Verification, Review, Approval, Forwarding)'
    );

    // Verify auto-expansion on filter navigation
    $pmIntakePage = $pmClient->request('GET', '/pm/projects?status=pending');
    assertCheck(
        $pmIntakePage['status'] === 200
        && str_contains($pmIntakePage['body'], 'data-nav-group="pm-intake"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="pm-intake"/s', $pmIntakePage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Incoming Projects/s', $pmIntakePage['body']) === 1,
        'Incoming Projects filter returns HTTP 200 and auto-expands Project Intake dropdown'
    );

    $pmVerificationPage = $pmClient->request('GET', '/pm/projects?status=verification_queue');
    assertCheck(
        $pmVerificationPage['status'] === 200
        && str_contains($pmVerificationPage['body'], 'data-nav-group="pm-verification"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="pm-verification"/s', $pmVerificationPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Document Verification Queue/s', $pmVerificationPage['body']) === 1,
        'Document Verification Queue filter returns HTTP 200 and auto-expands Document Verification dropdown'
    );

    $pmReviewPage = $pmClient->request('GET', '/pm/projects?status=under_review');
    assertCheck(
        $pmReviewPage['status'] === 200
        && str_contains($pmReviewPage['body'], 'data-nav-group="pm-review"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="pm-review"/s', $pmReviewPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Projects Under Review/s', $pmReviewPage['body']) === 1,
        'Projects Under Review filter returns HTTP 200 and auto-expands Project Review dropdown'
    );

    $pmApprovalPage = $pmClient->request('GET', '/pm/projects?status=approved');
    assertCheck(
        $pmApprovalPage['status'] === 200
        && str_contains($pmApprovalPage['body'], 'data-nav-group="pm-approval"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="pm-approval"/s', $pmApprovalPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Approved Projects/s', $pmApprovalPage['body']) === 1,
        'Approved Projects filter returns HTTP 200 and auto-expands Project Approval dropdown'
    );

    $pmForwardingPage = $pmClient->request('GET', '/pm/projects?status=ready_for_admin_finance');
    assertCheck(
        $pmForwardingPage['status'] === 200
        && str_contains($pmForwardingPage['body'], 'data-nav-group="pm-forwarding"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="pm-forwarding"/s', $pmForwardingPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Ready for Admin Finance/s', $pmForwardingPage['body']) === 1,
        'Ready for Admin Finance filter returns HTTP 200 and auto-expands Project Forwarding dropdown'
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

    // PM completes document verification (Approves project)
    $verifyNote = 'All 6 required Marketing documents (SON, SLD, BOM, CA, Proposals) verified readable and complete.';
    $completeRes = $pmClient->request('POST', "/projects/{$projectId}/complete-verification", [
        '_csrf_token' => $pmCsrf,
        'verification_notes' => $verifyNote,
    ]);
    $projectVerified = Project::findById($projectId);
    assertCheck(
        $completeRes['status'] === 303
        && $projectVerified['status'] === Project::STATUS_APPROVED_BY_PM
        && $projectVerified['verification_status'] === Project::VERIFICATION_VERIFIED
        && (int) $projectVerified['verified_by'] === (int) $pmUserRow['id']
        && !empty($projectVerified['verified_at'])
        && $projectVerified['verification_notes'] === $verifyNote,
        'PM successfully approves complete project: status becomes Approved by Project Management, reviewer and timestamp saved'
    );

    // Business Rule: verify NO JOM number is automatically issued upon PM approval
    assertCheck(
        empty($projectVerified['jom_number']),
        'Business Rule Enforced: PM approval does NOT automatically generate a JOM number'
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
        'Marketing department users receive notification of Project Management approval'
    );

    // Verify project detail page displays Approved by Project Management banner, Forward to Admin Finance action, and NO PM JOM issuance button
    $verifiedPageView = $pmClient->request('GET', "/projects/{$projectId}");
    assertCheck(
        $verifiedPageView['status'] === 200
        && str_contains($verifiedPageView['body'], 'Approved by Project Management')
        && str_contains($verifiedPageView['body'], 'Forward to Admin Finance')
        && !str_contains($verifiedPageView['body'], 'id="admin-finance-jom-panel"'),
        'Project details page displays Approved by Project Management banner, Forward to Admin Finance action, and hides JOM issuance from PM'
    );

    // 12. Forwarding to Admin Finance, PM JOM Restriction & Admin Finance Incoming Queue
    echo "\n12. Forwarding to Admin Finance, PM JOM Restriction & Admin Finance Incoming Queue\n";

    // 12.1 Project Management CANNOT issue JOM numbers (strictly blocked with HTTP 403)
    $pmIssueJomAttempt = $pmClient->request('POST', "/projects/{$projectId}/issue-jom", [
        '_csrf_token' => $pmCsrf,
    ]);
    $reloadedAfterPmJomAttempt = Project::findById($projectId);
    assertCheck(
        $pmIssueJomAttempt['status'] === 403 && empty($reloadedAfterPmJomAttempt['jom_number']),
        'Project Management user is strictly blocked from issuing JOM numbers (HTTP 403)'
    );

    // 12.2 Marketing and Operations users cannot issue JOM numbers or forward to Admin Finance
    $mktIssueJom = $mktClient->request('POST', "/projects/{$projectId}/issue-jom", ['_csrf_token' => $mktCsrf]);
    $opsIssueJom = $opsClient->request('POST', "/projects/{$projectId}/issue-jom", ['_csrf_token' => $opsCsrf]);
    $mktForwardAfin = $mktClient->request('POST', "/projects/{$projectId}/forward-to-admin-finance", ['_csrf_token' => $mktCsrf]);
    $opsForwardAfin = $opsClient->request('POST', "/projects/{$projectId}/forward-to-admin-finance", ['_csrf_token' => $opsCsrf]);
    assertCheck(
        $mktIssueJom['status'] === 403
        && $opsIssueJom['status'] === 403
        && $mktForwardAfin['status'] === 403
        && $opsForwardAfin['status'] === 403,
        'Marketing and Operations users are strictly blocked from JOM issuance and forwarding to Admin Finance (HTTP 403)'
    );

    // 12.3 Unapproved / incomplete projects cannot be forwarded to Admin Finance
    $unapprovedForwardAttempt = $pmClient->request('POST', "/projects/{$project2Record['id']}/forward-to-admin-finance", [
        '_csrf_token' => $pmCsrf,
    ]);
    assertCheck(
        $unapprovedForwardAttempt['status'] === 403,
        'Unapproved/incomplete projects cannot be forwarded to Admin Finance (blocked with HTTP 403)'
    );

    // 12.4 Authorized Project Management user forwards approved project to Admin Finance
    $afinDept = Department::findByCode(Department::CODE_ADMIN_FINANCE);
    $forwardNote = 'All 6 Marketing documents approved by PM. Forwarding to Admin Finance for JOM issuance.';
    $forwardRes = $pmClient->request('POST', "/projects/{$projectId}/forward-to-admin-finance", [
        '_csrf_token' => $pmCsrf,
        'forward_notes' => $forwardNote,
    ]);
    $projectForwarded = Project::findById($projectId);
    assertCheck(
        $forwardRes['status'] === 303
        && $projectForwarded['status'] === Project::STATUS_FORWARDED_TO_ADMIN_FINANCE
        && (int) $projectForwarded['current_department_id'] === (int) $afinDept['id']
        && (int) $projectForwarded['forwarded_to_afin_by'] === (int) $pmUserRow['id']
        && !empty($projectForwarded['forwarded_to_afin_at'])
        && $projectForwarded['forwarded_to_afin_notes'] === $forwardNote
        && (int) $projectForwarded['verified_by'] === (int) $pmUserRow['id']
        && !empty($projectForwarded['verified_at'])
        && empty($projectForwarded['jom_number']),
        'Approved project forwarded to Admin Finance: status is Forwarded to Admin Finance, routed to ADMFIN, forwarding user/timestamp saved, verification history preserved, and no automatic JOM or Operations jump'
    );

    // 12.5 Duplicate forwarding is prevented
    $duplicateForwardRes = $pmClient->request('POST', "/projects/{$projectId}/forward-to-admin-finance", [
        '_csrf_token' => $pmCsrf,
        'forward_notes' => 'Duplicate forward attempt',
    ]);
    $projectAfterDupForward = Project::findById($projectId);
    assertCheck(
        $duplicateForwardRes['status'] === 403
        && $projectAfterDupForward['forwarded_to_afin_notes'] === $forwardNote,
        'Duplicate forwarding to Admin Finance is prevented (HTTP 403) and original forwarding metadata is preserved'
    );

    // 12.6 PM Dashboard and PM Projects list reflect Forwarded to Admin Finance count and filter
    $pmDashAfterForward = $pmClient->request('GET', '/pm/dashboard');
    $pmForwardedFilterRes = $pmClient->request('GET', '/pm/projects?v_status=forwarded_to_admin_finance');
    assertCheck(
        $pmDashAfterForward['status'] === 200
        && str_contains($pmDashAfterForward['body'], 'Forwarded to Admin Finance')
        && $pmForwardedFilterRes['status'] === 200
        && str_contains($pmForwardedFilterRes['body'], (string) $projectForwarded['project_code']),
        'PM Dashboard and PM Projects queue display Forwarded to Admin Finance summary card and filter results'
    );

    // 12.7 Admin Finance Incoming Project Dashboard, Access Control, JOM Issuance & Operations Handoff
    $afinUsername = "test_afin_{$suffix}";
    $adminClient->request('POST', '/users', [
        '_csrf_token' => $adminCsrf,
        'full_name' => 'Admin Finance Officer',
        'username' => $afinUsername,
        'email' => "{$afinUsername}@syb.local",
        'role_id' => (string) $deptRole['id'],
        'department_id' => (string) $afinDept['id'],
        'status' => 'active',
        'password' => 'AfinPass@2026!',
        'password_confirmation' => 'AfinPass@2026!',
    ]);
    $afinUserRow = User::findByUsername($afinUsername);
    if ($afinUserRow !== null) {
        $createdUserIds[] = (int) $afinUserRow['id'];
    }

    // Verify unauthorized departments (PM, Marketing, Operations) cannot access /admin-finance/dashboard or /admin-finance/projects
    $pmToAfinDash = $pmClient->request('GET', '/admin-finance/dashboard');
    $mktToAfinDash = $mktClient->request('GET', '/admin-finance/dashboard');
    $opsToAfinDash = $opsClient->request('GET', '/admin-finance/projects');
    assertCheck(
        $pmToAfinDash['status'] === 403
        && $mktToAfinDash['status'] === 403
        && $opsToAfinDash['status'] === 403,
        'Unauthorized departments (PM, Marketing, Operations) are blocked from Admin Finance module routes (HTTP 403)'
    );

    $afinClient = new HttpTestClient($baseUrl);
    $afinLoginGet = $afinClient->request('GET', '/login');
    $afinClient->request('POST', '/login', [
        '_csrf_token' => $afinClient->extractCsrfToken($afinLoginGet['body']),
        'username' => $afinUsername,
        'password' => 'AfinPass@2026!',
    ]);
    $afinDash = $afinClient->request('GET', '/dashboard');
    $afinCsrf = $afinClient->extractCsrfToken($afinDash['body']);
    $afinProjectsPage = $afinClient->request('GET', '/admin-finance/projects');
    $afinDetail = $afinClient->request('GET', "/projects/{$projectId}");
    assertCheck(
        $afinDash['status'] === 200
        && str_contains($afinDash['body'], 'Projects Awaiting JOM Issuance')
        && str_contains($afinDash['body'], 'Projects Under Admin Finance Review')
        && str_contains($afinDash['body'], 'Projects With JOM Issued')
        && str_contains($afinDash['body'], 'Projects Forwarded to Operations')
        && str_contains($afinDash['body'], (string) $projectForwarded['project_code'])
        && $afinProjectsPage['status'] === 200
        && str_contains($afinProjectsPage['body'], (string) $projectForwarded['project_code'])
        && $afinDetail['status'] === 200
        && str_contains($afinDetail['body'], 'id="admin-finance-jom-panel"'),
        'Admin Finance Dashboard (/dashboard & /admin-finance/projects) displays all 4 summary cards, approved incoming project, and JOM issuance panel'
    );

    // Admin Finance Department Redesigned Sidebar Navigation Verification
    assertCheck(
        str_contains($afinDash['body'], 'data-nav-group="afin-inbox"')
        && str_contains($afinDash['body'], 'data-nav-group="afin-jom"')
        && str_contains($afinDash['body'], 'data-nav-group="afin-handover"')
        && str_contains($afinDash['body'], 'data-nav-group="afin-actual-bom"')
        && str_contains($afinDash['body'], 'data-nav-group="afin-purchasing"')
        && str_contains($afinDash['body'], 'Admin Finance Dashboard')
        && str_contains($afinDash['body'], 'Incoming Approved Projects')
        && str_contains($afinDash['body'], 'All Received Projects')
        && str_contains($afinDash['body'], 'JOM Issuance Queue')
        && str_contains($afinDash['body'], 'Projects Under JOM Review')
        && str_contains($afinDash['body'], 'Issued JOM Records')
        && str_contains($afinDash['body'], 'Projects Ready for Operations')
        && str_contains($afinDash['body'], 'Forwarded Projects')
        && str_contains($afinDash['body'], 'Incoming Actual BOM')
        && str_contains($afinDash['body'], 'Actual BOM Under Review')
        && str_contains($afinDash['body'], 'Returned Actual BOM')
        && str_contains($afinDash['body'], 'Accepted Actual BOM')
        && str_contains($afinDash['body'], 'Purchase Order Records')
        && str_contains($afinDash['body'], 'Purchase Order Tracking'),
        'Admin Finance sidebar renders organized dropdown navigation (Inbox, JOM, Handover, Actual BOM, Purchasing)'
    );

    // Verify auto-expansion on filter navigation
    $afinInboxPage = $afinClient->request('GET', '/admin-finance/projects?status=incoming_approved');
    assertCheck(
        $afinInboxPage['status'] === 200
        && str_contains($afinInboxPage['body'], 'data-nav-group="afin-inbox"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="afin-inbox"/s', $afinInboxPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Incoming Approved Projects/s', $afinInboxPage['body']) === 1,
        'Incoming Approved Projects filter returns HTTP 200 and auto-expands Project Inbox dropdown'
    );

    $afinJomPage = $afinClient->request('GET', '/admin-finance/projects?status=awaiting_jom');
    assertCheck(
        $afinJomPage['status'] === 200
        && str_contains($afinJomPage['body'], 'data-nav-group="afin-jom"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="afin-jom"/s', $afinJomPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*JOM Issuance Queue/s', $afinJomPage['body']) === 1,
        'JOM Issuance Queue filter returns HTTP 200 and auto-expands Job Order Management dropdown'
    );

    $afinHandoverPage = $afinClient->request('GET', '/admin-finance/projects?status=ready_for_operations');
    assertCheck(
        $afinHandoverPage['status'] === 200
        && str_contains($afinHandoverPage['body'], 'data-nav-group="afin-handover"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="afin-handover"/s', $afinHandoverPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Projects Ready for Operations/s', $afinHandoverPage['body']) === 1,
        'Projects Ready for Operations filter returns HTTP 200 and auto-expands Operations Handover dropdown'
    );

    $afinActualBomPage = $afinClient->request('GET', '/admin-finance/projects?status=actual_bom_submitted');
    assertCheck(
        $afinActualBomPage['status'] === 200
        && str_contains($afinActualBomPage['body'], 'data-nav-group="afin-actual-bom"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="afin-actual-bom"/s', $afinActualBomPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Incoming Actual BOM/s', $afinActualBomPage['body']) === 1,
        'Incoming Actual BOM filter returns HTTP 200 and auto-expands Actual BOM Management dropdown'
    );

    $afinPurchasingPage = $afinClient->request('GET', '/admin-finance/projects?status=po_records');
    assertCheck(
        $afinPurchasingPage['status'] === 200
        && str_contains($afinPurchasingPage['body'], 'data-nav-group="afin-purchasing"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="afin-purchasing"/s', $afinPurchasingPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Purchase Order Records/s', $afinPurchasingPage['body']) === 1,
        'Purchase Order Records filter returns HTTP 200 and auto-expands Purchasing Management dropdown'
    );

    // 12.8 Verify Admin Finance receives ONLY approved & forwarded projects (unapproved project $project2Record is hidden & blocked)
    $afinUnapprovedView = $afinClient->request('GET', "/projects/{$project2Record['id']}");
    $afinUnapprovedJom = $afinClient->request('POST', "/projects/{$project2Record['id']}/issue-jom", ['_csrf_token' => $afinCsrf]);
    assertCheck(
        !str_contains($afinDash['body'], (string) $project2Record['project_code'])
        && !str_contains($afinProjectsPage['body'], (string) $project2Record['project_code'])
        && $afinUnapprovedView['status'] === 403
        && $afinUnapprovedJom['status'] === 403,
        'Admin Finance receives ONLY approved & forwarded projects; unapproved projects are excluded from queue and blocked from JOM issuance (HTTP 403)'
    );

    // 12.9 Admin Finance marks approved project as Under Admin Finance Review
    $afinStartReviewRes = $afinClient->request('POST', "/projects/{$projectId}/admin-finance-review", [
        '_csrf_token' => $afinCsrf,
    ]);
    $projectAfinReview = Project::findById($projectId);
    assertCheck(
        $afinStartReviewRes['status'] === 303
        && $projectAfinReview['status'] === Project::STATUS_UNDER_ADMIN_FINANCE_REVIEW
        && $projectAfinReview['verification_status'] === Project::VERIFICATION_UNDER_AFIN_REVIEW
        && (int) $projectAfinReview['afin_reviewed_by'] === (int) $afinUserRow['id']
        && !empty($projectAfinReview['afin_reviewed_at']),
        'Admin Finance marks project Under Admin Finance Review and records reviewer and timestamp'
    );

    // 12.10 JOM Issuance: Admin Finance issues JOM number while PM/Marketing/Operations remain blocked
    $pmStillBlockedJom = $pmClient->request('POST', "/projects/{$projectId}/issue-jom", ['_csrf_token' => $pmCsrf]);
    $afinIssueJomRes = $afinClient->request('POST', "/projects/{$projectId}/issue-jom", ['_csrf_token' => $afinCsrf]);
    $projectWithJom = Project::findById($projectId);
    $issuedJomNumber = (string) ($projectWithJom['jom_number'] ?? '');
    assertCheck(
        $pmStillBlockedJom['status'] === 403
        && $afinIssueJomRes['status'] === 303
        && $projectWithJom['status'] === Project::STATUS_JOM_ISSUED
        && !empty($issuedJomNumber)
        && preg_match('/^JOM-\d{4,6}-\d{4}/', $issuedJomNumber) === 1
        && (int) $projectWithJom['jom_issued_by'] === (int) $afinUserRow['id']
        && !empty($projectWithJom['jom_issued_at']),
        'Admin Finance issues unique server-generated JOM number, sets status to JOM Issued, and records issuing user and timestamp'
    );

    // 12.11 Prevent duplicate & concurrent JOM issuance (a project receives only one JOM number)
    $dupHttpJomRes = $afinClient->request('POST', "/projects/{$projectId}/issue-jom", ['_csrf_token' => $afinCsrf]);
    $dupServiceJomRes = \App\Services\ProjectService::issueJomNumber($projectWithJom, $afinUserRow, new \App\Core\Request());
    $projectAfterDupJom = Project::findById($projectId);
    $dbDuplicateCaught = false;
    try {
        $stmt = $pdo->prepare("INSERT INTO projects (project_code, jom_number, project_name, client_name, created_by) VALUES ('PRJ-DUP-TEST', :jom, 'Dup Project', 'Client', :creator_id)");
        $stmt->execute(['jom' => $issuedJomNumber, 'creator_id' => $adminUser['id']]);
    } catch (PDOException $e) {
        $dbDuplicateCaught = true;
    }
    assertCheck(
        $dupHttpJomRes['status'] === 403
        && $dupServiceJomRes['success'] === false
        && $projectAfterDupJom['jom_number'] === $issuedJomNumber
        && $dbDuplicateCaught,
        'A project receives only one JOM number: duplicate/concurrent JOM requests and DB duplicate JOM numbers are strictly prevented'
    );

    // 12.12 Verify PM and Marketing see the assigned JOM number read-only
    $pmDetailWithJom = $pmClient->request('GET', "/projects/{$projectId}");
    $mktDetailWithJom = $mktClient->request('GET', "/projects/{$projectId}");
    assertCheck(
        $pmDetailWithJom['status'] === 200
        && str_contains($pmDetailWithJom['body'], $issuedJomNumber)
        && $mktDetailWithJom['status'] === 200
        && str_contains($mktDetailWithJom['body'], $issuedJomNumber),
        'Project Management and Marketing can view the assigned JOM number read-only associated with the correct project'
    );

    // 12.13 Forwarding to Operations: require JOM Number + SLD + Marketing BOM (block if SLD or Marketing BOM is missing)
    $sldDocRow = ProjectDocument::findCurrentByType($projectId, 'SLD');
    $mktBomDocRow = ProjectDocument::findCurrentByType($projectId, 'BOM');
    if ($sldDocRow !== null) {
        $pdo->exec("UPDATE project_documents SET is_current = 0 WHERE id = " . (int) $sldDocRow['id']);
        $missingSldForwardRes = $afinClient->request('POST', "/projects/{$projectId}/forward-to-operations", [
            '_csrf_token' => $afinCsrf,
            'operations_forward_notes' => 'Attempt forward without SLD',
        ]);
        $projectNotForwardedOps = Project::findById($projectId);
        assertCheck(
            $missingSldForwardRes['status'] === 303
            && empty($projectNotForwardedOps['forwarded_to_ops_at'])
            && $projectNotForwardedOps['status'] === Project::STATUS_JOM_ISSUED,
            'Forwarding to Operations is blocked when any of the 3 required items (JOM Number, SLD, Marketing BOM) is missing'
        );
        $pdo->exec("UPDATE project_documents SET is_current = 1 WHERE id = " . (int) $sldDocRow['id']);
    }

    // 12.14 Admin Finance forwards JOM Number, SLD, and Marketing BOM to Operations
    $opsForwardNote = 'Official JOM Number, SLD, and Marketing BOM verified and forwarded to Operations.';
    $forwardOpsRes = $afinClient->request('POST', "/projects/{$projectId}/forward-to-operations", [
        '_csrf_token' => $afinCsrf,
        'operations_forward_notes' => $opsForwardNote,
    ]);
    $projectForwardedOps = Project::findById($projectId);
    assertCheck(
        $forwardOpsRes['status'] === 303
        && $projectForwardedOps['status'] === Project::STATUS_FORWARDED_TO_OPERATIONS
        && $projectForwardedOps['verification_status'] === Project::VERIFICATION_FORWARDED_OPS
        && (int) $projectForwardedOps['current_department_id'] === (int) $opsDept['id']
        && (int) $projectForwardedOps['forwarded_to_ops_by'] === (int) $afinUserRow['id']
        && !empty($projectForwardedOps['forwarded_to_ops_at'])
        && $projectForwardedOps['forwarded_to_ops_notes'] === $opsForwardNote
        && (int) $projectForwardedOps['ops_sld_document_id'] === (int) $sldDocRow['id']
        && (int) $projectForwardedOps['ops_bom_document_id'] === (int) $mktBomDocRow['id']
        && $projectForwardedOps['jom_number'] === $issuedJomNumber,
        'Admin Finance forwards JOM Number, SLD, and Marketing BOM to Operations: status becomes Forwarded to Operations, SLD & Marketing BOM IDs linked, sender/recipient/timestamp recorded'
    );

    // 12.15 Prevent duplicate forwarding to Operations
    $dupForwardOpsRes = $afinClient->request('POST', "/projects/{$projectId}/forward-to-operations", [
        '_csrf_token' => $afinCsrf,
        'operations_forward_notes' => 'Duplicate forward to Operations attempt',
    ]);
    assertCheck(
        $dupForwardOpsRes['status'] === 403,
        'Duplicate forwarding to Operations is prevented (HTTP 403)'
    );

    // 12.16 Operations user sees forwarded project in Operations Incoming Queue and can access JOM, SLD, and Marketing BOM
    $opsDashAfterForward = $opsClient->request('GET', '/dashboard');
    $opsProjectDetail = $opsClient->request('GET', "/projects/{$projectId}");
    $opsDownloadSld = $opsClient->request('GET', "/projects/{$projectId}/documents/" . (int) $sldDocRow['id'] . '/download');
    $opsDownloadBom = $opsClient->request('GET', "/projects/{$projectId}/documents/" . (int) $mktBomDocRow['id'] . '/download');
    $sonDocRow = ProjectDocument::findCurrentByType($projectId, 'SON');
    $opsDownloadSonBlocked = $opsClient->request('GET', "/projects/{$projectId}/documents/" . (int) $sonDocRow['id'] . '/download');
    assertCheck(
        $opsDashAfterForward['status'] === 200
        && (str_contains($opsDashAfterForward['body'], 'Operations Incoming Project Queue') || str_contains($opsDashAfterForward['body'], 'Operations Incoming Projects &amp; Purchase Request Queue'))
        && str_contains($opsDashAfterForward['body'], $issuedJomNumber)
        && $opsProjectDetail['status'] === 200
        && str_contains($opsProjectDetail['body'], 'id="operations-handoff-package"')
        && str_contains($opsProjectDetail['body'], $issuedJomNumber)
        && $opsDownloadSld['status'] === 200
        && $opsDownloadBom['status'] === 200
        && $opsDownloadSonBlocked['status'] === 403,
        'Operations user sees project in Operations Incoming Queue, can view/download SLD and Marketing BOM (HTTP 200), and is blocked from non-handoff documents (HTTP 403)'
    );

    // 13. Operations Purchase Request (PR) & OPS Finance Actual BOM Workflow
    echo "\n13. Operations Purchase Request (PR) & OPS Finance Actual BOM Workflow\n";

    // 13.1 Access Control: Verify unauthorized departments cannot access Operations or OPS Finance module routes
    $opfinDept = Department::findByCode(Department::CODE_OPERATION_FINANCE);
    $opfinUsername = "test_opfin_{$suffix}";
    $adminClient->request('POST', '/users', [
        '_csrf_token' => $adminCsrf,
        'full_name' => 'OPS Finance Specialist',
        'username' => $opfinUsername,
        'email' => "{$opfinUsername}@syb.local",
        'role_id' => (string) $deptRole['id'],
        'department_id' => (string) $opfinDept['id'],
        'status' => 'active',
        'password' => 'OpfinPass@2026!',
        'password_confirmation' => 'OpfinPass@2026!',
    ]);
    $opfinUserRow = User::findByUsername($opfinUsername);
    if ($opfinUserRow !== null) {
        $createdUserIds[] = (int) $opfinUserRow['id'];
    }

    $opfinClient = new HttpTestClient($baseUrl);
    $opfinLoginGet = $opfinClient->request('GET', '/login');
    $opfinClient->request('POST', '/login', [
        '_csrf_token' => $opfinClient->extractCsrfToken($opfinLoginGet['body']),
        'username' => $opfinUsername,
        'password' => 'OpfinPass@2026!',
    ]);
    $opfinDash = $opfinClient->request('GET', '/dashboard');
    $opfinCsrf = $opfinClient->extractCsrfToken($opfinDash['body']);

    // Check unauthorized route blocking (HTTP 403)
    $mktToOpsDash = $mktClient->request('GET', '/operations/dashboard');
    $pmToOpsDash = $pmClient->request('GET', '/operations/projects');
    $afinToOpsDash = $afinClient->request('GET', '/operations/dashboard');
    $mktToOpfinDash = $mktClient->request('GET', '/ops-finance/dashboard');
    $pmToOpfinDash = $pmClient->request('GET', '/ops-finance/projects');
    $opsToOpfinDash = $opsClient->request('GET', '/ops-finance/dashboard');
    assertCheck(
        $mktToOpsDash['status'] === 403
        && $pmToOpsDash['status'] === 403
        && $afinToOpsDash['status'] === 403
        && $mktToOpfinDash['status'] === 403
        && $pmToOpfinDash['status'] === 403
        && $opsToOpfinDash['status'] === 403,
        'Unauthorized departments are strictly blocked from Operations and OPS Finance module routes (HTTP 403)'
    );

    // 13.2 Operations Dashboard & Projects queue
    $opsDash = $opsClient->request('GET', '/operations/dashboard');
    $opsCsrf = $opsClient->extractCsrfToken($opsDash['body']);
    $opsProjectsPage = $opsClient->request('GET', '/operations/projects');
    assertCheck(
        $opsDash['status'] === 200
        && str_contains($opsDash['body'], 'Projects Received from Admin Finance')
        && str_contains($opsDash['body'], 'Projects Awaiting PR Preparation')
        && str_contains($opsDash['body'], 'PRs Being Prepared')
        && str_contains($opsDash['body'], 'PRs Submitted to OPS Finance')
        && str_contains($opsDash['body'], (string) $projectWithJom['project_code'])
        && str_contains($opsDash['body'], $issuedJomNumber)
        && $opsProjectsPage['status'] === 200
        && str_contains($opsProjectsPage['body'], (string) $projectWithJom['project_code']),
        'Operations Dashboard and Projects list load with HTTP 200, display all 4 metrics, and show the forwarded project with JOM, SLD, and Marketing BOM'
    );

    // Operations Department Redesigned Sidebar Navigation Verification
    assertCheck(
        str_contains($opsDash['body'], 'data-nav-group="ops-pm"')
        && str_contains($opsDash['body'], 'data-nav-group="ops-pr"')
        && str_contains($opsDash['body'], 'data-nav-group="ops-purchasing"')
        && str_contains($opsDash['body'], 'Operations Dashboard')
        && str_contains($opsDash['body'], 'Project Management')
        && str_contains($opsDash['body'], 'Received Projects')
        && str_contains($opsDash['body'], 'All Operations Projects')
        && str_contains($opsDash['body'], 'Purchase Request Management')
        && str_contains($opsDash['body'], 'Draft Purchase Requests')
        && str_contains($opsDash['body'], 'Submitted / Pending PRs')
        && str_contains($opsDash['body'], 'Approved PRs')
        && str_contains($opsDash['body'], 'Returned PRs')
        && str_contains($opsDash['body'], 'Rejected PRs')
        && str_contains($opsDash['body'], 'Purchasing Tracking')
        && str_contains($opsDash['body'], 'Purchase Order Tracking')
        && str_contains($opsDash['body'], 'Awaiting Purchasing'),
        'Operations Department sidebar renders organized dropdown navigation (Project Management, PR Management, Purchasing Tracking)'
    );

    // Verify auto-expansion on filter navigation
    $opsReceivedPage = $opsClient->request('GET', '/operations/projects?status=awaiting_pr');
    assertCheck(
        $opsReceivedPage['status'] === 200
        && str_contains($opsReceivedPage['body'], 'data-nav-group="ops-pm"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="ops-pm"/s', $opsReceivedPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Received Projects/s', $opsReceivedPage['body']) === 1,
        'Received Projects filter returns HTTP 200 and auto-expands Project Management dropdown'
    );

    $opsPrDraftPage = $opsClient->request('GET', '/operations/projects?status=draft');
    assertCheck(
        $opsPrDraftPage['status'] === 200
        && str_contains($opsPrDraftPage['body'], 'data-nav-group="ops-pr"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="ops-pr"/s', $opsPrDraftPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Draft Purchase Requests/s', $opsPrDraftPage['body']) === 1,
        'Draft Purchase Requests filter returns HTTP 200 and auto-expands Purchase Request Management dropdown'
    );

    $opsPrApprovedPage = $opsClient->request('GET', '/operations/projects?status=approved');
    assertCheck(
        $opsPrApprovedPage['status'] === 200
        && str_contains($opsPrApprovedPage['body'], 'data-nav-group="ops-pr"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="ops-pr"/s', $opsPrApprovedPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Approved PRs/s', $opsPrApprovedPage['body']) === 1,
        'Approved PRs filter returns HTTP 200 and auto-expands Purchase Request Management dropdown'
    );

    $opsPoTrackingPage = $opsClient->request('GET', '/operations/projects?status=converted_po');
    assertCheck(
        $opsPoTrackingPage['status'] === 200
        && str_contains($opsPoTrackingPage['body'], 'data-nav-group="ops-purchasing"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="ops-purchasing"/s', $opsPoTrackingPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Purchase Order Tracking/s', $opsPoTrackingPage['body']) === 1,
        'Purchase Order Tracking filter returns HTTP 200 and auto-expands Purchasing Tracking dropdown'
    );

    // 13.3 Prepare Purchase Request (PR) with multiple line items & validation
    $opsDetailBeforePr = $opsClient->request('GET', "/projects/{$projectId}");
    assertCheck(
        $opsDetailBeforePr['status'] === 200
        && str_contains($opsDetailBeforePr['body'], 'id="operations-pr-panel"'),
        'Operations user sees the Operations Purchase Request (PR) panel on project detail page'
    );

    // Validation: empty line item or missing specifications fails validation
    $badPrRes = $opsClient->request('POST', "/projects/{$projectId}/purchase-request", [
        '_csrf_token' => $opsCsrf,
        'submit_action' => 'draft',
        'pr_date' => date('Y-m-d'),
        'item_description' => [''],
        'item_specification' => [''],
        'quantity' => ['0'],
        'unit_of_measure' => ['pcs'],
    ]);
    assertCheck($badPrRes['status'] === 303, 'Invalid PR submission redirects with flash error');

    // Create Draft PR with multiple line items and attachment
    $prAttName = 'test_pr_att_' . bin2hex(random_bytes(3)) . '.pdf';
    $prAttContent = "%PDF-1.4\n%PR-ATTACHMENT-SPECIFICATION-LINE-ITEMS\n%%EOF";
    $prAttFile = [
        'pr_attachment' => [
            'filename' => $prAttName,
            'mime' => 'application/pdf',
            'content' => $prAttContent,
        ],
    ];
    $createdFilesOnDisk[] = dirname(__DIR__) . '/storage/documents/' . $prAttName;

    $createPrRes = $opsClient->request('POST', "/projects/{$projectId}/purchase-request", [
        '_csrf_token' => $opsCsrf,
        'submit_action' => 'draft',
        'pr_date' => date('Y-m-d'),
        'pr_remarks' => 'Draft Purchase Request for solar equipment and inverters based on SLD and Marketing BOM.',
        'item_description' => [
            'Solar Inverter 50kW',
            'Mounting Structure Aluminum Rails',
            'DC Solar Cable 6mm2 (Red/Black)',
        ],
        'item_specification' => [
            'Three Phase On-Grid Inverter, 400V, 50Hz, Dual MPPT with Wi-Fi Logger',
            'Anodized Aluminum 6005-T5, 4.2m lengths with stainless steel hardware',
            'Halogen-free, double insulated cross-linked polyolefin, UV resistant, 1000m drum',
        ],
        'quantity' => [
            '2.00',
            '48.00',
            '1.00',
        ],
        'unit_of_measure' => [
            'units',
            'pcs',
            'drum',
        ],
        'item_remarks' => [
            'Main inverters for arrays A and B',
            'Standard rail profiles',
            'Home run DC wiring',
        ],
    ], $prAttFile);

    $prRow = \App\Models\PurchaseRequest::findByProjectId($projectId);
    assertCheck(
        $createPrRes['status'] === 303
        && $prRow !== null
        && str_starts_with((string) $prRow['pr_number'], 'PR-')
        && $prRow['status'] === \App\Models\PurchaseRequest::STATUS_DRAFT
        && count($prRow['items']) === 3
        && !empty($prRow['attachment_document_id']),
        'Operations user creates Draft PR with 3 structured line items, unique PR number (PR-YYYYMM-XXXX), and PR attachment document'
    );

    // 13.4 Submit Purchase Request (PR) to OPS Finance
    // Unauthorized department (Marketing / PM) cannot submit PR
    $mktSubmitPr = $mktClient->request('POST', "/projects/{$projectId}/purchase-request/submit", ['_csrf_token' => $mktCsrf]);
    $pmSubmitPr = $pmClient->request('POST', "/projects/{$projectId}/purchase-request/submit", ['_csrf_token' => $pmCsrf]);
    assertCheck(
        $mktSubmitPr['status'] === 403 && $pmSubmitPr['status'] === 403,
        'Unauthorized departments (Marketing, PM) are blocked from submitting Purchase Requests (HTTP 403)'
    );

    // Operations user submits PR to OPS Finance
    $submitPrRes = $opsClient->request('POST', "/projects/{$projectId}/purchase-request/submit", [
        '_csrf_token' => $opsCsrf,
    ]);
    $prAfterSubmit = \App\Models\PurchaseRequest::findByProjectId($projectId);
    $projectAfterPrSubmit = Project::findById($projectId);
    assertCheck(
        $submitPrRes['status'] === 303
        && $prAfterSubmit['status'] === \App\Models\PurchaseRequest::STATUS_SUBMITTED_TO_OPS_FINANCE
        && !empty($prAfterSubmit['submitted_at'])
        && (int) $prAfterSubmit['submitted_by'] === (int) $opsUserRow['id']
        && (int) $prAfterSubmit['receiving_department_id'] === (int) $opfinDept['id']
        && $projectAfterPrSubmit['status'] === Project::STATUS_PR_SUBMITTED_TO_OPS_FINANCE
        && (int) $projectAfterPrSubmit['current_department_id'] === (int) $opfinDept['id'],
        'Operations submits PR to OPS Finance: status is Submitted to OPS Finance, routed to OPFIN, timestamp & submitter recorded, and project status updated'
    );

    // Verify Operations cannot edit submitted PR (locked after submission)
    $editLockedPrRes = $opsClient->request('POST', "/projects/{$projectId}/purchase-request", [
        '_csrf_token' => $opsCsrf,
        'submit_action' => 'draft',
        'pr_date' => date('Y-m-d'),
        'item_description' => ['Illegal Edit Inverter'],
        'item_specification' => ['Illegal Spec'],
        'quantity' => ['10'],
        'unit_of_measure' => ['pcs'],
    ]);
    assertCheck(
        $editLockedPrRes['status'] === 403,
        'Editing Purchase Request after submission is strictly blocked (HTTP 403)'
    );

    // Duplicate submission is prevented
    $dupSubmitPrRes = $opsClient->request('POST', "/projects/{$projectId}/purchase-request/submit", [
        '_csrf_token' => $opsCsrf,
    ]);
    assertCheck($dupSubmitPrRes['status'] === 403, 'Duplicate PR submission is prevented (HTTP 403)');

    // 13.5 OPS Finance Incoming Queue, PR Review & Controlled Return for Corrections
    $opfinDashAfterSubmit = $opfinClient->request('GET', '/ops-finance/dashboard');
    $opfinDetailView = $opfinClient->request('GET', "/projects/{$projectId}");
    $opfinDownloadPrAtt = $opfinClient->request('GET', "/projects/{$projectId}/documents/" . (int) $prAfterSubmit['attachment_document_id'] . '/download');
    assertCheck(
        $opfinDashAfterSubmit['status'] === 200
        && str_contains($opfinDashAfterSubmit['body'], (string) $prAfterSubmit['pr_number'])
        && str_contains($opfinDashAfterSubmit['body'], (string) $projectWithJom['project_code'])
        && $opfinDetailView['status'] === 200
        && str_contains($opfinDetailView['body'], 'Solar Inverter 50kW')
        && str_contains($opfinDetailView['body'], 'Mounting Structure Aluminum Rails')
        && $opfinDownloadPrAtt['status'] === 200,
        'OPS Finance receives PR in Incoming Queue, views all requested line items & specifications, and downloads PR attachment (HTTP 200)'
    );

    // OPS Finance Department Redesigned Sidebar Navigation Verification
    assertCheck(
        str_contains($opfinDashAfterSubmit['body'], 'data-nav-group="opfin-pr-inbox"')
        && str_contains($opfinDashAfterSubmit['body'], 'data-nav-group="opfin-actual-bom"')
        && str_contains($opfinDashAfterSubmit['body'], 'data-nav-group="opfin-bom-submission"')
        && str_contains($opfinDashAfterSubmit['body'], 'OPS Finance Dashboard')
        && str_contains($opfinDashAfterSubmit['body'], 'Purchase Request Inbox')
        && str_contains($opfinDashAfterSubmit['body'], 'Incoming Purchase Requests')
        && str_contains($opfinDashAfterSubmit['body'], 'All Received PRs')
        && str_contains($opfinDashAfterSubmit['body'], 'Pending PR Review')
        && str_contains($opfinDashAfterSubmit['body'], 'Returned PRs')
        && str_contains($opfinDashAfterSubmit['body'], 'Processed PRs')
        && str_contains($opfinDashAfterSubmit['body'], 'Actual BOM Management')
        && str_contains($opfinDashAfterSubmit['body'], 'Actual BOM Preparation')
        && str_contains($opfinDashAfterSubmit['body'], 'Draft Actual BOMs')
        && str_contains($opfinDashAfterSubmit['body'], 'Actual BOM Records')
        && str_contains($opfinDashAfterSubmit['body'], 'Actual BOM Submission')
        && str_contains($opfinDashAfterSubmit['body'], 'Pending Submission')
        && str_contains($opfinDashAfterSubmit['body'], 'Submitted Actual BOMs')
        && str_contains($opfinDashAfterSubmit['body'], 'Returned Actual BOMs'),
        'OPS Finance Department sidebar renders organized dropdown navigation (PR Inbox, Actual BOM Management, Actual BOM Submission)'
    );

    // Verify auto-expansion on filter navigation
    $opfinIncomingPrPage = $opfinClient->request('GET', '/ops-finance/projects?status=awaiting_review');
    assertCheck(
        $opfinIncomingPrPage['status'] === 200
        && str_contains($opfinIncomingPrPage['body'], 'data-nav-group="opfin-pr-inbox"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="opfin-pr-inbox"/s', $opfinIncomingPrPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Incoming Purchase Requests/s', $opfinIncomingPrPage['body']) === 1,
        'Incoming Purchase Requests filter returns HTTP 200 and auto-expands Purchase Request Inbox dropdown'
    );

    $opfinUnderReviewPage = $opfinClient->request('GET', '/ops-finance/projects?status=under_review');
    assertCheck(
        $opfinUnderReviewPage['status'] === 200
        && str_contains($opfinUnderReviewPage['body'], 'data-nav-group="opfin-pr-inbox"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="opfin-pr-inbox"/s', $opfinUnderReviewPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Pending PR Review/s', $opfinUnderReviewPage['body']) === 1,
        'Pending PR Review filter returns HTTP 200 and auto-expands Purchase Request Inbox dropdown'
    );

    $opfinBomPrepPage = $opfinClient->request('GET', '/ops-finance/projects?status=pending_actual_bom');
    assertCheck(
        $opfinBomPrepPage['status'] === 200
        && str_contains($opfinBomPrepPage['body'], 'data-nav-group="opfin-actual-bom"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="opfin-actual-bom"/s', $opfinBomPrepPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Actual BOM Preparation/s', $opfinBomPrepPage['body']) === 1,
        'Actual BOM Preparation filter returns HTTP 200 and auto-expands Actual BOM Management dropdown'
    );

    $opfinSubmittedAfinPage = $opfinClient->request('GET', '/ops-finance/projects?status=submitted_to_afin');
    assertCheck(
        $opfinSubmittedAfinPage['status'] === 200
        && str_contains($opfinSubmittedAfinPage['body'], 'data-nav-group="opfin-bom-submission"')
        && preg_match('/bms-nav-group\s+open.*data-nav-group="opfin-bom-submission"/s', $opfinSubmittedAfinPage['body']) === 1
        && preg_match('/bms-subnav-link\s+active.*Submitted Actual BOMs/s', $opfinSubmittedAfinPage['body']) === 1,
        'Submitted Actual BOMs filter returns HTTP 200 and auto-expands Actual BOM Submission dropdown'
    );

    // Controlled Return for Corrections test: OPS Finance returns PR to Operations
    $returnPrReason = 'Please specify copper connector sizes and separate DC cable colors into two distinct line items.';
    $returnPrRes = $opfinClient->request('POST', "/projects/{$projectId}/purchase-request/return", [
        '_csrf_token' => $opfinCsrf,
        'return_reason' => $returnPrReason,
    ]);
    $prAfterReturn = \App\Models\PurchaseRequest::findByProjectId($projectId);
    $projectAfterPrReturn = Project::findById($projectId);
    assertCheck(
        $returnPrRes['status'] === 303
        && $prAfterReturn['status'] === \App\Models\PurchaseRequest::STATUS_RETURNED_FOR_CORRECTIONS
        && $prAfterReturn['return_reason'] === $returnPrReason
        && (int) $prAfterReturn['returned_by'] === (int) $opfinUserRow['id']
        && !empty($prAfterReturn['returned_at'])
        && $projectAfterPrReturn['status'] === Project::STATUS_PR_BEING_PREPARED
        && (int) $projectAfterPrReturn['current_department_id'] === (int) $opsDept['id'],
        'OPS Finance returns PR to Operations for corrections: status is Returned for Corrections, return reason recorded, and routed back to Operations'
    );

    // Operations updates corrected PR and resubmits to OPS Finance
    $resubmitPrRes = $opsClient->request('POST', "/projects/{$projectId}/purchase-request", [
        '_csrf_token' => $opsCsrf,
        'submit_action' => 'submit',
        'pr_date' => date('Y-m-d'),
        'pr_remarks' => 'Resubmitted PR with separated cable colors per OPS Finance correction notes.',
        'item_description' => [
            'Solar Inverter 50kW',
            'Mounting Structure Aluminum Rails',
            'DC Solar Cable 6mm2 (Red)',
            'DC Solar Cable 6mm2 (Black)',
        ],
        'item_specification' => [
            'Three Phase On-Grid Inverter, 400V, 50Hz, Dual MPPT with Wi-Fi Logger',
            'Anodized Aluminum 6005-T5, 4.2m lengths with stainless steel hardware',
            'Halogen-free cross-linked polyolefin, UV resistant, Red, 500m spool',
            'Halogen-free cross-linked polyolefin, UV resistant, Black, 500m spool',
        ],
        'quantity' => [
            '2.00',
            '48.00',
            '1.00',
            '1.00',
        ],
        'unit_of_measure' => [
            'units',
            'pcs',
            'spool',
            'spool',
        ],
        'item_remarks' => [
            'Main inverters',
            'Hardware included',
            'Positive string wiring',
            'Negative string wiring',
        ],
    ]);
    $prResubmitted = \App\Models\PurchaseRequest::findByProjectId($projectId);
    assertCheck(
        $resubmitPrRes['status'] === 303
        && $prResubmitted['status'] === \App\Models\PurchaseRequest::STATUS_SUBMITTED_TO_OPS_FINANCE
        && count($prResubmitted['items']) === 4,
        'Operations updates line items (now 4 items) and resubmits PR directly to OPS Finance'
    );

    // OPS Finance marks PR Under Review
    $reviewPrRes = $opfinClient->request('POST', "/projects/{$projectId}/purchase-request/review", [
        '_csrf_token' => $opfinCsrf,
    ]);
    $prUnderReview = \App\Models\PurchaseRequest::findByProjectId($projectId);
    $projectActualBomPending = Project::findById($projectId);
    assertCheck(
        $reviewPrRes['status'] === 303
        && $prUnderReview['status'] === \App\Models\PurchaseRequest::STATUS_UNDER_OPS_FINANCE_REVIEW
        && (int) $prUnderReview['reviewed_by'] === (int) $opfinUserRow['id']
        && !empty($prUnderReview['reviewed_at'])
        && $projectActualBomPending['status'] === Project::STATUS_ACTUAL_BOM_PENDING_UPLOAD,
        'OPS Finance marks PR Under Review: project status transitions to Actual BOM Pending Upload'
    );

    // 13.6 OPS Finance Prepares and Uploads Actual BOM with Detailed Specifications
    // Unauthorized departments (Marketing, PM, Operations, Admin Finance) cannot upload Actual BOM
    $mktUploadAb = $mktClient->request('POST', "/projects/{$projectId}/actual-bom", ['_csrf_token' => $mktCsrf]);
    $pmUploadAb = $pmClient->request('POST', "/projects/{$projectId}/actual-bom", ['_csrf_token' => $pmCsrf]);
    $opsUploadAb = $opsClient->request('POST', "/projects/{$projectId}/actual-bom", ['_csrf_token' => $opsCsrf]);
    $afinUploadAb = $afinClient->request('POST', "/projects/{$projectId}/actual-bom", ['_csrf_token' => $afinCsrf]);
    assertCheck(
        $mktUploadAb['status'] === 403
        && $pmUploadAb['status'] === 403
        && $opsUploadAb['status'] === 403
        && $afinUploadAb['status'] === 403,
        'Marketing, PM, Operations, and Admin Finance are strictly blocked from uploading Actual BOM (HTTP 403)'
    );

    // OPS Finance uploads Actual BOM document (v1) with actual equipment/material specifications
    $abDocName = 'test_actual_bom_v1_' . bin2hex(random_bytes(3)) . '.pdf';
    $abDocContent = "%PDF-1.4\n% ACTUAL BOM V1 SPECIFICATIONS\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
    $abFiles = [
        'actual_bom_file' => [
            'filename' => $abDocName,
            'mime' => 'application/pdf',
            'content' => $abDocContent,
        ],
    ];
    $createdFilesOnDisk[] = dirname(__DIR__) . '/storage/documents/' . $abDocName;

    // Snapshot Marketing BOM before Actual BOM upload to verify it is NEVER overwritten
    $mktBomBeforeAb = ProjectDocument::findCurrentByType($projectId, 'BOM');

    $saveAbDraftRes = $opfinClient->request('POST', "/projects/{$projectId}/actual-bom", [
        '_csrf_token' => $opfinCsrf,
        'submit_action' => 'draft',
        'actual_bom_remarks' => 'OPS Finance verified equipment specifications and finalized pricing/models.',
        'ab_item_description' => [
            'Solar Inverter 50kW',
            'Mounting Structure Aluminum Rails',
            'DC Solar Cable 6mm2 (Red)',
            'DC Solar Cable 6mm2 (Black)',
        ],
        'ab_actual_specification' => [
            'Sungrow SG50CX-P2 Multi-MPPT String Inverter, 50kW, Max 1100Vdc, IP66 rated with smart forced air cooling',
            'Clenergy PV-ezRack SolarMatrix Pro Anodized Aluminum 6005-T5 with 304 Stainless Steel Allen bolts',
            'Top Cable TOPSOLAR H1Z2Z2-K EN50618 1x6mm2 Halogen-Free Flame Retardant Solar Cable (Red)',
            'Top Cable TOPSOLAR H1Z2Z2-K EN50618 1x6mm2 Halogen-Free Flame Retardant Solar Cable (Black)',
        ],
        'ab_quantity' => [
            '2.00',
            '48.00',
            '1.00',
            '1.00',
        ],
        'ab_unit_of_measure' => [
            'units',
            'pcs',
            'spool',
            'spool',
        ],
        'ab_item_remarks' => [
            'Model confirmed with engineering',
            'High wind load rated',
            'Certified 1500V DC',
            'Certified 1500V DC',
        ],
    ], $abFiles);

    $actualBomRow = \App\Models\ActualBom::findByProjectId($projectId);
    $mktBomAfterAb = ProjectDocument::findCurrentByType($projectId, 'BOM');
    assertCheck(
        $saveAbDraftRes['status'] === 303
        && $actualBomRow !== null
        && str_starts_with((string) $actualBomRow['actual_bom_number'], 'ABOM-')
        && (int) $actualBomRow['version_number'] === 1
        && $actualBomRow['status'] === \App\Models\ActualBom::STATUS_DRAFT
        && count($actualBomRow['items']) === 4
        && !empty($actualBomRow['document_id'])
        && (int) $mktBomAfterAb['id'] === (int) $mktBomBeforeAb['id']
        && (int) $mktBomAfterAb['version_number'] === (int) $mktBomBeforeAb['version_number']
        && (int) $mktBomAfterAb['is_current'] === 1,
        'OPS Finance creates Draft Actual BOM with unique number (ABOM-YYYYMM-XXXX), 4 detailed actual specifications, and Marketing BOM is PRESERVED completely untouched'
    );

    // 13.7 OPS Finance Submits Actual BOM to Admin Finance
    $mktSubmitAb = $mktClient->request('POST', "/projects/{$projectId}/actual-bom/submit", ['_csrf_token' => $mktCsrf]);
    $opsSubmitAb = $opsClient->request('POST', "/projects/{$projectId}/actual-bom/submit", ['_csrf_token' => $opsCsrf]);
    assertCheck(
        $mktSubmitAb['status'] === 403 && $opsSubmitAb['status'] === 403,
        'Marketing and Operations cannot submit Actual BOM to Admin Finance (HTTP 403)'
    );

    $submitAbRes = $opfinClient->request('POST', "/projects/{$projectId}/actual-bom/submit", [
        '_csrf_token' => $opfinCsrf,
    ]);
    $abAfterSubmit = \App\Models\ActualBom::findByProjectId($projectId);
    $projectAfterAbSubmit = Project::findById($projectId);
    assertCheck(
        $submitAbRes['status'] === 303
        && $abAfterSubmit['status'] === \App\Models\ActualBom::STATUS_SUBMITTED_TO_ADMIN_FINANCE
        && !empty($abAfterSubmit['submitted_at'])
        && (int) $abAfterSubmit['submitted_by'] === (int) $opfinUserRow['id']
        && $projectAfterAbSubmit['status'] === Project::STATUS_ACTUAL_BOM_SUBMITTED_TO_AFIN
        && (int) $projectAfterAbSubmit['current_department_id'] === (int) $afinDept['id'],
        'OPS Finance submits Actual BOM: status is Submitted to Admin Finance, routed to ADMFIN, and submitter & timestamp recorded'
    );

    // Duplicate submission of Actual BOM is prevented
    $dupSubmitAbRes = $opfinClient->request('POST', "/projects/{$projectId}/actual-bom/submit", [
        '_csrf_token' => $opfinCsrf,
    ]);
    assertCheck($dupSubmitAbRes['status'] === 403, 'Duplicate Actual BOM submission is prevented (HTTP 403)');

    // 13.8 Admin Finance Review Lifecycle: Received -> Under Review -> Return for Corrections -> Resubmit v2 -> Accept
    // Unauthorized departments (MKT, PM, OPS, OPFIN) cannot review or accept Actual BOM
    $opfinReviewAbAttempt = $opfinClient->request('POST', "/projects/{$projectId}/actual-bom/review", [
        '_csrf_token' => $opfinCsrf,
        'decision' => 'accept',
    ]);
    assertCheck($opfinReviewAbAttempt['status'] === 403, 'OPS Finance cannot approve their own Actual BOM (HTTP 403)');

    // Admin Finance sees incoming Actual BOM in Incoming Queue on Dashboard
    $afinDashWithAb = $afinClient->request('GET', '/admin-finance/dashboard');
    assertCheck(
        $afinDashWithAb['status'] === 200
        && str_contains($afinDashWithAb['body'], 'id="admin-finance-actual-bom-queue"')
        && str_contains($afinDashWithAb['body'], (string) $abAfterSubmit['actual_bom_number'])
        && str_contains($afinDashWithAb['body'], (string) $projectWithJom['project_code']),
        'Admin Finance dashboard displays incoming Actual BOM queue with PR and JOM reference'
    );

    // Step A: Admin Finance marks Actual BOM as Received
    $afinReceiveAbRes = $afinClient->request('POST', "/projects/{$projectId}/actual-bom/review", [
        '_csrf_token' => $afinCsrf,
        'decision' => 'received',
    ]);
    $abReceived = \App\Models\ActualBom::findByProjectId($projectId);
    $projReceived = Project::findById($projectId);
    assertCheck(
        $afinReceiveAbRes['status'] === 303
        && $abReceived['status'] === \App\Models\ActualBom::STATUS_RECEIVED
        && (int) $abReceived['received_by'] === (int) $afinUserRow['id']
        && !empty($abReceived['received_at'])
        && $projReceived['status'] === Project::STATUS_ACTUAL_BOM_RECEIVED,
        'Admin Finance marks Actual BOM as Received: status is Received, receiver & timestamp recorded'
    );

    // Step B: Admin Finance marks Actual BOM as Under Review
    $afinReviewAbRes = $afinClient->request('POST', "/projects/{$projectId}/actual-bom/review", [
        '_csrf_token' => $afinCsrf,
        'decision' => 'under_review',
    ]);
    $abUnderReview = \App\Models\ActualBom::findByProjectId($projectId);
    $projUnderReview = Project::findById($projectId);
    assertCheck(
        $afinReviewAbRes['status'] === 303
        && $abUnderReview['status'] === \App\Models\ActualBom::STATUS_UNDER_REVIEW
        && (int) $abUnderReview['reviewed_by'] === (int) $afinUserRow['id']
        && !empty($abUnderReview['reviewed_at'])
        && $projUnderReview['status'] === Project::STATUS_ACTUAL_BOM_UNDER_REVIEW,
        'Admin Finance marks Actual BOM as Under Review: reviewer & timestamp recorded'
    );

    // Step C: Admin Finance returns Actual BOM for corrections with notes
    $returnAbReason = 'Please attach the manufacturer warranty certificate and update rail lengths to 4.4m.';
    $afinReturnAbRes = $afinClient->request('POST', "/projects/{$projectId}/actual-bom/review", [
        '_csrf_token' => $afinCsrf,
        'decision' => 'return',
        'return_reason' => $returnAbReason,
    ]);
    $abReturned = \App\Models\ActualBom::findByProjectId($projectId);
    $projReturned = Project::findById($projectId);
    assertCheck(
        $afinReturnAbRes['status'] === 303
        && $abReturned['status'] === \App\Models\ActualBom::STATUS_RETURNED_FOR_CORRECTIONS
        && $abReturned['return_reason'] === $returnAbReason
        && (int) $abReturned['returned_by'] === (int) $afinUserRow['id']
        && !empty($abReturned['returned_at'])
        && $projReturned['status'] === Project::STATUS_ACTUAL_BOM_RETURNED
        && (int) $projReturned['current_department_id'] === (int) $opfinDept['id'],
        'Admin Finance returns Actual BOM for corrections: status is Returned for Corrections, reason recorded, routed back to OPS Finance'
    );

    // Step D: OPS Finance uploads updated Actual BOM (v2) with supporting document and resubmits to Admin Finance
    $abDocV2Name = 'test_actual_bom_v2_' . bin2hex(random_bytes(3)) . '.pdf';
    $abDocV2Content = "%PDF-1.4\n% ACTUAL BOM V2 SPECIFICATIONS REVISED\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
    $suppDocName = 'test_warranty_cert_' . bin2hex(random_bytes(3)) . '.pdf';
    $suppDocContent = "%PDF-1.4\n%MANUFACTURER-WARRANTY-CERTIFICATE\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
    $abV2Files = [
        'actual_bom_file' => [
            'filename' => $abDocV2Name,
            'mime' => 'application/pdf',
            'content' => $abDocV2Content,
        ],
        'supporting_file' => [
            'filename' => $suppDocName,
            'mime' => 'application/pdf',
            'content' => $suppDocContent,
        ],
    ];
    $createdFilesOnDisk[] = dirname(__DIR__) . '/storage/documents/' . $abDocV2Name;
    $createdFilesOnDisk[] = dirname(__DIR__) . '/storage/documents/' . $suppDocName;

    $resubmitAbRes = $opfinClient->request('POST', "/projects/{$projectId}/actual-bom", [
        '_csrf_token' => $opfinCsrf,
        'submit_action' => 'submit',
        'actual_bom_remarks' => 'Resubmitted Actual BOM v2 with updated 4.4m rails and attached manufacturer warranty certificate.',
        'ab_item_description' => [
            'Solar Inverter 50kW',
            'Mounting Structure Aluminum Rails',
            'DC Solar Cable 6mm2 (Red)',
            'DC Solar Cable 6mm2 (Black)',
        ],
        'ab_actual_specification' => [
            'Sungrow SG50CX-P2 Multi-MPPT String Inverter, 50kW, Max 1100Vdc, IP66 rated with smart forced air cooling',
            'Clenergy PV-ezRack SolarMatrix Pro Anodized Aluminum 6005-T5 (4.4m length) with 304 Stainless Steel Allen bolts',
            'Top Cable TOPSOLAR H1Z2Z2-K EN50618 1x6mm2 Halogen-Free Flame Retardant Solar Cable (Red)',
            'Top Cable TOPSOLAR H1Z2Z2-K EN50618 1x6mm2 Halogen-Free Flame Retardant Solar Cable (Black)',
        ],
        'ab_quantity' => [
            '2.00',
            '48.00',
            '1.00',
            '1.00',
        ],
        'ab_unit_of_measure' => [
            'units',
            'pcs',
            'spool',
            'spool',
        ],
        'ab_item_remarks' => [
            'Warranty certificate attached',
            'Updated to 4.4m per AFIN notes',
            'Certified 1500V DC',
            'Certified 1500V DC',
        ],
    ], $abV2Files);

    $abV2Row = \App\Models\ActualBom::findByProjectId($projectId);
    $abHistoryDocs = \App\Models\ActualBom::documentHistoryForProject($projectId);
    $mktBomCheckFinal = ProjectDocument::findCurrentByType($projectId, 'BOM');
    assertCheck(
        $resubmitAbRes['status'] === 303
        && $abV2Row !== null
        && (int) $abV2Row['version_number'] === 2
        && $abV2Row['status'] === \App\Models\ActualBom::STATUS_SUBMITTED_TO_ADMIN_FINANCE
        && !empty($abV2Row['supporting_document_id'])
        && count($abHistoryDocs) === 2
        && (int) $mktBomCheckFinal['id'] === (int) $mktBomBeforeAb['id'],
        'OPS Finance resubmits Actual BOM v2: both v1 and v2 are preserved in document history, supporting file attached, and Marketing BOM remains untouched'
    );

    // Step E: Admin Finance reviews and Accepts Actual BOM v2 with specifications
    $acceptNotes = 'Admin Finance verified all equipment specifications, 4.4m rails, and manufacturer warranty. Actual BOM accepted.';
    $afinAcceptRes = $afinClient->request('POST', "/projects/{$projectId}/actual-bom/review", [
        '_csrf_token' => $afinCsrf,
        'decision' => 'accept',
        'acceptance_notes' => $acceptNotes,
    ]);
    $abAccepted = \App\Models\ActualBom::findByProjectId($projectId);
    $projAccepted = Project::findById($projectId);
    assertCheck(
        $afinAcceptRes['status'] === 303
        && $abAccepted['status'] === \App\Models\ActualBom::STATUS_ACCEPTED
        && (int) $abAccepted['accepted_by'] === (int) $afinUserRow['id']
        && !empty($abAccepted['accepted_at'])
        && $abAccepted['acceptance_notes'] === $acceptNotes
        && $projAccepted['status'] === Project::STATUS_ACTUAL_BOM_ACCEPTED,
        'Admin Finance accepts Actual BOM: status is Accepted, project status is Actual BOM Accepted by Admin Finance, and accepter details & notes recorded'
    );

    // 14. Phase 2 Operations Purchase Request (PR) Full Lifecycle, Approval, Rejection & PO Eligibility
    echo "\n14. Phase 2 Operations Purchase Request (PR) Full Lifecycle, Approval, Rejection & PO Eligibility\n";

    // 14.1 Auto-retrieval verification & non-overwriting of separate Marketing BOM
    $prOnMainProject = \App\Models\PurchaseRequest::findByProjectId($projectId);
    $mktBomDoc = ProjectDocument::findCurrentByType($projectId, 'BOM');
    $actualBomRecord = \App\Models\ActualBom::findByProjectId($projectId);
    assertCheck(
        $prOnMainProject !== null
        && (int) $prOnMainProject['sld_document_id'] > 0
        && (int) $prOnMainProject['marketing_bom_document_id'] > 0
        && (int) $prOnMainProject['marketing_bom_document_id'] === (int) $mktBomDoc['id']
        && (int) $prOnMainProject['actual_bom_id'] === (int) $actualBomRecord['id']
        && !empty($prOnMainProject['requested_by'])
        && !empty($prOnMainProject['pr_date']),
        'PR automatically retrieves and links JOM, SLD, Marketing BOM, and Actual BOM while keeping BOMs as separate references'
    );

    // 14.2 Operations requester strictly blocked from approving or rejecting their own PR (HTTP 403)
    $opsApproveAttempt = $opsClient->request('POST', "/projects/{$projectId}/purchase-request/approve", [
        '_csrf_token' => $opsCsrf,
        'approval_notes' => 'Operations user unauthorized self-approval attempt.',
    ]);
    $opsRejectAttempt = $opsClient->request('POST', "/projects/{$projectId}/purchase-request/reject", [
        '_csrf_token' => $opsCsrf,
        'rejection_reason' => 'Operations user unauthorized self-rejection attempt.',
    ]);
    $mktApproveAttempt = $mktClient->request('POST', "/projects/{$projectId}/purchase-request/approve", [
        '_csrf_token' => $mktCsrf,
        'approval_notes' => 'Marketing unauthorized approval attempt.',
    ]);
    assertCheck(
        $opsApproveAttempt['status'] === 403
        && $opsRejectAttempt['status'] === 403
        && $mktApproveAttempt['status'] === 403,
        'Operations requester and unauthorized departments are strictly blocked from approving or rejecting PRs (HTTP 403)'
    );

    // 14.3 Authorized Approver (OPS Finance / Admin Finance) formally approves PR
    $prApproveNotes = 'PR line items and technical specifications formally approved against finalized Actual BOM.';
    $approvePrRes = $opfinClient->request('POST', "/projects/{$projectId}/purchase-request/approve", [
        '_csrf_token' => $opfinCsrf,
        'approval_notes' => $prApproveNotes,
    ]);
    $prApproved = \App\Models\PurchaseRequest::findByProjectId($projectId);
    $approvedItems = $prApproved['items'] ?? [];
    $allApprovedQuantitiesMatch = !empty($approvedItems);
    foreach ($approvedItems as $apprItem) {
        if ((float) $apprItem['approved_quantity'] !== (float) $apprItem['quantity']) {
            $allApprovedQuantitiesMatch = false;
        }
    }
    assertCheck(
        $approvePrRes['status'] === 303
        && $prApproved['status'] === \App\Models\PurchaseRequest::STATUS_APPROVED
        && (int) $prApproved['approved_by'] === (int) $opfinUserRow['id']
        && !empty($prApproved['approved_at'])
        && $prApproved['approval_notes'] === $prApproveNotes
        && $allApprovedQuantitiesMatch,
        'Authorized approver formally approves PR: status is Approved, approver and timestamp saved, and approved_quantity locked'
    );

    // 14.4 PR History audit trail (purchase_request_histories)
    $prHistories = \App\Models\PurchaseRequest::historiesForPr((int) $prApproved['id']);
    $prHistoryActions = array_column($prHistories, 'action');
    assertCheck(
        !empty($prHistories)
        && in_array('approved', $prHistoryActions, true),
        'Purchase Request history table (purchase_request_histories) logs approval event with timestamp, approver, and notes'
    );

    // 14.5 PO Eligibility and Order Quantity Tracking (Partial & Full Order)
    assertCheck(
        \App\Models\PurchaseRequest::isEligibleForPo($prApproved),
        'Approved Purchase Request is eligible for Purchase Order (PO) creation'
    );

    $testItem = $approvedItems[0];
    $apprQty = (float) $testItem['approved_quantity'];

    // Over-ordering exceeds approved quantity -> rejected with error
    $overOrderRes = \App\Models\PurchaseRequest::recordOrderQuantity((int) $prApproved['id'], (int) $testItem['id'], $apprQty + 5.0, (int) $opfinUserRow['id']);
    assertCheck(
        $overOrderRes['success'] === false && str_contains($overOrderRes['error'] ?? '', 'exceeds'),
        'Ordering quantity cannot exceed approved quantity unless an authorized amendment is recorded'
    );

    // Partial order -> status transitions to Partially Ordered
    $partialQty = round($apprQty / 2, 2);
    $partialRes = \App\Models\PurchaseRequest::recordOrderQuantity((int) $prApproved['id'], (int) $testItem['id'], $partialQty, (int) $opfinUserRow['id']);
    $prAfterPartial = \App\Models\PurchaseRequest::findById((int) $prApproved['id']);
    assertCheck(
        $partialRes['success'] === true
        && $prAfterPartial !== null
        && $prAfterPartial['status'] === \App\Models\PurchaseRequest::STATUS_PARTIALLY_ORDERED
        && (float) $prAfterPartial['ordered_quantity_total'] === $partialQty,
        'Partial order updates item ordered_quantity and transitions PR status to Partially Ordered'
    );

    // Complete order -> status transitions to Converted to PO / Fully Ordered
    $remainderQty = $apprQty - $partialQty;
    if ($remainderQty > 0) {
        \App\Models\PurchaseRequest::recordOrderQuantity((int) $prApproved['id'], (int) $testItem['id'], $remainderQty, (int) $opfinUserRow['id']);
    }
    for ($i = 1; $i < count($approvedItems); $i++) {
        $otherItm = $approvedItems[$i];
        \App\Models\PurchaseRequest::recordOrderQuantity((int) $prApproved['id'], (int) $otherItm['id'], (float) $otherItm['approved_quantity'], (int) $opfinUserRow['id']);
    }
    $prFullyOrdered = \App\Models\PurchaseRequest::findById((int) $prApproved['id']);
    assertCheck(
        $prFullyOrdered !== null
        && in_array($prFullyOrdered['status'], [\App\Models\PurchaseRequest::STATUS_CONVERTED_TO_PO, \App\Models\PurchaseRequest::STATUS_FULLY_ORDERED], true),
        'Full ordering of all approved line items transitions PR status to Converted to PO / Fully Ordered'
    );

    // 14.6 PR Creation with Model/Part No, Technical Specs, Purpose & Rejection Lifecycle
    $project3Id = Project::create([
        'project_code' => 'PRJ-' . date('Ymd') . '-P3' . substr(str_shuffle('0123456789'), 0, 3),
        'project_name' => 'Solar Hybrid Commercial System ' . $suffix,
        'client_name' => 'Metro Logistics Warehouse',
        'client_contact' => 'operations@metrologistics.ph',
        'project_location' => 'Laguna Technopark',
        'project_description' => 'Dedicated project for PR rejection and multi-field verification',
        'origin_department_id' => $mktDept['id'],
        'current_department_id' => $opsDept['id'],
        'status' => Project::STATUS_FORWARDED_TO_OPERATIONS,
        'created_by' => (int) $mktUserRow['id'],
    ]);
    $jomP3 = 'JOM-' . date('Ymd') . '-P3' . substr(str_shuffle('0123456789'), 0, 3);
    $pdo->prepare('UPDATE projects SET jom_number = :jom WHERE id = :id')->execute([
        'jom' => $jomP3,
        'id' => $project3Id,
    ]);
    $createdProjectIds[] = $project3Id;

    $createPr3Res = $opsClient->request('POST', "/projects/{$project3Id}/purchase-request", [
        '_csrf_token' => $opsCsrf,
        'submit_action' => 'submit',
        'purpose' => 'Urgent replacement of damaged high-capacity inverter components.',
        'required_delivery_date' => date('Y-m-d', strtotime('+14 days')),
        'pr_remarks' => 'Procurement requested with vendor expedited delivery.',
        'item_description' => [
            'Lithium Iron Phosphate Battery Rack 100kWh',
            'Hybrid Power Inverter 100kW',
        ],
        'model_part_number' => [
            'LFP-100KWH-HV',
            'HYB-INV-100K',
        ],
        'item_specification' => [
            '16S LiFePO4 cells with integrated BMS and fire suppression unit',
            '400V 3-phase hybrid inverter with grid export limiting control',
        ],
        'quantity' => [
            '1.00',
            '1.00',
        ],
        'unit_of_measure' => [
            'rack',
            'unit',
        ],
        'item_purpose' => [
            'Peak shaving and emergency backup',
            'Grid-tied power conversion and battery charging',
        ],
        'item_remarks' => [
            'Requires factory calibration certificate',
            'IP65 rated for outdoor installation',
        ],
    ]);

    $pr3Submitted = \App\Models\PurchaseRequest::findByProjectId($project3Id);
    $pr3Items = $pr3Submitted['items'] ?? [];
    assertCheck(
        $createPr3Res['status'] === 303
        && $pr3Submitted !== null
        && $pr3Submitted['status'] === \App\Models\PurchaseRequest::STATUS_SUBMITTED_TO_OPS_FINANCE
        && $pr3Submitted['purpose'] === 'Urgent replacement of damaged high-capacity inverter components.'
        && count($pr3Items) === 2
        && $pr3Items[0]['model_part_number'] === 'LFP-100KWH-HV'
        && $pr3Items[0]['purpose'] === 'Peak shaving and emergency backup',
        'Operations submits PR with model/part numbers, technical specifications, item purposes, and required delivery date'
    );

    // Authorized approver rejects PR #3 with reason
    $pr3RejectReason = 'Duplicate equipment requisition: energy storage rack already allocated under master warehouse stock.';
    $rejectPr3Res = $opfinClient->request('POST', "/projects/{$project3Id}/purchase-request/reject", [
        '_csrf_token' => $opfinCsrf,
        'rejection_reason' => $pr3RejectReason,
    ]);
    $pr3Rejected = \App\Models\PurchaseRequest::findByProjectId($project3Id);
    $pr3Histories = \App\Models\PurchaseRequest::historiesForPr((int) $pr3Rejected['id']);
    $pr3HistoryActions = array_column($pr3Histories, 'action');
    assertCheck(
        $rejectPr3Res['status'] === 303
        && $pr3Rejected['status'] === \App\Models\PurchaseRequest::STATUS_REJECTED
        && (int) $pr3Rejected['rejected_by'] === (int) $opfinUserRow['id']
        && !empty($pr3Rejected['rejected_at'])
        && $pr3Rejected['rejection_reason'] === $pr3RejectReason
        && in_array('rejected', $pr3HistoryActions, true)
        && !\App\Models\PurchaseRequest::isEligibleForPo($pr3Rejected),
        'Authorized approver formally rejects PR: status is Rejected, rejection reason saved, history logged, and PR ineligible for PO'
    );

    // 14.7 Operations Dashboard Phase 2 Metrics & Multi-filter Querying
    $opsDashPhase2 = $opsClient->request('GET', '/operations/dashboard');
    $opsFilterPrs = $opsClient->request('GET', '/operations/projects?status=Approved&date_from=' . date('Y-m-01') . '&date_to=' . date('Y-m-d'));
    assertCheck(
        $opsDashPhase2['status'] === 200
        && str_contains($opsDashPhase2['body'], 'Total PRs')
        && str_contains($opsDashPhase2['body'], 'Draft PRs')
        && str_contains($opsDashPhase2['body'], 'Approved PRs')
        && str_contains($opsDashPhase2['body'], 'PRs Converted to PO')
        && $opsFilterPrs['status'] === 200,
        'Operations Dashboard displays all Phase 2 metric cards and project list supports multi-criteria date and status filters'
    );

    // 15. Workflow History, Audit Trail & Session Logout Invalidation
    echo "\n15. Workflow History, Audit Trail & Session Logout Invalidation\n";
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
        'forwarded_to_admin_finance',
        'started_admin_finance_review',
        'issued_jom',
        'forwarded_to_operations',
        'created_purchase_request',
        'submitted_pr_to_ops_finance',
        'returned_pr_for_corrections',
        'ops_finance_reviewed_pr',
        'uploaded_actual_bom',
        'submitted_actual_bom_to_admin_finance',
        'admin_finance_received_actual_bom',
        'admin_finance_reviewing_actual_bom',
        'returned_actual_bom_for_corrections',
        'accepted_actual_bom',
    ];
    $missingHistory = array_diff($expectedHistoryActions, $historyActions);
    assertCheck(
        $missingHistory === [],
        'project_workflow_histories recorded complete end-to-end official workflow (Marketing -> PM Verification -> Admin Finance JOM Issuance -> Operations PR Preparation & Submission -> OPS Finance PR Review & Return/Resubmit -> OPS Finance Actual BOM Upload & Submission -> Admin Finance Actual BOM Receive/Review/Return/Accept)'
        . ($missingHistory !== [] ? ' (Missing: ' . implode(', ', $missingHistory) . ')' : '')
    );

    // 16. Phase 3: Purchase Order (PO), Payment Terms, Supplier Delivery Tracking & Stock In Integration
    echo "\n16. Phase 3: Purchase Order (PO), Payment Terms, Supplier Delivery Tracking & Stock In Integration\n";

    // 16.1 Setup Project #4 for End-to-End Phase 3 Verification
    $project4Code = 'PRJ-' . date('Ymd') . '-P4' . substr(str_shuffle('0123456789'), 0, 3);
    $project4Id = Project::create([
        'project_code' => $project4Code,
        'project_name' => 'Solar Commercial Rooftop Phase 3 ' . $suffix,
        'client_name' => 'Solaria Industrial Parks Inc.',
        'client_contact' => 'procurement@solariaparks.ph',
        'project_location' => 'Laguna Technopark Special Economic Zone',
        'project_description' => 'Dedicated project for PR-to-PO, Payment Terms, Supplier Delivery & Stock In verification',
        'origin_department_id' => (int) $mktDept['id'],
        'current_department_id' => (int) $opsDept['id'],
        'status' => Project::STATUS_FORWARDED_TO_OPERATIONS,
        'created_by' => (int) $mktUserRow['id'],
    ]);
    $createdProjectIds[] = $project4Id;

    $jomP4 = 'JOM-' . date('Ymd') . '-P4' . substr(str_shuffle('0123456789'), 0, 3);
    $pdo->prepare('UPDATE projects SET jom_number = :jom, jom_issued_by = :issuer, jom_issued_at = NOW() WHERE id = :id')
        ->execute([
            'jom' => $jomP4,
            'issuer' => (int) $afinUserRow['id'],
            'id' => $project4Id,
        ]);

    // Attach mock SLD and Marketing BOM to project #4
    $sldP4Name = 'test_sld_p4_' . bin2hex(random_bytes(3)) . '.pdf';
    $bomP4Name = 'test_bom_p4_' . bin2hex(random_bytes(3)) . '.pdf';
    $mockPdfContent = "%PDF-1.4\n% MOCK DOCUMENT\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
    file_put_contents(dirname(__DIR__) . '/storage/documents/' . $sldP4Name, $mockPdfContent);
    file_put_contents(dirname(__DIR__) . '/storage/documents/' . $bomP4Name, $mockPdfContent);
    $createdFilesOnDisk[] = dirname(__DIR__) . '/storage/documents/' . $sldP4Name;
    $createdFilesOnDisk[] = dirname(__DIR__) . '/storage/documents/' . $bomP4Name;

    $sldP4Id = ProjectDocument::create([
        'project_id' => $project4Id,
        'document_type' => 'SLD',
        'version_number' => 1,
        'original_filename' => $sldP4Name,
        'stored_filename' => $sldP4Name,
        'file_path' => 'storage/documents/' . $sldP4Name,
        'file_size' => strlen($mockPdfContent),
        'mime_type' => 'application/pdf',
        'uploaded_by' => (int) $mktUserRow['id'],
        'is_current' => 1,
    ]);
    $bomP4Id = ProjectDocument::create([
        'project_id' => $project4Id,
        'document_type' => 'BOM',
        'version_number' => 1,
        'original_filename' => $bomP4Name,
        'stored_filename' => $bomP4Name,
        'file_path' => 'storage/documents/' . $bomP4Name,
        'file_size' => strlen($mockPdfContent),
        'mime_type' => 'application/pdf',
        'uploaded_by' => (int) $mktUserRow['id'],
        'is_current' => 1,
    ]);

    // Operations submits PR for Project #4 with 2 equipment line items
    $createPr4Res = $opsClient->request('POST', "/projects/{$project4Id}/purchase-request", [
        '_csrf_token' => $opsCsrf,
        'submit_action' => 'submit',
        'purpose' => 'Procurement of primary string inverters and monocrystalline solar modules for Laguna jobsite.',
        'required_delivery_date' => date('Y-m-d', strtotime('+21 days')),
        'pr_remarks' => 'High-priority solar project PR.',
        'item_description' => [
            'Huawei SUN2000-100KTL-M2 High Power Inverter',
            'Canadian Solar 550W HiKu6 Mono PERC Solar Panels',
        ],
        'model_part_number' => [
            'SUN2000-100KTL-M2',
            'CS6W-550MS',
        ],
        'item_specification' => [
            '100kW, 10 MPPTs, 1100V DC Max, IP66 rated outdoor inverter with smart I-V curve diagnosis',
            '550W Monocrystalline PERC, 144 split-cell module, MC4-EVO2 compatible connectors',
        ],
        'quantity' => [
            '10.00',
            '100.00',
        ],
        'unit_of_measure' => [
            'units',
            'pcs',
        ],
        'item_purpose' => [
            'Three-phase utility-scale power conversion',
            'Primary PV array solar generation',
        ],
        'item_remarks' => [
            'Standard 5-year warranty required',
            'Linear performance warranty 25 years',
        ],
    ]);

    $pr4Submitted = PurchaseRequest::findByProjectId($project4Id);
    assertCheck(
        $createPr4Res['status'] === 303
        && $pr4Submitted !== null
        && $pr4Submitted['status'] === PurchaseRequest::STATUS_SUBMITTED_TO_OPS_FINANCE
        && count($pr4Submitted['items']) === 2,
        'Operations submits Purchase Request with 2 line items (Inverters & Panels) linked to JOM and separate documents'
    );

    // Authorized approver formally approves PR #4
    $approvePr4Res = $opfinClient->request('POST', "/projects/{$project4Id}/purchase-request/approve", [
        '_csrf_token' => $opfinCsrf,
        'approval_notes' => 'PR line items approved for procurement.',
    ]);
    $pr4Approved = PurchaseRequest::findByProjectId($project4Id);
    $pr4Items = $pr4Approved['items'] ?? [];
    $pr4Item1 = $pr4Items[0] ?? null; // Inverters (10.00 approved)
    $pr4Item2 = $pr4Items[1] ?? null; // Panels (100.00 approved)
    assertCheck(
        $approvePr4Res['status'] === 303
        && $pr4Approved['status'] === PurchaseRequest::STATUS_APPROVED
        && (float) ($pr4Item1['approved_quantity'] ?? 0) === 10.00
        && (float) ($pr4Item2['approved_quantity'] ?? 0) === 100.00,
        'Authorized approver approves PR #4: status is Approved and approved quantities are locked'
    );

    // 16.2 PO Authorization Hierarchy: Operations, Marketing, PM, and OPS Finance are strictly blocked from creating POs
    $opsPoCreateRes = $opsClient->request('POST', "/projects/{$project4Id}/purchase-orders", ['_csrf_token' => $opsCsrf]);
    $mktPoCreateRes = $mktClient->request('POST', "/projects/{$project4Id}/purchase-orders", ['_csrf_token' => $mktCsrf]);
    $pmPoCreateRes = $pmClient->request('POST', "/projects/{$project4Id}/purchase-orders", ['_csrf_token' => $pmCsrf]);
    $opfinPoCreateRes = $opfinClient->request('POST', "/projects/{$project4Id}/purchase-orders", ['_csrf_token' => $opfinCsrf]);
    assertCheck(
        $opsPoCreateRes['status'] === 403
        && $mktPoCreateRes['status'] === 403
        && $pmPoCreateRes['status'] === 403
        && $opfinPoCreateRes['status'] === 403,
        'Operations, Marketing, PM, and OPS Finance users are strictly blocked from creating Purchase Orders (HTTP 403)'
    );

    // 16.3 PR to PO Connection: Admin Finance creates PO #1 with auto-retrieved PR items & Milestone Terms of Payment
    $huaweiSupplier = Supplier::findByCode('SUP-HUAWEI');
    $canadianSupplier = Supplier::findByCode('SUP-CANADIAN');
    assertCheck(
        $huaweiSupplier !== null && $canadianSupplier !== null,
        'Active company suppliers (Huawei & Canadian Solar) are retrieved from suppliers catalog'
    );

    // PO #1 partial order: 6 out of 10 Inverters ordered from Huawei
    $createPo1Res = $afinClient->request('POST', "/projects/{$project4Id}/purchase-orders", [
        '_csrf_token' => $afinCsrf,
        'supplier_id' => (string) $huaweiSupplier['id'],
        'po_date' => date('Y-m-d'),
        'expected_delivery_date' => date('Y-m-d', strtotime('+14 days')),
        'delivery_terms' => 'CIF Jobsite - Laguna Technopark',
        'currency' => 'PHP',
        'po_remarks' => 'First partial PO for primary Huawei string inverters.',
        'pr_item_id' => [(string) $pr4Item1['id']],
        'item_description' => ['Huawei SUN2000-100KTL-M2 High Power Inverter'],
        'model_part_number' => ['SUN2000-100KTL-M2'],
        'item_specification' => ['100kW, 10 MPPTs, 1100V DC Max, IP66 rated outdoor inverter with smart I-V curve diagnosis'],
        'quantity_ordered' => ['6.00'],
        'unit_of_measure' => ['units'],
        'unit_price' => ['150000.00'],
        'payment_term' => ['Down Payment 30%', 'Delivery 60%', 'Retention 10%'],
        'term_percentage' => ['30.0', '60.0', '10.0'],
        'term_amount' => ['270000.00', '540000.00', '90000.00'],
        'term_due_date' => [date('Y-m-d', strtotime('+3 days')), date('Y-m-d', strtotime('+14 days')), date('Y-m-d', strtotime('+60 days'))],
        'term_remarks' => ['Upon PO confirmation', 'Upon physical delivery to site', 'Upon final system energization'],
    ]);

    $poListAfter1 = PurchaseOrder::forProject($project4Id);
    $po1 = $poListAfter1[0] ?? null;
    $pr4AfterPo1 = PurchaseRequest::findByProjectId($project4Id);
    $pr4Item1After = null;
    foreach ($pr4AfterPo1['items'] as $it) {
        if ((int) $it['id'] === (int) $pr4Item1['id']) {
            $pr4Item1After = $it;
            break;
        }
    }

    assertCheck(
        $createPo1Res['status'] === 303
        && $po1 !== null
        && str_starts_with((string) $po1['po_number'], 'PO-')
        && preg_match('/^PO-\d{6}-\d{4}$/', (string) $po1['po_number']) === 1
        && (float) $po1['total_amount'] === 900000.00
        && (int) $po1['supplier_id'] === (int) $huaweiSupplier['id']
        && $po1['status'] === PurchaseOrder::STATUS_DRAFT
        && $pr4Item1After !== null
        && (float) $pr4Item1After['ordered_quantity'] === 6.00
        && ((float) $pr4Item1After['approved_quantity'] - (float) $pr4Item1After['ordered_quantity']) === 4.00
        && $pr4AfterPo1['status'] === PurchaseRequest::STATUS_PARTIALLY_ORDERED,
        'Admin Finance creates PO #1 (PO-YYYYMM-XXXX preserving leading zeros), tracks partial ordered quantity (6/10), and PR status transitions to Partially Ordered'
    );

    // 16.4 Over-Ordering Validation: Attempt to order 5 units of Item 1 (exceeding remaining 4) is blocked
    $overOrderAttempt = $afinClient->request('POST', "/projects/{$project4Id}/purchase-orders", [
        '_csrf_token' => $afinCsrf,
        'supplier_id' => (string) $huaweiSupplier['id'],
        'po_date' => date('Y-m-d'),
        'delivery_terms' => 'FOB Warehouse',
        'pr_item_id' => [(string) $pr4Item1['id']],
        'item_description' => ['Huawei SUN2000-100KTL-M2 High Power Inverter'],
        'quantity_ordered' => ['5.00'],
        'unit_price' => ['150000.00'],
    ]);
    assertCheck(
        $overOrderAttempt['status'] === 303,
        'Ordering quantity exceeding approved PR quantity is rejected with validation error'
    );

    // 16.5 Multiple POs Linked to One PR: Create PO #2 for remaining 4 Inverters + all 100 Panels
    $createPo2Res = $afinClient->request('POST', "/projects/{$project4Id}/purchase-orders", [
        '_csrf_token' => $afinCsrf,
        'supplier_id' => (string) $canadianSupplier['id'],
        'po_date' => date('Y-m-d'),
        'expected_delivery_date' => date('Y-m-d', strtotime('+18 days')),
        'delivery_terms' => 'DDP Jobsite',
        'currency' => 'PHP',
        'po_remarks' => 'Second PO fulfilling remaining inverters and all monocrystalline modules.',
        'pr_item_id' => [(string) $pr4Item1['id'], (string) $pr4Item2['id']],
        'item_description' => [
            'Huawei SUN2000-100KTL-M2 High Power Inverter',
            'Canadian Solar 550W HiKu6 Mono PERC Solar Panels',
        ],
        'model_part_number' => [
            'SUN2000-100KTL-M2',
            'CS6W-550MS',
        ],
        'item_specification' => [
            '100kW, 10 MPPTs, 1100V DC Max',
            '550W Monocrystalline PERC, 144 split-cell module',
        ],
        'quantity_ordered' => ['4.00', '100.00'],
        'unit_of_measure' => ['units', 'pcs'],
        'unit_price' => ['150000.00', '8500.00'],
        'payment_term' => ['Full Payment on Delivery'],
        'term_percentage' => ['100.0'],
        'term_amount' => ['1450000.00'],
        'term_due_date' => [date('Y-m-d', strtotime('+20 days'))],
    ]);

    $poListAfter2 = PurchaseOrder::forProject($project4Id);
    $pr4AfterPo2 = PurchaseRequest::findByProjectId($project4Id);
    assertCheck(
        $createPo2Res['status'] === 303
        && count($poListAfter2) === 2
        && in_array($pr4AfterPo2['status'], [PurchaseRequest::STATUS_CONVERTED_TO_PO, PurchaseRequest::STATUS_FULLY_ORDERED], true),
        'Multiple POs linked to one PR: PO #2 created for remaining items, and PR transitions to Converted to PO / Fully Ordered'
    );

    // 16.6 PO Approval and Authorization Workflow: Operations & OPS Finance blocked from authorizing PO
    $po1Id = (int) $po1['id'];
    $opsPoAuthAttempt = $opsClient->request('POST', "/projects/{$project4Id}/purchase-orders/{$po1Id}/authorize", [
        '_csrf_token' => $opsCsrf,
        'authorization_notes' => 'Operations unauthorized authorization attempt.',
    ]);
    $opfinPoAuthAttempt = $opfinClient->request('POST', "/projects/{$project4Id}/purchase-orders/{$po1Id}/authorize", [
        '_csrf_token' => $opfinCsrf,
        'authorization_notes' => 'OPS Finance unauthorized authorization attempt.',
    ]);
    assertCheck(
        $opsPoAuthAttempt['status'] === 403 && $opfinPoAuthAttempt['status'] === 403,
        'Operations and OPS Finance users are strictly blocked from authorizing Purchase Orders (HTTP 403)'
    );

    // Admin Finance authorizes PO #1
    $afinPoAuthRes = $afinClient->request('POST', "/projects/{$project4Id}/purchase-orders/{$po1Id}/authorize", [
        '_csrf_token' => $afinCsrf,
        'authorization_notes' => 'Admin Finance financial authorization approved per capital allocation budget.',
    ]);
    $po1Authorized = PurchaseOrder::findById($po1Id);
    assertCheck(
        $afinPoAuthRes['status'] === 303
        && $po1Authorized['status'] === PurchaseOrder::STATUS_APPROVED
        && (int) $po1Authorized['authorized_by'] === (int) $afinUserRow['id']
        && !empty($po1Authorized['authorized_at']),
        'Admin Finance financially authorizes PO #1: status is Approved, authorizer and timestamp saved'
    );

    // Operations and OPS Finance blocked from issuing PO to supplier
    $opsPoIssueAttempt = $opsClient->request('POST', "/projects/{$project4Id}/purchase-orders/{$po1Id}/issue", [
        '_csrf_token' => $opsCsrf,
    ]);
    $opfinPoIssueAttempt = $opfinClient->request('POST', "/projects/{$project4Id}/purchase-orders/{$po1Id}/issue", [
        '_csrf_token' => $opfinCsrf,
    ]);
    assertCheck(
        $opsPoIssueAttempt['status'] === 403 && $opfinPoIssueAttempt['status'] === 403,
        'Operations and OPS Finance users are strictly blocked from issuing PO to supplier (HTTP 403)'
    );

    // Admin Finance issues PO #1 to supplier
    $afinPoIssueRes = $afinClient->request('POST', "/projects/{$project4Id}/purchase-orders/{$po1Id}/issue", [
        '_csrf_token' => $afinCsrf,
    ]);
    $po1Issued = PurchaseOrder::findById($po1Id);
    assertCheck(
        $afinPoIssueRes['status'] === 303
        && $po1Issued['status'] === PurchaseOrder::STATUS_ISSUED_TO_SUPPLIER,
        'Admin Finance issues PO #1 to supplier: status transitions to Issued to Supplier'
    );

    // 16.7 Terms of Payment Management: Admin Finance adds additional milestone payment term
    $addTermRes = $afinClient->request('POST', "/projects/{$project4Id}/purchase-orders/{$po1Id}/payment-terms", [
        '_csrf_token' => $afinCsrf,
        'payment_term' => 'Testing & Grid Synchronization Milestone',
        'term_percentage' => '0.0',
        'term_amount' => '50000.00',
        'term_due_date' => date('Y-m-d', strtotime('+45 days')),
        'term_remarks' => 'Payable upon utility distribution certificate issuance',
    ]);
    $po1Terms = PurchaseOrder::paymentTermsForPo($po1Id);
    $termNames = array_column($po1Terms, 'payment_term');
    assertCheck(
        $addTermRes['status'] === 303
        && in_array('Testing & Grid Synchronization Milestone', $termNames, true)
        && count($po1Terms) === 4,
        'Admin Finance adds milestone payment term to PO schedule, verified in payment_terms_schedules table'
    );

    // 16.8 Supplier Delivery Tracking: Log partial delivery with discrepancy / rejection
    $po1Items = PurchaseOrder::itemsForPo($po1Id);
    $po1Item1 = $po1Items[0];
    $dr1No = 'DR-' . date('Ymd') . '-HUA01';

    // Unauthorized user (Marketing) blocked from logging supplier delivery
    $mktDeliveryAttempt = $mktClient->request('POST', "/projects/{$project4Id}/purchase-orders/{$po1Id}/deliveries", [
        '_csrf_token' => $mktCsrf,
        'delivery_receipt_no' => $dr1No,
    ]);
    assertCheck($mktDeliveryAttempt['status'] === 403, 'Marketing user is strictly blocked from recording supplier deliveries (HTTP 403)');

    // Operations / Logistics records Delivery Receipt #1: 4 inverters delivered, 3 accepted, 1 rejected due to transit damage
    $logDelivery1Res = $opsClient->request('POST', "/projects/{$project4Id}/purchase-orders/{$po1Id}/deliveries", [
        '_csrf_token' => $opsCsrf,
        'delivery_receipt_no' => $dr1No,
        'delivery_date' => date('Y-m-d'),
        'carrier_name' => 'Lalamove Cargo / Truck Plate NCF-8821',
        'receiving_notes' => 'Initial delivery of 4 inverters. 1 unit rejected with cracked enclosure.',
        'po_item_id' => [(string) $po1Item1['id']],
        'quantity_delivered' => ['4.00'],
        'quantity_accepted' => ['3.00'],
        'quantity_rejected' => ['1.00'],
        'rejection_reason' => ['Broken front panel and terminal housing from transit impact. Returned to driver.'],
    ]);

    $del1 = SupplierDelivery::findByDrNumber($dr1No);
    $del1Items = $del1 !== null ? SupplierDelivery::itemsForDelivery((int) $del1['id']) : [];
    $trackingSummary1 = SupplierDelivery::deliveryTrackingSummaryForPo($po1Id);
    $track1 = $trackingSummary1['items'][0] ?? [];

    assertCheck(
        $logDelivery1Res['status'] === 303
        && $del1 !== null
        && (float) ($del1Items[0]['quantity_delivered'] ?? 0) === 4.00
        && (float) ($del1Items[0]['quantity_accepted'] ?? 0) === 3.00
        && (float) ($del1Items[0]['quantity_rejected'] ?? 0) === 1.00
        && (float) ($track1['ordered_quantity'] ?? 0) === 6.00
        && (float) ($track1['previously_received_quantity'] ?? 0) === 0.00
        && (float) ($track1['remaining_quantity'] ?? 0) === 6.00,
        'Supplier Delivery Tracking records DR #1 with partial delivery and rejected damaged goods; rejected item does NOT count towards accepted quantity'
    );

    // 16.9 Stock In Integration: Physical inspection, actual company form fields, serialized units & double-entry perpetual ledger
    $del1Id = (int) $del1['id'];
    $dr1Serials = [
        "SN-HUA-100K-{$suffix}-001",
        "SN-HUA-100K-{$suffix}-002",
        "SN-HUA-100K-{$suffix}-003",
    ];

    $stockIn1Res = $opsClient->request('POST', "/projects/{$project4Id}/deliveries/{$del1Id}/stock-in", [
        '_csrf_token' => $opsCsrf,
        'inventory_date' => date('Y-m-d'),
        'warehouse_location' => 'PCT Main Warehouse - Bay 4',
        'warranty_duration' => [
            (string) $po1Item1['id'] => '5 Years',
        ],
        'serial_numbers' => [
            (string) $po1Item1['id'] => implode("\n", $dr1Serials),
        ],
    ]);

    $invUnits1 = Inventory::unitsForProject($project4Id);
    $invTxns1 = Inventory::transactionsForProject($project4Id);
    $po1AfterStock1 = PurchaseOrder::findById($po1Id);
    $po1Item1AfterStock1 = PurchaseOrder::itemsForPo($po1Id)[0];

    assertCheck(
        $stockIn1Res['status'] === 303
        && count($invUnits1) === 3
        && count($invTxns1) >= 1
        && (float) $invTxns1[0]['quantity'] === 3.00
        && (float) $invTxns1[0]['balance_before'] === 0.00
        && (float) $invTxns1[0]['balance_after'] === 3.00
        && $invUnits1[0]['serial_number'] === $dr1Serials[0]
        && $invUnits1[0]['warranty_duration'] === '5 Years'
        && $invUnits1[0]['status'] === 'In Stock'
        && $invUnits1[0]['po_number'] === $po1['po_number']
        && (float) $po1Item1AfterStock1['quantity_received'] === 3.00
        && $po1AfterStock1['status'] === PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        'Stock In transaction increments double-entry perpetual ledger (balance: 3.00), registers serialized physical units with company fields, and updates PO status to Partially Received'
    );

    // 16.10 Duplicate Serial Number Rejection: Attempting Stock In with existing serial number fails
    $dupDrNo = 'DR-' . date('Ymd') . '-HUA-DUP';
    $opsClient->request('POST', "/projects/{$project4Id}/purchase-orders/{$po1Id}/deliveries", [
        '_csrf_token' => $opsCsrf,
        'delivery_receipt_no' => $dupDrNo,
        'delivery_date' => date('Y-m-d'),
        'po_item_id' => [(string) $po1Item1['id']],
        'quantity_delivered' => ['1.00'],
        'quantity_accepted' => ['1.00'],
        'quantity_rejected' => ['0.00'],
    ]);
    $dupDelivery = SupplierDelivery::findByDrNumber($dupDrNo);
    $dupStockInRes = $opsClient->request('POST', "/projects/{$project4Id}/deliveries/" . (int) $dupDelivery['id'] . "/stock-in", [
        '_csrf_token' => $opsCsrf,
        'inventory_date' => date('Y-m-d'),
        'serial_numbers' => [
            (string) $po1Item1['id'] => $dr1Serials[0], // Duplicate serial!
        ],
    ]);
    $invUnitsAfterDup = Inventory::unitsForProject($project4Id);
    assertCheck(
        $dupStockInRes['status'] === 303
        && count($invUnitsAfterDup) === 3,
        'Duplicate serial number registration is strictly blocked and rolled back without modifying inventory units or ledger'
    );

    // 16.11 Second Delivery Completion: Deliver remaining 3 inverters to complete PO #1
    $dr2No = 'DR-' . date('Ymd') . '-HUA02';
    $dr2Serials = [
        "SN-HUA-100K-{$suffix}-004",
        "SN-HUA-100K-{$suffix}-005",
        "SN-HUA-100K-{$suffix}-006",
    ];

    $opsClient->request('POST', "/projects/{$project4Id}/purchase-orders/{$po1Id}/deliveries", [
        '_csrf_token' => $opsCsrf,
        'delivery_receipt_no' => $dr2No,
        'delivery_date' => date('Y-m-d'),
        'carrier_name' => 'Lalamove Cargo / Truck Plate NCF-8821',
        'receiving_notes' => 'Second delivery of remaining 3 inverters. All units inspected and intact.',
        'po_item_id' => [(string) $po1Item1['id']],
        'quantity_delivered' => ['3.00'],
        'quantity_accepted' => ['3.00'],
        'quantity_rejected' => ['0.00'],
    ]);
    $del2 = SupplierDelivery::findByDrNumber($dr2No);

    $stockIn2Res = $opsClient->request('POST', "/projects/{$project4Id}/deliveries/" . (int) $del2['id'] . "/stock-in", [
        '_csrf_token' => $opsCsrf,
        'inventory_date' => date('Y-m-d'),
        'warehouse_location' => 'PCT Main Warehouse - Bay 4',
        'warranty_duration' => [
            (string) $po1Item1['id'] => '5 Years',
        ],
        'serial_numbers' => [
            (string) $po1Item1['id'] => implode("\n", $dr2Serials),
        ],
    ]);

    $po1FullyReceived = PurchaseOrder::findById($po1Id);
    $po1Item1Final = PurchaseOrder::itemsForPo($po1Id)[0];
    $totalUnitsProject4 = Inventory::unitsForProject($project4Id);

    assertCheck(
        $stockIn2Res['status'] === 303
        && (float) $po1Item1Final['quantity_received'] === 6.00
        && (float) $po1Item1Final['remaining_to_receive'] === 0.00
        && $po1FullyReceived['status'] === PurchaseOrder::STATUS_FULLY_RECEIVED
        && count($totalUnitsProject4) === 6,
        'Second delivery fulfillment completes PO #1: 6/6 units received, PO status transitions to Fully Received, and 6 serialized units in inventory'
    );

    // 16.12 End-to-End Linked Document View & Traceability
    $p4DetailView = $afinClient->request('GET', "/projects/{$project4Id}");
    assertCheck(
        $p4DetailView['status'] === 200
        && str_contains($p4DetailView['body'], 'id="linked-document-workflow-bar"')
        && str_contains($p4DetailView['body'], 'id="purchase-order-panel"')
        && str_contains($p4DetailView['body'], 'id="supplier-deliveries-panel"')
        && str_contains($p4DetailView['body'], 'id="inventory-stockin-panel"')
        && str_contains($p4DetailView['body'], (string) $po1['po_number'])
        && str_contains($p4DetailView['body'], $dr1No)
        && str_contains($p4DetailView['body'], $dr1Serials[0])
        && str_contains($p4DetailView['body'], 'Fully Received'),
        'Project detail page renders end-to-end linked traceability timeline (Project -> JOM -> PR -> PO -> Terms of Payment -> Supplier Delivery -> Stock In -> Serialized Inventory)'
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
        'project.forwarded_to_admin_finance',
        'project.received_by_admin_finance',
        'project.jom_issued',
        'project.forwarded_to_operations',
        'document.uploaded',
        'document.replaced',
        'document.viewed',
        'document.downloaded',
        'pr.created',
        'pr.updated',
        'pr.submitted',
        'pr.forwarded_to_ops_finance',
        'pr.reviewed_by_ops_finance',
        'pr.returned_for_corrections',
        'actual_bom.uploaded',
        'actual_bom.submitted',
        'actual_bom.received',
        'actual_bom.reviewed',
        'actual_bom.returned_for_corrections',
        'actual_bom.accepted',
        'po.created',
        'po.authorized',
        'po.issued_to_supplier',
        'po.payment_term_added',
        'supplier_delivery.logged',
        'inventory.stock_in',
        'security.csrf_mismatch',
        'security.unauthorized_access',
    ];
    $missingActions = array_diff($requiredActions, $loggedActions);
    assertCheck(
        $missingActions === [],
        'All required authentication, user, Marketing workflow, PM verification, Admin Finance JOM issuance, Operations handoff, and security events are logged in activity_logs'
        . ($missingActions !== [] ? ' (Missing: ' . implode(', ', $missingActions) . ')' : '')
    );
} catch (Throwable $e) {
    $failed++;
    echo "\n[FAIL] Unexpected Exception: " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    echo "\n==> Cleaning up temporary test records...\n";
    foreach (array_unique($createdFilesOnDisk) as $filePath) {
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    if ($createdProjectIds !== []) {
        $in = implode(',', array_map('intval', $createdProjectIds));
        $pdo->exec("DELETE FROM inventory_units WHERE project_id IN ({$in})");
        $pdo->exec("DELETE FROM inventory_transactions WHERE project_id IN ({$in})");
        $pdo->exec("DELETE FROM supplier_delivery_items WHERE delivery_id IN (SELECT id FROM supplier_deliveries WHERE project_id IN ({$in}))");
        $pdo->exec("DELETE FROM supplier_deliveries WHERE project_id IN ({$in})");
        $pdo->exec("DELETE FROM payment_terms_schedules WHERE purchase_order_id IN (SELECT id FROM purchase_orders WHERE project_id IN ({$in}))");
        $pdo->exec("DELETE FROM purchase_order_histories WHERE purchase_order_id IN (SELECT id FROM purchase_orders WHERE project_id IN ({$in}))");
        $pdo->exec("DELETE FROM purchase_order_items WHERE purchase_order_id IN (SELECT id FROM purchase_orders WHERE project_id IN ({$in}))");
        $pdo->exec("DELETE FROM purchase_orders WHERE project_id IN ({$in})");
        $pdo->exec("DELETE FROM actual_bom_items WHERE actual_bom_id IN (SELECT id FROM actual_boms WHERE project_id IN ({$in}))");
        $pdo->exec("DELETE FROM actual_boms WHERE project_id IN ({$in})");
        $pdo->exec("DELETE FROM purchase_request_histories WHERE purchase_request_id IN (SELECT id FROM purchase_requests WHERE project_id IN ({$in}))");
        $pdo->exec("DELETE FROM purchase_request_items WHERE purchase_request_id IN (SELECT id FROM purchase_requests WHERE project_id IN ({$in}))");
        $pdo->exec("DELETE FROM purchase_requests WHERE project_id IN ({$in})");
        $pdo->exec("DELETE FROM notifications WHERE project_id IN ({$in})");
        $pdo->exec("DELETE FROM project_workflow_histories WHERE project_id IN ({$in})");
        $pdo->exec("DELETE FROM project_documents WHERE project_id IN ({$in})");
        $pdo->exec("DELETE FROM projects WHERE id IN ({$in})");
        $pdo->exec("DELETE FROM activity_logs WHERE entity_type = 'project' AND entity_id IN ({$in})");
    }

    $pdo->exec("DELETE FROM inventory_items WHERE item_code LIKE 'ITEM-SUN2000%' OR item_code LIKE 'ITEM-CS6W%'");

    if ($createdUserIds !== []) {
        $in = implode(',', array_map('intval', $createdUserIds));
        $pdo->exec("DELETE FROM notifications WHERE recipient_user_id IN ({$in})");
        $pdo->exec("DELETE FROM activity_logs WHERE user_id IN ({$in}) OR (entity_type = 'user' AND entity_id IN ({$in}))");
        $pdo->exec("DELETE FROM users WHERE id IN ({$in})");
    }

    // Clear only test-generated activity logs created during this run
    $pdo->exec('DELETE FROM activity_logs WHERE id > ' . (int) $maxLogIdBeforeTest);

    if (is_array($adminSnapshot) && !empty($adminSnapshot['id'])) {
        $stmtRestoreAdmin = $pdo->prepare('UPDATE users SET password_hash = :hash, status = :status WHERE id = :id');
        $stmtRestoreAdmin->execute([
            'hash' => (string) $adminSnapshot['password_hash'],
            'status' => (string) $adminSnapshot['status'],
            'id' => (int) $adminSnapshot['id'],
        ]);
    }
}

echo "\n=======================================================================\n";
echo " TEST SUMMARY: {$passed} passed, {$failed} failed\n";
echo "=======================================================================\n";

exit($failed > 0 ? 1 : 0);
