<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

require_admin_manager('/admin');

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

// Pesquisa e filtro por função (apenas contas da loja: admin e superadmin)
$filtro_q    = trim($_GET['q'] ?? '');
$filtro_role = trim($_GET['role'] ?? '');
$roles_validos = ['admin', 'superadmin'];
if (!in_array($filtro_role, $roles_validos, true)) {
    $filtro_role = '';
}

$conds  = ["id != ?", "role IN ('admin', 'superadmin')"];
$params = [(int)$_SESSION['admin_id']];
$types  = 'i';

if ($filtro_role !== '') {
    $conds[]  = 'role = ?';
    $params[] = $filtro_role;
    $types   .= 's';
}
if ($filtro_q !== '') {
    $like     = '%' . $filtro_q . '%';
    $conds[]  = '(username LIKE ? OR email LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

$sort_map = [
    'username'     => 'username',
    'email'        => 'email',
    'role'         => 'role',
    'data_criacao' => 'data_criacao',
];
$sort_by   = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map) ? $_GET['sort'] : '';
$sort_dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'desc') ? 'desc' : 'asc';
$order_sql = $sort_by !== '' ? ($sort_map[$sort_by] . ' ' . strtoupper($sort_dir) . ', id ASC') : 'id ASC';

$stmt = $conn->prepare("SELECT id, username, email, role, data_criacao FROM administradores WHERE " . implode(' AND ', $conds) . " ORDER BY {$order_sql}");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>


<main class="dashboard-container animate-entry">

    <?php echo ui_page_header("Gestão de Administradores", ['layout' => 'admin', 'back_url' => '/admin', 'back_title' => 'Painel']); ?>

    <?php if ($mensagem_sucesso !== '' || $mensagem_erro !== ''): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof mostrarPopup === 'function') {
            mostrarPopup(
                <?php echo json_encode($mensagem_sucesso !== '' ? $mensagem_sucesso : $mensagem_erro, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                <?php echo json_encode($mensagem_sucesso !== '' ? 'sucesso' : 'erro', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
            );
        }
        if (window.history && typeof window.history.replaceState === 'function') {
            const url = new URL(window.location.href);
            url.searchParams.delete('sucesso');
            url.searchParams.delete('erro');
            window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
        }
    });
    </script>
    <?php endif; ?>

    <?php echo ui_table_toolbar([
        'primary_action' => ui_button('Adicionar Admin', ['href' => 'adicionar_admin.php?return_to=listar_admins', 'class' => 'button btn-with-plus btn-with-plus-text']),
        'filter'         => ['name' => 'role', 'options' => ['' => 'Todas as funções', 'admin' => 'Admin', 'superadmin' => 'Superadmin'], 'value' => $filtro_role, 'title' => 'Filtrar por função'],
        'search'         => ['name' => 'q', 'value' => $filtro_q],
    ]); ?>

    <?php
    $adminRows = [];
    if ($result && $result->num_rows > 0) {
        while ($admin = $result->fetch_assoc()) {
            $role_tone = match($admin['role']) {
                'superadmin' => 'danger',
                'admin'      => 'success',
                default      => 'neutral',
            };
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

            $adminRows[] = [
                'attrs' => ['data-id' => (int)$admin['id'], 'data-username' => $admin['username']],
                'cells' => [
                    ui_e($admin['username']),
                    ui_e($admin['email']),
                    ui_badge((string)$admin['role'], $role_tone),
                    date('d/m/Y', strtotime($admin['data_criacao'])),
                    $acoes,
                ],
            ];
        }
    }

    echo ui_table([
        ['label' => 'Username', 'sort' => 'username'],
        ['label' => 'Email', 'sort' => 'email'],
        ['label' => 'Função', 'sort' => 'role'],
        ['label' => 'Criado em', 'sort' => ['key' => 'data_criacao', 'default_dir' => 'desc']],
        'Ações',
    ], $adminRows, [
        'selectable'        => true,
        'sel_hidden_inline' => true,
        'wrap_id'           => 'table-wrapper',
        'wrap_style'        => 'max-width: 1200px; margin: 0 auto;',
        'empty_message'     => 'Nenhum administrador encontrado.',
        'sort'              => ['by' => $sort_by, 'dir' => $sort_dir],
    ]);
    ?>
</main>

<?php echo ui_bulk_bar('form-delete-admins', 'apagar_admins_massa.php', ['action_label' => 'Apagar Selecionados']); ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    window.initAdminBulkSelection({
        formId: 'form-delete-admins',
        confirmTitle: 'Apagar Administradores',
        confirmMessage: ids => `Apagar permanentemente ${ids.length} administrador(es)?`,
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
    ctxDel.style.display = 'flex';

    // Configurar ação de apagar
    ctxDel.onclick = () => {
        const btnApagar = row.querySelector('.btn-apagar-confirmado');
        if (btnApagar) btnApagar.click();
    };
});
</script>

<?php include '../templates/footer.php'; ?>
