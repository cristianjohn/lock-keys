<?php

namespace Senhas;

use Senhas\Database;

class RateLimiter
{
    private int $maxAttempts;
    private int $lockoutSeconds;

    public function __construct()
    {
        $this->maxAttempts = (int)($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5);
        $this->lockoutSeconds = ((int)($_ENV['LOGIN_LOCKOUT_MINUTES'] ?? 15)) * 60;
    }

    public function isLocked(string $ip, ?string $email = null): bool
    {
        $pdo = Database::getInstance()->getConnection();
        $window = date('Y-m-d H:i:s', time() - $this->lockoutSeconds);

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND created_at > ?"
        );
        $stmt->execute([$ip, $window]);
        $ipAttempts = (int)$stmt->fetchColumn();

        if ($ipAttempts >= $this->maxAttempts) {
            return true;
        }

        if ($email !== null) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND created_at > ?"
            );
            $stmt->execute([$email, $window]);
            $emailAttempts = (int)$stmt->fetchColumn();
            if ($emailAttempts >= $this->maxAttempts * 2) {
                return true;
            }
        }

        return false;
    }

    public function recordAttempt(string $ip, ?string $email, bool $success): void
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO login_attempts (ip_address, email, success) VALUES (?, ?, ?)"
        );
        $stmt->execute([$ip, $email, (int)$success]);
    }

    public function getRemainingAttempts(string $ip): int
    {
        $pdo = Database::getInstance()->getConnection();
        $window = date('Y-m-d H:i:s', time() - $this->lockoutSeconds);

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND created_at > ?"
        );
        $stmt->execute([$ip, $window]);
        $attempts = (int)$stmt->fetchColumn();

        return max(0, $this->maxAttempts - $attempts);
    }

    public function cleanup(): void
    {
        $pdo = Database::getInstance()->getConnection();
        $cutoff = date('Y-m-d H:i:s', time() - $this->lockoutSeconds * 2);
        $pdo->prepare("DELETE FROM login_attempts WHERE created_at < ?")->execute([$cutoff]);
    }
}
