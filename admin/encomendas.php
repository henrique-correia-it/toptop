<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';
require_once __DIR__ . '/../config/formatters.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: /entrar");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- LÓGICA DE PAGINAÇÃO ---
$resultados_por_pagina = 15;
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

// --- LÓGICA DE FILTRAGEM E PESQUISA ---
$filtro_estado = $_GET['estado'] ?? 'todos';
$termo_pesquisa = trim($_GET['q'] ?? '');

// CONSTRUIR A QUERY BASE E CONDIÇÕES
$sql_base = "FROM encomendas";
$condicoes = ["estado != 'incompleta'"];
$params = [];
$types = "";

if ($filtro_estado === 'por_enviar') {
    $condicoes[] = "estado = 'pago'";
} elseif ($filtro_estado !== 'todos' && !empty($filtro_estado)) {
    $condicoes[] = "estado = ?";
    $params[] = $filtro_estado;
    $types .= "s";
}

if (!empty($termo_pesquisa)) {
    $termo_like = "%" . $termo_pesquisa . "%";
    if (is_numeric($termo_pesquisa)) {
        $condicoes[] = "(cliente_nome LIKE ? OR cliente_email LIKE ? OR codigo_tracking LIKE ? OR id = ?)";
        $params[] = $termo_like;
        $params[] = $termo_like;
        $params[] = $termo_like;
        $params[] = (int)$termo_pesquisa;
        $types .= "sssi";
    } else {
        $condicoes[] = "(cliente_nome LIKE ? OR cliente_email LIKE ? OR codigo_tracking LIKE ?)";
        $params[] = $termo_like;
        $params[] = $termo_like;
        $params[] = $termo_like;
        $types .= "sss";
    }
}

$where_clause = !empty($condicoes) ? " WHERE " . implode(" AND ", $condicoes) : "";

// OBTER O NÚMERO TOTAL DE RESULTADOS PARA A PAGINAÇÃO
$stmt_count = $conn->prepare("SELECT COUNT(id) as total " . $sql_base . $where_clause);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_resultados = (int)$stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = max(1, (int)ceil($total_resultados / $resultados_por_pagina));
$pagina_atual = min($pagina_atual, $total_paginas);
$offset = ($pagina_atual - 1) * $resultados_por_pagina;
$stmt_count->close();

$sort_map = [
    'id'      => 'id',
    'cliente' => 'cliente_nome',
    'data'    => 'data_encomenda',
    'total'   => "(total + CASE WHEN LOWER(TRIM(COALESCE(metodo_entrega, ''))) = 'recolha' THEN 0 ELSE portes_envio END)",
    'entrega' => 'metodo_entrega',
    'estado'  => 'estado',
];
$sort_by  = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map) ? $_GET['sort'] : 'data';
$sort_dir = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'asc') ? 'asc' : (isset($_GET['dir']) ? 'desc' : 'desc');
$order_sql = $sort_map[$sort_by] . ' ' . strtoupper($sort_dir) . ', id DESC';

// OBTER OS RESULTADOS PARA A PÁGINA ATUAL
$params_paginacao = $params;
$types_paginacao = $types;
$types_paginacao .= "ii";
$params_paginacao[] = $resultados_por_pagina;
$params_paginacao[] = $offset;

$stmt = $conn->prepare("SELECT id, cliente_nome, data_encomenda, total, portes_envio, estado, metodo_entrega, metodo_pagamento " . $sql_base . $where_clause . " ORDER BY {$order_sql} LIMIT ? OFFSET ?");
$stmt->bind_param($types_paginacao, ...$params_paginacao);
$stmt->execute();
$encomendas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$map_nomes_estados = [
    'pendente'                 => 'Pendente',
    'pago'                     => 'Pago',
    'em processamento'         => 'Em Processamento',
    'a aguardar pagamento'     => 'Aguardar Pagamento',
    'pronta para levantamento' => 'Pronta p/ Levantamento',
    'enviada'                  => 'Enviada',
    'concluida'                => 'Concluída',
    'cancelada'                => 'Cancelada',
    'pagamento na entrega'     => 'Pag. na Entrega',
];

$map_badge_estados = [
    'pendente'                 => 'warning',
    'a aguardar pagamento'     => 'warning',
    'incompleta'               => 'warning',
    'pago'                     => 'success',
    'em processamento'         => 'info',
    'enviada'                  => 'info',
    'pronta para levantamento' => 'info',
    'concluida'                => 'success',
    'entregue'                 => 'success',
    'cancelada'                => 'danger',
    'pagamento na entrega'     => 'purple',
];

$estados_existentes = [];
$res_estados = $conn->query("SELECT DISTINCT estado FROM encomendas WHERE estado != 'incompleta'");
if ($res_estados) {
    while ($estado_row = $res_estados->fetch_assoc()) {
        if (!empty($estado_row['estado'])) {
            $estados_existentes[] = $estado_row['estado'];
        }
    }
}

$estados_filtro = array_values(array_filter(array_keys($map_nomes_estados), fn($estado) => in_array($estado, $estados_existentes, true)));
foreach ($estados_existentes as $estado_existente) {
    if (!in_array($estado_existente, $estados_filtro, true)) {
        $estados_filtro[] = $estado_existente;
    }
}

// FUNÇÃO PARA GERAR OS LINKS DE PAGINAÇÃO


include '../templates/header.php';
?>

<main class="dashboard-container animate-entry">

    <?php echo ui_page_header("Gerir Encomendas", ['layout' => 'admin', 'back_url' => '/admin', 'back_title' => 'Voltar ao Painel']); ?>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                mostrarPopup(<?php echo json_encode($_SESSION['flash_message']['texto'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($_SESSION['flash_message']['tipo'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
            });
        </script>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php
    $opcoes_estado_filtro = [
        'todos'      => 'Todos os estados',
        'por_enviar' => 'Por Enviar',
    ];
    foreach ($estados_filtro as $estado) {
        $opcoes_estado_filtro[$estado] = $map_nomes_estados[$estado] ?? ucfirst($estado);
    }
    echo ui_table_toolbar([
        'filter' => ['name' => 'estado', 'options' => $opcoes_estado_filtro, 'value' => $filtro_estado, 'title' => 'Filtrar por estado'],
        'search' => ['name' => 'q', 'value' => $termo_pesquisa],
    ]);
    ?>

    <?php
    $encRows = [];
    foreach ($encomendas as $enc) {
        $portes_enc  = (strtolower(trim((string)($enc['metodo_entrega'] ?? ''))) === 'recolha') ? 0.0 : (float)$enc['portes_envio'];
        $total_final = (float)$enc['total'] + $portes_enc;
        $mp = $enc['metodo_pagamento'] ?? 'Stripe';
        $acoes = '<div class="acoes-tabela">'
            . ui_icon_button('edit', ['href' => 'detalhes_encomenda.php?id=' . (int)$enc['id'] . '&return_to=encomendas', 'title' => 'Gerir encomenda'])
            . '<form action="apagar_encomenda.php" method="POST" style="margin:0;">'
            . '<input type="hidden" name="id" value="' . (int)$enc['id'] . '">'
            . '<input type="hidden" name="csrf_token" value="' . ui_e($_SESSION['csrf_token']) . '">'
            . ui_icon_button('delete', [
                'type'  => 'submit',
                'class' => 'btn-apagar-confirmado',
                'title' => 'Apagar encomenda',
                'attrs' => [
                    'data-mensagem-confirmacao' => 'Tem a certeza que quer apagar permanentemente a encomenda #' . (int)$enc['id'] . '? Esta ação não repõe o stock.',
                ],
            ])
            . '</form></div>';

        $isRecolha = strtolower(trim((string)($enc['metodo_entrega'] ?? ''))) === 'recolha';
        $entregaBadge = ui_badge($isRecolha ? 'Recolha' : 'Envio', $isRecolha ? 'purple' : 'neutral');

        $encRows[] = [
            'attrs' => [
                'data-id'       => (int)$enc['id'],
                'data-edit-url' => 'detalhes_encomenda.php?id=' . (int)$enc['id'] . '&return_to=encomendas',
            ],
            'cells' => [
                '<span class="order-id-cell">#' . (int)$enc['id'] . '</span>',
                ui_e($enc['cliente_nome']),
                date('d/m/Y H:i', strtotime($enc['data_encomenda'])),
                format_money($total_final),
                $entregaBadge,
                ui_badge($map_nomes_estados[$enc['estado']] ?? ucfirst($enc['estado']), $map_badge_estados[$enc['estado']] ?? 'neutral'),
                $acoes,
            ],
        ];
    }

    echo ui_table([
        ['label' => 'ID', 'sort' => ['key' => 'id', 'default_dir' => 'desc']],
        ['label' => 'Cliente', 'sort' => 'cliente'],
        ['label' => 'Data', 'sort' => ['key' => 'data', 'default_dir' => 'desc']],
        ['label' => 'Total', 'sort' => ['key' => 'total', 'default_dir' => 'desc']],
        ['label' => 'Entrega', 'sort' => 'entrega'],
        ['label' => 'Estado', 'sort' => 'estado'],
        'Ações',
    ], $encRows, [
        'selectable'    => true,
        'wrap_id'       => 'table-wrapper',
        'wrap_style'    => 'max-width: 1200px; width: 100%;',
        'table_class'   => 'admin-table tabela-encomendas',
        'empty_message' => 'Não foram encontradas encomendas com os critérios selecionados.',
        'sort'          => ['by' => $sort_by, 'dir' => $sort_dir, 'page_param' => 'pagina'],
    ]);
    ?>

    <?php
    $query_params = [
        'estado' => $filtro_estado,
        'q'      => $termo_pesquisa,
        'sort'   => isset($_GET['sort']) ? $sort_by : null,
        'dir'    => isset($_GET['sort']) ? $sort_dir : null,
    ];
    echo ui_pagination((int)$pagina_atual, (int)$total_paginas, function ($p) use ($query_params) {
        $params = array_merge($query_params, ['pagina' => $p]);
        return '?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
    });
    ?>
</main>

<?php echo ui_bulk_bar('form-delete', 'apagar_encomendas_massa.php', [
    'unit'         => 'selecionada(s)',
    'action_label' => 'Apagar',
    'hidden'       => ['return_to' => $_SERVER['REQUEST_URI'] ?? '/admin/encomendas.php'],
]); ?>

<!-- ── Context Menu ── -->
<?php 
renderContextMenu([
    [
        'href' => '#', 
        'id' => 'ctx-edit-full', 
        'label' => 'Gerir Encomenda', 
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>'
    ],
    'separator',
    [
        'href' => '#', 
        'id' => 'ctx-delete', 
        'class' => 'danger', 
        'label' => 'Apagar Encomenda', 
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>'
    ]
]); 
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    window.initAdminBulkSelection({
        formId: 'form-delete',
        inputName: 'ids_encomendas[]',
        selectAllLabel: 'Selecionar todas',
        deselectAllLabel: 'Desselecionar todas',
        confirmTitle: 'Apagar Encomendas',
        confirmMessage: ids => `Apagar permanentemente ${ids.length} encomenda(s)? Esta ação não repõe o stock.`,
    });

    document.addEventListener('admin:contextmenu', function(e) {
        const { row, menu } = e.detail;
        menu.querySelector('#ctx-edit-full').href = 'detalhes_encomenda.php?id=' + row.dataset.id + '&return_to=encomendas';
        menu.querySelector('#ctx-delete').onclick = () => {
            const btnApagar = row.querySelector('.btn-apagar-confirmado');
            if (btnApagar) btnApagar.click();
        };
    });
});
</script>

<?php include '../templates/footer.php'; ?>
