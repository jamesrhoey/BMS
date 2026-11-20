<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

/**
 * Client / Customer master model backed by `clients`.
 * Supports automatic client registration when ad-hoc company names are entered on Stock Out (Form 2).
 */
final class Client
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function allActive(): array
    {
        $sql = 'SELECT c.*, pt.name AS payment_term_name, pt.days AS payment_term_days
                FROM clients c
                LEFT JOIN payment_terms pt ON pt.id = c.payment_term_id
                WHERE c.is_active = 1
                ORDER BY c.company_name ASC';
        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, pt.name AS payment_term_name, pt.days AS payment_term_days
             FROM clients c
             LEFT JOIN payment_terms pt ON pt.id = c.payment_term_id
             WHERE c.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function generateClientCode(?PDO $pdo = null): string
    {
        $db = $pdo ?? Database::connection();
        $stmt = $db->query("SELECT client_code FROM clients WHERE client_code LIKE 'CLI-%' ORDER BY id DESC LIMIT 1");
        $last = $stmt->fetchColumn();

        if (is_string($last) && preg_match('/CLI-(\d+)/', $last, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        return 'CLI-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Find an existing client by company name (case-insensitive) or register a new one automatically.
     *
     * @return array<string, mixed>
     */
    public static function findOrCreateByCompanyName(
        string $companyName,
        ?string $shippingAddress = null,
        ?string $contactPerson = null,
        ?string $phone = null,
        ?string $email = null,
        ?PDO $pdo = null
    ): array {
        $name = trim($companyName);
        if ($name === '') {
            throw new InvalidArgumentException('Client company name is required.');
        }

        $db = $pdo ?? Database::connection();
        $stmt = $db->prepare('SELECT * FROM clients WHERE LOWER(TRIM(company_name)) = LOWER(:name) LIMIT 1');
        $stmt->execute(['name' => $name]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ($shippingAddress !== null && trim($shippingAddress) !== '' && empty($existing['shipping_address'])) {
                $upd = $db->prepare('UPDATE clients SET shipping_address = :addr WHERE id = :id');
                $upd->execute(['addr' => trim($shippingAddress), 'id' => (int) $existing['id']]);
                $existing['shipping_address'] = trim($shippingAddress);
            }
            return $existing;
        }

        $code = self::generateClientCode($db);
        $ins = $db->prepare(
            'INSERT INTO clients (client_code, company_name, contact_person, email, phone, billing_address, shipping_address, is_active)
             VALUES (:code, :name, :contact, :email, :phone, :baddr, :saddr, 1)'
        );
        $addr = $shippingAddress !== null && trim($shippingAddress) !== '' ? trim($shippingAddress) : null;
        $ins->execute([
            'code' => $code,
            'name' => $name,
            'contact' => $contactPerson !== null && trim($contactPerson) !== '' ? trim($contactPerson) : null,
            'email' => $email !== null && trim($email) !== '' ? trim($email) : null,
            'phone' => $phone !== null && trim($phone) !== '' ? trim($phone) : null,
            'baddr' => $addr,
            'saddr' => $addr,
        ]);

        $newId = (int) $db->lastInsertId();
        $fetch = $db->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $fetch->execute(['id' => $newId]);
        return (array) $fetch->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $companyName = trim((string) ($data['company_name'] ?? ''));
        if ($companyName === '') {
            throw new InvalidArgumentException('Company name is required.');
        }

        $db = Database::connection();
        $code = trim((string) ($data['client_code'] ?? ''));
        if ($code === '') {
            $code = self::generateClientCode($db);
        }

        $stmt = $db->prepare(
            'INSERT INTO clients (client_code, company_name, contact_person, email, phone, billing_address, shipping_address, tax_id, payment_term_id, is_active)
             VALUES (:code, :name, :contact, :email, :phone, :baddr, :saddr, :tax_id, :pt_id, 1)'
        );
        $stmt->execute([
            'code' => $code,
            'name' => $companyName,
            'contact' => ($data['contact_person'] ?? '') !== '' ? trim((string) $data['contact_person']) : null,
            'email' => ($data['email'] ?? '') !== '' ? trim((string) $data['email']) : null,
            'phone' => ($data['phone'] ?? '') !== '' ? trim((string) $data['phone']) : null,
            'baddr' => ($data['billing_address'] ?? '') !== '' ? trim((string) $data['billing_address']) : null,
            'saddr' => ($data['shipping_address'] ?? '') !== '' ? trim((string) $data['shipping_address']) : null,
            'tax_id' => ($data['tax_id'] ?? '') !== '' ? trim((string) $data['tax_id']) : null,
            'pt_id' => !empty($data['payment_term_id']) ? (int) $data['payment_term_id'] : null,
        ]);

        return (int) $db->lastInsertId();
    }
}
