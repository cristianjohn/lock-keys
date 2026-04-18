<?php

namespace LockKeys;

use LockKeys\Database;
use LockKeys\AuditLog;

class Vault
{
    private function getUserId(): ?int
    {
        return Session::get('user_id');
    }

    public function create(string $title, ?string $category, string $encryptedData, string $iv, string $authTag): array
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return ['success' => false, 'error' => 'Não autenticado'];
        }

        $title = trim($title);
        if (empty($title) || strlen($title) > 255) {
            return ['success' => false, 'error' => 'Título inválido'];
        }

        if (strlen($encryptedData) > 65536) {
            return ['success' => false, 'error' => 'Dados muito grandes'];
        }

        if (strlen($iv) !== 24 || !ctype_xdigit($iv)) {
            return ['success' => false, 'error' => 'IV inválido'];
        }

        if (strlen($authTag) !== 32 || !ctype_xdigit($authTag)) {
            return ['success' => false, 'error' => 'Tag de autenticação inválida'];
        }

        $validCategories = ['servidor', 'banco_dados', 'servico', 'email', 'api_key', 'outro'];
        if ($category !== null && !in_array($category, $validCategories, true)) {
            $category = 'outro';
        }

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO vault_items (user_id, title, category, encrypted_data, iv, auth_tag)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $title, $category, $encryptedData, $iv, $authTag]);

        $itemId = (int)$pdo->lastInsertId();

        AuditLog::log($userId, 'vault.create', $itemId);

        return ['success' => true, 'id' => $itemId];
    }

    public function readAll(): array
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return [];
        }

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, title, category, encrypted_data, iv, auth_tag, favorite, created_at, updated_at
             FROM vault_items WHERE user_id = ? ORDER BY updated_at DESC"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function read(int $itemId): ?array
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return null;
        }

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, title, category, encrypted_data, iv, auth_tag, favorite, created_at, updated_at
             FROM vault_items WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$itemId, $userId]);

        $item = $stmt->fetch();
        return $item ?: null;
    }

    public function update(int $itemId, string $title, ?string $category, string $encryptedData, string $iv, string $authTag): array
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return ['success' => false, 'error' => 'Não autenticado'];
        }

        $existing = $this->read($itemId);
        if (!$existing) {
            return ['success' => false, 'error' => 'Item não encontrado'];
        }

        $title = trim($title);
        if (empty($title) || strlen($title) > 255) {
            return ['success' => false, 'error' => 'Título inválido'];
        }

        $validCategories = ['servidor', 'banco_dados', 'servico', 'email', 'api_key', 'outro'];
        if ($category !== null && !in_array($category, $validCategories, true)) {
            $category = 'outro';
        }

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "UPDATE vault_items SET title = ?, category = ?, encrypted_data = ?, iv = ?, auth_tag = ?
             WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$title, $category, $encryptedData, $iv, $authTag, $itemId, $userId]);

        AuditLog::log($userId, 'vault.update', $itemId);

        return ['success' => true];
    }

    public function delete(int $itemId): array
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return ['success' => false, 'error' => 'Não autenticado'];
        }

        $existing = $this->read($itemId);
        if (!$existing) {
            return ['success' => false, 'error' => 'Item não encontrado'];
        }

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("DELETE FROM vault_items WHERE id = ? AND user_id = ?");
        $stmt->execute([$itemId, $userId]);

        AuditLog::log($userId, 'vault.delete', $itemId);

        return ['success' => true];
    }

    public function toggleFavorite(int $itemId): array
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return ['success' => false, 'error' => 'Não autenticado'];
        }

        $existing = $this->read($itemId);
        if (!$existing) {
            return ['success' => false, 'error' => 'Item não encontrado'];
        }

        $pdo = Database::getInstance()->getConnection();
        $newFav = $existing['favorite'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE vault_items SET favorite = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$newFav, $itemId, $userId]);

        return ['success' => true, 'favorite' => $newFav];
    }
}
