// public/js/gestao_variacoes.js

document.addEventListener('DOMContentLoaded', () => {
    const atributosContainer = document.querySelector('.atributos-container');
    const variacoesWrapper = document.getElementById('variacoes-container');
    const hiddenVariacoesInput = document.getElementById('hidden-variacoes-json');

    if (!atributosContainer || !variacoesWrapper) return;

    let estado = {
        atributos: {}, 
        gruposParaVariacao: new Set(), 
        variacoesGuardadas: [],
        variacoesParaExibir: [], 
    };

    function atributosSaoIguais(objA, objB) {
        const keysA = Object.keys(objA).sort();
        const keysB = Object.keys(objB).sort();
        if (keysA.length !== keysB.length) return false;
        for (let i = 0; i < keysA.length; i++) {
            if (keysA[i] !== keysB[i] || objA[keysA[i]] !== objB[keysB[i]]) {
                return false;
            }
        }
        return true;
    }
    
    function lerAtributosDoDOM() {
        const novosAtributos = {};
        document.querySelectorAll('.atributo-bloco').forEach(bloco => {
            const nomeAtributo = bloco.dataset.nomeAtributo || bloco.querySelector('.input-nome-personalizado')?.value.trim();
            if (!nomeAtributo) return;

            const valores = new Set();
            if (bloco.dataset.nomeAtributo) { 
                bloco.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => valores.add(cb.value.trim()));
            } else { 
                const valoresInput = bloco.querySelector('.input-valores-personalizado')?.value;
                if (valoresInput) {
                    valoresInput.split(',').map(v => v.trim()).filter(Boolean).forEach(v => valores.add(v));
                }
            }
            if (valores.size > 0) novosAtributos[nomeAtributo] = Array.from(valores);
        });
        estado.atributos = novosAtributos;

        const nomesAtributosAtuais = Object.keys(estado.atributos);
        estado.gruposParaVariacao.forEach(grupo => {
            if (!nomesAtributosAtuais.includes(grupo)) {
                estado.gruposParaVariacao.delete(grupo);
            }
        });

        renderizarControlosDeVariacao();
    }
    
    function renderizarControlosDeVariacao() {
        const todosOsGrupos = new Set([...Object.keys(estado.atributos), ...Array.from(estado.gruposParaVariacao)]);

        if (todosOsGrupos.size === 0) {
            variacoesWrapper.innerHTML = `<div class="variacoes-placeholder"><p>Adicione atributos ao produto para poder gerir o stock.</p></div>`;
            return;
        }

        let html = '<div class="variacoes-controlos"><h4>Gerar Variações de Stock com Base em:</h4>';
        todosOsGrupos.forEach(nome => {
            const isChecked = estado.gruposParaVariacao.has(nome) ? 'checked' : '';
            html += `<label class="filtro-checkbox"><input type="checkbox" class="variacao-control-checkbox" data-grupo-nome="${nome}" ${isChecked}> ${nome}</label>`;
        });
        html += '</div><div class="variacoes-table-wrapper"></div>';
        
        variacoesWrapper.innerHTML = html;
        gerarCombinacoesDeVariacoes();
    }
    
    function gerarCombinacoesDeVariacoes() {
        const gruposSelecionados = Array.from(estado.gruposParaVariacao);
        if (gruposSelecionados.length === 0) {
            estado.variacoesParaExibir = [];
            renderizarTabelaDeVariacoes();
            return;
        }

        const arraysParaCombinar = gruposSelecionados
            .map(nome => (estado.atributos[nome] || []).map(valor => ({ [nome]: valor })))
            .filter(arr => arr.length > 0);

        if (arraysParaCombinar.length !== gruposSelecionados.length) {
             estado.variacoesParaExibir = [];
             renderizarTabelaDeVariacoes();
             return;
        }

        let combinacoes = [{}];
        for (const array of arraysParaCombinar) {
            combinacoes = combinacoes.flatMap(d => array.map(e => ({ ...d, ...e })));
        }
        const novasCombinacoes = combinacoes[0] && Object.keys(combinacoes[0]).length > 0 ? combinacoes : [];

        estado.variacoesParaExibir = novasCombinacoes.map(combinacao => {
            const variacaoExistente = estado.variacoesGuardadas.find(v => v.atributos && atributosSaoIguais(v.atributos, combinacao));
            return {
                atributos: combinacao,
                quantidade: variacaoExistente?.quantidade ?? '',
                referencia: variacaoExistente?.referencia || '',
                imagens_associadas: variacaoExistente?.imagens_associadas || []
            };
        });
        renderizarTabelaDeVariacoes();
    }

    function criarSeletorDeImagens(variacaoIndex, imagensAssociadas = []) {
        const imagensGaleria = window.getImagensGaleria();
        if (imagensGaleria.length === 0) {
            return '<span class="sem-imagens-aviso">Adicione imagens ao produto</span>';
        }

        const containerId = `image-selector-${variacaoIndex}`;
        const radioGroupName = `variacao-imagem-${variacaoIndex}`;
        
        let thumbnailsHtml = imagensGaleria.map((img, imgIndex) => {
            const imageId = (img.tipo === 'existente') ? String(img.id) : img.placeholder;
            const isChecked = imagensAssociadas.length > 0 && imagensAssociadas[0] === imageId;

            return `
                <label class="variacao-imagem-thumbnail ${isChecked ? 'selecionada' : ''}">
                    <input type="radio" 
                           name="${radioGroupName}"
                           data-variacao-index="${variacaoIndex}" 
                           data-image-id="${imageId}" 
                           ${isChecked ? 'checked' : ''}>
                    <img src="${img.url || img.dados}" alt="Imagem ${imgIndex + 1}">
                </label>
            `;
        }).join('');

        return `<div class="variacao-imagens-container" id="${containerId}">${thumbnailsHtml}</div>`;
    }

    function renderizarTabelaDeVariacoes() {
        const container = variacoesWrapper.querySelector('.variacoes-table-wrapper');
        if (!container) return;

        if (estado.variacoesParaExibir.length === 0) {
            container.innerHTML = (estado.gruposParaVariacao.size > 0) ? '<p class="variacoes-placeholder">Nenhuma variação para exibir.</p>' : '';
            return;
        }

        const headers = Object.keys(estado.variacoesParaExibir[0].atributos);
        const headHtml = headers.map(h => `<th class="col-atributo">${h}</th>`).join('') + 
                         '<th class="col-stock">Stock</th>' + 
                         '<th class="col-sku">Referência (SKU)</th>' + 
                         '<th class="col-images">Imagem da Variação</th>';

        const bodyHtml = estado.variacoesParaExibir.map((variacao, index) => {
            const rowHtml = headers.map(header => `<td class="col-atributo" data-label="${header}">${variacao.atributos[header]}</td>`).join('');
            const seletorImagensHtml = criarSeletorDeImagens(index, variacao.imagens_associadas);
            return `<tr data-variacao-index="${index}">
                ${rowHtml}
                <td class="col-stock">
                    <div class="input-with-icon">
                        <input type="number" class="variacao-input" data-field="quantidade" value="${variacao.quantidade}" placeholder="0" min="0">
                    </div>
                </td>
                <td class="col-sku">
                    <input type="text" class="variacao-input" data-field="referencia" value="${variacao.referencia}" placeholder="SKU">
                </td>
                <td class="col-images">${seletorImagensHtml}</td>
            </tr>`;
        }).join('');

        container.innerHTML = `<div class="table-wrapper"><table class="admin-table variacoes-table"><thead><tr>${headHtml}</tr></thead><tbody>${bodyHtml}</tbody></table></div>`;
    }

    variacoesWrapper.addEventListener('change', (e) => {
        if (e.target.classList.contains('variacao-control-checkbox')) {
            const nomeGrupo = e.target.dataset.grupoNome;
            e.target.checked ? estado.gruposParaVariacao.add(nomeGrupo) : estado.gruposParaVariacao.delete(nomeGrupo);
            gerarCombinacoesDeVariacoes();
        } 
        else if (e.target.matches('.variacao-imagem-thumbnail input[type="radio"]')) {
            const radio = e.target;
            const variacaoIndex = radio.dataset.variacaoIndex;
            const imageId = radio.dataset.imageId;
            const variacao = estado.variacoesParaExibir[variacaoIndex];
            
            variacao.imagens_associadas = [imageId];

            const radioGroup = document.getElementsByName(radio.name);
            radioGroup.forEach(rb => {
                rb.closest('label').classList.toggle('selecionada', rb.checked);
            });
        }
    });
    
    variacoesWrapper.addEventListener('input', (e) => {
        if (e.target.classList.contains('variacao-input')) {
            const index = e.target.closest('tr').dataset.variacaoIndex;
            const field = e.target.dataset.field;
            if (estado.variacoesParaExibir[index]) {
                estado.variacoesParaExibir[index][field] = e.target.value;
            }
        }
    });

    atributosContainer.addEventListener('input', debounce(lerAtributosDoDOM, 400));
    new MutationObserver(debounce(lerAtributosDoDOM, 400)).observe(atributosContainer, { childList: true, subtree: true });

    document.addEventListener('galeriaAtualizada', () => {
        if (estado.variacoesParaExibir.length > 0) {
            renderizarTabelaDeVariacoes();
        }
    });

    window.compilarVariacoesParaJSON = () => {
        for (const variacao of estado.variacoesParaExibir) {
            if (variacao.quantidade === '' || variacao.quantidade === null || isNaN(parseInt(variacao.quantidade, 10))) {
                mostrarPopup('Por favor, preencha o campo "Stock" para todas as variações geradas.', 'erro');
                return false;
            }
            delete variacao.preco;
        }
        hiddenVariacoesInput.value = JSON.stringify(estado.variacoesParaExibir);
        return true;
    }

    document.addEventListener('atributosProntos', () => {
        if (typeof variacoesGuardadas !== 'undefined' && Array.isArray(variacoesGuardadas)) {
            estado.variacoesGuardadas = JSON.parse(JSON.stringify(variacoesGuardadas));
            variacoesGuardadas.forEach(v => {
                if (v.atributos) Object.keys(v.atributos).forEach(g => estado.gruposParaVariacao.add(g));
            });
        }
        lerAtributosDoDOM();
    });

    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }
});