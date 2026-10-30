<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class GuestMiddleware
{
    public function handle(Request $request): void
    {
        if (AuthService::check()) {
            Response::redirect('/dashboard');
        }
    }
}

