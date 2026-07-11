<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/formatters.php';
require_once __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || $_SESSION['admin_role'] !== 'desenvolvedor') {
    header("Location: /admin/admin.php");
    exit;
}

include '../config/database.php';
include '../templates/header.php';

$por_pagina = 50;
$pagina     = max(1, (int)($_GET['p'] ?? 1));
$offset     = ($pagina - 1) * $por_pagina;

$filtro_resultado = $_GET['resultado'] ?? '';
$filtro_username  = trim($_GET['username'] ?? '');

$where  = [];
$params = [];
$types  = '';

if ($filtro_resultado === 'sucesso' || $filtro_resultado === 'falha') {
    $where[]  = 'resultado = ?';
    $params[] = $filtro_resultado;
    $types   .= 's';
}
if ($filtro_username !== '') {
    $like     = '%' . $filtro_username . '%';
    $where[]  = 'username LIKE ?';
    $params[] = $like;
    $types   .= 's';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total_stmt = $conn->prepare("SELECT COUNT(*) FROM admin_login_logs $where_sql");
if ($params) { $total_stmt->bind_param($types, ...$params); }
$total_stmt->execute();
$total = $total_stmt->get_result()->fetch_row()[0];
$total_stmt->close();
$total_paginas = max(1, ceil($total / $por_pagina));

$stmt = $conn->prepare("SELECT * FROM admin_login_logs $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?");
$p_exec  = $params;
$t_exec  = $types . 'ii';
$p_exec[] = $por_pagina;
$p_exec[] = $offset;
$stmt->bind_param($t_exec, ...$p_exec);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function parseUserAgent(string $ua): array {
    $browser = 'Desconhecido';
    $os      = 'Desconhecido';

    if (preg_match('/Edg\//i', $ua))          $browser = 'Edge';
    elseif (preg_match('/OPR\//i', $ua))       $browser = 'Opera';
    elseif (preg_match('/Chrome\//i', $ua))    $browser = 'Chrome';
    elseif (preg_match('/Firefox\//i', $ua))   $browser = 'Firefox';
    elseif (preg_match('/Safari\//i', $ua))    $browser = 'Safari';

    if (preg_match('/Android/i', $ua))         $os = 'Android';
    elseif (preg_match('/iPhone|iPad/i', $ua)) $os = 'iOS';
    elseif (preg_match('/Windows/i', $ua))     $os = 'Windows';
    elseif (preg_match('/Mac OS/i', $ua))      $os = 'macOS';
    elseif (preg_match('/Linux/i', $ua))       $os = 'Linux';

    return ['browser' => $browser, 'os' => $os];
}

$qs_base = http_build_query(array_filter([
    'resultado' => $filtro_resultado,
    'username'  => $filtro_username,
]));
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
            <h2>Registos de Login</h2>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                mostrarPopup("<?php echo addslashes($_SESSION['flash_message']['texto']); ?>", "<?php echo addslashes($_SESSION['flash_message']['tipo']); ?>");
            });
        </script>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <div class="prod-toolbar">
        <div class="prod-toolbar-right">
            <form method="GET" style="display:flex; align-items:center; gap:10px;">
                <div class="select-wrapper sort-select-wrapper">
                    <select name="resultado" class="sort-select" onchange="this.form.submit()">
                        <option value="">Todos os resultados</option>
                        <option value="sucesso" <?php echo $filtro_resultado === 'sucesso' ? 'selected' : ''; ?>>Sucesso</option>
                        <option value="falha"   <?php echo $filtro_resultado === 'falha'   ? 'selected' : ''; ?>>Falhados</option>
                    </select>
                </div>
                <div class="search-input-wrap">
                    <svg class="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" name="username" placeholder="Filtrar utilizador..."
                           value="<?php echo htmlspecialchars($filtro_username); ?>"
                           class="search-input-toolbar" autocomplete="off">
                </div>
            </form>
            <button class="button btn-sel-mode" id="btn-sel-mode">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="5" width="4" height="4" rx="1"/><rect x="3" y="13" width="4" height="4" rx="1"/><line x1="11" y1="7" x2="21" y2="7"/><line x1="11" y1="15" x2="21" y2="15"/></svg>
                Selecionar
            </button>
        </div>
    </div>

    <!-- ── Selection mode info bar ── -->
        <div class="sel-mode-bar" id="sel-mode-bar">
            <span><span class="sel-count" id="sel-count">0</span> selecionado(s)</span>
            <button class="btn-sel-all" id="btn-sel-all" type="button">Selecionar todos</button>
        </div>

        <div class="table-wrapper" id="table-wrapper" style="max-width: 1200px; width: 100%;">
            <table class="admin-table logs-table">
                <thead>
                    <tr>
                        <th class="col-sel" style="width: 40px; display: none;"></th>
                        <th>Data / Hora</th>
                        <th>Utilizador</th>
                        <th>Resultado</th>
                        <th>Dispositivo</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="reservation-empty">Nenhum registo encontrado para os filtros selecionados.</td>
                    </tr>
                <?php else: foreach ($logs as $log):
                    $device = parseUserAgent($log['user_agent'] ?? '');
                    $data_formatada = date('d/m/Y H:i:s', strtotime($log['created_at']));
                    $relativo = format_time_ago($log['created_at'], 'short');
                    $iniciais = strtoupper(substr($log['username'], 0, 2));
                ?>
                <tr data-id="<?php echo $log['id']; ?>">
                    <td class="col-sel" style="display: none;">
                        <div class="log-check-circle">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                    </td>
                    <td>
                        <div class="time-cell">
                            <span class="date"><?php echo $data_formatada; ?></span>
                            <span class="rel"><?php echo $relativo; ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar"><?php echo $iniciais; ?></div>
                            <div>
                                <span style="font-weight:700; color:#1e293b;"><?php echo htmlspecialchars($log['username']); ?></span>
                                <?php if ($log['role']): ?>
                                    <br><span style="font-size:.7rem; color:#94a3b8; text-transform:uppercase; font-weight:700; letter-spacing:.05em;"><?php echo htmlspecialchars($log['role']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($log['resultado'] === 'sucesso'): ?>
                            <span class="badge badge-verde">Sucesso</span>
                        <?php else: ?>
                            <span class="badge badge-vermelho">Falha</span>
                            <?php if ($log['motivo_falha']): ?>
                                <span class="motivo"><?php echo htmlspecialchars($log['motivo_falha']); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="device-info">
                            <span class="browser"><?php echo htmlspecialchars($device['browser']); ?></span>
                            <span class="os"><?php echo htmlspecialchars($device['os']); ?></span>
                        </div>
                    </td>
                    <td>
                        <span class="ip-pill"><?php echo htmlspecialchars($log['ip']); ?></span>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
        <div class="logs-paginacao">
            <?php if ($pagina > 1): ?>
                <a href="?p=1&<?php echo $qs_base; ?>" title="Primeira Página">«</a>
                <a href="?p=<?php echo $pagina - 1; ?>&<?php echo $qs_base; ?>">‹</a>
            <?php endif; ?>
            
            <?php 
            $max_visible = 5;
            $start = max(1, $pagina - floor($max_visible / 2));
            $end = min($total_paginas, $start + $max_visible - 1);
            if ($end - $start + 1 < $max_visible) $start = max(1, $end - $max_visible + 1);

            for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i === $pagina): ?>
                    <span class="atual"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?p=<?php echo $i; ?>&<?php echo $qs_base; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($pagina < $total_paginas): ?>
                <a href="?p=<?php echo $pagina + 1; ?>&<?php echo $qs_base; ?>">›</a>
                <a href="?p=<?php echo $total_paginas; ?>&<?php echo $qs_base; ?>" title="Última Página">»</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
</main>

<!-- ── Floating bulk action bar ── -->
<div class="bulk-bar" id="bulk-bar">
    <span class="bulk-bar-count"><span id="bulk-count">0</span> selecionado(s)</span>
    <div class="bulk-bar-spacer"></div>
    <button class="bulk-bar-cancel" id="bulk-cancel" type="button">Cancelar</button>
    <button class="btn-admin-danger" id="bulk-del" type="button">
        Apagar Selecionados
    </button>
</div>

<!-- Form para apagar -->
<form id="form-delete-logs" action="apagar_logs_massa.php" method="POST" style="display:none;">
    <?php echo csrf_input(); ?>
    <div id="form-ids"></div>
</form>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnSelMode   = document.getElementById('btn-sel-mode');
    const selModeBar   = document.getElementById('sel-mode-bar');
    const selCountEl   = document.getElementById('sel-count');
    const btnSelAll    = document.getElementById('btn-sel-all');
    const bulkBar      = document.getElementById('bulk-bar');
    const bulkCountEl  = document.getElementById('bulk-count');
    const bulkCancel   = document.getElementById('bulk-cancel');
    const bulkDel      = document.getElementById('bulk-del');
    const tableWrap    = document.getElementById('table-wrapper');
    
    let selMode = false;
    let selected = new Set();

    function updateUI() {
        const n = selected.size;
        selCountEl.textContent = n;
        bulkCountEl.textContent = n;
        const total = document.querySelectorAll('.logs-table tbody tr').length;
        btnSelAll.textContent = (n === total && total > 0) ? 'Desselecionar todos' : 'Selecionar todos';
        bulkBar.classList.toggle('visible', n > 0);
    }

    btnSelMode.addEventListener('click', () => {
        selMode = !selMode;
        tableWrap.classList.toggle('sel-mode', selMode);
        btnSelMode.classList.toggle('active', selMode);
        selModeBar.classList.toggle('visible', selMode);
        if (!selMode) {
            selected.clear();
            document.querySelectorAll('.logs-table tbody tr').forEach(r => r.classList.remove('selecionado'));
        }
        updateUI();
    });

    bulkCancel.addEventListener('click', () => btnSelMode.click());

    document.querySelector('.logs-table tbody')?.addEventListener('click', function(e) {
        if (!selMode) return;
        const tr = e.target.closest('tr');
        if (!tr) return;
        const id = tr.dataset.id;
        if (selected.has(id)) {
            selected.delete(id);
            tr.classList.remove('selecionado');
        } else {
            selected.add(id);
            tr.classList.add('selecionado');
        }
        updateUI();
    });

    btnSelAll.addEventListener('click', () => {
        const rows = document.querySelectorAll('.logs-table tbody tr');
        const allSel = [...rows].every(r => selected.has(r.dataset.id));
        rows.forEach(r => {
            if (allSel) {
                selected.delete(r.dataset.id);
                r.classList.remove('selecionado');
            } else {
                selected.add(r.dataset.id);
                r.classList.add('selecionado');
            }
        });
        updateUI();
    });

    function submitDelete(ids) {
        const form = document.getElementById('form-delete-logs');
        const container = document.getElementById('form-ids');
        container.innerHTML = '';
        ids.forEach(id => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
            container.appendChild(inp);
        });
        form.submit();
    }

    bulkDel.addEventListener('click', () => {
        const ids = [...selected];
        if (!ids.length) return;
        mostrarModalConfirmacao('Limpar Registos', `Apagar permanentemente ${ids.length} registo(s) selecionado(s)?`, () => submitDelete(ids));
    });
});
</script>

<?php include '../templates/footer.php'; ?>
