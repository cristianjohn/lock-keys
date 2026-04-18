<?php

namespace LockKeys;

use LockKeys\Database;
use LockKeys\Session;

class Category
{
    private function getUserId(): ?int
    {
        return Session::get('user_id');
    }

    public function readAll(): array
    {
        $userId = $this->getUserId();
        if (!$userId) return [];

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, name, slug, fields, sort_order
             FROM categories WHERE user_id = ? ORDER BY sort_order ASC, name ASC"
        );
        $stmt->execute([$userId]);
        $categories = $stmt->fetchAll();

        if (empty($categories)) {
            self::initDefaultsForUser($userId);
            $stmt->execute([$userId]);
            $categories = $stmt->fetchAll();
        }

        foreach ($categories as &$cat) {
            $cat['fields'] = json_decode($cat['fields'], true) ?? [];
        }

        return $categories;
    }

    public function create(string $name, string $slug, array $fields): array
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return ['success' => false, 'error' => 'Não autenticado'];
        }

        $name = trim($name);
        $slug = trim($slug);

        if (empty($name) || strlen($name) > 100) {
            return ['success' => false, 'error' => 'Nome inválido'];
        }

        if (empty($slug) || strlen($slug) > 100 || !preg_match('/^[a-z][a-z0-9_]*$/', $slug)) {
            return ['success' => false, 'error' => 'Slug inválido. Use apenas letras minúsculas, números e underscore, começando com letra.'];
        }

        $fields = $this->validateFields($fields);
        if ($fields === null) {
            return ['success' => false, 'error' => 'Campos inválidos'];
        }

        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND slug = ?");
        $stmt->execute([$userId, $slug]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Já existe uma categoria com esse slug'];
        }

        $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare(
            "INSERT INTO categories (user_id, name, slug, fields) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $name, $slug, $fieldsJson]);

        return ['success' => true, 'id' => (int)$pdo->lastInsertId()];
    }

    public function update(int $categoryId, string $name, string $slug, array $fields): array
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return ['success' => false, 'error' => 'Não autenticado'];
        }

        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare("SELECT id, slug FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);
        $existing = $stmt->fetch();
        if (!$existing) {
            return ['success' => false, 'error' => 'Categoria não encontrada'];
        }

        $name = trim($name);
        $slug = trim($slug);

        if (empty($name) || strlen($name) > 100) {
            return ['success' => false, 'error' => 'Nome inválido'];
        }

        if (empty($slug) || strlen($slug) > 100 || !preg_match('/^[a-z][a-z0-9_]*$/', $slug)) {
            return ['success' => false, 'error' => 'Slug inválido. Use apenas letras minúsculas, números e underscore, começando com letra.'];
        }

        $fields = $this->validateFields($fields);
        if ($fields === null) {
            return ['success' => false, 'error' => 'Campos inválidos'];
        }

        $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND slug = ? AND id != ?");
        $stmt->execute([$userId, $slug, $categoryId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Já existe outra categoria com esse slug'];
        }

        $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE);

        $pdo->beginTransaction();
        try {
            $oldSlug = $existing['slug'];
            if ($oldSlug !== $slug) {
                $stmt = $pdo->prepare("UPDATE vault_items SET category = ? WHERE user_id = ? AND category = ?");
                $stmt->execute([$slug, $userId, $oldSlug]);
            }

            $stmt = $pdo->prepare(
                "UPDATE categories SET name = ?, slug = ?, fields = ? WHERE id = ? AND user_id = ?"
            );
            $stmt->execute([$name, $slug, $fieldsJson, $categoryId, $userId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Erro ao atualizar categoria'];
        }

        return ['success' => true];
    }

    public function delete(int $categoryId): array
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return ['success' => false, 'error' => 'Não autenticado'];
        }

        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare("SELECT id, slug FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);
        $existing = $stmt->fetch();
        if (!$existing) {
            return ['success' => false, 'error' => 'Categoria não encontrada'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vault_items WHERE user_id = ? AND category = ?");
        $stmt->execute([$userId, $existing['slug']]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'error' => 'Esta categoria contém itens. Mova-os antes de excluir.'];
        }

        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);

        return ['success' => true];
    }

    public function reorder(array $order): array
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return ['success' => false, 'error' => 'Não autenticado'];
        }

        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND id = ?");
        foreach ($order as $id) {
            $stmt->execute([$userId, (int)$id]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'error' => 'Categoria não encontrada'];
            }
        }

        $stmt = $pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ? AND user_id = ?");
        foreach ($order as $i => $id) {
            $stmt->execute([$i, (int)$id, $userId]);
        }

        return ['success' => true];
    }

    public static function initDefaultsForUser(int $userId): void
    {
        $defaults = self::getDefaultCategories();
        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare(
            "INSERT INTO categories (user_id, name, slug, fields, sort_order) VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($defaults as $i => $cat) {
            $fieldsJson = json_encode($cat['fields'], JSON_UNESCAPED_UNICODE);
            $stmt->execute([$userId, $cat['name'], $cat['slug'], $fieldsJson, $i]);
        }
    }

    private static function getDefaultCategories(): array
    {
        return [
            [
                'name' => 'Servidor / VPS',
                'slug' => 'servidor',
                'fields' => [
                    ['name' => 'IP / Host', 'type' => 'text', 'key' => 'ip'],
                    ['name' => 'Usuário', 'type' => 'text', 'key' => 'username'],
                    ['name' => 'Senha', 'type' => 'password', 'key' => 'password'],
                    ['name' => 'Porta SSH', 'type' => 'text', 'key' => 'ssh_port'],
                    ['name' => 'Acesso Root', 'type' => 'text', 'key' => 'root_access'],
                ],
            ],
            [
                'name' => 'Banco de Dados',
                'slug' => 'banco_dados',
                'fields' => [
                    ['name' => 'Host', 'type' => 'text', 'key' => 'host'],
                    ['name' => 'Porta', 'type' => 'text', 'key' => 'port'],
                    ['name' => 'Banco de Dados', 'type' => 'text', 'key' => 'database'],
                    ['name' => 'Usuário', 'type' => 'text', 'key' => 'username'],
                    ['name' => 'Senha', 'type' => 'password', 'key' => 'password'],
                ],
            ],
            [
                'name' => 'Serviço',
                'slug' => 'servico',
                'fields' => [
                    ['name' => 'URL', 'type' => 'text', 'key' => 'url'],
                    ['name' => 'Usuário', 'type' => 'text', 'key' => 'username'],
                    ['name' => 'Senha', 'type' => 'password', 'key' => 'password'],
                ],
            ],
            [
                'name' => 'Email',
                'slug' => 'email',
                'fields' => [
                    ['name' => 'Email', 'type' => 'text', 'key' => 'email'],
                    ['name' => 'Senha', 'type' => 'password', 'key' => 'password'],
                    ['name' => 'Servidor IMAP', 'type' => 'text', 'key' => 'imap'],
                    ['name' => 'Servidor SMTP', 'type' => 'text', 'key' => 'smtp'],
                ],
            ],
            [
                'name' => 'API Key',
                'slug' => 'api_key',
                'fields' => [
                    ['name' => 'Chave', 'type' => 'password', 'key' => 'key'],
                    ['name' => 'URL', 'type' => 'text', 'key' => 'url'],
                ],
            ],
            [
                'name' => 'Outro',
                'slug' => 'outro',
                'fields' => [
                    ['name' => 'Campo 1', 'type' => 'text', 'key' => 'field1'],
                    ['name' => 'Campo 2', 'type' => 'password', 'key' => 'field2'],
                ],
            ],
        ];
    }

    private function validateFields(array $fields): ?array
    {
        if (count($fields) > 20) return null;

        $validated = [];
        foreach ($fields as $field) {
            if (!is_array($field)) return null;

            $name = trim($field['name'] ?? '');
            $type = $field['type'] ?? '';
            $key = trim($field['key'] ?? '');

            if (empty($name) || !in_array($type, ['text', 'password'], true)) return null;
            if (empty($key) || !preg_match('/^[a-z][a-z0-9_]*$/', $key)) return null;

            $validated[] = ['name' => $name, 'type' => $type, 'key' => $key];
        }

        return $validated;
    }
}
