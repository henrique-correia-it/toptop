<?php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: /entrar");
    exit;
}

include '../templates/header.php';
include '../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensagem_sucesso = '';
$mensagem_erro    = '';
switch ($_GET['sucesso'] ?? '') {
    case '1': $mensagem_sucesso = 'Cliente removido com sucesso.'; break;
    case '2': $mensagem_sucesso = 'Clientes removidos com sucesso.'; break;
    case '3': $mensagem_sucesso = 'Estado do cliente atualizado.'; break;
}
switch ($_GET['erro'] ?? '') {
    case '1': $mensagem_erro = 'Erro ao remover o cliente.'; break;
    case 'csrf': $mensagem_erro = 'Erro de segurança. Recarregue a página.'; break;
}

$filtro_q = trim($_GET['q'] ?? '');
$pagina   = max(1, (int)($_GET['p'] ?? 1));
$por_pagina   = 25;
$offset       = ($pagina - 1) * $por_pagina;

$where  = [];
$params = [];
$types  = '';

if ($filtro_q !== '') {
    $like     = '%' . $filtro_q . '%';
    $where[]  = '(c.nome LIKE ? OR c.email LIKE ? OR c.telefone LIKE ?)';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total para paginação
$count_sql = "SELECT COUNT(*) FROM clientes c $where_sql";
if ($params) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
} else {
    $total = (int)$conn->query($count_sql)->fetch_row()[0];
}
$total_paginas = max(1, (int)ceil($total / $por_pagina));

// Dados da página
$sql          = "SELECT c.id, c.nome, c.email, c.telefone, c.ativo, c.aceita_marketing,
                        c.data_criacao, c.ultimo_login,
                        (SELECT COUNT(*) FROM encomendas e WHERE e.cliente_id = c.id) AS total_encomendas
                 FROM clientes c $where_sql
                 ORDER BY c.data_criacao DESC
                 LIMIT ? OFFSET ?";
$params_page  = array_merge($params, [$por_pagina, $offset]);
$types_page   = $types . 'ii';
$stmt         = $conn->prepare($sql);
$stmt->bind_param($types_page, ...$params_page);
$stmt->execute();
$clientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<main class="dashboard-container animate-entry">
<script>
    if ('scrollRestoration' in history) { history.scrollRestoration = 'manual'; }
    window.scrollTo(0, 0);
</script>

<div class="admin-page-header">
    <div class="header-title-container">
        <?php renderBackButton('/admin', 'Painel'); ?>
        <h2>Contas de Clientes</h2>
    </div>
    <div class="header-actions">
        <span class="badge badge-cinzento"><?= $total ?> conta<?= $total !== 1 ? 's' : '' ?></span>
    </div>
</div>

<?php if ($mensagem_sucesso): ?>
    <p class="auth-message success" style="max-width:1200px;margin:0 auto 20px;"><?= htmlspecialchars($mensagem_sucesso) ?></p>
<?php elseif ($mensagem_erro): ?>
    <p class="auth-message error" style="max-width:1200px;margin:0 auto 20px;"><?= htmlspecialchars($mensagem_erro) ?></p>
<?php endif; ?>

<div class="prod-toolbar">
    <div class="prod-toolbar-left"></div>
    <div class="prod-toolbar-right">
        <form method="GET" style="display:flex;align-items:center;gap:10px;">
            <div class="search-input-wrap">
                <svg class="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" name="q" placeholder="Nome, email ou telefone..." value="<?= htmlspecialchars($filtro_q) ?>" class="search-input-toolbar" autocomplete="off">
            </div>
        </form>
        <button class="button btn-sel-mode" id="btn-sel-mode" type="button">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="5" width="4" height="4" rx="1"/><rect x="3" y="13" width="4" height="4" rx="1"/><line x1="11" y1="7" x2="21" y2="7"/><line x1="11" y1="15" x2="21" y2="15"/></svg>
            Selecionar
        </button>
    </div>
</div>

<div class="sel-mode-bar" id="sel-mode-bar">
    <span><span id="sel-count">0</span> selecionado(s)</span>
    <button class="btn-sel-all" id="btn-sel-all" type="button">Selecionar todos</button>
</div>

<div class="table-wrapper" id="table-wrapper" style="max-width:1200px;margin:0 auto;">
    <table class="admin-table">
        <thead>
            <tr>
                <th class="col-sel" style="width:40px;display:none;"></th>
                <th>Nome</th>
                <th>Email</th>
                <th>Telefone</th>
                <th>Estado</th>
                <th>Encomendas</th>
                <th>Registado em</th>
                <th>Último login</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($clientes): ?>
                <?php foreach ($clientes as $c): ?>
                <tr data-id="<?= $c['id'] ?>" data-nome="<?= htmlspecialchars($c['nome']) ?>">
                    <td class="col-sel" style="display:none;">
                        <div class="log-check-circle">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                    </td>
                    <td style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($c['nome']) ?></td>
                    <td style="color:#64748b;"><?= htmlspecialchars($c['email']) ?></td>
                    <td style="color:#64748b;"><?= htmlspecialchars($c['telefone'] ?? '') ?: '<span style="color:#cbd5e1;">—</span>' ?></td>
                    <td>
                        <span class="badge <?= $c['ativo'] ? 'badge-verde' : 'badge-cinzento' ?> badge-ativo-toggle"
                              data-id="<?= $c['id'] ?>"
                              data-ativo="<?= (int)$c['ativo'] ?>"
                              title="Clique para <?= $c['ativo'] ? 'suspender' : 'ativar' ?>"
                              style="cursor:pointer;user-select:none;">
                            <?= $c['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td style="color:<?= $c['total_encomendas'] > 0 ? '#1e293b' : '#94a3b8' ?>;font-weight:<?= $c['total_encomendas'] > 0 ? '600' : '400' ?>;">
                        <?= (int)$c['total_encomendas'] ?>
                    </td>
                    <td style="color:#64748b;font-size:0.85rem;"><?= date('d/m/Y', strtotime($c['data_criacao'])) ?></td>
                    <td style="color:#64748b;font-size:0.85rem;"><?= $c['ultimo_login'] ? date('d/m/Y H:i', strtotime($c['ultimo_login'])) : '<span style="color:#cbd5e1;">—</span>' ?></td>
                    <td>
                        <div class="acoes-tabela">
                            <form action="apagar_cliente_admin.php" method="POST" style="margin:0;">
                                <input type="hidden" name="id"         value="<?= $c['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button type="submit" name="action" value="apagar"
                                        class="btn-del-single btn-apagar-confirmado"
                                        data-titulo-confirmacao="Apagar Cliente"
                                        data-mensagem-confirmacao="Apagar permanentemente a conta de <strong><?= htmlspecialchars($c['nome']) ?></strong>?<br>As suas encomendas ficam guardadas."
                                        title="Apagar cliente"></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="reservation-empty">Nenhum cliente encontrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($total_paginas > 1): ?>
<div style="max-width:1200px;margin:20px auto 0;display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
        <?php $qs = http_build_query(array_filter(['q' => $filtro_q, 'p' => $i > 1 ? $i : null], fn($v) => $v !== '' && $v !== null)); ?>
        <a href="?<?= $qs ?>"
           style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;font-size:0.85rem;font-weight:600;text-decoration:none;border:1px solid <?= $i === $pagina ? '#111827' : '#e2e8f0' ?>;background:<?= $i === $pagina ? '#111827' : '#fff' ?>;color:<?= $i === $pagina ? '#fff' : '#374151' ?>;">
            <?= $i ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

</main>

<!-- Bulk bar -->
<div class="bulk-bar" id="bulk-bar">
    <span class="bulk-bar-count"><span id="bulk-count">0</span> selecionado(s)</span>
    <div class="bulk-bar-spacer"></div>
    <button class="bulk-bar-cancel" id="bulk-cancel" type="button">Cancelar</button>
    <button class="btn-admin-danger" id="bulk-del" type="button">Apagar Selecionados</button>
</div>

<form id="form-delete-clientes" action="apagar_clientes_massa.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div id="form-ids"></div>
</form>

<script>
const CSRF = '<?php echo $_SESSION['csrf_token']; ?>';
document.addEventListener('DOMContentLoaded', function () {
    const btnSelMode  = document.getElementById('btn-sel-mode');
    const selModeBar  = document.getElementById('sel-mode-bar');
    const selCountEl  = document.getElementById('sel-count');
    const btnSelAll   = document.getElementById('btn-sel-all');
    const bulkBar     = document.getElementById('bulk-bar');
    const bulkCountEl = document.getElementById('bulk-count');
    const bulkCancel  = document.getElementById('bulk-cancel');
    const bulkDel     = document.getElementById('bulk-del');
    const tableWrap   = document.getElementById('table-wrapper');
    let selMode = false, selected = new Set();

    function getRows() { return [...document.querySelectorAll('.admin-table tbody tr[data-id]')]; }
    function updateUI() {
        const n = selected.size, total = getRows().length;
        selCountEl.textContent  = n;
        bulkCountEl.textContent = n;
        btnSelAll.textContent   = (n === total && total > 0) ? 'Desselecionar todos' : 'Selecionar todos';
        bulkBar.classList.toggle('visible', n > 0);
    }

    btnSelMode.addEventListener('click', () => {
        selMode = !selMode;
        tableWrap.classList.toggle('sel-mode', selMode);
        btnSelMode.classList.toggle('active', selMode);
        selModeBar.classList.toggle('visible', selMode);
        if (!selMode) { selected.clear(); getRows().forEach(r => r.classList.remove('selecionado')); }
        updateUI();
    });

    bulkCancel.addEventListener('click', () => btnSelMode.click());

    document.querySelector('.admin-table tbody')?.addEventListener('click', function (e) {
        if (!selMode) return;
        const tr = e.target.closest('tr[data-id]');
        if (!tr) return;
        const id = tr.dataset.id;
        if (selected.has(id)) { selected.delete(id); tr.classList.remove('selecionado'); }
        else                   { selected.add(id);    tr.classList.add('selecionado'); }
        updateUI();
    });

    btnSelAll.addEventListener('click', () => {
        const rows  = getRows();
        const allSel = rows.every(r => selected.has(r.dataset.id));
        rows.forEach(r => {
            if (allSel) { selected.delete(r.dataset.id); r.classList.remove('selecionado'); }
            else        { selected.add(r.dataset.id);    r.classList.add('selecionado'); }
        });
        updateUI();
    });

    bulkDel.addEventListener('click', () => {
        const ids = [...selected];
        if (!ids.length) return;
        mostrarModalConfirmacao(
            'Apagar Clientes',
            `Apagar permanentemente ${ids.length} conta(s) de cliente? As encomendas associadas ficam guardadas.`,
            () => {
                const form      = document.getElementById('form-delete-clientes');
                const container = document.getElementById('form-ids');
                container.innerHTML = '';
                ids.forEach(id => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
                    container.appendChild(inp);
                });
                form.submit();
            }
        );
    });

    // Toggle ativo
    document.querySelectorAll('.badge-ativo-toggle').forEach(function (badge) {
        badge.addEventListener('click', function () {
            const id        = this.dataset.id;
            const novoAtivo = parseInt(this.dataset.ativo) ? 0 : 1;
            fetch('/admin/ajax_toggle_cliente_ativo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: CSRF, id: id, ativo: novoAtivo })
            })
            .then(r => r.json())
            .then(data => {
                if (data.sucesso) {
                    this.dataset.ativo  = novoAtivo;
                    this.textContent    = novoAtivo ? 'Ativo' : 'Inativo';
                    this.className      = 'badge ' + (novoAtivo ? 'badge-verde' : 'badge-cinzento') + ' badge-ativo-toggle';
                    this.title          = 'Clique para ' + (novoAtivo ? 'suspender' : 'ativar');
                    mostrarPopup(data.mensagem, 'sucesso');
                } else {
                    mostrarPopup(data.mensagem || 'Erro', 'erro');
                }
            })
            .catch(() => mostrarPopup('Erro de ligação', 'erro'));
        });
    });
});
</script>

<?php
renderContextMenu([
    [
        'href'  => '#',
        'id'    => 'ctx-toggle',
        'label' => 'Ativar / Suspender',
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64A9 9 0 1 1 5.64 6.64"/><line x1="12" y1="2" x2="12" y2="12"/></svg>'
    ],
    'separator',
    [
        'href'  => '#',
        'id'    => 'ctx-delete',
        'class' => 'danger',
        'label' => 'Eliminar Cliente',
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>'
    ]
]);
?>

<script>
document.addEventListener('admin:contextmenu', function (e) {
    const { row, menu } = e.detail;
    const ctxToggle = menu.querySelector('#ctx-toggle');
    const ctxDel    = menu.querySelector('#ctx-delete');

    ctxToggle.onclick = () => row.querySelector('.badge-ativo-toggle')?.click();
    ctxDel.onclick    = () => row.querySelector('.btn-apagar-confirmado')?.click();
});
</script>

<?php include '../templates/footer.php'; ?>
