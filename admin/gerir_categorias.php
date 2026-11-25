<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

require_admin_manager('/admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../config/database.php';

$filtro_visivel = trim((string)($_GET['visivel'] ?? ''));
$filtro_q       = trim((string)($_GET['q'] ?? ''));

$where  = [];
$params = [];
$types  = '';

if ($filtro_visivel === '1') {
    $where[] = 'c.visivel = 1';
} elseif ($filtro_visivel === '0') {
    $where[] = 'c.visivel = 0';
}
if ($filtro_q !== '') {
    $where[]  = 'c.nome LIKE ?';
    $params[] = '%' . $filtro_q . '%';
    $types   .= 's';
}

$sort_map = [
    'nome'     => 'c.nome',
    'produtos' => 'total_produtos',
    'visivel'  => 'c.visivel',
];
$sort_by   = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map) ? $_GET['sort'] : '';
$sort_dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'desc') ? 'desc' : 'asc';
$order_sql = $sort_by !== '' ? ($sort_map[$sort_by] . ' ' . strtoupper($sort_dir) . ', c.id ASC') : 'c.id ASC';

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT c.*,
               (SELECT COUNT(*) FROM produtos p WHERE p.categoria COLLATE utf8mb4_unicode_ci = c.nome COLLATE utf8mb4_unicode_ci) AS total_produtos
        FROM categorias c
        {$where_sql}
        ORDER BY {$order_sql}";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$categorias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Lista completa de categorias para o modal de migração (mesmo quando há filtros ativos)
$res_todas = $conn->query("SELECT id, nome FROM categorias ORDER BY nome ASC");
$todas_categorias = ($res_todas !== false) ? $res_todas->fetch_all(MYSQLI_ASSOC) : [];

$titulo_pagina = 'Gerir Categorias';

include '../templates/header.php';
?>

<main class="dashboard-container animate-entry">

    <?php echo ui_page_header('Gerir Categorias', [
        'layout'     => 'admin',
        'back_url'   => '/admin',
        'back_title' => 'Painel',
    ]); ?>

    <?php echo ui_table_toolbar([
        'primary_action' => ui_button('Nova Categoria', ['id' => 'btn-nova-categoria', 'class' => 'button btn-with-plus btn-with-plus-text']),
        'filter'         => [
            'name'    => 'visivel',
            'options' => [
                ''  => 'Todas as categorias',
                '1' => 'Na Pág. Inicial',
                '0' => 'Ocultas na Home',
            ],
            'value'   => $filtro_visivel,
            'title'   => 'Filtrar por visibilidade',
        ],
        'search'         => ['name' => 'q', 'value' => $filtro_q],
    ]); ?>

    <?php
    $catRows = [];
    foreach ($categorias as $cat) {
        $id            = (int)$cat['id'];
        $visivel       = (int)$cat['visivel'] === 1;
        $totalProdsCat = (int)($cat['total_produtos'] ?? 0);

        $thumbInner = !empty($cat['foto_capa'])
            ? '<img src="/public/' . ui_e($cat['foto_capa']) . '" alt="' . ui_e($cat['nome']) . '" id="gc-foto-img-' . $id . '">'
            : '<div class="gc-foto-placeholder" id="gc-foto-img-' . $id . '"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>';

        $fotoCell = '<div class="gc-foto" id="gc-foto-wrap-' . $id . '" data-cat-id="' . $id . '" title="Clica para alterar a foto de capa">'
            . $thumbInner
            . '<div class="gc-foto-overlay"><span>Alterar</span></div>'
            . '<input type="file" class="gc-foto-input" id="gc-foto-input-' . $id . '" accept="image/*" style="display:none;" data-cat-id="' . $id . '">'
            . '</div>';

        $nomeCell = '<span class="gc-nome-texto" id="gc-nome-' . $id . '">' . ui_e($cat['nome']) . '</span>';

        $estadoBadge = ui_badge($visivel ? 'Visível' : 'Oculta', $visivel ? 'success' : 'neutral', [
            'tag'         => 'button',
            'interactive' => true,
            'id'          => 'gc-tag-' . $id,
            'class'       => 'gc-toggle-visivel',
            'title'       => $visivel ? 'Ocultar da página inicial' : 'Mostrar na página inicial (máx. 8)',
            'attrs'       => ['data-cat-id' => $id, 'data-visivel' => $visivel ? '1' : '0'],
        ]);

        $acoesCell = '<div class="acoes-tabela">'
            . ui_icon_button('edit', [
                'class' => 'gc-btn-editar',
                'title' => 'Editar nome da categoria',
                'attrs' => [
                    'data-cat-id' => $id,
                    'data-nome'   => $cat['nome'],
                ],
            ])
            . ui_icon_button('delete', [
                'class' => 'gc-btn-apagar',
                'title' => 'Apagar categoria',
                'attrs' => [
                    'data-cat-id' => $id,
                    'data-nome'   => $cat['nome'],
                ],
            ])
            . '</div>';

        $catRows[] = [
            'attrs' => [
                'data-id'             => $id,
                'data-nome'           => $cat['nome'],
                'data-total-produtos' => $totalProdsCat,
                'class'               => 'gc-item',
            ],
            'cells' => [
                $fotoCell,
                $nomeCell,
                ['class' => 'gc-count-cell', 'attrs' => ['id' => 'gc-count-' . $id], 'html' => (string)$totalProdsCat],
                $estadoBadge,
                $acoesCell,
            ],
        ];
    }

    echo ui_table([
        'Capa',
        ['label' => 'Categoria', 'sort' => 'nome'],
        ['label' => 'Produtos', 'sort' => ['key' => 'produtos', 'default_dir' => 'desc']],
        ['label' => 'Pág. Inicial', 'sort' => ['key' => 'visivel', 'default_dir' => 'desc']],
        'Ações',
    ], $catRows, [
        'wrap_id'       => 'table-wrapper',
        'wrap_style'    => 'max-width: 1200px; margin: 0 auto;',
        'table_class'   => 'admin-table tabela-categorias',
        'empty_message' => ($filtro_q !== '' || $filtro_visivel !== '') ? 'Nenhuma categoria encontrada com os filtros selecionados.' : 'Ainda não existem categorias. Cria a primeira acima.',
        'sort'          => ['by' => $sort_by, 'dir' => $sort_dir],
    ]);
    ?>
</main>

<?php echo ui_bulk_bar('', '', [
    'unit'         => 'selecionada(s)',
    'action_label' => 'Apagar Selecionadas',
]); ?>

<!-- ── Modal: Nova Categoria ─────────────────────────────────────────────── -->
<?php
$novaCatBody = ui_field('nova_cat_nome', 'Nome da Categoria', [
    'id'          => 'nova-cat-nome',
    'placeholder' => 'Ex: Novidades',
    'attrs'       => ['autocomplete' => 'off'],
]);
$novaCatFooter = ui_button('Cancelar', ['id' => 'btn-cancelar-modal-cat', 'class' => 'qe-btn-cancel'])
    . ui_button('Criar Categoria', ['id' => 'btn-criar-categoria', 'variant' => 'primary']);

echo ui_modal('modal-nova-cat', 'Nova Categoria', $novaCatBody, [
    'class'    => 'gc-modal',
    'close_id' => 'btn-fechar-modal-cat',
    'footer'   => $novaCatFooter,
]);

$editarCatBody = ui_field('editar_cat_nome', 'Nome da Categoria', [
    'id'          => 'editar-cat-nome',
    'placeholder' => 'Nome da categoria',
    'attrs'       => ['autocomplete' => 'off'],
]);
$editarCatFooter = ui_button('Cancelar', ['id' => 'btn-cancelar-modal-editar', 'class' => 'qe-btn-cancel'])
    . ui_button('Guardar', ['id' => 'btn-guardar-editar-cat', 'variant' => 'primary']);

echo ui_modal('modal-editar-cat', 'Editar Categoria', $editarCatBody, [
    'class'    => 'gc-modal',
    'close_id' => 'btn-fechar-modal-editar',
    'footer'   => $editarCatFooter,
]);
?>

<!-- ── Modal: Mover Produtos e Apagar Categoria (mesmo estilo de #modalConfirmacao) ── -->
<div id="modal-migrar-cat" class="modal-confirmacao" role="dialog" aria-modal="true" aria-labelledby="modal-migrar-cat-titulo">
    <div class="modal-confirmacao-conteudo" style="max-width: 440px; overflow: visible;">
        <h3 id="modal-migrar-cat-titulo">Mover produtos antes de apagar</h3>
        <p id="migrar-cat-aviso"></p>
        <div id="migrar-cat-select-wrap" style="text-align: left; margin-bottom: 20px;">
            <label for="migrar-cat-destino" style="display:block; font-size:0.8rem; font-weight:600; color:var(--cor-slate-600); margin-bottom:6px;">Mover produtos para</label>
            <?php echo ui_select('', [], '', ['id' => 'migrar-cat-destino']); ?>
        </div>
        <div class="modal-confirmacao-acoes">
            <?php
            echo ui_button('Cancelar', ['id' => 'btn-cancelar-modal-migrar']);
            echo ui_button('Mover e Apagar', ['id' => 'btn-confirmar-migrar-cat', 'variant' => 'danger']);
            ?>
        </div>
    </div>
</div>

<script>
const CSRF = <?php echo json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_AMP); ?>;
let todasCategorias = <?php echo json_encode(array_map(fn($c) => ['id' => (int)$c['id'], 'nome' => (string)$c['nome']], $todas_categorias), JSON_HEX_TAG | JSON_HEX_AMP); ?>;

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function ajaxCat(dados) {
    return fetch('ajax_categoria.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...dados, csrf_token: CSRF })
    }).then(r => r.json());
}

const modalNovaCat      = document.getElementById('modal-nova-cat');
const inputNovaNome     = document.getElementById('nova-cat-nome');
const btnCriarCategoria = document.getElementById('btn-criar-categoria');
const modalEditarCat    = document.getElementById('modal-editar-cat');
const inputEditarNome   = document.getElementById('editar-cat-nome');
const btnGuardarEditar  = document.getElementById('btn-guardar-editar-cat');
const tbody             = document.querySelector('.tabela-categorias tbody');
let editarCatId         = 0;

let bulkSel = null;
document.addEventListener('DOMContentLoaded', () => {
    bulkSel = window.initAdminBulkSelection({
        selectAllLabel: 'Selecionar todas',
        deselectAllLabel: 'Desselecionar todas',
        onAction: ids => {
            const comProdutos = ids.filter(id => {
                const tr = tbody.querySelector(`tr[data-id="${id}"]`);
                return tr && (parseInt(tr.dataset.totalProdutos, 10) || 0) > 0;
            });
            if (comProdutos.length > 0) {
                mostrarPopup('Categorias com produtos não podem ser apagadas em massa. Apaga-as individualmente para mover os produtos.', 'erro');
                return;
            }
            window.mostrarModalConfirmacao(
                'Apagar Categorias',
                `Tens a certeza que queres apagar ${ids.length} categoria(s) vazia(s)?`,
                async () => {
                    let apagadas = 0;
                    for (const id of ids) {
                        try {
                            const data = await ajaxCat({ acao: 'apagar', id: parseInt(id, 10) });
                            if (data.sucesso) {
                                removerLinhaCategoriaUI(id);
                                apagadas++;
                            }
                        } catch {}
                    }
                    if (bulkSel) bulkSel.setMode(false);
                    if (apagadas > 0) {
                        mostrarPopup(`${apagadas} categoria(s) apagada(s) com sucesso.`, 'sucesso');
                    }
                }
            );
        },
    });
});

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

document.getElementById('btn-nova-categoria')?.addEventListener('click', () => {
    inputNovaNome.value = '';
    abrirModal(modalNovaCat);
    setTimeout(() => inputNovaNome.focus(), 50);
});

document.getElementById('btn-fechar-modal-cat')?.addEventListener('click', () => fecharModal(modalNovaCat));
document.getElementById('btn-cancelar-modal-cat')?.addEventListener('click', () => fecharModal(modalNovaCat));
inputNovaNome?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); btnCriarCategoria.click(); } });

btnCriarCategoria?.addEventListener('click', function() {
    const nome = inputNovaNome.value.trim();
    if (!nome) {
        mostrarPopup('Escreve o nome da categoria.', 'erro');
        return;
    }

    this.disabled = true;
    this.textContent = 'A criar...';

    ajaxCat({ acao: 'criar', nome })
        .then(data => {
            if (data.sucesso) {
                fecharModal(modalNovaCat);
                mostrarPopup(data.mensagem, 'sucesso');
                todasCategorias.push({ id: parseInt(data.id, 10), nome });
                adicionarLinhaCategoria(data.id, nome, data.visivel ?? 1, 0);
            } else {
                mostrarPopup(data.mensagem, 'erro');
            }
        })
        .catch(() => mostrarPopup('Erro de ligação.', 'erro'))
        .finally(() => {
            this.disabled = false;
            this.textContent = 'Criar Categoria';
        });
});

function atualizarContagemCategoriaUI(catId, novoTotal) {
    const tr = document.querySelector(`.tabela-categorias tbody tr[data-id="${catId}"]`);
    if (tr) tr.dataset.totalProdutos = String(novoTotal);
    const cell = document.getElementById('gc-count-' + catId);
    if (cell) {
        cell.textContent = String(novoTotal);
    }
}

function adicionarLinhaCategoria(id, nome, visivel = 1, totalProdutos = 0) {
    const emptyRow = tbody.querySelector('td[colspan]')?.closest('tr');
    if (emptyRow) emptyRow.remove();

    const safeNome = escapeHtml(nome);
    const isVis = Number(visivel) === 1;

    const html = `
    <tr class="gc-item" data-id="${id}" data-nome="${safeNome}" data-total-produtos="${totalProdutos}">
        <td>
            <div class="gc-foto" id="gc-foto-wrap-${id}" data-cat-id="${id}" title="Clica para alterar a foto de capa">
                <div class="gc-foto-placeholder" id="gc-foto-img-${id}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
                <div class="gc-foto-overlay"><span>Alterar</span></div>
                <input type="file" class="gc-foto-input" id="gc-foto-input-${id}" accept="image/*" style="display:none;" data-cat-id="${id}">
            </div>
        </td>
        <td><span class="gc-nome-texto" id="gc-nome-${id}">${safeNome}</span></td>
        <td class="gc-count-cell" id="gc-count-${id}">${totalProdutos}</td>
        <td>
            <button type="button" id="gc-tag-${id}" class="ui-badge ${isVis ? 'ui-badge--success' : 'ui-badge--neutral'} ui-badge--interactive gc-toggle-visivel" data-cat-id="${id}" data-visivel="${isVis ? '1' : '0'}" title="${isVis ? 'Ocultar da página inicial' : 'Mostrar na página inicial (máx. 8)'}">${isVis ? 'Visível' : 'Oculta'}</button>
        </td>
        <td>
            <div class="acoes-tabela">
                <button type="button" class="btn-edit-single gc-btn-editar" data-cat-id="${id}" data-nome="${safeNome}" title="Editar nome da categoria" aria-label="Editar nome da categoria"></button>
                <button type="button" class="btn-del-single gc-btn-apagar" data-cat-id="${id}" data-nome="${safeNome}" title="Apagar categoria" aria-label="Apagar categoria"></button>
            </div>
        </td>
    </tr>`;
    tbody.insertAdjacentHTML('beforeend', html);
}

// ── Modal: Editar Nome ──
document.getElementById('btn-fechar-modal-editar')?.addEventListener('click', () => fecharModal(modalEditarCat));
document.getElementById('btn-cancelar-modal-editar')?.addEventListener('click', () => fecharModal(modalEditarCat));
inputEditarNome?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); btnGuardarEditar.click(); } });

btnGuardarEditar?.addEventListener('click', function() {
    const nome = inputEditarNome.value.trim();
    if (!editarCatId || !nome) {
        mostrarPopup('O nome não pode estar vazio.', 'erro');
        return;
    }
    this.disabled = true;
    this.textContent = 'A guardar...';

    ajaxCat({ acao: 'editar', id: editarCatId, nome })
        .then(data => {
            if (data.sucesso) {
                const tr = tbody.querySelector(`tr[data-id="${editarCatId}"]`);
                if (tr) tr.dataset.nome = nome;
                const nomeEl = document.getElementById('gc-nome-' + editarCatId);
                if (nomeEl) nomeEl.textContent = nome;
                const btnEdit = tbody.querySelector(`.gc-btn-editar[data-cat-id="${editarCatId}"]`);
                if (btnEdit) btnEdit.dataset.nome = nome;
                const btnDel = tbody.querySelector(`.gc-btn-apagar[data-cat-id="${editarCatId}"]`);
                if (btnDel) btnDel.dataset.nome = nome;
                const entry = todasCategorias.find(c => c.id === editarCatId);
                if (entry) entry.nome = nome;
                fecharModal(modalEditarCat);
                mostrarPopup(data.mensagem, 'sucesso');
            } else {
                mostrarPopup(data.mensagem, 'erro');
            }
        })
        .catch(() => mostrarPopup('Erro de ligação.', 'erro'))
        .finally(() => {
            this.disabled = false;
            this.textContent = 'Guardar';
        });
});

// ── Event delegation no tbody (funciona também após ordenação/filtro AJAX) ──
tbody?.addEventListener('click', function(e) {
    if (bulkSel && bulkSel.isSelMode()) return;

    // 1. Upload de foto de capa
    const fotoWrap = e.target.closest('.gc-foto');
    if (fotoWrap && !e.target.closest('.gc-foto-input')) {
        document.getElementById('gc-foto-input-' + fotoWrap.dataset.catId)?.click();
        return;
    }

    // 2. Editar nome (lápis)
    const btnEditar = e.target.closest('.gc-btn-editar');
    if (btnEditar) {
        editarCatId = parseInt(btnEditar.dataset.catId, 10);
        inputEditarNome.value = btnEditar.dataset.nome || '';
        abrirModal(modalEditarCat);
        setTimeout(() => { inputEditarNome.focus(); inputEditarNome.select(); }, 50);
        return;
    }

    // 3. Toggle Visível <-> Oculta na Pág. Inicial
    const badgeToggle = e.target.closest('.gc-toggle-visivel');
    if (badgeToggle && !badgeToggle.classList.contains('loading')) {
        const id = parseInt(badgeToggle.dataset.catId, 10);
        const vaiAtivar = badgeToggle.dataset.visivel !== '1';
        if (vaiAtivar) {
            const visiveis = Array.from(document.querySelectorAll('.gc-toggle-visivel')).filter(b => b.dataset.visivel === '1').length;
            if (visiveis >= 8) {
                mostrarPopup('Máximo de 8 categorias visíveis atingido. Oculta outra primeiro.', 'erro');
                return;
            }
        }
        badgeToggle.classList.add('loading');
        ajaxCat({ acao: 'toggle_visivel', id })
            .then(data => {
                if (data.sucesso) {
                    const isVis = Boolean(data.visivel);
                    badgeToggle.dataset.visivel = isVis ? '1' : '0';
                    badgeToggle.textContent = isVis ? 'Visível' : 'Oculta';
                    badgeToggle.classList.toggle('ui-badge--success', isVis);
                    badgeToggle.classList.toggle('ui-badge--neutral', !isVis);
                    badgeToggle.title = isVis ? 'Ocultar da página inicial' : 'Mostrar na página inicial (máx. 8)';
                    mostrarPopup(data.mensagem, isVis ? 'sucesso' : 'info');
                } else {
                    mostrarPopup(data.mensagem, 'erro');
                }
            })
            .catch(() => mostrarPopup('Erro de ligação.', 'erro'))
            .finally(() => badgeToggle.classList.remove('loading'));
        return;
    }

    // 4. Apagar categoria (ou abrir modal de migração se tiver produtos)
    const btnApagar = e.target.closest('.gc-btn-apagar');
    if (btnApagar) {
        const id = parseInt(btnApagar.dataset.catId, 10);
        const nome = btnApagar.dataset.nome;
        const linha = btnApagar.closest('tr[data-id]');
        const totalProdutos = linha ? (parseInt(linha.dataset.totalProdutos, 10) || 0) : 0;

        if (totalProdutos > 0) {
            abrirModalMigrarCategoria(id, nome, totalProdutos);
            return;
        }

        window.mostrarModalConfirmacao(
            'Apagar Categoria',
            `Tens a certeza que queres apagar a categoria "<strong>${escapeHtml(nome)}</strong>"?`,
            () => {
                ajaxCat({ acao: 'apagar', id })
                    .then(data => {
                        if (data.sucesso) {
                            removerLinhaCategoriaUI(id);
                            mostrarPopup(data.mensagem, 'sucesso');
                        } else if (data.requer_migracao) {
                            atualizarContagemCategoriaUI(id, data.total_produtos || 1);
                            abrirModalMigrarCategoria(id, nome, data.total_produtos || 1);
                        } else {
                            mostrarPopup(data.mensagem, 'erro');
                        }
                    })
                    .catch(() => mostrarPopup('Erro de ligação.', 'erro'));
            }
        );
    }
});

tbody?.addEventListener('change', async function(e) {
    const input = e.target.closest('.gc-foto-input');
    if (!input) return;
    const id = input.dataset.catId;
    const file = input.files[0];
    if (!file) return;

    const wrap = document.getElementById('gc-foto-wrap-' + id);
    if (wrap) wrap.classList.add('uploading');

    try {
        const localUrl = URL.createObjectURL(file);
        setFotoCapaThumb(id, localUrl);

        const compressedFile = await comprimirImagem(file, 1200, 1200, 0.85);
        const fd = new FormData();
        fd.append('acao', 'upload_capa');
        fd.append('id', id);
        fd.append('foto', compressedFile, compressedFile.name || 'upload.jpg');
        fd.append('csrf_token', CSRF);

        const res = await fetch('ajax_categoria.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.sucesso) {
            setFotoCapaThumb(id, data.url + '?t=' + Date.now());
            mostrarPopup(data.mensagem, 'sucesso');
        } else {
            mostrarPopup(data.mensagem, 'erro');
        }
        URL.revokeObjectURL(localUrl);
    } catch (err) {
        console.error(err);
        mostrarPopup('Erro ao carregar imagem.', 'erro');
    } finally {
        if (wrap) wrap.classList.remove('uploading');
        input.value = '';
    }
});

// ── Modal de Migração ──
const modalMigrarCat     = document.getElementById('modal-migrar-cat');
const migrarAvisoEl      = document.getElementById('migrar-cat-aviso');
const migrarSelectWrap   = document.getElementById('migrar-cat-select-wrap');
const migrarDestinoSel   = document.getElementById('migrar-cat-destino');
const btnConfirmarMigrar = document.getElementById('btn-confirmar-migrar-cat');
let migrarOrigemId       = 0;

function abrirModalMigrarBox() {
    if (!modalMigrarCat) return;
    modalMigrarCat.classList.add('ativo');
    document.documentElement.classList.add('scroll-lock');
}

function fecharModalMigrar() {
    if (modalMigrarCat) modalMigrarCat.classList.remove('ativo');
    document.documentElement.classList.remove('scroll-lock');
    migrarOrigemId = 0;
}

document.getElementById('btn-cancelar-modal-migrar')?.addEventListener('click', fecharModalMigrar);
modalMigrarCat?.addEventListener('click', e => { if (e.target === modalMigrarCat) fecharModalMigrar(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && modalMigrarCat?.classList.contains('ativo')) fecharModalMigrar(); });

function removerLinhaCategoriaUI(id) {
    todasCategorias = todasCategorias.filter(c => c.id !== parseInt(id, 10));
    const linha = tbody.querySelector(`tr[data-id="${id}"]`);
    if (!linha) return;
    linha.remove();
    if (!tbody.querySelector('tr[data-id]')) {
        tbody.innerHTML = '<tr><td colspan="5" class="reservation-empty">Ainda não existem categorias. Cria a primeira acima.</td></tr>';
    }
}

function abrirModalMigrarCategoria(id, nome, totalProdutos) {
    migrarOrigemId = id;
    const outras = todasCategorias.filter(c => c.id !== id);

    if (outras.length === 0) {
        migrarAvisoEl.innerHTML = `A categoria "<strong>${escapeHtml(nome)}</strong>" tem <strong>${totalProdutos} produto${totalProdutos === 1 ? '' : 's'}</strong> associado${totalProdutos === 1 ? '' : 's'} e não existe outra categoria para os receber. Cria primeiro outra categoria antes de apagar esta.`;
        if (migrarSelectWrap) migrarSelectWrap.style.display = 'none';
        btnConfirmarMigrar.style.display = 'none';
        abrirModalMigrarBox();
        return;
    }

    migrarAvisoEl.innerHTML = `A categoria "<strong>${escapeHtml(nome)}</strong>" tem <strong>${totalProdutos} produto${totalProdutos === 1 ? '' : 's'}</strong> associado${totalProdutos === 1 ? '' : 's'}. Não pode ser apagada diretamente - escolhe para qual categoria existente os queres mover:`;
    btnConfirmarMigrar.textContent = `Mover ${totalProdutos} produto${totalProdutos === 1 ? '' : 's'} e apagar`;
    btnConfirmarMigrar.style.display = '';
    if (migrarSelectWrap) migrarSelectWrap.style.display = '';

    migrarDestinoSel.innerHTML = '';
    outras.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = String(cat.id);
        opt.textContent = cat.nome;
        migrarDestinoSel.appendChild(opt);
    });

    migrarDestinoSel.value = String(outras[0].id);
    if (typeof window.uiRefreshSelect === 'function') window.uiRefreshSelect(migrarDestinoSel);

    abrirModalMigrarBox();
}

btnConfirmarMigrar?.addEventListener('click', function() {
    if (!migrarOrigemId) return;
    const destinoId = parseInt(migrarDestinoSel.value, 10);
    if (!destinoId) {
        mostrarPopup('Escolhe uma categoria de destino.', 'erro');
        return;
    }

    const origemId = migrarOrigemId;
    this.disabled = true;
    const txtOrig = this.textContent;
    this.textContent = 'A mover...';

    ajaxCat({ acao: 'apagar', id: origemId, destino_id: destinoId })
        .then(data => {
            if (data.sucesso) {
                fecharModalMigrar();
                removerLinhaCategoriaUI(origemId);
                if (data.destino_id) {
                    const destItem = tbody.querySelector(`tr[data-id="${data.destino_id}"]`);
                    const atual = destItem ? (parseInt(destItem.dataset.totalProdutos, 10) || 0) : 0;
                    atualizarContagemCategoriaUI(data.destino_id, atual + (parseInt(data.movidos, 10) || 0));
                }
                mostrarPopup(data.mensagem, 'sucesso');
            } else {
                mostrarPopup(data.mensagem || 'Erro ao mover produtos.', 'erro');
            }
        })
        .catch(() => mostrarPopup('Erro de ligação.', 'erro'))
        .finally(() => {
            this.disabled = false;
            this.textContent = txtOrig;
        });
});

function setFotoCapaThumb(id, url) {
    const wrap = document.getElementById('gc-foto-wrap-' + id);
    const thumbnail = document.getElementById('gc-foto-img-' + id);
    if (!wrap) return;
    if (thumbnail && thumbnail.tagName === 'IMG') {
        thumbnail.src = url;
        return;
    }
    const img = document.createElement('img');
    img.id = 'gc-foto-img-' + id;
    img.src = url;
    if (thumbnail) thumbnail.replaceWith(img);
    else wrap.insertBefore(img, wrap.firstChild);
}

function comprimirImagem(file, maxWidth, maxHeight, quality) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = e => {
            const img = new Image();
            img.src = e.target.result;
            img.onload = () => {
                let width = img.width;
                let height = img.height;
                if (width > maxWidth || height > maxHeight) {
                    if (width > height) { height *= maxWidth / width; width = maxWidth; }
                    else                { width *= maxHeight / height; height = maxHeight; }
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob(blob => {
                    if (blob) resolve(new File([blob], file.name.replace(/\.[^/.]+$/, '') + '.webp', { type: 'image/webp' }));
                    else canvas.toBlob(b => resolve(new File([b], file.name, { type: 'image/jpeg' })), 'image/jpeg', quality);
                }, 'image/webp', quality);
            };
            img.onerror = reject;
        };
        reader.onerror = reject;
    });
}
</script>

<?php include '../templates/footer.php'; ?>
