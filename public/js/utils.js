// public/js/utils.js

/**
 * Formata um número de forma idêntica à função number_format do PHP.
 * @param {number} number O número a ser formatado.
 * @param {number} decimals O número de casas decimais.
 * @param {string} dec_point O separador para os decimais.
 * @param {string} thousands_sep O separador para os milhares.
 * @returns {string} O número formatado.
 */
function number_format(number, decimals, dec_point, thousands_sep) {
    number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
    var n = !isFinite(+number) ? 0 : +number,
        prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
        sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
        dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
        s = '',
        toFixedFix = function(n, prec) {
            var k = Math.pow(10, prec);
            return '' + Math.round(n * k) / k;
        };
    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
    if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    if ((s[1] || '').length < prec) {
        s[1] = s[1] || '';
        s[1] += new Array(prec - s[1].length + 1).join('0');
    }
    return s.join(dec);
}

(function() {
    const limparFocoOpcoes = function(lista) {
        if (!lista) return;
        lista.querySelectorAll('.custom-select-option.is-focused').forEach(function(el) {
            el.classList.remove('is-focused');
        });
    };

    const fecharSelects = function(excecao) {
        document.querySelectorAll('.select-wrapper.is-open').forEach(function(wrapper) {
            if (wrapper !== excecao) {
                wrapper.classList.remove('is-open', 'drop-up');
                const trigger = wrapper.querySelector('.custom-select-trigger');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
                limparFocoOpcoes(wrapper.querySelector('.custom-select-options'));
            }
        });
    };

    const textoOpcao = function(option) {
        return option ? option.textContent.trim() : '';
    };

    const ajustarPosicaoELarguraCustomSelect = function(select, trigger, lista) {
        if (!select || !trigger || !lista) return;
        const wrapper = select.closest('.select-wrapper');
        if (!wrapper) return;

        const margemViewport = 24;
        const rect = trigger.getBoundingClientRect();
        const larguraTrigger = rect.width || trigger.offsetWidth || 0;
        const larguraMaxima = Math.max(200, Math.min(380, window.innerWidth - margemViewport));
        let larguraTexto = 0;

        const medidor = document.createElement('span');
        medidor.dataset.customSelectMeasure = '1';
        const estiloTrigger = window.getComputedStyle(trigger);
        medidor.style.position = 'fixed';
        medidor.style.left = '-9999px';
        medidor.style.top = '-9999px';
        medidor.style.visibility = 'hidden';
        medidor.style.whiteSpace = 'nowrap';
        medidor.style.fontFamily = estiloTrigger.fontFamily;
        medidor.style.fontSize = estiloTrigger.fontSize;
        medidor.style.fontWeight = estiloTrigger.fontWeight;
        document.body.appendChild(medidor);

        Array.from(select.options).forEach(function(option) {
            medidor.textContent = textoOpcao(option);
            larguraTexto = Math.max(larguraTexto, medidor.offsetWidth);
        });

        medidor.remove();

        const larguraConteudo = larguraTexto + 64;
        const larguraIdeal = Math.min(Math.max(larguraTrigger, larguraConteudo), larguraMaxima);
        lista.style.setProperty('--custom-select-menu-width', Math.ceil(larguraIdeal) + 'px');
        lista.classList.toggle('align-right', rect.left + larguraIdeal > window.innerWidth - 12);

        // Abre para cima se o espaço inferior for reduzido e houver mais espaço acima
        const espacoAbaixo = window.innerHeight - rect.bottom;
        const espacoAcima = rect.top;
        const deveSubir = espacoAbaixo < 230 && espacoAcima > espacoAbaixo;
        wrapper.classList.toggle('drop-up', deveSubir);
    };

    const focarOpcaoPorIndice = function(lista, index) {
        const opcoes = Array.from(lista.querySelectorAll('.custom-select-option:not(.is-disabled)'));
        if (!opcoes.length) return;
        const clamped = Math.max(0, Math.min(index, opcoes.length - 1));
        limparFocoOpcoes(lista);
        const alvo = opcoes[clamped];
        alvo.classList.add('is-focused');
        alvo.scrollIntoView({ block: 'nearest' });
    };

    const selecionarOpcao = function(select, wrapper, trigger, optionValue) {
        if (select.value !== optionValue) {
            select.value = optionValue;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            atualizarCustomSelect(select);
        }
        wrapper.classList.remove('is-open', 'drop-up');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.focus();
    };

    const atualizarCustomSelect = function(select) {
        const wrapper = select.closest('.select-wrapper');
        if (!wrapper) return;

        let trigger = wrapper.querySelector('.custom-select-trigger');
        let lista = wrapper.querySelector('.custom-select-options');
        if (!trigger || !lista) return;

        const selecionada = select.options[select.selectedIndex] || select.options[0];
        let labelSpan = trigger.querySelector('.custom-select-label');
        if (!labelSpan) {
            labelSpan = document.createElement('span');
            labelSpan.className = 'custom-select-label';
            trigger.textContent = '';
            trigger.appendChild(labelSpan);
        }
        labelSpan.textContent = textoOpcao(selecionada);

        const isDisabled = select.disabled || select.classList.contains('disabled');
        trigger.disabled = isDisabled;
        wrapper.classList.toggle('is-disabled', isDisabled);
        if (select.title) {
            trigger.title = select.title;
        }

        lista.innerHTML = '';
        Array.from(select.options).forEach(function(option) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'custom-select-option';
            item.textContent = textoOpcao(option);
            item.dataset.value = option.value;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            item.tabIndex = -1;

            if (option.selected) item.classList.add('is-selected');
            if (option.disabled) {
                item.classList.add('is-disabled');
                item.disabled = true;
            }

            item.addEventListener('click', function(e) {
                e.stopPropagation();
                if (option.disabled) return;
                selecionarOpcao(select, wrapper, trigger, option.value);
            });

            lista.appendChild(item);
        });

        ajustarPosicaoELarguraCustomSelect(select, trigger, lista);
    };

    const abrirCustomSelect = function(select, wrapper, trigger, lista) {
        fecharSelects(wrapper);
        ajustarPosicaoELarguraCustomSelect(select, trigger, lista);
        wrapper.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');

        const opcoesAtivas = Array.from(lista.querySelectorAll('.custom-select-option:not(.is-disabled)'));
        const idxSel = opcoesAtivas.findIndex(function(el) { return el.classList.contains('is-selected'); });
        focarOpcaoPorIndice(lista, idxSel >= 0 ? idxSel : 0);
    };

    const inicializarCustomSelect = function(select) {
        const wrapper = select.closest('.select-wrapper');
        if (!wrapper || wrapper.dataset.customSelect === 'true') return;

        wrapper.dataset.customSelect = 'true';
        wrapper.classList.add('custom-select-wrapper');

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'custom-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');

        const labelSpan = document.createElement('span');
        labelSpan.className = 'custom-select-label';
        trigger.appendChild(labelSpan);

        const lista = document.createElement('div');
        lista.className = 'custom-select-options';
        lista.setAttribute('role', 'listbox');

        // Associar label[for="select.id"] ao clique do trigger customizado
        if (select.id) {
            const labelAssociada = document.querySelector('label[for="' + CSS.escape(select.id) + '"]');
            if (labelAssociada && !labelAssociada.dataset.customSelectBound) {
                labelAssociada.dataset.customSelectBound = 'true';
                labelAssociada.addEventListener('click', function(e) {
                    if (trigger.disabled) return;
                    e.preventDefault();
                    trigger.focus();
                    abrirCustomSelect(select, wrapper, trigger, lista);
                });
            }
        }

        select.insertAdjacentElement('afterend', trigger);
        trigger.insertAdjacentElement('afterend', lista);

        trigger.addEventListener('click', function() {
            if (trigger.disabled) return;
            const vaiAbrir = !wrapper.classList.contains('is-open');
            if (vaiAbrir) {
                abrirCustomSelect(select, wrapper, trigger, lista);
            } else {
                wrapper.classList.remove('is-open', 'drop-up');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });

        let typeaheadBuffer = '';
        let typeaheadTimer = null;

        trigger.addEventListener('keydown', function(event) {
            if (trigger.disabled) return;
            const aberto = wrapper.classList.contains('is-open');
            const opcoes = Array.from(lista.querySelectorAll('.custom-select-option:not(.is-disabled)'));
            if (!opcoes.length) return;

            let idxFocado = opcoes.findIndex(function(el) { return el.classList.contains('is-focused'); });
            if (idxFocado < 0) {
                idxFocado = opcoes.findIndex(function(el) { return el.classList.contains('is-selected'); });
            }
            if (idxFocado < 0) idxFocado = 0;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (!aberto) {
                    abrirCustomSelect(select, wrapper, trigger, lista);
                } else {
                    focarOpcaoPorIndice(lista, Math.min(idxFocado + 1, opcoes.length - 1));
                }
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (!aberto) {
                    abrirCustomSelect(select, wrapper, trigger, lista);
                } else {
                    focarOpcaoPorIndice(lista, Math.max(idxFocado - 1, 0));
                }
            } else if (event.key === 'Home' && aberto) {
                event.preventDefault();
                focarOpcaoPorIndice(lista, 0);
            } else if (event.key === 'End' && aberto) {
                event.preventDefault();
                focarOpcaoPorIndice(lista, opcoes.length - 1);
            } else if ((event.key === 'Enter' || event.key === ' ') && aberto) {
                event.preventDefault();
                const focada = opcoes[idxFocado];
                if (focada) {
                    selecionarOpcao(select, wrapper, trigger, focada.dataset.value);
                }
            } else if (event.key === 'Escape' || event.key === 'Tab') {
                if (aberto) {
                    wrapper.classList.remove('is-open', 'drop-up');
                    trigger.setAttribute('aria-expanded', 'false');
                    if (event.key === 'Escape') event.preventDefault();
                }
            } else if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                // Pesquisa rápida por teclado (typeahead)
                typeaheadBuffer += event.key.toLocaleLowerCase();
                clearTimeout(typeaheadTimer);
                typeaheadTimer = setTimeout(function() { typeaheadBuffer = ''; }, 600);

                const matchIdx = opcoes.findIndex(function(el) {
                    return el.textContent.trim().toLocaleLowerCase().startsWith(typeaheadBuffer);
                });
                if (matchIdx >= 0) {
                    if (aberto) {
                        focarOpcaoPorIndice(lista, matchIdx);
                    } else {
                        selecionarOpcao(select, wrapper, trigger, opcoes[matchIdx].dataset.value);
                    }
                }
            }
        });

        select.addEventListener('change', function() {
            atualizarCustomSelect(select);
        });

        // Interceta alterações programáticas a select.value e select.selectedIndex em JS
        const proto = HTMLSelectElement.prototype;
        const valDesc = Object.getOwnPropertyDescriptor(proto, 'value');
        const idxDesc = Object.getOwnPropertyDescriptor(proto, 'selectedIndex');
        if (valDesc && valDesc.get && valDesc.set) {
            Object.defineProperty(select, 'value', {
                configurable: true,
                enumerable: true,
                get: function() { return valDesc.get.call(this); },
                set: function(v) {
                    valDesc.set.call(this, v);
                    atualizarCustomSelect(this);
                }
            });
        }
        if (idxDesc && idxDesc.get && idxDesc.set) {
            Object.defineProperty(select, 'selectedIndex', {
                configurable: true,
                enumerable: true,
                get: function() { return idxDesc.get.call(this); },
                set: function(i) {
                    idxDesc.set.call(this, i);
                    atualizarCustomSelect(this);
                }
            });
        }

        new MutationObserver(function() {
            atualizarCustomSelect(select);
        }).observe(select, { childList: true, subtree: true, attributes: true });

        atualizarCustomSelect(select);
    };

    // Helpers globais da biblioteca de componentes (JS counterpart de ui_select)
    window.uiRefreshSelect = function(select) {
        if (select) atualizarCustomSelect(select);
    };

    window.uiCreateSelect = function(opts = {}) {
        const wrapper = document.createElement('div');
        wrapper.className = 'select-wrapper' + (opts.wrapClass ? ' ' + opts.wrapClass : '');

        const select = document.createElement('select');
        select.className = 'select-estilizado' + (opts.className ? ' ' + opts.className : '');
        if (opts.id) select.id = opts.id;
        if (opts.name) select.name = opts.name;
        if (opts.required) select.required = true;
        if (opts.disabled) select.disabled = true;
        if (opts.dataset && typeof opts.dataset === 'object') {
            Object.keys(opts.dataset).forEach(function(k) {
                select.dataset[k] = opts.dataset[k];
            });
        }

        if (typeof opts.placeholder === 'string') {
            const phOpt = document.createElement('option');
            phOpt.value = '';
            phOpt.textContent = opts.placeholder;
            select.appendChild(phOpt);
        }

        const list = Array.isArray(opts.options) ? opts.options : [];
        list.forEach(function(item) {
            const opt = document.createElement('option');
            if (item && typeof item === 'object') {
                opt.value = String(item.value ?? '');
                opt.textContent = String(item.label ?? item.value ?? '');
                if (item.disabled) opt.disabled = true;
            } else {
                opt.value = String(item ?? '');
                opt.textContent = String(item ?? '');
            }
            if (opts.selected !== undefined && String(opts.selected) === opt.value) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });

        if (typeof opts.onChange === 'function') {
            select.addEventListener('change', opts.onChange);
        }

        wrapper.appendChild(select);
        inicializarCustomSelect(select);
        return { wrapper: wrapper, select: select };
    };

    const inicializarTodos = function(root) {
        if (!root || !root.querySelectorAll) return;
        root.querySelectorAll('.select-wrapper select').forEach(inicializarCustomSelect);
    };

    const bootCustomSelects = function() {
        inicializarTodos(document);
        if (document.body) {
            new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType !== 1 || (node.dataset && node.dataset.customSelectMeasure === '1')) return;
                        if (node.matches && node.matches('.select-wrapper select')) {
                            inicializarCustomSelect(node);
                        }
                        if (node.querySelectorAll) {
                            inicializarTodos(node);
                        }
                    });
                });
            }).observe(document.body, { childList: true, subtree: true });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootCustomSelects, { once: true });
    } else {
        bootCustomSelects();
    }

    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            document.querySelectorAll('.select-wrapper.custom-select-wrapper select').forEach(function(select) {
                const wrapper = select.closest('.select-wrapper');
                if (!wrapper) return;
                ajustarPosicaoELarguraCustomSelect(
                    select,
                    wrapper.querySelector('.custom-select-trigger'),
                    wrapper.querySelector('.custom-select-options')
                );
            });
        }, 120);
    });

    document.addEventListener('pointerdown', function(event) {
        if (!event.target.closest('.select-wrapper')) {
            fecharSelects();
        }
    });
})();
