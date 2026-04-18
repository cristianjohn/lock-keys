<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Senhas\Database;
use Senhas\Session;
use Senhas\SecurityHeaders;
use Senhas\Csrf;
use Senhas\Auth;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

SecurityHeaders::apply();
Session::start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

$action = $input['action'] ?? '';

if ($action === 'register') {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!Csrf::validate($csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido']);
        exit;
    }

    $email = $input['email'] ?? '';
    $authHash = $input['auth_hash'] ?? '';
    $salt = $input['salt'] ?? '';
    $kdfIterations = (int)($input['kdf_iterations'] ?? 600000);

    $auth = new Auth();
    $result = $auth->register($email, $authHash, $salt, $kdfIterations);

    if ($result['success']) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['error']]);
    }
    exit;
}

if ($action === 'login') {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!Csrf::validate($csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido']);
        exit;
    }

    $email = $input['email'] ?? '';
    $authHash = $input['auth_hash'] ?? '';

    $auth = new Auth();
    $result = $auth->login($email, $authHash);

    if ($result['success']) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => $result['error']]);
    }
    exit;
}

if ($action === 'logout') {
    $auth = new Auth();
    $auth->logout();
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida']);
