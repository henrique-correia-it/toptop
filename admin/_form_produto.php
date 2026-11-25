<?php
if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || !isset($conn)) {
    http_response_code(403);
    exit('Acesso negado.');
}
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
$_json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$_atributos_dec = json_decode((string)($produto['atributos'] ?? '{}'), true);
$atributos_guardados_json = json_encode(is_array($_atributos_dec) ? $_atributos_dec : new stdClass(), $_json_flags);
$imagens_iniciais_json = json_encode(isset($todas_as_imagens) && is_array($todas_as_imagens) ? $todas_as_imagens : [], $_json_flags);
$produto_id_para_js = (int)($produto['id'] ?? 0);
$_variacoes_dec = isset($variacoes_guardadas_json) ? json_decode((string)$variacoes_guardadas_json, true) : [];
$variacoes_guardadas_json = json_encode(is_array($_variacoes_dec) ? $_variacoes_dec : [], $_json_flags);
?>

<main class="dashboard-container animate-entry">

    <?php echo ui_page_header($titulo_pagina, ['layout' => 'admin', 'back_url' => $return_url, 'back_title' => 'Voltar']); ?>

    <form id="formProduto" action="<?php echo $action_url; ?>" method="post" class="admin-form-container" style="max-width: 1200px;" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" id="produto_id" value="<?php echo $produto_id_para_js; ?>">

        <div class="admin-form-grid">
            
            <div class="form-coluna-principal">
                
                <?php ob_start(); ?>
                <div class="form-group">
                    <label for="nome">Nome do Produto</label>
                    <?php echo ui_input('nome', ['id' => 'nome', 'value' => $nome_produto, 'placeholder' => 'Ex: Vestido Floral de Verão', 'required' => true]); ?>
                </div>
                <div class="form-group">
                    <label for="descricao">Descrição Detalhada</label>
                    <?php echo ui_textarea('descricao', (string)$descricao_produto, ['id' => 'descricao', 'rows' => 6, 'placeholder' => 'Descreva as características, material e estilo do produto...', 'required' => true]); ?>
                </div>
                <?php
                echo ui_card(ob_get_clean(), [
                    'variant'    => 'form',
                    'title_html' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> Informação Geral',
                ]);
                ?>

                <?php ob_start(); ?>
                <div class="form-group">
                    <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 15px;">A primeira imagem será a capa do produto. Pode carregar até 5 imagens.</p>
                    <input type="file" id="fotosInput" multiple accept="image/*" style="display:none;">
                    <?php echo ui_button('Carregar Fotos do Produto', [
                        'class'   => 'btn-add-full',
                        'onclick' => "document.getElementById('fotosInput').click();",
                        'icon'    => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; vertical-align: middle;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> ',
                    ]); ?>
                    <div id="galeriaPreview" class="galeria-preview"></div>
                </div>
                <?php
                echo ui_card(ob_get_clean(), [
                    'variant'    => 'form',
                    'title_html' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg> Galeria de Imagens',
                ]);
                ?>

            </div>

            <div class="form-coluna-secundaria">

                <?php ob_start(); ?>
                <div class="form-group">
                    <label for="referencia">Referência Principal (SKU)</label>
                    <?php echo ui_input('referencia', ['id' => 'referencia', 'value' => $referencia_produto, 'placeholder' => 'Ex: TOPTOP-001', 'required' => true]); ?>
                </div>
                <div class="form-group">
                    <label for="categoria">Categoria</label>
                    <?php
                    $opcoes_categorias_prod = [];
                    foreach ($_cats_db as $_cat) {
                        $opcoes_categorias_prod[$_cat['nome']] = $_cat['nome'];
                    }
                    if ($categoria_produto !== '' && !isset($opcoes_categorias_prod[$categoria_produto])) {
                        $opcoes_categorias_prod[$categoria_produto] = $categoria_produto;
                    }
                    echo ui_select('categoria', $opcoes_categorias_prod, (string)$categoria_produto, ['id' => 'categoria', 'required' => true]);
                    ?>
                </div>
                
                <div class="form-group">
                    <label for="guia_tamanho_id">Guia de Tamanhos</label>
                    <?php
                    $_guias_r = $conn->query("SELECT id, titulo FROM guias_tamanho ORDER BY titulo ASC");
                    $guias = ($_guias_r !== false && $_guias_r->num_rows > 0) ? $_guias_r->fetch_all(MYSQLI_ASSOC) : [];
                    $opcoes_guias_prod = ['' => 'Nenhum guia selecionado'];
                    foreach ($guias as $guia) {
                        $opcoes_guias_prod[(string)$guia['id']] = $guia['titulo'];
                    }
                    echo ui_select('guia_tamanho_id', $opcoes_guias_prod, (string)($guia_tamanho_id ?? ''), ['id' => 'guia_tamanho_id']);
                    ?>
                </div>
                <div class="form-group">
                    <label for="peso_gramas">Peso (gramas)</label>
                    <?php echo ui_input('peso_gramas', ['type' => 'number', 'id' => 'peso_gramas', 'value' => $peso_gramas_produto, 'step' => '1', 'min' => '0', 'placeholder' => 'Ex: 250', 'required' => true]); ?>
                </div>
                <div class="form-group">
                    <label for="ativo">Status na Loja</label>
                    <?php echo ui_select('ativo', ['1' => 'Público (Visível)', '0' => 'Rascunho (Oculto)'], (string)(int)$ativo_produto, ['id' => 'ativo']); ?>
                </div>
                <?php
                echo ui_card(ob_get_clean(), [
                    'variant'    => 'form',
                    'title_html' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg> Organização',
                ]);
                ?>

                <?php ob_start(); ?>
                <div class="form-group">
                    <label for="preco">Preço Base (€)</label>
                    <?php echo ui_input('preco', ['type' => 'number', 'id' => 'preco', 'step' => '0.01', 'value' => $preco_produto, 'placeholder' => '0.00', 'required' => true]); ?>
                </div>
                <div class="form-group">
                    <label for="preco_promocional">Preço Promocional (€)</label>
                    <?php echo ui_input('preco_promocional', ['type' => 'number', 'id' => 'preco_promocional', 'step' => '0.01', 'value' => $preco_promocional_produto, 'placeholder' => '0.00']); ?>
                </div>
                <?php
                echo ui_card(ob_get_clean(), [
                    'variant'    => 'form',
                    'title_html' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg> Preços & Venda',
                ]);
                ?>
                
            </div>
        </div>

        <?php ob_start(); ?>
        <div class="atributos-container" style="border: none; padding: 0; background: none;">
            <div class="atributos-standard-adder">
                <?php
                $opcoes_grupos_std = ['' => 'Escolher Atributo Padrão...'];
                foreach ($grupos_disponiveis as $grupo) {
                    $opcoes_grupos_std[(string)$grupo['id']] = $grupo['nome'];
                }
                echo ui_select('', $opcoes_grupos_std, '', ['id' => 'select-grupo-standard']);
                echo ui_button('Adicionar Grupo', ['id' => 'btn-add-grupo-standard', 'class' => 'button btn-with-plus btn-with-plus-text']);
                ?>
            </div>
            <div id="atributos-standard-container"></div>
            <hr class="atributos-divisor">
            <div id="atributos-personalizados-container"></div>
            <?php echo ui_button('Adicionar Atributo Personalizado', ['id' => 'btn-add-atributo-personalizado', 'class' => 'button btn-add-full btn-with-plus btn-with-plus-text']); ?>
        </div>
        <input type="hidden" name="atributos_json" id="hidden-atributos-json">
        <?php
        echo ui_card(ob_get_clean(), [
            'variant'    => 'form',
            'class'      => 'full-width-card',
            'title_html' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg> Atributos & Características',
        ]);
        ?>

        <?php ob_start(); ?>
        <div id="variacoes-bloco">
            <div id="variacoes-container" class="variacoes-wrapper"></div>
        </div>
        <div id="stock-simples-container" class="stock-simples-box">
            <div class="stock-simples-copy">
                <label for="stock_simples">Stock Disponível</label>
                <span class="stock-simples-hint">Usado quando o produto não tem variações de stock selecionadas.</span>
            </div>
            <div class="stock-input-wrapper">
                <?php echo ui_input('stock_simples', ['type' => 'number', 'id' => 'stock_simples', 'min' => '0', 'step' => '1', 'placeholder' => '0']); ?>
                <span class="stock-unit">un.</span>
            </div>
        </div>
        <input type="hidden" name="variacoes_json" id="hidden-variacoes-json">
        <?php
        echo ui_card(ob_get_clean(), [
            'variant'    => 'form',
            'class'      => 'full-width-card',
            'title_html' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg> Gestão de Stock & Variações',
        ]);
        ?>

        <div class="form-actions">
            <?php echo ui_button((string)$texto_botao, ['type' => 'submit', 'variant' => 'primary']); ?>
        </div>
    </form>
</main>

<div id="cropperModal" class="modal-cropper" role="dialog" aria-modal="true" aria-labelledby="cropperModalTitle">
    <div class="qe-card modal-cropper-conteudo">
        <div class="qe-hd"><h3 id="cropperModalTitle">Recortar Imagem</h3></div>
        <div class="cropper-container"><img id="imagemParaCortar" src="" alt="Imagem para recortar"></div>
        <div class="qe-btns">
            <?php echo ui_button('Cancelar', ['id' => 'cancelarCorteBtn', 'class' => 'qe-btn-cancel']); ?>
            <?php echo ui_button('Guardar Corte', ['id' => 'guardarCorteBtn', 'variant' => 'primary']); ?>
        </div>
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
        mostrarPopup(<?php echo json_encode($popupMensagem, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($popupTipo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
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
        const hasCheckedVariations = document.querySelector('.variacao-control-checkbox:checked') !== null;
        const hasAnyControls = document.querySelector('.variacao-control-checkbox') !== null;
        variacoesBloco.style.display = hasAnyControls ? 'block' : 'none';
        stockSimplesContainer.style.display = hasCheckedVariations ? 'none' : 'flex';
    }

    document.getElementById('variacoes-container').addEventListener('change', toggleStockVisibility);
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
        const csrfInput = form.querySelector('input[name="csrf_token"]');
        if (csrfInput) {
            formData.append('csrf_token', csrfInput.value);
        }

        try {
            const response = await fetch('ajax_validar_produto.php', { method: 'POST', body: formData });
            if (!response.ok) {
                throw new Error('Erro HTTP ' + response.status);
            }
            const data = await response.json();

            if (data.valido === false) {
                if (data.campo === 'geral') {
                    mostrarPopup(data.mensagem || 'Erro ao validar o produto.', 'erro');
                    return;
                }
                const field = data.campo === 'nome' ? nomeInput : referenciaInput;
                field.setCustomValidity(data.mensagem);
                field.reportValidity();
            } else {
                if (typeof window.compilarAtributosParaJSON === 'function') {
                    window.compilarAtributosParaJSON();
                }
                if (stockSimplesContainer.style.display !== 'none') {
                    const stockSimplesValor = stockSimplesInput.value.trim();
                    const stockInt = Number(stockSimplesValor);
                    if (stockSimplesValor === '' || !Number.isInteger(stockInt) || stockInt < 0) {
                        mostrarPopup('Por favor, preencha o campo de Stock com um número inteiro válido (>= 0).', 'erro');
                        return;
                    }
                    const variacaoSimples = [{
                        atributos: {},
                        quantidade: stockInt,
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
