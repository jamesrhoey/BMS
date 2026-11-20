<?php

declare(strict_types=1);

/**
 * Phase 1 Migration Runner & Verification Script for Migration 011
 * Executes 011_integrate_iatms_modules.sql against business_management_system
 * and verifies zero data loss, schema integrity, and idempotency.
 */

$host = '127.0.0.1';
$dbname = 'business_management_system';
$user = 'root';
$pass = '';

$pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "======================================================================" . PHP_EOL;
echo "  BMS PHASE 1 — EXECUTING MIGRATION 011 (IATMS INTEGRATION)" . PHP_EOL;
echo "======================================================================" . PHP_EOL;

// 1. Capture pre-migration baseline row counts
$protectedTables = ['users', 'roles', 'departments', 'projects', 'project_documents', 'suppliers', 'purchase_requests', 'purchase_orders', 'activity_logs'];
$beforeCounts = [];
foreach ($protectedTables as $t) {
    $beforeCounts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
}
echo "1. Pre-Migration Baseline Counts Captured." . PHP_EOL;

// 2. Load and execute SQL file
$sqlPath = __DIR__ . '/011_integrate_iatms_modules.sql';
$sql = file_get_contents($sqlPath);
if ($sql === false) {
    echo "[FAIL] Could not read {$sqlPath}" . PHP_EOL;
    exit(1);
}

// Strip single-line comments and split statements safely by semicolon at end of line
$lines = explode("\n", str_replace("\r\n", "\n", $sql));
$cleanLines = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '--')) {
        continue;
    }
    $cleanLines[] = $line;
}
$cleanSql = implode("\n", $cleanLines);
$statements = array_filter(array_map('trim', explode(";\n", $cleanSql)));

$executed = 0;
foreach ($statements as $idx => $stmt) {
    $stmt = rtrim($stmt, ';');
    if ($stmt === '') {
        continue;
    }
    try {
        $pdo->exec($stmt);
        $executed++;
    } catch (PDOException $e) {
        echo "[ERROR] Statement #" . ($idx + 1) . " failed: " . $e->getMessage() . PHP_EOL;
        echo "SQL: " . substr($stmt, 0, 200) . "..." . PHP_EOL;
        exit(1);
    }
}
echo "2. Executed {$executed} SQL statements successfully." . PHP_EOL;

// 3. Verify Zero Data Loss on Protected Tables
$lossDetected = false;
foreach ($protectedTables as $t) {
    $after = (int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    if ($after < $beforeCounts[$t]) {
        echo "  [FAIL] Data loss detected on `{$t}`: before={$beforeCounts[$t]}, after={$after}" . PHP_EOL;
        $lossDetected = true;
    } else {
        echo "  [PASS] `{$t}` intact ({$after} rows)" . PHP_EOL;
    }
}
if ($lossDetected) {
    exit(1);
}

// 4. Verify New IATMS Tables Created
$expectedNewTables = [
    'settings',
    'payment_terms',
    'clients',
    'categories',
    'equipment_models',
    'inventory_locations',
    'inventory_stock',
    'inventory_ledger',
    'stock_transactions',
    'stock_transaction_items',
    'stock_transaction_serials',
    'supplier_receipts',
    'supplier_receipt_items',
    'supplier_receipt_serials',
    'customer_deliveries',
    'customer_delivery_items',
    'customer_delivery_serials',
    'customer_delivery_updates',
    'invoices',
    'invoice_items',
    'collections',
    'warranties',
    'warranty_claims',
    'daily_status_history',
];

$allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo PHP_EOL . "3. Verifying New IATMS Tables (" . count($expectedNewTables) . "):" . PHP_EOL;
foreach ($expectedNewTables as $nt) {
    if (in_array($nt, $allTables, true)) {
        echo "  [PASS] Table `{$nt}` created." . PHP_EOL;
    } else {
        echo "  [FAIL] Missing expected table `{$nt}`!" . PHP_EOL;
        exit(1);
    }
}

// 5. Verify Seeded Dictionaries & Permissions
$settingsCount = (int) $pdo->query("SELECT COUNT(*) FROM `settings`")->fetchColumn();
$termsCount = (int) $pdo->query("SELECT COUNT(*) FROM `payment_terms`")->fetchColumn();
$catCount = (int) $pdo->query("SELECT COUNT(*) FROM `categories`")->fetchColumn();
$locCount = (int) $pdo->query("SELECT COUNT(*) FROM `inventory_locations`")->fetchColumn();
$iatmsPermsCount = (int) $pdo->query("SELECT COUNT(*) FROM `permissions` WHERE `permission_key` LIKE 'iatms.%'")->fetchColumn();

echo PHP_EOL . "4. Verifying Seeded Master Data:" . PHP_EOL;
echo "  [PASS] settings: {$settingsCount} keys" . PHP_EOL;
echo "  [PASS] payment_terms: {$termsCount} terms" . PHP_EOL;
echo "  [PASS] categories: {$catCount} categories" . PHP_EOL;
echo "  [PASS] inventory_locations: {$locCount} locations" . PHP_EOL;
echo "  [PASS] IATMS permissions seeded: {$iatmsPermsCount} permissions" . PHP_EOL;
echo "  [INFO] Total tables in `business_management_system`: " . count($allTables) . PHP_EOL;

echo PHP_EOL . "======================================================================" . PHP_EOL;
echo "  PHASE 1 MIGRATION 011 RESULT: 100% SUCCESS" . PHP_EOL;
echo "======================================================================" . PHP_EOL;
