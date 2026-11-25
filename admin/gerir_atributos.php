<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Serviço AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    if (!csrf_from_post()) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de validação CSRF.']);
        exit;
    }
    $acao = $_POST['acao'] ?? '';
    $response = ['sucesso' => false, 'mensagem' => 'Ação inválida.'];
    if (is_admin_manager()) {
        try {
            if ($acao === 'adicionar_grupo') {
                $nome_grupo  = trim((string)($_POST['nome_grupo'] ?? ''));
                $e_filtravel = !empty($_POST['e_filtravel']) ? 1 : 0;
                if ($nome_grupo === '') {
                    $response['mensagem'] = 'O nome do grupo não pode estar vazio.';
                } else {
                    $stmt_check = $conn->prepare("SELECT id FROM atributos_grupos WHERE nome = ?");
                    $stmt_check->bind_param("s", $nome_grupo);
                    $stmt_check->execute();
                    if ($stmt_check->get_result()->num_rows > 0) {
                        $response['mensagem'] = 'O grupo "' . htmlspecialchars($nome_grupo) . '" já existe.';
                    } else {
                        $stmt_insert = $conn->prepare("INSERT INTO atributos_grupos (nome, e_filtravel) VALUES (?, ?)");
                        $stmt_insert->bind_param("si", $nome_grupo, $e_filtravel);
                        $stmt_insert->execute();
                        $response = [
                            'sucesso'     => true,
                            'mensagem'    => 'Grupo adicionado!',
                            'id'          => (int)$conn->insert_id,
                            'nome'        => $nome_grupo,
                            'e_filtravel' => $e_filtravel,
                        ];
                        $stmt_insert->close();
                    }
                    $stmt_check->close();
                }
            } elseif ($acao === 'adicionar_valor') {
                $grupo_id = (int)($_POST['grupo_id'] ?? 0);
                $valor    = trim((string)($_POST['valor'] ?? ''));
                if ($valor === '' || $grupo_id <= 0) {
                    $response['mensagem'] = 'Dados inválidos.';
                } else {
                    $stmt_check = $conn->prepare("SELECT id FROM atributos_valores WHERE grupo_id = ? AND valor = ?");
                    $stmt_check->bind_param("is", $grupo_id, $valor);
                    $stmt_check->execute();
                    if ($stmt_check->get_result()->num_rows > 0) {
                        $response['mensagem'] = 'O valor "' . htmlspecialchars($valor) . '" já existe neste grupo.';
                    } else {
                        $stmt_max = $conn->prepare("SELECT MAX(ordem) AS max_ordem FROM atributos_valores WHERE grupo_id = ?");
                        $stmt_max->bind_param("i", $grupo_id);
                        $stmt_max->execute();
                        $max_ordem = $stmt_max->get_result()->fetch_assoc()['max_ordem'];
                        $stmt_max->close();
                        $nova_ordem = is_null($max_ordem) ? 0 : ((int)$max_ordem + 1);
                        $stmt_insert = $conn->prepare("INSERT INTO atributos_valores (grupo_id, valor, ordem) VALUES (?, ?, ?)");
                        $stmt_insert->bind_param("isi", $grupo_id, $valor, $nova_ordem);
                        $stmt_insert->execute();
                        $response = ['sucesso' => true, 'mensagem' => 'Valor adicionado!', 'id' => (int)$conn->insert_id, 'valor' => $valor];
                        $stmt_insert->close();
                    }
                    $stmt_check->close();
                }
            } elseif ($acao === 'apagar_grupo') {
                $id_grupo = (int)($_POST['id_grupo'] ?? 0);
                if ($id_grupo > 0) {
                    $stmt = $conn->prepare("DELETE FROM atributos_grupos WHERE id = ?");
                    $stmt->bind_param("i", $id_grupo);
                    $stmt->execute();
                    $stmt->close();
                    $response = ['sucesso' => true, 'mensagem' => 'Grupo apagado.'];
                }
            } elseif ($acao === 'apagar_valor') {
                $id_valor = (int)($_POST['id_valor'] ?? 0);
                if ($id_valor > 0) {
                    $stmt = $conn->prepare("DELETE FROM atributos_valores WHERE id = ?");
                    $stmt->bind_param("i", $id_valor);
                    $stmt->execute();
                    $stmt->close();
                    $response = ['sucesso' => true, 'mensagem' => 'Valor apagado.'];
                }
            } elseif ($acao === 'verificar_uso') {
                $id   = (int)($_POST['id'] ?? 0);
                $tipo = $_POST['tipo'] ?? '';
                $nome_a_verificar    = '';
                $grupo_nome_contexto = '';
                $contagem = 0;

                if ($id > 0 && in_array($tipo, ['grupo', 'valor'], true)) {
                    if ($tipo === 'grupo') {
                        $stmt = $conn->prepare("SELECT nome FROM atributos_grupos WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        $nome_a_verificar = $stmt->get_result()->fetch_assoc()['nome'] ?? '';
                    } else {
                        $stmt = $conn->prepare("SELECT v.valor, g.nome AS grupo_nome FROM atributos_valores v JOIN atributos_grupos g ON v.grupo_id = g.id WHERE v.id = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        $res = $stmt->get_result()->fetch_assoc();
                        $nome_a_verificar    = $res['valor'] ?? '';
                        $grupo_nome_contexto = $res['grupo_nome'] ?? '';
                    }
                    $stmt->close();

                    if ($nome_a_verificar !== '') {
                        $produtos_result = $conn->query("SELECT atributos FROM produtos WHERE atributos IS NOT NULL AND JSON_VALID(atributos)");
                        while ($row = $produtos_result->fetch_assoc()) {
                            $atributos_produto = json_decode($row['atributos'], true);
                            if (!is_array($atributos_produto)) continue;
                            if ($tipo === 'grupo') {
                                if (array_key_exists($nome_a_verificar, $atributos_produto)) $contagem++;
                            } else {
                                if (isset($atributos_produto[$grupo_nome_contexto]) && is_array($atributos_produto[$grupo_nome_contexto])) {
                                    if (in_array($nome_a_verificar, $atributos_produto[$grupo_nome_contexto], true)) $contagem++;
                                }
                            }
                        }
                    }
                }
                $response = ['sucesso' => true, 'em_uso' => $contagem > 0, 'contagem' => $contagem];
            }
        } catch (Exception $e) {
            $response['mensagem'] = 'Erro na base de dados.';
        }
    } else {
        $response['mensagem'] = 'Sem permissão.';
    }
    echo json_encode($response);
    exit;
}

if (!is_admin_manager()) {
    header("Location: /admin");
    exit;
}

$filtro_tipo = trim((string)($_GET['tipo'] ?? ''));
$filtro_q    = trim((string)($_GET['q'] ?? ''));

$where  = [];
$params = [];
$types  = '';

if ($filtro_tipo === '1') {
    $where[] = 'g.e_filtravel = 1';
} elseif ($filtro_tipo === '0') {
    $where[] = 'g.e_filtravel = 0';
}
if ($filtro_q !== '') {
    $like     = '%' . $filtro_q . '%';
    $where[]  = '(g.nome LIKE ? OR EXISTS (SELECT 1 FROM atributos_valores av WHERE av.grupo_id = g.id AND av.valor LIKE ?))';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

$sort_map = [
    'nome'      => 'g.nome',
    'filtravel' => 'g.e_filtravel',
];
$sort_by   = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map) ? $_GET['sort'] : '';
$sort_dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'desc') ? 'desc' : 'asc';
$order_sql = $sort_by !== '' ? ($sort_map[$sort_by] . ' ' . strtoupper($sort_dir) . ', g.nome ASC') : 'g.nome ASC';

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $conn->prepare("SELECT g.* FROM atributos_grupos g {$where_sql} ORDER BY {$order_sql}");
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$grupos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$todos_valores_result = $conn->query("SELECT id, grupo_id, valor, ordem FROM atributos_valores ORDER BY grupo_id, ordem ASC, id ASC");
$valores_agrupados = [];
if ($todos_valores_result) {
    while ($valor = $todos_valores_result->fetch_assoc()) {
        $valores_agrupados[(int)$valor['grupo_id']][] = [
            'id'    => (int)$valor['id'],
            'valor' => (string)$valor['valor'],
        ];
    }
}

include '../templates/header.php';
?>

<main class="dashboard-container animate-entry">

    <?php echo ui_page_header("Gestão de Atributos", ['layout' => 'admin', 'back_url' => '/admin', 'back_title' => 'Voltar ao Painel']); ?>

    <?php echo ui_table_toolbar([
        'primary_action' => ui_button('Novo Grupo', ['id' => 'btn-novo-grupo', 'class' => 'button btn-with-plus btn-with-plus-text']),
        'filter'         => [
            'name'    => 'tipo',
            'options' => [
                ''  => 'Todos os atributos',
                '1' => 'Filtro na loja',
                '0' => 'Interno',
            ],
            'value'   => $filtro_tipo,
            'title'   => 'Filtrar por tipo',
        ],
        'search'         => ['name' => 'q', 'value' => $filtro_q],
    ]); ?>

    <?php
    $attrRows = [];
    foreach ($grupos as $grupo) {
        $gid       = (int)$grupo['id'];
        $filtravel = (int)$grupo['e_filtravel'] === 1;
        $vals      = $valores_agrupados[$gid] ?? [];
        $totalVals = count($vals);

        if ($totalVals === 0) {
            $valsPreview = '<span class="attr-vals-empty">Sem valores</span>';
        } else {
            $primeiros = array_map(fn($v) => ui_e($v['valor']), array_slice($vals, 0, 5));
            $extra     = $totalVals > 5 ? ' <span class="attr-vals-more">+' . ($totalVals - 5) . '</span>' : '';
            $valsPreview = '<span class="attr-vals-preview">' . implode(', ', $primeiros) . $extra . '</span>';
        }

        $filtroCheckbox = ui_checkbox('', '', $filtravel, [
            'id'          => false,
            'class'       => 'attr-filtravel-check',
            'input_class' => 'cb-toggle-filtravel',
            'title'       => $filtravel ? 'Ativo como filtro na loja (clica para desativar)' : 'Desativado na loja (clica para ativar como filtro)',
            'attrs'       => ['data-id' => $gid],
        ]);

        $acoesCell = '<div class="acoes-tabela">'
            . '<button type="button" class="btn-expand-single" data-id="' . $gid . '" title="Ver valores" aria-label="Ver valores" aria-expanded="false"></button>'
            . ui_icon_button('edit', ['class' => 'btn-editar-grupo', 'title' => 'Editar nome do grupo', 'attrs' => ['data-id' => $gid]])
            . ui_icon_button('delete', ['class' => 'btn-apagar-grupo', 'title' => 'Eliminar grupo', 'attrs' => ['data-id' => $gid, 'data-nome' => $grupo['nome']]])
            . '</div>';

        $attrRows[] = [
            'attrs' => [
                'data-id'   => $gid,
                'data-nome' => $grupo['nome'],
            ],
            'cells' => [
                '<strong class="attr-grupo-nome" style="color:var(--cor-slate-800);">' . ui_e($grupo['nome']) . '</strong>',
                $valsPreview,
                $filtroCheckbox,
                $acoesCell,
            ],
        ];
    }

    echo ui_table([
        ['label' => 'Grupo', 'sort' => 'nome'],
        'Valores',
        ['label' => 'Filtro na loja', 'sort' => ['key' => 'filtravel', 'default_dir' => 'desc']],
        'Ações',
    ], $attrRows, [
        'wrap_id'       => 'table-wrapper',
        'wrap_style'    => 'max-width: 1200px; margin: 0 auto;',
        'table_class'   => 'admin-table tabela-atributos',
        'empty_message' => ($filtro_q !== '' || $filtro_tipo !== '') ? 'Nenhum atributo encontrado com os filtros selecionados.' : 'Ainda não existem grupos de atributos.',
        'sort'          => ['by' => $sort_by, 'dir' => $sort_dir],
    ]);
    ?>
</main>

<?php echo ui_bulk_bar('', '', [
    'unit'         => 'selecionado(s)',
    'action_label' => 'Apagar Selecionados',
]); ?>

<!-- Modais simples (1 campo cada) -->
<?php
$novoGrupoBody = '<form id="form-novo-grupo" class="attr-modal-form">'
    . '<div class="qe-f">'
    . '  <label for="novo-grupo-nome">Nome do Grupo</label>'
    . ui_input('nome_grupo', ['id' => 'novo-grupo-nome', 'class' => 'qe-in', 'placeholder' => 'Ex: Material, Estação, Estilo...', 'required' => true, 'attrs' => ['autocomplete' => 'off']])
    . '</div>'
    . '<div class="attr-switch-box">'
    . ui_checkbox('e_filtravel', 'Mostrar como filtro na loja', true, ['value' => '1', 'id' => 'novo-grupo-filtravel'])
    . '</div>'
    . '</form>';
echo ui_modal('modal-novo-grupo', 'Novo Grupo de Atributos', $novoGrupoBody, [
    'class'  => 'attr-modal',
    'footer' => ui_button('Cancelar', ['id' => 'btn-cancelar-novo-grupo', 'class' => 'qe-btn-cancel'])
        . ui_button('Criar Grupo', ['type' => 'submit', 'variant' => 'primary', 'form' => 'form-novo-grupo', 'id' => 'btn-submit-novo-grupo']),
]);

$editGrupoBody = '<form id="form-editar-grupo" class="attr-modal-form"><div class="qe-f">'
    . '<label for="editar-grupo-nome">Nome do Grupo</label>'
    . ui_input('nome', ['id' => 'editar-grupo-nome', 'class' => 'qe-in', 'placeholder' => 'Nome do grupo', 'required' => true, 'attrs' => ['autocomplete' => 'off']])
    . '</div></form>';
echo ui_modal('modal-editar-grupo', 'Editar Grupo', $editGrupoBody, [
    'class'  => 'attr-modal',
    'footer' => ui_button('Cancelar', ['id' => 'btn-cancelar-editar-grupo', 'class' => 'qe-btn-cancel'])
        . ui_button('Guardar', ['type' => 'submit', 'variant' => 'primary', 'form' => 'form-editar-grupo', 'id' => 'btn-submit-editar-grupo']),
]);

$novoValBody = '<form id="form-novo-valor" class="attr-modal-form"><div class="qe-f">'
    . '<label for="novo-valor-input">Valor</label>'
    . ui_input('valor', ['id' => 'novo-valor-input', 'class' => 'qe-in', 'placeholder' => 'Ex: Algodão, S, Preto...', 'required' => true, 'attrs' => ['autocomplete' => 'off', 'maxlength' => '50']])
    . '</div></form>';
echo ui_modal('modal-novo-valor', 'Novo Valor', $novoValBody, [
    'class'  => 'attr-modal',
    'footer' => ui_button('Cancelar', ['id' => 'btn-cancelar-novo-valor', 'class' => 'qe-btn-cancel'])
        . ui_button('Adicionar', ['type' => 'submit', 'variant' => 'primary', 'form' => 'form-novo-valor', 'id' => 'btn-submit-novo-valor']),
]);

$editValBody = '<form id="form-editar-valor" class="attr-modal-form"><div class="qe-f">'
    . '<label for="editar-valor-input">Valor</label>'
    . ui_input('valor', ['id' => 'editar-valor-input', 'class' => 'qe-in', 'placeholder' => 'Nome do valor', 'required' => true, 'attrs' => ['autocomplete' => 'off', 'maxlength' => '50']])
    . '</div></form>';
echo ui_modal('modal-editar-valor', 'Editar Valor', $editValBody, [
    'class'  => 'attr-modal',
    'footer' => ui_button('Cancelar', ['id' => 'btn-cancelar-editar-valor', 'class' => 'qe-btn-cancel'])
        . ui_button('Guardar', ['type' => 'submit', 'variant' => 'primary', 'form' => 'form-editar-valor', 'id' => 'btn-submit-editar-valor']),
]);
?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken       = <?php echo json_encode($_SESSION['csrf_token']); ?>;
    const valoresPorGrupo = <?php echo json_encode($valores_agrupados, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    const tableWrap       = document.getElementById('table-wrapper');
    const gripSvg         = '<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="5" r="1.8"/><circle cx="15" cy="5" r="1.8"/><circle cx="9" cy="12" r="1.8"/><circle cx="15" cy="12" r="1.8"/><circle cx="9" cy="19" r="1.8"/><circle cx="15" cy="19" r="1.8"/></svg>';

    const modalNovo     = document.getElementById('modal-novo-grupo');
    const formNovo      = document.getElementById('form-novo-grupo');
    const inputNovoNome = document.getElementById('novo-grupo-nome');

    const modalEdit     = document.getElementById('modal-editar-grupo');
    const formEdit      = document.getElementById('form-editar-grupo');
    const inputEditNome = document.getElementById('editar-grupo-nome');
    let editGrupoId     = 0;

    const modalNovoVal  = document.getElementById('modal-novo-valor');
    const formNovoVal   = document.getElementById('form-novo-valor');
    const inputNovoVal  = document.getElementById('novo-valor-input');
    let addValGrupoId   = 0;

    const modalEditVal  = document.getElementById('modal-editar-valor');
    const formEditVal   = document.getElementById('form-editar-valor');
    const inputEditVal  = document.getElementById('editar-valor-input');
    let editValId       = 0;
    let editValGrupoId  = 0;

    let openGrupoId     = 0;
    let sortableInst    = null;

    function esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }
    function postAjax(url, formData) {
        formData.set('csrf_token', csrfToken);
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        }).then(r => r.json());
    }
    function abrirModal(el) {
        if (!el) return;
        el.style.display = 'flex';
        document.documentElement.classList.add('scroll-lock');
    }
    function fecharModal(el) {
        if (!el) return;
        el.style.display = 'none';
        document.documentElement.classList.remove('scroll-lock');
    }

    // Fechar modais
    const todosModais = [modalNovo, modalEdit, modalNovoVal, modalEditVal];
    document.getElementById('btn-cancelar-novo-grupo')?.addEventListener('click', () => fecharModal(modalNovo));
    document.getElementById('btn-cancelar-editar-grupo')?.addEventListener('click', () => fecharModal(modalEdit));
    document.getElementById('btn-cancelar-novo-valor')?.addEventListener('click', () => fecharModal(modalNovoVal));
    document.getElementById('btn-cancelar-editar-valor')?.addEventListener('click', () => fecharModal(modalEditVal));
    todosModais.forEach(m => m?.addEventListener('click', e => { if (e.target === m) fecharModal(m); }));
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        const c = document.getElementById('modalConfirmacao');
        if (c && c.classList.contains('ativo')) return;
        todosModais.forEach(m => { if (m?.style.display === 'flex') fecharModal(m); });
    });

    // Atualiza preview na linha pai
    function renderValoresPreview(gid) {
        const tr = document.querySelector(`.tabela-atributos tbody tr[data-id="${gid}"]`);
        if (!tr) return;
        const vals = valoresPorGrupo[gid] || [];
        const td = tr.children[1];
        if (!td) return;
        if (vals.length === 0) {
            td.innerHTML = '<span class="attr-vals-empty">Sem valores</span>';
        } else {
            const primeiros = vals.slice(0, 5).map(v => esc(v.valor));
            const extra = vals.length > 5 ? ` <span class="attr-vals-more">+${vals.length - 5}</span>` : '';
            td.innerHTML = `<span class="attr-vals-preview">${primeiros.join(', ')}${extra}</span>`;
        }
    }

    // Subtabela inline com animação suave de abertura e fecho
    function fecharSubtabela(imediato = false) {
        if (sortableInst) { sortableInst.destroy(); sortableInst = null; }
        document.querySelectorAll('.tabela-atributos tbody tr.is-expanded').forEach(r => r.classList.remove('is-expanded'));
        document.querySelectorAll('.btn-expand-single.is-open').forEach(b => {
            b.classList.remove('is-open');
            b.setAttribute('aria-expanded', 'false');
        });
        openGrupoId = 0;

        document.querySelectorAll('.tabela-atributos tbody tr.attr-subrow').forEach(r => {
            if (imediato) {
                r.remove();
                return;
            }
            if (r.dataset.closing === '1') return;
            r.dataset.closing = '1';
            r.classList.remove('is-open');
            const collapse = r.querySelector('.attr-subrow-collapse');
            let removed = false;
            const cleanup = () => {
                if (removed) return;
                removed = true;
                r.remove();
            };
            collapse?.addEventListener('transitionend', ev => {
                if (ev.propertyName === 'grid-template-rows') cleanup();
            }, { once: true });
            setTimeout(cleanup, 360);
        });
    }

    function renderSubtabelaConteudo(gid, subTr) {
        const vals = valoresPorGrupo[gid] || [];
        const trPai = document.querySelector(`.tabela-atributos tbody tr[data-id="${gid}"]`);
        const grupoNome = trPai ? (trPai.dataset.nome || '') : '';

        const itensHtml = vals.length === 0
            ? '<div class="attr-sub-empty">Ainda não existem valores neste grupo.</div>'
            : `<div class="attr-sub-grid" data-grupo-id="${gid}">` + vals.map(v => `
                <div class="attr-sub-val-row" data-val-id="${v.id}" data-grupo-id="${gid}" data-valor="${esc(v.valor)}">
                    <span class="value-drag-handle" title="Arrastar para reordenar">${gripSvg}</span>
                    <span class="attr-sub-val-name" title="${esc(v.valor)}">${esc(v.valor)}</span>
                    <div class="attr-sub-val-actions">
                        <button type="button" class="btn-edit-single btn-editar-valor" title="Editar valor" aria-label="Editar valor"></button>
                        <button type="button" class="btn-del-single btn-apagar-valor" title="Eliminar valor" aria-label="Eliminar valor"></button>
                    </div>
                </div>
            `).join('') + '</div>';

        const btnOrdenarHtml = vals.length >= 2
            ? `<button type="button" class="ui-btn ui-btn--default button btn-ordenar-subvalores" data-grupo-id="${gid}">Ordenar A-Z</button>`
            : '';

        subTr.innerHTML = `
            <td colspan="4" class="attr-subrow-cell">
                <div class="attr-subrow-collapse">
                    <div class="attr-subrow-inner">
                        <div class="attr-subtable-panel">
                            <div class="attr-subtable-head">
                                <button type="button" class="ui-btn ui-btn--default button btn-with-plus btn-with-plus-text btn-add-subvalor" data-grupo-id="${gid}">Novo Valor</button>
                                ${btnOrdenarHtml}
                            </div>
                            <div class="attr-subtable-body">
                                ${itensHtml}
                            </div>
                        </div>
                    </div>
                </div>
            </td>
        `;

        if (sortableInst) { sortableInst.destroy(); sortableInst = null; }
        const subGrid = subTr.querySelector('.attr-sub-grid');
        if (subGrid && vals.length >= 2 && typeof Sortable !== 'undefined') {
            sortableInst = new Sortable(subGrid, {
                animation: 180,
                handle: '.value-drag-handle',
                ghostClass: 'attr-val-ghost',
                fallbackClass: 'attr-val-fallback',
                forceFallback: true,
                fallbackOnBody: true,
                scroll: true,
                bubbleScroll: true,
                scrollSensitivity: 90,
                scrollSpeed: 18,
                onEnd: () => {
                    const ordemIds = Array.from(subGrid.querySelectorAll('.attr-sub-val-row')).map(r => parseInt(r.dataset.valId, 10));
                    if (ordemIds.length < 2) return;
                    const fd = new FormData();
                    fd.append('ordem', JSON.stringify(ordemIds));
                    postAjax('ajax_salvar_ordem.php', fd).then(data => {
                        if (data.sucesso) {
                            const map = new Map((valoresPorGrupo[gid] || []).map(v => [v.id, v]));
                            valoresPorGrupo[gid] = ordemIds.map(id => map.get(id)).filter(Boolean);
                            renderValoresPreview(gid);
                            mostrarPopup('Ordem guardada!', 'sucesso');
                        }
                    });
                }
            });
        }
    }

    function toggleSubtabela(gid) {
        const tr = document.querySelector(`.tabela-atributos tbody tr[data-id="${gid}"]`);
        if (!tr) return;
        if (openGrupoId === gid) {
            fecharSubtabela(false);
            return;
        }
        fecharSubtabela(false);
        openGrupoId = gid;
        tr.classList.add('is-expanded');
        const btn = tr.querySelector('.btn-expand-single');
        if (btn) {
            btn.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
        }
        const subTr = document.createElement('tr');
        subTr.className = 'attr-subrow';
        subTr.dataset.parentId = gid;
        renderSubtabelaConteudo(gid, subTr);
        tr.insertAdjacentElement('afterend', subTr);
        // Força layout inicial (0fr) antes de animar para 1fr
        void subTr.offsetHeight;
        requestAnimationFrame(() => {
            if (subTr.dataset.closing !== '1') {
                subTr.classList.add('is-open');
            }
        });
    }

    // Seleção em massa
    const bulkSel = window.initAdminBulkSelection({
        onAction: ids => {
            mostrarModalConfirmacao(
                'Apagar Grupos',
                `Tens a certeza que queres apagar permanentemente ${ids.length} grupo(s) de atributos e todos os seus valores?`,
                async () => {
                    const tbody = document.querySelector('.tabela-atributos tbody');
                    let okCount = 0;
                    for (const id of ids) {
                        const fd = new FormData();
                        fd.append('acao', 'apagar_grupo');
                        fd.append('id_grupo', id);
                        try {
                            const data = await postAjax('gerir_atributos.php', fd);
                            if (data.sucesso) {
                                tbody?.querySelector(`tr[data-id="${id}"]`)?.remove();
                                delete valoresPorGrupo[id];
                                okCount++;
                            }
                        } catch {}
                    }
                    if (bulkSel) bulkSel.setMode(false);
                    if (tbody && !tbody.querySelector('tr[data-id]')) {
                        tbody.innerHTML = '<tr><td colspan="4" class="reservation-empty">Ainda não existem grupos de atributos.</td></tr>';
                    }
                    if (okCount > 0) mostrarPopup(`${okCount} grupo(s) apagado(s).`, 'sucesso');
                }
            );
        }
    });

    // Ao entrar em modo seleção, fecha qualquer subtabela aberta
    document.getElementById('btn-sel-mode')?.addEventListener('click', () => fecharSubtabela());

    // Novo Grupo
    document.getElementById('btn-novo-grupo')?.addEventListener('click', () => {
        formNovo.reset();
        abrirModal(modalNovo);
        setTimeout(() => inputNovoNome.focus(), 50);
    });

    formNovo?.addEventListener('submit', e => {
        e.preventDefault();
        const btn = document.getElementById('btn-submit-novo-grupo');
        btn.disabled = true;
        btn.textContent = 'A criar...';
        const fd = new FormData(formNovo);
        fd.append('acao', 'adicionar_grupo');
        postAjax('gerir_atributos.php', fd)
            .then(data => {
                if (data.sucesso) {
                    fecharModal(modalNovo);
                    mostrarPopup(data.mensagem, 'sucesso');
                    setTimeout(() => window.location.reload(), 350);
                } else {
                    mostrarPopup(data.mensagem || 'Erro ao criar grupo.', 'erro');
                }
            })
            .catch(() => mostrarPopup('Erro de ligação.', 'erro'))
            .finally(() => { btn.disabled = false; btn.textContent = 'Criar Grupo'; });
    });

    // Editar Nome do Grupo
    formEdit?.addEventListener('submit', e => {
        e.preventDefault();
        const novoNome = inputEditNome.value.trim();
        if (!editGrupoId || !novoNome) return;
        const btn = document.getElementById('btn-submit-editar-grupo');
        btn.disabled = true;
        btn.textContent = 'A guardar...';
        const fd = new FormData();
        fd.append('id', editGrupoId);
        fd.append('tipo', 'grupo');
        fd.append('nome', novoNome);
        postAjax('ajax_editar_atributo.php', fd)
            .then(data => {
                if (data.sucesso) {
                    const tr = document.querySelector(`.tabela-atributos tbody tr[data-id="${editGrupoId}"]`);
                    if (tr) {
                        tr.dataset.nome = novoNome;
                        const nomeEl = tr.querySelector('.attr-grupo-nome');
                        if (nomeEl) nomeEl.textContent = novoNome;
                        const delBtn = tr.querySelector('.btn-apagar-grupo');
                        if (delBtn) delBtn.dataset.nome = novoNome;
                    }
                    if (openGrupoId === editGrupoId) {
                        const subTr = document.querySelector(`.tabela-atributos tbody tr.attr-subrow[data-parent-id="${editGrupoId}"]`);
                        if (subTr) renderSubtabelaConteudo(editGrupoId, subTr);
                    }
                    fecharModal(modalEdit);
                    mostrarPopup('Nome atualizado!', 'sucesso');
                } else {
                    mostrarPopup(data.mensagem || 'Erro ao guardar nome.', 'erro');
                }
            })
            .catch(() => mostrarPopup('Erro de ligação.', 'erro'))
            .finally(() => { btn.disabled = false; btn.textContent = 'Guardar'; });
    });

    // Novo Valor (na subtabela)
    formNovoVal?.addEventListener('submit', e => {
        e.preventDefault();
        const valor = inputNovoVal.value.trim();
        if (!addValGrupoId || !valor) return;
        const btn = document.getElementById('btn-submit-novo-valor');
        btn.disabled = true;
        btn.textContent = 'A adicionar...';
        const fd = new FormData();
        fd.append('acao', 'adicionar_valor');
        fd.append('grupo_id', addValGrupoId);
        fd.append('valor', valor);
        postAjax('gerir_atributos.php', fd)
            .then(data => {
                if (data.sucesso) {
                    if (!valoresPorGrupo[addValGrupoId]) valoresPorGrupo[addValGrupoId] = [];
                    valoresPorGrupo[addValGrupoId].push({ id: parseInt(data.id, 10), valor: data.valor });
                    renderValoresPreview(addValGrupoId);
                    const subTr = document.querySelector(`.tabela-atributos tbody tr.attr-subrow[data-parent-id="${addValGrupoId}"]`);
                    if (subTr) renderSubtabelaConteudo(addValGrupoId, subTr);
                    fecharModal(modalNovoVal);
                    mostrarPopup(data.mensagem, 'sucesso');
                } else {
                    mostrarPopup(data.mensagem || 'Erro ao adicionar valor.', 'erro');
                }
            })
            .catch(() => mostrarPopup('Erro de ligação.', 'erro'))
            .finally(() => { btn.disabled = false; btn.textContent = 'Adicionar'; });
    });

    // Editar Valor (na subtabela)
    formEditVal?.addEventListener('submit', e => {
        e.preventDefault();
        const novo = inputEditVal.value.trim();
        if (!editValId || !novo) return;
        const btn = document.getElementById('btn-submit-editar-valor');
        btn.disabled = true;
        btn.textContent = 'A guardar...';
        const fd = new FormData();
        fd.append('id', editValId);
        fd.append('tipo', 'valor');
        fd.append('nome', novo);
        postAjax('ajax_editar_atributo.php', fd)
            .then(data => {
                if (data.sucesso) {
                    const item = (valoresPorGrupo[editValGrupoId] || []).find(v => v.id === editValId);
                    if (item) item.valor = novo;
                    renderValoresPreview(editValGrupoId);
                    const subTr = document.querySelector(`.tabela-atributos tbody tr.attr-subrow[data-parent-id="${editValGrupoId}"]`);
                    if (subTr) renderSubtabelaConteudo(editValGrupoId, subTr);
                    fecharModal(modalEditVal);
                    mostrarPopup('Valor atualizado!', 'sucesso');
                } else {
                    mostrarPopup(data.mensagem || 'Erro ao editar valor.', 'erro');
                }
            })
            .catch(() => mostrarPopup('Erro de ligação.', 'erro'))
            .finally(() => { btn.disabled = false; btn.textContent = 'Guardar'; });
    });

    // Toggle Filtro na loja via checkbox
    tableWrap?.addEventListener('change', e => {
        const cb = e.target.closest('.cb-toggle-filtravel');
        if (!cb) return;
        if (bulkSel && bulkSel.isSelMode()) {
            cb.checked = !cb.checked;
            return;
        }
        const id = cb.dataset.id;
        const prevState = !cb.checked;
        cb.disabled = true;
        const fd = new FormData();
        fd.append('id', id);
        postAjax('ajax_toggle_filtravel.php', fd)
            .then(data => {
                if (data.sucesso) {
                    const isFilt = data.novo_estado === 1;
                    cb.checked = isFilt;
                    const lbl = cb.closest('label');
                    if (lbl) {
                        lbl.title = isFilt
                            ? 'Ativo como filtro na loja (clica para desativar)'
                            : 'Desativado na loja (clica para ativar como filtro)';
                    }
                } else {
                    cb.checked = prevState;
                    mostrarPopup(data.mensagem || 'Erro ao atualizar.', 'erro');
                }
            })
            .catch(() => { cb.checked = prevState; mostrarPopup('Erro de ligação.', 'erro'); })
            .finally(() => { cb.disabled = false; });
    });

    // Duplo clique na linha pai expande/recolhe a subtabela
    tableWrap?.addEventListener('dblclick', e => {
        if (bulkSel && bulkSel.isSelMode()) return;
        if (e.target.closest('a, button, label, input, .attr-subrow')) return;
        const tr = e.target.closest('tr[data-id]');
        if (tr) toggleSubtabela(parseInt(tr.dataset.id, 10));
    });

    // Delegação global na tabela e subtabela
    tableWrap?.addEventListener('click', e => {
        if (bulkSel && bulkSel.isSelMode()) return;
        if (e.target.closest('.attr-filtravel-check')) return;

        // Seta expandir/recolher subtabela
        const expandBtn = e.target.closest('.btn-expand-single');
        if (expandBtn) {
            toggleSubtabela(parseInt(expandBtn.dataset.id, 10));
            return;
        }

        // Editar nome do grupo
        const editGrupoBtn = e.target.closest('.btn-editar-grupo');
        if (editGrupoBtn) {
            const tr = editGrupoBtn.closest('tr[data-id]');
            if (!tr) return;
            editGrupoId = parseInt(tr.dataset.id, 10);
            inputEditNome.value = tr.dataset.nome || '';
            abrirModal(modalEdit);
            setTimeout(() => { inputEditNome.focus(); inputEditNome.select(); }, 50);
            return;
        }

        // Apagar grupo
        const delGrupoBtn = e.target.closest('.btn-apagar-grupo');
        if (delGrupoBtn) {
            const tr = delGrupoBtn.closest('tr[data-id]');
            const id = parseInt(tr.dataset.id, 10);
            const nome = tr.dataset.nome;
            const checkFd = new FormData();
            checkFd.append('acao', 'verificar_uso');
            checkFd.append('id', id);
            checkFd.append('tipo', 'grupo');
            postAjax('gerir_atributos.php', checkFd).then(data => {
                let msg = `Tens a certeza que queres apagar o grupo "<strong>${esc(nome)}</strong>" e todos os seus valores?`;
                if (data.em_uso) {
                    msg = `<strong>Atenção:</strong> O grupo "<strong>${esc(nome)}</strong>" está em uso em <strong>${data.contagem} produto(s)</strong>.<br><br>Apagar mesmo assim?`;
                }
                mostrarModalConfirmacao('Apagar Grupo', msg, () => {
                    const delFd = new FormData();
                    delFd.append('acao', 'apagar_grupo');
                    delFd.append('id_grupo', id);
                    postAjax('gerir_atributos.php', delFd).then(res => {
                        if (res.sucesso) {
                            if (openGrupoId === id) fecharSubtabela();
                            tr.remove();
                            delete valoresPorGrupo[id];
                            mostrarPopup(res.mensagem, 'sucesso');
                            const tbody = document.querySelector('.tabela-atributos tbody');
                            if (tbody && !tbody.querySelector('tr[data-id]')) {
                                tbody.innerHTML = '<tr><td colspan="4" class="reservation-empty">Ainda não existem grupos de atributos.</td></tr>';
                            }
                        } else {
                            mostrarPopup(res.mensagem || 'Erro ao apagar.', 'erro');
                        }
                    });
                });
            });
            return;
        }

        // Ordenar valores A-Z (botão na subtabela)
        const sortAzBtn = e.target.closest('.btn-ordenar-subvalores');
        if (sortAzBtn) {
            const gid = parseInt(sortAzBtn.dataset.grupoId, 10);
            const vals = valoresPorGrupo[gid] || [];
            if (vals.length < 2) return;
            vals.sort((a, b) => a.valor.localeCompare(b.valor, 'pt', { numeric: true, sensitivity: 'base' }));
            const ordemIds = vals.map(v => v.id);
            const fd = new FormData();
            fd.append('ordem', JSON.stringify(ordemIds));
            postAjax('ajax_salvar_ordem.php', fd).then(data => {
                if (data.sucesso) {
                    renderValoresPreview(gid);
                    const subTr = document.querySelector(`.tabela-atributos tbody tr.attr-subrow[data-parent-id="${gid}"]`);
                    if (subTr) renderSubtabelaConteudo(gid, subTr);
                    mostrarPopup('Ordenado A-Z e guardado!', 'sucesso');
                }
            });
            return;
        }

        // Novo Valor (botão na subtabela)
        const addValBtn = e.target.closest('.btn-add-subvalor');
        if (addValBtn) {
            addValGrupoId = parseInt(addValBtn.dataset.grupoId, 10);
            formNovoVal.reset();
            abrirModal(modalNovoVal);
            setTimeout(() => inputNovoVal.focus(), 50);
            return;
        }

        // Editar Valor (na subtabela)
        const editValBtn = e.target.closest('.btn-editar-valor');
        if (editValBtn) {
            const subRow = editValBtn.closest('.attr-sub-val-row');
            if (!subRow) return;
            editValId = parseInt(subRow.dataset.valId, 10);
            editValGrupoId = parseInt(subRow.dataset.grupoId, 10);
            inputEditVal.value = subRow.dataset.valor || '';
            abrirModal(modalEditVal);
            setTimeout(() => { inputEditVal.focus(); inputEditVal.select(); }, 50);
            return;
        }

        // Apagar Valor (na subtabela)
        const delValBtn = e.target.closest('.btn-apagar-valor');
        if (delValBtn) {
            const subRow = delValBtn.closest('.attr-sub-val-row');
            if (!subRow) return;
            const valId = parseInt(subRow.dataset.valId, 10);
            const gid = parseInt(subRow.dataset.grupoId, 10);
            const valNome = subRow.dataset.valor || '';
            const checkFd = new FormData();
            checkFd.append('acao', 'verificar_uso');
            checkFd.append('id', valId);
            checkFd.append('tipo', 'valor');
            postAjax('gerir_atributos.php', checkFd).then(data => {
                let msg = `Tens a certeza que queres apagar o valor "<strong>${esc(valNome)}</strong>"?`;
                if (data.em_uso) {
                    msg = `<strong>Atenção:</strong> O valor "<strong>${esc(valNome)}</strong>" está em uso em <strong>${data.contagem} produto(s)</strong>.<br><br>Apagar mesmo assim?`;
                }
                mostrarModalConfirmacao('Apagar Valor', msg, () => {
                    const delFd = new FormData();
                    delFd.append('acao', 'apagar_valor');
                    delFd.append('id_valor', valId);
                    postAjax('gerir_atributos.php', delFd).then(res => {
                        if (res.sucesso) {
                            valoresPorGrupo[gid] = (valoresPorGrupo[gid] || []).filter(v => v.id !== valId);
                            renderValoresPreview(gid);
                            const subTr = document.querySelector(`.tabela-atributos tbody tr.attr-subrow[data-parent-id="${gid}"]`);
                            if (subTr) renderSubtabelaConteudo(gid, subTr);
                            mostrarPopup(res.mensagem, 'sucesso');
                        } else {
                            mostrarPopup(res.mensagem || 'Erro ao apagar valor.', 'erro');
                        }
                    });
                });
            });
        }
    });
});
</script>

<?php include '../templates/footer.php'; ?>
