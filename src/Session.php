<?php

namespace Senhas;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        $name = $_ENV['SESSION_NAME'] ?? 'senhas_session';
        session_name($name);

        $lifetime = (int)($_ENV['SESSION_LIFETIME'] ?? 3600);
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime'  => $lifetime,
            'path'      => '/',
            'domain'    => '',
            'secure'    => $secure,
            'httponly'  => true,
            'samesite'  => 'Strict',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_start();
        self::$started = true;
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

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function setUser(int $userId, string $email): void
    {
        self::set('user_id', $userId);
        self::set('user_email', $email);
        self::set('fingerprint', self::generateFingerprint());
        self::set('last_activity', time());
    }

    public static function isAuthenticated(): bool
    {
        if (!self::has('user_id')) {
            return false;
        }

        $fingerprint = self::get('fingerprint');
        if ($fingerprint !== null && !hash_equals($fingerprint, self::generateFingerprint())) {
            self::destroy();
            return false;
        }

        $lifetime = (int)($_ENV['SESSION_LIFETIME'] ?? 3600);
        $lastActivity = self::get('last_activity', 0);
        if (time() - $lastActivity > $lifetime) {
            self::destroy();
            return false;
        }

        self::set('last_activity', time());
        return true;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        self::$started = false;
    }

    private static function generateFingerprint(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash('sha256', $ip . $ua . ($_ENV['APP_KEY'] ?? ''));
    }
}
