<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Models\ActivityLog;
use App\Models\User;

final class AuthService
{
    public const SESSION_USER_KEY = 'auth_user_id';
    private const GENERIC_LOGIN_ERROR = 'Invalid username or password.';
    private const THROTTLE_ERROR = 'Too many failed sign-in attempts. Please wait a few minutes before trying again.';

    /**
     * Precomputed bcrypt hash used for constant-time comparison when a username does not exist.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    /** @var array<string, mixed>|null */
    private static ?array $cachedUser = null;
    private static bool $userResolved = false;

    /**
     * @return array{success: bool, error?: string, user?: array<string, mixed>}
     */
    public static function attemptLogin(string $username, string $password, Request $request): array
    {
        $ip = $request->ip();

        if (ActivityLog::countRecentFailedLogins($ip, 10) >= 12) {
            ActivityLogger::log(
                'auth.login_throttled',
                'auth',
                null,
                'Sign-in attempt throttled due to repeated failed attempts.',
                $request,
                null,
                false
            );
            return [
                'success' => false,
                'error' => self::THROTTLE_ERROR,
            ];
        }

        $user = User::findByUsername($username);

        if ($user === null) {
            password_verify($password, self::DUMMY_PASSWORD_HASH);
            ActivityLogger::log(
                'auth.login_failed',
                'auth',
                null,
                'Failed sign-in attempt for non-existent or invalid credentials.',
                $request,
                null,
                false
            );
            return [
                'success' => false,
                'error' => self::GENERIC_LOGIN_ERROR,
            ];
        }

        $passwordMatches = password_verify($password, (string) $user['password_hash']);

        if (!$passwordMatches) {
            ActivityLogger::log(
                'auth.login_failed',
                'user',
                (int) $user['id'],
                "Failed sign-in attempt for username '{$user['username']}'.",
                $request,
                (int) $user['id'],
                false
            );
            return [
                'success' => false,
                'error' => self::GENERIC_LOGIN_ERROR,
            ];
        }

        if (($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            ActivityLogger::log(
                'auth.login_blocked_inactive',
                'user',
                (int) $user['id'],
                "Blocked sign-in attempt for inactive account '{$user['username']}'.",
                $request,
                (int) $user['id'],
                false
            );
            return [
                'success' => false,
                'error' => self::GENERIC_LOGIN_ERROR,
            ];
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            User::updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        Session::regenerate();
        Csrf::rotate();
        Session::set(self::SESSION_USER_KEY, (int) $user['id']);

        User::recordLastLogin((int) $user['id']);
        $freshUser = User::findById((int) $user['id']) ?? $user;

        self::$cachedUser = $freshUser;
        self::$userResolved = true;

        ActivityLogger::log(
            'auth.login_success',
            'user',
            (int) $freshUser['id'],
            "User '{$freshUser['username']}' signed in successfully.",
            $request,
            (int) $freshUser['id'],
            false
        );

        return [
            'success' => true,
            'user' => $freshUser,
        ];
    }

    /**
     * Resolve and validate the currently authenticated active user from the database.
     *
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        if (self::$userResolved) {
            return self::$cachedUser;
        }

        self::$userResolved = true;
        $userId = Session::get(self::SESSION_USER_KEY);

        if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
            self::$cachedUser = null;
            return null;
        }

        $user = User::findById((int) $userId);
        if ($user === null || ($user['status'] ?? '') !== User::STATUS_ACTIVE) {
            Session::remove(self::SESSION_USER_KEY);
            Csrf::rotate();
            self::$cachedUser = null;
            return null;
        }

        self::$cachedUser = $user;
        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(Request $request): void
    {
        $user = self::user();
        if ($user !== null) {
            ActivityLogger::log(
                'auth.logout',
                'user',
                (int) $user['id'],
                "User '{$user['username']}' signed out.",
                $request,
                (int) $user['id'],
                false
            );
        }

        self::clearCache();
        Session::destroy();
    }

    public static function clearCache(): void
    {
        self::$cachedUser = null;
        self::$userResolved = false;
    }
}

