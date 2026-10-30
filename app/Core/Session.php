<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private const FLASH_NEXT_KEY = '_flash_next';
    private const FLASH_CURRENT_KEY = '_flash_current';

    public static function start(Request $request): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $sessionName = (string) Config::get('app.session.name', 'SYB_BMS_SESSION');
        $secureSetting = strtolower((string) Config::get('app.session.secure_cookie', 'auto'));
        $isSecure = $secureSetting === 'true' || $secureSetting === '1'
            ? true
            : ($secureSetting === 'auto' ? $request->isHttps() : false);

        $cookiePath = $request->basePath() !== '' ? $request->basePath() : '/';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', $isSecure ? '1' : '0');

        session_name($sessionName);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        self::enforceExpiration();
        self::ageFlashData();
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_regenerated_at'] = time();
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    [
                        'expires' => time() - 42000,
                        'path' => $params['path'] ?: '/',
                        'domain' => $params['domain'] ?: '',
                        'secure' => (bool) $params['secure'],
                        'httponly' => (bool) $params['httponly'],
                        'samesite' => $params['samesite'] ?: 'Lax',
                    ]
                );
            }

            session_destroy();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        if (!isset($_SESSION[self::FLASH_NEXT_KEY]) || !is_array($_SESSION[self::FLASH_NEXT_KEY])) {
            $_SESSION[self::FLASH_NEXT_KEY] = [];
        }
        $_SESSION[self::FLASH_NEXT_KEY][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        if (isset($_SESSION[self::FLASH_CURRENT_KEY][$key])) {
            return $_SESSION[self::FLASH_CURRENT_KEY][$key];
        }
        if (isset($_SESSION[self::FLASH_NEXT_KEY][$key])) {
            return $_SESSION[self::FLASH_NEXT_KEY][$key];
        }
        return $default;
    }

    /**
     * @param array<string, string> $errors
     */
    public static function flashErrors(array $errors): void
    {
        self::flash('_validation_errors', $errors);
    }

    /**
     * @return array<string, string>
     */
    public static function errors(): array
    {
        $errors = self::getFlash('_validation_errors', []);
        return is_array($errors) ? $errors : [];
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function flashOldInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_csrf_token']);
        self::flash('_old_input', $input);
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        $old = self::getFlash('_old_input', []);
        if (is_array($old) && array_key_exists($key, $old)) {
            return $old[$key];
        }
        return $default;
    }

    private static function enforceExpiration(): void
    {
        $now = time();
        $idleLifetime = (int) Config::get('app.session.lifetime', 3600);
        $absoluteLifetime = (int) Config::get('app.session.absolute_lifetime', 28800);

        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = $now;
            $_SESSION['_last_activity'] = $now;
            $_SESSION['_regenerated_at'] = $now;
            return;
        }

        $expiredByIdle = isset($_SESSION['_last_activity'])
            && ($now - (int) $_SESSION['_last_activity']) > $idleLifetime;
        $expiredByAbsolute = ($now - (int) $_SESSION['_created_at']) > $absoluteLifetime;

        if ($expiredByIdle || $expiredByAbsolute) {
            $wasAuthenticated = isset($_SESSION['auth_user_id']);
            $_SESSION = [
                '_created_at' => $now,
                '_last_activity' => $now,
                '_regenerated_at' => $now,
            ];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            if ($wasAuthenticated) {
                self::flash('warning', 'Your session has expired due to inactivity. Please sign in again.');
            }
            return;
        }

        $_SESSION['_last_activity'] = $now;

        // Periodically rotate session ID every 15 minutes during active sessions
        if (!isset($_SESSION['_regenerated_at']) || ($now - (int) $_SESSION['_regenerated_at']) > 900) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            $_SESSION['_regenerated_at'] = $now;
        }
    }

    private static function ageFlashData(): void
    {
        $_SESSION[self::FLASH_CURRENT_KEY] = $_SESSION[self::FLASH_NEXT_KEY] ?? [];
        $_SESSION[self::FLASH_NEXT_KEY] = [];
    }
}

