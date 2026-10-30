<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Notification;
use App\Services\ActivityLogger;
use App\Services\AuthService;
use App\Services\AuthorizationService;

abstract class Controller
{
    /**
     * @return array<string, mixed>|null
     */
    protected function currentUser(): ?array
    {
        return AuthService::user();
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireUser(): array
    {
        $user = $this->currentUser();
        if ($user === null) {
            Response::redirect('/login');
        }
        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireAdmin(Request $request): array
    {
        $user = $this->requireUser();
        if (!AuthorizationService::isAdmin($user)) {
            $this->denyAccess($request, "Non-administrator attempted to access '{$request->path()}'.");
        }
        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireMarketingAccess(Request $request): array
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessMarketingModule($user)) {
            $this->denyAccess(
                $request,
                "Unauthorized user '{$user['username']}' attempted to access Marketing route '{$request->path()}'."
            );
        }
        return $user;
    }

    protected function denyAccess(Request $request, string $auditReason, string $userMessage = 'You do not have permission to perform this action or view this resource.'): never
    {
        ActivityLogger::log(
            'security.unauthorized_access',
            'authorization',
            null,
            $auditReason,
            $request
        );

        http_response_code(403);
        $this->render('errors/403', [
            'pageTitle' => 'Access Denied',
            'errorMessage' => $userMessage,
        ]);
        exit;
    }

    protected function notFound(string $message = 'The requested record or page could not be found.'): never
    {
        http_response_code(404);
        $this->render('errors/404', [
            'pageTitle' => 'Not Found',
            'errorMessage' => $message,
        ], $this->currentUser() !== null ? 'layouts/app' : 'layouts/auth');
        exit;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $user = $this->currentUser();
        $unreadNotifications = $user !== null ? Notification::unreadCountForUser((int) $user['id']) : 0;
        $topbarNotifications = $user !== null ? Notification::forUser((int) $user['id'], 5) : [];

        $defaults = [
            'appName' => (string) Config::get('app.name', 'SYB Business Management System'),
            'currentUser' => $user,
            'isAdmin' => AuthorizationService::isAdmin($user),
            'isMarketingUser' => AuthorizationService::isMarketingUser($user),
            'isProjectManagementUser' => AuthorizationService::isProjectManagementUser($user),
            'canAccessMarketing' => AuthorizationService::canAccessMarketingModule($user),
            'canRegisterProject' => AuthorizationService::canRegisterProject($user),
            'unreadNotificationCount' => $unreadNotifications,
            'topbarNotifications' => $topbarNotifications,
            'activeNav' => $data['activeNav'] ?? '',
            'pageTitle' => $data['pageTitle'] ?? 'Dashboard',
        ];

        View::render($view, array_merge($defaults, $data), $layout);
    }
}
