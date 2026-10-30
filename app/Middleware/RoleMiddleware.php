<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ActivityLogger;
use App\Services\AuthService;

final class RoleMiddleware
{
    /** @var list<string> */
    private array $allowedRoles;

    public function __construct(string ...$allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function handle(Request $request): void
    {
        $user = AuthService::user();
        if ($user === null) {
            Response::redirect('/login');
        }

        $userRoleSlug = (string) ($user['role_slug'] ?? '');
        if (!in_array($userRoleSlug, $this->allowedRoles, true)) {
            ActivityLogger::log(
                'security.unauthorized_access',
                'route',
                null,
                "Blocked unauthorized access attempt to '{$request->path()}' by user '{$user['username']}' ({$user['role_name']}).",
                $request
            );

            http_response_code(403);
            View::render('errors/403', [
                'pageTitle' => 'Access Denied',
                'currentUser' => $user,
            ], 'layouts/app');
            exit;
        }
    }
}

