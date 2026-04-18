<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use LockKeys\Database;
use LockKeys\Category;

echo "=== Migration 20260418: Create categories table ===\n\n";

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$pdo = Database::getInstance()->getConnection();

$pdo->exec("CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED    NOT NULL,
    name        VARCHAR(100)    NOT NULL,
    slug        VARCHAR(100)    NOT NULL,
    fields      JSON            NOT NULL,
    sort_order  INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_slug (user_id, slug),
    INDEX idx_categories_user (user_id),
    CONSTRAINT fk_categories_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB");

echo "Tabela 'categories' criada/verificada.\n\n";

$users = $pdo->query("SELECT id FROM users")->fetchAll();
echo "Encontrados " . count($users) . " usuário(s).\n";

foreach ($users as $user) {
    $userId = (int)$user['id'];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE user_id = ?");
    $stmt->execute([$userId]);
    $count = (int)$stmt->fetchColumn();

    if ($count > 0) {
        echo "  Usuário {$userId}: já possui {$count} categoria(s) — ignorando.\n";
        continue;
    }

    Category::initDefaultsForUser($userId);
    echo "  Usuário {$userId}: categorias padrão criadas.\n";
}

echo "\nMigration concluída.\n";
