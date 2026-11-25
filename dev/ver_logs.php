<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

require_developer('/admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../config/database.php';

$titulo_pagina = 'Registos de Erros';
include '../templates/header.php';

$headerActions = ui_button('Atualizar', [
    'id'      => 'btn-refresh',
    'variant' => 'default',
    'size'    => 'sm',
    'icon'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
]) . ui_button('Descarregar', [
    'id'      => 'btn-download',
    'variant' => 'default',
    'size'    => 'sm',
    'icon'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
]) . ui_button('Limpar Log', [
    'id'      => 'btn-limpar',
    'variant' => 'danger',
    'size'    => 'sm',
    'icon'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
]) . ui_button('Limpar Todos', [
    'id'      => 'btn-limpar-todos',
    'variant' => 'ghost',
    'size'    => 'sm',
    'class'   => 'log-btn-clear-all',
]);

$logTabs = [
    'app'       => 'App',
    'sql'       => 'SQL',
    'email'     => 'Email',
    'stripe'    => 'Stripe',
    'seguranca' => 'Segurança',
    'js'        => 'JS',
];
?>

<main class="dashboard-container animate-entry">
<div class="logs-page">

    <?php echo ui_page_header('Registos de Erros', [
        'layout'        => 'admin',
        'back_url'      => '/dev',
        'back_title'    => 'Voltar ao Painel Dev',
        'actions'       => $headerActions,
        'actions_class' => 'header-actions log-header-actions',
    ]); ?>

    <div class="log-tabs-bar">
        <div class="ui-tabs log-tabs" id="log-tabs" role="tablist">
            <?php foreach ($logTabs as $tabKey => $tabLabel): $isFirst = ($tabKey === 'app'); ?>
                <button type="button"
                        class="ui-tab log-tab<?php echo $isFirst ? ' is-active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $isFirst ? 'true' : 'false'; ?>"
                        data-log="<?php echo ui_e($tabKey); ?>">
                    <span><?php echo ui_e($tabLabel); ?></span>
                    <span class="log-tab-badge" id="badge-<?php echo ui_e($tabKey); ?>" style="display:none"></span>
                </button>
            <?php endforeach; ?>
        </div>

        <?php echo ui_switch('auto_refresh', 'Tempo real (10s)', false, [
            'id'      => 'log-auto-refresh',
            'variant' => 'compact',
        ]); ?>
    </div>

    <?php
    $leftFilters = '<div class="log-filters-left">'
        . ui_select('nivel', [
            ''       => 'Todos os níveis',
            'erros'  => 'Erros e Fatais',
            'avisos' => 'Avisos / Segurança / 404',
            'info'   => 'Apenas Info',
        ], '', ['id' => 'log-nivel', 'variant' => 'toolbar', 'title' => 'Filtrar por gravidade'])
        . ui_select('linhas', [
            '200'  => 'Últimas 200',
            '500'  => 'Últimas 500',
            '1000' => 'Últimas 1000',
            '2000' => 'Últimas 2000',
        ], '500', ['id' => 'log-linhas', 'variant' => 'toolbar', 'title' => 'Número de linhas'])
        . '</div>';

    $rightFilters = ui_search_input('', 'Pesquisar mensagem, ficheiro, IP...', ['id' => 'log-search']);

    echo ui_toolbar($leftFilters, $rightFilters, ['class' => 'log-toolbar']);
    ?>

    <div class="ui-table-wrap log-table-wrap">
        <div class="log-table-status">
            <span id="log-meta" class="log-meta-text">A carregar registos...</span>
            <?php echo ui_button('Copiar tudo', [
                'id'      => 'btn-copy-all',
                'variant' => 'ghost',
                'size'    => 'sm',
                'icon'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
            ]); ?>
        </div>

        <div class="log-table-scroll" id="log-scroll" role="log" aria-live="polite">
            <table class="ui-table log-table">
                <thead>
                    <tr>
                        <th style="width: 156px;">Data / Hora</th>
                        <th style="width: 144px;">Nível</th>
                        <th>Evento</th>
                        <th style="width: 52px;"></th>
                    </tr>
                </thead>
                <tbody id="log-tbody">
                    <tr>
                        <td colspan="4">
                            <div class="log-state-cell">
                                <span class="ui-spinner" aria-hidden="true"></span>
                                <span>A carregar registos...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const CSRF         = <?php echo json_encode($_SESSION['csrf_token'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const tbody        = document.getElementById('log-tbody');
    const scrollBox    = document.getElementById('log-scroll');
    const selectNivel  = document.getElementById('log-nivel');
    const selectLinhas = document.getElementById('log-linhas');
    const inputSearch  = document.getElementById('log-search');
    const metaEl       = document.getElementById('log-meta');

    let logAtivo       = 'app';
    let ultimoConteudo = '';
    let timer          = null;
    let searchTimer    = null;
    let loadController = null;

    const COPY_ICON_SVG = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
    const EMPTY_ICON_SVG = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>';

    function escHtml(s) {
        return String(s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    }

    function fmt(bytes) {
        if (!bytes) return '0 B';
        if (bytes < 1024)    return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function badgeTone(lvl) {
        const u = String(lvl || '').toUpperCase();
        if (['ERROR', 'FATAL', 'FATAL-SHUTDOWN', 'FATAL-EXCEPTION', 'PHP-ERROR', 'SQL', 'EMAIL', 'JS-ERROR', 'JS-PROMISE', 'JS-FETCH', 'JS-RESOURCE'].includes(u) || u.startsWith('HTTP-5')) {
            return 'ui-badge--danger';
        }
        if (u === 'SECURITY') return 'ui-badge--purple';
        if (['WARNING', 'NOTICE', 'DEPRECATED', '404'].includes(u)) return 'ui-badge--warning';
        return 'ui-badge--info';
    }

    function parseLine(rawLine) {
        const m = rawLine.match(/^\[(\d{4}-\d{2}-\d{2}\s+[^\]]+)\]\s+\[([^\]]+)\](?:\s+\[([^\]]+)\])?\s+(.*)$/);
        if (m) {
            const body = m[4];
            const pipeIdx = body.indexOf(' | ');
            return {
                ts:   m[1],
                lvl:  m[2],
                ctx:  m[3] || '',
                msg:  pipeIdx !== -1 ? body.slice(0, pipeIdx) : body,
                meta: pipeIdx !== -1 ? body.slice(pipeIdx + 3) : '',
            };
        }

        const mp = rawLine.match(/^\[([^\]]+)\]\s+(.*)$/);
        if (mp) {
            const isFatal = /fatal|parse|uncaught/i.test(mp[2]);
            return {
                ts:   mp[1],
                lvl:  isFatal ? 'PHP-ERROR' : 'PHP-SYS',
                ctx:  'php',
                msg:  mp[2],
                meta: '',
            };
        }

        return { ts: '-', lvl: 'INFO', ctx: '', msg: rawLine, meta: '' };
    }

    function buildLogRow(rawLine) {
        const parsed = parseLine(rawLine);
        const tr = document.createElement('tr');
        tr.dataset.raw = rawLine;

        const tdTs = document.createElement('td');
        tdTs.className = 'log-cell-ts';
        tdTs.textContent = parsed.ts;
        tr.appendChild(tdTs);

        const tdLvl = document.createElement('td');
        tdLvl.className = 'log-cell-lvl';
        const badge = document.createElement('span');
        badge.className = 'ui-badge ' + badgeTone(parsed.lvl);
        badge.textContent = parsed.lvl;
        tdLvl.appendChild(badge);
        tr.appendChild(tdLvl);

        const tdEvent = document.createElement('td');
        tdEvent.className = 'log-cell-event';

        const topRow = document.createElement('div');
        topRow.className = 'log-msg-main';
        if (parsed.ctx) {
            const src = document.createElement('span');
            src.className = 'log-src-tag';
            src.textContent = parsed.ctx;
            topRow.appendChild(src);
        }
        const msgText = document.createTextNode(parsed.msg);
        topRow.appendChild(msgText);
        tdEvent.appendChild(topRow);

        if (parsed.meta) {
            const metaLine = document.createElement('div');
            metaLine.className = 'log-meta-line';
            metaLine.textContent = parsed.meta;
            tdEvent.appendChild(metaLine);
        }
        tr.appendChild(tdEvent);

        const tdAct = document.createElement('td');
        tdAct.className = 'log-cell-act';
        const copyBtn = document.createElement('button');
        copyBtn.type = 'button';
        copyBtn.className = 'log-copy-btn';
        copyBtn.title = 'Copiar linha';
        copyBtn.setAttribute('aria-label', 'Copiar linha');
        copyBtn.innerHTML = COPY_ICON_SVG;
        tdAct.appendChild(copyBtn);
        tr.appendChild(tdAct);

        return tr;
    }

    function renderStateRow(message, isLoading = false) {
        tbody.textContent = '';
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 4;

        if (isLoading) {
            const box = document.createElement('div');
            box.className = 'log-state-cell';
            const spin = document.createElement('span');
            spin.className = 'ui-spinner';
            spin.setAttribute('aria-hidden', 'true');
            const span = document.createElement('span');
            span.textContent = message;
            box.appendChild(spin);
            box.appendChild(span);
            td.appendChild(box);
        } else {
            const empty = document.createElement('div');
            empty.className = 'ui-empty';
            const iconWrap = document.createElement('span');
            iconWrap.className = 'ui-empty__icon';
            iconWrap.setAttribute('aria-hidden', 'true');
            iconWrap.innerHTML = EMPTY_ICON_SVG;
            const p = document.createElement('p');
            p.textContent = message;
            empty.appendChild(iconWrap);
            empty.appendChild(p);
            td.appendChild(empty);
        }

        tr.appendChild(td);
        tbody.appendChild(tr);
    }

    function renderLogLines(conteudo) {
        if (!conteudo) {
            const hasFilters = Boolean((selectNivel && selectNivel.value !== '') || (inputSearch && inputSearch.value.trim() !== ''));
            renderStateRow(hasFilters ? 'Nenhum registo encontrado com os filtros atuais.' : 'Sem registos neste log.');
            return;
        }

        const lines = conteudo.split(/\r?\n/).filter(Boolean).reverse();
        const frag = document.createDocumentFragment();
        lines.forEach(line => frag.appendChild(buildLogRow(line)));

        tbody.textContent = '';
        tbody.appendChild(frag);
        if (scrollBox) scrollBox.scrollTop = 0;
    }

    function carregarLog(log, showSpinner = false) {
        if (loadController) loadController.abort();
        loadController = new AbortController();

        if (showSpinner) {
            renderStateRow('A carregar registos...', true);
        }

        const nivel  = selectNivel ? selectNivel.value : '';
        const linhas = selectLinhas ? selectLinhas.value : '500';
        const q      = inputSearch ? inputSearch.value.trim() : '';

        const url = 'ajax_ver_logs.php?acao=ler'
            + '&log=' + encodeURIComponent(log)
            + '&linhas=' + encodeURIComponent(linhas)
            + '&nivel=' + encodeURIComponent(nivel)
            + '&q=' + encodeURIComponent(q);

        fetch(url, { signal: loadController.signal })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(d => {
                if (d.sucesso) {
                    ultimoConteudo = d.conteudo || '';
                    renderLogLines(ultimoConteudo);
                    metaEl.textContent = fmt(d.tamanho)
                        + ' · ' + d.linhas_filtradas + ' de ' + d.total_linhas + ' linhas'
                        + ' · Atualizado às ' + new Date().toLocaleTimeString('pt-PT');
                } else {
                    renderStateRow('Erro: ' + (d.mensagem || 'Não foi possível ler o log.'));
                }
            })
            .catch(err => {
                if (err && err.name === 'AbortError') return;
                renderStateRow('Erro de ligação ao servidor.');
            });
    }

    function carregarTamanhos() {
        fetch('ajax_ver_logs.php?acao=tamanhos')
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(d => {
                if (!d.sucesso) return;
                const erros = d.erros || {};
                Object.entries(d.tamanhos || {}).forEach(([nome, tam]) => {
                    const badge = document.getElementById('badge-' + nome);
                    if (!badge) return;
                    const errCount = erros[nome] || 0;
                    if (errCount > 0) {
                        badge.textContent = errCount;
                        badge.className = 'log-tab-badge log-tab-badge--err';
                        badge.title = errCount + ' erro(s) recente(s) · ' + fmt(tam);
                        badge.style.display = 'inline-flex';
                    } else if (tam > 0) {
                        badge.textContent = fmt(tam);
                        badge.className = 'log-tab-badge log-tab-badge--info';
                        badge.title = fmt(tam);
                        badge.style.display = 'inline-flex';
                    } else {
                        badge.style.display = 'none';
                    }
                });
            })
            .catch(() => {});
    }

    // Copiar linha no botão de ação
    tbody.addEventListener('click', e => {
        const btn = e.target.closest('.log-copy-btn');
        if (!btn || !navigator.clipboard) return;
        const row = btn.closest('tr');
        const raw = row ? (row.dataset.raw || '') : '';
        if (!raw) return;
        navigator.clipboard.writeText(raw).then(() => {
            mostrarPopup('Linha copiada.', 'sucesso');
        });
    });

    // Troca de tab
    document.getElementById('log-tabs').addEventListener('click', e => {
        const tab = e.target.closest('.log-tab');
        if (!tab) return;
        document.querySelectorAll('.log-tab').forEach(b => {
            b.classList.remove('is-active');
            b.setAttribute('aria-selected', 'false');
        });
        tab.classList.add('is-active');
        tab.setAttribute('aria-selected', 'true');
        logAtivo = tab.dataset.log;
        carregarLog(logAtivo, true);
    });

    // Filtros
    selectNivel?.addEventListener('change', () => carregarLog(logAtivo, false));
    selectLinhas?.addEventListener('change', () => carregarLog(logAtivo, false));
    inputSearch?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => carregarLog(logAtivo, false), 220);
    });

    // Atualizar
    document.getElementById('btn-refresh').addEventListener('click', () => {
        carregarLog(logAtivo, false);
        carregarTamanhos();
    });

    // Download
    document.getElementById('btn-download').addEventListener('click', () => {
        window.location.href = 'ajax_ver_logs.php?acao=download&log=' + encodeURIComponent(logAtivo);
    });

    // Copiar tudo visível
    document.getElementById('btn-copy-all').addEventListener('click', () => {
        if (!ultimoConteudo || !navigator.clipboard) {
            mostrarPopup('Não há linhas visíveis para copiar.', 'info');
            return;
        }
        navigator.clipboard.writeText(ultimoConteudo).then(() => {
            mostrarPopup('Conteúdo do log copiado.', 'sucesso');
        });
    });

    // Limpar Log atual
    document.getElementById('btn-limpar').addEventListener('click', () => {
        const alvo = logAtivo;
        window.mostrarModalConfirmacao(
            'Limpar Log',
            'Apagar todo o conteúdo de <strong>' + escHtml(alvo) + '.log</strong>? Esta ação é irreversível.',
            () => {
                fetch('ajax_ver_logs.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ acao: 'limpar', log: alvo, csrf_token: CSRF })
                })
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(d => {
                    if (d.sucesso) {
                        mostrarPopup(d.mensagem, 'sucesso');
                        carregarLog(alvo, false);
                        carregarTamanhos();
                    } else {
                        mostrarPopup(d.mensagem || 'Erro ao limpar.', 'erro');
                    }
                })
                .catch(() => mostrarPopup('Erro de ligação.', 'erro'));
            }
        );
    });

    // Limpar Todos os logs
    document.getElementById('btn-limpar-todos').addEventListener('click', () => {
        window.mostrarModalConfirmacao(
            'Limpar Todos os Logs',
            'Apagar o conteúdo dos 6 ficheiros de log (App, SQL, Email, Stripe, Segurança e JS)?',
            () => {
                fetch('ajax_ver_logs.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ acao: 'limpar_todos', csrf_token: CSRF })
                })
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(d => {
                    if (d.sucesso) {
                        mostrarPopup(d.mensagem, 'sucesso');
                        carregarLog(logAtivo, false);
                        carregarTamanhos();
                    } else {
                        mostrarPopup(d.mensagem || 'Erro ao limpar.', 'erro');
                    }
                })
                .catch(() => mostrarPopup('Erro de ligação.', 'erro'));
            }
        );
    });

    // Auto-refresh
    document.getElementById('log-auto-refresh')?.addEventListener('change', function () {
        clearInterval(timer);
        if (this.checked) {
            timer = setInterval(() => {
                carregarLog(logAtivo, false);
                carregarTamanhos();
            }, 10000);
        } else {
            timer = null;
        }
    });

    // Arranque
    carregarLog('app', true);
    carregarTamanhos();
});
</script>

<?php include '../templates/footer.php'; ?>
