<?php

declare(strict_types=1);

/**
 * CLI Administrator Management & Lockout Recovery Utility
 *
 * Provides a secure server-side recovery procedure if all administrator accounts
 * are accidentally deactivated or if an administrator password needs resetting.
 *
 * Commands:
 *   php scripts/manage-admin.php list
 *   php scripts/manage-admin.php recover --username=admin [--password=NewStrongPassword123!]
 *   php scripts/manage-admin.php create --username=admin2 --name="Jane Doe" --email=jane@syb.local --password=...
 */

use App\Core\Bootstrap;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI execution only.');
}

require_once dirname(__DIR__) . '/app/Core/Bootstrap.php';
Bootstrap::init(dirname(__DIR__));

$command = $argv[1] ?? 'help';
$options = getopt('', ['username::', 'password::', 'name::', 'email::']);

$pdo = Database::connection();

$roleStmt = $pdo->prepare('SELECT id FROM roles WHERE role_slug = :slug LIMIT 1');
$roleStmt->execute(['slug' => 'administrator']);
$adminRoleId = (int) $roleStmt->fetchColumn();

if ($adminRoleId <= 0) {
    fwrite(STDERR, "Error: 'Administrator' role is missing. Run scripts/setup-database.php first.\n");
    exit(1);
}

switch ($command) {
    case 'list':
        $stmt = $pdo->prepare(
            'SELECT id, username, full_name, email, status, last_login_at
             FROM users
             WHERE role_id = :role_id
             ORDER BY id ASC'
        );
        $stmt->execute(['role_id' => $adminRoleId]);
        $admins = $stmt->fetchAll();

        if ($admins === []) {
            echo "No Administrator accounts found.\n";
            exit(0);
        }

        echo "ID | Username | Full Name | Status | Last Login\n";
        echo str_repeat('-', 72) . "\n";
        foreach ($admins as $admin) {
            printf(
                "%d | %s | %s | %s | %s\n",
                $admin['id'],
                $admin['username'],
                $admin['full_name'],
                $admin['status'],
                $admin['last_login_at'] ?? 'Never'
            );
        }
        break;

    case 'recover':
        $username = trim((string) ($options['username'] ?? ''));
        $newPassword = (string) ($options['password'] ?? '');

        if ($username === '') {
            fwrite(STDERR, "Usage: php scripts/manage-admin.php recover --username=<username> [--password=<new_password>]\n");
            exit(1);
        }

        if ($newPassword !== '' && strlen($newPassword) < 8) {
            fwrite(STDERR, "Error: Password must be at least 8 characters long.\n");
            exit(1);
        }

        $userStmt = $pdo->prepare('SELECT id, username, role_id, status FROM users WHERE username = :username LIMIT 1');
        $userStmt->execute(['username' => $username]);
        $user = $userStmt->fetch();

        if (!$user) {
            fwrite(STDERR, "Error: User '{$username}' was not found.\n");
            exit(1);
        }

        Database::transaction(static function (PDO $tx) use ($user, $adminRoleId, $newPassword): void {
            if ($newPassword !== '') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upd = $tx->prepare(
                    'UPDATE users SET role_id = :role_id, status = :status, password_hash = :hash WHERE id = :id'
                );
                $upd->execute([
                    'role_id' => $adminRoleId,
                    'status' => 'active',
                    'hash' => $hash,
                    'id' => $user['id'],
                ]);
                $desc = "Emergency CLI recovery: reactivated Administrator '{$user['username']}' and reset password.";
            } else {
                $upd = $tx->prepare(
                    'UPDATE users SET role_id = :role_id, status = :status WHERE id = :id'
                );
                $upd->execute([
                    'role_id' => $adminRoleId,
                    'status' => 'active',
                    'id' => $user['id'],
                ]);
                $desc = "Emergency CLI recovery: reactivated Administrator '{$user['username']}'.";
            }

            $log = $tx->prepare(
                'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address)
                 VALUES (:user_id, :action, :entity_type, :entity_id, :description, :ip_address)'
            );
            $log->execute([
                'user_id' => $user['id'],
                'action' => 'user.admin_recovered',
                'entity_type' => 'user',
                'entity_id' => $user['id'],
                'description' => $desc,
                'ip_address' => 'CLI',
            ]);
        });

        echo "Success: Account '{$username}' is now an ACTIVE Administrator"
            . ($newPassword !== '' ? ' with an updated password hash.' : '.') . "\n";
        break;

    case 'create':
        $username = trim((string) ($options['username'] ?? ''));
        $fullName = trim((string) ($options['name'] ?? 'System Administrator'));
        $email = trim((string) ($options['email'] ?? ''));
        $password = (string) ($options['password'] ?? '');

        if ($username === '' || strlen($password) < 8) {
            fwrite(STDERR, "Usage: php scripts/manage-admin.php create --username=<user> --password=<min8chars> [--name=\"Full Name\"] [--email=user@example.com]\n");
            exit(1);
        }

        Database::transaction(static function (PDO $tx) use ($adminRoleId, $fullName, $username, $email, $password): void {
            $stmt = $tx->prepare(
                'INSERT INTO users (department_id, role_id, full_name, username, email, password_hash, status)
                 VALUES (NULL, :role_id, :full_name, :username, :email, :password_hash, :status)'
            );
            $stmt->execute([
                'role_id' => $adminRoleId,
                'full_name' => $fullName,
                'username' => $username,
                'email' => $email !== '' ? $email : null,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 'active',
            ]);

            $newId = (int) $tx->lastInsertId();
            $log = $tx->prepare(
                'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address)
                 VALUES (:user_id, :action, :entity_type, :entity_id, :description, :ip_address)'
            );
            $log->execute([
                'user_id' => $newId,
                'action' => 'user.admin_created_cli',
                'entity_type' => 'user',
                'entity_id' => $newId,
                'description' => "Created Administrator '{$username}' via CLI utility.",
                'ip_address' => 'CLI',
            ]);
        });

        echo "Success: Administrator '{$username}' created and activated.\n";
        break;

    default:
        echo "SYB BMS Administrator CLI Utility\n";
        echo "  php scripts/manage-admin.php list\n";
        echo "  php scripts/manage-admin.php recover --username=<username> [--password=<new_password>]\n";
        echo "  php scripts/manage-admin.php create --username=<username> --password=<min8chars> [--name=\"Full Name\"] [--email=addr]\n";
        break;
}

