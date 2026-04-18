# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Lock Keys (package: `cristianjohn/lock-keys`) is a self-hosted, zero-knowledge password manager built in PHP. All encryption/decryption happens client-side via the Web Crypto API — the server only ever stores encrypted blobs. The UI and README are written in both English and Portuguese.

## Commands

```bash
composer install          # Install dependencies (only vlucas/phpdotenv)
```

Database setup:
```bash
mysql -u root -p < database/schema.sql   # Create DB and tables
```

Generate APP_KEY:
```bash
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

There are no composer scripts, no test suite, and no build step. The web server document root must point to `public/`.

## Architecture

### Routing & Entry Point

`public/index.php` is the sole entry point and router. It matches URI paths (`/`, `/login`, `/register`, `/vault`) to templates. There is no framework — routing is done with simple `switch` on `$_SERVER['REQUEST_URI']`.

### API Layer

Three JSON API endpoints under `public/api/`:
- **auth.php** — register, login, logout
- **vault.php** — CRUD for encrypted vault items
- **export.php** — export vault data

All POST endpoints validate CSRF tokens and return JSON responses.

### Backend Classes (`src/`, PSR-4 namespace `LockKeys\`)

| Class | Responsibility |
|-------|---------------|
| `Database` | PDO singleton, reads from `.env` via phpdotenv |
| `Auth` | Register/login/logout, delegates to `Session` and `RateLimiter` |
| `Session` | Session management with IP + User-Agent fingerprinting |
| `Vault` | CRUD operations on `vault_items` table |
| `Csrf` | Double-submit cookie CSRF protection |
| `RateLimiter` | Tracks login attempts in `login_attempts` table |
| `AuditLog` | Logs all user actions to `audit_log` with IP/user-agent |
| `SecurityHeaders` | Sets CSP, HSTS, X-Frame-Options, etc. |
| `Export` | Exports vault items for download |

### Client-Side Encryption (`public/js/`)

- **crypto.js** — Web Crypto API wrappers: PBKDF2 key derivation (600k iterations), AES-256-GCM encrypt/decrypt, base64/hex utilities
- **app.js** — Auth flows, API calls, auto-lock, CSRF token management
- **vault.js** — Vault UI: CRUD, search, categories, favorites

### Templates (`templates/`)

PHP templates with a `layout.php` base that renders content blocks. `header.php`/`footer.php` partials inject CSRF meta tags and JS. All output is escaped with `htmlspecialchars()`.

### Database

MySQL/MariaDB via PDO with prepared statements. Four tables: `users`, `vault_items`, `audit_log`, `login_attempts`. Schema is in `database/schema.sql` — the database name is hardcoded as `make2_senhas` in the schema file but the `.env` `DB_DATABASE` setting is what the app uses at runtime.

### Environment Configuration

Copy `.env.example` to `.env`. Key variables: `APP_KEY`, `DB_*`, `SESSION_*`, `LOGIN_MAX_ATTEMPTS`, `LOGIN_LOCKOUT_MINUTES`.
