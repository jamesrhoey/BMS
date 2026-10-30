<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

final class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (!AuthService::check()) {
            if ($request->isGet() && $request->path() !== '/login') {
                Session::set('_intended_url', $request->path());
            }
            Response::redirect('/login');
        }
    }
}

