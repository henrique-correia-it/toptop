// public/js/popup.js

/**
 * Exibe um pop-up de notificação moderno.
 * @param {string} mensagem A mensagem a ser exibida.
 * @param {string} [tipo='sucesso'] O tipo de pop-up ('sucesso' ou 'erro').
 */
function mostrarPopup(mensagem, tipo = 'sucesso') {
    const popup = document.getElementById('popupMensagem');
    if (!popup) return;

    const titulo = document.getElementById('popupTitulo');
    const texto = document.getElementById('popupTexto');
    const fecharBtn = document.getElementById('popupFechar');

    // Remove classes antigas para garantir um estado limpo
    popup.classList.remove('sucesso', 'erro', 'ativo');

    // Define o título e a mensagem
    texto.textContent = mensagem;

    if (tipo === 'erro') {
        titulo.textContent = 'Erro!';
        popup.classList.add('erro');
    } else {
        titulo.textContent = 'Sucesso!';
        popup.classList.add('sucesso');
    }

    // Mostra o pop-up
    popup.classList.add('ativo');

    // Esconde o pop-up automaticamente após 5 segundos
    setTimeout(() => {
        popup.classList.remove('ativo');
    }, 5000);

    // Evento de clique no botão de fechar
    fecharBtn.onclick = function() {
        popup.classList.remove('ativo');
    };
}