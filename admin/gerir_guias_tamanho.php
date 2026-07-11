<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

// Segurança: Apenas superadmins e desenvolvedores podem aceder
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    header("Location: /admin/admin.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$guias = $conn->query("SELECT * FROM guias_tamanho ORDER BY titulo ASC")->fetch_all(MYSQLI_ASSOC);

include '../templates/header.php';
?>

<main class="dashboard-container animate-entry guias-premium-content">

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
            <h2>Gerir Guias de Tamanho</h2>
        </div>
    </div>

    <div class="admin-container" style="max-width: 1200px; margin: 0 auto;">

        <div class="guias-container-premium">
            <div class="guias-list-header">
                <div class="search-filter-wrapper">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="filtro-guias" placeholder="Procurar guia por título...">
                </div>
                <button type="button" id="btn-novo-guia" class="button add-btn btn-with-plus btn-with-plus-text" style="margin:0;">Novo Guia</button>
            </div>
            
            <div style="padding: 30px;">
                <div class="guias-grid" id="lista-guias">
            <?php if (empty($guias)): ?>
                <div class="empty-state" style="grid-column: 1/-1; text-align: center; padding: 40px; background: #fff; border-radius: 8px; border: 1px dashed #ccc;">
                    <p>Ainda não foram criados guias de tamanho.</p>
                </div>
            <?php else: ?>
                <?php foreach ($guias as $guia): ?>
                    <div class="guia-card" data-id="<?php echo $guia['id']; ?>">
                        <div class="guia-card-content">
                            <div class="guia-card-header">
                                <div class="guia-card-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                </div>
                                <h3 class="guia-card-title" data-titulo="<?php echo htmlspecialchars($guia['titulo']); ?>" data-conteudo="<?php echo htmlspecialchars($guia['conteudo']); ?>">
                                    <?php echo htmlspecialchars($guia['titulo']); ?>
                                </h3>
                            </div>
                        </div>
                        <div class="guia-card-actions">
                            <button type="button" class="btn-view-single btn-preview-guia" title="Ver Guia"></button>
                            <button type="button" class="btn-edit-single btn-editar-guia" title="Editar Guia"></button>
                            <button type="button" class="btn-del-single btn-apagar-guia" title="Apagar Guia"></button>
                        </div>
                    </div> <!-- fecha guia-card -->
                <?php endforeach; ?>
            <?php endif; ?>
                </div> <!-- fecha guias-grid -->
            </div>
        </div>
    </div>
</main>

<div id="modal-guia" class="qe-modal">
    <div class="qe-card" style="max-width: 850px;">
        <button type="button" class="btn-close-unified qe-close" title="Fechar">&times;</button>
        <h3 id="modal-guia-titulo">Adicionar Novo Guia de Tamanhos</h3>
        <form id="form-guia">
            <input type="hidden" name="id" id="guia-id">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label for="guia-titulo-input">Título</label>
                <input type="text" id="guia-titulo-input" name="titulo" required>
            </div>
            
            <div class="tabela-visual-editor">
                <div class="editor-toolbar">
                    <div class="toolbar-section">
                        <div class="control-group">
                            <span class="control-label">Linhas</span>
                            <div class="control-buttons">
                                <button type="button" id="btn-adicionar-linha" class="btn-ctrl add" title="Adicionar Linha">+</button>
                                <button type="button" id="btn-remover-linha" class="btn-ctrl remove" title="Remover Linha">-</button>
                            </div>
                        </div>
                        <div class="control-group">
                            <span class="control-label">Colunas</span>
                            <div class="control-buttons">
                                <button type="button" id="btn-adicionar-coluna" class="btn-ctrl add" title="Adicionar Coluna">+</button>
                                <button type="button" id="btn-remover-coluna" class="btn-ctrl remove" title="Remover Coluna">-</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="gerador-visual-wrapper">
                    <!-- A tabela será gerada aqui -->
                </div>
            </div>

            <div class="form-group" style="display: none;">
                <label for="guia-conteudo-input">Conteúdo (HTML da Tabela)</label>
                <textarea id="guia-conteudo-input" name="conteudo" rows="10"></textarea>
            </div>

            <div class="qe-footer">
                <button type="button" class="btn-admin-secondary qe-btn-cancel">Cancelar</button>
                <button type="submit" class="btn-admin-primary">Guardar Guia</button>
            </div>
        </form>
    </div>
</div>

<div id="modalGuiaTamanhos" class="qe-modal">
    <div class="qe-card" style="max-width: 850px;">
        <button type="button" class="btn-close-unified qe-close" title="Fechar">&times;</button>
        <h3 id="guia-titulo"></h3>
        <div id="guia-conteudo"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bloco de seletores de elementos
    const modal = document.getElementById('modal-guia');
    const form = document.getElementById('form-guia');
    const modalTitulo = document.getElementById('modal-guia-titulo');
    const guiaIdInput = document.getElementById('guia-id');
    const guiaTituloInput = document.getElementById('guia-titulo-input');
    const guiaConteudoInput = document.getElementById('guia-conteudo-input');
    const listaGuias = document.getElementById('lista-guias');
    const btnNovoGuia = document.getElementById('btn-novo-guia');
    const filtroInput = document.getElementById('filtro-guias');

    // Filtro de pesquisa
    filtroInput.addEventListener('input', function() {
        const termo = this.value.toLowerCase().trim();
        const cards = document.querySelectorAll('.guia-card');
        
        cards.forEach(card => {
            const titulo = card.querySelector('.guia-card-title').textContent.toLowerCase();
            if (titulo.includes(termo)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // Elementos do gerador
    const btnAdicionarLinha = document.getElementById('btn-adicionar-linha');
    const btnRemoverLinha = document.getElementById('btn-remover-linha');
    const btnAdicionarColuna = document.getElementById('btn-adicionar-coluna');
    const btnRemoverColuna = document.getElementById('btn-remover-coluna');
    const geradorVisualWrapper = document.getElementById('gerador-visual-wrapper');

    // Elementos do Modal de Pré-visualização
    const modalPreview = document.getElementById('modalGuiaTamanhos');
    const previewTitulo = document.getElementById('guia-titulo');
    const previewConteudo = document.getElementById('guia-conteudo');
    const btnFecharPreview = modalPreview.querySelector('.qe-close');

    // --- Funções do Editor de Tabela ---

    const gerarTabelaDefault = () => {
        const table = document.createElement('table');
        table.id = 'tabela-visual-gerada';

        const thead = table.createTHead();
        const headerRow = thead.insertRow();
        ['Tamanho', 'Peito (cm)', 'Cintura (cm)'].forEach(text => {
            const th = document.createElement('th');
            th.setAttribute('contenteditable', 'true');
            th.textContent = text;
            headerRow.appendChild(th);
        });

        const tbody = table.createTBody();
        ['S', 'M', 'L'].forEach(size => {
            const row = tbody.insertRow();
            const td1 = row.insertCell();
            td1.setAttribute('contenteditable', 'true');
            td1.textContent = size;
            
            for(let i = 0; i < 2; i++) {
                const td = row.insertCell();
                td.setAttribute('contenteditable', 'true');
                td.textContent = '-';
            }
        });
        
        geradorVisualWrapper.innerHTML = '';
        geradorVisualWrapper.appendChild(table);
        table.addEventListener('input', atualizarHtmlDoTextarea);
        atualizarHtmlDoTextarea();
    };

    const manipularTabela = (acao) => {
        const tabela = document.getElementById('tabela-visual-gerada');
        if (!tabela) return;

        if (acao === 'addLinha') {
            const novaLinha = tabela.tBodies[0].insertRow(-1);
            for (let i = 0; i < tabela.rows[0].cells.length; i++) {
                const novaCelula = novaLinha.insertCell(-1);
                novaCelula.setAttribute('contenteditable', 'true');
                novaCelula.textContent = '...';
            }
        } else if (acao === 'removerLinha' && tabela.rows.length > 2) {
            tabela.deleteRow(-1);
        } else if (acao === 'addColuna') {
            const novoHeader = document.createElement('th');
            novoHeader.setAttribute('contenteditable', 'true');
            novoHeader.textContent = `Titulo ${tabela.rows[0].cells.length + 1}`; // CORREÇÃO AQUI
            tabela.rows[0].appendChild(novoHeader);
            for (let i = 1; i < tabela.rows.length; i++) {
                const novaCelula = tabela.rows[i].insertCell(-1);
                novaCelula.setAttribute('contenteditable', 'true');
                novaCelula.textContent = '...';
            }
        } else if (acao === 'removerColuna' && tabela.rows[0].cells.length > 1) {
            for (let i = 0; i < tabela.rows.length; i++) {
                tabela.rows[i].deleteCell(-1);
            }
        }
        atualizarHtmlDoTextarea();
    };

    const atualizarHtmlDoTextarea = () => {
        const tabelaVisual = document.getElementById('tabela-visual-gerada');
        if (tabelaVisual) {
            const cloneTabela = tabelaVisual.cloneNode(true);
            cloneTabela.removeAttribute('id');
            cloneTabela.querySelectorAll('[contenteditable]').forEach(el => {
                el.removeAttribute('contenteditable');
            });
            guiaConteudoInput.value = cloneTabela.outerHTML;
        } else {
            guiaConteudoInput.value = '';
        }
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
            
            if (tabelaExistente) {
                tabelaExistente.id = 'tabela-visual-gerada';
                // Removemos classes antigas para garantir o estilo novo e limpo
                tabelaExistente.className = ''; 
                tabelaExistente.querySelectorAll('th, td').forEach(cell => {
                    cell.setAttribute('contenteditable', 'true');
                });
                
                geradorVisualWrapper.appendChild(tabelaExistente);
                tabelaExistente.addEventListener('input', atualizarHtmlDoTextarea);
            } else {
                 gerarTabelaDefault();
            }
        } else {
            gerarTabelaDefault();
        }
        modal.classList.add('ativo');
    };

    const fecharModal = () => {
        modal.classList.remove('ativo');
        geradorVisualWrapper.innerHTML = '';
    };

    const abrirPreview = (titulo, conteudo) => {
        previewTitulo.textContent = titulo;
        previewConteudo.innerHTML = conteudo;
        modalPreview.classList.add('ativo');
    };

    const fecharPreview = () => modalPreview.classList.remove('ativo');

    // Event Listeners
    btnNovoGuia.addEventListener('click', () => abrirModal());
    modal.querySelector('.qe-close').addEventListener('click', fecharModal);
    modal.querySelector('.qe-btn-cancel').addEventListener('click', fecharModal);
    btnFecharPreview.addEventListener('click', fecharPreview);
    modalPreview.addEventListener('click', (e) => { if (e.target === modalPreview) fecharPreview(); });

    btnAdicionarLinha.addEventListener('click', () => manipularTabela('addLinha'));
    btnRemoverLinha.addEventListener('click', () => manipularTabela('removerLinha'));
    btnAdicionarColuna.addEventListener('click', () => manipularTabela('addColuna'));
    btnRemoverColuna.addEventListener('click', () => manipularTabela('removerColuna'));
    
    listaGuias.addEventListener('click', function(e) {

        const target = e.target;
        const card = target.closest('.guia-card');
        if (!card) return;
        const id = card.dataset.id;
        const info = card.querySelector('.guia-card-title');

        if (target.closest('.btn-editar-guia')) {
            abrirModal(id, info.dataset.titulo, info.dataset.conteudo);
        } else if (target.closest('.btn-preview-guia')) {
            abrirPreview(info.dataset.titulo, info.dataset.conteudo);
        } else if (target.closest('.btn-apagar-guia')) {
            mostrarModalConfirmacao('Apagar Guia', `Tem a certeza que quer apagar o guia "${info.dataset.titulo}"?`, () => {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                
                fetch('ajax_operations_guias.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.sucesso) {
                        mostrarPopup('Guia apagado com sucesso!', 'sucesso');
                        card.remove();
                    } else {
                        mostrarPopup(data.mensagem, 'erro');
                    }
                }).catch(() => mostrarPopup('Ocorreu um erro de comunicação.', 'erro'));
            });
        }
    });

    // Duplo clique para editar
    listaGuias.addEventListener('dblclick', function(e) {
        const card = e.target.closest('.guia-card');
        if (card) {
            const id = card.dataset.id;
            const info = card.querySelector('.guia-card-title');
            abrirModal(id, info.dataset.titulo, info.dataset.conteudo);
        }
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', guiaIdInput.value ? 'edit' : 'add');
        
        const botao = form.querySelector('button[type="submit"]');
        botao.disabled = true;

        fetch('ajax_operations_guias.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.sucesso) {
                mostrarPopup('Guia guardado com sucesso!', 'sucesso');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                mostrarPopup(data.mensagem, 'erro');
                botao.disabled = false;
            }
        }).catch(() => {
            mostrarPopup('Ocorreu um erro de comunicação.', 'erro');
            botao.disabled = false;
        });
    });
});
</script>

<?php include '../templates/footer.php'; ?>
