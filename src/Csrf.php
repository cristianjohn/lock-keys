<?php

namespace LockKeys;

class Csrf
{
    public static function generate(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        return $token;
    }

    public static function getToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            return self::generate();
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate(string $token): bool
    {
        if (!isset($_SESSION['csrf_token'], $_SESSION['csrf_token_time'])) {
            return false;
        }

        $lifetime = (int)($_ENV['SESSION_LIFETIME'] ?? 3600);
        if (time() - $_SESSION['csrf_token_time'] > $lifetime) {
            self::generate();
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function field(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function meta(): string
    {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
