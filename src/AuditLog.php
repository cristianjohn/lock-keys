<?php

namespace Senhas;

use Senhas\Database;

class AuditLog
{
    public static function log(
        ?int $userId,
        string $action,
        ?int $entityId = null,
        ?array $details = null
    ): void {
        $pdo = Database::getInstance()->getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $detailsJson = $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $pdo->prepare(
            "INSERT INTO audit_log (user_id, action, entity_id, ip_address, user_agent, details)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $action, $entityId, $ip, $ua, $detailsJson]);
    }
}
