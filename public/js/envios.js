/* Página "Portes e Envios" (envios.php):
   - localiza o nome nativo dos países no browser (fallback quando o servidor
     não tem a extensão Intl do PHP);
   - acordeão por país (delegação de eventos, sem onclick inline);
   - filtro de pesquisa por país (sem onkeyup inline). */
(function () {
    'use strict';

    // Preenche o nome nativo do país quando o servidor não o conseguiu resolver.
    // iso vem dos dados do servidor e nativeName da API Intl — sem input do utilizador.
    function localizarNomesPaises() {
        try {
            document.querySelectorAll('.accordion-pais').forEach(function (acc) {
                const isoEl = acc.querySelector('.iso-code');
                const label = acc.querySelector('.badge-pais');
                if (!isoEl || !label) return;

                const iso = isoEl.textContent.trim();
                const restante = label.textContent.replace(iso, '').trim();
                if (restante && restante !== iso) return; // já tem nome

                const isoToLocale = { CH: 'de', LU: 'fr', BE: 'fr', GB: 'en', IE: 'en', AT: 'de' };
                const locale = isoToLocale[iso.toUpperCase()] || iso.toLowerCase();
                const nativeName = new Intl.DisplayNames([locale], { type: 'region' }).of(iso.toUpperCase());

                if (nativeName) {
                    label.innerHTML = '<span class="iso-code">' + iso + '</span> ' + nativeName;
                    acc.setAttribute('data-pais', nativeName.toLowerCase() + ' ' + iso.toLowerCase());
                }
            });
        } catch (e) {
            console.warn('Intl.DisplayNames não suportado ou erro na localização:', e);
        }
    }

    function filtrarTabela(termo) {
        const filtro = (termo || '').toLowerCase();
        document.querySelectorAll('.accordion-pais').forEach(function (acc) {
            const pais = acc.getAttribute('data-pais') || '';
            acc.style.display = pais.indexOf(filtro) !== -1 ? '' : 'none';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        localizarNomesPaises();

        // Acordeão (delegação): abrir/fechar país ao clicar no cabeçalho.
        const lista = document.getElementById('listaPortes');
        if (lista) {
            lista.addEventListener('click', function (e) {
                const header = e.target.closest('.accordion-header');
                if (header) header.parentElement.classList.toggle('active');
            });
        }

        // Filtro de pesquisa por país.
        const input = document.getElementById('filtroPais');
        if (input) {
            input.addEventListener('input', function () { filtrarTabela(input.value); });
        }
    });
})();
