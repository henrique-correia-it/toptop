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
$offset = ($pagina_atual - 1) * $resultados_por_pagina;

$so_visiveis = !empty($_GET['visiveis']) && $_GET['visiveis'] === '1';
$where_visiveis = $so_visiveis ? " WHERE ativo = 1" : "";
$total_resultados = $conn->query("SELECT COUNT(id) as total FROM produtos{$where_visiveis}")->fetch_assoc()['total'];
$total_paginas = ceil($total_resultados / $resultados_por_pagina);

$ordenar_opcoes = [
    'id_desc'       => ['col' => 'p.id',         'dir' => 'DESC'],
    'nome_asc'      => ['col' => 'p.nome',        'dir' => 'ASC'],
    'nome_desc'     => ['col' => 'p.nome',        'dir' => 'DESC'],
    'preco_asc'     => ['col' => 'p.preco',       'dir' => 'ASC'],
    'preco_desc'    => ['col' => 'p.preco',       'dir' => 'DESC'],
    'stock_desc'    => ['col' => 'stock_total',   'dir' => 'DESC'],
    'stock_asc'     => ['col' => 'stock_total',   'dir' => 'ASC'],
    'categoria_asc' => ['col' => 'p.categoria',   'dir' => 'ASC'],
];
$ordenar_key = $_GET['ordenar'] ?? 'id_desc';
if (!array_key_exists($ordenar_key, $ordenar_opcoes)) $ordenar_key = 'id_desc';
$ordem = $ordenar_opcoes[$ordenar_key];


function gerarPaginacao($pagina_atual, $total_paginas, $ordenar_key = 'id_desc', $so_visiveis = false) {
    if ($total_paginas <= 1) return '';
    $params = [];
    if ($ordenar_key !== 'id_desc') $params[] = 'ordenar=' . urlencode($ordenar_key);
    if ($so_visiveis) $params[] = 'visiveis=1';
    $extra = $params ? '&' . implode('&', $params) : '';
    $html = '<nav class="pagination-container"><ul class="pagination-list">';

    // Anterior
    $html .= '<li class="pagination-item prev ' . ($pagina_atual <= 1 ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="?pagina=' . ($pagina_atual - 1) . $extra . '" title="Anterior">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
              </a></li>';

    for ($i = 1; $i <= $total_paginas; $i++) {
        $html .= '<li class="pagination-item ' . ($i == $pagina_atual ? 'active' : '') . '">';
        $html .= '<a class="page-link" href="?pagina=' . $i . $extra . '">' . $i . '</a></li>';
    }

    // Seguinte
    $html .= '<li class="pagination-item next ' . ($pagina_atual >= $total_paginas ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="?pagina=' . ($pagina_atual + 1) . $extra . '" title="Seguinte">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
              </a></li>';

    $html .= '</ul></nav>';
    return $html;
}
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
            <h2>Gerir Produtos da Loja</h2>
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

    <!-- ── Toolbar ── -->
    <div class="prod-toolbar">
        <div class="prod-toolbar-left">
            <a href="adicionar.php?return_to=admin_produtos" class="button btn-with-plus btn-with-plus-text">Adicionar Produto</a>
        </div>
        <div class="prod-toolbar-right">
            <form method="GET" id="form-ordenar" style="display:flex; align-items:center; gap:10px;">
                <label class="toggle-visiveis" title="Mostrar apenas produtos visíveis">
                    <input type="checkbox" name="visiveis" value="1"
                           <?= $so_visiveis ? 'checked' : '' ?>
                           onchange="this.form.submit()">
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                    <span>Só visíveis</span>
                </label>
                <div class="select-wrapper sort-select-wrapper">
                    <select name="ordenar" class="sort-select" onchange="this.form.submit()" title="Ordenar por">
                    <option value="id_desc"       <?= $ordenar_key === 'id_desc'       ? 'selected' : '' ?>>Mais recente</option>
                    <option value="nome_asc"      <?= $ordenar_key === 'nome_asc'      ? 'selected' : '' ?>>Nome (A → Z)</option>
                    <option value="nome_desc"     <?= $ordenar_key === 'nome_desc'     ? 'selected' : '' ?>>Nome (Z → A)</option>
                    <option value="preco_asc"     <?= $ordenar_key === 'preco_asc'     ? 'selected' : '' ?>>Preço (↑)</option>
                    <option value="preco_desc"    <?= $ordenar_key === 'preco_desc'    ? 'selected' : '' ?>>Preço (↓)</option>
                    <option value="stock_desc"    <?= $ordenar_key === 'stock_desc'    ? 'selected' : '' ?>>Stock (↓)</option>
                    <option value="stock_asc"     <?= $ordenar_key === 'stock_asc'     ? 'selected' : '' ?>>Stock (↑)</option>
                    <option value="categoria_asc" <?= $ordenar_key === 'categoria_asc' ? 'selected' : '' ?>>Categoria (A → Z)</option>
                    </select>
                </div>
            </form>
            <button class="button btn-sel-mode" id="btn-sel-mode" type="button">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="5" width="4" height="4" rx="1"/><rect x="3" y="13" width="4" height="4" rx="1"/><line x1="11" y1="7" x2="21" y2="7"/><line x1="11" y1="15" x2="21" y2="15"/></svg>
                Selecionar
            </button>
        </div>
    </div>

    <!-- ── Selection mode info bar ── -->
    <div class="sel-mode-bar" id="sel-mode-bar">
        <span><span class="sel-count" id="sel-count">0</span> selecionado(s)</span>
        <button class="btn-sel-all" id="btn-sel-all" type="button">Selecionar todos</button>
        <div class="sel-spacer"></div>
    </div>

    <!-- ── Table ── -->
    <div class="table-wrapper" style="max-width: 1200px; width: 100%; margin-left:auto; margin-right:auto;" id="table-wrapper">
        <?php
        $where_sql = $so_visiveis ? " WHERE p.ativo = 1" : "";
        $sql = "SELECT p.*, (SELECT SUM(pv.quantidade) FROM produto_variacoes pv WHERE pv.produto_id = p.id) as stock_total FROM produtos p{$where_sql} ORDER BY {$ordem['col']} {$ordem['dir']} LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $resultados_por_pagina, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            echo "<table class='admin-table tabela-produtos'>";
            echo "<thead><tr>
                    <th>Foto</th>
                    <th>Nome</th>
                    <th>Referência</th>
                    <th>Stock</th>
                    <th>Estado</th>
                    <th>Preço</th>
                    <th>Promo</th>
                    <th>Categoria</th>
                    <th>Ações</th>
                </tr></thead>";

            echo "<tbody>";
            while ($row = $result->fetch_assoc()) {
                $id = $row['id'];
                $stock_total = (int) ($row['stock_total'] ?? 0);
                $ativo = (int) $row['ativo'];
                $mostrar_aviso_stock = ($ativo === 1 && $stock_total <= 0);
                $motivo_aviso_stock = "Oculto na loja: sem stock.";
                $aviso_stock_html = $mostrar_aviso_stock
                    ? "<span class='stock-warning-wrap' tabindex='0' data-tooltip='{$motivo_aviso_stock}' aria-label='{$motivo_aviso_stock}'><span class='stock-zero-warning' aria-hidden='true'>!</span></span>"
                    : "";
                $preco_fmt = number_format($row['preco'], 2, '.', '');
                $promo_fmt = ($row['preco_promocional'] > 0) ? number_format($row['preco_promocional'], 2, '.', '') : '';
                
                echo "<tr data-id='{$id}' 
                          data-edit-url='editar.php?id={$id}&return_to=admin_produtos'
                          data-nome='" . htmlspecialchars($row['nome'], ENT_QUOTES) . "'
                          data-ref='" . htmlspecialchars($row['referencia'], ENT_QUOTES) . "'
                          data-stock='{$stock_total}'
                          data-ativo='{$ativo}'
                          data-preco='{$preco_fmt}'
                          data-promo='{$promo_fmt}'>";
                echo "<td>
                        <div class='prod-foto-wrap'>
                            <img src='/public/images/" . htmlspecialchars($row['foto_principal']) . "' alt='" . htmlspecialchars($row['nome']) . "'>
                            <span class='prod-check-badge'>
                                <svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><polyline points='20 6 9 17 4 12'/></svg>
                            </span>
                        </div>
                      </td>";
                echo "<td><div class='nome-produto-tabela'>" . htmlspecialchars($row['nome']) . "</div></td>";
                echo "<td>" . htmlspecialchars($row['referencia']) . "</td>";
                echo "<td class='stock-cell'><span class='stock-value'>{$stock_total}</span>{$aviso_stock_html}</td>";
                echo "<td style='text-align:center;'>
                        <span class='badge " . ($ativo === 1 ? 'visivel' : 'oculto') . " interativo btn-toggle-status'
                               data-id='{$id}'>" . ($ativo === 1 ? 'Visível' : 'Oculto') . "</span>
                      </td>";
                echo "<td><span class='preco-tabela'>" . format_money($row['preco']) . "</span></td>";
                echo "<td>";
                if (!empty($row['preco_promocional']) && $row['preco_promocional'] > 0) {
                    echo "<span class='promo-tabela'>" . format_money($row['preco_promocional']) . "</span>";
                } else { echo "—"; }
                echo "</td>";
                echo "<td><span style='font-size: 0.85rem; color: #64748b;'>" . htmlspecialchars($row['categoria']) . "</span></td>";
                echo '<td>
                        <div class="acoes-tabela" style="display:flex; gap:8px;">
                            <a href="editar.php?id=' . $id . '&return_to=admin_produtos" class="btn-edit-single" title="Editar produto"></a>
                            <button type="button" class="btn-duplicate-single btn-duplicar"
                                    data-id="' . $id . '"
                                    data-csrf="' . $_SESSION['csrf_token'] . '"
                                    title="Duplicar produto"></button>
                            <button type="button" class="btn-del-single"
                                    data-id="' . $id . '"
                                    data-nome="' . htmlspecialchars($row['nome'], ENT_QUOTES) . '"
                                    title="Apagar produto"></button>
                        </div>
                      </td>';
                echo "</tr>";
            }
            echo "</tbody></table>";
            $stmt->close();
        } else {
            echo "<p>Ainda não há produtos registados.</p>";
        }
        ?>
    </div>

    <?php echo gerarPaginacao($pagina_atual, $total_paginas, $ordenar_key, $so_visiveis); ?>

</main>

<!-- ── Floating bulk action bar ── -->
<div class="bulk-bar" id="bulk-bar">
    <span class="bulk-bar-count"><span id="bulk-count">0</span> selecionado(s)</span>
    <div class="bulk-bar-spacer"></div>
    <button class="bulk-bar-cancel" id="bulk-cancel" type="button">Cancelar</button>
    <button class="btn-admin-danger" id="bulk-del" type="button" style="padding: 10px 20px;">
        Apagar
    </button>
</div>

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
            <input type="text" id="qe-nome" name="nome" class="qe-in" required>
        </div>
        <div class="qe-row">
            <div class="qe-f">
                <label>Referência</label>
                <input type="text" id="qe-ref" name="referencia" class="qe-in" required>
            </div>
            <div class="qe-f">
                <label>Stock Total</label>
                <input type="number" id="qe-stock" name="stock" class="qe-in" required min="0">
            </div>
        </div>
        <div class="qe-row">
            <div class="qe-f">
                <label>Preço (€)</label>
                <input type="number" id="qe-preco" name="preco" class="qe-in" required step="0.01" min="0">
            </div>
            <div class="qe-f">
                <label>Promoção (€)</label>
                <input type="number" id="qe-promo" name="preco_promocional" class="qe-in" step="0.01" min="0" placeholder="Opcional">
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
    const CSRF = '<?php echo $_SESSION['csrf_token']; ?>';
    const wrapper      = document.getElementById('table-wrapper');
    const btnSelMode   = document.getElementById('btn-sel-mode');
    const selModeBar   = document.getElementById('sel-mode-bar');
    const selCountEl   = document.getElementById('sel-count');
    const btnSelAll    = document.getElementById('btn-sel-all');
    const bulkBar      = document.getElementById('bulk-bar');
    const bulkCountEl  = document.getElementById('bulk-count');
    const bulkCancel   = document.getElementById('bulk-cancel');
    const bulkDel      = document.getElementById('bulk-del');
    const formDelete   = document.getElementById('form-delete');
    const formIds      = document.getElementById('form-ids');
    
    let selMode = false;
    let selected = new Set();

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

    function updateUI() {
        const n = selected.size;
        selCountEl.textContent = n;
        bulkCountEl.textContent = n;
        const total = document.querySelectorAll('.tabela-produtos tbody tr').length;
        btnSelAll.textContent = selected.size === total ? 'Desselecionar todos' : 'Selecionar todos';
        bulkBar.classList.toggle('visible', n > 0);
    }

    btnSelMode.addEventListener('click', () => {
        selMode = !selMode;
        wrapper.classList.toggle('sel-mode', selMode);
        btnSelMode.classList.toggle('active', selMode);
        selModeBar.classList.toggle('visible', selMode);
        if (!selMode) {
            selected.clear();
            document.querySelectorAll('.tabela-produtos tbody tr').forEach(r => r.classList.remove('selecionado'));
        }
        updateUI();
    });

    bulkCancel.addEventListener('click', () => btnSelMode.click());

    // Seleção de linhas
    document.querySelector('.tabela-produtos tbody')?.addEventListener('click', function (e) {
        if (!selMode || e.target.closest('.acoes-tabela')) return;
        const tr = e.target.closest('tr');
        if (!tr) return;
        const id = tr.dataset.id;
        if (selected.has(id)) { selected.delete(id); tr.classList.remove('selecionado'); }
        else { selected.add(id); tr.classList.add('selecionado'); }
        updateUI();
    });

    btnSelAll.addEventListener('click', () => {
        const rows = document.querySelectorAll('.tabela-produtos tbody tr');
        const allSel = [...rows].every(r => selected.has(r.dataset.id));
        rows.forEach(r => {
            if (allSel) { selected.delete(r.dataset.id); r.classList.remove('selecionado'); }
            else { selected.add(r.dataset.id); r.classList.add('selecionado'); }
        });
        updateUI();
    });

    // Operações AJAX e Confirmações
    function submitDelete(ids) {
        formIds.innerHTML = '';
        ids.forEach(id => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'ids_produtos[]'; inp.value = id;
            formIds.appendChild(inp);
        });
        formDelete.submit();
    }

    bulkDel.addEventListener('click', () => {
        const ids = [...selected];
        if (!ids.length) return;
        mostrarModalConfirmacao('Apagar Produtos', `Apagar permanentemente ${ids.length} produto(s)?`, () => submitDelete(ids));
    });

    // Delegação para botões de ação na tabela
    document.querySelector('.tabela-produtos tbody')?.addEventListener('click', function(e) {
        const tr = e.target.closest('tr');
        if (!tr) return;

        // Apagar
        if (e.target.closest('.btn-del-single')) {
            mostrarModalConfirmacao('Apagar Produto', `Apagar permanentemente ${tr.dataset.nome}?`, () => submitDelete([tr.dataset.id]));
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
        if (btnToggle && !selMode) {
            btnToggle.classList.add('loading');
            const fd = new FormData(); fd.append('id', tr.dataset.id); fd.append('csrf_token', CSRF);
            fetch('ajax_toggle_visibilidade.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(data => {
                    btnToggle.classList.remove('loading');
                    if (data.sucesso) {
                        btnToggle.textContent = data.label;
                        btnToggle.classList.toggle('visivel', data.novo_estado == 1);
                        btnToggle.classList.toggle('oculto', data.novo_estado != 1);
                        tr.dataset.ativo = data.novo_estado == 1 ? '1' : '0';
                        renderStockCell(tr);
                        mostrarPopup('Visibilidade atualizada.', 'sucesso');
                    }
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
            mostrarModalConfirmacao('Apagar Produto', `Apagar permanentemente ${row.dataset.nome}?`, () => submitDelete([row.dataset.id]));
        };
    });

    // QUICK EDIT SUBMIT
    document.querySelector('#qe-modal form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const btnSave = form.querySelector('.qe-btn-save');
        btnSave.disabled = true; btnSave.textContent = 'A guardar...';

        const fd = new FormData(form);
        fd.append('id', form.querySelector('#qe-id').value);
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
                        tr.querySelector('td:nth-child(3)').textContent = data.referencia;
                        renderStockCell(tr);
                        tr.querySelector('.preco-tabela').textContent = '€' + data.preco.toFixed(2).replace('.', ',');
                    }
                    document.getElementById('qe-modal').style.display = 'none';
                    mostrarPopup('Produto atualizado!', 'sucesso');
                } else {
                    mostrarPopup('Erro: ' + (data.mensagem || 'Ocorreu um erro desconhecido.'), 'erro');
                }
            });
    });
});
</script>

<?php include '../templates/footer.php'; ?>
