<?php

declare(strict_types=1);

use App\Controllers\ActivityLogController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\DepartmentController;
use App\Controllers\MarketingController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\ProjectController;
use App\Controllers\ProjectDocumentController;
use App\Controllers\ProjectManagementController;
use App\Controllers\UserController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\Role;

/** @var Router $router */

// Enforce CSRF verification globally on all state-changing HTTP methods
$router->addGlobalMiddleware(CsrfMiddleware::class);

// Root & Authentication Routes
$router->get('/', [AuthController::class, 'home']);
$router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
$router->post('/login', [AuthController::class, 'login'], [GuestMiddleware::class]);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

// Authenticated Workspace, Profile & Notification Routes
$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);
$router->get('/dashboard/admin', [DashboardController::class, 'adminDashboard'], [
    AuthMiddleware::class,
    [RoleMiddleware::class, Role::SLUG_ADMIN],
]);
$router->get('/dashboard/department', [DashboardController::class, 'departmentDashboard'], [AuthMiddleware::class]);
$router->get('/profile', [ProfileController::class, 'show'], [AuthMiddleware::class]);

$router->get('/notifications', [NotificationController::class, 'index'], [AuthMiddleware::class]);
$router->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], [AuthMiddleware::class]);
$router->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], [AuthMiddleware::class]);

// Marketing Department Module Routes
$router->get('/marketing/dashboard', [MarketingController::class, 'dashboard'], [AuthMiddleware::class]);
$router->get('/marketing/projects', [MarketingController::class, 'projects'], [AuthMiddleware::class]);
$router->get('/marketing/projects/create', [MarketingController::class, 'createProject'], [AuthMiddleware::class]);
$router->post('/marketing/projects', [MarketingController::class, 'storeProject'], [AuthMiddleware::class]);

// Project Management Department Module Routes
$router->get('/pm/dashboard', [ProjectManagementController::class, 'dashboard'], [AuthMiddleware::class]);
$router->get('/pm/projects', [ProjectManagementController::class, 'projects'], [AuthMiddleware::class]);

// Administrator-Only User Management Routes
$adminMiddleware = [
    AuthMiddleware::class,
    [RoleMiddleware::class, Role::SLUG_ADMIN],
];

$router->get('/users', [UserController::class, 'index'], $adminMiddleware);
$router->get('/users/create', [UserController::class, 'create'], $adminMiddleware);
$router->post('/users', [UserController::class, 'store'], $adminMiddleware);
$router->get('/users/{id}/edit', [UserController::class, 'edit'], $adminMiddleware);
$router->post('/users/{id}', [UserController::class, 'update'], $adminMiddleware);
$router->post('/users/{id}/status', [UserController::class, 'toggleStatus'], $adminMiddleware);

// Administrator-Only Department Overview Routes
$router->get('/departments', [DepartmentController::class, 'index'], $adminMiddleware);
$router->get('/departments/{id}', [DepartmentController::class, 'show'], $adminMiddleware);

// Administrator-Only Immutable Audit Log Route
$router->get('/activity-logs', [ActivityLogController::class, 'index'], $adminMiddleware);

// Shared Project Registration, Marketing Checklist & Minimal PM Return Handoff Routes
$router->get('/projects', [ProjectController::class, 'index'], [AuthMiddleware::class]);
$router->get('/projects/create', [ProjectController::class, 'create'], [AuthMiddleware::class]);
$router->post('/projects', [ProjectController::class, 'store'], [AuthMiddleware::class]);
$router->get('/projects/{id}', [ProjectController::class, 'show'], [AuthMiddleware::class]);
$router->get('/projects/{id}/edit', [ProjectController::class, 'edit'], [AuthMiddleware::class]);
$router->post('/projects/{id}', [ProjectController::class, 'update'], [AuthMiddleware::class]);
$router->post('/projects/{id}/prepare-documents', [ProjectController::class, 'startPreparation'], [AuthMiddleware::class]);
$router->post('/projects/{id}/submit', [ProjectController::class, 'submit'], [AuthMiddleware::class]);
$router->post('/projects/{id}/start-review', [ProjectManagementController::class, 'startReview'], [AuthMiddleware::class]);
$router->post('/projects/{id}/complete-verification', [ProjectManagementController::class, 'completeVerification'], [AuthMiddleware::class]);
$router->post('/projects/{id}/issue-jom', [ProjectManagementController::class, 'issueJomNumber'], [AuthMiddleware::class]);
$router->post('/projects/{id}/return-to-marketing', [ProjectController::class, 'returnToMarketing'], [AuthMiddleware::class]);

// Project Document Upload, Replacement, Authorized View & Download Routes
$router->post('/projects/{id}/documents', [ProjectDocumentController::class, 'upload'], [AuthMiddleware::class]);
$router->get('/projects/{id}/documents/{docId}/view', [ProjectDocumentController::class, 'view'], [AuthMiddleware::class]);
$router->get('/projects/{id}/documents/{docId}/raw', [ProjectDocumentController::class, 'raw'], [AuthMiddleware::class]);
$router->get('/projects/{id}/documents/{docId}/download', [ProjectDocumentController::class, 'download'], [AuthMiddleware::class]);
