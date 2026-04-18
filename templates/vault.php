<?php
use LockKeys\Csrf;
use LockKeys\Session;

$csrf = Csrf::getToken();
$title = 'Cofre';
ob_start();
?>
<div class="vault-container">
    <header class="vault-header">
        <div class="vault-header-left">
            <button class="sidebar-toggle" id="sidebar-toggle">☰</button>
            <h1>Lock Keys</h1>
            <span class="vault-count" id="vault-count">0 itens</span>
        </div>
        <div class="vault-header-right">
            <span class="user-email" id="user-email"></span>
            <meta name="user-email" content="<?= htmlspecialchars(Session::get('user_email') ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <meta name="auto-logout-minutes" content="<?= htmlspecialchars($_ENV['AUTO_LOGOUT_MINUTES'] ?? '15') ?>">
            <button class="btn btn-secondary btn-sm" id="btn-logout">Sair</button>
        </div>
    </header>

    <div class="vault-body">
        <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
        <aside class="vault-sidebar">
            <button class="btn btn-primary btn-block" id="btn-add-item">+ Adicionar Item</button>
            <nav id="vault-categories" class="vault-categories"></nav>
            <div class="vault-actions">
                <button class="btn btn-secondary btn-sm btn-block" id="btn-manage-categories">Gerenciar Categorias</button>
                <button class="btn btn-secondary btn-sm btn-block" id="btn-import">Importar Cofre</button>
                <button class="btn btn-secondary btn-sm btn-block" id="btn-export">Exportar Cofre</button>
                <button class="btn btn-secondary btn-sm btn-block" id="btn-change-password">Alterar Senha Mestra</button>
            </div>
        </aside>

        <main class="vault-main">
            <div class="vault-search">
                <input type="text" id="search-input" placeholder="Buscar itens..." autocomplete="off">
            </div>
            <div class="vault-items" id="vault-items">
                <div class="vault-empty" id="vault-empty">
                    <p>Nenhum item no cofre</p>
                    <p>Clique em "+ Adicionar Item" para começar</p>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal de adicionar/editar item -->
<div class="modal-overlay" id="item-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modal-title">Novo Item</h2>
            <button class="modal-close" id="modal-close">&times;</button>
        </div>
        <form id="item-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" id="item-id" name="item_id" value="">
            <div class="form-group">
                <label for="item-title">Título</label>
                <input type="text" id="item-title" name="title" required placeholder="Ex: Servidor Produção" maxlength="255">
            </div>
            <div class="form-group">
                <label for="item-category">Categoria</label>
                <select id="item-category" name="category"></select>
            </div>
            <div class="form-group">
                <label>Campos</label>
                <div id="fields-container"></div>
                <button type="button" class="btn btn-secondary btn-sm" id="btn-add-field">+ Adicionar Campo</button>
            </div>
            <div class="form-group">
                <label for="item-notes">Notas</label>
                <textarea id="item-notes" name="notes" rows="3" placeholder="Notas opcionais..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btn-cancel">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btn-save">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de visualizar item -->
<div class="modal-overlay" id="view-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h2 id="view-title"></h2>
            <button class="modal-close" id="view-close">&times;</button>
        </div>
        <div class="view-category" id="view-category"></div>
        <div id="view-fields"></div>
        <div id="view-notes" class="view-notes"></div>
        <div class="modal-actions">
            <button class="btn btn-secondary" id="btn-view-edit">Editar</button>
            <button class="btn btn-danger" id="btn-view-delete">Excluir</button>
        </div>
    </div>
</div>

<!-- Modal de gerenciar categorias -->
<div class="modal-overlay" id="category-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h2 id="cat-modal-title">Gerenciar Categorias</h2>
            <button class="modal-close" id="cat-modal-close">&times;</button>
        </div>

        <!-- List section -->
        <div id="cat-list-section">
            <div class="modal-actions" style="margin-bottom:16px;">
                <button type="button" class="btn btn-primary btn-sm" id="cat-btn-add">+ Nova Categoria</button>
            </div>
            <div id="cat-list"></div>
        </div>

        <!-- Form section -->
        <div id="cat-form-section" style="display:none; padding: 24px;">
            <div class="form-group">
                <label for="cat-name">Nome</label>
                <input type="text" id="cat-name" placeholder="Ex: Servidor Cloud" maxlength="100">
            </div>
            <div class="form-group">
                <label for="cat-slug">Slug</label>
                <input type="text" id="cat-slug" placeholder="Ex: servidor_cloud" maxlength="100">
            </div>
            <div class="form-group">
                <label>Campos do Template</label>
                <div id="cat-fields-container"></div>
                <button type="button" class="btn btn-secondary btn-sm" id="cat-btn-add-field">+ Adicionar Campo</button>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="cat-btn-back">Voltar</button>
                <button type="button" class="btn btn-primary" id="cat-btn-save">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de importar cofre -->
<div class="modal-overlay" id="import-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h2>Importar Cofre</h2>
            <button class="modal-close" id="import-modal-close">&times;</button>
        </div>
        <div style="padding: 24px;">
            <div class="form-group">
                <label>Arquivo de exportação</label>
                <input type="file" id="import-file" accept=".json" style="padding:8px;">
            </div>
            <div id="import-preview" style="display:none;">
                <p id="import-file-info" style="font-size:13px; color:var(--text-secondary); margin-bottom:12px;"></p>
                <div class="error-message" style="background:rgba(255,152,0,.1); border-color:rgba(255,152,0,.3); color:#e67e22;">
                    Os itens importados continuam criptografados com a chave mestra original. Se você importar de uma conta diferente, os itens não poderão ser descriptografados.
                </div>
            </div>
            <div id="import-progress" style="display:none; margin-top:16px;">
                <p id="import-progress-text" style="font-size:13px; color:var(--text-secondary);"></p>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" id="btn-import-cancel">Cancelar</button>
            <button class="btn btn-primary" id="btn-import-confirm" disabled>Importar</button>
        </div>
    </div>
</div>

<!-- Modal de alterar senha mestra -->
<div class="modal-overlay" id="change-password-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h2>Alterar Senha Mestra</h2>
            <button class="modal-close" id="change-password-modal-close">&times;</button>
        </div>

        <!-- Tela A: Aviso -->
        <div id="change-password-warning" style="padding: 24px;">
            <div class="warning-box">
                <strong>Atenção:</strong> Alterar a senha mestra envolve recriptografar todos os itens do cofre.
                <br><br>
                <strong>Antes de continuar:</strong>
                <ul>
                    <li>Exporte seu cofre (botão "Exportar Cofre" no menu lateral)</li>
                    <li>Salve o arquivo de exportação em local seguro</li>
                </ul>
                <br>
                <strong>Se o processo falhar no meio:</strong>
                <ul>
                    <li>Faça login com sua <u>senha anterior</u></li>
                    <li>Limpe todos os itens do cofre</li>
                    <li>Importe o backup exportado</li>
                </ul>
                <br>
                Após a alteração, os itens exportados <strong>anteriormente</strong> não poderão ser importados com a nova senha — apenas o backup feito agora servirá para recuperação.
            </div>
            <div class="ack-row">
                <input type="checkbox" id="change-password-ack">
                <label for="change-password-ack">Eu fiz backup do meu cofre e entendo os riscos</label>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="btn-change-password-cancel">Cancelar</button>
                <button class="btn btn-primary" id="btn-change-password-continue" disabled>Continuar</button>
            </div>
        </div>

        <!-- Tela B: Formulario -->
        <div id="change-password-form-screen" style="display:none; padding: 24px;">
            <form id="change-password-form">
                <div class="form-group">
                    <label for="current-password">Senha Atual</label>
                    <input type="password" id="current-password" placeholder="Sua senha atual" required>
                </div>
                <div class="form-group">
                    <label for="new-password">Nova Senha</label>
                    <input type="password" id="new-password" placeholder="Mínimo 12 caracteres" required>
                    <div style="margin-top:6px;">
                        <div style="background:var(--bg-tertiary);border-radius:2px;height:4px;overflow:hidden;">
                            <div id="cp-strength-bar" style="height:100%;width:0;transition:var(--transition);"></div>
                        </div>
                        <span id="cp-strength-text" style="font-size:12px;"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm-new-password">Confirmar Nova Senha</label>
                    <input type="password" id="confirm-new-password" placeholder="Repita a nova senha" required>
                </div>
                <div class="error-message" id="change-password-error" style="display:none;"></div>
                <p id="change-password-progress" style="font-size:13px;color:var(--text-secondary);display:none;"></p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="btn-change-password-back">Voltar</button>
                    <button type="submit" class="btn btn-primary" id="btn-change-password-submit">
                        <span class="btn-text">Alterar Senha</span>
                        <span class="btn-loading" style="display:none;"><span class="spinner"></span> Alterando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$scripts = ['/js/app.js', '/js/vault.js'];
require __DIR__ . '/layout.php';
