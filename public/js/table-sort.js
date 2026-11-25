// public/js/table-sort.js - Ordenação, filtros, pesquisa e paginação sem refresh (AJAX + client-side fallback)
document.addEventListener('DOMContentLoaded', function () {
    const NON_SORTABLE = new Set(['', 'foto', 'ações', 'acoes', 'ação', 'acao']);
    let activeController = null;

    // ── 0. Botão "×" limpar pesquisa em todos os ui_search_input() do site ──
    document.querySelectorAll('.search-input-wrap').forEach(wrap => {
        const input = wrap.querySelector('input');
        const clearBtn = wrap.querySelector('.search-clear-btn');
        if (!input || !clearBtn) return;

        const sync = () => wrap.classList.toggle('has-value', input.value.length > 0);
        sync();
        input.addEventListener('input', sync);
        clearBtn.addEventListener('click', () => {
            input.value = '';
            sync();
            input.focus();
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    function loadTableUrl(targetUrl, table, opts) {
        opts = opts || {};
        if (!table) return;

        if (activeController) {
            activeController.abort();
        }
        activeController = typeof AbortController === 'function' ? new AbortController() : null;

        table.classList.add('is-sorting');

        // Captura larguras das colunas visíveis apenas quando pedido (ordenar) e há linhas reais
        if (opts.lockWidths && !table._lockedWidths && table.tHead && table.offsetWidth > 0) {
            const totalW = table.getBoundingClientRect().width;
            const curThs = Array.from(table.tHead.querySelectorAll('th'));
            if (totalW > 0 && curThs.length > 0) {
                table._lockedWidths = curThs.map(h => {
                    const w = h.getBoundingClientRect().width;
                    return w > 0 ? ((w / totalW) * 100).toFixed(3) + '%' : '';
                });
            }
        }

        const fetchOpts = { headers: { 'X-Requested-With': 'XMLHttpRequest' } };
        if (activeController) fetchOpts.signal = activeController.signal;

        fetch(targetUrl, fetchOpts)
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const wrap = table.closest('.table-wrapper');
                const newWrap = wrap && wrap.id
                    ? doc.getElementById(wrap.id)
                    : doc.querySelector('table.admin-table')?.closest('.table-wrapper');
                const newTable = newWrap ? newWrap.querySelector('table') : doc.querySelector('table.admin-table');

                if (!newTable || !newTable.tHead || !newTable.tBodies[0] || !table.tHead || !table.tBodies[0]) {
                    window.location.href = targetUrl;
                    return;
                }

                // Substitui apenas o conteúdo interno para preservar todos os event listeners ligados ao <tbody>
                table.tHead.innerHTML = newTable.tHead.innerHTML;
                table.tBodies[0].innerHTML = newTable.tBodies[0].innerHTML;

                // Se passou para estado vazio, liberta table-layout para não distorcer colspan
                const hasEmpty = Boolean(table.tBodies[0].querySelector('td[colspan]'));
                if (hasEmpty) {
                    table.style.tableLayout = '';
                } else if (opts.lockWidths && Array.isArray(table._lockedWidths)) {
                    const newThs = Array.from(table.tHead.querySelectorAll('th'));
                    if (newThs.length === table._lockedWidths.length) {
                        newThs.forEach((h, i) => {
                            if (table._lockedWidths[i]) h.style.width = table._lockedWidths[i];
                        });
                        table.style.tableLayout = 'fixed';
                    }
                }

                // Atualiza ou esconde paginação
                const curPag = document.querySelector('.pagination-container');
                const newPag = doc.querySelector('.pagination-container');
                if (curPag && newPag) {
                    curPag.innerHTML = newPag.innerHTML;
                    curPag.style.display = '';
                } else if (curPag && !newPag) {
                    curPag.innerHTML = '';
                    curPag.style.display = 'none';
                } else if (!curPag && newPag && wrap) {
                    wrap.insertAdjacentElement('afterend', newPag);
                }

                // Sincroniza inputs hidden sort/dir nos filtros da toolbar
                const urlObj = new URL(targetUrl, window.location.origin);
                const newSort = urlObj.searchParams.get('sort') || '';
                const newDir  = urlObj.searchParams.get('dir') || '';
                document.querySelectorAll('.prod-toolbar form').forEach(form => {
                    ['sort', 'dir'].forEach(name => {
                        const val = name === 'sort' ? newSort : newDir;
                        let inp = form.querySelector('input[type="hidden"][name="' + name + '"]');
                        if (val) {
                            if (!inp) {
                                inp = document.createElement('input');
                                inp.type = 'hidden';
                                inp.name = name;
                                form.prepend(inp);
                            }
                            inp.value = val;
                        } else if (inp) {
                            inp.remove();
                        }
                    });
                });

                window.history.replaceState(null, '', targetUrl);
            })
            .catch(err => {
                if (err && err.name === 'AbortError') return;
                window.location.href = targetUrl;
            })
            .finally(() => {
                table.classList.remove('is-sorting');
            });
    }

    function buildFormUrl(form) {
        const params = new URLSearchParams();
        new FormData(form).forEach((val, key) => {
            const s = String(val).trim();
            if (s !== '') params.set(key, s);
        });
        const qs = params.toString();
        const base = window.location.pathname;
        return qs ? (base + '?' + qs) : base;
    }

    // ── 1. Ordenação por cabeçalho (sem refresh) ──
    document.addEventListener('click', function (e) {
        const th = e.target.closest('th.is-sortable');
        if (!th) return;
        const link = th.querySelector('a.th-sort-link[href]');
        if (!link) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;

        e.preventDefault();
        const table = th.closest('table');
        if (!table) return;

        const dataRows = table.tBodies[0]
            ? Array.from(table.tBodies[0].querySelectorAll('tr')).filter(tr => !tr.querySelector('td[colspan]'))
            : [];
        if (dataRows.length < 2) return;

        loadTableUrl(link.getAttribute('href'), table, { lockWidths: true });
    });

    // ── 2. Filtros e pesquisa da toolbar (sem refresh) ──
    document.querySelectorAll('.prod-toolbar form[method="GET"], .prod-toolbar form[method="get"]').forEach(form => {
        const table = document.querySelector('#table-wrapper table.admin-table, table.admin-table');
        if (!table) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            loadTableUrl(buildFormUrl(form), table, { lockWidths: false });
        });

        const searchInput = form.querySelector('.search-input-toolbar');
        if (searchInput) {
            let debounceTimer = null;
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    loadTableUrl(buildFormUrl(form), table, { lockWidths: false });
                }, 280);
            });
        }
    });

    // ── 3. Paginação (sem refresh) ──
    document.addEventListener('click', function (e) {
        const pagLink = e.target.closest('.pagination-container a[href]');
        if (!pagLink) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;
        const table = document.querySelector('#table-wrapper table.admin-table, table.admin-table');
        if (!table) return;

        e.preventDefault();
        loadTableUrl(pagLink.getAttribute('href'), table, { lockWidths: false });
    });

    // ── 4. Ordenação client-side para tabelas não paginadas (data-client-sort="1") ──
    function parseValue(raw) {
        const text = (raw || '').trim();
        if (!text || text === '-') return { type: 'empty', val: '' };

        const dMatch = text.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        if (dMatch) {
            const ts = new Date(
                parseInt(dMatch[3], 10),
                parseInt(dMatch[2], 10) - 1,
                parseInt(dMatch[1], 10),
                parseInt(dMatch[4] || '0', 10),
                parseInt(dMatch[5] || '0', 10),
                parseInt(dMatch[6] || '0', 10)
            ).getTime();
            return { type: 'num', val: ts };
        }

        const wMatch = text.match(/^(\d+(?:[.,]\d+)?)\s*kg/i);
        if (wMatch) {
            return { type: 'num', val: parseFloat(wMatch[1].replace(',', '.')) };
        }

        const cleaned = text.replace(/[€£$#\s]/g, '').replace(/\.(?=\d{3}(?:[,.]|$))/g, '').replace(',', '.');
        if (/^-?\d+(?:\.\d+)?$/.test(cleaned)) {
            return { type: 'num', val: parseFloat(cleaned) };
        }

        return { type: 'str', val: text.toLocaleLowerCase('pt') };
    }

    function compareCells(aRaw, bRaw, dir) {
        const a = parseValue(aRaw);
        const b = parseValue(bRaw);
        if (a.type === 'empty' && b.type === 'empty') return 0;
        if (a.type === 'empty') return 1;
        if (b.type === 'empty') return -1;
        const mult = dir === 'desc' ? -1 : 1;
        if (a.type === 'num' && b.type === 'num') {
            return (a.val - b.val) * mult;
        }
        return String(a.val).localeCompare(String(b.val), 'pt', { numeric: true }) * mult;
    }

    document.querySelectorAll('table[data-client-sort="1"]').forEach(table => {
        const thead = table.tHead;
        const tbody = table.tBodies[0];
        if (!thead || !tbody) return;

        const ths = Array.from(thead.querySelectorAll('th'));
        Array.from(tbody.querySelectorAll('tr')).forEach((tr, idx) => {
            tr.dataset.origIdx = String(idx);
        });
        ths.forEach((th, colIdx) => {
            if (th.classList.contains('col-sel') || th.hasAttribute('data-no-sort') || th.querySelector('.th-sort-link')) {
                return;
            }
            const label = th.textContent.trim();
            if (NON_SORTABLE.has(label.toLocaleLowerCase('pt'))) return;

            th.classList.add('is-sortable');
            th.setAttribute('aria-sort', 'none');

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'th-sort-link';
            btn.title = 'Ordenar por ' + label;
            const span = document.createElement('span');
            span.textContent = label;
            btn.appendChild(span);
            th.textContent = '';
            th.appendChild(btn);

            th.addEventListener('click', () => {
                const rows = Array.from(tbody.querySelectorAll('tr')).filter(tr => !tr.querySelector('td[colspan]'));
                if (rows.length < 2) return;

                const curDir = th.dataset.sortDir === 'asc' ? 'asc' : (th.dataset.sortDir === 'desc' ? 'desc' : '');
                const nextDir = curDir === '' ? 'asc' : (curDir === 'asc' ? 'desc' : '');

                ths.forEach(other => {
                    if (other.classList.contains('is-sortable')) {
                        other.classList.remove('is-sorted');
                        other.setAttribute('aria-sort', 'none');
                        delete other.dataset.sortDir;
                        const ic = other.querySelector('.th-sort-icon');
                        if (ic) ic.remove();
                    }
                });

                if (nextDir === '') {
                    rows.sort((ra, rb) => (parseInt(ra.dataset.origIdx || '0', 10) - parseInt(rb.dataset.origIdx || '0', 10)));
                    rows.forEach(r => tbody.appendChild(r));
                    return;
                }

                th.classList.add('is-sorted');
                th.dataset.sortDir = nextDir;
                th.setAttribute('aria-sort', nextDir === 'asc' ? 'ascending' : 'descending');
                btn.insertAdjacentHTML(
                    'beforeend',
                    '<svg class="th-sort-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                    (nextDir === 'asc' ? '<path d="M18 15l-6-6-6 6"/>' : '<path d="M6 9l6 6 6-6"/>') +
                    '</svg>'
                );

                rows.sort((ra, rb) => {
                    const ca = ra.children[colIdx] ? ra.children[colIdx].textContent : '';
                    const cb = rb.children[colIdx] ? rb.children[colIdx].textContent : '';
                    return compareCells(ca, cb, nextDir);
                });
                rows.forEach(r => tbody.appendChild(r));
            });
        });
    });
});
