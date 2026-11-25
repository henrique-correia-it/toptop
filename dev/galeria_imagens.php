<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/formatters.php';
require_once __DIR__ . '/includes/storage_helper.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || $_SESSION['admin_role'] !== 'desenvolvedor') {
    header("Location: /admin/admin.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$galeria         = dev_listar_imagens_galeria();
$imagens         = $galeria['imagens'];
$contagens       = $galeria['contagens'];
$total_ficheiros = $galeria['total_count'];
$total_bytes     = $galeria['total_bytes'];
$tipo_labels     = dev_galeria_tipo_labels();

$titulo_pagina = 'Galeria de Imagens';
include '../templates/header.php';
?>

<main class="dashboard-container animate-entry">
<div class="gal-page">

    <?php echo ui_page_header('Galeria de Imagens', [
        'layout'     => 'admin',
        'back_url'   => '/dev',
        'back_title' => 'Voltar ao Painel Dev',
    ]); ?>

    <!-- KPIs (ui_stat_card oficial) -->
    <div class="stats-grid gal-stats-grid">
        <?php
        echo ui_stat_card((string)$total_ficheiros, 'Total de Imagens', [
            'value_id' => 'stat-total',
            'icon'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        ]);
        echo ui_stat_card(format_bytes($total_bytes, 2, 'B'), 'Espaço Ocupado', [
            'value_id' => 'stat-bytes',
            'icon'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        ]);
        echo ui_stat_card('-', 'Fotos Fantasma', [
            'value_id' => 'stat-ghost',
            'class'    => 'gal-stat-ghost',
            'attrs'    => [
                'id'       => 'card-stat-ghost',
                'role'     => 'button',
                'tabindex' => '0',
                'title'    => 'Filtrar fotos fantasma',
            ],
            'icon'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>',
        ]);
        ?>
    </div>

    <!-- Toolbar unificada (ui_toolbar oficial) -->
    <?php
    $toolbarLeft = ui_button('Reanalisar', [
        'id'      => 'btn-scan',
        'variant' => 'default',
        'icon'    => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
    ]) . ui_button('Apagar fantasmas', [
        'id'      => 'btn-del-all',
        'variant' => 'danger',
        'class'   => 'gal-del-all',
        'icon'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>',
    ]);

    $filterOptions = [
        'todas'     => "Todas ({$total_ficheiros})",
        'produto'   => "Produtos ({$contagens['produto']})",
        'categoria' => "Categorias ({$contagens['categoria']})",
        'logo'      => "Logos ({$contagens['logo']})",
        'hero'      => "Hero ({$contagens['hero']})",
    ];
    if (($contagens['outros'] ?? 0) > 0) {
        $filterOptions['outros'] = "Outros ({$contagens['outros']})";
    }
    $filterOptions['fantasmas'] = ['value' => 'fantasmas', 'label' => 'Fantasmas (-)', 'disabled' => true];

    $toolbarRight = '<div class="gal-filter-controls">'
        . ui_select('', $filterOptions, 'todas', [
            'id'      => 'gal-filter',
            'variant' => 'toolbar',
            'title'   => 'Filtrar imagens',
        ])
        . ui_search_input('', 'Pesquisar ficheiro...', ['id' => 'gal-search'])
        . '</div>';

    echo ui_toolbar($toolbarLeft, $toolbarRight, ['class' => 'gal-toolbar-wrap']);
    ?>

    <!-- Grelha de Imagens -->
    <div class="gal-grid" id="gal-grid">
        <?php foreach ($imagens as $img):
            $tipo      = $img['tipo'];
            $size_fmt  = format_bytes($img['tamanho'], 2, 'B');
            $tipo_lbl  = $tipo_labels[$tipo] ?? ucfirst($tipo);
        ?>
        <div class="gal-card"
             data-nome="<?php echo ui_e($img['nome']); ?>"
             data-rel="<?php echo ui_e($img['rel']); ?>"
             data-src="<?php echo ui_e($img['src']); ?>"
             data-size="<?php echo ui_e($size_fmt); ?>"
             data-bytes="<?php echo (int)$img['tamanho']; ?>"
             data-tipo="<?php echo ui_e($tipo); ?>"
             data-tipo-label="<?php echo ui_e($tipo_lbl); ?>"
             onclick="abrirLightbox(this)">

            <img src="<?php echo ui_e($img['src']); ?>" alt="<?php echo ui_e($img['nome']); ?>" loading="lazy">

            <div class="gal-overlay">
                <div class="gal-overlay-nome"><?php echo ui_e($img['nome']); ?></div>
                <div class="gal-overlay-meta"><?php echo ui_e($tipo_lbl . ' · ' . $size_fmt); ?></div>
            </div>

            <div class="gal-ghost-overlay">
                <?php echo ui_badge('Fantasma', 'danger', ['class' => 'gal-ghost-badge']); ?>
                <?php echo ui_button('Apagar', [
                    'variant' => 'danger',
                    'size'    => 'sm',
                    'class'   => 'gal-ghost-del',
                    'onclick' => 'event.stopPropagation(); apagarFoto(this)',
                    'icon'    => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>',
                ]); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php echo ui_empty_state('Nenhuma imagem encontrada.', [
        'class' => 'gal-empty-state',
        'icon'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        'attrs' => [
            'id'    => 'gal-empty-state',
            'style' => empty($imagens) ? '' : 'display:none;',
        ],
    ]); ?>

</div>
</main>

<!-- Modal de Preview -->
<div class="gal-lb" id="lightbox" role="dialog" aria-modal="true" aria-labelledby="lb-nome" onclick="fecharLightbox()">
    <div class="gal-modal-card" onclick="event.stopPropagation()">
        <div class="gal-modal-hd">
            <div class="gal-modal-title-wrap">
                <span id="lb-nome" class="gal-modal-name"></span>
                <span id="lb-tipo" class="ui-badge ui-badge--neutral"></span>
            </div>
            <?php echo ui_close_button(['class' => 'gal-modal-close', 'onclick' => 'fecharLightbox()']); ?>
        </div>

        <div class="gal-modal-stage">
            <button type="button" id="lb-prev" class="gal-modal-nav gal-modal-nav--prev" aria-label="Imagem anterior" title="Anterior">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <img id="lb-img" src="" alt="">
            <button type="button" id="lb-next" class="gal-modal-nav gal-modal-nav--next" aria-label="Imagem seguinte" title="Seguinte">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
            </button>
        </div>

        <div class="gal-modal-ft">
            <div class="gal-modal-meta" id="lb-meta"></div>
            <div class="gal-modal-actions">
                <?php
                echo ui_button('Copiar caminho', [
                    'id'      => 'lb-copy',
                    'variant' => 'default',
                    'size'    => 'sm',
                    'icon'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
                ]);
                echo ui_button('Abrir', [
                    'id'      => 'lb-open',
                    'href'    => '#',
                    'target'  => '_blank',
                    'variant' => 'primary',
                    'size'    => 'sm',
                    'attrs'   => ['rel' => 'noopener'],
                    'icon'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',
                ]);
                ?>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = <?php echo json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const filterSelect = document.getElementById('gal-filter');
const searchInput  = document.getElementById('gal-search');
const emptyStateEl = document.getElementById('gal-empty-state');
const ghostCardEl  = document.getElementById('card-stat-ghost');
const btnDelAll    = document.getElementById('btn-del-all');

let scanConcluido = false;

function formatBytesPt(bytes) {
    if (!bytes || bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
    const val = bytes / Math.pow(1024, i);
    return (i === 0 ? Math.round(val) : val.toFixed(2)) + ' ' + units[i];
}

function atualizarContadores() {
    const cards = [...document.querySelectorAll('.gal-card')];
    const counts = { todas: cards.length, produto: 0, categoria: 0, logo: 0, hero: 0, outros: 0, fantasmas: 0 };
    let totalBytes = 0;

    cards.forEach(card => {
        const tipo = card.dataset.tipo || 'produto';
        if (Object.prototype.hasOwnProperty.call(counts, tipo)) counts[tipo]++;
        if (card.classList.contains('fantasma')) counts.fantasmas++;
        totalBytes += parseInt(card.dataset.bytes || '0', 10) || 0;
    });

    document.getElementById('stat-total').textContent = String(counts.todas);
    document.getElementById('stat-bytes').textContent = formatBytesPt(totalBytes);

    const ghostText = scanConcluido ? String(counts.fantasmas) : '-';
    document.getElementById('stat-ghost').textContent = ghostText;
    ghostCardEl?.classList.toggle('has-ghosts', scanConcluido && counts.fantasmas > 0);
    btnDelAll?.classList.toggle('show', scanConcluido && counts.fantasmas > 0);

    if (filterSelect) {
        const labels = {
            todas:     'Todas (' + counts.todas + ')',
            produto:   'Produtos (' + counts.produto + ')',
            categoria: 'Categorias (' + counts.categoria + ')',
            logo:      'Logos (' + counts.logo + ')',
            hero:      'Hero (' + counts.hero + ')',
            outros:    'Outros (' + counts.outros + ')',
            fantasmas: 'Fantasmas (' + (scanConcluido ? counts.fantasmas : '-') + ')',
        };
        Array.from(filterSelect.options).forEach(opt => {
            if (labels[opt.value]) opt.textContent = labels[opt.value];
            if (opt.value === 'fantasmas') opt.disabled = !scanConcluido;
        });
        if (typeof window.uiRefreshSelect === 'function') {
            window.uiRefreshSelect(filterSelect);
        }
    }
}

function aplicarFiltro() {
    const filtroAtual = filterSelect ? filterSelect.value : 'todas';
    const termo       = (searchInput ? searchInput.value : '').trim().toLowerCase();
    let visiveis      = 0;

    document.querySelectorAll('.gal-card').forEach(card => {
        const fantasma = card.classList.contains('fantasma');
        const tipo     = card.dataset.tipo;
        const nome     = (card.dataset.nome || '').toLowerCase();

        let matchTipo = false;
        if (filtroAtual === 'todas') matchTipo = true;
        else if (filtroAtual === 'fantasmas') matchTipo = fantasma;
        else matchTipo = (tipo === filtroAtual);

        const matchBusca = (termo === '' || nome.includes(termo));
        const show       = matchTipo && matchBusca;

        card.classList.toggle('hidden-card', !show);
        if (show) visiveis++;
    });

    if (emptyStateEl) {
        emptyStateEl.style.display = visiveis === 0 ? '' : 'none';
    }
}

filterSelect?.addEventListener('change', aplicarFiltro);
searchInput?.addEventListener('input', aplicarFiltro);

function toggleGhostFilter() {
    if (!scanConcluido || !filterSelect) return;
    filterSelect.value = (filterSelect.value === 'fantasmas') ? 'todas' : 'fantasmas';
    if (typeof window.uiRefreshSelect === 'function') {
        window.uiRefreshSelect(filterSelect);
    }
    aplicarFiltro();
}

ghostCardEl?.addEventListener('click', toggleGhostFilter);
ghostCardEl?.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggleGhostFilter();
    }
});

// Modal de Preview
const lbEl     = document.getElementById('lightbox');
const lbImg    = document.getElementById('lb-img');
const lbNome   = document.getElementById('lb-nome');
const lbTipo   = document.getElementById('lb-tipo');
const lbMeta   = document.getElementById('lb-meta');
const lbOpen   = document.getElementById('lb-open');
const lbCopy   = document.getElementById('lb-copy');
const lbPrev   = document.getElementById('lb-prev');
const lbNext   = document.getElementById('lb-next');

let currentLbCard = null;
let currentLbSrc  = '';
let currentLbBaseMeta = '';

function getVisibleGalCards() {
    return [...document.querySelectorAll('.gal-card:not(.hidden-card)')];
}

function updateLbDimensions() {
    if (!lbImg || !lbMeta || !lbEl?.classList.contains('open')) return;
    if (lbImg.naturalWidth > 0 && lbImg.naturalHeight > 0) {
        lbMeta.textContent = currentLbBaseMeta + ' · ' + lbImg.naturalWidth + ' × ' + lbImg.naturalHeight + ' px';
    }
}

lbImg?.addEventListener('load', updateLbDimensions);

function abrirLightbox(card) {
    if (!card || !lbEl) return;
    currentLbCard = card;

    const nome      = card.dataset.nome || '';
    const size      = card.dataset.size || '';
    const tipo      = card.dataset.tipo || 'produto';
    const tipoLabel = card.dataset.tipoLabel || tipo;
    const src       = card.dataset.src || ('/public/' + (card.dataset.rel || 'images/' + encodeURIComponent(nome)));
    currentLbSrc    = src;

    const visCards = getVisibleGalCards();
    const idx      = visCards.indexOf(card);
    const showNav  = visCards.length > 1 && idx !== -1;

    if (lbPrev) lbPrev.style.display = showNav ? '' : 'none';
    if (lbNext) lbNext.style.display = showNav ? '' : 'none';

    currentLbBaseMeta = size + (showNav ? (' · ' + (idx + 1) + ' / ' + visCards.length) : '');
    if (lbNome) lbNome.textContent = nome;
    if (lbTipo) lbTipo.textContent = tipoLabel;
    if (lbMeta) lbMeta.textContent = currentLbBaseMeta;
    if (lbOpen) lbOpen.href = src;
    if (lbImg) {
        lbImg.alt = nome;
        lbImg.src = src;
        if (lbImg.complete && lbImg.naturalWidth > 0) {
            updateLbDimensions();
        }
    }

    lbEl.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function navegarLightbox(delta) {
    if (!currentLbCard) return;
    const visCards = getVisibleGalCards();
    if (visCards.length <= 1) return;
    const idx = visCards.indexOf(currentLbCard);
    if (idx === -1) return;
    const nextIdx = (idx + delta + visCards.length) % visCards.length;
    abrirLightbox(visCards[nextIdx]);
}

function fecharLightbox() {
    if (!lbEl) return;
    lbEl.classList.remove('open');
    if (lbImg) lbImg.src = '';
    currentLbCard = null;
    currentLbSrc  = '';
    document.body.style.overflow = '';
}

lbPrev?.addEventListener('click', () => navegarLightbox(-1));
lbNext?.addEventListener('click', () => navegarLightbox(1));
lbCopy?.addEventListener('click', () => {
    if (!currentLbSrc || !navigator.clipboard) return;
    navigator.clipboard.writeText(currentLbSrc).then(() => {
        mostrarPopup('Caminho copiado.', 'sucesso');
    });
});

document.addEventListener('keydown', e => {
    if (!lbEl?.classList.contains('open')) return;
    if (e.key === 'Escape') fecharLightbox();
    else if (e.key === 'ArrowLeft') navegarLightbox(-1);
    else if (e.key === 'ArrowRight') navegarLightbox(1);
});

// Scan
const btnScan = document.getElementById('btn-scan');
const scanBtnOrigHtml = btnScan ? btnScan.innerHTML : '';

async function runScan(manual = false) {
    if (!btnScan) return;
    btnScan.disabled = true;
    btnScan.innerHTML = '<span class="gal-spin" aria-hidden="true"></span><span>A analisar...</span>';

    try {
        const res  = await fetch('ajax_galeria_imagens.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'analisar', csrf_token: CSRF })
        });
        const data = await res.json();
        if (!data.sucesso) throw new Error(data.mensagem || 'Erro ao analisar imagens.');

        const ghosts = new Set(data.fantasmas);
        const total  = data.fantasmas.length;

        document.querySelectorAll('.gal-card').forEach(card => {
            const rel     = card.dataset.rel || '';
            const key     = (card.dataset.tipo || 'produto') + ':' + card.dataset.nome;
            const isGhost = ghosts.has(rel) || ghosts.has(key) || ghosts.has(card.dataset.nome);
            card.classList.toggle('fantasma', isGhost);
        });

        scanConcluido = true;
        atualizarContadores();
        aplicarFiltro();

        if (manual) {
            if (total === 0) {
                mostrarPopup('Tudo limpo - nenhuma foto fantasma encontrada.', 'sucesso');
            } else {
                mostrarPopup(total + ' foto(s) fantasma encontrada(s).', 'info');
            }
        }
    } catch (e) {
        mostrarPopup('Erro: ' + e.message, 'erro');
    } finally {
        btnScan.disabled = false;
        btnScan.innerHTML = scanBtnOrigHtml;
    }
}

btnScan?.addEventListener('click', () => runScan(true));
runScan(false);

// Delete All Ghosts
const delAllOrigHtml = btnDelAll ? btnDelAll.innerHTML : '';

btnDelAll?.addEventListener('click', () => {
    const fantasmas = [...document.querySelectorAll('.gal-card.fantasma')];
    if (!fantasmas.length) return;

    mostrarModalConfirmacao(
        'Apagar todas as fantasmas',
        'Vai apagar permanentemente <strong>' + fantasmas.length + ' imagem(ns)</strong> não referenciadas. Esta ação não pode ser revertida.',
        async () => {
            btnDelAll.disabled = true;
            btnDelAll.innerHTML = '<span class="gal-spin" aria-hidden="true"></span><span>A apagar...</span>';

            let apagadas = 0;
            for (const card of fantasmas) {
                const nome = card.dataset.nome;
                const tipo = card.dataset.tipo || '';
                const rel  = card.dataset.rel || '';
                try {
                    const res  = await fetch('ajax_galeria_imagens.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'apagar', csrf_token: CSRF, ficheiro: nome, tipo, rel })
                    });
                    const data = await res.json();
                    if (data.sucesso) {
                        card.remove();
                        apagadas++;
                    }
                } catch (_) {}
            }

            if (apagadas > 0) {
                mostrarPopup(apagadas + ' imagem(ns) apagada(s) com sucesso.', 'sucesso');
            } else {
                mostrarPopup('Não foi possível apagar as imagens selecionadas.', 'erro');
            }

            btnDelAll.disabled = false;
            btnDelAll.innerHTML = delAllOrigHtml;

            atualizarContadores();
            if (filterSelect && filterSelect.value === 'fantasmas' && document.querySelectorAll('.gal-card.fantasma').length === 0) {
                filterSelect.value = 'todas';
                if (typeof window.uiRefreshSelect === 'function') {
                    window.uiRefreshSelect(filterSelect);
                }
            }
            aplicarFiltro();
        }
    );
});

function escapeGalHtml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// Delete Single Ghost
async function apagarFoto(btn) {
    const card = btn.closest('.gal-card');
    const nome = card ? card.dataset.nome : '';
    const tipo = card ? (card.dataset.tipo || '') : '';
    const rel  = card ? (card.dataset.rel || '') : '';
    if (!nome) return;

    mostrarModalConfirmacao(
        'Apagar imagem',
        'Apagar permanentemente <strong>' + escapeGalHtml(nome) + '</strong>?',
        async () => {
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="gal-spin" aria-hidden="true"></span>';

            try {
                const res  = await fetch('ajax_galeria_imagens.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'apagar', csrf_token: CSRF, ficheiro: nome, tipo, rel })
                });
                const data = await res.json();
                if (!data.sucesso) throw new Error(data.mensagem || 'Erro ao apagar.');

                card.remove();
                atualizarContadores();
                if (filterSelect && filterSelect.value === 'fantasmas' && document.querySelectorAll('.gal-card.fantasma').length === 0) {
                    filterSelect.value = 'todas';
                    if (typeof window.uiRefreshSelect === 'function') {
                        window.uiRefreshSelect(filterSelect);
                    }
                }
                aplicarFiltro();
                mostrarPopup('Imagem apagada.', 'sucesso');
            } catch (e) {
                mostrarPopup('Erro: ' + e.message, 'erro');
                btn.disabled = false;
                btn.innerHTML = orig;
            }
        }
    );
}
</script>

<?php include '../templates/footer.php'; ?>
