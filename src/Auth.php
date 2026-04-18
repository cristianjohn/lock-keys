<?php

namespace Senhas;

use Senhas\Database;
use Senhas\RateLimiter;
use Senhas\AuditLog;

class Auth
{
    private RateLimiter $rateLimiter;

    public function __construct()
    {
        $this->rateLimiter = new RateLimiter();
    }

    public function register(string $email, string $authHash, string $salt, int $kdfIterations = 600000): array
    {
        $email = trim(strtolower($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Email inválido'];
        }

        if (strlen($authHash) < 64) {
            return ['success' => false, 'error' => 'Hash de autenticação inválido'];
        }

        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Este email já está registrado'];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO users (email, auth_hash, password_salt, kdf_iterations) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$email, $authHash, $salt, $kdfIterations]);

        $userId = (int)$pdo->lastInsertId();

        AuditLog::log($userId, 'auth.register');

        return ['success' => true, 'user_id' => $userId];
    }

    public function login(string $email, string $authHash): array
    {
        $email = trim(strtolower($email));
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if ($this->rateLimiter->isLocked($ip, $email)) {
            AuditLog::log(null, 'auth.locked', null, ['email' => $email]);
            return ['success' => false, 'error' => 'Muitas tentativas. Tente novamente em alguns minutos.'];
        }

        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare("SELECT id, email, auth_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !hash_equals($user['auth_hash'], $authHash)) {
            $this->rateLimiter->recordAttempt($ip, $email, false);
            AuditLog::log($user['id'] ?? null, 'auth.login_failed', null, ['email' => $email]);
            return ['success' => false, 'error' => 'Email ou senha incorretos'];
        }

        $this->rateLimiter->recordAttempt($ip, $email, true);
        $this->rateLimiter->cleanup();

        Session::regenerate();
        Session::setUser((int)$user['id'], $user['email']);

        AuditLog::log((int)$user['id'], 'auth.login');

        return ['success' => true, 'user_id' => (int)$user['id']];
    }

    public function logout(): void
    {
        $userId = Session::get('user_id');
        AuditLog::log($userId, 'auth.logout');
        Session::destroy();
    }
}
