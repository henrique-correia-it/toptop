/* Página "Portes e Envios" (envios.php):
   - localiza o nome nativo dos países no browser (fallback quando o servidor
     não tem a extensão Intl do PHP);
   - acordeão por país (delegação de eventos, sem onclick inline);
   - filtro de pesquisa por país (sem onkeyup inline). */
(function () {
    'use strict';

    function normalizarTexto(str) {
        return String(str || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    // Preenche o nome nativo do país quando o servidor não o conseguiu resolver.
    function localizarNomesPaises() {
        if (typeof Intl === 'undefined' || typeof Intl.DisplayNames !== 'function') return;
        const isoToLocale = {
            CH: 'de', LU: 'fr', BE: 'fr', GB: 'en', IE: 'en', AT: 'de',
            SE: 'sv', BR: 'pt', DK: 'da', CZ: 'cs', GR: 'el', SI: 'sl',
            EE: 'et', UA: 'uk', NO: 'nb', CY: 'el', MT: 'mt', US: 'en', CA: 'en', AU: 'en'
        };
        document.querySelectorAll('.accordion-pais').forEach(function (acc) {
            try {
                const isoEl = acc.querySelector('.iso-code');
                const label = acc.querySelector('.badge-pais');
                if (!isoEl || !label) return;

                const iso = isoEl.textContent.trim();
                const restante = label.textContent.replace(iso, '').trim();
                if (restante && restante !== iso) return; // já tem nome

                const upper = iso.toUpperCase();
                const locale = isoToLocale[upper] || iso.toLowerCase();
                const nativeName = new Intl.DisplayNames([locale], { type: 'region' }).of(upper);

                if (nativeName) {
                    label.textContent = '';
                    const span = document.createElement('span');
                    span.className = 'iso-code';
                    span.textContent = iso;
                    label.appendChild(span);
                    label.appendChild(document.createTextNode(' ' + nativeName));
                    acc.setAttribute('data-pais', normalizarTexto(nativeName + ' ' + iso));
                }
            } catch (e) {
                // Ignora país individual com locale inválido sem abortar os restantes
            }
        });
    }

    function filtrarTabela(termo) {
        const filtro = normalizarTexto(termo);
        let visiveis = 0;
        document.querySelectorAll('.accordion-pais').forEach(function (acc) {
            const pais = normalizarTexto(acc.getAttribute('data-pais') || '');
            const match = pais.indexOf(filtro) !== -1;
            acc.style.display = match ? '' : 'none';
            if (match) visiveis++;
        });
        const vazio = document.getElementById('portesSemResultados');
        if (vazio) vazio.style.display = visiveis === 0 ? '' : 'none';
    }

    document.addEventListener('DOMContentLoaded', function () {
        localizarNomesPaises();

        // Acordeão (delegação): abrir/fechar país ao clicar no cabeçalho.
        const lista = document.getElementById('listaPortes');
        if (lista) {
            lista.addEventListener('click', function (e) {
                const header = e.target.closest('.accordion-header');
                if (header) {
                    const isOpen = header.parentElement.classList.toggle('active');
                    header.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                }
            });
        }

        // Filtro de pesquisa por país.
        const input = document.getElementById('filtroPais');
        if (input) {
            input.addEventListener('input', function () { filtrarTabela(input.value); });
        }
    });
})();
