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
}
switch ($_GET['erro'] ?? '') {
    case '1': $mensagem_erro = 'Erro ao remover o cliente.'; break;
    case 'csrf': $mensagem_erro = 'Erro de segurança. Recarregue a página.'; break;
}

$filtro_q      = trim($_GET['q'] ?? '');
$filtro_estado = $_GET['estado'] ?? '';
$pagina        = max(1, (int)($_GET['p'] ?? 1));
$por_pagina    = 25;

$where  = [];
$params = [];
$types  = '';

if ($filtro_estado === 'ativo') {
    $where[] = 'c.ativo = 1';
} elseif ($filtro_estado === 'inativo') {
    $where[] = 'c.ativo = 0';
}
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
$pagina = min($pagina, $total_paginas);
$offset = ($pagina - 1) * $por_pagina;

$sort_map = [
    'nome'         => 'c.nome',
    'email'        => 'c.email',
    'telefone'     => 'c.telefone',
    'estado'       => 'c.ativo',
    'encomendas'   => 'total_encomendas',
    'data_criacao' => 'c.data_criacao',
    'ultimo_login' => 'c.ultimo_login',
];
$sort_by   = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map) ? $_GET['sort'] : 'data_criacao';
$sort_dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'asc') ? 'asc' : (isset($_GET['dir']) ? 'desc' : 'desc');
$order_sql = $sort_map[$sort_by] . ' ' . strtoupper($sort_dir) . ', c.id DESC';

// Dados da página
$sql          = "SELECT c.id, c.nome, c.email, c.telefone, c.ativo, c.aceita_marketing,
                        c.data_criacao, c.ultimo_login,
                        (SELECT COUNT(*) FROM encomendas e WHERE e.cliente_id = c.id) AS total_encomendas
                 FROM clientes c $where_sql
                 ORDER BY {$order_sql}
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

<?= ui_page_header('Contas de Clientes', [
    'layout'     => 'admin',
    'back_url'   => '/admin',
    'back_title' => 'Painel',
]) ?>

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

<?= ui_table_toolbar([
    'filter' => ['name' => 'estado', 'options' => ['' => 'Todos os estados', 'ativo' => 'Ativos', 'inativo' => 'Inativos'], 'value' => $filtro_estado, 'title' => 'Filtrar por estado'],
    'search' => ['name' => 'q', 'value' => $filtro_q],
]) ?>

<?php
$clienteRows = [];
foreach ($clientes as $c) {
    $acoes = '<div class="acoes-tabela"><form action="apagar_cliente_admin.php" method="POST" style="margin:0;">'
        . '<input type="hidden" name="id" value="' . (int)$c['id'] . '">'
        . '<input type="hidden" name="csrf_token" value="' . ui_e($_SESSION['csrf_token']) . '">'
        . ui_icon_button('delete', [
            'type'  => 'submit',
            'class' => 'btn-apagar-confirmado',
            'title' => 'Apagar cliente',
            'attrs' => [
                'name'                      => 'action',
                'value'                     => 'apagar',
                'data-titulo-confirmacao'   => 'Apagar Cliente',
                'data-mensagem-confirmacao' => 'Apagar permanentemente a conta de <strong>' . ui_e($c['nome']) . '</strong>?<br>As suas encomendas ficam guardadas.',
            ],
        ])
        . '</form></div>';

    $badgeAtivo = ui_badge($c['ativo'] ? 'Ativo' : 'Inativo', $c['ativo'] ? 'success' : 'neutral', [
        'tag'         => 'button',
        'interactive' => true,
        'class'       => 'badge-ativo-toggle',
        'title'       => 'Clique para ' . ($c['ativo'] ? 'suspender' : 'ativar'),
        'attrs'       => [
            'data-id'    => (int)$c['id'],
            'data-ativo' => (int)$c['ativo'],
        ],
    ]);

    $clienteRows[] = [
        'attrs' => ['data-id' => (int)$c['id'], 'data-nome' => $c['nome']],
        'cells' => [
            ui_e($c['nome']),
            ui_e($c['email']),
            ui_e($c['telefone'] ?? '') ?: '-',
            $badgeAtivo,
            (string)(int)$c['total_encomendas'],
            date('d/m/Y', strtotime($c['data_criacao'])),
            $c['ultimo_login'] ? date('d/m/Y H:i', strtotime($c['ultimo_login'])) : '-',
            $acoes,
        ],
    ];
}

echo ui_table([
    ['label' => 'Nome', 'sort' => 'nome'],
    ['label' => 'Email', 'sort' => 'email'],
    ['label' => 'Telefone', 'sort' => 'telefone'],
    ['label' => 'Estado', 'sort' => ['key' => 'estado', 'default_dir' => 'desc']],
    ['label' => 'Encomendas', 'sort' => ['key' => 'encomendas', 'default_dir' => 'desc']],
    ['label' => 'Registado em', 'sort' => ['key' => 'data_criacao', 'default_dir' => 'desc']],
    ['label' => 'Último login', 'sort' => ['key' => 'ultimo_login', 'default_dir' => 'desc']],
    'Ações',
], $clienteRows, [
    'selectable'        => true,
    'sel_hidden_inline' => true,
    'wrap_id'           => 'table-wrapper',
    'wrap_style'        => 'max-width:1200px;margin:0 auto;',
    'empty_message'     => 'Nenhum cliente encontrado.',
    'sort'              => ['by' => $sort_by, 'dir' => $sort_dir, 'page_param' => 'p'],
]);
?>

<?= ui_pagination($pagina, $total_paginas, fn($i) => '?' . http_build_query(array_filter([
    'estado' => $filtro_estado,
    'q'      => $filtro_q,
    'sort'   => isset($_GET['sort']) ? $sort_by : null,
    'dir'    => isset($_GET['sort']) ? $sort_dir : null,
    'p'      => $i > 1 ? $i : null,
], fn($v) => $v !== '' && $v !== null))) ?>

</main>

<?php echo ui_bulk_bar('form-delete-clientes', 'apagar_clientes_massa.php', ['action_label' => 'Apagar Selecionados']); ?>

<script>
const CSRF = '<?php echo $_SESSION['csrf_token']; ?>';
document.addEventListener('DOMContentLoaded', function () {
    const bulkSel = window.initAdminBulkSelection({
        formId: 'form-delete-clientes',
        confirmTitle: 'Apagar Clientes',
        confirmMessage: ids => `Apagar permanentemente ${ids.length} conta(s) de cliente? As encomendas associadas ficam guardadas.`,
    });

    // Toggle ativo
    document.querySelectorAll('.badge-ativo-toggle').forEach(function (badge) {
        badge.addEventListener('click', function (e) {
            if ((bulkSel && bulkSel.isSelMode()) || this.dataset.loading === '1') return;
            e.stopPropagation();
            const id        = this.dataset.id;
            const novoAtivo = parseInt(this.dataset.ativo, 10) ? 0 : 1;
            this.dataset.loading = '1';
            this.style.opacity = '0.6';
            fetch('/admin/ajax_toggle_cliente_ativo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: CSRF, id: id, ativo: novoAtivo })
            })
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => {
                if (data.sucesso) {
                    this.dataset.ativo  = novoAtivo;
                    this.textContent    = novoAtivo ? 'Ativo' : 'Inativo';
                    this.className      = 'ui-badge ' + (novoAtivo ? 'ui-badge--success' : 'ui-badge--neutral') + ' ui-badge--interactive badge-ativo-toggle';
                    this.title          = 'Clique para ' + (novoAtivo ? 'suspender' : 'ativar');
                    mostrarPopup(data.mensagem, 'sucesso');
                } else {
                    mostrarPopup(data.mensagem || 'Erro', 'erro');
                }
            })
            .catch(() => mostrarPopup('Erro de ligação', 'erro'))
            .finally(() => {
                delete this.dataset.loading;
                this.style.opacity = '';
            });
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
