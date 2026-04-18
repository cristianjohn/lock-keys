<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Senhas\Session;
use Senhas\SecurityHeaders;
use Senhas\Csrf;
use Senhas\Export;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

SecurityHeaders::apply();
Session::start();

if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$csrfToken = $input['csrf_token'] ?? '';

if (!Csrf::validate($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$export = new Export();
$items = $export->exportAll();

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="senhas-export-' . date('Y-m-d') . '.json"');

echo json_encode([
    'version' => 1,
    'exported_at' => date('c'),
    'items' => $items
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
