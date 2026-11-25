<?php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: /entrar");
    exit;
}

include '../templates/header.php';
include '../config/database.php';
require_once __DIR__ . '/../config/formatters.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$resultados_por_pagina = 15;
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

$filtro_q    = trim((string)($_GET['q'] ?? ''));
$filtro_cat  = trim((string)($_GET['categoria'] ?? ''));

$where_conds  = [];
$where_params = [];
$where_types  = '';
if ($filtro_cat !== '') {
    $where_conds[]  = "p.categoria = ?";
    $where_params[] = $filtro_cat;
    $where_types   .= 's';
}
if ($filtro_q !== '') {
    $like = '%' . $filtro_q . '%';
    $where_conds[]  = "(p.nome LIKE ? OR p.referencia LIKE ?)";
    $where_params[] = $like;
    $where_params[] = $like;
    $where_types   .= 'ss';
}
$where_sql = $where_conds ? ' WHERE ' . implode(' AND ', $where_conds) : '';

$stmt_cnt = $conn->prepare("SELECT COUNT(p.id) AS total FROM produtos p{$where_sql}");
if ($where_params) {
    $stmt_cnt->bind_param($where_types, ...$where_params);
}
$stmt_cnt->execute();
$total_resultados = (int)$stmt_cnt->get_result()->fetch_assoc()['total'];
$stmt_cnt->close();

$total_paginas = max(1, (int)ceil($total_resultados / $resultados_por_pagina));
$pagina_atual  = min($pagina_atual, $total_paginas);
$offset        = ($pagina_atual - 1) * $resultados_por_pagina;

$cats_filtro = ['' => 'Todas as categorias'];
$res_cats_f = $conn->query("SELECT DISTINCT categoria FROM produtos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria ASC");
if ($res_cats_f) {
    while ($rc = $res_cats_f->fetch_assoc()) {
        $cats_filtro[$rc['categoria']] = $rc['categoria'];
    }
}

$sort_map = [
    'id'         => 'p.id',
    'nome'       => 'p.nome',
    'referencia' => 'p.referencia',
    'categoria'  => 'p.categoria',
    'preco'      => 'CASE WHEN COALESCE(p.preco_promocional, 0) > 0 THEN p.preco_promocional ELSE p.preco END',
    'stock'      => 'COALESCE(stock_total, 0)',
    'estado'     => 'p.ativo',
];
$sort_by  = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map) ? $_GET['sort'] : 'id';
$sort_dir = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'asc') ? 'asc' : (isset($_GET['dir']) ? 'desc' : 'desc');
$ordem    = ['col' => $sort_map[$sort_by], 'dir' => strtoupper($sort_dir)];



?>


<main class="dashboard-container animate-entry">


    <?php echo ui_page_header("Gerir Produtos da Loja", ['layout' => 'admin', 'back_url' => '/admin', 'back_title' => 'Voltar ao Painel']); ?>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                mostrarPopup(<?php echo json_encode($_SESSION['flash_message']['texto'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($_SESSION['flash_message']['tipo'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
            });
        </script>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <!-- ── Toolbar ── -->
    <?php echo ui_table_toolbar([
        'form_id'        => 'form-ordenar',
        'primary_action' => ui_button('Adicionar Produto', ['href' => 'adicionar.php?return_to=admin_produtos', 'class' => 'button btn-with-plus btn-with-plus-text']),
        'filter'         => ['name' => 'categoria', 'options' => $cats_filtro, 'value' => $filtro_cat, 'title' => 'Filtrar por categoria'],
        'search'         => ['name' => 'q', 'value' => $filtro_q],
    ]); ?>

    <!-- ── Table ── -->
    <?php
    $sql = "SELECT p.*, (SELECT SUM(pv.quantidade) FROM produto_variacoes pv WHERE pv.produto_id = p.id) as stock_total FROM produtos p{$where_sql} ORDER BY {$ordem['col']} {$ordem['dir']} LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $exec_params = array_merge($where_params, [$resultados_por_pagina, $offset]);
    $exec_types  = $where_types . 'ii';
    $stmt->bind_param($exec_types, ...$exec_params);
    $stmt->execute();
    $result = $stmt->get_result();

        $prodRows = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $id = (int)$row['id'];
                $stock_total = (int)($row['stock_total'] ?? 0);
                $ativo = (int)$row['ativo'];
                $mostrar_aviso_stock = ($ativo === 1 && $stock_total <= 0);
                $motivo_aviso_stock = 'Oculto na loja: sem stock.';
                $aviso_stock_html = $mostrar_aviso_stock
                    ? "<span class='stock-warning-wrap' tabindex='0' data-tooltip='{$motivo_aviso_stock}' aria-label='{$motivo_aviso_stock}'><span class='stock-zero-warning' aria-hidden='true'>!</span></span>"
                    : '';
                $preco_fmt = number_format($row['preco'], 2, '.', '');
                $promo_fmt = ($row['preco_promocional'] > 0) ? number_format($row['preco_promocional'], 2, '.', '') : '';

                $fotoCell = "<div class='prod-foto-wrap'><img src='/public/images/" . ui_e($row['foto_principal']) . "' alt='" . ui_e($row['nome']) . "'></div>";

                $acoesCell = '<div class="acoes-tabela" style="display:flex; gap:8px;">'
                    . ui_icon_button('edit', ['href' => 'editar.php?id=' . $id . '&return_to=admin_produtos', 'title' => 'Editar produto'])
                    . ui_icon_button('duplicate', ['class' => 'btn-duplicar', 'title' => 'Duplicar produto', 'attrs' => ['data-id' => $id, 'data-csrf' => $_SESSION['csrf_token']]])
                    . ui_icon_button('delete', ['class' => 'btn-del-single', 'title' => 'Apagar produto', 'attrs' => ['data-id' => $id, 'data-nome' => $row['nome']]])
                    . '</div>';

                $temPromo = !empty($row['preco_promocional']) && $row['preco_promocional'] > 0;
                $precoHtml = $temPromo
                    ? "<span class='promo-tabela'>" . format_money($row['preco_promocional']) . "</span><br><small style='text-decoration:line-through;'>" . format_money($row['preco']) . '</small>'
                    : "<span class='preco-tabela'>" . format_money($row['preco']) . '</span>';

                $produtoHtml = "<span class='nome-produto-tabela'>" . ui_e($row['nome']) . '</span>';
                $refTexto    = trim((string)($row['referencia'] ?? ''));
                $refHtml     = "<span class='ref-produto-tabela'>" . ($refTexto !== '' ? ui_e($refTexto) : '-') . '</span>';

                $prodRows[] = [
                    'attrs' => [
                        'data-id'       => $id,
                        'data-edit-url' => 'editar.php?id=' . $id . '&return_to=admin_produtos',
                        'data-nome'     => $row['nome'],
                        'data-ref'      => $row['referencia'],
                        'data-stock'    => $stock_total,
                        'data-ativo'    => $ativo,
                        'data-preco'    => $preco_fmt,
                        'data-promo'    => $promo_fmt,
                    ],
                    'cells' => [
                        $fotoCell,
                        $produtoHtml,
                        $refHtml,
                        ui_e($row['categoria']),
                        ['class' => 'preco-cell', 'html' => $precoHtml],
                        ['class' => 'stock-cell', 'html' => "<span class='stock-value'>{$stock_total}</span>{$aviso_stock_html}"],
                        ['style' => 'text-align:center;', 'html' => ui_badge($ativo === 1 ? 'Visível' : 'Oculto', $ativo === 1 ? 'success' : 'neutral', [
                            'tag'         => 'button',
                            'interactive' => true,
                            'class'       => 'btn-toggle-status ' . ($ativo === 1 ? 'visivel' : 'oculto'),
                            'title'       => 'Clique para alternar visibilidade',
                            'attrs'       => ['data-id' => $id],
                        ])],
                        $acoesCell,
                    ],
                ];
            }
            $stmt->close();
        }
        echo ui_table([
            'Foto',
            ['label' => 'Produto', 'sort' => 'nome'],
            ['label' => 'Referência', 'sort' => 'referencia'],
            ['label' => 'Categoria', 'sort' => 'categoria'],
            ['label' => 'Preço', 'sort' => 'preco'],
            ['label' => 'Stock', 'sort' => ['key' => 'stock', 'default_dir' => 'desc']],
            ['label' => 'Estado', 'sort' => ['key' => 'estado', 'default_dir' => 'desc']],
            'Ações',
        ], $prodRows, [
            'wrap_id'       => 'table-wrapper',
            'wrap_style'    => 'max-width: 1200px; width: 100%; margin-left:auto; margin-right:auto;',
            'table_class'   => 'admin-table tabela-produtos',
            'empty_message' => ($filtro_q !== '' || $filtro_cat !== '') ? 'Nenhum produto encontrado com os filtros selecionados.' : 'Ainda não há produtos registados.',
            'sort'          => ['by' => $sort_by, 'dir' => $sort_dir, 'page_param' => 'pagina', 'unset_params' => ['ordenar']],
        ]);
        ?>

    <?php
    $pag_sort = isset($_GET['sort']) ? $sort_by : null;
    $pag_dir  = isset($_GET['sort']) ? $sort_dir : null;
    echo ui_pagination((int)$pagina_atual, (int)$total_paginas, function ($p) use ($filtro_q, $filtro_cat, $pag_sort, $pag_dir) {
        $params = [];
        if ($pag_sort) {
            $params['sort'] = $pag_sort;
            $params['dir']  = $pag_dir;
        }
        if ($filtro_cat !== '') $params['categoria'] = $filtro_cat;
        if ($filtro_q !== '') $params['q'] = $filtro_q;
        $params['pagina'] = $p;
        return '?' . http_build_query($params);
    });
    ?>

</main>

<?php echo ui_bulk_bar('', '', ['action_label' => 'Apagar']); ?>

<!-- ── Context Menu ── -->
<?php 
renderContextMenu([
    [
        'href' => '#', 
        'id' => 'ctx-edit-quick', 
        'label' => 'Editar Rápido', 
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'
    ],
    [
        'href' => '#', 
        'id' => 'ctx-edit-full', 
        'label' => 'Editar Completo', 
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>'
    ],
    'separator',
    [
        'href' => '#', 
        'id' => 'ctx-delete', 
        'class' => 'danger', 
        'label' => 'Eliminar Produto', 
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>'
    ]
]); 
?>

<!-- ── Quick Edit Modal ── -->
<?php renderQuickEditModal('qe-modal', 'Editar Produto'); ?>

<!-- Hidden form helpers for Quick Edit -->
<template id="qe-form-template">
    <input type="hidden" id="qe-id" name="id">
    <div class="qe-body">
        <div class="qe-f">
            <label>Nome do Produto</label>
            <?php echo ui_input('nome', ['id' => 'qe-nome', 'class' => 'qe-in', 'required' => true]); ?>
        </div>
        <div class="qe-row">
            <div class="qe-f">
                <label>Referência</label>
                <?php echo ui_input('referencia', ['id' => 'qe-ref', 'class' => 'qe-in', 'required' => true]); ?>
            </div>
            <div class="qe-f">
                <label>Stock Total</label>
                <?php echo ui_input('stock', ['type' => 'number', 'id' => 'qe-stock', 'class' => 'qe-in', 'required' => true, 'min' => '0']); ?>
            </div>
        </div>
        <div class="qe-row">
            <div class="qe-f">
                <label>Preço (€)</label>
                <?php echo ui_input('preco', ['type' => 'number', 'id' => 'qe-preco', 'class' => 'qe-in', 'required' => true, 'step' => '0.01', 'min' => '0']); ?>
            </div>
            <div class="qe-f">
                <label>Promoção (€)</label>
                <?php echo ui_input('preco_promocional', ['type' => 'number', 'id' => 'qe-promo', 'class' => 'qe-in', 'step' => '0.01', 'min' => '0', 'placeholder' => 'Opcional']); ?>
            </div>
        </div>
    </div>
</template>

<!-- Hidden form for bulk/single delete -->
<form id="form-delete" action="apagar_massa.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <div id="form-ids"></div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const CSRF       = '<?php echo $_SESSION['csrf_token']; ?>';
    const formDelete = document.getElementById('form-delete');
    const formIds    = document.getElementById('form-ids');

    function renderStockCell(tr) {
        const cell = tr.querySelector('.stock-cell');
        if (!cell) return;

        const stock = parseInt(tr.dataset.stock, 10) || 0;
        const isVisible = tr.dataset.ativo === '1';
        const reason = "Oculto na loja: sem stock.";
        const warning = isVisible && stock <= 0
            ? `<span class="stock-warning-wrap" tabindex="0" data-tooltip="${reason}" aria-label="${reason}"><span class="stock-zero-warning" aria-hidden="true">!</span></span>`
            : "";

        cell.innerHTML = `<span class="stock-value">${stock}</span>${warning}`;
    }

    function escapeHtmlAdmin(str) {
        return String(str ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    }

    function submitDelete(ids) {
        formIds.innerHTML = '';
        ids.forEach(id => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'ids_produtos[]'; inp.value = id;
            formIds.appendChild(inp);
        });
        formDelete.submit();
    }

    const bulkSel = window.initAdminBulkSelection({
        confirmTitle: 'Apagar Produtos',
        confirmMessage: ids => `Apagar permanentemente ${ids.length} produto(s)?`,
        onAction: ids => mostrarModalConfirmacao('Apagar Produtos', `Apagar permanentemente ${ids.length} produto(s)?`, () => submitDelete(ids)),
    });

    // Delegação para botões de ação na tabela
    document.querySelector('.tabela-produtos tbody')?.addEventListener('click', function(e) {
        const tr = e.target.closest('tr');
        if (!tr) return;

        // Apagar
        if (e.target.closest('.btn-del-single')) {
            mostrarModalConfirmacao('Apagar Produto', `Apagar permanentemente ${escapeHtmlAdmin(tr.dataset.nome)}?`, () => submitDelete([tr.dataset.id]));
        }

        // Duplicar
        if (e.target.closest('.btn-duplicar')) {
            mostrarModalConfirmacao('Duplicar Produto', 'Criar uma cópia deste produto?', () => {
                const f = document.createElement('form'); f.method = 'POST'; f.action = 'duplicar_produto.php';
                f.innerHTML = `<input type="hidden" name="id" value="${tr.dataset.id}"><input type="hidden" name="csrf_token" value="${CSRF}">`;
                document.body.appendChild(f); f.submit();
            });
        }

        // Toggle Status
        const btnToggle = e.target.closest('.btn-toggle-status');
        if (btnToggle && !(bulkSel && bulkSel.isSelMode())) {
            btnToggle.classList.add('loading');
            const fd = new FormData(); fd.append('id', tr.dataset.id); fd.append('csrf_token', CSRF);
            fetch('ajax_toggle_visibilidade.php', { method: 'POST', body: fd })
                .then(r => {
                    if (!r.ok) throw new Error('Erro HTTP ' + r.status);
                    return r.json();
                }).then(data => {
                    btnToggle.classList.remove('loading');
                    if (data.sucesso) {
                        btnToggle.textContent = data.label;
                        const isVis = data.novo_estado == 1;
                        btnToggle.classList.toggle('visivel', isVis);
                        btnToggle.classList.toggle('oculto', !isVis);
                        btnToggle.classList.toggle('ui-badge--success', isVis);
                        btnToggle.classList.toggle('ui-badge--neutral', !isVis);
                        tr.dataset.ativo = isVis ? '1' : '0';
                        renderStockCell(tr);
                        mostrarPopup('Visibilidade atualizada.', 'sucesso');
                    } else {
                        mostrarPopup(data.mensagem || 'Não foi possível atualizar a visibilidade.', 'erro');
                    }
                }).catch(() => {
                    btnToggle.classList.remove('loading');
                    mostrarPopup('Erro de comunicação ao atualizar visibilidade.', 'erro');
                });
        }
    });

    // CONTEXT MENU EVENT
    document.addEventListener('admin:contextmenu', function(e) {
        const { row, menu } = e.detail;
        menu.querySelector('#ctx-edit-full').href = 'editar.php?id=' + row.dataset.id + '&return_to=admin_produtos';
        
        // Editar Rápido
        menu.querySelector('#ctx-edit-quick').onclick = () => {
            const modal = document.getElementById('qe-modal');
            const template = document.getElementById('qe-form-template');
            const formBody = modal.querySelector('.qe-body');
            
            // Popular modal
            formBody.innerHTML = template.innerHTML;
            modal.querySelector('#qe-id').value = row.dataset.id;
            modal.querySelector('#qe-nome').value = row.dataset.nome;
            modal.querySelector('#qe-ref').value = row.dataset.ref;
            modal.querySelector('#qe-stock').value = row.dataset.stock;
            modal.querySelector('#qe-preco').value = row.dataset.preco;
            modal.querySelector('#qe-promo').value = row.dataset.promo;
            
            modal.style.display = 'flex';
        };

        // Apagar via menu
        menu.querySelector('#ctx-delete').onclick = () => {
            mostrarModalConfirmacao('Apagar Produto', `Apagar permanentemente ${escapeHtmlAdmin(row.dataset.nome)}?`, () => submitDelete([row.dataset.id]));
        };
    });

    // QUICK EDIT SUBMIT
    document.querySelector('#qe-modal form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const btnSave = form.querySelector('.qe-btn-save');
        btnSave.disabled = true; btnSave.textContent = 'A guardar...';

        const fd = new FormData(form);
        fd.set('id', form.querySelector('#qe-id').value);
        fd.append('csrf_token', CSRF);

        fetch('ajax_quick_edit_product.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(data => {
                btnSave.disabled = false; btnSave.textContent = 'Guardar Alterações';
                if (data.sucesso) {
                    const tr = document.querySelector(`tr[data-id="${data.id}"]`);
                    if (tr) {
                        tr.dataset.nome = data.nome; tr.dataset.ref = data.referencia; tr.dataset.stock = data.stock;
                        tr.dataset.preco = data.preco.toFixed(2); tr.dataset.promo = data.preco_promocional ? data.preco_promocional.toFixed(2) : '';
                        tr.querySelector('.nome-produto-tabela').textContent = data.nome;
                        const refEl = tr.querySelector('.ref-produto-tabela');
                        if (refEl) refEl.textContent = (data.referencia && String(data.referencia).trim() !== '') ? data.referencia : '-';
                        renderStockCell(tr);
                        const precoCell = tr.querySelector('.preco-cell');
                        if (precoCell) {
                            const precoStr = '€' + data.preco.toFixed(2).replace('.', ',');
                            if (data.preco_promocional && data.preco_promocional > 0) {
                                const promoStr = '€' + data.preco_promocional.toFixed(2).replace('.', ',');
                                precoCell.innerHTML = `<span class="promo-tabela">${promoStr}</span><br><small style="text-decoration:line-through;">${precoStr}</small>`;
                            } else {
                                precoCell.innerHTML = `<span class="preco-tabela">${precoStr}</span>`;
                            }
                        }
                    }
                    document.getElementById('qe-modal').style.display = 'none';
                    mostrarPopup('Produto atualizado!', 'sucesso');
                } else {
                    mostrarPopup('Erro: ' + (data.mensagem || 'Ocorreu um erro desconhecido.'), 'erro');
                }
            }).catch(() => {
                mostrarPopup('Erro de comunicação ao guardar o produto.', 'erro');
            }).finally(() => {
                btnSave.disabled = false; btnSave.textContent = 'Guardar Alterações';
            });
    });
});
</script>

<?php include '../templates/footer.php'; ?>
