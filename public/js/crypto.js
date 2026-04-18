const Crypto = (() => {
    const KDF_ITERATIONS = 600000;
    const PBKDF2_HASH = 'SHA-256';
    const AES_ALGO = 'AES-GCM';
    const KEY_LENGTH = 256;

    function arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    function base64ToArrayBuffer(base64) {
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }

    function arrayBufferToHex(buffer) {
        return Array.from(new Uint8Array(buffer))
            .map(b => b.toString(16).padStart(2, '0'))
            .join('');
    }

    function hexToArrayBuffer(hex) {
        const bytes = new Uint8Array(hex.match(/.{1,2}/g).map(byte => parseInt(byte, 16)));
        return bytes.buffer;
    }

    async function deriveMasterKey(password, email, iterations = KDF_ITERATIONS) {
        const encoder = new TextEncoder();
        const keyMaterial = await crypto.subtle.importKey(
            'raw',
            encoder.encode(password),
            { name: 'PBKDF2' },
            false,
            ['deriveBits', 'deriveKey']
        );

        const masterKey = await crypto.subtle.deriveKey(
            {
                name: 'PBKDF2',
                salt: encoder.encode(email),
                iterations: iterations,
                hash: PBKDF2_HASH
            },
            keyMaterial,
            { name: AES_ALGO, length: KEY_LENGTH },
            true,
            ['encrypt', 'decrypt']
        );

        return masterKey;
    }

    async function deriveAuthHash(masterKey, password) {
        const encoder = new TextEncoder();

        const rawKey = await crypto.subtle.exportKey('raw', masterKey);

        const keyMaterial = await crypto.subtle.importKey(
            'raw',
            rawKey,
            { name: 'PBKDF2' },
            false,
            ['deriveBits']
        );

        const bits = await crypto.subtle.deriveBits(
            {
                name: 'PBKDF2',
                salt: encoder.encode(password),
                iterations: 1,
                hash: PBKDF2_HASH
            },
            keyMaterial,
            KEY_LENGTH
        );

        return arrayBufferToHex(bits);
    }

    async function encryptData(plaintext, masterKey) {
        const encoder = new TextEncoder();
        const iv = crypto.getRandomValues(new Uint8Array(12));

        const encrypted = await crypto.subtle.encrypt(
            { name: AES_ALGO, iv: iv },
            masterKey,
            encoder.encode(plaintext)
        );

        const encryptedBytes = new Uint8Array(encrypted);
        const tag = encryptedBytes.slice(encryptedBytes.length - 16);
        const ciphertext = encryptedBytes.slice(0, encryptedBytes.length - 16);

        return {
            encrypted_data: arrayBufferToBase64(ciphertext.buffer),
            iv: arrayBufferToHex(iv.buffer),
            auth_tag: arrayBufferToHex(tag.buffer)
        };
    }

    async function decryptData(encryptedDataBase64, ivHex, tagHex, masterKey) {
        const ciphertext = new Uint8Array(base64ToArrayBuffer(encryptedDataBase64));
        const iv = new Uint8Array(hexToArrayBuffer(ivHex));
        const tag = new Uint8Array(hexToArrayBuffer(tagHex));

        const combined = new Uint8Array(ciphertext.length + tag.length);
        combined.set(ciphertext);
        combined.set(tag, ciphertext.length);

        const decrypted = await crypto.subtle.decrypt(
            { name: AES_ALGO, iv: iv },
            masterKey,
            combined.buffer
        );

        const decoder = new TextDecoder();
        return decoder.decode(decrypted);
    }

    function generatePassword(length = 20, options = {}) {
        const defaults = { uppercase: true, lowercase: true, numbers: true, symbols: true };
        const opts = { ...defaults, ...options };

        let chars = '';
        if (opts.uppercase) chars += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if (opts.lowercase) chars += 'abcdefghijklmnopqrstuvwxyz';
        if (opts.numbers)   chars += '0123456789';
        if (opts.symbols)   chars += '!@#$%^&*()_+-=[]{}|;:,.<>?';

        if (!chars) chars = 'abcdefghijklmnopqrstuvwxyz';

        const array = new Uint32Array(length);
        crypto.getRandomValues(array);
        return Array.from(array, x => chars[x % chars.length]).join('');
    }

    function calculatePasswordStrength(password) {
        if (!password) return { score: 0, label: '', color: '' };

        let score = 0;
        if (password.length >= 8)  score++;
        if (password.length >= 12) score++;
        if (password.length >= 16) score++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
        if (/\d/.test(password)) score++;
        if (/[^a-zA-Z0-9]/.test(password)) score++;

        score = Math.min(4, Math.floor(score * 4 / 6));

        const levels = [
            { label: 'Muito fraca', color: '#e74c5e' },
            { label: 'Fraca', color: '#ff9800' },
            { label: 'Razoável', color: '#ffc107' },
            { label: 'Forte', color: '#8bc34a' },
            { label: 'Muito forte', color: '#4caf50' }
        ];

        return { score, ...levels[score] };
    }

    return {
        deriveMasterKey,
        deriveAuthHash,
        encryptData,
        decryptData,
        generatePassword,
        calculatePasswordStrength,
        arrayBufferToBase64,
        base64ToArrayBuffer,
        arrayBufferToHex,
        hexToArrayBuffer
    };
})();
