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
?>

<main class="dashboard-container animate-entry">
<div class="logs-page">

    <?php echo ui_page_header('Registos de Erros', [
        'layout'     => 'admin',
        'back_url'   => '/dev',
        'back_title' => 'Voltar ao Painel Dev',
    ]); ?>

    <?php
    $leftFilters = '<div class="log-filters-left">'
        . ui_select('nivel', [
            ''       => 'Todos os níveis',
            'erros'  => 'Erros e Fatais',
            'avisos' => 'Avisos / Segurança / 404',
            'info'   => 'Apenas Info',
        ], '', ['id' => 'log-nivel', 'variant' => 'toolbar', 'title' => 'Filtrar por gravidade'])
        . ui_select('tipo', [
            ''          => 'Todos os tipos',
            'app'       => 'App',
            'sql'       => 'SQL',
            'email'     => 'Email',
            'stripe'    => 'Stripe',
            'seguranca' => 'Segurança',
            'js'        => 'JS',
        ], '', ['id' => 'log-tipo', 'variant' => 'toolbar', 'title' => 'Filtrar por tipo de log'])
        . ui_search_input('', 'Pesquisa', ['id' => 'log-search'])
        . '</div>';

    $rightActions = '<div class="log-toolbar-actions">'
        . ui_button('Copiar tudo', [
            'id'    => 'btn-copy-all',
            'class' => 'button btn-sel-mode',
            'type'  => 'button',
            'html'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'
                . "\n                    Copiar tudo",
        ])
        . ui_select_mode_button()
        . '</div>';

    echo ui_toolbar($leftFilters, $rightActions, ['class' => 'log-toolbar']);
    ?>

    <?php echo ui_table(
        [
            ['label' => 'Data'],
            ['label' => 'Tipo'],
            ['label' => 'Nível'],
            ['label' => 'Origem'],
            ['label' => 'Mensagem', 'class' => 'log-col-msg'],
            ['label' => 'Ações', 'sortable' => false],
        ],
        [],
        [
            'wrap_id'     => 'table-wrapper',
            'table_class' => 'admin-table tabela-logs',
            'tbody_id'    => 'log-tbody',
            'client_sort' => true,
            'tbody_html'  => '<tr><td colspan="6" class="reservation-empty">A carregar registos...</td></tr>',
        ]
    ); ?>

</div>
</main>

<?php echo ui_selection_bar(); ?>
<?php echo ui_bulk_bar('', '', [
    'unit'         => 'registo(s)',
    'action_label' => 'Eliminar Selecionados',
]); ?>

<?php
$detailBodyHtml = '<div class="log-detail-grid">'
    . '<div class="log-detail-item"><span class="log-detail-label">Data</span><span class="log-detail-value" id="log-detail-ts">-</span></div>'
    . '<div class="log-detail-item"><span class="log-detail-label">Tipo</span><span class="log-detail-value" id="log-detail-tipo">-</span></div>'
    . '<div class="log-detail-item"><span class="log-detail-label">Nível</span><span class="log-detail-value" id="log-detail-lvl">-</span></div>'
    . '<div class="log-detail-item"><span class="log-detail-label">Origem</span><span class="log-detail-value" id="log-detail-ctx">-</span></div>'
    . '</div>'
    . '<div class="log-detail-section"><span class="log-detail-label">Mensagem</span><div class="log-detail-text" id="log-detail-msg"></div></div>'
    . '<div class="log-detail-section" id="log-detail-meta-wrap" hidden><span class="log-detail-label">Contexto</span><div class="log-detail-text" id="log-detail-meta"></div></div>'
    . '<div class="log-detail-section"><span class="log-detail-label">Linha completa</span><pre class="log-detail-raw" id="log-detail-raw"></pre></div>';

echo ui_modal(
    'modal-log-detail',
    'Detalhes do Registo',
    $detailBodyHtml,
    [
        'class'  => 'log-detail-modal',
        'footer' => ui_button('Copiar linha', ['id' => 'btn-modal-copy-log', 'variant' => 'default'])
            . ui_button('Fechar', ['id' => 'btn-modal-close-log', 'variant' => 'primary']),
    ]
);
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const CSRF         = <?php echo json_encode($_SESSION['csrf_token'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const tbody        = document.getElementById('log-tbody');
    const selectNivel  = document.getElementById('log-nivel');
    const selectTipo   = document.getElementById('log-tipo');
    const inputSearch  = document.getElementById('log-search');
    const detailModal  = document.getElementById('modal-log-detail');

    const LOG_TYPE_META = {
        app:       { label: 'App',       tone: 'ui-badge--neutral' },
        sql:       { label: 'SQL',       tone: 'ui-badge--warning' },
        email:     { label: 'Email',     tone: 'ui-badge--info' },
        stripe:    { label: 'Stripe',    tone: 'ui-badge--accent' },
        seguranca: { label: 'Segurança', tone: 'ui-badge--danger' },
        js:        { label: 'JS',        tone: 'ui-badge--warning' },
    };

    let searchTimer    = null;
    let loadController = null;
    let modalRawLine   = '';
    let registosAtuais = [];

    function copiarTexto(texto, msgSucesso) {
        if (!texto) return;
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(texto)
                .then(() => mostrarPopup(msgSucesso, 'sucesso'))
                .catch(() => copiarFallback(texto, msgSucesso));
            return;
        }
        copiarFallback(texto, msgSucesso);
    }

    function copiarFallback(texto, msgSucesso) {
        const ta = document.createElement('textarea');
        ta.value = texto;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            mostrarPopup(msgSucesso, 'sucesso');
        } catch (e) {
            mostrarPopup('Não foi possível copiar para a área de transferência.', 'erro');
        }
        ta.remove();
    }

    function formatTs(ts) {
        const m = String(ts || '').match(/^(\d{4})-(\d{2})-(\d{2})(.*)$/);
        if (m) return `${m[3]}/${m[2]}/${m[1]}${m[4]}`;
        return String(ts || '-');
    }

    function badgeTone(lvl) {
        const u = String(lvl || '').toUpperCase();
        if (['ERROR', 'FATAL', 'FATAL-SHUTDOWN', 'FATAL-EXCEPTION', 'PHP-ERROR', 'SQL', 'EMAIL', 'JS-ERROR', 'JS-PROMISE', 'JS-FETCH', 'JS-RESOURCE'].includes(u) || u.startsWith('HTTP-5')) {
            return 'ui-badge--danger';
        }
        if (u === 'SECURITY') return 'ui-badge--accent';
        if (['WARNING', 'NOTICE', 'DEPRECATED', '404'].includes(u)) return 'ui-badge--warning';
        return 'ui-badge--info';
    }

    function getTipoMeta(logKey) {
        return LOG_TYPE_META[logKey] || { label: String(logKey || 'App').toUpperCase(), tone: 'ui-badge--neutral' };
    }

    function parseLine(rawLine) {
        const m = rawLine.match(/^\[(\d{4}-\d{2}-\d{2}\s+[^\]]+)\]\s+\[([^\]]+)\](?:\s+\[([^\]]+)\])?\s+(.*)$/);
        if (m) {
            const body = m[4];
            const pipeIdx = body.indexOf(' | ');
            return {
                ts:   formatTs(m[1]),
                lvl:  m[2],
                ctx:  m[3] || '-',
                msg:  pipeIdx !== -1 ? body.slice(0, pipeIdx) : body,
                meta: pipeIdx !== -1 ? body.slice(pipeIdx + 3) : '',
            };
        }

        const mp = rawLine.match(/^\[([^\]]+)\]\s+(.*)$/);
        if (mp) {
            const isFatal = /fatal|parse|uncaught/i.test(mp[2]);
            return {
                ts:   formatTs(mp[1]),
                lvl:  isFatal ? 'PHP-ERROR' : 'PHP-SYS',
                ctx:  'php',
                msg:  mp[2],
                meta: '',
            };
        }

        return { ts: '-', lvl: 'INFO', ctx: '-', msg: rawLine, meta: '' };
    }

    function buildLogRow(item) {
        const logKey  = item.log || 'app';
        const rawLine = item.raw || '';
        const parsed  = parseLine(rawLine);
        const tipo    = getTipoMeta(logKey);

        const tr = document.createElement('tr');
        tr.dataset.id   = item.id || (logKey + ':' + rawLine);
        tr.dataset.raw  = rawLine;
        tr.dataset.log  = logKey;
        tr.dataset.ts   = parsed.ts;
        tr.dataset.lvl  = parsed.lvl;
        tr.dataset.ctx  = parsed.ctx;
        tr.dataset.msg  = parsed.msg;
        tr.dataset.meta = parsed.meta;

        const tdTs = document.createElement('td');
        tdTs.className = 'log-cell-ts';
        tdTs.textContent = parsed.ts;
        tr.appendChild(tdTs);

        const tdTipo = document.createElement('td');
        const tipoBadge = document.createElement('span');
        tipoBadge.className = 'ui-badge ' + tipo.tone;
        tipoBadge.textContent = tipo.label;
        tdTipo.appendChild(tipoBadge);
        tr.appendChild(tdTipo);

        const tdLvl = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = 'ui-badge ' + badgeTone(parsed.lvl);
        badge.textContent = parsed.lvl;
        tdLvl.appendChild(badge);
        tr.appendChild(tdLvl);

        const tdCtx = document.createElement('td');
        tdCtx.textContent = parsed.ctx;
        tr.appendChild(tdCtx);

        const tdMsg = document.createElement('td');
        tdMsg.className = 'log-cell-msg';
        const msgClamp = document.createElement('div');
        msgClamp.className = 'log-msg-clamp';
        msgClamp.textContent = parsed.msg;
        tdMsg.appendChild(msgClamp);
        tr.appendChild(tdMsg);

        const tdAct = document.createElement('td');
        const actionsWrap = document.createElement('div');
        actionsWrap.className = 'acoes-tabela';

        const copyBtn = document.createElement('button');
        copyBtn.type = 'button';
        copyBtn.className = 'btn-duplicate-single btn-log-copy';
        copyBtn.title = 'Copiar registo';
        copyBtn.setAttribute('aria-label', 'Copiar registo');
        actionsWrap.appendChild(copyBtn);

        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'btn-del-single btn-log-del';
        delBtn.title = 'Eliminar registo';
        delBtn.setAttribute('aria-label', 'Eliminar registo');
        actionsWrap.appendChild(delBtn);

        tdAct.appendChild(actionsWrap);
        tr.appendChild(tdAct);

        return tr;
    }

    function renderStateRow(message) {
        tbody.textContent = '';
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 6;
        td.className = 'reservation-empty';
        td.textContent = message;
        tr.appendChild(td);
        tbody.appendChild(tr);
    }

    function renderRegistos(registos) {
        if (!Array.isArray(registos) || registos.length === 0) {
            const hasFilters = Boolean(
                (selectNivel && selectNivel.value !== '')
                || (selectTipo && selectTipo.value !== '')
                || (inputSearch && inputSearch.value.trim() !== '')
            );
            renderStateRow(hasFilters ? 'Nenhum registo encontrado com os filtros atuais.' : 'Sem registos de log.');
            return;
        }

        const frag = document.createDocumentFragment();
        registos.forEach((item, idx) => {
            if (!item || !item.raw) return;
            const tr = buildLogRow(item);
            tr.dataset.origIdx = String(idx);
            frag.appendChild(tr);
        });

        tbody.textContent = '';
        tbody.appendChild(frag);
        const tbl = tbody.closest('table');
        if (tbl && typeof tbl._applyClientSort === 'function') {
            tbl._applyClientSort();
        }
        if (bulkSel && typeof bulkSel.updateUI === 'function') {
            bulkSel.updateUI();
        }
    }

    function abrirDetalheModal(row) {
        if (!detailModal || !row) return;
        modalRawLine = row.dataset.raw || '';

        const tipo = getTipoMeta(row.dataset.log || 'app');
        document.getElementById('log-detail-ts').textContent  = row.dataset.ts || '-';
        document.getElementById('log-detail-ctx').textContent = row.dataset.ctx || '-';
        document.getElementById('log-detail-msg').textContent = row.dataset.msg || '';
        document.getElementById('log-detail-raw').textContent = modalRawLine;

        const tipoEl = document.getElementById('log-detail-tipo');
        tipoEl.textContent = '';
        const tipoBadge = document.createElement('span');
        tipoBadge.className = 'ui-badge ' + tipo.tone;
        tipoBadge.textContent = tipo.label;
        tipoEl.appendChild(tipoBadge);

        const lvlEl = document.getElementById('log-detail-lvl');
        lvlEl.textContent = '';
        const badge = document.createElement('span');
        badge.className = 'ui-badge ' + badgeTone(row.dataset.lvl || 'INFO');
        badge.textContent = row.dataset.lvl || 'INFO';
        lvlEl.appendChild(badge);

        const metaWrap = document.getElementById('log-detail-meta-wrap');
        const metaText = document.getElementById('log-detail-meta');
        if (row.dataset.meta) {
            metaText.textContent = row.dataset.meta;
            metaWrap.hidden = false;
        } else {
            metaText.textContent = '';
            metaWrap.hidden = true;
        }

        detailModal.style.display = 'flex';
        document.documentElement.classList.add('scroll-lock');
    }

    function fecharDetalheModal() {
        if (!detailModal) return;
        detailModal.style.display = 'none';
        document.documentElement.classList.remove('scroll-lock');
    }

    function carregarLogs(showLoading = false) {
        if (loadController) loadController.abort();
        loadController = new AbortController();

        if (showLoading) {
            renderStateRow('A carregar registos...');
        }

        const nivel = selectNivel ? selectNivel.value : '';
        const tipo  = selectTipo ? selectTipo.value : '';
        const q     = inputSearch ? inputSearch.value.trim() : '';

        const url = 'ajax_ver_logs.php?acao=ler'
            + '&log=todos'
            + '&linhas=all'
            + '&tipo=' + encodeURIComponent(tipo)
            + '&nivel=' + encodeURIComponent(nivel)
            + '&q=' + encodeURIComponent(q);

        fetch(url, { signal: loadController.signal })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(d => {
                if (d.sucesso) {
                    registosAtuais = Array.isArray(d.registos) ? d.registos : [];
                    renderRegistos(registosAtuais);
                } else {
                    registosAtuais = [];
                    renderStateRow('Erro: ' + (d.mensagem || 'Não foi possível ler os logs.'));
                }
            })
            .catch(err => {
                if (err && err.name === 'AbortError') return;
                registosAtuais = [];
                renderStateRow('Erro de ligação ao servidor.');
            });
    }

    function apagarRegistos(ids, exitSelMode = false) {
        if (!Array.isArray(ids) || ids.length === 0) return;
        fetch('ajax_ver_logs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'apagar_linhas', ids, csrf_token: CSRF })
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(d => {
            if (d.sucesso) {
                mostrarPopup(d.mensagem, 'sucesso');
                if (exitSelMode && bulkSel) bulkSel.setMode(false);
                carregarLogs(false);
            } else {
                mostrarPopup(d.mensagem || 'Erro ao eliminar.', 'erro');
            }
        })
        .catch(() => mostrarPopup('Erro de ligação.', 'erro'));
    }

    const bulkSel = window.initAdminBulkSelection({
        onAction: ids => {
            window.mostrarModalConfirmacao(
                'Eliminar Registos',
                `Eliminar permanentemente <strong>${ids.length} registo(s)</strong> selecionado(s)?`,
                () => apagarRegistos(ids, true)
            );
        }
    });

    // Abrir detalhe no olho, eliminar linha, ou duplo clique na linha
    tbody.addEventListener('click', e => {
        if (bulkSel && bulkSel.isSelMode()) return;

        const copyBtn = e.target.closest('.btn-log-copy');
        if (copyBtn) {
            const row = copyBtn.closest('tr[data-raw]');
            if (!row) return;
            copiarTexto(`[${getTipoMeta(row.dataset.log).label.toUpperCase()}] ${row.dataset.raw}`, 'Registo copiado.');
            return;
        }

        const delBtn = e.target.closest('.btn-log-del');
        if (delBtn) {
            const row = delBtn.closest('tr[data-id]');
            if (!row) return;
            window.mostrarModalConfirmacao(
                'Eliminar Registo',
                'Eliminar permanentemente este registo de log?',
                () => apagarRegistos([row.dataset.id], false)
            );
        }
    });

    tbody.addEventListener('dblclick', e => {
        if (bulkSel && bulkSel.isSelMode()) return;
        if (e.target.closest('button')) return;
        const row = e.target.closest('tr[data-raw]');
        if (row) abrirDetalheModal(row);
    });

    document.getElementById('btn-modal-close-log')?.addEventListener('click', fecharDetalheModal);
    detailModal?.addEventListener('click', e => { if (e.target === detailModal) fecharDetalheModal(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && detailModal?.style.display === 'flex') fecharDetalheModal();
    });
    document.getElementById('btn-modal-copy-log')?.addEventListener('click', () => {
        copiarTexto(modalRawLine, 'Linha copiada.');
    });

    // Filtros
    selectNivel?.addEventListener('change', () => carregarLogs(false));
    selectTipo?.addEventListener('change', () => carregarLogs(false));
    inputSearch?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => carregarLogs(false), 220);
    });

    // Copiar todos os registos visíveis com detalhe completo
    document.getElementById('btn-copy-all')?.addEventListener('click', () => {
        if (registosAtuais.length === 0) {
            mostrarPopup('Não há registos visíveis para copiar.', 'erro');
            return;
        }
        const textoCompleto = registosAtuais
            .filter(item => item && item.raw)
            .map(item => `[${getTipoMeta(item.log).label.toUpperCase()}] ${item.raw}`)
            .join('\n');
        copiarTexto(textoCompleto, 'Todos os registos copiados.');
    });

    // Auto-refresh em tempo real sempre ativo (a cada 10s, pausado durante seleção)
    setInterval(() => {
        if (!document.hidden && !(bulkSel && bulkSel.isSelMode())) {
            carregarLogs(false);
        }
    }, 10000);

    // Arranque
    carregarLogs(true);
});
</script>

<?php include '../templates/footer.php'; ?>
