<?php

declare(strict_types=1);

/**
 * CLI Database Setup, Incremental Migration Runner & Initial Local Administrator Bootstrapper
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts/setup-database.php [--seed-admin] [--admin-user=admin] [--admin-pass=...]
 */

use App\Core\Bootstrap;
use App\Core\Config;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI execution only.');
}

require_once dirname(__DIR__) . '/app/Core/Bootstrap.php';
Bootstrap::init(dirname(__DIR__));

/**
 * Execute a multi-statement SQL file cleanly.
 */
function executeSqlFile(PDO $pdo, string $filePath): int
{
    $sql = file_get_contents($filePath);
    if ($sql === false) {
        throw new RuntimeException("Unable to read SQL file: {$filePath}");
    }

    $lines = preg_split('/\R/', $sql) ?: [];
    $filteredLines = [];
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '--')) {
            continue;
        }
        $filteredLines[] = $line;
    }

    $cleanSql = implode("\n", $filteredLines);
    $statements = array_filter(array_map('trim', explode(';', $cleanSql)), static fn(string $s): bool => $s !== '');

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    return count($statements);
}

/**
 * Add a foreign key constraint only if it does not already exist in information_schema.
 */
function ensureForeignKey(PDO $pdo, string $dbName, string $table, string $constraintName, string $definitionSql): void
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = :schema
           AND TABLE_NAME = :table_name
           AND CONSTRAINT_NAME = :constraint_name
         LIMIT 1'
    );
    $stmt->execute([
        'schema' => $dbName,
        'table_name' => $table,
        'constraint_name' => $constraintName,
    ]);

    if ($stmt->fetchColumn() === false) {
        $pdo->exec("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraintName}` {$definitionSql}");
    }
}

$options = getopt('', ['seed-admin', 'admin-user::', 'admin-email::', 'admin-pass::', 'admin-name::']);

$schemaFile = BASE_PATH . '/database/schema.sql';
if (!is_file($schemaFile)) {
    fwrite(STDERR, "Schema file not found at {$schemaFile}\n");
    exit(1);
}

echo "==> Connecting to MySQL/MariaDB server...\n";
$serverPdo = Database::connectServerOnly();

$schemaCount = executeSqlFile($serverPdo, $schemaFile);
echo "==> Applied database/schema.sql ({$schemaCount} statements).\n";

$migrationFiles = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
sort($migrationFiles);
foreach ($migrationFiles as $migrationFile) {
    $migCount = executeSqlFile($serverPdo, $migrationFile);
    echo "==> Applied migration " . basename($migrationFile) . " ({$migCount} statements).\n";
}

Database::disconnect();
$pdo = Database::connection();
$dbName = (string) Config::get('database.database', 'business_management_system');

// Ensure foreign keys added by incremental migrations exist on upgraded databases
ensureForeignKey(
    $pdo,
    $dbName,
    'projects',
    'fk_projects_origin_department',
    'FOREIGN KEY (`origin_department_id`) REFERENCES `departments` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
ensureForeignKey(
    $pdo,
    $dbName,
    'projects',
    'fk_projects_submitted_by',
    'FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
ensureForeignKey(
    $pdo,
    $dbName,
    'projects',
    'fk_projects_returned_by',
    'FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

$deptCount = (int) $pdo->query('SELECT COUNT(*) FROM departments')->fetchColumn();
$roleCount = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
echo "==> Verified schema: {$deptCount} departments, {$roleCount} roles seeded in `{$dbName}`.\n";

if (array_key_exists('seed-admin', $options)) {
    $adminUsername = trim((string) ($options['admin-user'] ?? 'admin'));
    $adminEmail = trim((string) ($options['admin-email'] ?? 'admin@syb.local'));
    $adminName = trim((string) ($options['admin-name'] ?? 'System Administrator'));
    $adminPass = (string) ($options['admin-pass'] ?? 'Admin@SYB2026!');

    $adminRoleStmt = $pdo->prepare('SELECT id FROM roles WHERE role_slug = :slug LIMIT 1');
    $adminRoleStmt->execute(['slug' => 'administrator']);
    $adminRoleId = (int) $adminRoleStmt->fetchColumn();

    if ($adminRoleId <= 0) {
        fwrite(STDERR, "Administrator role not found.\n");
        exit(1);
    }

    $existingStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $existingStmt->execute(['username' => $adminUsername]);
    $existingId = $existingStmt->fetchColumn();

    if ($existingId !== false) {
        echo "==> Administrator user `{$adminUsername}` already exists (ID #{$existingId}). Skipping creation.\n";
    } else {
        $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);
        $insertStmt = $pdo->prepare(
            'INSERT INTO users (department_id, role_id, full_name, username, email, password_hash, status)
             VALUES (NULL, :role_id, :full_name, :username, :email, :password_hash, :status)'
        );
        $insertStmt->execute([
            'role_id' => $adminRoleId,
            'full_name' => $adminName,
            'username' => $adminUsername,
            'email' => $adminEmail !== '' ? $adminEmail : null,
            'password_hash' => $passwordHash,
            'status' => 'active',
        ]);

        $newUserId = (int) $pdo->lastInsertId();
        $logStmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address)
             VALUES (:user_id, :action, :entity_type, :entity_id, :description, :ip_address)'
        );
        $logStmt->execute([
            'user_id' => $newUserId,
            'action' => 'system.admin_bootstrapped',
            'entity_type' => 'user',
            'entity_id' => $newUserId,
            'description' => "Initial Administrator account ({$adminUsername}) created via CLI setup script.",
            'ip_address' => 'CLI',
        ]);

        echo "==> Seeded initial Administrator account: username=`{$adminUsername}` (ID #{$newUserId}).\n";
    }
}

echo "==> Database setup completed successfully.\n";
