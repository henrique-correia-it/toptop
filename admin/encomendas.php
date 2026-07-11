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
$offset = ($pagina_atual - 1) * $resultados_por_pagina;

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
    if (is_numeric($termo_pesquisa)) {
        // Se o termo for numérico, pesquisa no nome OU no ID
        $condicoes[] = "(cliente_nome LIKE ? OR id = ?)";
        $termo_like = "%" . $termo_pesquisa . "%";
        $params[] = $termo_like;
        $params[] = (int)$termo_pesquisa;
        $types .= "si";
    } else {
        // Se não for numérico, pesquisa apenas no nome
        $condicoes[] = "cliente_nome LIKE ?";
        $termo_like = "%" . $termo_pesquisa . "%";
        $params[] = $termo_like;
        $types .= "s";
    }
}

$where_clause = !empty($condicoes) ? " WHERE " . implode(" AND ", $condicoes) : "";

// OBTER O NÚMERO TOTAL DE RESULTADOS PARA A PAGINAÇÃO
$stmt_count = $conn->prepare("SELECT COUNT(id) as total " . $sql_base . $where_clause);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_resultados = $stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_resultados / $resultados_por_pagina);
$stmt_count->close();

// OBTER OS RESULTADOS PARA A PÁGINA ATUAL
$params_paginacao = $params;
$types_paginacao = $types;
$types_paginacao .= "ii";
$params_paginacao[] = $resultados_por_pagina;
$params_paginacao[] = $offset;

$stmt = $conn->prepare("SELECT id, cliente_nome, data_encomenda, total, portes_envio, estado, metodo_entrega " . $sql_base . $where_clause . " ORDER BY data_encomenda DESC LIMIT ? OFFSET ?");
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
    'pendente'                 => 'badge-amarelo',
    'a aguardar pagamento'     => 'badge-amarelo',
    'incompleta'               => 'badge-amarelo',
    'pago'                     => 'badge-verde',
    'em processamento'         => 'badge-azul',
    'enviada'                  => 'badge-azul',
    'pronta para levantamento' => 'badge-azul',
    'concluida'                => 'badge-teal',
    'entregue'                 => 'badge-teal',
    'cancelada'                => 'badge-vermelho',
    'pagamento na entrega'     => 'badge-roxo',
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
function gerarPaginacao($pagina_atual, $total_paginas, $params) {
    if ($total_paginas <= 1) return '';
    $html = '<nav class="pagination-container"><ul class="pagination-list">';
    
    // Anterior
    $params_prev = $params;
    $params_prev['pagina'] = $pagina_atual - 1;
    $html .= '<li class="pagination-item prev ' . ($pagina_atual <= 1 ? 'disabled' : '') . '">
                <a class="page-link" href="?' . http_build_query($params_prev) . '" title="Anterior">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </a></li>';

    for ($i = 1; $i <= $total_paginas; $i++) {
        $params_i = $params;
        $params_i['pagina'] = $i;
        $html .= '<li class="pagination-item ' . ($i == $pagina_atual ? 'active' : '') . '">
                    <a class="page-link" href="?' . http_build_query($params_i) . '">' . $i . '</a></li>';
    }

    // Seguinte
    $params_next = $params;
    $params_next['pagina'] = $pagina_atual + 1;
    $html .= '<li class="pagination-item next ' . ($pagina_atual >= $total_paginas ? 'disabled' : '') . '">
                <a class="page-link" href="?' . http_build_query($params_next) . '" title="Seguinte">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </a></li>';

    $html .= '</ul></nav>';
    return $html;
}

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
            <?php renderBackButton('/admin', 'Voltar ao Painel'); ?>
            <h2>Gerir Encomendas</h2>
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
                    <select name="estado" class="sort-select" onchange="this.form.submit()">
                        <option value="todos" <?php echo $filtro_estado === 'todos' ? 'selected' : ''; ?>>Todos os estados</option>
                        <?php foreach($estados_filtro as $estado): ?>
                            <option value="<?php echo $estado; ?>" <?php echo $filtro_estado === $estado ? 'selected' : ''; ?>>
                                <?php echo $map_nomes_estados[$estado] ?? ucfirst($estado); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="search-input-wrap">
                    <svg class="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" name="q" placeholder="Pesquisar cliente ou ID..."
                           value="<?php echo htmlspecialchars($termo_pesquisa); ?>"
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
        <span><span class="sel-count" id="sel-count">0</span> selecionada(s)</span>
        <button class="btn-sel-all" id="btn-sel-all" type="button">Selecionar todas</button>
        <div class="sel-spacer"></div>
    </div>

    <div class="table-wrapper" style="max-width: 1200px; width: 100%;" id="table-wrapper">
        <table class="admin-table tabela-encomendas">
            <thead>
                <tr>
                    <th class="col-sel"></th>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Data</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Pagamento</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($encomendas)): ?>
                    <?php foreach ($encomendas as $enc):
                        $total_final = (float)$enc['total'] + (float)$enc['portes_envio'];
                    ?>
                        <tr data-id="<?php echo $enc['id']; ?>" data-edit-url="detalhes_encomenda.php?id=<?php echo $enc['id']; ?>&return_to=encomendas">
                            <td class="col-sel">
                                <div class="log-check-circle">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                            </td>
                            <td>
                                <span class="order-id-cell">
                                    #<?php echo htmlspecialchars($enc['id']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($enc['cliente_nome']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($enc['data_encomenda'])); ?></td>
                            <td><?php echo format_money($total_final); ?></td>
                            <td><span class="badge <?= htmlspecialchars($map_badge_estados[$enc['estado']] ?? 'badge-cinzento') ?>"><?= htmlspecialchars($map_nomes_estados[$enc['estado']] ?? ucfirst($enc['estado'])) ?></span></td>
                            <td>
                                <?php
                                $mp = $enc['metodo_pagamento'] ?? 'Stripe';
                                $mp_badge = "<span class='badge badge-cinzento'>" . htmlspecialchars($mp) . "</span>";
                                echo $mp_badge;
                                ?>
                            </td>
                            <td>
                                <div class="acoes-tabela">
                                    <a href="detalhes_encomenda.php?id=<?php echo $enc['id']; ?>&return_to=encomendas" class="btn-edit-single" title="Gerir encomenda"></a>
                                    <form action="apagar_encomenda.php" method="POST" style="margin: 0;">
                                        <input type="hidden" name="id" value="<?php echo $enc['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn-del-single btn-apagar-confirmado" data-mensagem-confirmacao="Tem a certeza que quer apagar permanentemente a encomenda #<?php echo $enc['id']; ?>? Esta ação não repõe o stock." title="Apagar encomenda"></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="reservation-empty">Não foram encontradas encomendas com os critérios selecionados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    $query_params = ['estado' => $filtro_estado, 'q' => $termo_pesquisa];
    echo gerarPaginacao($pagina_atual, $total_paginas, $query_params);
    ?>
</main>

<div class="bulk-bar" id="bulk-bar">
    <span class="bulk-bar-count"><span id="bulk-count">0</span> selecionada(s)</span>
    <div class="bulk-bar-spacer"></div>
    <button class="bulk-bar-cancel" id="bulk-cancel" type="button">Cancelar</button>
    <button class="btn-admin-danger" id="bulk-del" type="button" style="padding: 10px 20px;">
        Apagar
    </button>
</div>

<form id="form-delete" action="apagar_encomendas_massa.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>">
    <div id="form-ids"></div>
</form>

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
    const wrapper = document.getElementById('table-wrapper');
    const btnSelMode = document.getElementById('btn-sel-mode');
    const selModeBar = document.getElementById('sel-mode-bar');
    const selCountEl = document.getElementById('sel-count');
    const btnSelAll = document.getElementById('btn-sel-all');
    const bulkBar = document.getElementById('bulk-bar');
    const bulkCountEl = document.getElementById('bulk-count');
    const bulkCancel = document.getElementById('bulk-cancel');
    const bulkDel = document.getElementById('bulk-del');
    const formDelete = document.getElementById('form-delete');
    const formIds = document.getElementById('form-ids');
    const tbody = document.querySelector('.tabela-encomendas tbody');

    let selMode = false;
    let selected = new Set();

    function getRows() {
        return [...document.querySelectorAll('.tabela-encomendas tbody tr[data-id]')];
    }

    function updateUI() {
        const n = selected.size;
        selCountEl.textContent = n;
        bulkCountEl.textContent = n;
        const rows = getRows();
        btnSelAll.textContent = rows.length > 0 && selected.size === rows.length ? 'Desselecionar todas' : 'Selecionar todas';
        bulkBar.classList.toggle('visible', n > 0);
    }

    function clearSelection() {
        selected.clear();
        getRows().forEach(row => row.classList.remove('selecionado'));
        updateUI();
    }

    function toggleSelectionMode(force = null) {
        selMode = force === null ? !selMode : force;
        wrapper.classList.toggle('sel-mode', selMode);
        btnSelMode.classList.toggle('active', selMode);
        selModeBar.classList.toggle('visible', selMode);

        if (!selMode) clearSelection();
        else updateUI();
    }

    function submitDelete(ids) {
        formIds.innerHTML = '';
        ids.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids_encomendas[]';
            input.value = id;
            formIds.appendChild(input);
        });
        formDelete.submit();
    }

    btnSelMode?.addEventListener('click', () => toggleSelectionMode());
    bulkCancel?.addEventListener('click', () => toggleSelectionMode(false));

    tbody?.addEventListener('click', function (e) {
        if (!selMode || e.target.closest('.acoes-tabela')) return;
        const row = e.target.closest('tr[data-id]');
        if (!row) return;

        const id = row.dataset.id;
        if (selected.has(id)) {
            selected.delete(id);
            row.classList.remove('selecionado');
        } else {
            selected.add(id);
            row.classList.add('selecionado');
        }
        updateUI();
    });

    btnSelAll?.addEventListener('click', () => {
        const rows = getRows();
        const allSelected = rows.length > 0 && rows.every(row => selected.has(row.dataset.id));

        rows.forEach(row => {
            if (allSelected) {
                selected.delete(row.dataset.id);
                row.classList.remove('selecionado');
            } else {
                selected.add(row.dataset.id);
                row.classList.add('selecionado');
            }
        });
        updateUI();
    });

    bulkDel?.addEventListener('click', () => {
        const ids = [...selected];
        if (!ids.length) return;
        mostrarModalConfirmacao(
            'Apagar Encomendas',
            `Apagar permanentemente ${ids.length} encomenda(s)? Esta acao nao repoe o stock.`,
            () => submitDelete(ids)
        );
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
