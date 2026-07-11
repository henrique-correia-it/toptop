<?php
include __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/url_helpers.php';

// --- PASSO A: Funções e Lógica de Dados (MOVIDO PARA O TOPO) ---
// Extrai o caminho do URL e o ID
$url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$slug_url = basename($url_path);
$partes_slug = explode('-', $slug_url);
$produto_id = (int)end($partes_slug);

$produto = null;
if ($produto_id > 0) {
    $stmt = $conn->prepare("SELECT p.*, (SELECT SUM(pv.quantidade) FROM produto_variacoes pv WHERE pv.produto_id = p.id) as stock_total FROM produtos p WHERE p.id = ? AND p.ativo = 1");
    $stmt->bind_param("i", $produto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $produto = $result->fetch_assoc();
    $stmt->close();
}

// --- PASSO B: Definir o Título para o SEO ---
if ($produto) {
    $titulo_pagina = $produto['nome'];
    $descricao_pagina = strip_tags($produto['descricao']);
} else {
    $titulo_pagina = "Produto não encontrado";
}

// --- PASSO C: Só AGORA incluímos o header ---
include __DIR__ . '/templates/header.php';

// O código continua normalmente a partir daqui (verificação de 404)...

if (!$produto) {
    http_response_code(404);
    echo "<main class='pagina-produto-detalhe' style='text-align:center;'><p style='font-size: 1.2rem;'>Ups! O produto que procura não foi encontrado.</p></main>";
    include __DIR__ . '/templates/footer.php';
    exit;
}

$imagens = [];
$stmt_img = $conn->prepare("SELECT nome_ficheiro FROM produto_imagens WHERE produto_id = ? ORDER BY FIELD(nome_ficheiro, ?) DESC, id ASC");
$stmt_img->bind_param("is", $produto['id'], $produto['foto_principal']);
$stmt_img->execute();
$result_img = $stmt_img->get_result();
while($img_row = $result_img->fetch_assoc()){
    $imagens[] = $img_row['nome_ficheiro'];
}
$fotos_json = htmlspecialchars(json_encode($imagens), ENT_QUOTES, 'UTF-8');
$atributos_json_html = htmlspecialchars($produto['atributos'] ?? '{}', ENT_QUOTES, 'UTF-8');
$stock_para_js = (int)($produto['stock_total'] ?? 0);
$slug_produto = criar_slug($produto['nome'] . '-' . $produto['id']);

// Capturamos todos os parâmetros GET para passar ao JavaScript
$filtros_url = [];
$query_string = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
if ($query_string) {
    parse_str($query_string, $params);
    $filtros_url = $params;
}
$filtros_url_json = htmlspecialchars(json_encode($filtros_url), ENT_QUOTES, 'UTF-8');
?>

<main class="pagina-produto-detalhe"
    data-id="<?php echo $produto['id']; ?>" data-slug="<?php echo $slug_produto; ?>" data-categoria="<?php echo htmlspecialchars($produto['categoria']); ?>"
    data-referencia="<?php echo htmlspecialchars($produto['referencia']); ?>" data-nome="<?php echo htmlspecialchars($produto['nome']); ?>"
    data-preco="<?php echo $produto['preco']; ?>" data-descricao="<?php echo htmlspecialchars($produto['descricao']); ?>"
    data-fotos='<?php echo $fotos_json; ?>' data-preco-promocional="<?php echo $produto['preco_promocional']; ?>"
    data-atributos='<?php echo $atributos_json_html; ?>' data-quantidade="<?php echo $stock_para_js; ?>"
    data-guia-tamanho-id="<?php echo $produto['guia_tamanho_id'] ?? ''; ?>"
    data-peso="<?php echo $produto['peso_gramas'] ?? 0; ?>"
    data-filtros-url='<?php echo $filtros_url_json; ?>'>

    <div class="produto-detalhe-container">
        <div class="modal-galeria">
            <div class="zoom-container">
                <div class="image-loading-spinner"></div>
                <img id="modalImagemPrincipal" src="" alt="Imagem principal do produto">
            </div>
            <div id="modalThumbnails" class="modal-thumbnails">
                </div>
        </div>

        <div class="modal-info">
            <h1 id="modalNome"></h1>
            <p id="modalPreco"></p>
            <div class="produto-meta-info">
                <span id="meta-referencia" class="meta-item" style="display: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    <span class="meta-texto"></span>
                </span>
                <span id="meta-stock" class="meta-item" style="display: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    <span class="meta-texto"></span>
                </span>
            </div>
            
            <div class="detalhes-produto-acordeao">
                <div class="acordeao-item">
                    <button class="acordeao-header">Descrição</button>
                    <div class="acordeao-conteudo">
                        <p id="modalDescricao"></p>
                    </div>
                </div>
            </div>
            <div class="bloco-acoes-compra">
                <div id="variacoesContainer" class="variacoes-container"></div>

                <div class="modal-acoes-principais">
                    <div class="seletor-quantidade" >
                        <label for="modalQuantidade">Quantidade:</label>
                        <div class="input-wrapper">
                            <button type="button" class="btn-qty" data-action="minus">-</button>
                            <input type="number" id="modalQuantidade" value="1" min="1" max="10" readonly>
                            <button type="button" class="btn-qty" data-action="plus">+</button>
                        </div>
                    </div>
                    <button type="button" id="adicionarCarrinhoBtn" class="button add-btn" disabled>A carregar...</button>
                </div>
            </div>
        </div>
    </div>

    <div id="produtosRelacionadosContainer" class="produtos-relacionados-container"></div>
    <script type="application/ld+json">
{
  "@context": "https://schema.org/",
  "@type": "Product",
  "name": "<?php echo htmlspecialchars($produto['nome']); ?>",
  "image": [
    "<?php echo $base_url; ?>/public/assets/img_produtos/<?php echo $produto['foto_principal']; ?>"
   ],
  "description": "<?php echo htmlspecialchars(strip_tags($produto['descricao'])); ?>",
  "sku": "<?php echo htmlspecialchars($produto['referencia']); ?>",
  "offers": {
    "@type": "Offer",
    "url": "<?php echo "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>",
    "priceCurrency": "EUR",
    "price": "<?php echo $produto['preco_promocional'] > 0 ? $produto['preco_promocional'] : $produto['preco']; ?>",
    "availability": "<?php echo ($produto['stock_total'] > 0) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'; ?>"
  }
}
</script>
</main>

<script src="/public/js/modalProduto.js?v=<?php echo filemtime(__DIR__ . '/public/js/modalProduto.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- LÓGICA DO ACORDEÃO DE DESCRIÇÃO ---
    document.querySelectorAll('.acordeao-header').forEach(header => {
        header.addEventListener('click', () => {
            const item = header.parentElement;
            const content = item.querySelector('.acordeao-conteudo');
            item.classList.toggle('ativo');
            if (item.classList.contains('ativo')) {
                // Usa scrollHeight para obter a altura total do conteúdo
                content.style.maxHeight = content.scrollHeight + "px";
            } else {
                content.style.maxHeight = '0px';
            }
        });
    });

    // --- INICIALIZAÇÃO PRINCIPAL ---
    const produtoContainer = document.querySelector('.pagina-produto-detalhe');
    if (produtoContainer) {
        // A função `abrirModalProduto` do script `modalProduto.js` agora vai atuar
        // diretamente nos elementos da página, preenchendo os dados.
        window.abrirModalProduto(produtoContainer);
    }
});
</script>

<?php
include __DIR__ . '/templates/footer.php';
?>
