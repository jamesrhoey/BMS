<?php

declare(strict_types=1);

/**
 * Migration 010 Preflight Verification Script
 * Target: MariaDB 10.4.32 on business_management_system
 * Validates environment, data types, signedness, index names, and conflict status.
 */

$host = '127.0.0.1';
$dbname = 'business_management_system';
$user = 'root';
$pass = '';

echo "======================================================================" . PHP_EOL;
echo "  BMS MIGRATION 010 PREFLIGHT VALIDATION (MariaDB 10.4.32)" . PHP_EOL;
echo "======================================================================" . PHP_EOL;

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "[CRITICAL FAIL] Database connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$allPassed = true;

function check(bool $condition, string $title, string $details = ''): void {
    global $allPassed;
    if ($condition) {
        echo "  [PASS] {$title}" . PHP_EOL;
    } else {
        $allPassed = false;
        echo "  [FAIL] {$title} -- {$details}" . PHP_EOL;
    }
}

// 1. Engine & Version Check
$version = (string) $pdo->query("SELECT VERSION()")->fetchColumn();
$comment = (string) $pdo->query("SELECT @@version_comment")->fetchColumn();
echo PHP_EOL . "1. Database Engine & Version:" . PHP_EOL;
echo "   Server Version: {$version} ({$comment})" . PHP_EOL;
check(str_contains(strtolower($version), 'mariadb') || str_contains(strtolower($comment), 'mariadb'), 
    "Database engine is MariaDB (Target: 10.4.32)", "Found: {$version}");

// 2. Existing Prerequisites Tables Check
echo PHP_EOL . "2. Prerequisite Tables Existence:" . PHP_EOL;
$prereqTables = [
    'users',
    'departments',
    'roles',
    'permissions',
    'role_permissions',
    'projects',
    'project_documents',
    'purchase_requests',
    'purchase_request_items',
    'purchase_orders',
    'purchase_order_items',
    'supplier_deliveries',
    'supplier_delivery_items',
    'inventory_items',
    'inventory_transactions',
    'inventory_units',
];

$existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($prereqTables as $tbl) {
    check(in_array($tbl, $existingTables, true), "Table `{$tbl}` exists");
}

// 3. Foreign Key Column Types & Signedness Validation
echo PHP_EOL . "3. Foreign Key Column Type & Signedness Parity:" . PHP_EOL;

function getColumnType(PDO $pdo, string $table, string $column): ?string {
    $stmt = $pdo->prepare("
        SELECT COLUMN_TYPE, IS_NULLABLE 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = :tbl 
          AND COLUMN_NAME = :col
    ");
    $stmt->execute(['tbl' => $table, 'col' => $column]);
    $row = $stmt->fetch();
    return $row ? (string) $row['COLUMN_TYPE'] : null;
}

$typeChecks = [
    ['inventory_transactions', 'id', 'bigint(20) unsigned', 'Parent PK for stock_out_transaction_id'],
    ['projects', 'id', 'int(10) unsigned', 'Parent PK for stock_out_issuances.project_id'],
    ['users', 'id', 'int(10) unsigned', 'Parent PK for stock_out_issuances personnel'],
    ['inventory_items', 'id', 'int(10) unsigned', 'Parent PK for stock_out_items.inventory_item_id'],
    ['project_documents', 'id', 'int(10) unsigned', 'Parent PK for supporting_document_id'],
    ['inventory_units', 'id', 'int(10) unsigned', 'Parent PK for dispatch unit allocations'],
];

foreach ($typeChecks as [$tbl, $col, $expected, $desc]) {
    $actual = getColumnType($pdo, $tbl, $col);
    check($actual === $expected, "{$tbl}.{$col} matches expected `{$expected}` ({$desc})", "Actual: {$actual}");
}

// 4. Check for Migration 010 Conflicts / Partially Applied Objects
echo PHP_EOL . "4. Migration 010 Target Objects Conflict Check:" . PHP_EOL;
$migTables = ['stock_out_issuances', 'stock_out_items', 'stock_out_dispatch_units'];
foreach ($migTables as $t) {
    $exists = in_array($t, $existingTables, true);
    if ($exists) {
        echo "  [INFO] Table `{$t}` already exists in database (will use IF NOT EXISTS)" . PHP_EOL;
    } else {
        echo "  [PASS] Table `{$t}` does not exist yet (clean slate for migration)" . PHP_EOL;
    }
}

// Check new columns on inventory_units
$iuNewCols = [
    'stock_out_issuance_id',
    'stock_out_item_id',
    'stock_out_transaction_id',
    'out_date',
    'out_qty',
    'warranty_expiration_date',
    'issued_to_company',
    'issued_to_address',
    'out_dr_number',
    'invoice_number'
];

foreach ($iuNewCols as $c) {
    $cType = getColumnType($pdo, 'inventory_units', $c);
    if ($cType !== null) {
        echo "  [INFO] `inventory_units`.`{$c}` already exists ({$cType})" . PHP_EOL;
    } else {
        echo "  [PASS] `inventory_units`.`{$c}` does not exist yet (clean to add)" . PHP_EOL;
    }
}

// 5. Existing Permissions & Roles Baseline Check
echo PHP_EOL . "5. RBAC & Roles Baseline Check:" . PHP_EOL;
$roles = $pdo->query("SELECT id, role_name, role_slug FROM roles")->fetchAll();
$roleSlugs = array_column($roles, 'role_slug');
check(in_array('administrator', $roleSlugs, true), "Role `administrator` exists");
check(in_array('department_user', $roleSlugs, true), "Role `department_user` exists");

$permCount = (int) $pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
echo "   Active permissions in system: {$permCount}" . PHP_EOL;
check($permCount >= 48, "Existing baseline permissions intact (expected >= 48, found {$permCount})");

// Check if any Migration 010 permission keys conflict
$newPermKeys = [
    'log.stock_out.view',
    'log.stock_out.create',
    'log.stock_out.authorize',
    'log.stock_out.manage',
    'log.stock_out.dispatch',
    'inv.reports.view',
    'inv.adjustments.manage'
];
$inClause = "'" . implode("','", $newPermKeys) . "'";
$existingNewPerms = $pdo->query("SELECT permission_key FROM permissions WHERE permission_key IN ({$inClause})")->fetchAll(PDO::FETCH_COLUMN);
if ($existingNewPerms) {
    echo "  [INFO] Some new permissions already registered: " . implode(', ', $existingNewPerms) . " (will use ON DUPLICATE KEY UPDATE)" . PHP_EOL;
} else {
    echo "  [PASS] All 7 new permission keys are completely novel and unmapped (clean insert)" . PHP_EOL;
}

echo PHP_EOL . "======================================================================" . PHP_EOL;
if ($allPassed) {
    echo "  PREFLIGHT RESULT: SUCCESS (Ready for Migration 010)" . PHP_EOL;
} else {
    echo "  PREFLIGHT RESULT: FAILED (Review failed checks before proceeding)" . PHP_EOL;
}
echo "======================================================================" . PHP_EOL;
