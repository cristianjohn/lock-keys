<?php

namespace LockKeys;

use LockKeys\Database;
use LockKeys\Session;
use LockKeys\AuditLog;

class Export
{
    public function exportAll(): array
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return [];
        }

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, title, category, encrypted_data, iv, auth_tag, created_at
             FROM vault_items WHERE user_id = ? ORDER BY title"
        );
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll();

        AuditLog::log($userId, 'vault.export');

        return $items;
    }
}
