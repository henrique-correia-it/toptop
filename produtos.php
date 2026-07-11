<?php
include __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/url_helpers.php';
require_once __DIR__ . '/config/formatters.php';

$is_ajax_request = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// --- LÓGICA PARA BUSCAR OS FILTROS (só no carregamento inicial) ---
if (!$is_ajax_request) {
    // Categorias visíveis da tabela de categorias (fallback para DISTINCT nos produtos)
    $cats_r = $conn->query("SELECT c.nome AS categoria FROM categorias c WHERE EXISTS (SELECT 1 FROM produtos p WHERE p.categoria COLLATE utf8mb4_unicode_ci = c.nome COLLATE utf8mb4_unicode_ci AND p.ativo = 1 AND EXISTS (SELECT 1 FROM produto_variacoes pv WHERE pv.produto_id = p.id AND pv.quantidade > 0)) ORDER BY c.ordem ASC, c.id ASC");
    if ($cats_r !== false && $cats_r->num_rows > 0) {
        $categorias = $cats_r->fetch_all(MYSQLI_ASSOC);
    } else {
        $categorias_result = $conn->query("SELECT DISTINCT categoria FROM produtos p WHERE categoria IS NOT NULL AND categoria != '' AND ativo = 1 AND EXISTS (SELECT 1 FROM produto_variacoes pv WHERE pv.produto_id = p.id AND pv.quantidade > 0) ORDER BY categoria ASC");
        $categorias = $categorias_result ? $categorias_result->fetch_all(MYSQLI_ASSOC) : [];
    }
    $atributos_existentes_result = $conn->query("SELECT atributos FROM produtos p WHERE ativo = 1 AND atributos IS NOT NULL AND JSON_VALID(atributos) AND EXISTS (SELECT 1 FROM produto_variacoes pv WHERE pv.produto_id = p.id AND pv.quantidade > 0)");
    $filtros_existentes = [];
    if ($atributos_existentes_result) {
        while ($row = $atributos_existentes_result->fetch_assoc()) {
            $atributos_produto = json_decode($row['atributos'], true);
            if (is_array($atributos_produto)) {
                foreach ($atributos_produto as $grupo => $valores) {
                    if (is_array($valores)) {
                        foreach ($valores as $valor) {
                            $filtros_existentes[$grupo][$valor] = true;
                        }
                    }
                }
            }
        }
    }
    $ordem_mestra_result = $conn->query("SELECT g.nome as grupo_nome, v.valor FROM atributos_grupos g JOIN atributos_valores v ON g.id = v.grupo_id WHERE g.e_filtravel = 1 ORDER BY g.nome ASC, v.ordem ASC, v.valor ASC");
    $filtros_disponiveis = [];
    if ($ordem_mestra_result) {
        while ($row = $ordem_mestra_result->fetch_assoc()) {
            if (isset($filtros_existentes[$row['grupo_nome']][$row['valor']])) {
                $filtros_disponiveis[$row['grupo_nome']][] = $row['valor'];
            }
        }
    }
}

// --- LÓGICA DE PESQUISA, FILTRO E PAGINAÇÃO (OTIMIZADA E CORRIGIDA) ---
$resultados_por_pagina = 12;
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset = ($pagina_atual - 1) * $resultados_por_pagina;

$termo_pesquisa = trim($_GET['q'] ?? '');
$promocao_selecionada = $_GET['promocao'] ?? '';
$categorias_selecionadas = $_GET['categorias'] ?? [];
$atributos_selecionados = $_GET['atributos'] ?? [];
$ordenar_selecionado = $_GET['ordenar'] ?? '';

// CONSTRUÇÃO DA QUERY OTIMIZADA
$sql_base = "FROM produtos p";
$condicoes = [
    "p.ativo = 1",
    "EXISTS (SELECT 1 FROM produto_variacoes pv_stock WHERE pv_stock.produto_id = p.id AND pv_stock.quantidade > 0)"
];
$params = [];
$types = "";

if (!empty($termo_pesquisa)) {
    $condicoes[] = "(p.nome LIKE ? OR p.descricao LIKE ? OR p.referencia LIKE ?)";
    $termo_like = "%" . $termo_pesquisa . "%";
    array_push($params, $termo_like, $termo_like, $termo_like);
    $types .= 'sss';
}
if (!empty($promocao_selecionada)) {
    $condicoes[] = "p.preco_promocional IS NOT NULL AND p.preco_promocional > 0";
}
if (!empty($categorias_selecionadas)) {
    $placeholders = implode(',', array_fill(0, count($categorias_selecionadas), '?'));
    $condicoes[] = "p.categoria IN ($placeholders)";
    foreach ($categorias_selecionadas as $cat) {
        $params[] = $cat;
        $types .= 's';
    }
}

if (!empty($atributos_selecionados)) {
    foreach ($atributos_selecionados as $grupo => $valores) {
        if (!empty($valores)) {
            $group_conditions = [];
            foreach ($valores as $valor) {
                // JSON_CONTAINS procura se o *valor* existe dentro do array JSON do grupo
                $group_conditions[] = "JSON_CONTAINS(p.atributos, JSON_QUOTE(?), ?)";
                $params[] = $valor;
                // O caminho JSON para o grupo, por exemplo '$.Cor'
                $params[] = '$.' . '"' . $grupo . '"';
                $types .= 'ss';
            }
            // Dentro de um grupo, a lógica é OU (ex: Cor = Azul OU Cor = Vermelho)
            $condicoes[] = "(" . implode(' OR ', $group_conditions) . ")";
        }
    }
}


$where_clause = " WHERE " . implode(' AND ', $condicoes);

// OBTER O TOTAL DE RESULTADOS
$stmt_count = $conn->prepare("SELECT COUNT(DISTINCT p.id) as total " . $sql_base . $where_clause);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_resultados = $stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_resultados / $resultados_por_pagina);
$stmt_count->close();

// ORDENAÇÃO — construir FIELD() dinâmico com ordem das categorias da BD
$_cats_order_r = $conn->query("SELECT nome FROM categorias ORDER BY ordem ASC, id ASC");
if ($_cats_order_r !== false && $_cats_order_r->num_rows > 0) {
    $_cats_order = array_column($_cats_order_r->fetch_all(MYSQLI_ASSOC), 'nome');
} else {
    $_cats_order = ['Roupa de mulher', 'Acessórios', 'Roupa interior e biquínis', 'Sapatos'];
}
$_field_cats  = implode(', ', array_map(fn($c) => "'" . $conn->real_escape_string($c) . "'", $_cats_order));
$_field_expr  = empty($_cats_order) ? '0' : "FIELD(p.categoria, $_field_cats)";

switch ($ordenar_selecionado) {
    case 'asc':
        $order_by_clause = " ORDER BY (CASE WHEN p.preco_promocional > 0 THEN p.preco_promocional ELSE p.preco END) ASC, p.id DESC";
        break;
    case 'desc':
        $order_by_clause = " ORDER BY (CASE WHEN p.preco_promocional > 0 THEN p.preco_promocional ELSE p.preco END) DESC, p.id DESC";
        break;
    case 'recentes':
        $order_by_clause = " ORDER BY p.id DESC";
        break;
    case 'categoria':
        $order_by_clause = " ORDER BY $_field_expr, p.id DESC";
        break;
    default:
        $order_by_clause = " ORDER BY
            (CASE WHEN p.preco_promocional > 0 THEN 0 ELSE 1 END) ASC,
            $_field_expr,
            p.id DESC";
        break;
}

// BUSCAR PRODUTOS PARA A PÁGINA ATUAL
$produtos_finais = [];
if ($total_resultados > 0) {
    // Adicionámos uma subconsulta para ir buscar o ID da variação base para produtos simples
    $query_final = "SELECT DISTINCT p.*,
        (SELECT SUM(pv.quantidade) FROM produto_variacoes pv WHERE pv.produto_id = p.id) as stock_total,
        (SELECT pv.id FROM produto_variacoes pv WHERE pv.produto_id = p.id AND pv.atributos = '{}') as base_variacao_id "
        . $sql_base . $where_clause . $order_by_clause . " LIMIT ? OFFSET ?";

    $params_paginacao = $params;
    $types_paginacao = $types . "ii";
    $params_paginacao[] = $resultados_por_pagina;
    $params_paginacao[] = $offset;

    $stmt_produtos = $conn->prepare($query_final);
    $stmt_produtos->bind_param($types_paginacao, ...$params_paginacao);
    $stmt_produtos->execute();
    $produtos_finais = $stmt_produtos->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_produtos->close();
}


// --- FUNÇÃO PARA GERAR PAGINAÇÃO (usada apenas no AJAX) ---
function gerarPaginacaoAjax($pagina_atual, $total_paginas)
{
    if ($total_paginas <= 1)
        return '';
    $html = '<nav class="pagination-container"><ul class="pagination-list">';
    
    // Anterior
    $html .= '<li class="pagination-item prev ' . ($pagina_atual <= 1 ? 'disabled' : '') . '">
                <a class="page-link" href="#" data-pagina="' . ($pagina_atual - 1) . '" title="Anterior">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </a></li>';

    for ($i = 1; $i <= $total_paginas; $i++) {
        $html .= '<li class="pagination-item ' . ($i == $pagina_atual ? 'active' : '') . '"><a class="page-link" href="#" data-pagina="' . $i . '">' . $i . '</a></li>';
    }

    // Seguinte
    $html .= '<li class="pagination-item next ' . ($pagina_atual >= $total_paginas ? 'disabled' : '') . '">
                <a class="page-link" href="#" data-pagina="' . ($pagina_atual + 1) . '" title="Seguinte">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </a></li>';

    $html .= '</ul></nav>';
    return $html;
}

// --- RENDERIZAÇÃO ---
if ($is_ajax_request) {
    ob_start();
}

// O HTML dos produtos e da ordenação é gerado aqui
ob_start(); // Inicia um novo buffer para o HTML dos produtos
?>
<?php if (!empty($produtos_finais)): ?>
    <div class="cabecalho-grelha">
        <div class="quick-filters desktop-only-flex">
            <label class="switch-premium" title="Mostrar apenas produtos em promoção">
                <input type="checkbox" class="promocao-toggle-sync" data-target="header" value="1" <?php echo !empty($promocao_selecionada) ? 'checked' : ''; ?>>
                <span class="slider"></span>
                <span class="label-text">Só Promoções</span>
            </label>
        </div>
        <div class="ordenar-container">
            <label for="ordenar">Ordenar por:</label>
            <div class="select-wrapper">
                <select name="ordenar" id="ordenar" class="select-estilizado">
                    <option value="" <?php if ($ordenar_selecionado == '') echo 'selected'; ?>>Destaques</option>
                    <option value="categoria" <?php if ($ordenar_selecionado == 'categoria') echo 'selected'; ?>>Por Categoria</option>
                    <option value="recentes" <?php if ($ordenar_selecionado == 'recentes') echo 'selected'; ?>>Mais Recentes</option>
                    <option value="asc" <?php if ($ordenar_selecionado == 'asc') echo 'selected'; ?>>Preço Crescente</option>
                    <option value="desc" <?php if ($ordenar_selecionado == 'desc') echo 'selected'; ?>>Preço Decrescente</option>
                </select>
            </div>
        </div>
    </div>
    <div class="produtos-grid">
        <?php
        $product_ids = array_column($produtos_finais, 'id');
        $imagens_por_produto = [];
        $variations_with_images_by_product_id = [];

        if (!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $types_ids = str_repeat('i', count($product_ids));

            $stmt_img = $conn->prepare("SELECT produto_id, nome_ficheiro FROM produto_imagens WHERE produto_id IN ($placeholders) ORDER BY produto_id, FIELD(nome_ficheiro, (SELECT foto_principal FROM produtos WHERE id = produto_id)) DESC, id ASC");
            $stmt_img->bind_param($types_ids, ...$product_ids);
            $stmt_img->execute();
            $result_img = $stmt_img->get_result();
            while ($img_row = $result_img->fetch_assoc()) {
                $imagens_por_produto[$img_row['produto_id']][] = $img_row['nome_ficheiro'];
            }
            $stmt_img->close();

            // SÓ VAI BUSCAR AS VARIAÇÕES COM IMAGEM SE HOUVER FILTROS ATIVOS
            if (!empty($atributos_selecionados)) {
                $sql_variations = "
                    SELECT pv.produto_id, pv.atributos, pi.nome_ficheiro
                    FROM produto_variacoes AS pv
                    JOIN variacao_imagens AS vi ON pv.id = vi.variacao_id
                    JOIN produto_imagens AS pi ON vi.imagem_id = pi.id
                    WHERE pv.produto_id IN ($placeholders)
                ";
                $stmt_vars = $conn->prepare($sql_variations);
                $stmt_vars->bind_param($types_ids, ...$product_ids);
                $stmt_vars->execute();
                $result_vars = $stmt_vars->get_result();
                while ($var_row = $result_vars->fetch_assoc()) {
                    $attrs = json_decode($var_row['atributos'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $variations_with_images_by_product_id[$var_row['produto_id']][] = [
                            'atributos' => $attrs,
                            'imagem' => $var_row['nome_ficheiro']
                        ];
                    }
                }
                $stmt_vars->close();
            }
        }
        $filtros_de_atributos_ativos = !empty(array_filter($atributos_selecionados));

        foreach ($produtos_finais as $row):
            $cards_para_renderizar = [];

            if ($filtros_de_atributos_ativos && isset($variations_with_images_by_product_id[$row['id']])) {
                $variacoes_do_produto = $variations_with_images_by_product_id[$row['id']];

                $variacoes_por_imagem = [];
                foreach ($variacoes_do_produto as $variacao) {
                    if (empty($variacao['imagem']))
                        continue;

                    $corresponde_a_todos_os_grupos = true;
                    foreach ($atributos_selecionados as $grupo_filtro => $valores_filtro) {
                        if (empty($valores_filtro))
                            continue;
                        if (!isset($variacao['atributos'][$grupo_filtro]) || !in_array($variacao['atributos'][$grupo_filtro], $valores_filtro)) {
                            $corresponde_a_todos_os_grupos = false;
                            break;
                        }
                    }

                    if ($corresponde_a_todos_os_grupos) {
                        $variacoes_por_imagem[$variacao['imagem']][] = $variacao;
                    }
                }

                foreach ($variacoes_por_imagem as $imagem => $variacoes_com_esta_imagem) {
                    if (empty($variacoes_com_esta_imagem))
                        continue;

                    $primeira_variacao_attrs = $variacoes_com_esta_imagem[0]['atributos'];
                    $atributos_comuns = [];
                    foreach ($primeira_variacao_attrs as $attr => $valor) {
                        $e_comum = true;
                        foreach ($variacoes_com_esta_imagem as $v) {
                            if (!isset($v['atributos'][$attr]) || $v['atributos'][$attr] !== $valor) {
                                $e_comum = false;
                                break;
                            }
                        }
                        if ($e_comum) {
                            $atributos_comuns[$attr] = $valor;
                        }
                    }

                    $cards_para_renderizar[] = [
                        'foto_principal' => $imagem,
                        'foto_secundaria' => null,
                        'atributos_link' => $atributos_comuns
                    ];
                }

            }

            if (empty($cards_para_renderizar)) {
                $cards_para_renderizar[] = [
                    'foto_principal' => $imagens_por_produto[$row['id']][0] ?? 'default.jpg',
                    'foto_secundaria' => $filtros_de_atributos_ativos ? null : ($imagens_por_produto[$row['id']][1] ?? null),
                    'atributos_link' => []
                ];
            }

            foreach ($cards_para_renderizar as $card_data):
                $foto_principal = $card_data['foto_principal'];
                $foto_secundaria = $card_data['foto_secundaria'];
                $slug = criar_slug($row['nome'] . '-' . $row['id']);

                $link_produto = "/produto/" . $slug;
                if (!empty($card_data['atributos_link'])) {
                    $query_params = http_build_query($card_data['atributos_link']);
                    $link_produto .= '?' . $query_params;
                }

                $stock_para_js = (int) ($row['stock_total'] ?? 0);
                $esgotado = ($stock_para_js <= 0);
                $tag_principal = $esgotado ? 'div' : 'a';
                $link_atributo = !$esgotado ? 'href="' . $link_produto . '"' : '';
                ?>
                <<?php echo $tag_principal; ?>             <?php echo $link_atributo; ?> class="produto
                    <?php echo $esgotado ? 'esgotado' : ''; ?>"
                    data-id="<?php echo $row['id']; ?>"
                    data-slug="<?php echo $slug; ?>"
                    data-categoria="<?php echo htmlspecialchars($row['categoria']); ?>"
                    data-referencia="<?php echo htmlspecialchars($row['referencia']); ?>"
                    data-nome="<?php echo htmlspecialchars($row['nome']); ?>"
                    data-preco="<?php echo $row['preco']; ?>"
                    data-descricao="<?php echo htmlspecialchars($row['descricao']); ?>"
                    data-fotos='<?php echo htmlspecialchars(json_encode($imagens_por_produto[$row['id']] ?? []), ENT_QUOTES, 'UTF-8'); ?>'
                    data-preco-promocional="<?php echo $row['preco_promocional']; ?>"
                    data-atributos='<?php echo htmlspecialchars($row['atributos'] ?? '{}', ENT_QUOTES, 'UTF-8'); ?>'
                    data-quantidade="<?php echo $stock_para_js; ?>"
                    data-base-variacao-id="<?php echo $row['base_variacao_id'] ?? ''; ?>"
                    data-guia-tamanho-id="<?php echo $row['guia_tamanho_id'] ?? ''; ?>"
                    data-peso="<?php echo $row['peso_gramas'] ?? 0; ?>"
                    data-filtros-ativos="<?php echo $filtros_de_atributos_ativos ? 'true' : 'false'; ?>">

                    <?php if (!empty($row['preco_promocional']) && $row['preco_promocional'] > 0): ?><span
                            class="badge promocao">Promoção</span><?php endif; ?>
                    <div class="produto-imagem-container">
                        <img src="/public/images/<?php echo htmlspecialchars($foto_principal); ?>"
                            alt="<?php echo htmlspecialchars($row['nome']); ?>" class="imagem-principal" loading="lazy">
                        <?php if ($foto_secundaria && $foto_secundaria !== $foto_principal): ?><img
                                src="/public/images/<?php echo htmlspecialchars($foto_secundaria); ?>"
                                alt="<?php echo htmlspecialchars($row['nome']); ?>" class="imagem-secundaria"
                                loading="lazy"><?php endif; ?>
                        <?php if ($esgotado): ?>
                            <div class="badge-esgotado-overlay">Esgotado</div>
                        <?php else: ?><span role="button" class="btn-adicionar-rapido"
                                data-tem-variacoes="<?php echo (!empty($row['atributos']) && $row['atributos'] !== '{}') ? '1' : '0'; ?>"><?php echo (!empty($row['atributos']) && $row['atributos'] !== '{}') ? 'Ver Opções' : 'Adicionar Rápido'; ?></span><?php endif; ?>
                    </div>
                    <div class="produto-info">
                        <h4><?php echo htmlspecialchars($row['nome']); ?></h4>
                        <?php if (!empty($row['preco_promocional']) && $row['preco_promocional'] > 0): ?>
                            <p class="preco-promocao"><del><?php echo format_money($row['preco']); ?></del>
                                <strong><?php echo format_money($row['preco_promocional']); ?></strong></p>
                        <?php else: ?>
                            <p><?php echo format_money($row['preco']); ?></p><?php endif; ?>
                    </div>
                </<?php echo $tag_principal; ?>>
            <?php endforeach; endforeach; ?>
    </div>
<?php else: ?>
    <p style="text-align:center;width:100%;padding:40px 0;">Não foram encontrados produtos com os critérios selecionados.
    </p><?php endif; ?>

<?php
$html_produtos = ob_get_clean();

if ($is_ajax_request) {
    $html_paginacao = gerarPaginacaoAjax($pagina_atual, $total_paginas);
    header('Content-Type: application/json');
    echo json_encode(['html_produtos' => $html_produtos, 'html_paginacao' => $html_paginacao]);
    exit;
}

// O resto do HTML só é renderizado no carregamento inicial da página
$total_filtros_ativos = (!empty($promocao_selecionada) ? 1 : 0) + count($categorias_selecionadas);
foreach ($atributos_selecionados as $valores_ativos) {
    $total_filtros_ativos += is_array($valores_ativos) ? count($valores_ativos) : 0;
}
// --- SEO ---
if (!empty($termo_pesquisa)) {
    $titulo_pagina = 'Pesquisa: ' . $termo_pesquisa;
    $descricao_pagina = 'Resultados para "' . $termo_pesquisa . '" na TopTop.';
    $noindex = true;
    $canonical_url = '/produtos.php?q=' . rawurlencode($termo_pesquisa);
} elseif (!empty($promocao_selecionada)) {
    $titulo_pagina = 'Promoções';
    $descricao_pagina = 'Descobre as promoções TopTop. Roupa de mulher e acessórios com desconto, com envio rápido para Portugal.';
    $canonical_url = '/produtos.php?promocao=1';
} elseif (count($categorias_selecionadas) === 1 && empty($atributos_selecionados)) {
    $cat_nome = $categorias_selecionadas[0];
    $titulo_pagina = $cat_nome;
    $descricao_pagina = 'Explora ' . $cat_nome . ' na TopTop. Moda feminina com envio rápido para Portugal.';
    $canonical_url = '/produtos.php?categorias%5B%5D=' . rawurlencode($cat_nome);
} else {
    $titulo_pagina = 'Produtos';
    $descricao_pagina = 'Explora toda a coleção TopTop: roupa de mulher, acessórios e muito mais. Envio rápido para Portugal.';
    $canonical_url = '/produtos.php';
}
if ($pagina_atual > 1) {
    $canonical_url .= (strpos($canonical_url, '?') !== false ? '&' : '?') . 'pagina=' . $pagina_atual;
}
$_pag_base = preg_replace('/([?&])pagina=\d+/', '', $canonical_url);
$_pag_base = rtrim($_pag_base, '?&');
$head_extra = '';
if ($pagina_atual > 1) {
    $_prev = $pagina_atual === 2 ? $_pag_base : $_pag_base . (strpos($_pag_base, '?') !== false ? '&' : '?') . 'pagina=' . ($pagina_atual - 1);
    $head_extra .= '    <link rel="prev" href="https://www.toptop.pt' . htmlspecialchars($_prev, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
if ($pagina_atual < $total_paginas) {
    $_next = $_pag_base . (strpos($_pag_base, '?') !== false ? '&' : '?') . 'pagina=' . ($pagina_atual + 1);
    $head_extra .= '    <link rel="next" href="https://www.toptop.pt' . htmlspecialchars($_next, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
include 'templates/header.php';
?>
<main class="pagina-produtos animate-entry">
    <div class="loja-header">
        <p class="loja-kicker"><?php echo !empty($termo_pesquisa) ? 'Pesquisa' : 'Catálogo'; ?></p>
        <h2><?php echo !empty($termo_pesquisa) ? 'Resultados para "' . htmlspecialchars($termo_pesquisa) . '"' : 'Produtos'; ?>
        </h2>
    </div>
    <div class="mobile-filter-bar">
        <button type="button" class="botao-filtros-mobile" aria-controls="filtros-produtos" aria-expanded="false"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                viewBox="0 0 24 24">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
            </svg><span>Filtrar e ordenar</span></button>
    </div>
    <div class="loja-container">
        <aside id="filtros-produtos" class="filtros-sidebar" aria-label="Filtros de produtos">
            <div class="filtros-mobile-header">
                <h3>Filtrar e ordenar</h3>
            </div>

            <form id="form-filtros" method="GET">
                <input type="hidden" name="q" value="<?php echo htmlspecialchars($termo_pesquisa); ?>">
                <!-- Checkbox real que controla o filtro -->
                <input type="checkbox" name="promocao" id="promocao-real" value="1" <?php echo !empty($promocao_selecionada) ? 'checked' : ''; ?> style="display: none;">

                <div class="sidebar-promo-toggle mobile-only-flex">
                    <label class="switch-premium">
                        <input type="checkbox" class="promocao-toggle-sync" data-target="sidebar" value="1" <?php echo !empty($promocao_selecionada) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                        <span class="label-text">Só Promoções</span>
                    </label>
                </div>
                
                <div class="filtro-sort-mobile">
                    <label for="ordenar-mobile">Ordenar por</label>
                    <div class="select-wrapper">
                        <select id="ordenar-mobile" class="select-estilizado">
                            <option value="" <?php if ($ordenar_selecionado == '') echo 'selected'; ?>>Destaques</option>
                            <option value="categoria" <?php if ($ordenar_selecionado == 'categoria') echo 'selected'; ?>>Por Categoria</option>
                            <option value="recentes" <?php if ($ordenar_selecionado == 'recentes') echo 'selected'; ?>>Mais Recentes</option>
                            <option value="asc" <?php if ($ordenar_selecionado == 'asc') echo 'selected'; ?>>Preço Crescente</option>
                            <option value="desc" <?php if ($ordenar_selecionado == 'desc') echo 'selected'; ?>>Preço Decrescente</option>
                        </select>
                    </div>
                </div>

                <div class="sidebar-desktop-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    <span>Filtrar por</span>
                </div>
                
                <?php if (!empty($categorias)): ?>
                    <div class="filtro-grupo">
                        <h3 class="filtro-titulo">Categoria <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"></polyline></svg></h3>
                        <div class="filtro-conteudo">
                            <div class="filtro-conteudo-inner">
                                <ul class="lista-filtros">
                                    <?php foreach ($categorias as $cat):
                                        $cat_nome = htmlspecialchars($cat['categoria']); ?>
                                        <li>
                                            <label class="filtro-checkbox">
                                                <input type="checkbox" name="categorias[]" value="<?php echo $cat_nome; ?>" <?php echo in_array($cat['categoria'], $categorias_selecionadas) ? 'checked' : ''; ?>>
                                                <span class="custom-checkbox"></span>
                                                <span class="filtro-label-text"><?php echo $cat_nome; ?></span>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach ($filtros_disponiveis as $nome_grupo => $valores): ?>
                    <div class="filtro-grupo">
                        <h3 class="filtro-titulo"><?php echo htmlspecialchars($nome_grupo); ?> <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"></polyline></svg></h3>
                        <div class="filtro-conteudo">
                            <div class="filtro-conteudo-inner">
                                <ul class="lista-filtros">
                                    <?php foreach ($valores as $valor):
                                        $valor_nome = htmlspecialchars($valor); ?>
                                        <li>
                                            <label class="filtro-checkbox">
                                                <input type="checkbox" name="atributos[<?php echo htmlspecialchars($nome_grupo); ?>][]" value="<?php echo $valor_nome; ?>" <?php echo (isset($atributos_selecionados[$nome_grupo]) && in_array($valor, $atributos_selecionados[$nome_grupo])) ? 'checked' : ''; ?>>
                                                <span class="custom-checkbox"></span>
                                                <span class="filtro-label-text"><?php echo $valor_nome; ?></span>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="filtro-acoes">
                    <a href="/produtos" class="link-limpar">Limpar Filtros</a>
                </div>
            </form>
        </aside>
        <div id="produtos-container">
            <div id="produtos-grid-container">
                <?php echo $html_produtos; // Renderiza o HTML dos produtos que já foi gerado ?>
            </div>
            <div id="paginacao-container">
                <?php echo gerarPaginacaoAjax($pagina_atual, $total_paginas); // Renderiza a paginação inicial ?>
            </div>
        </div>
    </div>
</main>

<div id="produtoModal" class="modal">
    <div class="modal-conteudo">
        <button type="button" class="btn-close-unified fechar" title="Fechar">&times;</button>

        <div class="modal-loading-overlay">
            <div class="spinner"></div>
        </div>

        <div class="modal-grid-conteudo">
            <div class="modal-galeria">
                <div class="zoom-container"><img id="modalImagemPrincipal" src=""></div>
                <div id="modalThumbnails" class="modal-thumbnails"></div>
            </div>

            <div class="modal-info">
                <h1 id="modalNome"></h1>
                <p id="modalPreco"></p>
                <div class="produto-meta-info">
                    <span id="meta-referencia" class="meta-item" style="display: none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z">
                            </path>
                            <line x1="7" y1="7" x2="7.01" y2="7"></line>
                        </svg>
                        <span class="meta-texto"></span>
                    </span>
                    <span id="meta-stock" class="meta-item" style="display: none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z">
                            </path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
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
                        <div class="seletor-quantidade">
                            <label for="modalQuantidade">Quantidade:</label>
                            <div class="input-wrapper">
                                <button type="button" class="btn-qty" data-action="minus">-</button>
                                <input type="number" id="modalQuantidade" value="1" min="1" max="10" readonly>
                                <button type="button" class="btn-qty" data-action="plus">+</button>
                            </div>
                        </div>
                        <button type="button" id="adicionarCarrinhoBtn" class="button add-btn" disabled>A
                            carregar...</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="produtos-relacionados-container modal-related-footer"></div>
    </div>
</div>

<script
    src="/public/js/modalProduto.js?v=<?php echo filemtime(__DIR__ . '/public/js/modalProduto.js'); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('produtoModal');
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target.classList.contains('acordeao-header')) {
                    const header = e.target;
                    const item = header.parentElement;
                    const content = item.querySelector('.acordeao-conteudo');
                    item.classList.toggle('ativo');
                    if (item.classList.contains('ativo')) {
                        content.style.maxHeight = content.scrollHeight + "px";
                    } else {
                        content.style.maxHeight = '0px';
                    }
                }
            });
        }

        const formFiltros = document.getElementById('form-filtros');
        const produtosContainer = document.getElementById('produtos-container');
        const produtosGridContainer = document.getElementById('produtos-grid-container');
        const paginacaoContainer = document.getElementById('paginacao-container');

        const promoToggles = document.querySelectorAll('.promocao-toggle-sync');
        const botaoFiltrosMobile = document.querySelector('.botao-filtros-mobile');

        function atualizarProdutos(pagina = 1) {
            const formData = new FormData(formFiltros);

            const ordenarMobileSelect = document.getElementById('ordenar-mobile');
            const ordenarDesktopSelect = document.getElementById('ordenar');
            const usarOrdenarMobile = ordenarMobileSelect && window.matchMedia('(max-width: 768px)').matches;
            const ordenarValue = usarOrdenarMobile ? ordenarMobileSelect.value : (ordenarDesktopSelect ? ordenarDesktopSelect.value : 'categoria');
            formData.set('ordenar', ordenarValue);
            formData.append('pagina', pagina);

            const params = new URLSearchParams(formData);
            history.pushState(null, '', '?' + params.toString().replace(/%5B/g, '[').replace(/%5D/g, ']'));

            produtosGridContainer.style.opacity = '0.5';
            paginacaoContainer.style.opacity = '0.5';

            fetch('<?php echo $_SERVER["PHP_SELF"]; ?>?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.json())
                .then(data => {
                    produtosGridContainer.innerHTML = data.html_produtos;
                    paginacaoContainer.innerHTML = data.html_paginacao;
                    produtosGridContainer.style.opacity = '1';
                    paginacaoContainer.style.opacity = '1';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    if (typeof window.ativarTooltipsProfissionais === 'function') {
                        window.ativarTooltipsProfissionais();
                    }
                })
                .catch(error => {
                    console.error('Erro ao atualizar os produtos:', error);
                    produtosGridContainer.style.opacity = '1';
                    paginacaoContainer.style.opacity = '1';
                });
        }

        if (formFiltros && produtosContainer) {
            // Ouvir mudanças no formulário para categorias e atributos
            formFiltros.addEventListener('change', (e) => {
                // Toggles de promoção são tratados pela delegação global abaixo
                if (e.target.classList.contains('promocao-toggle-sync')) return;
                atualizarProdutos(1);
            });

            // Sincronizar os Toggles Visuais com o Checkbox Real (Usando delegação para suportar AJAX)
            document.addEventListener('change', (e) => {
                if (e.target.classList.contains('promocao-toggle-sync')) {
                    const isChecked = e.target.checked;
                    const promoReal = document.getElementById('promocao-real');
                    const visualToggles = document.querySelectorAll('.promocao-toggle-sync');
                    
                    // 1. Atualizar o checkbox real (o que vai no form)
                    if (promoReal) promoReal.checked = isChecked;
                    
                    // 2. Sincronizar todos os toggles visuais (os que o utilizador vê)
                    visualToggles.forEach(t => {
                        if (t.checked !== isChecked) t.checked = isChecked;
                    });
                    
                    // 3. Atualizar os produtos
                    atualizarProdutos(1);
                }
            });

            produtosContainer.addEventListener('change', function (event) {
                if (event.target && event.target.id === 'ordenar') {
                    const ordenarMobileSelect = document.getElementById('ordenar-mobile');
                    if (ordenarMobileSelect) ordenarMobileSelect.value = event.target.value;
                    atualizarProdutos(1);
                }
            });

            paginacaoContainer.addEventListener('click', function (event) {
                const link = event.target.closest('.page-link');
                if (link && !link.closest('.pagination-item.disabled, .pagination-item.active')) {
                    event.preventDefault();
                    const pagina = link.dataset.pagina;
                    atualizarProdutos(pagina);
                }
            });
        }

        const filtrosSidebar = document.querySelector('.filtros-sidebar');
        if (botaoFiltrosMobile && filtrosSidebar) {
            const filtrosMobileQuery = window.matchMedia('(max-width: 768px)');
            const filtrosParentOriginal = filtrosSidebar.parentElement;
            const filtrosNextSiblingOriginal = filtrosSidebar.nextElementSibling;
            const pageOverlay = document.createElement('div');
            pageOverlay.className = 'page-overlay';
            document.body.appendChild(pageOverlay);

            const abrirFiltros = () => {
                if (filtrosMobileQuery.matches && filtrosSidebar.parentElement !== document.body) {
                    document.body.appendChild(filtrosSidebar);
                }
                filtrosSidebar.classList.add('ativo');
                pageOverlay.classList.add('ativo');
                botaoFiltrosMobile.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
            };

            const fecharFiltros = () => {
                filtrosSidebar.classList.remove('ativo');
                pageOverlay.classList.remove('ativo');
                botaoFiltrosMobile.setAttribute('aria-expanded', 'false');
                filtrosSidebar.style.transform = '';
                document.body.style.overflow = '';
                botaoFiltrosMobile.blur();
            };

            botaoFiltrosMobile.addEventListener('click', () => {
                if (filtrosSidebar.classList.contains('ativo')) {
                    fecharFiltros();
                } else {
                    abrirFiltros();
                }
            });

            pageOverlay.addEventListener('click', fecharFiltros);
            
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && filtrosSidebar.classList.contains('ativo')) fecharFiltros();
            });
            filtrosMobileQuery.addEventListener('change', (event) => {
                if (!event.matches && filtrosSidebar.parentElement === document.body) {
                    fecharFiltros();
                    filtrosParentOriginal.insertBefore(filtrosSidebar, filtrosNextSiblingOriginal);
                }
            });

            const filtrosHeader = filtrosSidebar.querySelector('.filtros-mobile-header');
            let dragStartY = 0;
            let dragAtualY = 0;
            let estaADragar = false;

            if (filtrosHeader) {
                filtrosHeader.addEventListener('pointerdown', (event) => {
                    if (!filtrosMobileQuery.matches || !filtrosSidebar.classList.contains('ativo')) return;
                    estaADragar = true;
                    dragStartY = event.clientY;
                    dragAtualY = 0;
                    filtrosSidebar.style.transition = 'none';
                    filtrosHeader.setPointerCapture(event.pointerId);
                });

                filtrosHeader.addEventListener('pointermove', (event) => {
                    if (!estaADragar) return;
                    dragAtualY = Math.max(0, event.clientY - dragStartY);
                    filtrosSidebar.style.transform = `translateY(${dragAtualY}px)`;
                });

                const terminarDrag = (event) => {
                    if (!estaADragar) return;
                    estaADragar = false;
                    filtrosSidebar.style.transition = '';
                    if (filtrosHeader.hasPointerCapture(event.pointerId)) {
                        filtrosHeader.releasePointerCapture(event.pointerId);
                    }

                    if (dragAtualY > 90) {
                        fecharFiltros();
                    } else {
                        filtrosSidebar.style.transform = '';
                    }
                };

                filtrosHeader.addEventListener('pointerup', terminarDrag);
                filtrosHeader.addEventListener('pointercancel', terminarDrag);
            }
        }

        // Lógica dos Acordeões de Filtros
        document.querySelectorAll('.filtro-titulo').forEach(titulo => {
            titulo.addEventListener('click', function() {
                const grupo = this.closest('.filtro-grupo');
                grupo.classList.toggle('aberto');
            });
        });

        const gridContainer = document.getElementById('produtos-grid-container');
        const deveAbrirPaginaProduto = () => window.matchMedia('(max-width: 768px)').matches;

        if (gridContainer) {
            gridContainer.addEventListener('click', function (e) {
                const produtoCardLink = e.target.closest('a.produto, div.produto');
                if (!produtoCardLink) return;

                if (produtoCardLink.tagName === 'A' && !e.metaKey && !e.ctrlKey) {
                    e.preventDefault();
                } else if (produtoCardLink.tagName === 'A') {
                    return;
                }

                if (produtoCardLink.classList.contains('esgotado')) {
                    return;
                }

                if (deveAbrirPaginaProduto()) {
                    window.location.href = produtoCardLink.getAttribute('href') || `/produto/${produtoCardLink.dataset.slug}`;
                    return;
                }

                const isTouchDevice = window.matchMedia("(hover: none) and (pointer: coarse)").matches;

                const imagemClicadaSrc = produtoCardLink.querySelector('.imagem-principal').src;

                if (!isTouchDevice) {
                    if (e.target.closest('.btn-adicionar-rapido')) {
                        adicionarRapidoAoCarrinho(e.target.closest('.btn-adicionar-rapido'));
                    } else {
                        window.abrirModalProduto(produtoCardLink, imagemClicadaSrc);
                    }
                    return;
                }

                const activeCard = document.querySelector('.produto.mobile-actions-visible');
                const clickedButton = e.target.closest('.btn-adicionar-rapido');

                if (clickedButton) {
                    adicionarRapidoAoCarrinho(clickedButton);
                    if (activeCard) activeCard.classList.remove('mobile-actions-visible');
                    return;
                }

                if (activeCard && activeCard === produtoCardLink) {
                    window.abrirModalProduto(produtoCardLink, imagemClicadaSrc);
                } else {
                    if (activeCard) activeCard.classList.remove('mobile-actions-visible');
                    produtoCardLink.classList.add('mobile-actions-visible');
                }
            });
        }

        function adicionarRapidoAoCarrinho(botao) {
            const produtoCard = botao.closest('.produto');
            const temVariacoes = botao.dataset.temVariacoes === '1';
            const imagemClicadaSrc = produtoCard.querySelector('.imagem-principal').src;

            if (temVariacoes) {
                if (deveAbrirPaginaProduto()) {
                    window.location.href = produtoCard.getAttribute('href') || `/produto/${produtoCard.dataset.slug}`;
                    return;
                }
                window.abrirModalProduto(produtoCard, imagemClicadaSrc);
            } else {
                // Lógica para produtos SIMPLES (sem variações)
                let baseVariacaoId = produtoCard.dataset.baseVariacaoId;
                let stockTotal = parseInt(produtoCard.dataset.quantidade, 10) || 1;

                // Se o ID da variação base JÁ FOI CARREGADO, prossegue imediatamente.
                if (baseVariacaoId) {
                    const precoFinal = parseFloat(produtoCard.dataset.precoPromocional) || parseFloat(produtoCard.dataset.preco);
                    const precoOriginal = parseFloat(produtoCard.dataset.preco);

                    const produtoParaCarrinho = {
                        id: produtoCard.dataset.id,
                        variacao_id: baseVariacaoId,
                        referencia: produtoCard.dataset.referencia,
                        nome: produtoCard.dataset.nome,
                        preco: precoFinal,
                        precoOriginal: precoOriginal,
                        emPromocao: !!produtoCard.dataset.precoPromocional,
                        foto: imagemClicadaSrc,
                        selecoes: {},
                        quantidade: 1,
                        stock: stockTotal,
                        peso_gramas: parseInt(produtoCard.dataset.peso, 10) || 0
                    };
                    window.adicionarAoCarrinho(produtoParaCarrinho, botao);
                    return;
                }

                // --- INÍCIO DA CORREÇÃO: Fallback com AJAX para obter o ID da variação base ---
                // Se o ID estiver em falta, desativa o botão temporariamente e faz um pedido AJAX
                botao.disabled = true;
                const originalText = botao.textContent;
                botao.textContent = 'A carregar...';

                fetch(`/ajax_get_variacoes.php?id=${produtoCard.dataset.id}`)
                    .then(res => res.json())
                    .then(data => {
                        const variacaoBase = data.variacoes.find(v => Object.keys(v.atributos).length === 0);

                        if (variacaoBase) {
                            baseVariacaoId = variacaoBase.id;
                            stockTotal = variacaoBase.quantidade;

                            // O stock é atualizado no dataset para que o próximo clique seja rápido
                            produtoCard.dataset.baseVariacaoId = baseVariacaoId;
                            produtoCard.dataset.quantidade = stockTotal;

                            const precoFinal = parseFloat(produtoCard.dataset.precoPromocional) || parseFloat(produtoCard.dataset.preco);
                            const precoOriginal = parseFloat(produtoCard.dataset.preco);

                            const produtoParaCarrinho = {
                                id: produtoCard.dataset.id,
                                variacao_id: baseVariacaoId,
                                referencia: produtoCard.dataset.referencia,
                                nome: produtoCard.dataset.nome,
                                preco: precoFinal,
                                precoOriginal: precoOriginal,
                                emPromocao: !!produtoCard.dataset.precoPromocional,
                                foto: imagemClicadaSrc,
                                selecoes: {},
                                quantidade: 1,
                                stock: stockTotal,
                                peso_gramas: parseInt(produtoCard.dataset.peso, 10) || 0
                            };
                            window.adicionarAoCarrinho(produtoParaCarrinho, botao);

                        } else {
                            mostrarPopup('Erro: Não foi encontrada a variação base do produto.', 'erro');
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar variação base:', error);
                        mostrarPopup('Erro de comunicação. Tente novamente.', 'erro');
                    })
                    .finally(() => {
                        botao.disabled = false;
                        botao.textContent = originalText;
                    });
                // --- FIM DA CORREÇÃO ---
            }
        }

        const produtoIdParaAbrir = sessionStorage.getItem('abrirModalProdutoId');
        if (produtoIdParaAbrir && !deveAbrirPaginaProduto()) {
            const produtoCard = document.querySelector(`.produto[data-id='${produtoIdParaAbrir}']`);
            if (produtoCard) {
                setTimeout(() => {
                    const imagemSrc = produtoCard.querySelector('.imagem-principal').src;
                    window.abrirModalProduto(produtoCard, imagemSrc);
                }, 150);
            }
        }
        if (produtoIdParaAbrir) sessionStorage.removeItem('abrirModalProdutoId');
    });
</script>
<?php
include 'templates/footer.php';
?>