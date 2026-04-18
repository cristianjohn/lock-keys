<?php

namespace LockKeys;

use LockKeys\Database;
use LockKeys\RateLimiter;
use LockKeys\AuditLog;
use LockKeys\Category;

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

        Category::initDefaultsForUser($userId);

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

    public function changePassword(string $currentAuthHash, string $newAuthHash, string $newSalt, array $reencryptedItems): array
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return ['success' => false, 'error' => 'Não autenticado'];
        }

        if (strlen($currentAuthHash) < 64 || strlen($newAuthHash) < 64) {
            return ['success' => false, 'error' => 'Hash de autenticação inválido'];
        }

        if (strlen($newSalt) < 64 || !ctype_xdigit($newSalt)) {
            return ['success' => false, 'error' => 'Salt inválido'];
        }

        $db = Database::getInstance();
        $pdo = $db->getConnection();

        $stmt = $pdo->prepare("SELECT auth_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !hash_equals($user['auth_hash'], $currentAuthHash)) {
            AuditLog::log($userId, 'auth.change_password_failed');
            return ['success' => false, 'error' => 'Senha atual incorreta'];
        }

        $validIds = [];
        if (!empty($reencryptedItems)) {
            $stmt = $pdo->prepare("SELECT id FROM vault_items WHERE user_id = ?");
            $stmt->execute([$userId]);
            $validIds = array_column($stmt->fetchAll(), 'id');
        }

        $db->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE users SET auth_hash = ?, password_salt = ? WHERE id = ?");
            $stmt->execute([$newAuthHash, $newSalt, $userId]);

            foreach ($reencryptedItems as $item) {
                $itemId = (int)($item['id'] ?? 0);
                if (!in_array($itemId, $validIds, true)) {
                    $db->rollBack();
                    return ['success' => false, 'error' => 'Item inválido: ' . $itemId];
                }

                $iv = $item['iv'] ?? '';
                $authTag = $item['auth_tag'] ?? '';
                $encryptedData = $item['encrypted_data'] ?? '';

                if (strlen($iv) !== 24 || !ctype_xdigit($iv)) {
                    $db->rollBack();
                    return ['success' => false, 'error' => 'IV inválido para item ' . $itemId];
                }
                if (strlen($authTag) !== 32 || !ctype_xdigit($authTag)) {
                    $db->rollBack();
                    return ['success' => false, 'error' => 'Tag de autenticação inválida para item ' . $itemId];
                }

                $stmt = $pdo->prepare(
                    "UPDATE vault_items SET encrypted_data = ?, iv = ?, auth_tag = ? WHERE id = ? AND user_id = ?"
                );
                $stmt->execute([$encryptedData, $iv, $authTag, $itemId, $userId]);
            }

            $db->commit();
            AuditLog::log($userId, 'auth.change_password');
            return ['success' => true];
        } catch (\Throwable $e) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Erro ao atualizar. Tente novamente.'];
        }
    }
}
