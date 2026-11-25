<?php
include __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/url_helpers.php';
require_once __DIR__ . '/config/formatters.php';
require_once __DIR__ . '/includes/ui/ui.php';

$requested_with = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
$fetch_destination = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '')));
$is_ajax_request = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && $requested_with === 'xmlhttprequest'
    && $fetch_destination !== 'document';

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

$termo_pesquisa = trim((string)($_GET['q'] ?? ''));
$promocao_selecionada = is_string($_GET['promocao'] ?? null) ? $_GET['promocao'] : '';
$categorias_selecionadas = is_array($_GET['categorias'] ?? null) ? array_values(array_filter($_GET['categorias'], 'is_string')) : [];
$atributos_selecionados = is_array($_GET['atributos'] ?? null) ? $_GET['atributos'] : [];
$ordenar_selecionado = is_string($_GET['ordenar'] ?? null) ? $_GET['ordenar'] : '';

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
        if (!is_string($grupo) || $grupo === '' || !is_array($valores)) {
            continue;
        }
        $grupo_seguro = str_replace(['\\', '"'], '', $grupo);
        if ($grupo_seguro === '') {
            continue;
        }
        $valores_limpos = array_values(array_filter($valores, fn($v) => is_string($v) && $v !== ''));
        if (!empty($valores_limpos)) {
            $group_conditions = [];
            foreach ($valores_limpos as $valor) {
                $group_conditions[] = "JSON_CONTAINS(p.atributos, JSON_QUOTE(?), ?)";
                $params[] = $valor;
                $params[] = '$.' . '"' . $grupo_seguro . '"';
                $types .= 'ss';
            }
            $condicoes[] = "(" . implode(' OR ', $group_conditions) . ")";
        }
    }
}


$where_clause = " WHERE " . implode(' AND ', $condicoes);

// OBTER O TOTAL DE RESULTADOS
$stmt_count = $conn->prepare("SELECT COUNT(*) as total " . $sql_base . $where_clause);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_resultados = (int)$stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = max(1, (int)ceil($total_resultados / $resultados_por_pagina));
$stmt_count->close();
if ($pagina_atual > $total_paginas) {
    $pagina_atual = $total_paginas;
    $offset = ($pagina_atual - 1) * $resultados_por_pagina;
}

// ORDENAÇÃO - construir FIELD() dinâmico com ordem das categorias da BD
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
    // Subconsulta para stock total e ID da variação base para produtos simples
    $query_final = "SELECT p.*,
        (SELECT SUM(pv.quantidade) FROM produto_variacoes pv WHERE pv.produto_id = p.id) as stock_total,
        (SELECT pv.id FROM produto_variacoes pv WHERE pv.produto_id = p.id AND pv.atributos = '{}' LIMIT 1) as base_variacao_id "
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


// --- FUNÇÃO PARA GERAR PAGINAÇÃO (URLs reais + windowing + ARIA, compatível com AJAX e SEO) ---
function gerarPaginacaoAjax($pagina_atual, $total_paginas)
{
    if ($total_paginas <= 1) {
        return '';
    }
    $pagina_atual = max(1, min((int)$total_paginas, (int)$pagina_atual));
    $query_base = $_GET;
    unset($query_base['_fragment'], $query_base['pagina']);

    $buildUrl = static function (int $p) use ($query_base): string {
        $q = $query_base;
        if ($p > 1) {
            $q['pagina'] = $p;
        }
        $qs = http_build_query($q);
        return '/produtos' . ($qs !== '' ? '?' . $qs : '');
    };

    $max_visible = 5;
    $start = max(1, $pagina_atual - (int)floor($max_visible / 2));
    $end   = min($total_paginas, $start + $max_visible - 1);
    if ($end - $start + 1 < $max_visible) {
        $start = max(1, $end - $max_visible + 1);
    }

    $html = '<nav class="pagination-container" aria-label="Paginação de produtos"><ul class="pagination-list">';

    // Anterior
    if ($pagina_atual > 1) {
        $prevUrl = htmlspecialchars($buildUrl($pagina_atual - 1), ENT_QUOTES, 'UTF-8');
        $html .= '<li class="pagination-item prev">'
            . '<a class="page-link" href="' . $prevUrl . '" data-pagina="' . ($pagina_atual - 1) . '" aria-label="Página anterior" title="Anterior">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>'
            . '</a></li>';
    } else {
        $html .= '<li class="pagination-item prev disabled">'
            . '<span class="page-link" aria-disabled="true" aria-label="Página anterior">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>'
            . '</span></li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $pageUrl = htmlspecialchars($buildUrl($i), ENT_QUOTES, 'UTF-8');
        $isActive = ($i === $pagina_atual);
        $html .= '<li class="pagination-item' . ($isActive ? ' active' : '') . '">'
            . '<a class="page-link" href="' . $pageUrl . '" data-pagina="' . $i . '"' . ($isActive ? ' aria-current="page"' : '') . '>' . $i . '</a></li>';
    }

    // Seguinte
    if ($pagina_atual < $total_paginas) {
        $nextUrl = htmlspecialchars($buildUrl($pagina_atual + 1), ENT_QUOTES, 'UTF-8');
        $html .= '<li class="pagination-item next">'
            . '<a class="page-link" href="' . $nextUrl . '" data-pagina="' . ($pagina_atual + 1) . '" aria-label="Página seguinte" title="Seguinte">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>'
            . '</a></li>';
    } else {
        $html .= '<li class="pagination-item next disabled">'
            . '<span class="page-link" aria-disabled="true" aria-label="Página seguinte">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>'
            . '</span></li>';
    }

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
            <?php echo ui_switch('', 'Só Promoções', !empty($promocao_selecionada), [
                'id'          => false,
                'input_class' => 'promocao-toggle-sync',
                'title'       => 'Mostrar apenas produtos em promoção',
                'attrs'       => ['data-target' => 'header'],
            ]); ?>
        </div>
        <div class="ordenar-container">
            <label for="ordenar">Ordenar por:</label>
            <?php
            $opcoes_ordenar_loja = [
                ''          => 'Destaques',
                'categoria' => 'Por Categoria',
                'recentes'  => 'Mais Recentes',
                'asc'       => 'Preço Crescente',
                'desc'      => 'Preço Decrescente',
            ];
            echo ui_select('ordenar', $opcoes_ordenar_loja, $ordenar_selecionado, ['id' => 'ordenar']);
            ?>
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

            $foto_principal_por_id = array_column($produtos_finais, 'foto_principal', 'id');
            $stmt_img = $conn->prepare("SELECT produto_id, nome_ficheiro FROM produto_imagens WHERE produto_id IN ($placeholders) ORDER BY produto_id, id ASC");
            $stmt_img->bind_param($types_ids, ...$product_ids);
            $stmt_img->execute();
            $result_img = $stmt_img->get_result();
            while ($img_row = $result_img->fetch_assoc()) {
                $pid = $img_row['produto_id'];
                if (!isset($imagens_por_produto[$pid])) {
                    $imagens_por_produto[$pid] = [];
                }
                if (($foto_principal_por_id[$pid] ?? '') === $img_row['nome_ficheiro']) {
                    array_unshift($imagens_por_produto[$pid], $img_row['nome_ficheiro']);
                } else {
                    $imagens_por_produto[$pid][] = $img_row['nome_ficheiro'];
                }
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
                    $attrs = json_decode((string)($var_row['atributos'] ?? '{}'), true);
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

                echo ui_product_card($row, [
                    'slug'            => $slug,
                    'href'            => $link_produto,
                    'foto_principal'  => $foto_principal,
                    'foto_secundaria' => $foto_secundaria,
                    'fotos'           => $imagens_por_produto[$row['id']] ?? [],
                    'filtros_ativos'  => $filtros_de_atributos_ativos,
                    'modal_data'      => true,
                    'quick_add'       => true,
                    'img_dimensions'  => true,
                ]);
                ?>
            <?php endforeach; endforeach; ?>
    </div>
<?php else: ?>
    <?php echo ui_empty_state('Não foram encontrados produtos com os critérios selecionados.'); ?>
<?php endif; ?>

<?php
$html_produtos = ob_get_clean();

if ($is_ajax_request) {
    $html_paginacao = gerarPaginacaoAjax($pagina_atual, $total_paginas);
    // Esta resposta parcial nunca pode ser reutilizada como documento numa
    // navegação normal (por exemplo, ao usar "voltar" no browser).
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Vary: X-Requested-With, Sec-Fetch-Dest');
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
    $canonical_url = '/produtos?q=' . rawurlencode($termo_pesquisa);
} elseif (!empty($promocao_selecionada)) {
    $titulo_pagina = 'Promoções';
    $descricao_pagina = 'Descobre as promoções TopTop. Roupa de mulher e acessórios com desconto, com envio rápido para Portugal.';
    $canonical_url = '/produtos?promocao=1';
} elseif (count($categorias_selecionadas) === 1 && empty($atributos_selecionados)) {
    $cat_nome = $categorias_selecionadas[0];
    $titulo_pagina = $cat_nome;
    $descricao_pagina = 'Explora ' . $cat_nome . ' na TopTop. Moda feminina com envio rápido para Portugal.';
    $canonical_url = '/produtos?categorias%5B%5D=' . rawurlencode($cat_nome);
} else {
    $titulo_pagina = 'Produtos';
    $descricao_pagina = 'Explora toda a coleção TopTop: roupa de mulher, acessórios e muito mais. Envio rápido para Portugal.';
    $canonical_url = '/produtos';
}
if ($pagina_atual > 1) {
    $canonical_url .= (strpos($canonical_url, '?') !== false ? '&' : '?') . 'pagina=' . $pagina_atual;
}
$_pag_base = preg_replace('/([?&])pagina=\d+/', '', $canonical_url);
$_pag_base = rtrim($_pag_base, '?&');
$head_extra = '';
if ($pagina_atual > 1) {
    $_prev = $pagina_atual === 2 ? $_pag_base : $_pag_base . (strpos($_pag_base, '?') !== false ? '&' : '?') . 'pagina=' . ($pagina_atual - 1);
    $head_extra .= '    <link rel="prev" href="https://toptop.pt' . htmlspecialchars($_prev, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
if ($pagina_atual < $total_paginas) {
    $_next = $_pag_base . (strpos($_pag_base, '?') !== false ? '&' : '?') . 'pagina=' . ($pagina_atual + 1);
    $head_extra .= '    <link rel="next" href="https://toptop.pt' . htmlspecialchars($_next, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
include 'templates/header.php';
?>
<main class="pagina-produtos animate-entry">
    <div class="loja-header">
        <p class="loja-kicker"><?php echo !empty($termo_pesquisa) ? 'Pesquisa' : 'Catálogo'; ?></p>
        <h1><?php echo !empty($termo_pesquisa) ? 'Resultados para "' . htmlspecialchars($termo_pesquisa) . '"' : 'Produtos'; ?></h1>
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
                    <?php echo ui_switch('', 'Só Promoções', !empty($promocao_selecionada), [
                        'id'          => false,
                        'input_class' => 'promocao-toggle-sync',
                        'attrs'       => ['data-target' => 'sidebar'],
                    ]); ?>
                </div>
                
                <div class="filtro-sort-mobile">
                    <label for="ordenar-mobile">Ordenar por</label>
                    <?php echo ui_select('', $opcoes_ordenar_loja, $ordenar_selecionado, ['id' => 'ordenar-mobile']); ?>
                </div>

                <div class="sidebar-desktop-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    <span>Filtrar por</span>
                </div>
                
                <?php if (!empty($categorias)): ?>
                    <div class="filtro-grupo">
                        <button type="button" class="filtro-titulo" aria-expanded="false">Categoria <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg></button>
                        <div class="filtro-conteudo">
                            <div class="filtro-conteudo-inner">
                                <ul class="lista-filtros">
                                    <?php foreach ($categorias as $cat): ?>
                                        <li>
                                            <?php echo ui_checkbox('categorias[]', $cat['categoria'], in_array($cat['categoria'], $categorias_selecionadas), [
                                                'variant' => 'filter',
                                                'value'   => $cat['categoria'],
                                            ]); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach ($filtros_disponiveis as $nome_grupo => $valores): ?>
                    <div class="filtro-grupo">
                        <button type="button" class="filtro-titulo" aria-expanded="false"><?php echo htmlspecialchars($nome_grupo); ?> <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg></button>
                        <div class="filtro-conteudo">
                            <div class="filtro-conteudo-inner">
                                <ul class="lista-filtros">
                                    <?php foreach ($valores as $valor): ?>
                                        <li>
                                            <?php echo ui_checkbox('atributos[' . $nome_grupo . '][]', $valor, isset($atributos_selecionados[$nome_grupo]) && in_array($valor, $atributos_selecionados[$nome_grupo]), [
                                                'variant' => 'filter',
                                                'value'   => $valor,
                                            ]); ?>
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
        <?php echo ui_close_button(['class' => 'fechar']); ?>

        <div class="modal-loading-overlay">
            <div class="spinner"></div>
        </div>

        <div class="modal-grid-conteudo">
            <div class="modal-galeria">
                <div class="zoom-container"><img id="modalImagemPrincipal" alt=""></div>
                <div id="modalThumbnails" class="modal-thumbnails"></div>
            </div>

            <div class="modal-info">
                <h2 id="modalNome"></h2>
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
                            <?php echo ui_qty_stepper(1, ['variant' => 'product', 'id' => 'modalQuantidade', 'min' => 1, 'max' => 10]); ?>
                        </div>
                        <?php echo ui_button('A carregar...', ['id' => 'adicionarCarrinhoBtn', 'class' => 'button add-btn', 'disabled' => true]); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="produtos-relacionados-container modal-related-footer"></div>
    </div>
</div>

<?php
$abrir_produto_id = isset($_GET['abrir_produto']) ? (int) $_GET['abrir_produto'] : 0;
if ($abrir_produto_id > 0 && !in_array($abrir_produto_id, array_map('intval', $product_ids ?? []), true)) {
    $stmt_preload = $conn->prepare(
        "SELECT p.*,
                (SELECT SUM(pv.quantidade) FROM produto_variacoes pv WHERE pv.produto_id = p.id) AS stock_total,
                (SELECT pv.id FROM produto_variacoes pv WHERE pv.produto_id = p.id AND pv.atributos = '{}' LIMIT 1) AS base_variacao_id
         FROM produtos p
         WHERE p.id = ? AND p.ativo = 1
         LIMIT 1"
    );
    if ($stmt_preload) {
        $stmt_preload->bind_param('i', $abrir_produto_id);
        $stmt_preload->execute();
        $preload_row = $stmt_preload->get_result()->fetch_assoc();
        $stmt_preload->close();

        if ($preload_row) {
            $preload_imgs = [];
            $stmt_pimg = $conn->prepare("SELECT nome_ficheiro FROM produto_imagens WHERE produto_id = ? ORDER BY FIELD(nome_ficheiro, ?) DESC, id ASC");
            if ($stmt_pimg) {
                $stmt_pimg->bind_param('is', $preload_row['id'], $preload_row['foto_principal']);
                $stmt_pimg->execute();
                $res_pimg = $stmt_pimg->get_result();
                while ($ir = $res_pimg->fetch_assoc()) {
                    $preload_imgs[] = $ir['nome_ficheiro'];
                }
                $stmt_pimg->close();
            }
            if (empty($preload_imgs) && !empty($preload_row['foto_principal'])) {
                $preload_imgs[] = $preload_row['foto_principal'];
            }
            $preload_foto = $preload_imgs[0] ?? 'default.jpg';
            $preload_slug = criar_slug($preload_row['nome'] . '-' . $preload_row['id']);
            ?>
            <div id="produto-modal-preload" class="produto" style="display:none;"
                data-id="<?php echo (int) $preload_row['id']; ?>"
                data-slug="<?php echo htmlspecialchars($preload_slug, ENT_QUOTES, 'UTF-8'); ?>"
                data-categoria="<?php echo htmlspecialchars((string)($preload_row['categoria'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-referencia="<?php echo htmlspecialchars((string)($preload_row['referencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-nome="<?php echo htmlspecialchars((string)($preload_row['nome'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-preco="<?php echo $preload_row['preco']; ?>"
                data-descricao="<?php echo htmlspecialchars((string)($preload_row['descricao'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-fotos='<?php echo htmlspecialchars(json_encode($preload_imgs), ENT_QUOTES, 'UTF-8'); ?>'
                data-preco-promocional="<?php echo $preload_row['preco_promocional'] ?? ''; ?>"
                data-atributos='<?php echo htmlspecialchars($preload_row['atributos'] ?? '{}', ENT_QUOTES, 'UTF-8'); ?>'
                data-quantidade="<?php echo (int) ($preload_row['stock_total'] ?? 0); ?>"
                data-base-variacao-id="<?php echo $preload_row['base_variacao_id'] ?? ''; ?>"
                data-guia-tamanho-id="<?php echo $preload_row['guia_tamanho_id'] ?? ''; ?>"
                data-peso="<?php echo $preload_row['peso_gramas'] ?? 0; ?>"
                data-filtros-ativos="false">
                <img src="/public/images/<?php echo htmlspecialchars($preload_foto, ENT_QUOTES, 'UTF-8'); ?>" class="imagem-principal" alt="">
            </div>
            <?php
        }
    }
}
?>

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

        let pedidoProdutosController = null;
        let queryRenderizada = window.location.search;

        function atualizarProdutos(pagina = 1) {
            const formData = new FormData(formFiltros);

            const ordenarMobileSelect = document.getElementById('ordenar-mobile');
            const ordenarDesktopSelect = document.getElementById('ordenar');
            const usarOrdenarMobile = ordenarMobileSelect && window.matchMedia('(max-width: 768px)').matches;
            const ordenarValue = usarOrdenarMobile ? ordenarMobileSelect.value : (ordenarDesktopSelect ? ordenarDesktopSelect.value : 'categoria');
            formData.set('ordenar', ordenarValue);
            formData.set('pagina', pagina);

            const paramsPublicos = new URLSearchParams(formData);
            const queryPublica = paramsPublicos.toString().replace(/%5B/g, '[').replace(/%5D/g, ']');
            const urlPublico = window.location.pathname + (queryPublica ? '?' + queryPublica : '');

            // O pedido parcial usa um URL diferente do que fica no histórico.
            // Assim, nem o browser nem um proxy podem confundir JSON com a página HTML.
            const paramsPedido = new URLSearchParams(paramsPublicos);
            paramsPedido.set('_fragment', 'produtos');
            const urlPedido = window.location.pathname + '?' + paramsPedido.toString();

            if (pedidoProdutosController) pedidoProdutosController.abort();
            const controller = new AbortController();
            pedidoProdutosController = controller;

            produtosGridContainer.style.opacity = '0.5';
            paginacaoContainer.style.opacity = '0.5';

            fetch(urlPedido, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store',
                credentials: 'same-origin',
                signal: controller.signal
            })
                .then(response => {
                    const contentType = response.headers.get('content-type') || '';
                    if (!response.ok) {
                        throw new Error(`Pedido de produtos falhou (${response.status}).`);
                    }
                    if (!contentType.toLowerCase().includes('application/json')) {
                        throw new Error('O servidor devolveu um formato inesperado.');
                    }
                    return response.json();
                })
                .then(data => {
                    if (pedidoProdutosController !== controller) return;
                    if (!data || typeof data.html_produtos !== 'string' || typeof data.html_paginacao !== 'string') {
                        throw new Error('A resposta dos produtos está incompleta.');
                    }

                    produtosGridContainer.innerHTML = data.html_produtos;
                    paginacaoContainer.innerHTML = data.html_paginacao;

                    if (urlPublico !== window.location.pathname + window.location.search) {
                        history.pushState({ produtosFiltros: true }, '', urlPublico);
                    }
                    queryRenderizada = window.location.search;

                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    if (typeof window.ativarTooltipsProfissionais === 'function') {
                        window.ativarTooltipsProfissionais();
                    }
                })
                .catch(error => {
                    if (error.name === 'AbortError') return;
                    console.error('Erro ao atualizar os produtos:', error);
                    // A renderização completa do servidor é o fallback mais seguro:
                    // mantém os filtros pedidos sem deixar a página num estado parcial.
                    window.location.assign(urlPublico);
                })
                .finally(() => {
                    if (pedidoProdutosController !== controller) return;
                    pedidoProdutosController = null;
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

            window.addEventListener('popstate', function () {
                // As alterações de hash pertencem ao modal de produto. Quando a
                // query muda, uma renderização completa garante que formulário,
                // produtos e paginação regressam todos ao mesmo estado.
                if (window.location.search !== queryRenderizada) {
                    window.location.reload();
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
                const aberto = grupo.classList.toggle('aberto');
                this.setAttribute('aria-expanded', aberto ? 'true' : 'false');
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

        const urlParamsAbrir = new URLSearchParams(window.location.search);
        const rawAbrirId = urlParamsAbrir.get('abrir_produto') || sessionStorage.getItem('abrirModalProdutoId') || '';
        const produtoIdParaAbrir = /^\d+$/.test(rawAbrirId) ? rawAbrirId : '';
        if (produtoIdParaAbrir) {
            const produtoCard = document.querySelector(`.produto[data-id='${produtoIdParaAbrir}']`)
                || document.getElementById('produto-modal-preload');
            if (produtoCard) {
                if (deveAbrirPaginaProduto()) {
                    window.location.href = produtoCard.getAttribute('href') || `/produto/${produtoCard.dataset.slug}`;
                } else {
                    setTimeout(() => {
                        const imgEl = produtoCard.querySelector('.imagem-principal');
                        const imagemSrc = imgEl ? imgEl.src : null;
                        window.abrirModalProduto(produtoCard, imagemSrc);
                    }, 150);
                }
            }
            sessionStorage.removeItem('abrirModalProdutoId');
            if (urlParamsAbrir.has('abrir_produto')) {
                urlParamsAbrir.delete('abrir_produto');
                const cleanQuery = urlParamsAbrir.toString();
                const cleanUrl = window.location.pathname + (cleanQuery ? '?' + cleanQuery : '');
                window.history.replaceState({}, '', cleanUrl);
            }
        }
    });
</script>
<?php
include 'templates/footer.php';
?>
