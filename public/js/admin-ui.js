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
        if (!row || e.target.closest('.acoes-tabela') || e.target.closest('.no-click')) return;

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
            setTimeout(() => popup.classList.remove('ativo'), 5000);
        } else {
            console.log(`[${tipo.toUpperCase()}] ${mensagem}`);
        }
    }
};
