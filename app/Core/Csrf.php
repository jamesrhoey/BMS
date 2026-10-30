<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public const TOKEN_KEY = '_csrf_token';

    public static function token(): string
    {
        $existing = Session::get(self::TOKEN_KEY);
        if (is_string($existing) && strlen($existing) === 64) {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        Session::set(self::TOKEN_KEY, $token);
        return $token;
    }

    public static function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(self::TOKEN_KEY, $token);
        return $token;
    }

    public static function verify(?string $submittedToken): bool
    {
        if (!is_string($submittedToken) || $submittedToken === '') {
            return false;
        }

        $sessionToken = Session::get(self::TOKEN_KEY);
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::TOKEN_KEY . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    }
}

