<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use LockKeys\Database;
use LockKeys\Session;
use LockKeys\SecurityHeaders;
use LockKeys\Csrf;
use LockKeys\Category;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

SecurityHeaders::apply();
Session::start();

header('Content-Type: application/json; charset=utf-8');

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$category = new Category();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $categories = $category->readAll();
    echo json_encode(['success' => true, 'categories' => $categories]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

$csrfToken = $input['csrf_token'] ?? '';
if (!Csrf::validate($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$action = $input['action'] ?? '';

if ($action === 'create') {
    $result = $category->create(
        $input['name'] ?? '',
        $input['slug'] ?? '',
        $input['fields'] ?? []
    );

    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['error']]);
    }
    exit;
}

if ($action === 'update') {
    $result = $category->update(
        (int)($input['id'] ?? 0),
        $input['name'] ?? '',
        $input['slug'] ?? '',
        $input['fields'] ?? []
    );

    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['error']]);
    }
    exit;
}

if ($action === 'delete') {
    $result = $category->delete((int)($input['id'] ?? 0));

    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['error']]);
    }
    exit;
}

if ($action === 'reorder') {
    $result = $category->reorder($input['order'] ?? []);

    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['error']]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida']);
