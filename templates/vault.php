<?php
use LockKeys\Csrf;

$csrf = Csrf::getToken();
$title = 'Cofre';
ob_start();
?>
<div class="vault-container">
    <header class="vault-header">
        <div class="vault-header-left">
            <h1>Lock Keys</h1>
            <span class="vault-count" id="vault-count">0 itens</span>
        </div>
        <div class="vault-header-right">
            <span class="user-email" id="user-email"></span>
            <button class="btn btn-secondary btn-sm" id="btn-lock">Bloquear</button>
            <button class="btn btn-secondary btn-sm" id="btn-logout">Sair</button>
        </div>
    </header>

    <div class="vault-body">
        <aside class="vault-sidebar">
            <button class="btn btn-primary btn-block" id="btn-add-item">+ Adicionar Item</button>
            <nav id="vault-categories" class="vault-categories"></nav>
            <div class="vault-actions">
                <button class="btn btn-secondary btn-sm btn-block" id="btn-manage-categories">Gerenciar Categorias</button>
                <button class="btn btn-secondary btn-sm btn-block" id="btn-export">Exportar Cofre</button>
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
<?php
$content = ob_get_clean();
$scripts = ['/js/app.js', '/js/vault.js'];
require __DIR__ . '/layout.php';
