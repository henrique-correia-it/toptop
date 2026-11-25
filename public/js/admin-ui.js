/**
 * TOPTOP Admin UI Engine
 * Versão: 2.0.0 (Clean Code / Event Delegation)
 * 
 * Este ficheiro centraliza toda a lógica de interface do painel administrativo.
 * Evita repetição de código em cada página PHP.
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // --- SELETORES GLOBAIS ---
    const body = document.body;
    const ctxMenus = document.querySelectorAll('.ctx-menu');
    const modals = document.querySelectorAll('.qe-modal');

    // --- 1. GESTÃO DE MENUS DE CONTEXTO ---
    document.addEventListener('contextmenu', function(e) {
        // Encontrar o elemento alvo que suporta menu de contexto
        const target = e.target.closest('tr[data-id], .activity-item-ctx, .nav-card, .template-accordion-item');
        if (!target) return;

        // Tenta encontrar o menu correto para este elemento
        let menuId = target.dataset.ctxId;
        if (!menuId) {
            if (target.classList.contains('activity-item-ctx')) menuId = 'ctx-menu-activity';
            else if (target.classList.contains('template-accordion-item')) menuId = 'ctx-menu-template';
            else menuId = 'ctx-menu';
        }

        const ctxMenu = document.getElementById(menuId) || document.querySelector('.ctx-menu');
        if (!ctxMenu) return;

        e.preventDefault();

        // Posicionamento inteligente (evita sair do ecrã)
        let x = e.clientX;
        let y = e.clientY;
        
        ctxMenu.style.display = 'block';
        
        // Ajuste de colisão com as bordas
        if (x + ctxMenu.offsetWidth > window.innerWidth) x -= ctxMenu.offsetWidth;
        if (y + ctxMenu.offsetHeight > window.innerHeight) y -= ctxMenu.offsetHeight;
        x = Math.max(8, x);
        y = Math.max(8, y);
        
        ctxMenu.style.left = x + 'px';
        ctxMenu.style.top = y + 'px';

        // Evento customizado para a página preencher os dados do menu
        // Mantemos 'row' por compatibilidade com scripts existentes
        const event = new CustomEvent('admin:contextmenu', { 
            detail: { row: target, menu: ctxMenu, originalEvent: e } 
        });
        document.dispatchEvent(event);
    });

    // --- 2. NAVEGAÇÃO POR DUPLO CLIQUE ---
    document.addEventListener('dblclick', function(e) {
        const row = e.target.closest('tr[data-id]');
        if (!row || row.closest('.sel-mode') || e.target.closest('.acoes-tabela') || e.target.closest('.no-click')) return;

        const editUrl = row.dataset.editUrl;
        if (editUrl) {
            window.location.href = editUrl;
        } else {
            // Tentativa de encontrar o link de edição na coluna de ações
            const editBtn = row.querySelector('.btn-edit-single, .btn-edit');
            if (editBtn && editBtn.href) {
                window.location.href = editBtn.href;
            }
        }
    });

    // --- 3. GESTÃO DE MODAIS ---
    // Abre modal (via trigger data-modal)
    document.addEventListener('click', function(e) {
        const trigger = e.target.closest('[data-modal-target]');
        if (trigger) {
            const modalId = trigger.dataset.modalTarget;
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'flex';
                document.documentElement.classList.add('scroll-lock');
                
                // Evento customizado para a página preencher o modal
                const event = new CustomEvent('admin:modalOpen', { 
                    detail: { trigger: trigger, modal: modal } 
                });
                document.dispatchEvent(event);
            }
        }
    });

    // Fecha modal/menu ao clicar fora ou no botão fechar
    document.addEventListener('click', function(e) {
        // Fechar menus de contexto ao clicar em qualquer lugar
        ctxMenus.forEach(menu => {
            if (menu.style.display === 'block' && !e.target.closest('.ctx-menu')) {
                menu.style.display = 'none';
            }
        });

        // Fechar modais
        if (e.target.closest('.qe-close') || e.target.classList.contains('qe-modal') || e.target.closest('.qe-btn-cancel')) {
            const modal = e.target.closest('.qe-modal');
            if (modal) {
                modal.style.display = 'none';
                document.documentElement.classList.remove('scroll-lock');
            }
        }
    });

    // Esc para fechar tudo
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            ctxMenus.forEach(m => m.style.display = 'none');
            modals.forEach(m => {
                m.style.display = 'none';
                document.documentElement.classList.remove('scroll-lock');
            });
        }
    });
});

// --- Utilitário reutilizável de seleção em massa para tabelas admin/dev ---
window.initAdminBulkSelection = function(opts) {
    opts = opts || {};
    const btnSelMode  = document.getElementById(opts.btnSelModeId || 'btn-sel-mode');
    const btnSelAll   = document.getElementById(opts.btnSelAllId || 'btn-sel-all');
    const bulkBar     = document.getElementById(opts.bulkBarId || 'bulk-bar');
    const bulkCountEl = document.getElementById(opts.bulkCountId || 'bulk-count');
    const bulkCancel  = document.getElementById(opts.bulkCancelId || 'bulk-cancel');
    const bulkDel     = document.getElementById(opts.bulkActionId || 'bulk-del');
    const tableWrap   = document.getElementById(opts.tableWrapId || 'table-wrapper');
    if (!btnSelMode || !tableWrap) return null;

    let selMode = false;
    const selected = new Set();
    const rowSelector      = opts.rowSelector || 'tbody tr[data-id]';
    const rowFilter        = typeof opts.rowFilter === 'function' ? opts.rowFilter : null;
    const selectAllLabel   = opts.selectAllLabel || 'Selecionar todos';
    const deselectAllLabel = opts.deselectAllLabel || 'Desselecionar todos';

    const getRows = () => {
        const rows = Array.from(tableWrap.querySelectorAll(rowSelector));
        return rowFilter ? rows.filter(rowFilter) : rows;
    };

    const updateUI = () => {
        const n = selected.size;
        const total = getRows().length;
        if (bulkCountEl) bulkCountEl.textContent = n;
        if (btnSelAll) btnSelAll.textContent = (total > 0 && n === total) ? deselectAllLabel : selectAllLabel;
        if (bulkBar) bulkBar.classList.toggle('visible', selMode);
        if (bulkDel) bulkDel.disabled = (n === 0);
    };

    const setMode = (active) => {
        selMode = Boolean(active);
        tableWrap.classList.toggle('sel-mode', selMode);
        btnSelMode.classList.toggle('active', selMode);
        if (!selMode) {
            selected.clear();
            getRows().forEach(r => r.classList.remove('selecionado'));
        }
        updateUI();
    };

    const submitForm = (ids) => {
        const form = opts.formId ? document.getElementById(opts.formId) : null;
        const container = document.getElementById(opts.idsContainerId || 'form-ids');
        if (!form || !container) return;
        const inputName = opts.inputName || 'ids[]';
        container.innerHTML = '';
        ids.forEach(id => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = inputName;
            inp.value = id;
            container.appendChild(inp);
        });
        form.submit();
    };

    btnSelMode.addEventListener('click', () => setMode(!selMode));
    if (bulkCancel) bulkCancel.addEventListener('click', () => setMode(false));

    const tbody = tableWrap.querySelector('tbody');
    if (tbody) {
        tbody.addEventListener('click', function(e) {
            if (!selMode || e.target.closest('.acoes-tabela')) return;
            const tr = e.target.closest('tr[data-id]');
            if (!tr || (rowFilter && !rowFilter(tr))) return;
            const id = tr.dataset.id;
            if (selected.has(id)) { selected.delete(id); tr.classList.remove('selecionado'); }
            else                  { selected.add(id);    tr.classList.add('selecionado'); }
            updateUI();
        });
    }

    if (btnSelAll) {
        btnSelAll.addEventListener('click', () => {
            const rows = getRows();
            if (!rows.length) return;
            const allSel = rows.every(r => selected.has(r.dataset.id));
            rows.forEach(r => {
                if (allSel) { selected.delete(r.dataset.id); r.classList.remove('selecionado'); }
                else        { selected.add(r.dataset.id);    r.classList.add('selecionado'); }
            });
            updateUI();
        });
    }

    if (bulkDel) {
        bulkDel.addEventListener('click', () => {
            const ids = Array.from(selected);
            if (!ids.length) return;
            if (typeof opts.onAction === 'function') {
                opts.onAction(ids);
            } else if (opts.formId) {
                const msg = typeof opts.confirmMessage === 'function'
                    ? opts.confirmMessage(ids)
                    : (opts.confirmMessage || `Confirmar ação para ${ids.length} item(ns)?`);
                mostrarModalConfirmacao(opts.confirmTitle || 'Confirmar', msg, () => submitForm(ids));
            }
        });
    }

    return {
        isSelMode: () => selMode,
        getSelected: () => Array.from(selected),
        setMode,
        submitForm,
        updateUI,
    };
};

// --- Utilitário global para popups de feedback (Toast) ---
// Captura a função original definida em popup.js para evitar que seja perdida
if (typeof window.mostrarPopup === 'function' && !window.originalMostrarPopup) {
    window.originalMostrarPopup = window.mostrarPopup;
}

window.mostrarPopup = function(mensagem, tipo = 'sucesso') {
    if (typeof window.originalMostrarPopup === 'function') {
        window.originalMostrarPopup(mensagem, tipo);
    } else {
        // Fallback robusto: tenta manipular o DOM diretamente se o popup.js falhar
        const popup = document.getElementById('popupMensagem');
        if (popup) {
            const texto = document.getElementById('popupTexto');
            if (texto) texto.textContent = mensagem;
            popup.classList.remove('sucesso', 'erro');
            popup.classList.add(tipo, 'ativo');
            clearTimeout(popup._fallbackTimer);
            popup._fallbackTimer = setTimeout(() => popup.classList.remove('ativo'), 5000);
        } else {
            console.log(`[${tipo.toUpperCase()}] ${mensagem}`);
        }
    }
};
