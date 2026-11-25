// public/js/multiCropper.js
function inicializarMultiCropper(inputId, previewContainerId, formId, maxFiles, imagensIniciais = []) {
    const inputFicheiros = document.getElementById(inputId);
    const previewContainer = document.getElementById(previewContainerId);
    const form = document.getElementById(formId);

    const modal = document.getElementById('cropperModal');
    const imagemParaCortar = document.getElementById('imagemParaCortar');
    const guardarCorteBtn = document.getElementById('guardarCorteBtn');
    if (!inputFicheiros || !previewContainer || !form || !modal || !imagemParaCortar || !guardarCorteBtn) return;

    let cropper = null;
    let cropperReady = false;
    let isSavingCrop = false;
    let ficheirosParaProcessar = [];
    let imagensGaleria = [];
    let idsParaApagar = [];
    let ficheiroAtualIndex = 0;
    let newImageCounter = 0;
    let sortable = null;

    let cancelarCorteBtn = document.getElementById('cancelarCorteBtn');
    if (!cancelarCorteBtn) {
        cancelarCorteBtn = document.createElement('button');
        cancelarCorteBtn.type = 'button';
        cancelarCorteBtn.id = 'cancelarCorteBtn';
        cancelarCorteBtn.className = 'voltar-btn';
        cancelarCorteBtn.textContent = 'Cancelar';
        guardarCorteBtn.insertAdjacentElement('beforebegin', cancelarCorteBtn);
    }

    window.getImagensGaleria = () => JSON.parse(JSON.stringify(imagensGaleria));

    function destruirCropper() {
        cropperReady = false;
        if (cropper) {
            try { cropper.destroy(); } catch (_) {}
            cropper = null;
        }
    }

    function fecharModalCorte(cancelarFila = false) {
        destruirCropper();
        modal.style.display = 'none';
        imagemParaCortar.removeAttribute('src');
        isSavingCrop = false;
        guardarCorteBtn.disabled = false;
        if (cancelarFila) {
            ficheirosParaProcessar = [];
            ficheiroAtualIndex = 0;
            inputFicheiros.value = '';
            document.dispatchEvent(new CustomEvent('galeriaAtualizada'));
        }
    }

    cancelarCorteBtn.addEventListener('click', () => fecharModalCorte(true));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            e.preventDefault();
            fecharModalCorte(true);
        }
    });

    function carregarImagensIniciais() {
        imagensGaleria = [];
        idsParaApagar = [];
        newImageCounter = 0;

        if (Array.isArray(imagensIniciais)) {
            imagensIniciais.forEach(img => {
                if (img && img.id !== undefined && img.url) {
                    imagensGaleria.push({ tipo: 'existente', id: String(img.id), url: img.url });
                }
            });
        }
        atualizarPreviews();
        inicializarSortable();
    }

    inputFicheiros.addEventListener('change', (event) => {
        const files = Array.from(event.target.files || []).filter(f => f.type && f.type.startsWith('image/'));
        if (files.length === 0) {
            mostrarPopup('Selecione apenas ficheiros de imagem válidos.', 'erro');
            inputFicheiros.value = '';
            return;
        }

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
            reader.onerror = () => {
                mostrarPopup('Não foi possível ler um dos ficheiros selecionados.', 'erro');
                ficheiroAtualIndex++;
                processarFicheiroSeguinte();
            };
            reader.readAsDataURL(ficheirosParaProcessar[ficheiroAtualIndex]);
        } else {
            ficheirosParaProcessar = [];
            ficheiroAtualIndex = 0;
            inputFicheiros.value = '';
            document.dispatchEvent(new CustomEvent('galeriaAtualizada'));
        }
    }

    function abrirModalDeCorte(imageDataUrl) {
        if (typeof Cropper !== 'function') {
            mostrarPopup('O editor de recorte não está disponível. Recarregue a página.', 'erro');
            fecharModalCorte(true);
            return;
        }
        destruirCropper();
        isSavingCrop = false;
        guardarCorteBtn.disabled = true;
        modal.style.display = 'flex';
        imagemParaCortar.src = imageDataUrl;
        cropper = new Cropper(imagemParaCortar, {
            aspectRatio: 0.75,
            viewMode: 1,
            autoCropArea: 1,
            ready() {
                cropperReady = true;
                guardarCorteBtn.disabled = false;
            }
        });
    }

    guardarCorteBtn.addEventListener('click', () => {
        if (!cropper || !cropperReady || isSavingCrop) return;
        isSavingCrop = true;
        guardarCorteBtn.disabled = true;

        const canvas = cropper.getCroppedCanvas({ width: 600, height: 800 });
        if (!canvas) {
            mostrarPopup('Não foi possível recortar a imagem. Tente novamente.', 'erro');
            isSavingCrop = false;
            guardarCorteBtn.disabled = false;
            return;
        }
        const base64ImageData = canvas.toDataURL('image/jpeg', 0.9);

        const placeholderId = `NEW_${newImageCounter++}`;
        imagensGaleria.push({ tipo: 'nova', dados: base64ImageData, placeholder: placeholderId });

        atualizarPreviews();
        fecharModalCorte(false);

        ficheiroAtualIndex++;
        processarFicheiroSeguinte();
    });

    function removerImagem(indexToRemove) {
        const imagem = imagensGaleria[indexToRemove];
        if (!imagem) return;
        if (imagem.tipo === 'existente') {
            idsParaApagar.push(imagem.id);
        }
        imagensGaleria.splice(indexToRemove, 1);
        atualizarPreviews();
        document.dispatchEvent(new CustomEvent('galeriaAtualizada'));
    }

    function atualizarPreviews() {
        previewContainer.innerHTML = '';

        imagensGaleria.forEach((imagem, index) => {
            const previewWrapper = document.createElement('div');
            previewWrapper.classList.add('preview-item');
            previewWrapper.dataset.imageId = (imagem.tipo === 'existente') ? String(imagem.id) : String(imagem.placeholder);

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

        const finalOrder = [];
        const newImagesData = [];

        imagensGaleria.forEach(img => {
            if (img.tipo === 'existente') {
                finalOrder.push(img.id);
            } else {
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
        if (sortable) {
            try { sortable.destroy(); } catch (_) {}
            sortable = null;
        }
        if (typeof Sortable !== 'function') return;

        sortable = new Sortable(previewContainer, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function (evt) {
                if (evt.oldIndex === undefined || evt.newIndex === undefined || evt.oldIndex === evt.newIndex) return;
                const [movedItem] = imagensGaleria.splice(evt.oldIndex, 1);
                imagensGaleria.splice(evt.newIndex, 0, movedItem);
                atualizarPreviews();
                document.dispatchEvent(new CustomEvent('galeriaAtualizada'));
            }
        });
    }

    carregarImagensIniciais();
}
