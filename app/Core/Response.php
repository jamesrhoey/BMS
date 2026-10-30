<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    private static string $basePath = '';

    public static function setBasePath(string $basePath): void
    {
        self::$basePath = rtrim($basePath, '/');
    }

    public static function basePath(): string
    {
        return self::$basePath;
    }

    public static function url(string $path = '/'): string
    {
        $normalized = '/' . ltrim($path, '/');
        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        if (self::$basePath === '') {
            return $normalized;
        }

        return $normalized === '/' ? self::$basePath . '/' : self::$basePath . $normalized;
    }

    public static function asset(string $assetPath): string
    {
        $cleanPath = ltrim($assetPath, '/');
        $baseAssetUrl = self::url('/assets/' . $cleanPath);
        $diskPath = defined('BASE_PATH')
            ? BASE_PATH . '/public/assets/' . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath)
            : '';

        if ($diskPath !== '' && is_file($diskPath)) {
            $mtime = @filemtime($diskPath);
            if ($mtime !== false) {
                return $baseAssetUrl . '?v=' . $mtime;
            }
        }

        return $baseAssetUrl;
    }

    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header(
            "Content-Security-Policy: default-src 'self'; " .
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
            "script-src 'self' https://cdn.jsdelivr.net; " .
            "font-src 'self' https://cdn.jsdelivr.net data:; " .
            "img-src 'self' data: blob:; " .
            "connect-src 'self'; " .
            "object-src 'self' blob:; " .
            "frame-src 'self' blob:; " .
            "frame-ancestors 'self'; " .
            "base-uri 'self'; " .
            "form-action 'self';"
        );
    }

    public static function redirect(string $path, int $statusCode = 303): never
    {
        // Enforce internal relative paths only to prevent open-redirect vulnerabilities
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $path) || str_starts_with($path, '//')) {
            $path = '/';
        }

        $target = str_starts_with($path, self::$basePath . '/') && self::$basePath !== ''
            ? $path
            : self::url($path);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (!headers_sent()) {
            self::sendSecurityHeaders();
            header('Location: ' . $target, true, $statusCode);
        }

        exit;
    }

    public static function download(string $absoluteFilePath, string $downloadFilename, string $mimeType): never
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (!is_file($absoluteFilePath) || !is_readable($absoluteFilePath)) {
            http_response_code(404);
            exit('Requested document could not be found.');
        }

        $safeName = preg_replace('/[^\w\s.\-()]/u', '_', basename($downloadFilename)) ?: 'document';
        $safeName = str_replace(['"', "\r", "\n"], '', $safeName);
        $fileSize = (int) filesize($absoluteFilePath);

        if (!headers_sent()) {
            self::sendSecurityHeaders();
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . $safeName . '"');
            header('Content-Length: ' . $fileSize);
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        readfile($absoluteFilePath);
        exit;
    }

    public static function streamInline(string $absoluteFilePath, string $displayFilename, string $mimeType): never
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (!is_file($absoluteFilePath) || !is_readable($absoluteFilePath)) {
            http_response_code(404);
            exit('Requested document could not be found.');
        }

        $safeName = preg_replace('/[^\w\s.\-()]/u', '_', basename($displayFilename)) ?: 'document';
        $safeName = str_replace(['"', "\r", "\n"], '', $safeName);
        $fileSize = (int) filesize($absoluteFilePath);

        if (!headers_sent()) {
            self::sendSecurityHeaders();
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: inline; filename="' . $safeName . '"');
            header('Content-Length: ' . $fileSize);
            header('Cache-Control: private, max-age=3600, must-revalidate');
            header('Pragma: private');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        readfile($absoluteFilePath);
        exit;
    }
}

