<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Models\ActivityLog;

final class ActivityLogger
{
    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId,
        string $description,
        ?Request $request = null,
        ?int $explicitUserId = null,
        bool $useCurrentAuthUser = true,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $userId = $explicitUserId;
        if ($userId === null && $useCurrentAuthUser) {
            $currentUser = AuthService::user();
            $userId = $currentUser !== null ? (int) $currentUser['id'] : null;
        }

        $ip = $request?->ip() ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        ActivityLog::record(
            $userId,
            $action,
            $entityType,
            $entityId,
            $description,
            $ip,
            $oldValues,
            $newValues,
            $userAgent
        );
    }
}

