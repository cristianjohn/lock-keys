<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use LockKeys\Database;
use LockKeys\Session;
use LockKeys\SecurityHeaders;
use LockKeys\Csrf;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$registrationEnabled = filter_var($_ENV['REGISTRATION_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

SecurityHeaders::apply();
Session::start();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$uri = '/' . trim($uri, '/');

if ($uri === '/') {
    if (Session::isAuthenticated()) {
        header('Location: /vault');
    } else {
        header('Location: /login');
    }
    exit;
}

if ($uri === '/login') {
    if (Session::isAuthenticated()) {
        header('Location: /vault');
        exit;
    }
    $title = 'Entrar';
    require __DIR__ . '/../templates/login.php';
    exit;
}

if ($uri === '/register') {
    if (Session::isAuthenticated()) {
        header('Location: /vault');
        exit;
    }
    if (!$registrationEnabled) {
        header('Location: /login');
        exit;
    }
    $title = 'Criar Conta';
    require __DIR__ . '/../templates/login.php';
    exit;
}

if ($uri === '/vault') {
    if (!Session::isAuthenticated()) {
        header('Location: /login');
        exit;
    }
    $title = 'Cofre';
    require __DIR__ . '/../templates/vault.php';
    exit;
}

http_response_code(404);
echo 'Página não encontrada';
