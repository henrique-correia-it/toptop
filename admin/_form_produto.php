<?php
// --- (O bloco de código PHP no início do ficheiro permanece inalterado) ---
$nome_produto = $produto['nome'] ?? '';
$referencia_produto = $produto['referencia'] ?? '';
$ativo_produto = $produto['ativo'] ?? 1;
$preco_produto = $produto['preco'] ?? '';
$preco_promocional_produto = $produto['preco_promocional'] ?? '';
$categoria_produto = $produto['categoria'] ?? '';

// Buscar categorias visíveis da BD
$_cats_r = $conn->query("SELECT nome FROM categorias ORDER BY ordem ASC, id ASC");
$_cats_db = ($_cats_r !== false && $_cats_r->num_rows > 0) ? $_cats_r->fetch_all(MYSQLI_ASSOC) : [];
// Fallback hardcoded se a tabela ainda não existir ou estiver vazia
if (empty($_cats_db)) {
    $_cats_db = [
        ['nome' => 'Roupa de mulher'],
        ['nome' => 'Acessórios'],
        ['nome' => 'Roupa interior e biquínis'],
        ['nome' => 'Sapatos'],
    ];
}
if ($categoria_produto === '') {
    $categoria_produto = $_cats_db[0]['nome'] ?? 'Roupa de mulher';
}
$descricao_produto = $produto['descricao'] ?? '';
$peso_gramas_produto = isset($produto['peso_gramas']) ? $produto['peso_gramas'] : '';
// --- INÍCIO DA ALTERAÇÃO ---
$guia_tamanho_id = $produto['guia_tamanho_id'] ?? null;
// --- FIM DA ALTERAÇÃO ---
$atributos_guardados_json = $produto['atributos'] ?? '{}';
$imagens_iniciais_json = isset($todas_as_imagens) ? json_encode($todas_as_imagens) : '[]';
$produto_id_para_js = $produto['id'] ?? 0;
$variacoes_guardadas_json = $variacoes_guardadas_json ?? '[]';
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
            <a href="<?php echo $return_url; ?>" class="btn-back-arrow" title="Voltar">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
            <h2><?php echo $titulo_pagina; ?></h2>
        </div>
    </div>

    <form id="formProduto" action="<?php echo $action_url; ?>" method="post" class="admin-form-container" style="max-width: 1200px;" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" id="produto_id" value="<?php echo $produto_id_para_js; ?>">

        <div class="admin-form-grid">
            
            <div class="form-coluna-principal">
                
                <div class="form-card">
                    <div class="form-card-header">
                        <h3>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            Informação Geral
                        </h3>
                    </div>
                    <div class="form-card-body">
                        <div class="form-group">
                            <label for="nome">Nome do Produto</label>
                            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome_produto); ?>" placeholder="Ex: Vestido Floral de Verão" required>
                        </div>
                        <div class="form-group">
                            <label for="descricao">Descrição Detalhada</label>
                            <textarea id="descricao" name="descricao" rows="6" placeholder="Descreva as características, material e estilo do produto..." required><?php echo htmlspecialchars($descricao_produto); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="form-card-header">
                        <h3>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            Galeria de Imagens
                        </h3>
                    </div>
                    <div class="form-card-body">
                        <div class="form-group">
                            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 15px;">A primeira imagem será a capa do produto. Pode carregar até 5 imagens.</p>
                            <input type="file" id="fotosInput" multiple accept="image/*" style="display:none;">
                            <button type="button" onclick="document.getElementById('fotosInput').click();" class="btn-add-full">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; vertical-align: middle;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                Carregar Fotos do Produto
                            </button>
                            <div id="galeriaPreview" class="galeria-preview"></div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="form-coluna-secundaria">

                <div class="form-card">
                    <div class="form-card-header">
                        <h3>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                            Organização
                        </h3>
                    </div>
                    <div class="form-card-body">
                        <div class="form-group">
                            <label for="referencia">Referência Principal (SKU)</label>
                            <input type="text" id="referencia" name="referencia" value="<?php echo htmlspecialchars($referencia_produto); ?>" placeholder="Ex: TOPTOP-001" required>
                        </div>
                        <div class="form-group">
                            <label for="categoria">Categoria</label>
                            <div class="select-wrapper">
                                <select id="categoria" name="categoria" required class="select-estilizado">
                                    <?php foreach ($_cats_db as $_cat): ?>
                                        <option value="<?php echo htmlspecialchars($_cat['nome']); ?>"
                                            <?php if ($categoria_produto == $_cat['nome']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($_cat['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="guia_tamanho_id">Guia de Tamanhos</label>
                            <div class="select-wrapper">
                                <?php
                                $guias = $conn->query("SELECT id, titulo FROM guias_tamanho ORDER BY titulo ASC")->fetch_all(MYSQLI_ASSOC);
                                ?>
                                <select id="guia_tamanho_id" name="guia_tamanho_id" class="select-estilizado">
                                    <option value="">Nenhum guia selecionado</option>
                                    <?php foreach ($guias as $guia): ?>
                                        <option value="<?php echo $guia['id']; ?>" <?php if ($guia_tamanho_id == $guia['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($guia['titulo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="peso_gramas">Peso (gramas)</label>
                            <input type="number" id="peso_gramas" name="peso_gramas" value="<?php echo htmlspecialchars($peso_gramas_produto); ?>" step="1" min="0" placeholder="Ex: 250" required>
                        </div>
                        <div class="form-group">
                            <label for="ativo">Status na Loja</label>
                            <div class="select-wrapper">
                                <select id="ativo" name="ativo" class="select-estilizado">
                                    <option value="1" <?php if ($ativo_produto == 1) echo 'selected'; ?>>Público (Visível)</option>
                                    <option value="0" <?php if ($ativo_produto == 0) echo 'selected'; ?>>Rascunho (Oculto)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="form-card-header">
                        <h3>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            Preços & Venda
                        </h3>
                    </div>
                    <div class="form-card-body">
                        <div class="form-group">
                            <label for="preco">Preço Base (€)</label>
                            <input type="number" id="preco" step="0.01" name="preco" value="<?php echo htmlspecialchars($preco_produto); ?>" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label for="preco_promocional">Preço Promocional (€)</label>
                            <input type="number" id="preco_promocional" step="0.01" name="preco_promocional" value="<?php echo htmlspecialchars($preco_promocional_produto); ?>" placeholder="0.00">
                        </div>
                    </div>
                </div>
                
            </div>
        </div>

        <div class="form-card full-width-card">
            <div class="form-card-header">
                <h3>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    Atributos & Características
                </h3>
            </div>
            <div class="form-card-body">
                <div class="atributos-container" style="border: none; padding: 0; background: none;">
                    <div class="atributos-standard-adder">
                        <div class="select-wrapper">
                            <select id="select-grupo-standard" class="select-estilizado">
                                <option value="">Escolher Atributo Padrão...</option>
                                <?php foreach ($grupos_disponiveis as $grupo): ?>
                                    <option value="<?php echo $grupo['id']; ?>"><?php echo htmlspecialchars($grupo['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" id="btn-add-grupo-standard" class="btn-with-plus btn-with-plus-text">Adicionar Grupo</button>
                    </div>
                    <div id="atributos-standard-container"></div>
                    <hr class="atributos-divisor">
                    <div id="atributos-personalizados-container"></div>
                    <button type="button" id="btn-add-atributo-personalizado" class="btn-add-full btn-with-plus btn-with-plus-text">
                        Adicionar Atributo Personalizado
                    </button>
                </div>
                <input type="hidden" name="atributos_json" id="hidden-atributos-json">
            </div>
        </div>

        <div class="form-card full-width-card">
            <div class="form-card-header">
                <h3>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    Gestão de Stock & Variações
                </h3>
            </div>
            <div class="form-card-body">
                <div id="stock-simples-container">
                    <label for="stock_simples">Disponibilidade</label>
                    <div class="stock-input-wrapper">
                        <input type="number" id="stock_simples" name="stock_simples" min="0" step="1" placeholder="0">
                    </div>
                </div>
                <div id="variacoes-bloco">
                    <div id="variacoes-container" class="variacoes-wrapper" style="padding: 0; border: none; background: none;"></div>
                </div>
            </div>
                <input type="hidden" name="variacoes_json" id="hidden-variacoes-json">
            </div>

        <div class="form-actions">
            <input type="submit" value="<?php echo $texto_botao; ?>">
        </div>
    </form>
</main>

<div id="cropperModal" class="modal-cropper">
    <div class="modal-cropper-conteudo">
        <p>Ajuste a imagem e recorte</p>
        <div class="cropper-container"><img id="imagemParaCortar" src=""></div>
        <button type="button" id="guardarCorteBtn" class="add-btn">Guardar Corte</button>
    </div>
</div>

<script>
    const atributosGuardados = <?php echo $atributos_guardados_json; ?>;
    const imagensIniciais = <?php echo $imagens_iniciais_json; ?>;
    const variacoesGuardadas = <?php echo $variacoes_guardadas_json; ?>;
</script>

<script src="/public/js/multiCropper.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/public/js/multiCropper.js'); ?>"></script>
<script src="/public/js/gestao_atributos.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/public/js/gestao_atributos.js'); ?>"></script>
<script src="/public/js/gestao_variacoes.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/public/js/gestao_variacoes.js'); ?>"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    inicializarMultiCropper(
        'fotosInput', 
        'galeriaPreview', 
        'formProduto', 
        5, 
        imagensIniciais
    );
    
    <?php if (!empty($popupMensagem)): ?>
        mostrarPopup("<?php echo addslashes($popupMensagem); ?>", "<?php echo $popupTipo; ?>");
    <?php endif; ?>

    const form = document.getElementById('formProduto');
    const nomeInput = document.getElementById('nome');
    const referenciaInput = document.getElementById('referencia');
    const produtoIdInput = document.getElementById('produto_id');
    const galeriaPreview = document.getElementById('galeriaPreview');
    const fotosInput = document.getElementById('fotosInput');
    
    const stockSimplesContainer = document.getElementById('stock-simples-container');
    const variacoesBloco = document.getElementById('variacoes-bloco');
    const stockSimplesInput = document.getElementById('stock_simples');

    function toggleStockVisibility() {
        const hasVariationControls = document.querySelector('.variacao-control-checkbox') !== null;
        stockSimplesContainer.style.display = hasVariationControls ? 'none' : 'block';
        variacoesBloco.style.display = hasVariationControls ? 'block' : 'none';
        
        if (variacoesBloco.style.display !== 'none') {
            stockSimplesContainer.style.marginBottom = '0';
        } else {
            stockSimplesContainer.style.marginBottom = '';
        }
    }

    new MutationObserver(toggleStockVisibility).observe(document.getElementById('variacoes-container'), {
      childList: true,
      subtree: true
    });
    toggleStockVisibility();

    if (variacoesGuardadas.length === 1 && Object.keys(variacoesGuardadas[0].atributos).length === 0) {
        stockSimplesInput.value = variacoesGuardadas[0].quantidade ?? '';
    }

    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        nomeInput.setCustomValidity('');
        referenciaInput.setCustomValidity('');
        fotosInput.setCustomValidity('');
        galeriaPreview.classList.remove('invalida');

        const totalImagens = document.querySelectorAll('.preview-item').length;
        if (totalImagens < 1 || totalImagens > 5) {
            fotosInput.setCustomValidity(totalImagens < 1 ? 'Deve enviar pelo menos 1 imagem.' : 'Não pode ter mais de 5 imagens.');
            galeriaPreview.classList.add('invalida');
            form.reportValidity();
            return;
        }

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const formData = new FormData();
        formData.append('nome', nomeInput.value);
        formData.append('referencia', referenciaInput.value);
        formData.append('id', produtoIdInput.value);

        try {
            const response = await fetch('ajax_validar_produto.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.valido === false) {
                const field = data.campo === 'nome' ? nomeInput : referenciaInput;
                field.setCustomValidity(data.mensagem);
                field.reportValidity();
            } else {
                if (typeof window.compilarAtributosParaJSON === 'function') {
                    window.compilarAtributosParaJSON();
                }
                if (stockSimplesContainer.style.display !== 'none') {
                    const stockSimplesValor = stockSimplesInput.value;
                    if (stockSimplesValor === '' || isNaN(parseInt(stockSimplesValor))) {
                        mostrarPopup('Por favor, preencha o campo de Stock.', 'erro');
                        return;
                    }
                    const variacaoSimples = [{
                        atributos: {},
                        quantidade: parseInt(stockSimplesValor),
                        preco: null,
                        referencia: null,
                        imagens_associadas: []
                    }];
                    document.getElementById('hidden-variacoes-json').value = JSON.stringify(variacaoSimples);
                } else if (typeof window.compilarVariacoesParaJSON === 'function') {
                    if (!window.compilarVariacoesParaJSON()) {
                        return;
                    }
                }
                
                form.submit();
            }
        } catch (error) {
            console.error('Erro na validação AJAX:', error);
            mostrarPopup('Não foi possível validar o produto. Verifique a sua ligação.', 'erro');
        }
    });

    nomeInput.addEventListener('input', () => nomeInput.setCustomValidity(''));
    referenciaInput.addEventListener('input', () => referenciaInput.setCustomValidity(''));
    
    new MutationObserver(() => {
        fotosInput.setCustomValidity('');
        galeriaPreview.classList.remove('invalida');
    }).observe(galeriaPreview, { childList: true });

    // Prevenir alteração de valores nos inputs numéricos ao fazer scroll (comportamento nativo indesejado)
    form.addEventListener('wheel', function(e) {
        if (document.activeElement.type === 'number') {
            document.activeElement.blur();
        }
    });
});
</script>
