<?php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || $_SESSION['admin_role'] !== 'desenvolvedor') {
    header("Location: /dev");
    exit;
}

include '../config/database.php';

$titulo_pagina = 'Registos de Erros';

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
    <div class="admin-page-header">
        <div class="header-title-container">
            <?php renderBackButton('/dev', 'Painel Dev'); ?>
            <h2>Registos de Erros</h2>
        </div>
    </div>

    <div class="logs-container">
        <div class="logs-card">
            <div class="log-tabs" id="log-tabs">
                <button class="log-tab active" data-log="app">
                    App <span class="log-badge" id="badge-app" style="display:none"></span>
                </button>
                <button class="log-tab" data-log="sql">
                    SQL <span class="log-badge" id="badge-sql" style="display:none"></span>
                </button>
                <button class="log-tab" data-log="email">
                    Email <span class="log-badge" id="badge-email" style="display:none"></span>
                </button>
                <button class="log-tab" data-log="stripe">
                    Stripe <span class="log-badge" id="badge-stripe" style="display:none"></span>
                </button>
                <button class="log-tab" data-log="seguranca">
                    Segurança <span class="log-badge" id="badge-seguranca" style="display:none"></span>
                </button>
            </div>

            <pre id="log-conteudo">A carregar...</pre>

            <div class="log-bar">
                <div class="log-controls-left">
                    <button id="btn-refresh" class="btn-admin-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        Atualizar
                    </button>
                    <button id="btn-limpar" class="btn-admin-danger">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        Limpar Log
                    </button>
                    <label class="log-label-ar">
                        <input type="checkbox" id="log-auto-refresh"> Auto-refresh (15s)
                    </label>
                </div>
                <span class="log-meta" id="log-meta"></span>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const CSRF   = '<?php echo $_SESSION['csrf_token']; ?>';
    const pre    = document.getElementById('log-conteudo');
    let logAtivo = 'app';
    let timer    = null;

    function fmt(bytes) {
        if (!bytes) return '0 B';
        if (bytes < 1024)    return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function carregarLog(log) {
        pre.textContent = 'A carregar ' + log + '.log…';
        fetch('ajax_ver_logs.php?acao=ler&log=' + log + '&linhas=300')
            .then(r => r.json())
            .then(d => {
                if (d.sucesso) {
                    pre.textContent = d.conteudo || '(log vazio)';
                    pre.scrollTop = pre.scrollHeight;
                    document.getElementById('log-meta').textContent =
                        fmt(d.tamanho) + ' — atualizado às ' + new Date().toLocaleTimeString('pt-PT');
                } else {
                    pre.textContent = 'Erro: ' + d.mensagem;
                }
            })
            .catch(() => { pre.textContent = 'Erro de ligação ao servidor.'; });
    }

    function carregarTamanhos() {
        fetch('ajax_ver_logs.php?acao=tamanhos')
            .then(r => r.json())
            .then(d => {
                if (!d.sucesso) return;
                Object.entries(d.tamanhos).forEach(([nome, tam]) => {
                    const badge = document.getElementById('badge-' + nome);
                    if (!badge) return;
                    if (tam > 0) { badge.textContent = fmt(tam); badge.style.display = 'inline-flex'; }
                    else           badge.style.display = 'none';
                });
            })
            .catch(() => {});
    }

    // Troca de tab
    document.getElementById('log-tabs').addEventListener('click', function (e) {
        const tab = e.target.closest('.log-tab');
        if (!tab) return;
        document.querySelectorAll('.log-tab').forEach(b => b.classList.remove('active'));
        tab.classList.add('active');
        logAtivo = tab.dataset.log;
        carregarLog(logAtivo);
    });

    // Atualizar
    document.getElementById('btn-refresh').addEventListener('click', () => carregarLog(logAtivo));

    // Limpar
    document.getElementById('btn-limpar').addEventListener('click', () => {
        window.mostrarModalConfirmacao(
            'Limpar Log',
            'Apagar todo o conteúdo do log <strong>' + logAtivo + '</strong>? Esta ação é irreversível.',
            () => {
                fetch('ajax_ver_logs.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ acao: 'limpar', log: logAtivo, csrf_token: CSRF })
                })
                .then(r => r.json())
                .then(d => {
                    if (d.sucesso) {
                        mostrarPopup(d.mensagem, 'sucesso');
                        carregarLog(logAtivo);
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
    document.getElementById('log-auto-refresh').addEventListener('change', function () {
        clearInterval(timer);
        if (this.checked) timer = setInterval(() => { carregarLog(logAtivo); carregarTamanhos(); }, 15000);
        else timer = null;
    });

    // Arranque
    carregarLog('app');
    carregarTamanhos();
});
</script>

<?php include '../templates/footer.php'; ?>
