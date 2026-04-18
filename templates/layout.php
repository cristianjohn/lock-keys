<?php
use LockKeys\Csrf;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= Csrf::meta() ?>
    <title><?= htmlspecialchars($title ?? 'Lock Keys') ?> — Gerenciador de Senhas</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?= $content ?? '' ?>
    <script src="/js/crypto.js"></script>
    <?php if (isset($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <script src="<?= htmlspecialchars($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
