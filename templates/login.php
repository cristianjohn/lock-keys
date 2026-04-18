<?php
use LockKeys\Csrf;

$csrf = Csrf::getToken();
$title = 'Entrar';
ob_start();
?>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Lock Keys</h1>
            <p>Gerenciador de senhas seguro</p>
        </div>

        <div id="auth-forms">
            <!-- Login Form -->
            <form id="login-form" class="auth-form active">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" required autocomplete="email" placeholder="seu@email.com">
                </div>
                <div class="form-group">
                    <label for="login-password">Senha Mestra</label>
                    <div class="password-input">
                        <input type="password" id="login-password" name="password" required autocomplete="current-password" placeholder="Sua senha mestra">
                        <button type="button" class="toggle-password" data-target="login-password">👁</button>
                    </div>
                </div>
                <div id="login-error" class="error-message" style="display:none"></div>
                <button type="submit" class="btn btn-primary" id="login-btn">
                    <span class="btn-text">Entrar</span>
                    <span class="btn-loading" style="display:none">Derivando chave...</span>
                </button>
                <p class="auth-switch">Não tem conta? <a href="#" id="show-register">Criar conta</a></p>
            </form>

            <!-- Register Form -->
            <form id="register-form" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="register-email">Email</label>
                    <input type="email" id="register-email" name="email" required autocomplete="email" placeholder="seu@email.com">
                </div>
                <div class="form-group">
                    <label for="register-password">Senha Mestra</label>
                    <div class="password-input">
                        <input type="password" id="register-password" name="password" required autocomplete="new-password" placeholder="Mínimo 12 caracteres" minlength="12">
                        <button type="button" class="toggle-password" data-target="register-password">👁</button>
                    </div>
                    <div class="strength-meter">
                        <div class="strength-bar" id="strength-bar"></div>
                    </div>
                    <small id="strength-text"></small>
                </div>
                <div class="form-group">
                    <label for="register-confirm">Confirmar Senha Mestra</label>
                    <div class="password-input">
                        <input type="password" id="register-confirm" name="confirm_password" required autocomplete="new-password" placeholder="Repita a senha mestra">
                        <button type="button" class="toggle-password" data-target="register-confirm">👁</button>
                    </div>
                </div>
                <div id="register-error" class="error-message" style="display:none"></div>
                <button type="submit" class="btn btn-primary" id="register-btn">
                    <span class="btn-text">Criar Conta</span>
                    <span class="btn-loading" style="display:none">Derivando chave...</span>
                </button>
                <p class="auth-switch">Já tem conta? <a href="#" id="show-login">Entrar</a></p>
            </form>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$scripts = ['/js/app.js'];
require __DIR__ . '/layout.php';
