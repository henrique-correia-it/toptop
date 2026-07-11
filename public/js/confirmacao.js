// public/js/confirmacao.js (VERSÃO CORRIGIDA - SEM RACE CONDITION)

const abrirModal = (titulo, texto, callback) => {
    const modal = document.getElementById('modalConfirmacao');
    if (!modal) return;

    const tituloEl = document.getElementById('modalConfirmacaoTitulo');
    const textoEl = document.getElementById('modalConfirmacaoTexto');
    const btnConfirmar = document.getElementById('modalConfirmacaoBtnConfirmar');
    const btnCancelar = document.getElementById('modalConfirmacaoBtnCancelar');

    if (tituloEl) tituloEl.innerHTML = titulo || 'Tem a certeza?';
    if (textoEl) textoEl.innerHTML = texto || 'Esta ação não pode ser revertida.';
    
    // Armazenar o callback num atributo do modal para acesso posterior
    modal.onConfirmCallback = callback;

    const fecharModal = () => {
        modal.classList.remove('ativo');
        modal.onConfirmCallback = null;
    };

    // Configurar botões (remover eventos antigos para evitar duplicados se a função for chamada múltiplas vezes)
    const novoBtnConfirmar = btnConfirmar.cloneNode(true);
    btnConfirmar.parentNode.replaceChild(novoBtnConfirmar, btnConfirmar);
    
    const novoBtnCancelar = btnCancelar.cloneNode(true);
    btnCancelar.parentNode.replaceChild(novoBtnCancelar, btnCancelar);

    novoBtnConfirmar.addEventListener('click', () => {
        if (typeof modal.onConfirmCallback === 'function') {
            modal.onConfirmCallback();
        }
        fecharModal();
    });

    novoBtnCancelar.addEventListener('click', fecharModal);
    
    // Fechar ao clicar fora
    const clickFora = (e) => {
        if (e.target === modal) {
            fecharModal();
            modal.removeEventListener('click', clickFora);
        }
    };
    modal.addEventListener('click', clickFora);

    modal.classList.add('ativo');
};

// Expõe a função para que outros scripts a possam chamar IMEDIATAMENTE
window.mostrarModalConfirmacao = abrirModal;

// Listener para botões com a classe .btn-apagar-confirmado (delegação de eventos)
document.addEventListener('DOMContentLoaded', () => {
    document.body.addEventListener('click', (e) => {
        const target = e.target.closest('.btn-apagar-confirmado');
        if (target) {
            e.preventDefault();

            const formParaSubmeter = target.closest('form');
            const urlParaApagar = target.getAttribute('href');
            const mensagem = target.dataset.mensagemConfirmacao || 'Tem a certeza que quer apagar este item? A ação é irreversível.';
            const titulo = target.dataset.tituloConfirmacao || 'Confirmar Eliminação';

            abrirModal(
                titulo,
                mensagem,
                () => {
                    if (formParaSubmeter) {
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = target.name || 'action';
                        actionInput.value = target.value || 'apagar';
                        formParaSubmeter.appendChild(actionInput);
                        formParaSubmeter.submit();
                    }
                    else if (urlParaApagar) {
                        window.location.href = urlParaApagar;
                    }
                }
            );
        }
    });
});