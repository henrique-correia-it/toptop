<?php
// --- BLOCO 1: Início de Sessão e Configuração ---
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/limpar_abandonadas.php';
// --- CARREGAMENTO DE COMPONENTES ---
require_once __DIR__ . '/../includes/ui/ui.php';
$requestPathHeader = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_starts_with($requestPathHeader, '/admin') || str_starts_with($requestPathHeader, '/dev')) {
    require_once __DIR__ . '/../admin/components/back_button.php';
    require_once __DIR__ . '/../admin/components/context_menu.php';
    require_once __DIR__ . '/../admin/components/quick_edit_modal.php';
}

require_once __DIR__ . '/../config/cliente_auth.php';
require_once __DIR__ . '/../config/csrf.php';

// --- BLOCO ANTI-CACHE ---
if (
    str_starts_with($requestPathHeader, '/admin')
    || str_starts_with($requestPathHeader, '/dev')
    || str_starts_with($requestPathHeader, '/minha-conta')
    || !empty($_SESSION['admin_logado'])
    || !empty($_SESSION['cliente_logado'])
) {
    header("Cache-Control: private, no-store, no-cache, must-revalidate");
} else {
    header("Cache-Control: no-cache, must-revalidate");
}
// -----------------------

// --- BLOCO 2: Versão do Site (Cache Busting) ---
$ficheiro_versao = __DIR__ . '/../config/versao_site.php';
// Se o ficheiro de versão existir, usa o número guardado. Se não, usa a hora atual.
$versao_global = (file_exists($ficheiro_versao)) ? require($ficheiro_versao) : time();

// Fallback de segurança
if (empty($versao_global)) { $versao_global = time(); }

$paginaAtual = basename($_SERVER['PHP_SELF']);
$noindex_page = (isset($noindex) && $noindex)
    || strpos($_SERVER['REQUEST_URI'], '/admin') === 0
    || strpos($_SERVER['REQUEST_URI'], '/dev') === 0
    || strpos($_SERVER['REQUEST_URI'], '/minha-conta') === 0
    || in_array($paginaAtual, ['entrar.php', 'registar.php', 'checkout.php', 'sucesso.php',
        'verificar-email.php', 'recuperar-conta.php', 'redefinir-conta.php']);

// --- META SEO: fonte única para <title>, meta description, Open Graph e Twitter ---
// Páginas definem $titulo_pagina/$descricao_pagina antes de incluir o header.
// Quando não definem, usa-se um fallback genérico da loja (e NÃO a descrição da 404).
$meta_titulo = isset($titulo_pagina)
    ? $titulo_pagina . ' | TopTop'
    : 'TopTop - Loja de Roupa Online';
$meta_descricao = isset($descricao_pagina)
    ? trim(mb_substr($descricao_pagina, 0, 160))
    : 'A tua loja de moda online: roupa feminina, acessórios e peças para bebé com curadoria exclusiva. Entregas rápidas em Portugal e em toda a Europa.';

// --- BLOCO 3: Configurações de Portes para JS ---
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/header_functions.php';
require_once __DIR__ . '/../includes/ShippingService.php';
$portes_js = get_shipping_rates($conn);
$portes_gratis_ativo = is_free_shipping_enabled($conn);
$portes_gratis_minimo = get_free_shipping_threshold($conn);
$isAdminHeader = is_global_edit_mode();
// Header dinâmico (esconde no scroll para baixo; desativado em modo de edição)
$headerAutoHide = !$isAdminHeader;
$headerLogoSrc = getHeaderLogo('/public/assets/logo1.jpg');
$headerLogoAlt = getHeaderText('logo_alt', 'Logo TopTop');
$headerNavHome = getHeaderText('nav_home', 'Home');
$headerNavProdutos = getHeaderText('nav_produtos', 'Produtos');
$headerNavContacto = getHeaderText('nav_contacto', 'Contacto');
$headerNavAdmin = getHeaderText('nav_admin', 'Painel Admin');
$clienteHeaderLogado = is_cliente_logged_in();
$isAdminLogado = is_admin_logged_in();
$isDevLogado = is_developer();
$isAnyLogado = $clienteHeaderLogado || $isAdminLogado;

$clienteHeaderUrl = $clienteHeaderLogado ? '/minha-conta' : ($isAdminLogado ? '/admin' : '/entrar');
$clienteHeaderLabel = $clienteHeaderLogado ? 'Minha conta' : ($isAdminLogado ? 'Painel Admin' : 'Entrar');

if ($isDevLogado && !$clienteHeaderLogado) {
    $clienteHeaderUrl = '/dev';
    $clienteHeaderLabel = 'Painel Dev';
}

$headerCategorias = [];
$resCategoriasHeader = $conn->query("SELECT c.nome AS categoria FROM categorias c WHERE EXISTS (SELECT 1 FROM produtos p WHERE p.categoria COLLATE utf8mb4_unicode_ci = c.nome COLLATE utf8mb4_unicode_ci AND p.ativo = 1 AND EXISTS (SELECT 1 FROM produto_variacoes pv WHERE pv.produto_id = p.id AND pv.quantidade > 0)) ORDER BY c.ordem ASC, c.id ASC");
if ($resCategoriasHeader && $resCategoriasHeader->num_rows > 0) {
    $headerCategorias = $resCategoriasHeader->fetch_all(MYSQLI_ASSOC);
} else {
    $resCategoriasHeader = $conn->query("SELECT DISTINCT categoria FROM produtos p WHERE categoria IS NOT NULL AND categoria != '' AND ativo = 1 AND EXISTS (SELECT 1 FROM produto_variacoes pv WHERE pv.produto_id = p.id AND pv.quantidade > 0) ORDER BY categoria ASC");
    $headerCategorias = $resCategoriasHeader ? $resCategoriasHeader->fetch_all(MYSQLI_ASSOC) : [];
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($meta_titulo, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($meta_descricao, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/jpeg" href="/public/assets/logo1_branco.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="/public/assets/logo1_branco.jpg">
    <link rel="apple-touch-icon" href="/public/assets/logo1_branco.jpg">
    
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://unpkg.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Fontes combinadas num único pedido (cedo, paralelo, sem bloquear o texto: display=swap) -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400;1,9..144,500&family=Playfair+Display:wght@700;900&display=swap">
    <?php if ($paginaAtual === 'index.php'): ?>
    <!-- Pré-carrega o hero da homepage (LCP) com prioridade alta. URL idêntico ao background-image para ser reutilizado. -->
    <link rel="preload" as="image" fetchpriority="high" href="<?php echo htmlspecialchars(getHeroBgUrl(), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $versao_global; ?>">
    <?php endif; ?>
    <?php if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true): ?>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js" defer></script>
    <?php endif; ?>
    <script src="https://unpkg.com/popper.js@1" defer></script>
    <script src="https://unpkg.com/tippy.js@5" defer></script>
    <script>
        window.LOJA_CONFIG_PORTES = <?php echo json_encode($portes_js); ?>;
        window.LOJA_CONFIG_PORTES_GRATIS = <?php echo json_encode([
            'ativo' => $portes_gratis_ativo,
            'pais' => 'PT',
            'valor_minimo' => $portes_gratis_minimo,
            'cp_min' => 1000,
            'cp_max' => 8999,
        ]); ?>;
    </script>
    
    <?php $cssScope = (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true) ? 'full' : 'public'; ?>
    <link rel="stylesheet" href="/public/css/style.php?scope=<?php echo $cssScope; ?>&amp;v=<?php echo $versao_global; ?>">
    <?php if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true): ?>
    <!-- Cropper.js só é usado por admins (recorte de fotos de produto e logo no modo edição). -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <?php endif; ?>

    <meta name="google-site-verification" content="Ojp_OX708v7vUvpub0aHPKnx1XKHF7sn86EpICZcMh4" />
	<meta name="google-site-verification" content="Q3I4uZFr6bLSBI6UCuPvydE0sXvWaH_6st--lc0tT8I" />
    <link rel="canonical" href="https://toptop.pt<?php echo isset($canonical_url) ? htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8') : htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?') . (parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ? '?' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) : ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <?php if ($noindex_page): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
    <?php echo $head_extra ?? ''; ?>
<?php
$_og_title = htmlspecialchars($meta_titulo, ENT_QUOTES, 'UTF-8');
$_og_desc  = htmlspecialchars($meta_descricao, ENT_QUOTES, 'UTF-8');
$_og_path  = isset($canonical_url) ? $canonical_url : ($_SERVER['REQUEST_URI'] ?? '/');
$_og_url   = 'https://toptop.pt' . htmlspecialchars($_og_path, ENT_QUOTES, 'UTF-8');
$_og_type  = (isset($produto) && is_array($produto)) ? 'product' : 'website';
$_og_image = (isset($produto) && !empty($produto['foto_principal']))
    ? 'https://toptop.pt/public/images/' . rawurlencode($produto['foto_principal'])
    : 'https://toptop.pt' . htmlspecialchars($headerLogoSrc, ENT_QUOTES, 'UTF-8');
?>
    <meta property="og:site_name" content="TopTop">
    <meta property="og:locale" content="pt_PT">
    <meta property="og:type" content="<?php echo $_og_type; ?>">
    <meta property="og:url" content="<?php echo $_og_url; ?>">
    <meta property="og:title" content="<?php echo $_og_title; ?>">
    <meta property="og:description" content="<?php echo $_og_desc; ?>">
    <meta property="og:image" content="<?php echo $_og_image; ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $_og_title; ?>">
    <meta name="twitter:description" content="<?php echo $_og_desc; ?>">
    <meta name="twitter:image" content="<?php echo $_og_image; ?>">
</head>
<body class="<?php echo trim(($isAdminHeader ? 'global-edit-mode-active ' : '') . 'site-bg-light'); ?>">

<header class="site-header<?php echo $headerAutoHide ? ' header-auto-hide' : ''; ?>">
   <div class="logo <?php echo $isAdminHeader ? 'header-editable' : ''; ?>" data-seccao="logo_src">
    <a href="/"><img src="<?php echo htmlspecialchars($headerLogoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($headerLogoAlt, ENT_QUOTES, 'UTF-8'); ?>" class="logo-img" fetchpriority="high"></a>
</div>

    <nav class="main-nav-desktop">
        <ul>
            <li><a href="/" class="<?php if ($paginaAtual == 'index.php') echo 'ativo'; ?>"><span class="<?php echo $isAdminHeader ? 'header-editable' : ''; ?>" data-seccao="nav_home"><?php echo htmlspecialchars($headerNavHome, ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <li class="nav-item-categorias">
                <a href="/produtos" class="nav-categorias-toggle <?php if ($paginaAtual == 'produtos.php') echo 'ativo'; ?>" aria-expanded="false" aria-haspopup="true">
                    <span class="<?php echo $isAdminHeader ? 'header-editable' : ''; ?>" data-seccao="nav_produtos"><?php echo htmlspecialchars($headerNavProdutos, ENT_QUOTES, 'UTF-8'); ?></span>
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </a>
                <div class="nav-categorias-dropdown">
                    <a href="/produtos" class="nav-categoria-link nav-categoria-todos">Todos os produtos</a>
                    <?php foreach ($headerCategorias as $headerCategoria):
                        $categoriaNome = $headerCategoria['categoria'] ?? '';
                        if ($categoriaNome === '') continue;
                        $categoriaUrl = '/produtos?categorias%5B%5D=' . rawurlencode($categoriaNome);
                    ?>
                        <a href="<?php echo htmlspecialchars($categoriaUrl, ENT_QUOTES, 'UTF-8'); ?>" class="nav-categoria-link"><?php echo htmlspecialchars($categoriaNome, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endforeach; ?>
                </div>
            </li>
            <li><a href="/contacto" class="<?php if ($paginaAtual == 'contacto.php') echo 'ativo'; ?>"><span class="<?php echo $isAdminHeader ? 'header-editable' : ''; ?>" data-seccao="nav_contacto"><?php echo htmlspecialchars($headerNavContacto, ENT_QUOTES, 'UTF-8'); ?></span></a></li>

            <?php if($isAdminLogado): ?>
                <li><a href="/admin"><b class="<?php echo $isAdminHeader ? 'header-editable' : ''; ?>" data-seccao="nav_admin"><?php echo htmlspecialchars($headerNavAdmin, ENT_QUOTES, 'UTF-8'); ?></b></a></li>
                <?php if($isDevLogado): ?>
                    <li><a href="/dev"><b>Painel Dev</b></a></li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="header-direita">
        <form action="/produtos" method="get" class="form-procura">
            <?php echo ui_search_input('q', 'Pesquisar...', ['value' => $_GET['q'] ?? '', 'aria_label' => 'Procurar produtos']); ?>
            <div class="search-results-container"></div>
        </form>

        <div class="header-acoes">
            <div class="header-account-container">
                <a href="<?php echo $clienteHeaderUrl; ?>" class="header-account-link <?php echo in_array($paginaAtual, ['entrar.php', 'registar.php', 'minha-conta.php', 'minha-conta-encomendas.php', 'minha-conta-encomenda.php', 'minha-conta-moradas.php', 'minha-conta-dados.php']) ? 'is-current' : ''; ?>" title="<?php echo htmlspecialchars($clienteHeaderLabel, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($clienteHeaderLabel, ENT_QUOTES, 'UTF-8'); ?>">
                    <svg class="account-icon-svg" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
                    <span><?php echo htmlspecialchars($clienteHeaderLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <?php if ($isAnyLogado): ?>
                <div class="header-account-dropdown">
                    <div class="account-dropdown-info">
                        <strong>Olá, <?php echo htmlspecialchars($_SESSION['cliente_nome'] ?? $_SESSION['admin_username'] ?? 'Utilizador'); ?></strong>
                    </div>

                    <?php if ($clienteHeaderLogado): ?>
                        <a href="/minha-conta" class="account-dropdown-link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            Área de Cliente
                        </a>
                        <a href="/minha-conta/encomendas" class="account-dropdown-link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                            Encomendas
                        </a>
                    <?php endif; ?>

                    <?php if ($isAdminLogado): ?>
                        <a href="<?php echo $isDevLogado ? '/dev/perfil.php' : '/admin/editar_admin.php?id=' . (int)$_SESSION['admin_id']; ?>" class="account-dropdown-link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
                            Editar Perfil
                        </a>
                    <?php endif; ?>

                    <div class="account-dropdown-divider"></div>
                    
                    <form method="post" action="/sair" style="margin:0; width:100%;">
                        <?php echo csrf_input(); ?>
                        <button type="submit" class="account-dropdown-link logout">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                            Sair
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <button id="search-btn-mobile" class="header-icon-mobile search-toggle-mobile" type="button" aria-label="Abrir pesquisa" aria-expanded="false" aria-controls="search-overlay">
                <svg class="search-icon-svg" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <span class="search-close-icon" aria-hidden="true">
                    <span class="search-close-line"></span>
                    <span class="search-close-line"></span>
                </span>
            </button>
            <?php if ($paginaAtual !== 'carrinho.php'): ?>
            <a href="/carrinho" class="icon-carrinho" title="Ver Carrinho" aria-label="Abrir carrinho" aria-expanded="false" aria-controls="side-cart">
                <svg class="cart-icon-svg" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                <span class="cart-close-icon" aria-hidden="true">
                    <span class="cart-close-line"></span>
                    <span class="cart-close-line"></span>
                </span>
                <div class="cart-badge-wrapper">
                    <span id="contagem-carrinho">0</span>
                </div>
            </a>
            <?php endif; ?>
            <?php if($isDevLogado): ?>
                <button type="button" id="btn-header-cache" class="header-icon-dev" title="Limpar Cache Global">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"></path><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                </button>
            <?php endif; ?>
            <button id="hamburger-btn" class="hamburger-btn" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="main-nav-mobile">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
        </div>
    </div>
</header>

<div id="search-overlay" class="search-overlay">
    <button id="fechar-pesquisa-btn" class="fechar-pesquisa-btn" aria-label="Fechar pesquisa">&times;</button>
    <div class="search-panel">
        <div class="search-panel-heading">
            <span>Pesquisa</span>
            <small>Produtos, categorias e promoções</small>
        </div>
        <form action="/produtos" method="get" class="form-procura-overlay">
            <?php echo ui_search_input('q', 'Pesquisar...', ['value' => $_GET['q'] ?? '', 'aria_label' => 'Procurar produtos']); ?>
            <div class="search-results-container"></div>
        </form>
        <div class="search-assist-panel">
            <div class="search-history-section">
                <div class="search-assist-title">
                    <span>Pesquisas recentes</span>
                    <button type="button" class="search-history-clear">Limpar</button>
                </div>
                <div class="search-history-list"></div>
            </div>
            <div class="search-quick-section">
                <div class="search-assist-title"><span>Atalhos rápidos</span></div>
                <div class="search-quick-links">
                    <a href="/produtos">Todos os produtos</a>
                    <a href="/produtos?promocao=1">Promoções</a>
                    <a href="/consultar-encomenda">Acompanhar encomenda</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="menu-overlay" class="menu-overlay"></div>
<nav id="main-nav-mobile" class="main-nav-mobile">
    <div class="nav-mobile-header">
        <a href="/" class="nav-mobile-logo <?php echo $isAdminHeader ? 'header-editable' : ''; ?>" data-seccao="logo_src">
            <img src="<?php echo htmlspecialchars($headerLogoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($headerLogoAlt, ENT_QUOTES, 'UTF-8'); ?>">
        </a>
    </div>

    <div class="nav-mobile-body">
        <ul>
            <li>
                <a href="/" class="<?php if ($paginaAtual == 'index.php') echo 'ativo'; ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <span class="<?php echo $isAdminHeader ? 'header-editable' : ''; ?>" data-seccao="nav_home"><?php echo htmlspecialchars($headerNavHome, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            </li>
            <li class="nav-mobile-categorias">
                <button type="button" class="nav-mobile-category-toggle <?php if ($paginaAtual == 'produtos.php') echo 'ativo'; ?>" aria-expanded="false">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/><path d="M3 9h18"/></svg>
                    <span class="<?php echo $isAdminHeader ? 'header-editable' : ''; ?>" data-seccao="nav_produtos"><?php echo htmlspecialchars($headerNavProdutos, ENT_QUOTES, 'UTF-8'); ?></span>
                    <svg class="nav-mobile-category-chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>
                <div class="nav-mobile-category-list">
                    <a href="/produtos">Todos os produtos</a>
                    <?php foreach ($headerCategorias as $headerCategoria):
                        $categoriaNome = $headerCategoria['categoria'] ?? '';
                        if ($categoriaNome === '') continue;
                        $categoriaUrl = '/produtos?categorias%5B%5D=' . rawurlencode($categoriaNome);
                    ?>
                        <a href="<?php echo htmlspecialchars($categoriaUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($categoriaNome, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endforeach; ?>
                </div>
            </li>
            <li>
                <a href="/contacto" class="<?php if ($paginaAtual == 'contacto.php') echo 'ativo'; ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l2.18-2.18a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <span class="<?php echo $isAdminHeader ? 'header-editable' : ''; ?>" data-seccao="nav_contacto"><?php echo htmlspecialchars($headerNavContacto, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo $clienteHeaderUrl; ?>" class="<?php if (in_array($paginaAtual, ['entrar.php', 'registar.php', 'minha-conta.php', 'minha-conta-encomendas.php', 'minha-conta-encomenda.php', 'minha-conta-moradas.php', 'minha-conta-dados.php'])) echo 'ativo'; ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
                    <span><?php echo htmlspecialchars($clienteHeaderLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            </li>
            <?php if ($clienteHeaderLogado): ?>
                <li>
                    <form method="post" action="/sair" style="margin:0; width:100%;">
                        <?php echo csrf_input(); ?>
                        <button type="submit" class="nav-mobile-logout-btn" style="display:flex; align-items:center; gap:12px; width:100%; padding:12px 16px; background:none; border:none; font:inherit; color:inherit; text-align:left; cursor:pointer;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Sair
                        </button>
                    </form>
                </li>
            <?php endif; ?>

            <?php if(isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true): ?>
                <li class="nav-mobile-divider"></li>
                <li>
                    <a href="/admin">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                        <b class="<?php echo $isAdminHeader ? 'header-editable' : ''; ?>" data-seccao="nav_admin"><?php echo htmlspecialchars($headerNavAdmin, ENT_QUOTES, 'UTF-8'); ?></b>
                    </a>
                </li>
                <?php if($isDevLogado): ?>
                <li>
                    <a href="/dev">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                        <b>Painel Dev</b>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <form method="post" action="/sair" style="margin:0; width:100%;">
                        <?php echo csrf_input(); ?>
                        <button type="submit" class="nav-mobile-logout-btn" style="display:flex; align-items:center; gap:12px; width:100%; padding:12px 16px; background:none; border:none; font:inherit; color:inherit; text-align:left; cursor:pointer;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Sair
                        </button>
                    </form>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="nav-mobile-footer">
        <ul class="nav-mobile-footer-links">
            <li><a href="/envios">Portes e Envios</a></li>
            <li><a href="/trocas">Trocas e Devoluções</a></li>
            <li><a href="/consultar-encomenda">Acompanhar Encomenda</a></li>
        </ul>
    </div>
</nav>
