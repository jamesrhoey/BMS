<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\ActivityLogger;

final class CsrfMiddleware
{
    public function handle(Request $request): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        // Detect oversized multipart POST bodies stripped by PHP's post_max_size setting
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
            http_response_code(413);
            View::render('errors/403', [
                'pageTitle' => 'Upload Too Large',
                'errorCode' => '413',
                'errorHeading' => 'Request Payload Too Large',
                'errorMessage' => 'The uploaded file exceeds the server maximum upload size limit. Please select a smaller file and try again.',
            ], Session::has('auth_user_id') ? 'layouts/app' : 'layouts/auth');
            exit;
        }

        $token = $request->post(Csrf::TOKEN_KEY);
        if (!is_string($token) || !Csrf::verify($token)) {
            ActivityLogger::log(
                'security.csrf_mismatch',
                'security',
                null,
                'Rejected state-changing request due to invalid or missing CSRF token on path ' . $request->path() . '.',
                $request
            );

            http_response_code(403);
            View::render('errors/403', [
                'pageTitle' => 'Security Verification Failed',
                'errorCode' => '403',
                'errorHeading' => '403 — Security Verification Failed',
                'errorMessage' => 'Your form security token was missing or expired. Please go back, refresh the page, and submit again.',
            ], Session::has('auth_user_id') ? 'layouts/app' : 'layouts/auth');
            exit;
        }
    }
}
