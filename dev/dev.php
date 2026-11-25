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
require_once __DIR__ . '/includes/storage_helper.php';

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_as_customer']) && $_POST['login_as_customer'] == 1 && csrf_from_post() && $linked_customer) {
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

// ── Lógica Fotos Fantasma & Uso de Disco (fonte única de verdade) ──
$storage             = dev_calcular_armazenamento();
$fotos_fantasma_real = count(dev_listar_fantasmas($conn, $storage['galeria']));
$espaco_disco_real   = format_bytes($storage['total_bytes']);

// ── Lógica Logs Recentes (agrega todos os logs por data/hora descendente) ──
function getRecentAppLogs($limit = 5) {
    $fontes = [
        'app'       => LOG_DIR . 'app.log',
        'sql'       => LOG_DIR . 'sql.log',
        'js'        => LOG_DIR . 'js.log',
        'stripe'    => LOG_DIR . 'stripe.log',
        'email'     => LOG_DIR . 'email.log',
        'seguranca' => LOG_DIR . 'seguranca.log',
    ];
    $entries = [];

    foreach ($fontes as $fonte => $file) {
        if (!file_exists($file)) continue;
        $fp = @fopen($file, 'rb');
        if ($fp === false) continue;
        $size = filesize($file);
        $readBytes = min($size ?: 0, 8192);
        if ($readBytes <= 0) { fclose($fp); continue; }
        fseek($fp, -$readBytes, SEEK_END);
        $chunk = fread($fp, $readBytes);
        fclose($fp);
        if (!is_string($chunk) || $chunk === '') continue;

        $lines = preg_split('/\r\n|\r|\n/', $chunk);
        if ($size > $readBytes && count($lines) > 1) array_shift($lines);

        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} .*?)\] \[(.*?)\](?: \[(.*?)\])? (.*)/', $line, $m)) {
                $ts = strtotime($m[1]) ?: 0;
                $msgClean = trim(explode(' | ', $m[4], 2)[0]);
                $entries[] = [
                    'ts'    => $ts,
                    'time'  => $ts ? date('H:i', $ts) : '',
                    'level' => $m[2] . ' · ' . $fonte,
                    'msg'   => $msgClean,
                ];
            } elseif (preg_match('/^\[(\d{2}-\w{3}-\d{4} .*?)\] (.*)/', $line, $m)) {
                $ts = strtotime($m[1]) ?: 0;
                $entries[] = [
                    'ts'    => $ts,
                    'time'  => $ts ? date('H:i', $ts) : substr($m[1], 12, 5),
                    'level' => 'PHP-SYS · ' . $fonte,
                    'msg'   => trim($m[2]),
                ];
            }
        }
    }

    usort($entries, fn($a, $b) => $b['ts'] <=> $a['ts']);
    return array_slice($entries, 0, $limit);
}

$recent_logs = getRecentAppLogs(5);
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
                <?php echo ui_button('Cópia de Segurança (DB)', ['class' => 'action-btn-pill btn-backup', 'title' => 'Gerar dump SQL completo', 'onclick' => "document.getElementById('backup-password-dialog').showModal()", 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> ']); ?>
            </form>
            <dialog id="backup-password-dialog" class="ui-dialog">
                <div class="qe-card">
                    <div class="qe-hd">
                        <h3>Confirmar backup</h3>
                    </div>
                    <form method="dialog" onsubmit="event.preventDefault(); descarregarBackupDb();" class="qe-body">
                        <p style="margin:0;">Introduza a sua palavra-passe para descarregar a base de dados.</p>
                        <?php echo ui_input('', ['id' => 'backup-current-password', 'type' => 'password', 'class' => 'qe-in', 'required' => true, 'autocomplete' => 'current-password', 'placeholder' => 'Palavra-passe atual']); ?>
                        <p id="backup-error" role="alert" style="display:none; margin:0; padding:10px 12px; border-radius:10px; background:var(--cor-perigo-bg); color:#b91c1c; font-size:0.84rem;"></p>
                        <div class="qe-btns">
                            <?php
                            echo ui_button('Cancelar', ['onclick' => "document.getElementById('backup-password-dialog').close()"]);
                            echo ui_button('Descarregar backup', ['id' => 'backup-confirm-button', 'variant' => 'primary', 'onclick' => 'descarregarBackupDb()']);
                            ?>
                        </div>
                    </form>
                </div>
            </dialog>
            <dialog id="stripe-password-dialog" class="ui-dialog">
                <div class="qe-card">
                    <div class="qe-hd">
                        <h3>Confirmar alteração do Stripe</h3>
                    </div>
                    <form id="stripe-password-form" method="dialog" class="qe-body">
                        <p style="margin:0;">Introduza a sua palavra-passe para alterar o modo do Stripe para <strong id="stripe-target-mode-label" style="color:var(--cor-slate-900);">LIVE</strong>.</p>
                        <?php echo ui_input('', ['id' => 'stripe-current-password', 'type' => 'password', 'class' => 'qe-in', 'required' => true, 'autocomplete' => 'current-password', 'placeholder' => 'Palavra-passe atual']); ?>
                        <p id="stripe-password-error" role="alert" style="display:none; margin:0; padding:10px 12px; border-radius:10px; background:var(--cor-perigo-bg); color:#b91c1c; font-size:0.84rem;"></p>
                        <div class="qe-btns">
                            <?php
                            echo ui_button('Cancelar', ['onclick' => "document.getElementById('stripe-password-dialog').close()"]);
                            echo ui_button('Confirmar alteração', ['id' => 'stripe-confirm-button', 'type' => 'submit', 'variant' => 'primary']);
                            ?>
                        </div>
                    </form>
                </div>
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
                <?php echo ui_button('Simular Encomenda', ['id' => 'btn-criar-encomenda-fake', 'class' => 'action-btn-pill btn-simulate', 'title' => 'Gerar encomenda fictícia', 'html' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg> Simular Encomenda <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; margin-left: 2px; transition: transform 0.2s;" id="simulate-chevron"><polyline points="6 9 12 15 18 9"/></svg>']); ?>
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
                        <span class="sim-dot" style="background:#10b981;"></span>
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
        <?php
        echo ui_stat_card($is_live ? 'LIVE' : 'TEST', 'Stripe Mode', [
            'class'      => $is_live ? 'stripe-live' : 'stripe-test',
            'value_id'   => 'stripe-mode-label',
            'attrs'      => ['id' => 'stripe-card-toggle', 'style' => 'cursor: pointer; position: relative;'],
            'icon'       => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
            'extra_html' => '<input type="checkbox" id="stripe-mode-hidden-checkbox" style="display:none;"' . ($is_live ? ' checked' : '') . '>',
        ]);

        echo ui_stat_card((string)$fotos_fantasma_real, 'Fotos Fantasma', [
            'href' => 'galeria_imagens.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>',
        ]);

        echo ui_stat_card((string)$espaco_disco_real, 'Espaço em Disco', [
            'href' => 'uso_armazenamento.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        ]);
        ?>
    </div>

    <div class="dashboard-grid-cols">
        <div class="main-actions-col">
            <section class="grid-area-gestao">
                <h3 class="dashboard-section-title">Gestão de Ativos</h3>
                <div class="dashboard-grid">
                    <?php
                    echo ui_nav_card('Galeria do Servidor', 'Gestão centralizada de todas as imagens.', 'galeria_imagens.php', [
                        'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
                    ]);
                    echo ui_nav_card('Uso de Armazenamento', 'Análise detalhada do espaço em disco.', 'uso_armazenamento.php', [
                        'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
                    ]);
                    ?>
                </div>
            </section>

            <section class="grid-area-seguranca">
                <h3 class="dashboard-section-title section-spacer">Segurança & Logs</h3>
                <div class="dashboard-grid">
                    <?php
                    echo ui_nav_card('Registos de Login', 'Histórico de acessos administrativos.', 'login_logs.php', [
                        'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>',
                    ]);
                    echo ui_nav_card('Registos de Erros', 'Logs de App, SQL, Email, Stripe, Segurança e JS.', 'ver_logs.php', [
                        'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                    ]);
                    echo ui_nav_card('Desenvolvedores', 'Criar e gerir contas de programador.', 'listar_devs.php', [
                        'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
                    ]);
                    ?>
                </div>
            </section>

            <section class="grid-area-ferramentas">
                <h3 class="dashboard-section-title section-spacer">Ferramentas de Programador</h3>
                <div class="dashboard-grid">
                    <?php
                    echo ui_nav_card('Developer Previews', 'Visualizar templates com dados simulados.', 'previews.php', [
                        'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
                    ]);
                    echo ui_nav_card('Emails de Sistema', 'Autenticação, segurança e alertas internos.', 'emails_sistema.php', [
                        'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
                    ]);
                    ?>
                </div>
            </section>
        </div>

        <!-- Coluna Direita: Sidebar -->
        <div class="activity-col">
            <!-- Área: Logs Sidebar -->
            <aside class="grid-area-logs">
                <h3 class="dashboard-section-title">Logs Recentes</h3>
                <div class="activity-feed-container">
                    <?php if (empty($recent_logs)): ?>
                        <p style="font-size: 0.8rem; color: #64748b; padding: 10px;">Sem registos recentes.</p>
                    <?php else: ?>
                        <?php foreach ($recent_logs as $log):
                            $icon_class = 'dev-terminal';
                            $is_error   = (bool)preg_match('/^(ERROR|FATAL|PHP-ERROR|SQL|EMAIL|JS-|HTTP-5)/i', $log['level']);
                            $is_warn    = !$is_error && (bool)preg_match('/^(WARNING|NOTICE|DEPRECATED|SECURITY|404|PHP-SYS)/i', $log['level']);
                            if ($is_error) $icon_class = 'encomenda'; // Vermelho
                            elseif ($is_warn) $icon_class = 'mensagem'; // Azul
                        ?>
                            <a href="ver_logs.php" class="activity-item" style="text-decoration: none; color: inherit; cursor: pointer;">
                                <div class="activity-icon-wrapper <?php echo $icon_class; ?>" style="<?php echo $is_error ? 'background:#fee2e2;color:#ef4444;' : ''; ?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
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

    // ── Stripe Toggle (no Card, com reautenticação por palavra-passe) ──
    const stripeCard          = document.getElementById('stripe-card-toggle');
    const stripeLabel         = document.getElementById('stripe-mode-label');
    const stripeCheckbox      = document.getElementById('stripe-mode-hidden-checkbox');
    const stripeDialog        = document.getElementById('stripe-password-dialog');
    const stripeForm          = document.getElementById('stripe-password-form');
    const stripePwInput       = document.getElementById('stripe-current-password');
    const stripePwError       = document.getElementById('stripe-password-error');
    const stripeConfirmBtn    = document.getElementById('stripe-confirm-button');
    const stripeTargetModeLbl = document.getElementById('stripe-target-mode-label');

    if (stripeCard && stripeDialog && stripeForm) {
        stripeCard.addEventListener('click', function () {
            const isLive = !stripeCheckbox.checked;
            const mode   = isLive ? 'LIVE' : 'TEST';
            if (stripeTargetModeLbl) stripeTargetModeLbl.textContent = mode;
            if (stripePwError) {
                stripePwError.style.display = 'none';
                stripePwError.textContent = '';
            }
            if (stripePwInput) stripePwInput.value = '';
            stripeDialog.showModal();
            if (stripePwInput) stripePwInput.focus();
        });

        stripeForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!stripePwInput || !stripePwInput.reportValidity()) return;

            const isLive = !stripeCheckbox.checked;
            const mode   = isLive ? 'live' : 'test';

            if (stripePwError) {
                stripePwError.style.display = 'none';
                stripePwError.textContent = '';
            }
            if (stripeConfirmBtn) {
                stripeConfirmBtn.disabled = true;
                stripeConfirmBtn.textContent = 'A verificar...';
            }

            const fd = new FormData();
            fd.append('stripe_mode', mode);
            fd.append('current_password', stripePwInput.value);
            fd.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

            fetch('ajax_toggle_stripe.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.sucesso) {
                        stripeCheckbox.checked = isLive;
                        stripeLabel.textContent = mode.toUpperCase();

                        if (isLive) {
                            stripeCard.classList.remove('stripe-test');
                            stripeCard.classList.add('stripe-live');
                        } else {
                            stripeCard.classList.remove('stripe-live');
                            stripeCard.classList.add('stripe-test');
                        }

                        stripePwInput.value = '';
                        stripeDialog.close();
                        mostrarPopup('Modo Stripe alterado para ' + mode.toUpperCase(), 'sucesso');
                    } else {
                        if (stripePwError) {
                            stripePwError.textContent = data.mensagem || 'Palavra-passe incorreta.';
                            stripePwError.style.display = 'block';
                        }
                        stripePwInput.select();
                        stripePwInput.focus();
                    }
                })
                .catch(() => {
                    if (stripePwError) {
                        stripePwError.textContent = 'Erro de rede ao alterar o modo Stripe.';
                        stripePwError.style.display = 'block';
                    }
                })
                .finally(() => {
                    if (stripeConfirmBtn) {
                        stripeConfirmBtn.disabled = false;
                        stripeConfirmBtn.textContent = 'Confirmar alteração';
                    }
                });
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
                    window.location.href = '/admin/detalhes_encomenda.php?id=' + data.id + '&return_to=encomendas';
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
