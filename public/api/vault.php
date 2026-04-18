<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use LockKeys\Database;
use LockKeys\Session;
use LockKeys\SecurityHeaders;
use LockKeys\Csrf;
use LockKeys\Vault;

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

$method = $_SERVER['REQUEST_METHOD'];
$vault = new Vault();

if ($method === 'GET') {
    $items = $vault->readAll();
    echo json_encode(['success' => true, 'items' => $items]);
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
    $result = $vault->create(
        $input['title'] ?? '',
        $input['category'] ?? null,
        $input['encrypted_data'] ?? '',
        $input['iv'] ?? '',
        $input['auth_tag'] ?? ''
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
    $itemId = (int)($input['id'] ?? 0);
    $result = $vault->update(
        $itemId,
        $input['title'] ?? '',
        $input['category'] ?? null,
        $input['encrypted_data'] ?? '',
        $input['iv'] ?? '',
        $input['auth_tag'] ?? ''
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
    $itemId = (int)($input['id'] ?? 0);
    $result = $vault->delete($itemId);

    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['error']]);
    }
    exit;
}

if ($action === 'toggle_favorite') {
    $itemId = (int)($input['id'] ?? 0);
    $result = $vault->toggleFavorite($itemId);

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
