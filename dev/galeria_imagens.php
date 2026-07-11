<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/formatters.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || $_SESSION['admin_role'] !== 'desenvolvedor') {
    header("Location: /admin/admin.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$images_dir  = __DIR__ . '/../public/images/';
$cats_dir    = __DIR__ . '/../public/assets/img_categorias/';
$logos_dir   = __DIR__ . '/../public/assets/header/';
$allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

$imagens = [];

// Carregar imagens de produtos
if (is_dir($images_dir)) {
    foreach (scandir($images_dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $images_dir . $f;
        if (!is_file($path)) continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) continue;
        $imagens[] = ['nome' => $f, 'tamanho' => filesize($path), 'data' => filemtime($path), 'tipo' => 'produto'];
    }
}

// Carregar imagens de categorias
if (is_dir($cats_dir)) {
    foreach (scandir($cats_dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $cats_dir . $f;
        if (!is_file($path)) continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) continue;
        $imagens[] = ['nome' => $f, 'tamanho' => filesize($path), 'data' => filemtime($path), 'tipo' => 'categoria'];
    }
}

// Carregar logos do cabeçalho
if (is_dir($logos_dir)) {
    foreach (scandir($logos_dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $logos_dir . $f;
        if (!is_file($path)) continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) continue;
        $imagens[] = ['nome' => $f, 'tamanho' => filesize($path), 'data' => filemtime($path), 'tipo' => 'logo'];
    }
}

usort($imagens, fn($a, $b) => $b['data'] - $a['data']);

$total_ficheiros = count($imagens);
$total_bytes     = array_sum(array_column($imagens, 'tamanho'));

include '../templates/header.php';
?>


<main class="dashboard-container animate-entry">
    
<!-- Bloquear scroll automático no refresh -->
<script>
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    window.scrollTo(0, 0);
</script>
<div class="gal-page">

    <div class="admin-page-header">
        <div class="header-title-container">
            <?php renderBackButton('/dev', 'Painel Dev'); ?>
            <h2>Galeria de Imagens do Servidor</h2>
        </div>
    </div>

    <!-- ── Page Header ── -->
    <div class="gal-ph">
        <div class="gal-ph-left"></div>
        <div class="gal-stats">
            <div class="gal-stat">
                <div class="gal-stat-icon gal-stat-icon--imgs">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
                <div class="gal-stat-text">
                    <small>Imagens</small>
                    <strong><?php echo $total_ficheiros; ?></strong>
                </div>
            </div>
            <div class="gal-stat">
                <div class="gal-stat-icon gal-stat-icon--size">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div class="gal-stat-text">
                    <small>Espaço</small>
                    <strong><?php echo format_bytes($total_bytes, 1, 'B'); ?></strong>
                </div>
            </div>
            <div class="gal-stat">
                <div class="gal-stat-icon gal-stat-icon--ghost">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34"/><polygon points="18 2 22 6 12 16 8 16 8 12 18 2"/></svg>
                </div>
                <div class="gal-stat-text">
                    <small>Fantasmas</small>
                    <strong id="stat-ghost">—</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Toolbar ── -->
    <div class="gal-toolbar">
        <div class="gal-tabs">
            <button class="gal-tab active" data-filter="todas">Todas</button>
            <button class="gal-tab" data-filter="produto">Produtos</button>
            <button class="gal-tab" data-filter="categoria">Categorias</button>
            <button class="gal-tab" data-filter="logo">Logos</button>
            <button class="gal-tab" data-filter="fantasmas" id="tab-fantasmas" disabled>Fantasmas</button>
        </div>
        <div class="gal-spacer"></div>
        <button class="gal-btn-del-all" id="btn-del-all">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            <span id="del-all-txt">Apagar todas as fantasmas</span>
        </button>
        <button class="gal-btn-scan" id="btn-scan">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <span id="scan-txt">Reanalisar</span>
        </button>
    </div>

    <!-- ── Result banner ── -->
    <div class="gal-banner" id="gal-banner"></div>

    <!-- ── Grid ── -->
    <div class="gal-grid" id="gal-grid">
        <?php if (empty($imagens)): ?>
            <div class="gal-empty">Nenhuma imagem encontrada em <code>/public/images/</code>.</div>
        <?php else: ?>
            <?php foreach ($imagens as $img): ?>
            <div class="gal-card"
                 data-nome="<?php echo htmlspecialchars($img['nome']); ?>"
                 data-size="<?php echo format_bytes($img['tamanho'], 1, 'B'); ?>"
                 data-tipo="<?php echo $img['tipo']; ?>"
                 onclick="abrirLightbox(this)">

                <?php 
                $src_path = '/public/images/';
                if ($img['tipo'] === 'categoria') $src_path = '/public/assets/img_categorias/';
                if ($img['tipo'] === 'logo') $src_path = '/public/assets/header/';
                ?>
                <img src="<?php echo $src_path . htmlspecialchars($img['nome']); ?>" alt="" loading="lazy">

                <div class="gal-overlay">
                    <div class="gal-overlay-nome"><?php echo htmlspecialchars($img['nome']); ?></div>
                    <div class="gal-overlay-size"><?php echo format_bytes($img['tamanho'], 1, 'B'); ?></div>
                </div>

                <div class="gal-ghost-overlay">
                    <span class="gal-ghost-label">Fantasma</span>
                    <button class="gal-ghost-del" onclick="event.stopPropagation(); apagarFoto(this, '<?php echo htmlspecialchars($img['nome']); ?>')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                        Apagar
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</main>

<!-- ── Lightbox ── -->
<div class="gal-lb" id="lightbox" onclick="fecharLightbox()">
    <button class="btn-close-unified gal-lb-close" onclick="fecharLightbox()" title="Fechar">&times;</button>
    <div class="gal-lb-inner" onclick="event.stopPropagation()">
        <img id="lb-img" src="" alt="">
        <div class="gal-lb-info" id="lb-info"></div>
    </div>
</div>

<script>
const CSRF = '<?php echo $_SESSION['csrf_token']; ?>';
let filtroAtual = 'todas';

// ── Tabs ──────────────────────────────────────────────────────────────────
document.querySelectorAll('.gal-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        if (btn.disabled) return;
        document.querySelectorAll('.gal-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        filtroAtual = btn.dataset.filter;
        aplicarFiltro();
    });
});

function aplicarFiltro() {
    document.querySelectorAll('.gal-card').forEach(card => {
        const fantasma = card.classList.contains('fantasma');
        const tipo     = card.dataset.tipo;
        
        let show = false;
        if (filtroAtual === 'todas') show = true;
        else if (filtroAtual === 'fantasmas') show = fantasma;
        else show = (tipo === filtroAtual);

        card.classList.toggle('hidden-card', !show);
    });
}

// ── Lightbox ─────────────────────────────────────────────────────────────
function abrirLightbox(card) {
    if (card.classList.contains('fantasma')) return;
    const nome = card.dataset.nome;
    const size = card.dataset.size;
    const tipo = card.dataset.tipo;
    
    let path = '/public/images/';
    if (tipo === 'categoria') path = '/public/assets/img_categorias/';
    if (tipo === 'logo') path = '/public/assets/header/';
    
    document.getElementById('lb-img').src = path + nome;
    document.getElementById('lb-info').textContent = nome + '  ·  ' + size;
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function fecharLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.getElementById('lb-img').src = '';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharLightbox(); });

// ── Scan ──────────────────────────────────────────────────────────────────
async function runScan() {
    const btn = document.getElementById('btn-scan');
    const txt = document.getElementById('scan-txt');
    btn.disabled = true;
    txt.innerHTML = '<span class="gal-spin"></span> A analisar...';

    try {
        const res  = await fetch('ajax_galeria_imagens.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'analisar', csrf_token: CSRF })
        });
        const data = await res.json();
        if (!data.sucesso) throw new Error(data.mensagem || 'Erro.');

        const ghosts = new Set(data.fantasmas);
        const total  = data.fantasmas.length;

        document.querySelectorAll('.gal-card').forEach(card => {
            const isGhost = ghosts.has(card.dataset.nome);
            card.classList.toggle('fantasma', isGhost);
            card.style.cursor = isGhost ? 'default' : 'zoom-in';
        });

        // Stats
        document.getElementById('stat-ghost').textContent = total;

        // Tab
        const tabF = document.getElementById('tab-fantasmas');
        tabF.disabled = false;
        tabF.textContent = 'Fantasmas (' + total + ')';

        // Delete-all button
        const btnDelAll = document.getElementById('btn-del-all');
        btnDelAll.classList.toggle('show', total > 0);

        // Banner
        const banner = document.getElementById('gal-banner');
        banner.className = 'gal-banner show';
        if (total === 0) {
            banner.classList.add('gal-banner--ok');
            banner.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Tudo limpo — nenhuma foto fantasma encontrada.';
        } else {
            banner.classList.add('gal-banner--warn');
            banner.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> <strong>' + total + ' foto(s) fantasma</strong> encontrada(s) — não referenciadas em nenhum produto, galeria ou encomenda.';
        }

        txt.textContent = 'Reanalisar';
        btn.disabled = false;
        aplicarFiltro();

    } catch (e) {
        mostrarPopup('Erro: ' + e.message, 'erro');
        txt.textContent = 'Reanalisar';
        btn.disabled = false;
    }
}

document.getElementById('btn-scan').addEventListener('click', runScan);

// Auto-scan on page load
runScan();

// ── Delete-All ────────────────────────────────────────────────────────────
document.getElementById('btn-del-all').addEventListener('click', () => {
    const fantasmas = [...document.querySelectorAll('.gal-card.fantasma')];
    if (!fantasmas.length) return;

    mostrarModalConfirmacao(
        'Apagar todas as fantasmas',
        'Vai apagar permanentemente <strong>' + fantasmas.length + ' imagem(ns)</strong> não referenciadas. Esta ação não pode ser revertida.',
        async () => {
            const txt = document.getElementById('del-all-txt');
            const btn = document.getElementById('btn-del-all');
            btn.disabled = true;
            txt.innerHTML = '<span class="gal-spin"></span> A apagar...';

            let apagadas = 0;
            for (const card of fantasmas) {
                const nome = card.dataset.nome;
                try {
                    const res  = await fetch('ajax_galeria_imagens.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'apagar', csrf_token: CSRF, ficheiro: nome })
                    });
                    const data = await res.json();
                    if (data.sucesso) {
                        card.style.transition = 'opacity .25s, transform .25s';
                        card.style.opacity = '0'; card.style.transform = 'scale(.9)';
                        setTimeout(() => card.remove(), 260);
                        apagadas++;
                    }
                } catch (_) {}
            }

            document.getElementById('stat-ghost').textContent = 0;
            document.getElementById('tab-fantasmas').textContent = 'Fantasmas (0)';
            btn.classList.remove('show');

            const banner = document.getElementById('gal-banner');
            banner.className = 'gal-banner show gal-banner--ok';
            banner.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> ' + apagadas + ' imagem(ns) apagada(s) com sucesso.';

            if (filtroAtual === 'fantasmas') {
                document.querySelectorAll('.gal-tab')[0].click();
            }
        }
    );
});

// ── Delete single ─────────────────────────────────────────────────────────
async function apagarFoto(btn, nome) {
    mostrarModalConfirmacao(
        'Apagar imagem',
        'Apagar permanentemente <strong>' + nome + '</strong>?',
        async () => {
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="gal-spin" style="border-top-color:#fff;border-color:rgba(255,255,255,.3)"></span>';

            try {
                const res  = await fetch('ajax_galeria_imagens.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'apagar', csrf_token: CSRF, ficheiro: nome })
                });
                const data = await res.json();
                if (!data.sucesso) throw new Error(data.mensagem);

                const card = btn.closest('.gal-card');
                card.style.transition = 'opacity .3s, transform .3s';
                card.style.opacity = '0'; card.style.transform = 'scale(.85)';
                setTimeout(() => card.remove(), 300);

                const cur = parseInt(document.getElementById('stat-ghost').textContent) || 0;
                const novo = Math.max(0, cur - 1);
                document.getElementById('stat-ghost').textContent = novo;
                const tabF = document.getElementById('tab-fantasmas');
                tabF.textContent = 'Fantasmas (' + novo + ')';
                if (novo === 0) document.getElementById('btn-del-all').classList.remove('show');

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
