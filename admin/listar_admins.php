<?php
require_once __DIR__ . '/../config/session.php';

// Segurança: Apenas superadmins e desenvolvedores podem aceder
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    header("Location: /admin");
    exit;
}

include '../templates/header.php';
include '../config/database.php';

// Garante que o token CSRF existe na sessão
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Lógica de mensagens
$codigo_sucesso = $_GET['sucesso'] ?? "";
$codigo_erro = $_GET['erro'] ?? "";

$mensagem_sucesso = "";
$mensagem_erro = "";

// Traduzir códigos de sucesso
if ($codigo_sucesso == '1') {
    $mensagem_sucesso = "Administrador criado com sucesso!";
} elseif ($codigo_sucesso == '3') {
    $mensagem_sucesso = "Administrador removido com sucesso!";
} elseif ($codigo_sucesso !== "") {
    $mensagem_sucesso = "Operação concluída com sucesso!";
}

// Traduzir códigos de erro
if ($codigo_erro == '1') {
    $mensagem_erro = "Acesso negado: Não tem permissões para realizar esta ação.";
} elseif ($codigo_erro == '2') {
    $mensagem_erro = "Ação bloqueada: Não é possível apagar a própria conta.";
} elseif ($codigo_erro == '3') {
    $mensagem_erro = "Ação bloqueada: Não pode apagar o último Super-Administrador.";
} elseif ($codigo_erro == '4') {
    $mensagem_erro = "Erro de sistema: Falha ao eliminar da base de dados.";
} elseif ($codigo_erro == 'protecao_dev') {
    $mensagem_erro = "Acesso negado: Apenas Desenvolvedores podem apagar outros Desenvolvedores.";
} elseif ($codigo_erro == 'ultimo_dev') {
    $mensagem_erro = "Ação bloqueada: Não pode apagar o último Desenvolvedor.";
} elseif ($codigo_erro !== "") {
    $mensagem_erro = "Ocorreu um erro desconhecido.";
}


// Pesquisa
$filtro_q = trim($_GET['q'] ?? '');

// Busca de dados
if ($filtro_q !== '') {
    $like = '%' . $filtro_q . '%';
    $stmt = $conn->prepare("SELECT id, username, email, role, data_criacao FROM administradores WHERE id != ? AND (username LIKE ? OR email LIKE ?) ORDER BY id ASC");
    $stmt->bind_param("iss", $_SESSION['admin_id'], $like, $like);
} else {
    $stmt = $conn->prepare("SELECT id, username, email, role, data_criacao FROM administradores WHERE id != ? ORDER BY id ASC");
    $stmt->bind_param("i", $_SESSION['admin_id']);
}
$stmt->execute();
$result = $stmt->get_result();
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
            <?php renderBackButton('/admin', 'Painel'); ?>
            <h2>Gestão de Administradores</h2>
        </div>
    </div>

    <?php if ($mensagem_sucesso): ?>
        <p class="auth-message success" style="max-width: 1200px; margin: 0 auto 20px;"><?php echo htmlspecialchars($mensagem_sucesso); ?></p>
    <?php elseif ($mensagem_erro): ?>
        <p class="auth-message error" style="max-width: 1200px; margin: 0 auto 20px;"><?php echo htmlspecialchars($mensagem_erro); ?></p>
    <?php endif; ?>

    <div class="prod-toolbar">
        <div class="prod-toolbar-left">
            <a href="adicionar_admin.php?return_to=listar_admins" class="button btn-with-plus btn-with-plus-text">Adicionar Admin</a>
        </div>
        <div class="prod-toolbar-right">
            <form method="GET" style="display:flex; align-items:center; gap:10px;">
                <div class="search-input-wrap">
                    <svg class="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" name="q" placeholder="Pesquisar admin..."
                           value="<?= htmlspecialchars($filtro_q) ?>"
                           class="search-input-toolbar" autocomplete="off">
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

    <div class="table-wrapper" id="table-wrapper" style="max-width: 1200px; margin: 0 auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th class="col-sel" style="width:40px; display:none;"></th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Função</th>
                    <th>Criado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($admin = $result->fetch_assoc()): ?>
                        <tr data-id="<?php echo $admin['id']; ?>" data-username="<?php echo htmlspecialchars($admin['username']); ?>">
                            <td class="col-sel" style="display:none;">
                                <div class="log-check-circle">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                            </td>
                            <td style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($admin['username']); ?></td>
                            <td style="color: #64748b;"><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td>
                                <?php
                                $role_cor = match($admin['role']) {
                                    'superadmin'   => 'badge-vermelho',
                                    'admin'        => 'badge-verde',
                                    'desenvolvedor'=> 'badge-dourado',
                                    default        => 'badge-cinzento',
                                };
                                ?>
                                <span class="badge <?= $role_cor ?>">
                                    <?php echo htmlspecialchars($admin['role']); ?>
                                </span>
                            </td>
                            <td style="color: #64748b; font-size: 0.85rem;">
                                <?php echo date('d/m/Y', strtotime($admin['data_criacao'])); ?>
                            </td>
                            <td>
                                <div class="acoes-tabela">
                                    <?php if ($_SESSION['admin_role'] === 'desenvolvedor' || $admin['role'] !== 'desenvolvedor'): ?>
                                        <a href="editar_admin.php?id=<?php echo $admin['id']; ?>&return_to=listar_admins" 
                                           class="btn-edit-single" title="Editar admin"></a>
                                        
                                        <form action="apagar_admin.php" method="POST" style="margin: 0;">
                                            <input type="hidden" name="id" value="<?php echo $admin['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <button type="submit" name="action" value="apagar" 
                                                    class="btn-del-single btn-apagar-confirmado" 
                                                    data-titulo-confirmacao="Apagar Administrador"
                                                    data-mensagem-confirmacao="Tem a certeza que deseja remover <strong><?php echo htmlspecialchars($admin['username']); ?></strong>?"
                                                    title="Apagar admin"></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge badge-cinzento protected-badge">Protegido</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="reservation-empty">Nenhum administrador encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- ── Bulk bar ── -->
<div class="bulk-bar" id="bulk-bar">
    <span class="bulk-bar-count"><span id="bulk-count">0</span> selecionado(s)</span>
    <div class="bulk-bar-spacer"></div>
    <button class="bulk-bar-cancel" id="bulk-cancel" type="button">Cancelar</button>
    <button class="btn-admin-danger" id="bulk-del" type="button">Apagar Selecionados</button>
</div>

<form id="form-delete-admins" action="apagar_admins_massa.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div id="form-ids"></div>
</form>

<script>
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

    let selMode  = false;
    let selected = new Set();

    function getRows() {
        return [...document.querySelectorAll('.admin-table tbody tr[data-id]')];
    }

    function updateUI() {
        const n = selected.size;
        selCountEl.textContent = n;
        bulkCountEl.textContent = n;
        const total = getRows().length;
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
            getRows().forEach(r => r.classList.remove('selecionado'));
        }
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
        const rows = getRows();
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
        mostrarModalConfirmacao('Apagar Administradores', `Apagar permanentemente ${ids.length} administrador(es)?`, () => {
            const form = document.getElementById('form-delete-admins');
            const container = document.getElementById('form-ids');
            container.innerHTML = '';
            ids.forEach(id => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
                container.appendChild(inp);
            });
            form.submit();
        });
    });
});
</script>

<!-- ── Context Menu ── -->
<?php 
renderContextMenu([
    [
        'href' => '#', 
        'id' => 'ctx-edit-full', 
        'label' => 'Editar Administrador', 
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>'
    ],
    'separator',
    [
        'href' => '#', 
        'id' => 'ctx-delete', 
        'class' => 'danger', 
        'label' => 'Eliminar Administrador', 
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>'
    ]
]); 
?>

<script>
document.addEventListener('admin:contextmenu', function(e) {
    const { row, menu } = e.detail;
    const ctxEdit = menu.querySelector('#ctx-edit-full');
    const ctxDel  = menu.querySelector('#ctx-delete');
    
    ctxEdit.href = 'editar_admin.php?id=' + row.dataset.id + '&return_to=listar_admins';
    
    // Esconder eliminar se for protegido
    if (row.querySelector('.protected-badge')) {
        ctxDel.style.display = 'none';
    } else {
        ctxDel.style.display = 'flex';
    }

    // Configurar ação de apagar
    ctxDel.onclick = () => {
        const btnApagar = row.querySelector('.btn-apagar-confirmado');
        if (btnApagar) btnApagar.click();
    };
});
</script>

<?php include '../templates/footer.php'; ?>
