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


// Flash message de ações em massa
if (isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])) {
    if (($_SESSION['flash_message']['tipo'] ?? '') === 'sucesso') {
        $mensagem_sucesso = (string)($_SESSION['flash_message']['texto'] ?? '');
    } else {
        $mensagem_erro = (string)($_SESSION['flash_message']['texto'] ?? '');
    }
    unset($_SESSION['flash_message']);
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
        <div style="max-width:1200px; margin:0 auto 20px;"><?php echo ui_alert($mensagem_sucesso, 'success'); ?></div>
    <?php elseif ($mensagem_erro): ?>
        <div style="max-width:1200px; margin:0 auto 20px;"><?php echo ui_alert($mensagem_erro, 'danger'); ?></div>
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

    <?php
    echo ui_selection_bar();

    $adminRows = [];
    if ($result && $result->num_rows > 0) {
        while ($admin = $result->fetch_assoc()) {
            $role_cor = match($admin['role']) {
                'superadmin'    => 'badge-vermelho',
                'admin'         => 'badge-verde',
                'desenvolvedor' => 'badge-dourado',
                default         => 'badge-cinzento',
            };
            if ($_SESSION['admin_role'] === 'desenvolvedor' || $admin['role'] !== 'desenvolvedor') {
                $acoes = '<div class="acoes-tabela">'
                    . ui_icon_button('edit', ['href' => 'editar_admin.php?id=' . (int)$admin['id'] . '&return_to=listar_admins', 'title' => 'Editar admin'])
                    . '<form action="apagar_admin.php" method="POST" style="margin:0;">'
                    . '<input type="hidden" name="id" value="' . (int)$admin['id'] . '">'
                    . '<input type="hidden" name="csrf_token" value="' . ui_e($_SESSION['csrf_token']) . '">'
                    . ui_icon_button('delete', [
                        'type'  => 'submit',
                        'class' => 'btn-apagar-confirmado',
                        'title' => 'Apagar admin',
                        'attrs' => [
                            'name'                      => 'action',
                            'value'                     => 'apagar',
                            'data-titulo-confirmacao'   => 'Apagar Administrador',
                            'data-mensagem-confirmacao' => 'Tem a certeza que deseja remover <strong>' . ui_e($admin['username']) . '</strong>?',
                        ],
                    ])
                    . '</form></div>';
            } else {
                $acoes = '<div class="acoes-tabela"><span class="badge badge-cinzento protected-badge">Protegido</span></div>';
            }

            $adminRows[] = [
                'attrs' => ['data-id' => (int)$admin['id'], 'data-username' => $admin['username']],
                'cells' => [
                    ['style' => 'font-weight:600; color:var(--cor-slate-800);', 'html' => ui_e($admin['username'])],
                    ['style' => 'color:var(--cor-slate-500);', 'html' => ui_e($admin['email'])],
                    '<span class="badge ' . $role_cor . '">' . ui_e($admin['role']) . '</span>',
                    ['style' => 'color:var(--cor-slate-500); font-size:0.85rem;', 'html' => date('d/m/Y', strtotime($admin['data_criacao']))],
                    $acoes,
                ],
            ];
        }
    }

    echo ui_table(['Username', 'Email', 'Função', 'Criado em', 'Ações'], $adminRows, [
        'selectable'        => true,
        'sel_hidden_inline' => true,
        'wrap_id'           => 'table-wrapper',
        'wrap_style'        => 'max-width: 1200px; margin: 0 auto;',
        'empty_message'     => 'Nenhum administrador encontrado.',
    ]);
    ?>
</main>

<?php echo ui_bulk_bar('form-delete-admins', 'apagar_admins_massa.php', ['action_label' => 'Apagar Selecionados']); ?>

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
        return [...document.querySelectorAll('.admin-table tbody tr[data-id]')].filter(r => !r.querySelector('.protected-badge'));
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
        if (!tr || tr.querySelector('.protected-badge')) return;
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
