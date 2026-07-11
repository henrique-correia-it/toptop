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

document.addEventListener('DOMContentLoaded', function() {
    const fecharSelects = function(excecao) {
        document.querySelectorAll('.select-wrapper.is-open').forEach(function(wrapper) {
            if (wrapper !== excecao) {
                wrapper.classList.remove('is-open');
                const trigger = wrapper.querySelector('.custom-select-trigger');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            }
        });
    };

    const textoOpcao = function(option) {
        return option ? option.textContent.trim() : '';
    };

    const ajustarLarguraCustomSelect = function(select, trigger, lista) {
        if (!select || !trigger || !lista) return;

        const margemViewport = 32;
        const larguraTrigger = trigger.getBoundingClientRect().width || trigger.offsetWidth || 0;
        const larguraMaxima = Math.max(180, Math.min(320, window.innerWidth - margemViewport));
        let larguraTexto = 0;

        const medidor = document.createElement('span');
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

        const larguraConteudo = larguraTexto + 70;
        const larguraIdeal = Math.min(Math.max(larguraTrigger, larguraConteudo), larguraMaxima);
        lista.style.setProperty('--custom-select-menu-width', Math.ceil(larguraIdeal) + 'px');

        const rect = trigger.getBoundingClientRect();
        lista.classList.toggle('align-right', rect.left + larguraIdeal > window.innerWidth - 16);
    };

    const atualizarCustomSelect = function(select) {
        const wrapper = select.closest('.select-wrapper');
        if (!wrapper) return;

        let trigger = wrapper.querySelector('.custom-select-trigger');
        let lista = wrapper.querySelector('.custom-select-options');
        if (!trigger || !lista) return;

        const selecionada = select.options[select.selectedIndex] || select.options[0];
        trigger.textContent = textoOpcao(selecionada);
        trigger.disabled = select.disabled;

        lista.innerHTML = '';
        Array.from(select.options).forEach(function(option) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'custom-select-option';
            item.textContent = textoOpcao(option);
            item.dataset.value = option.value;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', option.selected ? 'true' : 'false');

            if (option.selected) item.classList.add('is-selected');
            if (option.disabled) {
                item.classList.add('is-disabled');
                item.disabled = true;
            }

            item.addEventListener('click', function() {
                if (option.disabled) return;
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                wrapper.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.focus();
            });

            lista.appendChild(item);
        });

        ajustarLarguraCustomSelect(select, trigger, lista);
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

        const lista = document.createElement('div');
        lista.className = 'custom-select-options';
        lista.setAttribute('role', 'listbox');

        select.insertAdjacentElement('afterend', trigger);
        trigger.insertAdjacentElement('afterend', lista);

        trigger.addEventListener('click', function() {
            const vaiAbrir = !wrapper.classList.contains('is-open');
            fecharSelects(wrapper);
            ajustarLarguraCustomSelect(select, trigger, lista);
            wrapper.classList.toggle('is-open', vaiAbrir);
            trigger.setAttribute('aria-expanded', vaiAbrir ? 'true' : 'false');
        });

        trigger.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' || event.key === 'Tab') {
                wrapper.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });

        select.addEventListener('change', function() {
            atualizarCustomSelect(select);
        });

        new MutationObserver(function() {
            atualizarCustomSelect(select);
        }).observe(select, { childList: true, subtree: true, attributes: true });

        atualizarCustomSelect(select);
    };

    const inicializarTodos = function(root) {
        root.querySelectorAll('.select-wrapper select').forEach(inicializarCustomSelect);
    };

    inicializarTodos(document);

    window.addEventListener('resize', function() {
        document.querySelectorAll('.select-wrapper.custom-select-wrapper select').forEach(function(select) {
            const wrapper = select.closest('.select-wrapper');
            if (!wrapper) return;
            ajustarLarguraCustomSelect(
                select,
                wrapper.querySelector('.custom-select-trigger'),
                wrapper.querySelector('.custom-select-options')
            );
        });
    });

    new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType !== 1) return;
                if (node.matches && node.matches('.select-wrapper select')) {
                    inicializarCustomSelect(node);
                }
                if (node.querySelectorAll) {
                    inicializarTodos(node);
                }
            });
        });
    }).observe(document.body, { childList: true, subtree: true });

    document.addEventListener('pointerdown', function(event) {
        if (!event.target.closest('.select-wrapper')) {
            fecharSelects();
        }
    });
});
