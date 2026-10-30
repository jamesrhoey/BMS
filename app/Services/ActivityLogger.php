<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Models\ActivityLog;

final class ActivityLogger
{
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId,
        string $description,
        ?Request $request = null,
        ?int $explicitUserId = null,
        bool $useCurrentAuthUser = true
    ): void {
        $userId = $explicitUserId;
        if ($userId === null && $useCurrentAuthUser) {
            $currentUser = AuthService::user();
            $userId = $currentUser !== null ? (int) $currentUser['id'] : null;
        }

        $ip = $request?->ip();

        ActivityLog::record(
            $userId,
            $action,
            $entityType,
            $entityId,
            $description,
            $ip
        );
    }
}

