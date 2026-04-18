const VaultUI = (() => {
    let items = [];
    let categories = [];
    let currentCategory = 'all';
    let searchQuery = '';
    let currentViewId = null;

    function getCategoryLabel(slug) {
        if (!slug) return 'Outro';
        const cat = categories.find(c => c.slug === slug);
        return cat ? cat.name : slug;
    }

    function getCategoryFields(slug) {
        if (!slug) return [{ name: 'Campo 1', type: 'text', key: 'field1' }, { name: 'Campo 2', type: 'password', key: 'field2' }];
        const cat = categories.find(c => c.slug === slug);
        if (cat && cat.fields && cat.fields.length > 0) return cat.fields;
        return [{ name: 'Campo 1', type: 'text', key: 'field1' }, { name: 'Campo 2', type: 'password', key: 'field2' }];
    }

    async function loadCategories() {
        try {
            const response = await fetch('/api/category.php');
            const data = await response.json();
            if (data.success) {
                categories = data.categories;
                renderSidebar();
                renderCategorySelect();
            }
        } catch (err) {
            App.showToast('Erro ao carregar categorias', 'error');
        }
    }

    function renderSidebar() {
        const nav = document.getElementById('vault-categories');
        if (!nav) return;

        let html = '<a href="#" class="category-link' + (currentCategory === 'all' ? ' active' : '') + '" data-category="all">Todos</a>';
        for (const cat of categories) {
            html += '<a href="#" class="category-link' + (currentCategory === cat.slug ? ' active' : '') + '" data-category="' + escapeAttr(cat.slug) + '">' + escapeHtml(cat.name) + '</a>';
        }
        nav.innerHTML = html;
    }

    function renderCategorySelect() {
        const select = document.getElementById('item-category');
        if (!select) return;

        const currentValue = select.value;
        let html = '';
        for (const cat of categories) {
            html += '<option value="' + escapeAttr(cat.slug) + '">' + escapeHtml(cat.name) + '</option>';
        }
        select.innerHTML = html;

        if (currentValue && categories.find(c => c.slug === currentValue)) {
            select.value = currentValue;
        }
    }

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

            const categoryLabel = getCategoryLabel(item.category);

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
            App.logout();
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
        if (category) category.textContent = getCategoryLabel(item.category);

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

        renderCategorySelect();

        if (itemId) {
            const item = items.find(i => String(i.id) === String(itemId));
            if (!item) return;

            modalTitle.textContent = 'Editar Item';
            document.getElementById('item-id').value = item.id;
            document.getElementById('item-title').value = item.title;
            categorySelect.value = item.category || '';

            if (item._decrypted) {
                document.getElementById('item-notes').value = item._decrypted.notes || '';
            }

            loadFieldsForCategory(item.category, item);
        } else {
            modalTitle.textContent = 'Novo Item';
            if (categories.length > 0) {
                categorySelect.value = categories[0].slug;
                loadFieldsForCategory(categories[0].slug);
            }
        }

        modal.style.display = 'flex';
    }

    function loadFieldsForCategory(category, existingItem = null) {
        const fieldsContainer = document.getElementById('fields-container');
        const fields = getCategoryFields(category);

        let existingData = null;
        if (existingItem) {
            try {
                const raw = existingItem._decrypted;
                if (raw) existingData = raw;
            } catch {}
        }

        fieldsContainer.innerHTML = '';
        if (existingData && existingData.fields && existingData.fields.length > 0) {
            for (const savedField of existingData.fields) {
                const matched = fields.find(f => f.name === savedField.name);
                addFieldRow(savedField.name, matched?.type || savedField.type || 'text', savedField.value || '');
            }
        } else {
            for (const field of fields) {
                addFieldRow(field.name, field.type, '');
            }
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
        if (modalId === 'view-modal') currentViewId = null;
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

    // --- Import Vault ---

    let importData = null;

    function openImportModal() {
        importData = null;
        const fileInput = document.getElementById('import-file');
        const preview = document.getElementById('import-preview');
        const progress = document.getElementById('import-progress');
        const confirmBtn = document.getElementById('btn-import-confirm');

        if (fileInput) fileInput.value = '';
        if (preview) preview.style.display = 'none';
        if (progress) progress.style.display = 'none';
        if (confirmBtn) confirmBtn.disabled = true;
        document.getElementById('import-modal').style.display = 'flex';
    }

    function handleImportFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;

        const preview = document.getElementById('import-preview');
        const fileInfo = document.getElementById('import-file-info');
        const confirmBtn = document.getElementById('btn-import-confirm');

        if (!file.name.endsWith('.json')) {
            App.showToast('Selecione um arquivo JSON', 'error');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(event) {
            try {
                const data = JSON.parse(event.target.result);

                if (!data.version || data.version !== 1 || !Array.isArray(data.items)) {
                    App.showToast('Arquivo de exportação inválido', 'error');
                    return;
                }

                if (data.items.length === 0) {
                    App.showToast('O arquivo não contém itens', 'error');
                    return;
                }

                for (const item of data.items) {
                    if (!item.title || !item.encrypted_data || !item.iv || !item.auth_tag) {
                        App.showToast('Arquivo contém itens com dados incompletos', 'error');
                        return;
                    }
                }

                importData = data;
                fileInfo.textContent = file.name + ' — ' + data.items.length + ' item(ns) encontrado(s)';
                preview.style.display = 'block';
                confirmBtn.disabled = false;
            } catch (err) {
                App.showToast('Erro ao ler o arquivo JSON', 'error');
            }
        };
        reader.readAsText(file);
    }

    async function executeImport() {
        if (!importData || !importData.items || importData.items.length === 0) return;

        const confirmBtn = document.getElementById('btn-import-confirm');
        const progressEl = document.getElementById('import-progress');
        const progressText = document.getElementById('import-progress-text');

        confirmBtn.disabled = true;
        progressEl.style.display = 'block';

        let imported = 0;
        let failed = 0;
        const total = importData.items.length;

        for (let i = 0; i < total; i++) {
            const item = importData.items[i];
            progressText.textContent = 'Importando ' + (i + 1) + ' de ' + total + '...';

            try {
                const result = await App.apiCall('/api/vault.php', {
                    action: 'create',
                    title: item.title,
                    category: item.category || null,
                    encrypted_data: item.encrypted_data,
                    iv: item.iv,
                    auth_tag: item.auth_tag,
                    csrf_token: App.getCSRFToken()
                });

                if (result.success) {
                    imported++;
                } else {
                    failed++;
                }
            } catch (err) {
                failed++;
            }
        }

        progressText.textContent = 'Concluído: ' + imported + ' importado(s), ' + failed + ' falha(s).';

        if (imported > 0) {
            await loadItems();
            App.showToast(imported + ' item(ns) importado(s) com sucesso');
        }

        if (failed > 0) {
            App.showToast(failed + ' item(ns) falharam na importação', 'error');
        }

        setTimeout(() => {
            closeModal('import-modal');
        }, 1500);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escapeAttr(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // --- Category Management ---

    let editingCategoryId = null;

    function openCategoryModal() {
        editingCategoryId = null;
        const modal = document.getElementById('category-modal');
        document.getElementById('cat-modal-title').textContent = 'Gerenciar Categorias';
        document.getElementById('cat-form-section').style.display = 'none';
        document.getElementById('cat-list-section').style.display = 'block';
        renderCategoryList();
        modal.style.display = 'flex';
    }

    function renderCategoryList() {
        const listEl = document.getElementById('cat-list');
        if (!listEl) return;

        if (categories.length === 0) {
            listEl.innerHTML = '<p style="text-align:center;color:var(--text-muted);">Nenhuma categoria</p>';
            return;
        }

        listEl.innerHTML = '';
        for (const cat of categories) {
            const item = document.createElement('div');
            item.className = 'category-list-item';
            item.innerHTML = `
                <div class="cat-info">
                    <span class="cat-name">${escapeHtml(cat.name)}</span>
                    <span class="cat-slug">${escapeHtml(cat.slug)} — ${cat.fields.length} campo(s)</span>
                </div>
                <div class="cat-actions">
                    <button class="btn btn-secondary btn-sm cat-edit" data-id="${cat.id}">Editar</button>
                    <button class="btn btn-danger btn-sm cat-delete" data-id="${cat.id}">Excluir</button>
                </div>
            `;
            listEl.appendChild(item);
        }

        listEl.querySelectorAll('.cat-edit').forEach(btn => {
            btn.addEventListener('click', () => {
                const cat = categories.find(c => c.id === parseInt(btn.dataset.id));
                if (cat) editCategory(cat);
            });
        });

        listEl.querySelectorAll('.cat-delete').forEach(btn => {
            btn.addEventListener('click', async () => {
                const cat = categories.find(c => c.id === parseInt(btn.dataset.id));
                if (!cat) return;
                if (!confirm(`Excluir categoria "${cat.name}"?`)) return;

                const result = await App.apiCall('/api/category.php', {
                    action: 'delete',
                    id: cat.id,
                    csrf_token: App.getCSRFToken()
                });

                if (result.success) {
                    App.showToast('Categoria excluída');
                    await loadCategories();
                    renderCategoryList();
                } else {
                    App.showToast(result.error || 'Erro ao excluir', 'error');
                }
            });
        });
    }

    function editCategory(cat) {
        editingCategoryId = cat.id;
        document.getElementById('cat-modal-title').textContent = 'Editar Categoria';
        document.getElementById('cat-list-section').style.display = 'none';
        document.getElementById('cat-form-section').style.display = 'block';
        document.getElementById('cat-name').value = cat.name;
        document.getElementById('cat-slug').value = cat.slug;
        renderCatFieldRows(cat.fields);
    }

    function showCategoryCreateForm() {
        editingCategoryId = null;
        document.getElementById('cat-modal-title').textContent = 'Nova Categoria';
        document.getElementById('cat-list-section').style.display = 'none';
        document.getElementById('cat-form-section').style.display = 'block';
        document.getElementById('cat-name').value = '';
        document.getElementById('cat-slug').value = '';
        renderCatFieldRows([
            { name: 'Usuário', type: 'text', key: 'username' },
            { name: 'Senha', type: 'password', key: 'password' }
        ]);
    }

    function renderCatFieldRows(fields) {
        const container = document.getElementById('cat-fields-container');
        container.innerHTML = '';
        for (const field of fields) {
            addCatFieldRow(field.name, field.type, field.key);
        }
    }

    function addCatFieldRow(name = '', type = 'text', key = '') {
        const container = document.getElementById('cat-fields-container');
        const row = document.createElement('div');
        row.className = 'field-row';
        row.innerHTML = `
            <input type="text" placeholder="Nome" value="${escapeAttr(name)}" class="field-name" style="flex:2">
            <select class="field-type-select" style="flex:1">
                <option value="text" ${type === 'text' ? 'selected' : ''}>Texto</option>
                <option value="password" ${type === 'password' ? 'selected' : ''}>Senha</option>
            </select>
            <input type="text" placeholder="key" value="${escapeAttr(key)}" class="field-key" style="flex:1">
            <button type="button" class="btn-remove-field">&times;</button>
        `;
        row.querySelector('.btn-remove-field').addEventListener('click', () => row.remove());
        container.appendChild(row);
    }

    function collectCatFields() {
        const rows = document.querySelectorAll('#cat-fields-container .field-row');
        const fields = [];
        rows.forEach(row => {
            const name = row.querySelector('.field-name').value.trim();
            const type = row.querySelector('.field-type-select').value;
            const key = row.querySelector('.field-key').value.trim();
            if (name && key) {
                fields.push({ name, type, key });
            }
        });
        return fields;
    }

    function slugify(text) {
        return text.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9_]/g, '_')
            .replace(/_+/g, '_')
            .replace(/^_|_$/g, '')
            .substring(0, 100);
    }

    async function saveCategory() {
        const name = document.getElementById('cat-name').value.trim();
        const slug = document.getElementById('cat-slug').value.trim();
        const fields = collectCatFields();

        if (!name) {
            App.showToast('Informe o nome da categoria', 'error');
            return;
        }
        if (!slug) {
            App.showToast('Informe o slug', 'error');
            return;
        }
        if (fields.length === 0) {
            App.showToast('Adicione pelo menos um campo', 'error');
            return;
        }

        const payload = {
            name,
            slug,
            fields,
            csrf_token: App.getCSRFToken()
        };

        let result;
        if (editingCategoryId) {
            payload.action = 'update';
            payload.id = editingCategoryId;
            result = await App.apiCall('/api/category.php', payload);
        } else {
            payload.action = 'create';
            result = await App.apiCall('/api/category.php', payload);
        }

        if (result.success) {
            App.showToast(editingCategoryId ? 'Categoria atualizada!' : 'Categoria criada!');
            await loadCategories();
            openCategoryModal();
        } else {
            App.showToast(result.error || 'Erro ao salvar', 'error');
        }
    }

    // --- Change Password ---

    function openChangePasswordModal() {
        const form = document.getElementById('change-password-form');
        if (form) form.reset();
        const err = document.getElementById('change-password-error');
        if (err) err.style.display = 'none';
        const progress = document.getElementById('change-password-progress');
        if (progress) progress.style.display = 'none';
        const ack = document.getElementById('change-password-ack');
        if (ack) ack.checked = false;
        const continueBtn = document.getElementById('btn-change-password-continue');
        if (continueBtn) continueBtn.disabled = true;

        document.getElementById('change-password-warning').style.display = 'block';
        document.getElementById('change-password-form-screen').style.display = 'none';

        const modal = document.getElementById('change-password-modal');
        if (modal) modal.style.display = 'flex';
    }

    async function handlePasswordChangeSubmit(e) {
        e.preventDefault();

        const errEl = document.getElementById('change-password-error');
        const progressEl = document.getElementById('change-password-progress');
        const submitBtn = document.getElementById('btn-change-password-submit');

        function showError(msg) {
            if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
        }
        function hideError() { if (errEl) errEl.style.display = 'none'; }
        function setLoading(loading) {
            const text = submitBtn?.querySelector('.btn-text');
            const spinner = submitBtn?.querySelector('.btn-loading');
            if (submitBtn) submitBtn.disabled = loading;
            if (text) text.style.display = loading ? 'none' : 'inline';
            if (spinner) spinner.style.display = loading ? 'inline-flex' : 'none';
        }

        hideError();

        const currentPassword = document.getElementById('current-password').value;
        const newPassword = document.getElementById('new-password').value;
        const confirmPassword = document.getElementById('confirm-new-password').value;

        if (!currentPassword || !newPassword || !confirmPassword) {
            showError('Preencha todos os campos');
            return;
        }

        if (newPassword.length < 12) {
            showError('A nova senha deve ter no mínimo 12 caracteres');
            return;
        }

        const strength = Crypto.calculatePasswordStrength(newPassword);
        if (strength.score < 2) {
            showError('A nova senha é muito fraca. Use letras maiúsculas, minúsculas, números e símbolos.');
            return;
        }

        if (newPassword !== confirmPassword) {
            showError('As senhas não coincidem');
            return;
        }

        if (newPassword === currentPassword) {
            showError('A nova senha deve ser diferente da atual');
            return;
        }

        const masterKey = App.getMasterKey();
        if (!masterKey) {
            App.showToast('Sessão expirada', 'error');
            App.lockVault();
            return;
        }

        setLoading(true);

        try {
            const currentAuthHash = await Crypto.deriveAuthHash(masterKey, currentPassword);

            progressEl.style.display = 'block';
            progressEl.textContent = 'Buscando itens do cofre...';

            const response = await fetch('/api/vault.php');
            const data = await response.json();
            if (!data.success || !data.items) {
                showError('Erro ao buscar itens do cofre');
                setLoading(false);
                progressEl.style.display = 'none';
                return;
            }

            const allItems = data.items;
            const decryptedItems = [];

            for (let i = 0; i < allItems.length; i++) {
                const item = allItems[i];
                progressEl.textContent = 'Descriptografando ' + (i + 1) + ' de ' + allItems.length + '...';
                const json = await Crypto.decryptData(item.encrypted_data, item.iv, item.auth_tag, masterKey);
                decryptedItems.push({ id: item.id, plaintext: json });
            }

            const emailMeta = document.querySelector('meta[name="user-email"]');
            const email = emailMeta ? emailMeta.getAttribute('content') : '';

            const newMasterKey = await Crypto.deriveMasterKey(newPassword, email);
            const newAuthHash = await Crypto.deriveAuthHash(newMasterKey, newPassword);
            const newSalt = Crypto.arrayBufferToHex(await crypto.subtle.exportKey('raw', newMasterKey)).substring(0, 64);

            const reencryptedItems = [];
            for (let i = 0; i < decryptedItems.length; i++) {
                const item = decryptedItems[i];
                progressEl.textContent = 'Recriptografando ' + (i + 1) + ' de ' + decryptedItems.length + '...';
                const encrypted = await Crypto.encryptData(item.plaintext, newMasterKey);
                reencryptedItems.push({
                    id: item.id,
                    encrypted_data: encrypted.encrypted_data,
                    iv: encrypted.iv,
                    auth_tag: encrypted.auth_tag
                });
            }

            progressEl.textContent = 'Atualizando servidor...';

            const result = await App.apiCall('/api/auth.php', {
                action: 'change_password',
                current_auth_hash: currentAuthHash,
                new_auth_hash: newAuthHash,
                new_salt: newSalt,
                items: reencryptedItems,
                csrf_token: App.getCSRFToken()
            });

            if (result.success) {
                await App.setMasterKey(newMasterKey);
                progressEl.textContent = '';
                progressEl.style.display = 'none';
                App.showToast('Senha alterada com sucesso!');
                closeModal('change-password-modal');
                await loadItems();
            } else {
                showError(result.error || 'Erro ao alterar senha');
                progressEl.style.display = 'none';
            }
        } catch (err) {
            showError('Erro ao processar. Tente novamente.');
            console.error('Change password error:', err);
            progressEl.style.display = 'none';
        }

        setLoading(false);
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

        App.initAutoLogout();
        await loadCategories();
        await loadItems();

        // Sidebar toggle (mobile)
        const sidebar = document.querySelector('.vault-sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const toggleBtn = document.getElementById('sidebar-toggle');

        function openSidebar() {
            sidebar?.classList.add('open');
            backdrop?.classList.add('open');
        }
        function closeSidebar() {
            sidebar?.classList.remove('open');
            backdrop?.classList.remove('open');
        }

        toggleBtn?.addEventListener('click', () => {
            sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
        });
        backdrop?.addEventListener('click', closeSidebar);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeSidebar();
        });

        // Category links — event delegation on nav
        document.getElementById('vault-categories')?.addEventListener('click', (e) => {
            const link = e.target.closest('.category-link');
            if (!link) return;
            e.preventDefault();
            document.querySelectorAll('.category-link').forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            currentCategory = link.dataset.category;
            renderItems();
            closeSidebar();
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
        document.getElementById('btn-view-edit')?.addEventListener('click', async (e) => {
            e.stopPropagation();
            if (currentViewId) {
                const editId = currentViewId;
                closeModal('view-modal');
                const item = items.find(i => String(i.id) === String(editId));
                if (item) {
                    const masterKey = App.getMasterKey();
                    if (masterKey) {
                        try {
                            const json = await Crypto.decryptData(item.encrypted_data, item.iv, item.auth_tag, masterKey);
                            item._decrypted = JSON.parse(json);
                        } catch {}
                    }
                    openItemForm(editId);
                }
            }
        });

        document.getElementById('btn-view-delete')?.addEventListener('click', () => {
            if (currentViewId) deleteItem(currentViewId);
        });

        // Export
        document.getElementById('btn-export')?.addEventListener('click', exportVault);

        // Import
        document.getElementById('btn-import')?.addEventListener('click', openImportModal);
        document.getElementById('import-modal-close')?.addEventListener('click', () => closeModal('import-modal'));
        document.getElementById('btn-import-cancel')?.addEventListener('click', () => closeModal('import-modal'));
        document.getElementById('import-file')?.addEventListener('change', handleImportFileSelect);
        document.getElementById('btn-import-confirm')?.addEventListener('click', executeImport);

        // Category management
        document.getElementById('btn-manage-categories')?.addEventListener('click', openCategoryModal);
        document.getElementById('cat-modal-close')?.addEventListener('click', () => closeModal('category-modal'));
        document.getElementById('cat-btn-add')?.addEventListener('click', showCategoryCreateForm);
        document.getElementById('cat-btn-back')?.addEventListener('click', openCategoryModal);
        document.getElementById('cat-btn-save')?.addEventListener('click', saveCategory);
        document.getElementById('cat-btn-add-field')?.addEventListener('click', () => addCatFieldRow());

        // Auto-slug from name
        document.getElementById('cat-name')?.addEventListener('input', (e) => {
            const slugInput = document.getElementById('cat-slug');
            if (slugInput && !editingCategoryId) {
                slugInput.value = slugify(e.target.value);
            }
        });

        // Change password
        document.getElementById('btn-change-password')?.addEventListener('click', openChangePasswordModal);
        document.getElementById('change-password-modal-close')?.addEventListener('click', () => closeModal('change-password-modal'));
        document.getElementById('btn-change-password-cancel')?.addEventListener('click', () => closeModal('change-password-modal'));
        document.getElementById('change-password-ack')?.addEventListener('change', (e) => {
            const btn = document.getElementById('btn-change-password-continue');
            if (btn) btn.disabled = !e.target.checked;
        });
        document.getElementById('btn-change-password-continue')?.addEventListener('click', () => {
            document.getElementById('change-password-warning').style.display = 'none';
            document.getElementById('change-password-form-screen').style.display = 'block';
        });
        document.getElementById('btn-change-password-back')?.addEventListener('click', () => {
            document.getElementById('change-password-form-screen').style.display = 'none';
            document.getElementById('change-password-warning').style.display = 'block';
        });
        document.getElementById('change-password-form')?.addEventListener('submit', handlePasswordChangeSubmit);
        document.getElementById('new-password')?.addEventListener('input', (e) => {
            const result = Crypto.calculatePasswordStrength(e.target.value);
            const bar = document.getElementById('cp-strength-bar');
            const text = document.getElementById('cp-strength-text');
            if (bar) {
                bar.style.width = ((result.score + 1) * 20) + '%';
                bar.style.backgroundColor = result.color;
            }
            if (text) {
                text.textContent = result.label;
                text.style.color = result.color;
            }
        });

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

    return { loadItems, renderItems, loadCategories };
})();
