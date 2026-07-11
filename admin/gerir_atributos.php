<?php
// PRIMEIRO: Inicia a sessão e a ligação à BD.
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

// GARANTE QUE O TOKEN CSRF EXISTE
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// SEGUNDO: O nosso "serviço" AJAX.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    if (!csrf_from_post()) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de validacao CSRF.']);
        exit;
    }
    $acao = $_POST['acao'] ?? '';
    $response = ['sucesso' => false, 'mensagem' => 'Ação inválida.'];
    if (isset($_SESSION['admin_logado']) && in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
        try {
            if ($acao === 'adicionar_grupo') {
                $nome_grupo = trim($_POST['nome_grupo']);
                $e_filtravel = isset($_POST['e_filtravel']) ? 1 : 0;
                if (empty($nome_grupo)) {
                    $response['mensagem'] = 'O nome do grupo não pode estar vazio.';
                } else {
                    $stmt_check = $conn->prepare("SELECT id FROM atributos_grupos WHERE nome = ?");
                    $stmt_check->bind_param("s", $nome_grupo); $stmt_check->execute();
                    if ($stmt_check->get_result()->num_rows > 0) {
                        $response['mensagem'] = "O grupo \"".htmlspecialchars($nome_grupo)."\" já existe.";
                    } else {
                        $stmt_insert = $conn->prepare("INSERT INTO atributos_grupos (nome, e_filtravel) VALUES (?, ?)");
                        $stmt_insert->bind_param("si", $nome_grupo, $e_filtravel); $stmt_insert->execute();
                        $response = ['sucesso' => true, 'mensagem' => 'Grupo adicionado!'];
                    }
                }
            } elseif ($acao === 'adicionar_valor') {
                 $grupo_id = (int)($_POST['grupo_id'] ?? 0);
                 $valor = trim($_POST['valor']);
                 if (empty($valor) || $grupo_id <= 0) { $response['mensagem'] = 'Dados inválidos.';
                 } else {
                     $stmt_check = $conn->prepare("SELECT id FROM atributos_valores WHERE grupo_id = ? AND valor = ?");
                     $stmt_check->bind_param("is", $grupo_id, $valor); $stmt_check->execute();
                     if ($stmt_check->get_result()->num_rows > 0) {
                         $response['mensagem'] = "O valor \"".htmlspecialchars($valor)."\" já existe neste grupo.";
                     } else {
                         $stmt_max = $conn->prepare("SELECT MAX(ordem) as max_ordem FROM atributos_valores WHERE grupo_id = ?");
                         $stmt_max->bind_param("i", $grupo_id); $stmt_max->execute();
                         $max_ordem = $stmt_max->get_result()->fetch_assoc()['max_ordem'];
                         $nova_ordem = is_null($max_ordem) ? 0 : $max_ordem + 1;
                         $stmt_insert = $conn->prepare("INSERT INTO atributos_valores (grupo_id, valor, ordem) VALUES (?, ?, ?)");
                         $stmt_insert->bind_param("isi", $grupo_id, $valor, $nova_ordem); $stmt_insert->execute();
                         $response = ['sucesso' => true, 'mensagem' => 'Valor adicionado!', 'id' => $conn->insert_id, 'valor' => $valor];
                     }
                 }
            } elseif ($acao === 'apagar_grupo') {
                 $id_grupo = (int)($_POST['id_grupo'] ?? 0);
                 if ($id_grupo > 0) {
                     $stmt = $conn->prepare("DELETE FROM atributos_grupos WHERE id = ?");
                     $stmt->bind_param("i", $id_grupo); $stmt->execute();
                     $response = ['sucesso' => true, 'mensagem' => 'Grupo apagado.'];
                 }
            } elseif ($acao === 'apagar_valor') {
                 $id_valor = (int)($_POST['id_valor'] ?? 0);
                 if ($id_valor > 0) {
                     $stmt = $conn->prepare("DELETE FROM atributos_valores WHERE id = ?");
                     $stmt->bind_param("i", $id_valor); $stmt->execute();
                     $response = ['sucesso' => true, 'mensagem' => 'Valor apagado.'];
                 }
            } elseif ($acao === 'verificar_uso') {
                $id = (int)($_POST['id'] ?? 0);
                $tipo = $_POST['tipo'] ?? '';
                $nome_a_verificar = '';
                $grupo_nome_contexto = '';
                $contagem = 0;
                $response['em_uso'] = false;
        
                if ($id > 0 && in_array($tipo, ['grupo', 'valor'])) {
                    if ($tipo === 'grupo') {
                        $stmt = $conn->prepare("SELECT nome FROM atributos_grupos WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        $nome_a_verificar = $stmt->get_result()->fetch_assoc()['nome'];
                    } else {
                        $stmt = $conn->prepare("SELECT v.valor, g.nome as grupo_nome FROM atributos_valores v JOIN atributos_grupos g ON v.grupo_id = g.id WHERE v.id = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        $res = $stmt->get_result()->fetch_assoc();
                        $nome_a_verificar = $res['valor'] ?? null;
                        $grupo_nome_contexto = $res['grupo_nome'] ?? null;
                    }
                    $stmt->close();
        
                    if ($nome_a_verificar) {
                        $produtos_result = $conn->query("SELECT atributos FROM produtos WHERE atributos IS NOT NULL AND JSON_VALID(atributos)");
                        while($row = $produtos_result->fetch_assoc()) {
                            $atributos_produto = json_decode($row['atributos'], true);
                            if (!is_array($atributos_produto)) continue;
        
                            if ($tipo === 'grupo') {
                                if (array_key_exists($nome_a_verificar, $atributos_produto)) $contagem++;
                            } else {
                                if (isset($atributos_produto[$grupo_nome_contexto]) && is_array($atributos_produto[$grupo_nome_contexto])) {
                                    if (in_array($nome_a_verificar, $atributos_produto[$grupo_nome_contexto])) $contagem++;
                                }
                            }
                        }
                    }
                }
                if ($contagem > 0) {
                    $response = ['sucesso' => true, 'em_uso' => true, 'contagem' => $contagem];
                } else {
                    $response = ['sucesso' => true, 'em_uso' => false];
                }
            }
        } catch(Exception $e) { $response['mensagem'] = 'Erro na base de dados.'; }
    } else { $response['mensagem'] = 'Sem permissão.'; }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// RENDERIZAÇÃO DA PÁGINA
include '../templates/header.php';
if (!isset($_SESSION['admin_logado']) || !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    echo "<main class='admin-main-content'><p>Acesso negado.</p></main>";
    include '../templates/footer.php';
    exit;
}

$grupos = $conn->query("SELECT * FROM atributos_grupos ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$todos_valores_result = $conn->query("SELECT * FROM atributos_valores ORDER BY grupo_id, ordem ASC, id ASC");
$valores_agrupados = [];
while ($valor = $todos_valores_result->fetch_assoc()) {
    $valores_agrupados[(int)$valor['grupo_id']][] = $valor;
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
            <h2>Gestão de Atributos</h2>
        </div>
    </div>

    <div class="admin-container large">

        <!-- Adicionar Novo Grupo -->
        <div class="add-group-card">
            <h3 style="margin:0; font-size: 1.1rem;">Criar Novo Grupo</h3>
            <form id="form-add-group">
                <div class="input-group">
                    <input type="text" name="nome_grupo" placeholder="Ex: Material, Estação, Estilo..." required autocomplete="off">
                </div>
                <label class="toggle-visiveis">
                    <input type="checkbox" name="e_filtravel" value="1" checked>
                    <div class="toggle-track"><div class="toggle-thumb"></div></div>
                    <span>Mostrar como filtro</span>
                </label>
                <div class="btn-add">
                    <button type="submit" class="add-btn btn-with-plus btn-with-plus-text">Adicionar Grupo</button>
                </div>
            </form>
        </div>

        <!-- Lista de Atributos (Cards) -->
        <div class="attributes-grid" id="lista-grupos-container">
            <?php foreach ($grupos as $grupo): $grupo_id = (int)$grupo['id']; ?>
                <div class="attribute-card collapsed" data-id="<?php echo $grupo_id; ?>" data-tipo="grupo">
                    
                    <div class="attribute-card-header">
                        <div class="attribute-info" title="Clique para expandir/colapsar">
                            <svg class="chevron-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            <span class="attribute-title nome-display"><?php echo htmlspecialchars($grupo['nome']); ?></span>
                            
                            <div class="filter-toggle-container" title="Ativar/Desativar como filtro na loja">
                                <label class="toggle-visiveis" style="transform: scale(0.85);">
                                    <input type="checkbox" class="toggle-filtravel-checkbox" data-id="<?php echo $grupo_id; ?>" <?php echo $grupo['e_filtravel'] ? 'checked' : ''; ?>>
                                    <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                </label>
                                <span class="badge-status <?php echo $grupo['e_filtravel'] ? 'visivel' : 'oculto'; ?>">
                                    <?php echo $grupo['e_filtravel'] ? 'Filtro' : 'Interno'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="attribute-actions">
                            <button type="button" class="button voltar-btn btn-sort-alpha" style="font-size: 0.75rem; padding: 5px 12px;" title="Ordenar valores de A a Z">Ordenar A-Z</button>
                            <button type="button" class="btn-edit-single btn-editar" title="Editar Nome"></button>
                            <button type="button" class="btn-del-single btn-apagar" title="Eliminar Grupo"></button>
                        </div>
                    </div>

                    <div class="values-container sortable-list" data-grupo-id="<?php echo $grupo_id; ?>">
                        <?php if (isset($valores_agrupados[$grupo_id])): ?>
                            <?php foreach ($valores_agrupados[$grupo_id] as $v): ?>
                                <div class="value-item" data-id="<?php echo $v['id']; ?>" data-tipo="valor">
                                    <div class="value-drag-handle">☰</div>
                                    <div class="value-name">
                                        <span class="nome-display"><?php echo htmlspecialchars($v['valor']); ?></span>
                                    </div>
                                    <div class="value-actions">
                                        <button type="button" class="btn-edit-single btn-editar" title="Editar"></button>
                                        <button type="button" class="btn-del-single btn-apagar" title="Eliminar Valor"></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-values-msg" style="padding: 20px; text-align: center; color: #999; font-size: 0.9rem;">
                                Ainda não há valores. Adicione o primeiro abaixo!
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="attribute-card-footer">
                        <form class="add-value-form">
                            <input type="hidden" name="grupo_id" value="<?php echo $grupo_id; ?>">
                            <input type="text" name="valor" placeholder="Novo valor (ex: Algodão)..." required autocomplete="off" maxlength="50">
                            <button type="submit" class="add-btn" style="padding: 8px 15px; font-size: 0.85rem;">Adicionar</button>
                        </form>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const gestorContainer = document.querySelector('.admin-container');
    const mostrarFeedback = window.mostrarPopup;
    const csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;

    // --- Motor de Requisições AJAX ---
    function handleAjaxRequest(url, bodyData, successCallback) {
        bodyData.set('csrf_token', csrfToken);
        fetch(url, { 
            method: 'POST', 
            headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
            body: bodyData 
        })
        .then(res => res.json())
        .then(data => {
            if (data.sucesso) {
                if(data.mensagem) mostrarFeedback(data.mensagem, 'sucesso');
                if(successCallback) successCallback(data);
            } else { 
                mostrarFeedback(data.mensagem || 'Ocorreu um erro.', 'erro'); 
            }
        })
        .catch(err => { 
            console.error("Erro AJAX:", err); 
            mostrarFeedback('Erro de comunicação.', 'erro'); 
        });
    }

    // --- Inicializar Drag & Drop ---
    function initSortable() {
        document.querySelectorAll('.sortable-list').forEach(list => {
            if (list.sortable) list.sortable.destroy(); // Limpar anterior se existir
            list.sortable = new Sortable(list, {
                animation: 200,
                handle: '.value-drag-handle',
                ghostClass: 'ghost-class',
                onEnd: function(evt) {
                    const tbody = evt.to;
                    const ordemIds = Array.from(tbody.querySelectorAll('.value-item')).map(el => el.dataset.id);
                    const formData = new FormData();
                    formData.append('ordem', JSON.stringify(ordemIds));
                    handleAjaxRequest('ajax_salvar_ordem.php', formData, () => {
                        mostrarFeedback('Ordem guardada com sucesso!', 'sucesso');
                    });
                }
            });
        });
    }
    initSortable();

    // --- Toggle Filtrável ---
    gestorContainer.addEventListener('change', function(e) {
        if (!e.target.classList.contains('toggle-filtravel-checkbox')) return;
        const checkbox = e.target;
        const id = checkbox.dataset.id;
        const badge = checkbox.closest('.filter-toggle-container').querySelector('.badge-status');
        const formData = new FormData();
        formData.append('id', id);
        formData.append('csrf_token', csrfToken);

        fetch('ajax_toggle_filtravel.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.sucesso) {
                const isFiltravel = data.novo_estado === 1;
                badge.textContent = isFiltravel ? 'Filtro' : 'Interno';
                badge.classList.remove('visivel', 'oculto');
                badge.classList.add(isFiltravel ? 'visivel' : 'oculto');
            } else {
                checkbox.checked = !checkbox.checked;
                mostrarFeedback(data.mensagem || 'Erro ao atualizar.', 'erro');
            }
        })
        .catch(() => {
            checkbox.checked = !checkbox.checked;
            mostrarFeedback('Erro de comunicação.', 'erro');
        });
    });

    // --- Criar Grupo ---
    document.getElementById('form-add-group')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('acao', 'adicionar_grupo');
        handleAjaxRequest('gerir_atributos.php', formData, () => setTimeout(() => window.location.reload(), 500));
    });

    // --- Adicionar Valor ---
    gestorContainer.addEventListener('submit', function(e) {
        if (e.target.classList.contains('add-value-form')) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('acao', 'adicionar_valor');

            handleAjaxRequest('gerir_atributos.php', formData, (data) => {
                const listContainer = form.closest('.attribute-card').querySelector('.sortable-list');
                const emptyMsg = listContainer.querySelector('.empty-values-msg');
                if(emptyMsg) emptyMsg.remove();

                const newItem = document.createElement('div');
                newItem.className = 'value-item';
                newItem.dataset.id = data.id;
                newItem.dataset.tipo = 'valor';
                newItem.innerHTML = `
                    <div class="value-drag-handle">☰</div>
                    <div class="value-name">
                        <span class="nome-display">${data.valor}</span>
                    </div>
                    <div class="value-actions">
                        <button type="button" class="btn-edit-single btn-editar" title="Editar"></button>
                        <button type="button" class="btn-del-single btn-apagar" title="Eliminar Valor"></button>
                    </div>
                `;
                listContainer.appendChild(newItem);
                form.reset();
            });
        }
    });

    // --- Cliques Globais (Editar / Eliminar) ---
    gestorContainer.addEventListener('click', function(e) {
        const target = e.target;
        
        // 0. MINIMIZAR / EXPANDIR
        const headerInfo = target.closest('.attribute-info');
        if (headerInfo && !target.closest('.toggle-visiveis')) {
            headerInfo.closest('.attribute-card').classList.toggle('collapsed');
            return;
        }

        // 0.1 ORDENAR A-Z
        if (target.classList.contains('btn-sort-alpha')) {
            const listContainer = target.closest('.attribute-card').querySelector('.sortable-list');
            const items = Array.from(listContainer.querySelectorAll('.value-item'));
            items.sort((a, b) => a.querySelector('.nome-display').textContent.trim().localeCompare(b.querySelector('.nome-display').textContent.trim()));
            items.forEach(item => listContainer.appendChild(item));
            
            // Salvar ordem na BD
            const ordemIds = items.map(el => el.dataset.id);
            const fd = new FormData();
            fd.append('ordem', JSON.stringify(ordemIds));
            handleAjaxRequest('ajax_salvar_ordem.php', fd, () => mostrarFeedback('Ordenado por A-Z e guardado!', 'sucesso'));
            return;
        }

        const element = target.closest('[data-id]');
        if (!element) return;

        const id = element.dataset.id;
        const tipo = element.dataset.tipo;

        // 1. EDITAR (Inline)
        if (target.classList.contains('btn-editar')) {
            const nomeSpan = element.querySelector('.nome-display');
            if (!nomeSpan || element.querySelector('.editable-container-wrapper')) return;

            const nomeOriginal = nomeSpan.textContent.trim();
            const wrapper = document.createElement('div');
            wrapper.className = 'editable-container-wrapper';
            wrapper.innerHTML = `
                <div class="editable-container">
                    <input type="text" class="edit-input" value="${nomeOriginal}" maxlength="50">
                    <button type="button" class="save-btn" title="Guardar">✓</button>
                    <button type="button" class="cancel-btn" title="Cancelar">×</button>
                </div>
            `;

            nomeSpan.style.display = 'none';
            nomeSpan.parentNode.insertBefore(wrapper, nomeSpan);
            
            const input = wrapper.querySelector('.edit-input');
            input.select();
            input.focus();

            // Guardar
            wrapper.querySelector('.save-btn').onclick = () => {
                const novoNome = input.value.trim();
                if (novoNome && novoNome !== nomeOriginal) {
                    const fd = new FormData();
                    fd.append('id', id);
                    fd.append('tipo', tipo);
                    fd.append('nome', novoNome);
                    handleAjaxRequest('ajax_editar_atributo.php', fd, () => {
                        nomeSpan.textContent = novoNome;
                    });
                }
                nomeSpan.style.display = '';
                wrapper.remove();
            };

            // Cancelar
            wrapper.querySelector('.cancel-btn').onclick = () => {
                nomeSpan.style.display = '';
                wrapper.remove();
            };

            input.onkeydown = (ev) => {
                if (ev.key === 'Enter') wrapper.querySelector('.save-btn').click();
                if (ev.key === 'Escape') wrapper.querySelector('.cancel-btn').click();
            };
        }

        // 2. ELIMINAR
        if (target.classList.contains('btn-apagar')) {
            const nome = element.querySelector('.nome-display').textContent;
            
            const checkData = new FormData();
            checkData.append('acao', 'verificar_uso');
            checkData.append('id', id);
            checkData.append('tipo', tipo);

            handleAjaxRequest('gerir_atributos.php', checkData, (data) => {
                let msg = `Tem a certeza que quer eliminar ${tipo === 'grupo' ? 'o grupo' : 'o valor'} "${nome}"?`;
                if (data.em_uso) {
                    msg = `ATENÇÃO: Este ${tipo} está a ser usado em ${data.contagem} produto(s). Eliminar irá remover esta informação de todos eles. Continuar?`;
                }

                window.mostrarModalConfirmacao('Confirmar Eliminação', msg, () => {
                    const delData = new FormData();
                    delData.append('acao', `apagar_${tipo}`);
                    delData.append(`id_${tipo}`, id);
                    handleAjaxRequest('gerir_atributos.php', delData, () => element.remove());
                });
            });
        }
    });

});
</script>

<?php include '../templates/footer.php'; ?>
