<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
include '../config/database.php';

// Segurança: Apenas gestores da loja podem aceder
require_admin_manager('/admin/admin.php');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$filtro_q = trim((string)($_GET['q'] ?? ''));

$sort_map = [
    'titulo'   => 'g.titulo',
    'produtos' => 'total_produtos',
];
$sort_by   = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map) ? $_GET['sort'] : '';
$sort_dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'desc') ? 'desc' : 'asc';
$order_sql = $sort_by !== '' ? ($sort_map[$sort_by] . ' ' . strtoupper($sort_dir) . ', g.titulo ASC') : 'g.titulo ASC';

$where_sql = '';
$params    = [];
$types     = '';
if ($filtro_q !== '') {
    $where_sql = 'WHERE g.titulo LIKE ?';
    $params[]  = '%' . $filtro_q . '%';
    $types     = 's';
}

$sql = "SELECT g.*, COALESCE(p.total_produtos, 0) AS total_produtos
        FROM guias_tamanho g
        LEFT JOIN (
            SELECT guia_tamanho_id, COUNT(*) AS total_produtos
            FROM produtos
            WHERE guia_tamanho_id IS NOT NULL
            GROUP BY guia_tamanho_id
        ) p ON p.guia_tamanho_id = g.id
        {$where_sql}
        ORDER BY {$order_sql}";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$guias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function resumir_estrutura_guia(string $html): string
{
    if (trim($html) === '') {
        return '-';
    }
    $cols = 0;
    if (preg_match('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $m)) {
        $cols = preg_match_all('/<(?:th|td)\b/i', $m[1]);
    }
    $totalRows = preg_match_all('/<tr\b/i', $html);
    $bodyRows  = max(0, $totalRows - 1);
    if ($cols <= 0 && $totalRows <= 0) {
        return '-';
    }
    return $bodyRows . ' ' . ($bodyRows === 1 ? 'linha' : 'linhas') . ' · ' . $cols . ' ' . ($cols === 1 ? 'coluna' : 'colunas');
}

include '../templates/header.php';
?>

<main class="dashboard-container animate-entry">

    <?php echo ui_page_header("Gerir Guias de Tamanho", ['layout' => 'admin', 'back_url' => '/admin', 'back_title' => 'Voltar ao Painel']); ?>

    <?php echo ui_table_toolbar([
        'primary_action' => ui_button('Novo Guia', ['id' => 'btn-novo-guia', 'class' => 'button btn-with-plus btn-with-plus-text']),
        'search'         => ['name' => 'q', 'value' => $filtro_q],
    ]); ?>

    <?php
    $guiaRows = [];
    foreach ($guias as $guia) {
        $gid       = (int)$guia['id'];
        $totalProd = (int)$guia['total_produtos'];
        $estrutura = resumir_estrutura_guia((string)$guia['conteudo']);

        $acoesCell = '<div class="acoes-tabela">'
            . ui_icon_button('view', ['class' => 'btn-preview-guia', 'title' => 'Ver Guia'])
            . ui_icon_button('edit', ['class' => 'btn-editar-guia', 'title' => 'Editar Guia'])
            . ui_icon_button('delete', ['class' => 'btn-apagar-guia', 'title' => 'Apagar Guia'])
            . '</div>';

        $guiaRows[] = [
            'attrs' => [
                'data-id'       => $gid,
                'data-titulo'   => (string)$guia['titulo'],
                'data-conteudo' => (string)$guia['conteudo'],
            ],
            'cells' => [
                '<strong style="color:var(--cor-slate-800);">' . ui_e($guia['titulo']) . '</strong>',
                '<span class="guia-estrutura-meta">' . ui_e($estrutura) . '</span>',
                ui_badge($totalProd . ' ' . ($totalProd === 1 ? 'produto' : 'produtos'), $totalProd > 0 ? 'info' : 'neutral'),
                $acoesCell,
            ],
        ];
    }

    echo ui_table([
        ['label' => 'Título', 'sort' => 'titulo'],
        'Estrutura',
        ['label' => 'Produtos', 'sort' => ['key' => 'produtos', 'default_dir' => 'desc']],
        'Ações',
    ], $guiaRows, [
        'wrap_id'       => 'table-wrapper',
        'wrap_style'    => 'max-width: 1200px; margin: 0 auto;',
        'table_class'   => 'admin-table tabela-guias',
        'empty_message' => $filtro_q !== '' ? 'Nenhum guia encontrado com essa pesquisa.' : 'Ainda não foram criados guias de tamanho.',
        'sort'          => ['by' => $sort_by, 'dir' => $sort_dir],
    ]);
    ?>
</main>

<?php echo ui_bulk_bar('', '', [
    'unit'         => 'guia(s)',
    'action_label' => 'Apagar Selecionados',
]); ?>

<!-- Modal: Criar / Editar Guia -->
<?php
$iconPlus  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
$iconMinus = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/></svg>';

$editorBody = '<form id="form-guia" class="guia-editor-form">'
    . '<input type="hidden" name="id" id="guia-id">'
    . '<input type="hidden" name="csrf_token" value="' . ui_e($_SESSION['csrf_token']) . '">'
    . '<div class="qe-f">'
    . '  <label for="guia-titulo-input">Título</label>'
    . ui_input('titulo', ['id' => 'guia-titulo-input', 'class' => 'qe-in', 'placeholder' => 'Ex: Vestidos, Calças, Bebé...', 'required' => true, 'attrs' => ['autocomplete' => 'off']])
    . '</div>'
    . '<div class="tabela-visual-editor">'
    . '  <div class="editor-toolbar">'
    . '    <span class="editor-hint">Clica nas células para editar</span>'
    . '    <div class="toolbar-section">'
    . '      <div class="control-group">'
    . '        <span class="control-label">Linhas</span>'
    . '        <div class="control-buttons">'
    . '          <button type="button" id="btn-remover-linha" class="guia-ctrl-btn remove" title="Remover Linha" aria-label="Remover Linha">' . $iconMinus . '</button>'
    . '          <button type="button" id="btn-adicionar-linha" class="guia-ctrl-btn add" title="Adicionar Linha" aria-label="Adicionar Linha">' . $iconPlus . '</button>'
    . '        </div>'
    . '      </div>'
    . '      <div class="control-group">'
    . '        <span class="control-label">Colunas</span>'
    . '        <div class="control-buttons">'
    . '          <button type="button" id="btn-remover-coluna" class="guia-ctrl-btn remove" title="Remover Coluna" aria-label="Remover Coluna">' . $iconMinus . '</button>'
    . '          <button type="button" id="btn-adicionar-coluna" class="guia-ctrl-btn add" title="Adicionar Coluna" aria-label="Adicionar Coluna">' . $iconPlus . '</button>'
    . '        </div>'
    . '      </div>'
    . '    </div>'
    . '  </div>'
    . '  <div id="gerador-visual-wrapper"></div>'
    . '</div>'
    . '<div style="display:none;">'
    . ui_textarea('conteudo', '', ['id' => 'guia-conteudo-input', 'rows' => 10])
    . '</div>'
    . '</form>';

echo ui_modal('modal-guia', 'Novo Guia de Tamanhos', $editorBody, [
    'class'  => 'guia-modal-wide',
    'footer' => ui_button('Cancelar', ['id' => 'btn-cancelar-guia', 'class' => 'qe-btn-cancel'])
        . ui_button('Guardar Guia', ['type' => 'submit', 'variant' => 'primary', 'form' => 'form-guia', 'id' => 'btn-guardar-guia']),
]);

echo ui_modal('modalGuiaTamanhos', 'Pré-visualização do Guia', '<div id="guia-conteudo"></div>', [
    'class'  => 'guia-modal-wide',
    'footer' => ui_button('Fechar', ['id' => 'btn-fechar-preview-guia', 'class' => 'qe-btn-cancel']),
]);
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken            = <?php echo json_encode($_SESSION['csrf_token']); ?>;
    const tableWrap            = document.getElementById('table-wrapper');
    const modal                = document.getElementById('modal-guia');
    const form                 = document.getElementById('form-guia');
    const modalTitulo          = document.getElementById('modal-guia-title');
    const guiaIdInput          = document.getElementById('guia-id');
    const guiaTituloInput      = document.getElementById('guia-titulo-input');
    const guiaConteudoInput    = document.getElementById('guia-conteudo-input');
    const btnNovoGuia          = document.getElementById('btn-novo-guia');
    const geradorVisualWrapper = document.getElementById('gerador-visual-wrapper');

    const modalPreview    = document.getElementById('modalGuiaTamanhos');
    const previewTitulo   = document.getElementById('modalGuiaTamanhos-title');
    const previewConteudo = document.getElementById('guia-conteudo');

    function esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    }

    // --- Editor Visual de Tabela (inputs limpos, sem contenteditable) ---
    const criarCelulaInput = (valor = '', isHeader = false) => {
        const el = document.createElement(isHeader ? 'th' : 'td');
        const inp = document.createElement('input');
        inp.type = 'text';
        inp.className = isHeader ? 'guia-cell-input is-header' : 'guia-cell-input';
        inp.value = String(valor ?? '').trim();
        inp.setAttribute('autocomplete', 'off');
        inp.placeholder = isHeader ? 'Título' : '-';
        inp.addEventListener('input', atualizarHtmlDoTextarea);
        inp.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const td = inp.closest('th, td');
                const tr = td?.closest('tr');
                const table = td?.closest('table');
                if (!td || !tr || !table) return;
                const colIdx = td.cellIndex;
                const nextTr = table.rows[tr.rowIndex + 1];
                const nextInput = nextTr?.cells[colIdx]?.querySelector('input');
                if (nextInput) {
                    nextInput.focus();
                    nextInput.select();
                } else {
                    inp.blur();
                }
            }
        });
        el.appendChild(inp);
        return el;
    };

    const atualizarHtmlDoTextarea = () => {
        const tabelaVisual = document.getElementById('tabela-visual-gerada');
        if (!tabelaVisual) {
            guiaConteudoInput.value = '';
            return;
        }
        const cleanTable = document.createElement('table');
        const thead = cleanTable.createTHead();
        const headerRow = thead.insertRow();
        const srcHeadRow = tabelaVisual.tHead?.rows[0] || tabelaVisual.rows[0];
        if (srcHeadRow) {
            Array.from(srcHeadRow.cells).forEach(cell => {
                const th = document.createElement('th');
                const inp = cell.querySelector('input');
                th.textContent = (inp ? inp.value : cell.textContent).trim();
                headerRow.appendChild(th);
            });
        }
        const tbody = cleanTable.createTBody();
        const srcBodyRows = tabelaVisual.tBodies[0] ? Array.from(tabelaVisual.tBodies[0].rows) : Array.from(tabelaVisual.rows).slice(1);
        srcBodyRows.forEach(srcRow => {
            const row = tbody.insertRow();
            Array.from(srcRow.cells).forEach(cell => {
                const td = row.insertCell();
                const inp = cell.querySelector('input');
                td.textContent = (inp ? inp.value : cell.textContent).trim();
            });
        });
        guiaConteudoInput.value = cleanTable.outerHTML;
    };

    const montarTabelaEditor = (headers, rowsData) => {
        const table = document.createElement('table');
        table.id = 'tabela-visual-gerada';

        const thead = table.createTHead();
        const headerRow = thead.insertRow();
        headers.forEach(text => headerRow.appendChild(criarCelulaInput(text, true)));

        const tbody = table.createTBody();
        rowsData.forEach(cols => {
            const row = tbody.insertRow();
            cols.forEach(val => row.appendChild(criarCelulaInput(val, false)));
        });

        geradorVisualWrapper.innerHTML = '';
        geradorVisualWrapper.appendChild(table);
        atualizarHtmlDoTextarea();
    };

    const gerarTabelaDefault = () => {
        montarTabelaEditor(
            ['Tamanho', 'Peito (cm)', 'Cintura (cm)'],
            [
                ['S', '-', '-'],
                ['M', '-', '-'],
                ['L', '-', '-'],
            ]
        );
    };

    const manipularTabela = (acao) => {
        const tabela = document.getElementById('tabela-visual-gerada');
        if (!tabela) return;

        if (acao === 'addLinha') {
            const novaLinha = tabela.tBodies[0].insertRow(-1);
            const numCols = tabela.rows[0].cells.length;
            for (let i = 0; i < numCols; i++) {
                novaLinha.appendChild(criarCelulaInput('', false));
            }
            novaLinha.cells[0]?.querySelector('input')?.focus();
        } else if (acao === 'removerLinha' && tabela.rows.length > 2) {
            tabela.deleteRow(-1);
        } else if (acao === 'addColuna') {
            const newIdx = tabela.rows[0].cells.length + 1;
            const novoHeader = criarCelulaInput(`Coluna ${newIdx}`, true);
            tabela.rows[0].appendChild(novoHeader);
            for (let i = 1; i < tabela.rows.length; i++) {
                tabela.rows[i].appendChild(criarCelulaInput('', false));
            }
            const hInp = novoHeader.querySelector('input');
            hInp?.focus();
            hInp?.select();
        } else if (acao === 'removerColuna' && tabela.rows[0].cells.length > 1) {
            for (let i = 0; i < tabela.rows.length; i++) {
                tabela.rows[i].deleteCell(-1);
            }
        }
        atualizarHtmlDoTextarea();
    };

    const abrirModal = (id = null, titulo = '', conteudo = '') => {
        form.reset();
        guiaIdInput.value = id || '';
        guiaTituloInput.value = titulo;
        guiaConteudoInput.value = conteudo;
        modalTitulo.textContent = id ? 'Editar Guia de Tamanhos' : 'Novo Guia de Tamanhos';
        geradorVisualWrapper.innerHTML = '';

        if (conteudo) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = conteudo;
            const tabelaExistente = tempDiv.querySelector('table');
            if (tabelaExistente && tabelaExistente.rows.length > 0) {
                const headers = Array.from(tabelaExistente.rows[0].cells).map(c => c.textContent.trim());
                const rowsData = Array.from(tabelaExistente.rows).slice(1).map(r =>
                    Array.from(r.cells).map(c => c.textContent.trim())
                );
                if (rowsData.length === 0) {
                    rowsData.push(headers.map(() => ''));
                }
                montarTabelaEditor(headers, rowsData);
            } else {
                gerarTabelaDefault();
            }
        } else {
            gerarTabelaDefault();
        }
        modal.classList.add('ativo');
        document.documentElement.classList.add('scroll-lock');
        setTimeout(() => guiaTituloInput.focus(), 50);
    };

    const fecharModal = () => {
        modal.classList.remove('ativo');
        document.documentElement.classList.remove('scroll-lock');
        geradorVisualWrapper.innerHTML = '';
    };

    const abrirPreview = (titulo, conteudo) => {
        previewTitulo.textContent = titulo;
        previewConteudo.innerHTML = conteudo;
        modalPreview.classList.add('ativo');
        document.documentElement.classList.add('scroll-lock');
    };

    const fecharPreview = () => {
        modalPreview.classList.remove('ativo');
        document.documentElement.classList.remove('scroll-lock');
    };

    // Fechar modais
    btnNovoGuia?.addEventListener('click', () => abrirModal());
    document.getElementById('btn-cancelar-guia')?.addEventListener('click', fecharModal);
    document.getElementById('btn-fechar-preview-guia')?.addEventListener('click', fecharPreview);
    modal?.addEventListener('click', e => { if (e.target === modal) fecharModal(); });
    modalPreview?.addEventListener('click', e => { if (e.target === modalPreview) fecharPreview(); });
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        const c = document.getElementById('modalConfirmacao');
        if (c && c.classList.contains('ativo')) return;
        if (modal?.classList.contains('ativo')) fecharModal();
        if (modalPreview?.classList.contains('ativo')) fecharPreview();
    });

    document.getElementById('btn-adicionar-linha')?.addEventListener('click', () => manipularTabela('addLinha'));
    document.getElementById('btn-remover-linha')?.addEventListener('click', () => manipularTabela('removerLinha'));
    document.getElementById('btn-adicionar-coluna')?.addEventListener('click', () => manipularTabela('addColuna'));
    document.getElementById('btn-remover-coluna')?.addEventListener('click', () => manipularTabela('removerColuna'));

    // Seleção em massa
    const bulkSel = window.initAdminBulkSelection({
        onAction: ids => {
            mostrarModalConfirmacao(
                'Apagar Guias',
                `Tens a certeza que queres apagar permanentemente ${ids.length} guia(s) de tamanhos?`,
                async () => {
                    const tbody = document.querySelector('.tabela-guias tbody');
                    let ok = 0;
                    for (const id of ids) {
                        const fd = new FormData();
                        fd.append('action', 'delete');
                        fd.append('id', id);
                        fd.append('csrf_token', csrfToken);
                        try {
                            const data = await fetch('ajax_operations_guias.php', { method: 'POST', body: fd }).then(r => r.json());
                            if (data.sucesso) {
                                tbody?.querySelector(`tr[data-id="${id}"]`)?.remove();
                                ok++;
                            }
                        } catch {}
                    }
                    if (bulkSel) bulkSel.setMode(false);
                    if (tbody && !tbody.querySelector('tr[data-id]')) {
                        tbody.innerHTML = '<tr><td colspan="4" class="reservation-empty">Ainda não foram criados guias de tamanho.</td></tr>';
                    }
                    if (ok > 0) mostrarPopup(`${ok} guia(s) apagado(s) com sucesso!`, 'sucesso');
                }
            );
        }
    });

    // Delegação na tabela (funciona também após ordenar/pesquisar via AJAX)
    tableWrap?.addEventListener('click', function(e) {
        if (bulkSel && bulkSel.isSelMode()) return;
        const tr = e.target.closest('tr[data-id]');
        if (!tr) return;
        const id = tr.dataset.id;

        if (e.target.closest('.btn-editar-guia')) {
            abrirModal(id, tr.dataset.titulo, tr.dataset.conteudo);
        } else if (e.target.closest('.btn-preview-guia')) {
            abrirPreview(tr.dataset.titulo, tr.dataset.conteudo);
        } else if (e.target.closest('.btn-apagar-guia')) {
            mostrarModalConfirmacao('Apagar Guia', `Tens a certeza que queres apagar o guia "<strong>${esc(tr.dataset.titulo)}</strong>"?`, () => {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                formData.append('csrf_token', csrfToken);
                fetch('ajax_operations_guias.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.sucesso) {
                            mostrarPopup('Guia apagado com sucesso!', 'sucesso');
                            tr.remove();
                            const tbody = document.querySelector('.tabela-guias tbody');
                            if (tbody && !tbody.querySelector('tr[data-id]')) {
                                tbody.innerHTML = '<tr><td colspan="4" class="reservation-empty">Ainda não foram criados guias de tamanho.</td></tr>';
                            }
                        } else {
                            mostrarPopup(data.mensagem || 'Erro ao apagar.', 'erro');
                        }
                    })
                    .catch(() => mostrarPopup('Ocorreu um erro de comunicação.', 'erro'));
            });
        }
    });

    // Duplo clique na linha abre o editor (bloqueado em modo seleção)
    tableWrap?.addEventListener('dblclick', function(e) {
        if (bulkSel && bulkSel.isSelMode()) return;
        if (e.target.closest('button, a')) return;
        const tr = e.target.closest('tr[data-id]');
        if (tr) abrirModal(tr.dataset.id, tr.dataset.titulo, tr.dataset.conteudo);
    });

    form?.addEventListener('submit', function(e) {
        e.preventDefault();
        atualizarHtmlDoTextarea();
        const formData = new FormData(this);
        formData.append('action', guiaIdInput.value ? 'edit' : 'add');

        const botao = document.getElementById('btn-guardar-guia');
        botao.disabled = true;
        botao.textContent = 'A guardar...';

        fetch('ajax_operations_guias.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.sucesso) {
                    fecharModal();
                    mostrarPopup('Guia guardado com sucesso!', 'sucesso');
                    setTimeout(() => window.location.reload(), 450);
                } else {
                    mostrarPopup(data.mensagem || 'Erro ao guardar guia.', 'erro');
                    botao.disabled = false;
                    botao.textContent = 'Guardar Guia';
                }
            })
            .catch(() => {
                mostrarPopup('Ocorreu um erro de comunicação.', 'erro');
                botao.disabled = false;
                botao.textContent = 'Guardar Guia';
            });
    });
});
</script>

<?php include '../templates/footer.php'; ?>
