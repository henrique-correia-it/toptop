document.addEventListener('DOMContentLoaded', () => {
    const standardGroupSelect = document.getElementById('select-grupo-standard');
    const addStandardBtn = document.getElementById('btn-add-grupo-standard');
    const standardContainer = document.getElementById('atributos-standard-container');
    const addCustomBtn = document.getElementById('btn-add-atributo-personalizado');
    const customContainer = document.getElementById('atributos-personalizados-container');
    const form = document.getElementById('formProduto');
    const hiddenJsonInput = document.getElementById('hidden-atributos-json');

    if (!form) return;

    let atributosAdicionados = new Set();

    const criarBlocoStandard = (grupoId, grupoNome, valores) => {
        if (atributosAdicionados.has(grupoNome)) {
            mostrarPopup(`O atributo padrão "${grupoNome}" já foi adicionado.`, 'erro');
            return;
        }
        const fieldset = document.createElement('fieldset');
        fieldset.classList.add('atributo-bloco');
        fieldset.dataset.nomeAtributo = grupoNome;
        let valoresHtml = valores.map(v => `
            <label><input type="checkbox" name="attr_std_${grupoId}[]" value="${v.valor}"> ${v.valor}</label>
        `).join('');
        fieldset.innerHTML = `
            <div class="atributo-bloco-header">
                <legend>${grupoNome}</legend>
            <button type="button" class="btn-del-single" title="Remover Atributo"></button>
            </div>
            <div class="atributo-bloco-valores">${valoresHtml}</div>
        `;
        standardContainer.appendChild(fieldset);
        atributosAdicionados.add(grupoNome);
    };

    const criarBlocoPersonalizado = (nome = '', valores = '') => {
        const div = document.createElement('div');
        div.classList.add('atributo-bloco', 'atributo-personalizado-item');
        div.innerHTML = `
            <input type="text" class="input-nome-personalizado" placeholder="Nome do Atributo (ex: Material)" value="${nome}" autocomplete="off">
            <input type="text" class="input-valores-personalizado" placeholder="Valores (ex: Algodão, Seda)" value="${valores}" autocomplete="off">
            <button type="button" class="btn-remover-atributo" title="Remover Atributo">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
            </button>
        `;
        customContainer.appendChild(div);
    };

    addStandardBtn?.addEventListener('click', () => {
        const grupoId = standardGroupSelect.value;
        if (!grupoId) {
            mostrarPopup('Por favor, selecione um atributo da lista.', 'erro');
            return;
        }
        const grupoNome = standardGroupSelect.options[standardGroupSelect.selectedIndex].text;
        fetch(`ajax_get_valores.php?grupo_id=${grupoId}`)
            .then(response => response.json())
            .then(valores => criarBlocoStandard(grupoId, grupoNome, valores))
            .catch(error => console.error('Erro ao buscar valores:', error));
    });

    addCustomBtn?.addEventListener('click', () => criarBlocoPersonalizado('', ''));

    form.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.btn-del-single');
        if (removeBtn) {
            const bloco = removeBtn.closest('.atributo-bloco');
            if (bloco?.dataset.nomeAtributo) {
                atributosAdicionados.delete(bloco.dataset.nomeAtributo);
            }
            bloco?.remove();
        }
    });

    window.compilarAtributosParaJSON = () => {
        const resultado = {};
        document.querySelectorAll('#atributos-standard-container .atributo-bloco').forEach(bloco => {
            const nomeAtributo = bloco.dataset.nomeAtributo;
            const valoresSelecionados = Array.from(bloco.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value);
            if (valoresSelecionados.length > 0) resultado[nomeAtributo] = valoresSelecionados;
        });
        document.querySelectorAll('#atributos-personalizados-container .atributo-personalizado-item').forEach(item => {
            const nomeAtributo = item.querySelector('.input-nome-personalizado').value.trim();
            const valoresInput = item.querySelector('.input-valores-personalizado').value.trim();
            if (nomeAtributo && valoresInput) {
                if (atributosAdicionados.has(nomeAtributo)) {
                    mostrarPopup(`O atributo "${nomeAtributo}" já existe como padrão e será ignorado.`, 'erro');
                    return;
                }
                const valoresArray = valoresInput.split(',').map(v => v.trim()).filter(Boolean);
                if (valoresArray.length > 0) resultado[nomeAtributo] = valoresArray;
            }
        });
        hiddenJsonInput.value = JSON.stringify(resultado);
    };
    
    // CORREÇÃO: A função agora espera um objeto JavaScript, não uma string.
    const carregarAtributosGuardados = (objAtributos) => {
        const todosOsGruposPadrao = Array.from(standardGroupSelect.options).reduce((acc, opt) => {
            if (opt.value) acc[opt.text] = opt.value;
            return acc;
        }, {});

        const promises = Object.keys(objAtributos).map(nomeAtributo => {
            const valoresGuardados = objAtributos[nomeAtributo];
            if (todosOsGruposPadrao[nomeAtributo]) {
                const grupoId = todosOsGruposPadrao[nomeAtributo];
                return fetch(`ajax_get_valores.php?grupo_id=${grupoId}`)
                    .then(response => response.json())
                    .then(valoresPossiveis => {
                        criarBlocoStandard(grupoId, nomeAtributo, valoresPossiveis);
                        const bloco = standardContainer.querySelector(`[data-nome-atributo="${CSS.escape(nomeAtributo)}"]`);
                        if (bloco) {
                            valoresGuardados.forEach(valorGuardado => {
                                const checkbox = bloco.querySelector(`input[value="${CSS.escape(valorGuardado)}"]`);
                                if (checkbox) checkbox.checked = true;
                            });
                        }
                    });
            } else {
                criarBlocoPersonalizado(nomeAtributo, valoresGuardados.join(', '));
                return Promise.resolve();
            }
        });

        Promise.all(promises).then(() => {
            document.dispatchEvent(new CustomEvent('atributosProntos'));
        });
    };
    
    // CORREÇÃO: Verifica se a variável `atributosGuardados` existe e não está vazia.
    if (typeof atributosGuardados !== 'undefined' && Object.keys(atributosGuardados).length > 0) {
        carregarAtributosGuardados(atributosGuardados);
    } else {
        document.dispatchEvent(new CustomEvent('atributosProntos'));
    }
});