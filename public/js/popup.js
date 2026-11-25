// public/js/popup.js

/**
 * Exibe um pop-up de notificação moderno.
 * @param {string} mensagem A mensagem a ser exibida.
 * @param {string} [tipo='sucesso'] O tipo de pop-up ('sucesso' ou 'erro').
 */
let _popupAutoCloseTimer = null;

function mostrarPopup(mensagem, tipo = 'sucesso') {
    const popup = document.getElementById('popupMensagem');
    if (!popup) return;

    const titulo = document.getElementById('popupTitulo');
    const texto = document.getElementById('popupTexto');
    const fecharBtn = document.getElementById('popupFechar');

    // Remove classes antigas e cancela timer anterior para evitar fecho prematuro
    if (_popupAutoCloseTimer) {
        clearTimeout(_popupAutoCloseTimer);
        _popupAutoCloseTimer = null;
    }
    popup.classList.remove('sucesso', 'erro', 'ativo');

    // Define o título e a mensagem
    if (texto) texto.textContent = mensagem;

    if (tipo === 'erro') {
        if (titulo) titulo.textContent = 'Erro!';
        popup.classList.add('erro');
        popup.setAttribute('role', 'alert');
    } else {
        if (titulo) titulo.textContent = 'Sucesso!';
        popup.classList.add('sucesso');
        popup.setAttribute('role', 'status');
    }

    // Força reflow para reiniciar animação em toasts consecutivos
    void popup.offsetWidth;
    popup.classList.add('ativo');

    // Esconde o pop-up automaticamente após 5 segundos
    _popupAutoCloseTimer = setTimeout(() => {
        popup.classList.remove('ativo');
        _popupAutoCloseTimer = null;
    }, 5000);

    // Evento de clique no botão de fechar
    if (fecharBtn) {
        fecharBtn.onclick = function() {
            if (_popupAutoCloseTimer) {
                clearTimeout(_popupAutoCloseTimer);
                _popupAutoCloseTimer = null;
            }
            popup.classList.remove('ativo');
        };
    }
}