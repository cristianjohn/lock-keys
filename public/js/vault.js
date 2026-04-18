const VaultUI = (() => {
    let items = [];
    let currentCategory = 'all';
    let searchQuery = '';
    let currentViewId = null;

    const CATEGORY_LABELS = {
        servidor: 'Servidor / VPS',
        banco_dados: 'Banco de Dados',
        servico: 'Serviço',
        email: 'Email',
        api_key: 'API Key',
        outro: 'Outro'
    };

    const CATEGORY_FIELDS = {
        servidor: [
            { name: 'IP / Host', type: 'text', key: 'ip' },
            { name: 'Usuário', type: 'text', key: 'username' },
            { name: 'Senha', type: 'password', key: 'password' },
            { name: 'Porta SSH', type: 'text', key: 'ssh_port' },
            { name: 'Acesso Root', type: 'text', key: 'root_access' }
        ],
        banco_dados: [
            { name: 'Host', type: 'text', key: 'host' },
            { name: 'Porta', type: 'text', key: 'port' },
            { name: 'Banco de Dados', type: 'text', key: 'database' },
            { name: 'Usuário', type: 'text', key: 'username' },
            { name: 'Senha', type: 'password', key: 'password' }
        ],
        servico: [
            { name: 'URL', type: 'text', key: 'url' },
            { name: 'Usuário', type: 'text', key: 'username' },
            { name: 'Senha', type: 'password', key: 'password' }
        ],
        email: [
            { name: 'Email', type: 'text', key: 'email' },
            { name: 'Senha', type: 'password', key: 'password' },
            { name: 'Servidor IMAP', type: 'text', key: 'imap' },
            { name: 'Servidor SMTP', type: 'text', key: 'smtp' }
        ],
        api_key: [
            { name: 'Chave', type: 'password', key: 'key' },
            { name: 'URL', type: 'text', key: 'url' }
        ],
        outro: [
            { name: 'Campo 1', type: 'text', key: 'field1' },
            { name: 'Campo 2', type: 'password', key: 'field2' }
        ]
    };

    async function loadItems() {
        try {
            const response = await fetch('/api/vault.php');
            const data = await response.json();
            if (data.success) {
                items = data.items;
                renderItems();
            }
        } catch (err) {
            App.showToast('Erro ao carregar itens', 'error');
        }
    }

    function getFilteredItems() {
        return items.filter(item => {
            const matchCategory = currentCategory === 'all' || item.category === currentCategory;
            const matchSearch = !searchQuery ||
                item.title.toLowerCase().includes(searchQuery.toLowerCase());
            return matchCategory && matchSearch;
        });
    }

    function renderItems() {
        const container = document.getElementById('vault-items');
        const emptyEl = document.getElementById('vault-empty');
        const countEl = document.getElementById('vault-count');
        if (!container) return;

        const filtered = getFilteredItems();

        if (countEl) {
            countEl.textContent = `${filtered.length} ${filtered.length === 1 ? 'item' : 'itens'}`;
        }

        if (filtered.length === 0) {
            container.innerHTML = '';
            const empty = document.createElement('div');
            empty.className = 'vault-empty';
            empty.innerHTML = searchQuery || currentCategory !== 'all'
                ? '<p>Nenhum item encontrado</p>'
                : '<p>Nenhum item no cofre</p><p>Clique em "+ Adicionar Item" para começar</p>';
            container.appendChild(empty);
            return;
        }

        container.innerHTML = '';

        for (const item of filtered) {
            const card = document.createElement('div');
            card.className = 'item-card';
            card.dataset.id = item.id;

            const categoryLabel = CATEGORY_LABELS[item.category] || item.category || 'Outro';

            card.innerHTML = `
                <div class="item-card-header">
                    <span class="item-card-title">${escapeHtml(item.title)}</span>
                    <span class="item-card-category">${escapeHtml(categoryLabel)}</span>
                </div>
                <div class="item-card-preview">
                    <span>${escapeHtml(categoryLabel)}</span>
                </div>
            `;

            card.addEventListener('click', () => viewItem(item.id));
            container.appendChild(card);
        }
    }

    async function viewItem(itemId) {
        const item = items.find(i => String(i.id) === String(itemId));
        if (!item) return;

        currentViewId = item.id;

        const masterKey = App.getMasterKey();
        if (!masterKey) {
            App.showToast('Sessão expirada', 'error');
            App.lockVault();
            return;
        }

        let decryptedData;
        try {
            const json = await Crypto.decryptData(item.encrypted_data, item.iv, item.auth_tag, masterKey);
            decryptedData = JSON.parse(json);
        } catch (err) {
            App.showToast('Erro ao descriptografar', 'error');
            return;
        }

        const modal = document.getElementById('view-modal');
        const title = document.getElementById('view-title');
        const category = document.getElementById('view-category');
        const fieldsContainer = document.getElementById('view-fields');
        const notesContainer = document.getElementById('view-notes');

        if (title) title.textContent = item.title;
        if (category) category.textContent = CATEGORY_LABELS[item.category] || 'Outro';

        if (fieldsContainer) {
            fieldsContainer.innerHTML = '';
            const fields = decryptedData.fields || [];
            for (const field of fields) {
                const row = document.createElement('div');
                row.className = 'view-field';
                const isPassword = field.type === 'password';
                row.innerHTML = `
                    <span class="view-field-label">${escapeHtml(field.name)}</span>
                    <span class="view-field-value ${isPassword ? 'masked' : ''}" data-value="${escapeAttr(field.value)}" data-masked="${isPassword}">${isPassword ? '••••••••' : escapeHtml(field.value)}</span>
                    <div class="view-field-actions">
                        ${isPassword ? '<button class="btn btn-secondary btn-sm btn-toggle-view">Mostrar</button>' : ''}
                        <button class="btn btn-secondary btn-sm btn-copy-field">Copiar</button>
                    </div>
                `;
                fieldsContainer.appendChild(row);
            }

            fieldsContainer.querySelectorAll('.btn-toggle-view').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const valueEl = e.target.closest('.view-field').querySelector('.view-field-value');
                    if (valueEl.dataset.masked === 'true') {
                        valueEl.textContent = valueEl.dataset.value;
                        valueEl.dataset.masked = 'false';
                        valueEl.classList.remove('masked');
                        e.target.textContent = 'Ocultar';
                    } else {
                        valueEl.textContent = '••••••••';
                        valueEl.dataset.masked = 'true';
                        valueEl.classList.add('masked');
                        e.target.textContent = 'Mostrar';
                    }
                });
            });

            fieldsContainer.querySelectorAll('.btn-copy-field').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const valueEl = e.target.closest('.view-field').querySelector('.view-field-value');
                    await copyToClipboard(valueEl.dataset.value);
                    App.showToast('Copiado!');
                });
            });
        }

        if (notesContainer) {
            notesContainer.textContent = decryptedData.notes || '';
            notesContainer.style.display = decryptedData.notes ? 'block' : 'none';
        }

        modal.style.display = 'flex';
    }

    function openItemForm(itemId = null) {
        const modal = document.getElementById('item-modal');
        const modalTitle = document.getElementById('modal-title');
        const form = document.getElementById('item-form');
        const fieldsContainer = document.getElementById('fields-container');
        const categorySelect = document.getElementById('item-category');

        form.reset();
        document.getElementById('item-id').value = '';
        fieldsContainer.innerHTML = '';

        if (itemId) {
            const item = items.find(i => String(i.id) === String(itemId));
            if (!item) return;

            modalTitle.textContent = 'Editar Item';
            document.getElementById('item-id').value = item.id;
            document.getElementById('item-title').value = item.title;
            categorySelect.value = item.category || 'outro';

            loadFieldsForCategory(item.category, item);
        } else {
            modalTitle.textContent = 'Novo Item';
            categorySelect.value = 'servidor';
            loadFieldsForCategory('servidor');
        }

        modal.style.display = 'flex';
    }

    function loadFieldsForCategory(category, existingItem = null) {
        const fieldsContainer = document.getElementById('fields-container');
        const fields = CATEGORY_FIELDS[category] || CATEGORY_FIELDS.outro;

        let existingData = null;
        if (existingItem) {
            try {
                const raw = existingItem._decrypted;
                if (raw) existingData = raw;
            } catch {}
        }

        fieldsContainer.innerHTML = '';
        for (const field of fields) {
            addFieldRow(field.name, field.type, existingData ? (existingData.fields?.find(f => f.key === field.key)?.value || '') : '');
        }
    }

    function addFieldRow(name = '', type = 'text', value = '') {
        const container = document.getElementById('fields-container');
        const row = document.createElement('div');
        row.className = 'field-row';
        row.innerHTML = `
            <input type="text" placeholder="Nome" value="${escapeAttr(name)}" class="field-name">
            <input type="${type}" placeholder="Valor" value="${escapeAttr(value)}" class="field-value" ${type === 'password' ? 'autocomplete="off"' : ''}>
            ${type === 'password' ? '<button type="button" class="btn-generate" title="Gerar senha">Gerar</button>' : ''}
            <button type="button" class="btn-remove-field">&times;</button>
        `;

        row.querySelector('.btn-remove-field').addEventListener('click', () => row.remove());

        const genBtn = row.querySelector('.btn-generate');
        if (genBtn) {
            genBtn.addEventListener('click', () => {
                const input = row.querySelector('.field-value');
                input.value = Crypto.generatePassword(20);
            });
        }

        container.appendChild(row);
    }

    function collectFormData() {
        const rows = document.querySelectorAll('#fields-container .field-row');
        const fields = [];
        rows.forEach(row => {
            const name = row.querySelector('.field-name').value.trim();
            const value = row.querySelector('.field-value').value;
            if (name) {
                fields.push({ name, value, type: row.querySelector('.field-value').type === 'password' ? 'password' : 'text' });
            }
        });
        return fields;
    }

    async function saveItem(e) {
        e.preventDefault();

        const masterKey = App.getMasterKey();
        if (!masterKey) {
            App.showToast('Sessão expirada', 'error');
            return;
        }

        const title = document.getElementById('item-title').value.trim();
        const category = document.getElementById('item-category').value;
        const fields = collectFormData();
        const notes = document.getElementById('item-notes').value;
        const itemId = document.getElementById('item-id').value;

        if (!title) {
            App.showToast('Informe um título', 'error');
            return;
        }

        const dataToEncrypt = JSON.stringify({ fields, notes });
        const encrypted = await Crypto.encryptData(dataToEncrypt, masterKey);

        const payload = {
            title,
            category,
            encrypted_data: encrypted.encrypted_data,
            iv: encrypted.iv,
            auth_tag: encrypted.auth_tag,
            csrf_token: App.getCSRFToken()
        };

        let result;
        if (itemId) {
            payload.action = 'update';
            payload.id = parseInt(itemId);
            result = await App.apiCall('/api/vault.php', payload);
        } else {
            payload.action = 'create';
            result = await App.apiCall('/api/vault.php', payload);
        }

        if (result.success) {
            App.showToast(itemId ? 'Item atualizado!' : 'Item criado!');
            closeModal('item-modal');
            await loadItems();
        } else {
            App.showToast(result.error || 'Erro ao salvar', 'error');
        }
    }

    async function deleteItem(itemId) {
        if (!confirm('Tem certeza que deseja excluir este item?')) return;
        if (!confirm('Esta ação não pode ser desfeita. Confirmar exclusão?')) return;

        const result = await App.apiCall('/api/vault.php', {
            action: 'delete',
            id: parseInt(itemId),
            csrf_token: App.getCSRFToken()
        });

        if (result.success) {
            App.showToast('Item excluído');
            closeModal('view-modal');
            await loadItems();
        } else {
            App.showToast(result.error || 'Erro ao excluir', 'error');
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'none';
        currentViewId = null;
    }

    async function copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
    }

    async function exportVault() {
        const masterKey = App.getMasterKey();
        if (!masterKey) {
            App.showToast('Sessão expirada', 'error');
            return;
        }

        const exportData = {
            version: 1,
            exported_at: new Date().toISOString(),
            items: items.map(item => ({
                title: item.title,
                category: item.category,
                encrypted_data: item.encrypted_data,
                iv: item.iv,
                auth_tag: item.auth_tag,
                created_at: item.created_at
            }))
        };

        const json = JSON.stringify(exportData, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `senhas-export-${new Date().toISOString().slice(0, 10)}.json`;
        a.click();
        URL.revokeObjectURL(url);

        App.showToast('Cofre exportado (dados permanecem criptografados)');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escapeAttr(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    async function init() {
        const email = document.getElementById('user-email');
        const sessionEmail = document.querySelector('meta[name="user-email"]');
        if (email && sessionEmail) {
            email.textContent = sessionEmail.getAttribute('content');
        }

        const hasKey = await App.initVaultSession();
        if (!hasKey) {
            window.location.href = '/login';
            return;
        }

        App.initAutoLock();
        await loadItems();

        // Category links
        document.querySelectorAll('.category-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelectorAll('.category-link').forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                currentCategory = link.dataset.category;
                renderItems();
            });
        });

        // Search
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                searchQuery = searchInput.value;
                renderItems();
            });
        }

        // Add item
        document.getElementById('btn-add-item')?.addEventListener('click', () => openItemForm());

        // Category change loads preset fields
        document.getElementById('item-category')?.addEventListener('change', (e) => {
            loadFieldsForCategory(e.target.value);
        });

        // Add custom field
        document.getElementById('btn-add-field')?.addEventListener('click', () => addFieldRow());

        // Save item
        document.getElementById('item-form')?.addEventListener('submit', saveItem);

        // Close modals
        document.getElementById('modal-close')?.addEventListener('click', () => closeModal('item-modal'));
        document.getElementById('btn-cancel')?.addEventListener('click', () => closeModal('item-modal'));
        document.getElementById('view-close')?.addEventListener('click', () => closeModal('view-modal'));

        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) closeModal(overlay.id);
            });
        });

        // View modal actions
        document.getElementById('btn-view-edit')?.addEventListener('click', async () => {
            if (currentViewId) {
                closeModal('view-modal');
                const item = items.find(i => String(i.id) === String(currentViewId));
                if (item) {
                    const masterKey = App.getMasterKey();
                    if (masterKey) {
                        try {
                            const json = await Crypto.decryptData(item.encrypted_data, item.iv, item.auth_tag, masterKey);
                            item._decrypted = JSON.parse(json);
                        } catch {}
                    }
                    openItemForm(currentViewId);
                }
            }
        });

        document.getElementById('btn-view-delete')?.addEventListener('click', () => {
            if (currentViewId) deleteItem(currentViewId);
        });

        // Export
        document.getElementById('btn-export')?.addEventListener('click', exportVault);

        // Escape key closes modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(m => {
                    if (m.style.display !== 'none') closeModal(m.id);
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', init);

    return { loadItems, renderItems };
})();
