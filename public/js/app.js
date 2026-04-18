const App = (() => {
    let masterKey = null;
    let autoLogoutTimer = null;

    function getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    async function apiCall(endpoint, data) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return response.json();
    }

    function showError(elementId, message) {
        const el = document.getElementById(elementId);
        if (el) {
            el.textContent = message;
            el.style.display = 'block';
        }
    }

    function hideError(elementId) {
        const el = document.getElementById(elementId);
        if (el) el.style.display = 'none';
    }

    function setLoading(btnId, loading) {
        const btn = document.getElementById(btnId);
        if (!btn) return;
        const text = btn.querySelector('.btn-text');
        const spinner = btn.querySelector('.btn-loading');
        btn.disabled = loading;
        if (text) text.style.display = loading ? 'none' : 'inline';
        if (spinner) spinner.style.display = loading ? 'inline-flex' : 'none';
    }

    function initAuthForms() {
        const showRegister = document.getElementById('show-register');
        const showLogin = document.getElementById('show-login');
        const loginForm = document.getElementById('login-form');
        const registerForm = document.getElementById('register-form');

        if (showRegister) {
            showRegister.addEventListener('click', (e) => {
                e.preventDefault();
                loginForm.classList.remove('active');
                registerForm.classList.add('active');
            });
        }

        if (showLogin) {
            showLogin.addEventListener('click', (e) => {
                e.preventDefault();
                registerForm.classList.remove('active');
                loginForm.classList.add('active');
            });
        }

        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                if (input) {
                    input.type = input.type === 'password' ? 'text' : 'password';
                }
            });
        });

        const regPassword = document.getElementById('register-password');
        if (regPassword) {
            regPassword.addEventListener('input', () => {
                const result = Crypto.calculatePasswordStrength(regPassword.value);
                const bar = document.getElementById('strength-bar');
                const text = document.getElementById('strength-text');
                if (bar) {
                    bar.style.width = ((result.score + 1) * 20) + '%';
                    bar.style.backgroundColor = result.color;
                }
                if (text) {
                    text.textContent = result.label;
                    text.style.color = result.color;
                }
            });
        }
    }

    async function handleLogin(e) {
        e.preventDefault();
        hideError('login-error');
        setLoading('login-btn', true);

        const email = document.getElementById('login-email').value.trim().toLowerCase();
        const password = document.getElementById('login-password').value;

        if (!email || !password) {
            showError('login-error', 'Preencha todos os campos');
            setLoading('login-btn', false);
            return;
        }

        try {
            const key = await Crypto.deriveMasterKey(password, email);
            const authHash = await Crypto.deriveAuthHash(key, password);

            const result = await apiCall('/api/auth.php', {
                action: 'login',
                email: email,
                auth_hash: authHash,
                csrf_token: getCSRFToken()
            });

            if (result.success) {
                masterKey = key;
                sessionStorage.setItem('master_key_material', await exportMasterKey(key));
                window.location.href = '/vault';
            } else {
                showError('login-error', result.error || 'Erro ao fazer login');
            }
        } catch (err) {
            showError('login-error', 'Erro interno. Tente novamente.');
            console.error('Login error:', err);
        }

        setLoading('login-btn', false);
    }

    async function handleRegister(e) {
        e.preventDefault();
        hideError('register-error');

        const email = document.getElementById('register-email').value.trim().toLowerCase();
        const password = document.getElementById('register-password').value;
        const confirm = document.getElementById('register-confirm').value;

        if (!email || !password || !confirm) {
            showError('register-error', 'Preencha todos os campos');
            return;
        }

        if (password.length < 12) {
            showError('register-error', 'A senha mestra deve ter no mínimo 12 caracteres');
            return;
        }

        if (password !== confirm) {
            showError('register-error', 'As senhas não coincidem');
            return;
        }

        const strength = Crypto.calculatePasswordStrength(password);
        if (strength.score < 2) {
            showError('register-error', 'A senha é muito fraca. Use letras maiúsculas, minúsculas, números e símbolos.');
            return;
        }

        setLoading('register-btn', true);

        try {
            const key = await Crypto.deriveMasterKey(password, email);
            const authHash = await Crypto.deriveAuthHash(key, password);
            const salt = Crypto.arrayBufferToHex(await crypto.subtle.exportKey('raw', key)).substring(0, 64);

            const result = await apiCall('/api/auth.php', {
                action: 'register',
                email: email,
                auth_hash: authHash,
                salt: salt,
                kdf_iterations: 600000,
                csrf_token: getCSRFToken()
            });

            if (result.success) {
                masterKey = key;
                sessionStorage.setItem('master_key_material', await exportMasterKey(key));
                window.location.href = '/vault';
            } else {
                showError('register-error', result.error || 'Erro ao criar conta');
            }
        } catch (err) {
            showError('register-error', 'Erro interno. Tente novamente.');
            console.error('Register error:', err);
        }

        setLoading('register-btn', false);
    }

    async function exportMasterKey(key) {
        const raw = await crypto.subtle.exportKey('raw', key);
        return Crypto.arrayBufferToBase64(raw);
    }

    async function importMasterKey(base64) {
        const raw = Crypto.base64ToArrayBuffer(base64);
        return await crypto.subtle.importKey(
            'raw',
            raw,
            { name: 'AES-GCM', length: 256 },
            false,
            ['encrypt', 'decrypt']
        );
    }

    function getMasterKey() {
        return masterKey;
    }

    async function initVaultSession() {
        const keyMaterial = sessionStorage.getItem('master_key_material');
        if (keyMaterial) {
            try {
                masterKey = await importMasterKey(keyMaterial);
                resetAutoLogout();
                return true;
            } catch {
                sessionStorage.removeItem('master_key_material');
                return false;
            }
        }
        return false;
    }

    async function logout() {
        await apiCall('/api/auth.php', { action: 'logout' });
        masterKey = null;
        sessionStorage.removeItem('master_key_material');
        window.location.href = '/login';
    }

    function getAutoLogoutMinutes() {
        const meta = document.querySelector('meta[name="auto-logout-minutes"]');
        return parseInt(meta?.content, 10) || 15;
    }

    function resetAutoLogout() {
        clearTimeout(autoLogoutTimer);
        autoLogoutTimer = setTimeout(() => {
            logout();
        }, getAutoLogoutMinutes() * 60 * 1000);
    }

    function initAutoLogout() {
        ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(evt => {
            document.addEventListener(evt, () => {
                if (masterKey) resetAutoLogout();
            }, { passive: true });
        });
        resetAutoLogout();
    }

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    function init() {
        initAuthForms();

        const loginForm = document.getElementById('login-form');
        const registerForm = document.getElementById('register-form');

        if (loginForm) loginForm.addEventListener('submit', handleLogin);
        if (registerForm) registerForm.addEventListener('submit', handleRegister);

        const btnLogout = document.getElementById('btn-logout');

        if (btnLogout) btnLogout.addEventListener('click', logout);
    }

    document.addEventListener('DOMContentLoaded', init);

    return { getMasterKey, initVaultSession, logout, showToast, getCSRFToken, apiCall, initAutoLogout };
})();
