<?php
use LockKeys\Csrf;

$composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
$appVersion = $composer['version'] ?? '1.0.0';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= Csrf::meta() ?>
    <title><?= htmlspecialchars($title ?? 'Lock Keys') ?> — Gerenciador de Senhas</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= $appVersion ?>">
</head>
<body>
    <?= $content ?? '' ?>
    <script src="/js/crypto.js?v=<?= $appVersion ?>"></script>
    <?php if (isset($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <script src="<?= htmlspecialchars($script) ?>?v=<?= $appVersion ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
