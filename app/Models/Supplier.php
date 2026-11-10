<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Supplier
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function allActive(): array
    {
        $stmt = Database::connection()->query(
            "SELECT * FROM suppliers WHERE status = 'active' ORDER BY supplier_name ASC"
        );

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM suppliers WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByCode(string $code): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM suppliers WHERE supplier_code = :code LIMIT 1'
        );
        $stmt->execute(['code' => trim($code)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO suppliers (
                supplier_code,
                supplier_name,
                contact_person,
                email,
                phone,
                address,
                tax_identification_number,
                default_payment_terms,
                status,
                created_by
            ) VALUES (
                :supplier_code,
                :supplier_name,
                :contact_person,
                :email,
                :phone,
                :address,
                :tax_identification_number,
                :default_payment_terms,
                :status,
                :created_by
            )'
        );

        $stmt->execute([
            'supplier_code' => $data['supplier_code'],
            'supplier_name' => $data['supplier_name'],
            'contact_person' => $data['contact_person'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'tax_identification_number' => $data['tax_identification_number'] ?? null,
            'default_payment_terms' => $data['default_payment_terms'] ?? 'Net 30',
            'status' => $data['status'] ?? 'active',
            'created_by' => $data['created_by'],
        ]);

        return (int) $pdo->lastInsertId();
    }
}
