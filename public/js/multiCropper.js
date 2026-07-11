// public/js/multiCropper.js
function inicializarMultiCropper(inputId, previewContainerId, formId, maxFiles, imagensIniciais = []) {
    const inputFicheiros = document.getElementById(inputId);
    const previewContainer = document.getElementById(previewContainerId);
    const form = document.getElementById(formId);

    const modal = document.getElementById('cropperModal');
    const imagemParaCortar = document.getElementById('imagemParaCortar');
    const guardarCorteBtn = document.getElementById('guardarCorteBtn');

    let cropper;
    let ficheirosParaProcessar = [];
    // Estrutura: { tipo: 'nova', dados: 'base64...', placeholder: 'NEW_0' } ou { tipo: 'existente', id: 123, url: 'imagem.jpg' }
    let imagensGaleria = [];
    let idsParaApagar = [];
    let ficheiroAtualIndex = 0;
    let newImageCounter = 0; // Contador para placeholders de novas imagens
    let sortable = null;

    // --- NOVA FUNÇÃO ---
    // Torna a lista de imagens da galeria acessível a outros scripts
    window.getImagensGaleria = () => JSON.parse(JSON.stringify(imagensGaleria));
    // --- FIM DA NOVA FUNÇÃO ---


    function carregarImagensIniciais() {
        imagensGaleria = [];
        idsParaApagar = [];
        newImageCounter = 0;

        imagensIniciais.forEach(img => {
            imagensGaleria.push({ tipo: 'existente', id: img.id, url: img.url });
        });
        atualizarPreviews();
        inicializarSortable();
    }

    inputFicheiros.addEventListener('change', (event) => {
        const files = Array.from(event.target.files);
        if (files.length === 0) return;

        if ((imagensGaleria.length + files.length) > maxFiles) {
            mostrarPopup(`Pode ter no máximo ${maxFiles} imagens no total.`, 'erro');
            inputFicheiros.value = '';
            return;
        }

        ficheirosParaProcessar = files;
        ficheiroAtualIndex = 0;
        processarFicheiroSeguinte();
    });

    function processarFicheiroSeguinte() {
        if (ficheiroAtualIndex < ficheirosParaProcessar.length) {
            const reader = new FileReader();
            reader.onload = (e) => abrirModalDeCorte(e.target.result);
            reader.readAsDataURL(ficheirosParaProcessar[ficheiroAtualIndex]);
        } else {
            // Dispara um evento para notificar outros scripts que a galeria foi atualizada
            document.dispatchEvent(new CustomEvent('galeriaAtualizada'));
        }
    }

    function abrirModalDeCorte(imageDataUrl) {
        modal.style.display = 'flex';
        imagemParaCortar.src = imageDataUrl;
        if (cropper) cropper.destroy();
        cropper = new Cropper(imagemParaCortar, { aspectRatio: 0.75, viewMode: 1, autoCropArea: 1 });
    }

    guardarCorteBtn.addEventListener('click', () => {
        const canvas = cropper.getCroppedCanvas({ width: 600, height: 800 });
        const base64ImageData = canvas.toDataURL('image/jpeg', 0.9);

        // Adiciona um placeholder único para a nova imagem
        const placeholderId = `NEW_${newImageCounter++}`;
        imagensGaleria.push({ tipo: 'nova', dados: base64ImageData, placeholder: placeholderId });

        atualizarPreviews();

        modal.style.display = 'none';
        ficheiroAtualIndex++;
        processarFicheiroSeguinte();
    });

    function removerImagem(indexToRemove) {
        const imagem = imagensGaleria[indexToRemove];
        if (imagem.tipo === 'existente') {
            idsParaApagar.push(imagem.id);
        }
        imagensGaleria.splice(indexToRemove, 1);
        atualizarPreviews();
        // Notifica outros scripts da alteração
        document.dispatchEvent(new CustomEvent('galeriaAtualizada'));
    }

    function atualizarPreviews() {
        previewContainer.innerHTML = '';

        imagensGaleria.forEach((imagem, index) => {
            const previewWrapper = document.createElement('div');
            previewWrapper.classList.add('preview-item');
            // Adiciona um identificador único ao elemento do DOM
            previewWrapper.dataset.imageId = (imagem.tipo === 'existente') ? imagem.id : imagem.placeholder;

            const img = document.createElement('img');
            img.src = (imagem.tipo === 'nova') ? imagem.dados : imagem.url;

            const removerBtn = document.createElement('button');
            removerBtn.type = 'button';
            removerBtn.classList.add('remover-preview-btn');
            removerBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>';
            removerBtn.onclick = () => removerImagem(index);

            if (index === 0) {
                const principalLabel = document.createElement('span');
                principalLabel.classList.add('principal-label');
                principalLabel.textContent = 'Principal';
                previewWrapper.appendChild(principalLabel);
            }

            previewWrapper.appendChild(img);
            previewWrapper.appendChild(removerBtn);
            previewContainer.appendChild(previewWrapper);
        });
    }

    form.addEventListener('submit', (event) => {
        if (imagensGaleria.length < 1 || imagensGaleria.length > maxFiles) {
            mostrarPopup(`Deve ter entre 1 e ${maxFiles} imagens.`, 'erro');
            event.preventDefault();
            return;
        }

        document.querySelectorAll('.hidden-image-input').forEach(el => el.remove());

        let newImageCounterSubmit = 0;
        const finalOrder = [];
        const newImagesData = [];

        imagensGaleria.forEach(img => {
            if (img.tipo === 'existente') {
                finalOrder.push(img.id);
            } else {
                // Usa o placeholder consistente
                finalOrder.push(img.placeholder);
                newImagesData.push({ placeholder: img.placeholder, dados: img.dados });
            }
        });

        const inputOrdem = document.createElement('input');
        inputOrdem.type = 'hidden';
        inputOrdem.name = 'ordem_imagens_json';
        inputOrdem.value = JSON.stringify(finalOrder);
        inputOrdem.classList.add('hidden-image-input');
        form.appendChild(inputOrdem);

        const inputNovas = document.createElement('input');
        inputNovas.type = 'hidden';
        inputNovas.name = 'imagens_cortadas_json';
        inputNovas.value = JSON.stringify(newImagesData);
        inputNovas.classList.add('hidden-image-input');
        form.appendChild(inputNovas);

        const inputApagar = document.createElement('input');
        inputApagar.type = 'hidden';
        inputApagar.name = 'imagens_a_apagar_json';
        inputApagar.value = JSON.stringify(idsParaApagar);
        inputApagar.classList.add('hidden-image-input');
        form.appendChild(inputApagar);
    });

    function inicializarSortable() {
        if (sortable) sortable.destroy();

        sortable = new Sortable(previewContainer, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function (evt) {
                const [movedItem] = imagensGaleria.splice(evt.oldIndex, 1);
                imagensGaleria.splice(evt.newIndex, 0, movedItem);
                atualizarPreviews();
                // Notifica outros scripts da alteração na ordem
                document.dispatchEvent(new CustomEvent('galeriaAtualizada'));
            }
        });
    }

    carregarImagensIniciais();
}