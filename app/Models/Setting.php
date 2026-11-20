<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * System Settings model backed by the `settings` table.
 */
final class Setting
{
    /** @var array<string, string|null> */
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key] ?? $default;
        }

        $stmt = Database::connection()->prepare(
            'SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1'
        );
        $stmt->execute(['k' => $key]);
        $val = $stmt->fetchColumn();

        if ($val === false) {
            return $default;
        }

        self::$cache[$key] = $val !== null ? (string) $val : null;
        return self::$cache[$key] ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $val = self::get($key, null);
        return $val !== null && is_numeric($val) ? (int) $val : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = self::get($key, null);
        if ($val === null) {
            return $default;
        }
        return in_array(strtolower(trim((string) $val)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function set(
        string $key,
        ?string $value,
        string $group = 'general',
        ?string $description = null
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO settings (setting_key, setting_value, setting_group, description)
             VALUES (:k, :v, :g, :d)
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_group = VALUES(setting_group),
                description = COALESCE(VALUES(description), description),
                updated_at = NOW()'
        );
        $stmt->execute([
            'k' => $key,
            'v' => $value,
            'g' => $group,
            'd' => $description,
        ]);
        self::$cache[$key] = $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT * FROM settings ORDER BY setting_group ASC, setting_key ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
