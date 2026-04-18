# Lock Keys

A secure, zero-knowledge password manager built with PHP.

---

## About

Lock Keys is a self-hosted password manager that keeps your credentials safe using client-side encryption. The server never sees your plain text passwords — all encryption and decryption happens in your browser using the Web Crypto API.

## Features

- **Zero-knowledge architecture** — passwords are encrypted/decrypted only on the client side
- **AES-GCM encryption** with PBKDF2 key derivation (600,000 iterations)
- **Category organization** — Servers, Databases, Services, Emails, API Keys, and more
- **Favorites** — quick access to your most-used credentials
- **Search** — find passwords fast
- **Export** — backup your vault data
- **Audit log** — track all actions with IP and user agent
- **Rate limiting** — brute-force protection (5 attempts, 15-min lockout)
- **CSRF protection**, security headers (CSP, HSTS, X-Frame-Options)
- **Dark theme** with responsive design

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 7.4+ (PSR-4 autoloading) |
| Database | MySQL / MariaDB (PDO) |
| Encryption | Web Crypto API (AES-GCM + PBKDF2) |
| Frontend | Vanilla JS, CSS |
| Dependencies | vlucas/phpdotenv |

## Requirements

- PHP 7.4+ with PDO MySQL extension
- MySQL 5.7+ or MariaDB 10.3+
- Apache (with mod_rewrite) or Nginx
- HTTPS recommended

## Installation

```bash
# 1. Clone the repository
git clone <repo-url> lock-keys
cd lock-keys

# 2. Install dependencies
composer install

# 3. Configure environment
cp .env.example .env
# Edit .env with your database credentials and app URL

# 4. Generate an app key
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
# Paste the result into APP_KEY in .env

# 5. Create the database
mysql -u root -p < database/schema.sql

# 6. Configure your web server
# Point the document root to the public/ directory
# Ensure mod_rewrite is enabled (Apache) or configure rewrite rules (Nginx)
```

## Project Structure

```
lock-keys/
├── src/Senhas/
│   ├── Auth.php              # Authentication logic
│   ├── Vault.php             # Password vault operations
│   ├── Database.php          # Database singleton (PDO)
│   ├── Session.php           # Session management
│   ├── Csrf.php              # CSRF protection
│   ├── SecurityHeaders.php   # Security headers
│   ├── RateLimiter.php       # Login attempt limiting
│   ├── AuditLog.php          # Audit logging
│   └── Export.php            # Data export
├── public/
│   ├── index.php             # Main router
│   ├── api/
│   │   ├── auth.php          # Authentication API
│   │   ├── vault.php         # Vault API
│   │   └── export.php        # Export API
│   ├── css/style.css         # Styles
│   └── js/
│       ├── app.js            # Main application
│       ├── crypto.js         # Client-side crypto
│       └── vault.js          # Vault UI logic
├── templates/
│   ├── layout.php            # Base template
│   ├── login.php             # Login/register
│   └── vault.php             # Vault interface
├── database/
│   └── schema.sql            # MySQL schema
├── .env.example              # Environment template
└── composer.json              # Dependencies & autoloading
```

## Security

| Measure | Detail |
|---------|--------|
| Encryption | AES-256-GCM for vault items |
| Key derivation | PBKDF2-SHA256 with 600,000 iterations |
| Zero-knowledge | Server only stores encrypted blobs — no plain text passwords |
| Session security | Fingerprinting, strict cookies, HTTPS enforcement |
| CSRF | Double-submit cookie pattern |
| Rate limiting | 5 login attempts per 15 minutes |
| Headers | CSP, HSTS, X-Frame-Options, X-Content-Type-Options |
| Audit | All user actions logged with IP and user agent |

---

## Sobre (Portugues)

O Lock Keys e um gerenciador de senhas auto-hospedado que mantem suas credenciais seguras usando criptografia no lado do cliente. O servidor nunca ve suas senhas em texto puro — toda criptografia e descriptografia acontece no navegador usando a Web Crypto API.

## Funcionalidades

- **Arquitetura zero-knowledge** — senhas sao criptografadas/descriptografadas apenas no cliente
- **Criptografia AES-GCM** com derivacao de chave PBKDF2 (600.000 iteracoes)
- **Organizacao por categorias** — Servidores, Bancos de Dados, Servicos, Emails, Chaves de API e mais
- **Favoritos** — acesso rapido as credenciais mais usadas
- **Busca** — encontre senhas rapidamente
- **Exportacao** — faca backup dos dados do cofre
- **Log de auditoria** — rastreie todas as acoes com IP e user agent
- **Limite de tentativas** — protecao contra forca bruta (5 tentativas, bloqueio de 15 min)
- **Protecao CSRF**, headers de seguranca (CSP, HSTS, X-Frame-Options)
- **Tema escuro** com design responsivo

## Tecnologias

| Camada | Tecnologia |
|--------|-----------|
| Backend | PHP 7.4+ (autoloading PSR-4) |
| Banco de dados | MySQL / MariaDB (PDO) |
| Criptografia | Web Crypto API (AES-GCM + PBKDF2) |
| Frontend | JS puro, CSS |
| Dependencias | vlucas/phpdotenv |

## Requisitos

- PHP 7.4+ com extensao PDO MySQL
- MySQL 5.7+ ou MariaDB 10.3+
- Apache (com mod_rewrite) ou Nginx
- HTTPS recomendado

## Instalacao

```bash
# 1. Clone o repositorio
git clone <repo-url> lock-keys
cd lock-keys

# 2. Instale as dependencias
composer install

# 3. Configure o ambiente
cp .env.example .env
# Edite o .env com suas credenciais do banco e URL da aplicacao

# 4. Gere uma chave da aplicacao
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
# Cole o resultado em APP_KEY no .env

# 5. Crie o banco de dados
mysql -u root -p < database/schema.sql

# 6. Configure o servidor web
# Aponte o document root para o diretorio public/
# Certifique-se de que o mod_rewrite esta habilitado (Apache) ou configure regras de reescrita (Nginx)
```

## Seguranca

| Medida | Detalhe |
|--------|---------|
| Criptografia | AES-256-GCM para itens do cofre |
| Derivacao de chave | PBKDF2-SHA256 com 600.000 iteracoes |
| Zero-knowledge | O servidor armazena apenas blobs criptografados — sem senhas em texto puro |
| Seguranca de sessao | Fingerprinting, cookies restritos, aplicacao de HTTPS |
| CSRF | Padrao double-submit cookie |
| Limite de tentativas | 5 tentativas de login a cada 15 minutos |
| Headers | CSP, HSTS, X-Frame-Options, X-Content-Type-Options |
| Auditoria | Todas as acoes do usuario sao registradas com IP e user agent |

## Licenca

Este projeto esta sob a licenca MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.
