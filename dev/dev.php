<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/formatters.php';
require_once __DIR__ . '/../config/csrf.php';

// Segurança: Apenas DEVs podem entrar
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || $_SESSION['admin_role'] !== 'desenvolvedor') {
    header("Location: /admin/admin.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/cliente_auth.php';

// ── Lógica Conta de Cliente Vinculada ──
$admin_email = '';
$linked_customer = null;
if (isset($_SESSION['admin_id'])) {
    $stmt = $conn->prepare("SELECT email FROM administradores WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $admin_email = $row['email'];
    }
    $stmt->close();

    if ($admin_email) {
        $stmt = $conn->prepare("SELECT id, nome, email FROM clientes WHERE email = ? AND ativo = 1 LIMIT 1");
        $stmt->bind_param("s", $admin_email);
        $stmt->execute();
        $linked_customer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Ação de login como cliente
if (isset($_GET['login_as_customer']) && $_GET['login_as_customer'] == 1 && $linked_customer) {
    login_cliente_session($linked_customer);
    header("Location: /minha-conta");
    exit;
}

$titulo_pagina = 'Painel Developer';
include '../templates/header.php';

// Token para AJAX
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Dados reais (Agora o header já incluiu as funções e BD)
$stripe_mode = getLojaConfig('stripe_mode', 'live');
$is_live     = $stripe_mode === 'live';

// Header dinâmico (esconder no scroll)
$header_auto_hide = getLojaConfig('header_auto_hide', '1') === '1';

// ── Lógica Fotos Fantasma (Ficheiros sem registo na BD) ──
$referenciados = [];
$queries_f = [
    "SELECT foto_principal AS f FROM produtos WHERE foto_principal != ''",
    "SELECT nome_ficheiro AS f FROM produto_imagens WHERE nome_ficheiro != ''",
    "SELECT foto_snapshot AS f FROM encomenda_itens WHERE foto_snapshot != ''",
    "SELECT foto_capa AS f FROM categorias WHERE foto_capa != ''",
];
foreach ($queries_f as $sql) {
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $nome = basename($row['f']);
            $referenciados[$nome] = true;
        }
    }
}

$dirs_scan = [__DIR__ . '/../public/images/', __DIR__ . '/../public/assets/img_categorias/'];
$exts_allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
$fotos_fantasma_real = 0;

foreach ($dirs_scan as $dir) {
    if (is_dir($dir)) {
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            if (!is_file($dir . $f)) continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, $exts_allowed) && !isset($referenciados[$f])) $fotos_fantasma_real++;
        }
    }
}

// ── Lógica Uso de Disco ──
if (!function_exists('calcFolderSize')) {
    function calcFolderSize($path) {
        $size = 0;
        try {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) { if ($file->isFile()) $size += $file->getSize(); }
        } catch (Exception $e) {}
        return $size;
    }
}
$usage_bytes = calcFolderSize(__DIR__ . '/..');
$espaco_disco_real = format_bytes($usage_bytes);

// ── Lógica Logs Recentes ──
function getRecentAppLogs($limit = 3) {
    $file = LOG_DIR . 'app.log';
    if (!file_exists($file)) return [];
    
    // Ler as linhas ignorando vazias
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return [];
    
    // Inverter para processar do mais recente para o mais antigo
    $lines = array_reverse($lines);
    $formatted = [];
    
    foreach ($lines as $line) {
        if (count($formatted) >= $limit) break;

        // Formato custom: [timestamp] [LEVEL] [Context] Message
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} .*?)\] \[(.*?)\](?: \[(.*?)\])? (.*)/', $line, $m)) {
            $formatted[] = [
                'time'  => date('H:i', strtotime($m[1])),
                'level' => $m[2],
                'msg'   => trim($m[4])
            ];
        } 
        // Formato PHP nativo: [dd-Mmm-yyyy hh:mm:ss Timezone] PHP message
        elseif (preg_match('/^\[(\d{2}-\w{3}-\d{4} .*?)\] (.*)/', $line, $m)) {
            $ts = strtotime($m[1]);
            $formatted[] = [
                'time'  => $ts ? date('H:i', $ts) : substr($m[1], 12, 5),
                'level' => 'PHP-SYS',
                'msg'   => trim($m[2])
            ];
        }
    }
    return $formatted;
}

$recent_logs = getRecentAppLogs(3);
?>

<!-- Carregar CSS Próprio -->
<link rel="stylesheet" href="/public/css/pages/_dev.css?v=<?php echo time(); ?>">

<main class="dashboard-container dev-dashboard animate-entry">
    
    <!-- ── Header ── -->
    <div class="dashboard-header">
        <div class="header-welcome">
            <h2>Developer Workspace</h2>
            <p>Bem-vindo ao centro de controlo técnico, <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>.</p>
        </div>
        <div class="header-actions">
            <form id="backup-db-form" method="post" action="ajax_backup_db.php" style="display:inline; margin:0;">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="current_password" value="">
                <button type="button" class="action-btn-pill btn-backup" title="Gerar dump SQL completo" onclick="document.getElementById('backup-password-dialog').showModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 16px; height: 16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Cópia de Segurança (DB)
                </button>
            </form>
            <style>#backup-password-dialog::backdrop { background:rgba(15,23,42,.48); backdrop-filter:blur(2px); }</style>
            <dialog id="backup-password-dialog" style="position:fixed; inset:50% auto auto 50%; transform:translate(-50%,-50%); margin:0; border:0; border-radius:16px; padding:0; box-shadow:0 20px 60px rgba(15,23,42,.25); max-width:420px; width:calc(100% - 32px);">
                <form method="dialog" onsubmit="event.preventDefault(); descarregarBackupDb();" style="padding:24px; display:grid; gap:16px;">
                    <div>
                        <h3 style="margin:0 0 8px;">Confirmar backup</h3>
                        <p style="margin:0; color:#64748b;">Introduza a sua palavra-passe para descarregar a base de dados.</p>
                    </div>
                    <input id="backup-current-password" type="password" required autocomplete="current-password" placeholder="Palavra-passe atual" style="width:100%; box-sizing:border-box; padding:12px 14px; border:1px solid #cbd5e1; border-radius:10px;">
                    <p id="backup-error" role="alert" style="display:none; margin:0; padding:10px 12px; border-radius:10px; background:#fef2f2; color:#b91c1c;"></p>
                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="action-btn-pill" onclick="document.getElementById('backup-password-dialog').close()">Cancelar</button>
                        <button id="backup-confirm-button" type="button" class="action-btn-pill btn-backup" onclick="descarregarBackupDb()">Descarregar backup</button>
                    </div>
                </form>
            </dialog>
            <script>
            async function descarregarBackupDb() {
                const dialog = document.getElementById('backup-password-dialog');
                const input = document.getElementById('backup-current-password');
                const errorBox = document.getElementById('backup-error');
                const button = document.getElementById('backup-confirm-button');
                const backupForm = document.getElementById('backup-db-form');

                if (!input.reportValidity()) return;

                errorBox.style.display = 'none';
                errorBox.textContent = '';
                button.disabled = true;
                button.textContent = 'A preparar...';
                backupForm.elements.current_password.value = input.value;

                try {
                    const response = await fetch(backupForm.action, {
                        method: 'POST',
                        body: new FormData(backupForm)
                    });

                    if (!response.ok) {
                        const mensagem = (await response.text()).trim();
                        throw new Error(mensagem || 'Nao foi possivel gerar o backup.');
                    }

                    const blob = await response.blob();
                    const disposition = response.headers.get('Content-Disposition') || '';
                    const match = disposition.match(/filename="?([^";]+)"?/i);
                    const filename = match ? match[1] : 'backup_db.sql';
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(url);
                    input.value = '';
                    backupForm.elements.current_password.value = '';
                    dialog.close();
                } catch (error) {
                    errorBox.textContent = error.message;
                    errorBox.style.display = 'block';
                    input.select();
                    input.focus();
                } finally {
                    button.disabled = false;
                    button.textContent = 'Descarregar backup';
                }
            }
            </script>
            <div class="simulate-wrap" style="position:relative;">
                <button type="button" id="btn-criar-encomenda-fake" class="action-btn-pill btn-simulate" title="Gerar encomenda fictícia" style="border: 1px solid #e2e8f0;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 16px; height: 16px;"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    Simular Encomenda
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 14px; height: 14px; margin-left: 2px; transition: transform 0.2s;" id="simulate-chevron"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div id="simulate-dropdown" class="simulate-dropdown" style="display:none;">
                    <div class="simulate-dropdown-item" data-estado="pago">
                        <span class="sim-dot" style="background:#10b981;"></span>
                        <div><strong>Pago</strong><span>Pagamento confirmado</span></div>
                    </div>
                    <div class="simulate-dropdown-item" data-estado="a aguardar pagamento">
                        <span class="sim-dot" style="background:#f59e0b;"></span>
                        <div><strong>A Aguardar Pagamento</strong><span>Stripe criado, sem confirmação</span></div>
                    </div>
                    <div class="simulate-dropdown-item" data-estado="incompleta">
                        <span class="sim-dot" style="background:#f59e0b;"></span>
                        <div><strong>Incompleta</strong><span>Checkout abandonado</span></div>
                    </div>
                    <div class="simulate-dropdown-item" data-estado="em processamento">
                        <span class="sim-dot" style="background:#3b82f6;"></span>
                        <div><strong>Em Processamento</strong><span>Admin a preparar</span></div>
                    </div>
                    <div class="simulate-dropdown-item" data-estado="enviada">
                        <span class="sim-dot" style="background:#3b82f6;"></span>
                        <div><strong>Enviada</strong><span>Com número de tracking</span></div>
                    </div>
                    <div class="simulate-dropdown-item" data-estado="pronta para levantamento">
                        <span class="sim-dot" style="background:#3b82f6;"></span>
                        <div><strong>Pronta para Levantamento</strong><span>Aguarda recolha na loja</span></div>
                    </div>
                    <div class="simulate-dropdown-item" data-estado="concluida">
                        <span class="sim-dot" style="background:#0f766e;"></span>
                        <div><strong>Concluída</strong><span>Entregue e fechada</span></div>
                    </div>
                    <div class="simulate-dropdown-item" data-estado="cancelada">
                        <span class="sim-dot" style="background:#ef4444;"></span>
                        <div><strong>Cancelada</strong><span>Cancelada e stock reposto</span></div>
                    </div>
                    <div class="simulate-dropdown-item" data-estado="pagamento na entrega">
                        <span class="sim-dot" style="background:#7c3aed;"></span>
                        <div><strong>Pagamento na Entrega</strong><span>Pagar ao receber / na loja</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Stats Grid ── -->
    <div class="stats-grid">
        <!-- Stripe Toggle (Funcional) -->
        <div class="stat-card <?php echo $is_live ? 'stripe-live' : 'stripe-test'; ?>" id="stripe-card-toggle" style="cursor: pointer; position: relative;">
            <div class="stat-card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            </div>
            <div class="stat-card-info">
                <span class="stat-number" id="stripe-mode-label"><?php echo $is_live ? 'LIVE' : 'TEST'; ?></span>
                <span class="stat-label">Stripe Mode</span>
                <input type="checkbox" id="stripe-mode-hidden-checkbox" style="display:none;" <?php echo $is_live ? 'checked' : ''; ?>>
            </div>
        </div>

        <!-- Ghost Photos -->
        <a href="galeria_imagens.php" class="stat-card">
            <div class="stat-card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
            </div>
            <div class="stat-card-info">
                <span class="stat-number"><?php echo $fotos_fantasma_real; ?></span>
                <span class="stat-label">Fotos Fantasma</span>
            </div>
        </a>

        <!-- Disk Space -->
        <a href="uso_armazenamento.php" class="stat-card">
            <div class="stat-card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            </div>
            <div class="stat-card-info">
                <span class="stat-number"><?php echo $espaco_disco_real; ?></span>
                <span class="stat-label">Espaço em Disco</span>
            </div>
        </a>

        <!-- Header Dinâmico Toggle (Funcional) -->
        <div class="stat-card <?php echo $header_auto_hide ? 'header-hide-on' : 'header-hide-off'; ?>" id="header-hide-toggle" style="cursor: pointer; position: relative;" title="Esconder o cabeçalho ao fazer scroll para baixo">
            <div class="stat-card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline><polyline points="18 21 12 15 6 21"></polyline></svg>
            </div>
            <div class="stat-card-info">
                <span class="stat-number" id="header-hide-label"><?php echo $header_auto_hide ? 'ON' : 'OFF'; ?></span>
                <span class="stat-label">Header Dinâmico</span>
                <input type="checkbox" id="header-hide-checkbox" style="display:none;" <?php echo $header_auto_hide ? 'checked' : ''; ?>>
            </div>
        </div>
    </div>

    <!-- ── Layout Principal ── -->
    <!-- ── Layout em Grid (Alinhamento Preciso) ── -->
    <div class="dashboard-grid-cols">
        
        <!-- Coluna Esquerda: Conteúdo Principal -->
        <div class="main-actions-col">
            <!-- Área: Gestão de Ativos -->
            <section class="grid-area-gestao">
                <h3 class="dashboard-section-title">Gestão de Ativos</h3>
                <div class="dashboard-grid">
                    <a href="galeria_imagens.php" class="nav-card">
                        <div class="nav-card-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
                        <div class="nav-card-body">
                            <h3>Galeria do Servidor</h3>
                            <p>Gestão centralizada de todas as imagens.</p>
                        </div>
                    </a>

                    <a href="uso_armazenamento.php" class="nav-card">
                        <div class="nav-card-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
                        <div class="nav-card-body">
                            <h3>Uso de Armazenamento</h3>
                            <p>Análise detalhada do espaço em disco.</p>
                        </div>
                    </a>
                </div>
            </section>

            <!-- Área: Segurança & Logs -->
            <section class="grid-area-seguranca">
                <h3 class="dashboard-section-title section-spacer">Segurança & Logs</h3>
                <div class="dashboard-grid">
                    <a href="login_logs.php" class="nav-card">
                        <div class="nav-card-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg></div>
                        <div class="nav-card-body">
                            <h3>Registos de Login</h3>
                            <p>Histórico de acessos administrativos.</p>
                        </div>
                    </a>

                    <a href="ver_logs.php" class="nav-card">
                        <div class="nav-card-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                        <div class="nav-card-body">
                            <h3>Registos de Erros</h3>
                            <p>Logs de App, SQL, Email e Stripe.</p>
                        </div>
                    </a>
                </div>
            </section>

            <!-- Área: Ferramentas de Programador -->
            <section class="grid-area-ferramentas">
                <h3 class="dashboard-section-title section-spacer">Ferramentas de Programador</h3>
                <div class="dashboard-grid">
                    <a href="previews.php" class="nav-card">
                        <div class="nav-card-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
                        <div class="nav-card-body">
                            <h3>Developer Previews</h3>
                            <p>Visualizar templates com dados simulados.</p>
                        </div>
                    </a>
                </div>
            </section>
        </div>

        <!-- Coluna Direita: Sidebar -->
        <div class="activity-col">
            <!-- Área: Logs Sidebar -->
            <aside class="grid-area-logs">
                <h3 class="dashboard-section-title">Logs Recentes (App)</h3>
                <div class="activity-feed-container">
                    <?php if (empty($recent_logs)): ?>
                        <p style="font-size: 0.8rem; color: #64748b; padding: 10px;">Sem registos recentes.</p>
                    <?php else: ?>
                        <?php foreach ($recent_logs as $log): 
                            $icon_class = 'dev-terminal';
                            $is_error = in_array($log['level'], ['ERROR', 'FATAL', 'FATAL-SHUTDOWN']);
                            if ($is_error) $icon_class = 'encomenda'; // Vermelho
                            elseif (in_array($log['level'], ['WARNING', 'PHP-SYS'])) $icon_class = 'mensagem'; // Azul
                        ?>
                            <a href="ver_logs.php" class="activity-item" style="text-decoration: none; color: inherit; cursor: pointer;">
                                <div class="activity-icon-wrapper <?php echo $icon_class; ?>" style="<?php echo $is_error ? 'background:#fee2e2;color:#ef4444;' : ''; ?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                                </div>
                                <div class="activity-content">
                                    <strong><?php echo htmlspecialchars($log['level']); ?></strong>
                                    <span title="<?php echo htmlspecialchars($log['msg']); ?>"><?php echo htmlspecialchars(mb_strimwidth($log['msg'], 0, 45, "...")); ?></span>
                                </div>
                                <span class="activity-time"><?php echo $log['time']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>


        </div>

    </div>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Stripe Toggle (no Card) ──
    const stripeCard = document.getElementById('stripe-card-toggle');
    const stripeLabel = document.getElementById('stripe-mode-label');
    const stripeCheckbox = document.getElementById('stripe-mode-hidden-checkbox');

    if (stripeCard) {
        stripeCard.addEventListener('click', function () {
            const isLive = !stripeCheckbox.checked;
            const mode   = isLive ? 'live' : 'test';
            
            const fd = new FormData();
            fd.append('stripe_mode', mode);
            fd.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

            fetch('ajax_toggle_stripe.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.sucesso) {
                        stripeCheckbox.checked = isLive;
                        stripeLabel.textContent = mode.toUpperCase();
                        
                        // Atualizar Cores do Card
                        if (isLive) {
                            stripeCard.classList.remove('stripe-test');
                            stripeCard.classList.add('stripe-live');
                        } else {
                            stripeCard.classList.remove('stripe-live');
                            stripeCard.classList.add('stripe-test');
                        }

                        mostrarPopup('Modo Stripe alterado para ' + mode.toUpperCase(), 'sucesso');
                    } else {
                        mostrarPopup('Erro ao alterar modo Stripe.', 'erro');
                    }
                })
                .catch(() => { mostrarPopup('Erro de rede ao alterar Stripe.', 'erro'); });
        });
    }


    // ── Header Dinâmico Toggle (no Card) ──
    const headerCard = document.getElementById('header-hide-toggle');
    const headerLabel = document.getElementById('header-hide-label');
    const headerCheckbox = document.getElementById('header-hide-checkbox');

    if (headerCard) {
        headerCard.addEventListener('click', function () {
            const enable = !headerCheckbox.checked;

            const fd = new FormData();
            fd.append('header_auto_hide', enable ? '1' : '0');
            fd.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

            fetch('ajax_toggle_header_scroll.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.sucesso) {
                        headerCheckbox.checked = enable;
                        headerLabel.textContent = enable ? 'ON' : 'OFF';
                        headerCard.classList.toggle('header-hide-on', enable);
                        headerCard.classList.toggle('header-hide-off', !enable);
                        mostrarPopup('Header dinâmico ' + (enable ? 'ativado' : 'desativado') + '. A atualizar...', 'sucesso');
                        // Recarregar para o header da página atual refletir a mudança
                        setTimeout(() => location.reload(), 900);
                    } else {
                        mostrarPopup(data.mensagem || 'Erro ao alterar.', 'erro');
                    }
                })
                .catch(() => { mostrarPopup('Erro de rede ao alterar o header.', 'erro'); });
        });
    }


    // ── Simular Encomenda (dropdown) ──
    const btnFakeEnc = document.getElementById('btn-criar-encomenda-fake');
    const simDropdown = document.getElementById('simulate-dropdown');
    const simChevron  = document.getElementById('simulate-chevron');

    function criarEncomendaFake(estado) {
        simDropdown.style.display = 'none';
        simChevron.style.transform = '';
        btnFakeEnc.disabled = true;

        fetch('ajax_criar_encomenda_fake.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: '<?php echo $_SESSION['csrf_token']; ?>', estado })
        })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                mostrarPopup(data.mensagem, 'sucesso');
                setTimeout(() => {
                    window.location.href = '/admin/detalhes_encomenda.php?id=' + data.id + '&return_to=dev';
                }, 1500);
            } else {
                mostrarPopup('Erro: ' + data.mensagem, 'erro');
                btnFakeEnc.disabled = false;
            }
        })
        .catch(() => {
            mostrarPopup('Erro de ligação.', 'erro');
            btnFakeEnc.disabled = false;
        });
    }

    if (btnFakeEnc) {
        btnFakeEnc.addEventListener('click', function (e) {
            e.stopPropagation();
            const open = simDropdown.style.display !== 'none';
            simDropdown.style.display = open ? 'none' : 'block';
            simChevron.style.transform = open ? '' : 'rotate(180deg)';
        });

        simDropdown.querySelectorAll('.simulate-dropdown-item').forEach(item => {
            item.addEventListener('click', function () {
                criarEncomendaFake(this.dataset.estado);
            });
        });

        document.addEventListener('click', function () {
            simDropdown.style.display = 'none';
            simChevron.style.transform = '';
        });
        simDropdown.addEventListener('click', e => e.stopPropagation());
    }

});
</script>

<?php include '../templates/footer.php'; ?>
