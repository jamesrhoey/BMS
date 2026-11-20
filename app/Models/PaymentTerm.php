<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateTimeImmutable;
use PDO;

/**
 * Payment Terms Master Dictionary model backed by `payment_terms`.
 */
final class PaymentTerm
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function allActive(): array
    {
        return Database::connection()
            ->query('SELECT * FROM payment_terms WHERE is_active = 1 ORDER BY days ASC, name ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM payment_terms WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Compute due date by adding credit days to the base reference date.
     */
    public static function calculateDueDate(string $baseDate, int $creditDays): string
    {
        $dt = new DateTimeImmutable($baseDate);
        if ($creditDays <= 0) {
            return $dt->format('Y-m-d');
        }
        return $dt->modify("+{$creditDays} days")->format('Y-m-d');
    }
}
