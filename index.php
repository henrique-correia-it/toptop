<?php
// Inclui o header da página
// A variável $versao_global é definida dentro do header.php
$titulo_pagina = "Página Inicial";
$descricao_pagina = "Bem-vindo à TopTop (Top Top)! Descubra as últimas tendências em roupa de mulher, moda e acessórios. Envios rápidos para todo o país.";
require_once __DIR__ . '/config/url_helpers.php';
require_once __DIR__ . '/config/formatters.php';

$_ld = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebSite',
            '@id' => 'https://www.toptop.pt/#website',
            'url' => 'https://www.toptop.pt/',
            'name' => 'TopTop',
            'description' => $descricao_pagina,
            'inLanguage' => 'pt-PT',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => 'https://www.toptop.pt/produtos.php?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ],
        [
            '@type' => 'Organization',
            '@id' => 'https://www.toptop.pt/#organization',
            'name' => 'TopTop',
            'url' => 'https://www.toptop.pt/',
            'logo' => ['@type' => 'ImageObject', 'url' => 'https://www.toptop.pt/public/assets/logo1.jpg'],
            'contactPoint' => ['@type' => 'ContactPoint', 'contactType' => 'customer service', 'url' => 'https://www.toptop.pt/contacto.php'],
        ],
    ],
];
$head_extra = '<script type="application/ld+json">' . json_encode($_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

include 'templates/header.php';
$isGlobalEditMode = $isAdminHeader ?? false;
$portesGratisHome = number_format(
    $portes_gratis_minimo,
    (float)(int)$portes_gratis_minimo === (float)$portes_gratis_minimo ? 0 : 2,
    ',',
    '.'
);

// Buscar categorias visíveis (com foto de capa)
$cats_r = $conn->query("SELECT id, nome, foto_capa, foto_posicao, foto_zoom, foto_mobile_posicao, foto_mobile_zoom FROM categorias WHERE visivel = 1 ORDER BY ordem ASC, id ASC LIMIT 8");
if ($cats_r !== false && $cats_r->num_rows > 0) {
    $cats_visiveis = $cats_r->fetch_all(MYSQLI_ASSOC);
} else {
    $cats_visiveis = [];
}

// Buscar configurações da loja para o layout bento
$saved_layouts = json_decode(getLojaConfig('home_bento_layouts', '{}'), true) ?: [];

// Mapeamento de variantes (Igual ao do gerir_categorias.php)
$bento_variants = [
    4 => [
        ['large', 'wide', '', ''],
        ['wide', 'wide', 'wide', 'wide'],
        ['large', '', '', ''],
        ['', '', '', '']
    ],
    5 => [
        ['large', '', '', 'wide', ''],
        ['wide', 'wide', 'wide', '', ''],
        ['large', '', '', '', ''],
        ['', '', '', '', '']
    ],
    6 => [
        ['wide', 'wide', 'wide', '', '', ''],
        ['large', '', '', '', '', ''],
        ['wide', 'wide', '', '', '', ''],
        ['', '', '', '', '', '']
    ],
    7 => [
        ['large', 'wide', '', '', '', '', ''],
        ['wide', 'wide', 'wide', 'wide', '', '', ''],
        ['large', '', '', '', '', '', ''],
        ['', '', '', '', '', '', '']
    ],
    8 => [
        ['large', 'wide', 'wide', '', '', '', '', ''],
        ['wide', 'wide', 'wide', 'wide', 'wide', 'wide', '', ''],
        ['large', '', '', '', '', '', '', ''],
        ['', '', '', '', '', '', '', '']
    ]
];

// Determinar layout bento conforme número de categorias (Máximo 8)
$n_cats = count($cats_visiveis);
if ($n_cats == 1) {
    $bento_container_class = 'count-1';
    $bento_card_classes    = [''];
} elseif ($n_cats == 2) {
    $bento_container_class = 'count-2';
    $bento_card_classes    = ['', ''];
} elseif ($n_cats == 3) {
    $bento_container_class = 'count-3';
    $bento_card_classes    = ['', '', ''];
} elseif ($n_cats >= 4 && $n_cats <= 8) {
    $bento_container_class = 'count-' . $n_cats;
    $variant = $saved_layouts[$n_cats] ?? 1;
    $bento_card_classes = $bento_variants[$n_cats][$variant - 1] ?? $bento_variants[$n_cats][0];
} else {
    $bento_container_class = 'count-many';
    $bento_card_classes    = array_fill(0, $n_cats, '');
}

// Buscar as 4 últimas novidades
$recentes_query = $conn->query("
    SELECT p.*, 
    (SELECT pi.nome_ficheiro FROM produto_imagens pi WHERE pi.produto_id = p.id ORDER BY FIELD(pi.nome_ficheiro, p.foto_principal) DESC, pi.id ASC LIMIT 1) as foto_exibicao,
    (SELECT SUM(pv.quantidade) FROM produto_variacoes pv WHERE pv.produto_id = p.id) as stock_total
    FROM produtos p 
    WHERE p.ativo = 1
      AND EXISTS (
          SELECT 1
          FROM produto_variacoes pv_stock
          WHERE pv_stock.produto_id = p.id
            AND pv_stock.quantidade > 0
      )
    ORDER BY p.id DESC 
    LIMIT 4
");
$recentes = $recentes_query ? $recentes_query->fetch_all(MYSQLI_ASSOC) : [];
?>

<!-- ANIMAÇÃO DE CORTINAS PREMIUM - HOME -->
<div class="premium-preloader" id="premium-preloader">
    <!-- Camadas de fundo (Parallax) -->
    <div class="curtain-panel bg-layer left"></div>
    <div class="curtain-panel bg-layer right"></div>
    
    <!-- Camadas principais -->
    <div class="curtain-panel main-layer left"></div>
    <div class="curtain-panel main-layer right"></div>
    
    <!-- Linha de detalhe ao centro -->
    <div class="curtain-glow-line" id="curtain-line"></div>
    
    <!-- Conteúdo -->
    <div class="preloader-content" id="preloader-content">
        <img src="<?php echo htmlspecialchars($headerLogoSrc ?? '/public/assets/logo1.jpg', ENT_QUOTES, 'UTF-8'); ?>" alt="TopTop" class="preloader-logo-img">
    </div>
</div>

<script>
(function() {
    var preloader = document.getElementById('premium-preloader');
    if (!preloader) return;
    
    var lastVisit = localStorage.getItem('toptop_last_curtain');
    var now = new Date().getTime();
    var tenMinutes = 10 * 60 * 1000; // 10 minutos em milissegundos
    
    // Se a pessoa já viu as cortinas há menos de 10 minutos
    if (lastVisit && (now - parseInt(lastVisit) < tenMinutes)) {
        preloader.style.display = 'none'; // Esconde instantaneamente sem "piscar"
    } else {
        // Se já passaram 10 mins ou é a primeira visita, guarda o momento atual
        localStorage.setItem('toptop_last_curtain', now);
        
        // E deixa correr a animação normalmente quando a página carrega
        document.addEventListener("DOMContentLoaded", function() {
            setTimeout(function() {
                preloader.classList.add('open');
                setTimeout(() => {
                    preloader.remove();
                }, 1600); 
            }, 800);
        });
    }
})();
</script>
<!-- FIM ANIMAÇÃO DE CORTINAS PREMIUM -->

<main class="main-home animate-entry">

    <!-- HERO SECTION -->
    <section class="hero-section <?php echo $isGlobalEditMode ? 'home-editable' : ''; ?>" data-chave="home_hero_bg">
        <div class="hero-bg" style="background-image: url('<?php echo getLojaConfig('home_hero_bg', '/public/assets/hero.avif'); ?>?v=<?php echo $versao_global; ?>');"></div>
        <div class="hero-content">
            <h1 class="home-editable" data-chave="home_hero_title"><?php echo htmlspecialchars(getLojaConfig('home_hero_title', 'O Teu Estilo Começa Aqui')); ?></h1>
            <p class="home-editable" data-chave="home_hero_subtitle"><?php echo htmlspecialchars(getLojaConfig('home_hero_subtitle', 'Descubra peças exclusivas para um look inconfundível. Alta qualidade, design moderno e conforto em cada detalhe.')); ?></p>
            <a href="produtos.php" class="button-hero"><?php echo htmlspecialchars(getLojaConfig('home_hero_btn', 'Ver Nova Coleção')); ?></a>
        </div>
        <div class="hero-scroll" aria-hidden="true">
            <span class="hero-scroll-line"></span>
            <span>Descer</span>
        </div>
    </section>

    <!-- MARQUEE / FAIXA EDITORIAL -->
    <div class="home-marquee" data-reveal>
        <div class="home-marquee__track" aria-hidden="true">
            <span>Nova Coleção</span><span>Edições Limitadas</span><span>Estilo Atemporal</span><span>Envios Rápidos</span><span>Feito para Ti</span>
            <span>Nova Coleção</span><span>Edições Limitadas</span><span>Estilo Atemporal</span><span>Envios Rápidos</span><span>Feito para Ti</span>
        </div>
    </div>

    <!-- TRUST BADGES -->
    <section class="trust-badges" data-reveal>
        <a href="/envios.php" class="trust-badge trust-badge--shipping" aria-label="Consultar condições dos portes grátis">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
            <div class="trust-badge-info">
                <h4>Portes grátis</h4>
                <p>Portugal Continental · desde <?php echo htmlspecialchars($portesGratisHome, ENT_QUOTES, 'UTF-8'); ?> €</p>
            </div>
        </a>
        <div class="trust-badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            <div class="trust-badge-info">
                <h4 class="home-editable" data-chave="home_trust_2_title"><?php echo htmlspecialchars(getLojaConfig('home_trust_2_title', 'Pagamento Seguro')); ?></h4>
                <p class="home-editable" data-chave="home_trust_2_desc"><?php echo htmlspecialchars(getLojaConfig('home_trust_2_desc', '100% Protegido')); ?></p>
            </div>
        </div>
        <div class="trust-badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
            <div class="trust-badge-info">
                <h4 class="home-editable" data-chave="home_trust_3_title"><?php echo htmlspecialchars(getLojaConfig('home_trust_3_title', 'Apoio ao Cliente')); ?></h4>
                <p class="home-editable" data-chave="home_trust_3_desc"><?php echo htmlspecialchars(getLojaConfig('home_trust_3_desc', 'Sempre disponíveis')); ?></p>
            </div>
        </div>
    </section>

    <!-- BENTO GRID CATEGORIAS -->
    <?php if (!empty($cats_visiveis)): ?>
    <section class="featured-categories <?php echo $isGlobalEditMode ? 'home-category-editor' : ''; ?>">
        <div class="home-section-head" data-reveal>
            <p class="section-kicker">Explorar</p>
            <h2 class="section-title home-editable" data-chave="home_cat_title"><?php echo htmlspecialchars(getLojaConfig('home_cat_title', 'As Nossas Categorias')); ?></h2>
        </div>
        <?php if ($isGlobalEditMode): ?>
            <div class="home-bento-editbar" id="home-bento-editbar">
                <div class="bento-layout-selector home-layout-selector" id="home-layout-selector" style="display:none;">
                    <span class="layout-label">Layout:</span>
                    <div class="layout-btns">
                        <button type="button" class="layout-btn" data-variant="1">1</button>
                        <button type="button" class="layout-btn" data-variant="2">2</button>
                        <button type="button" class="layout-btn" data-variant="3">3</button>
                        <button type="button" class="layout-btn" data-variant="4">4</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="category-bento <?php echo $bento_container_class; ?>"<?php echo $isGlobalEditMode ? ' id="home-bento-grid"' : ''; ?>>
            <?php foreach ($cats_visiveis as $i => $cat):
                $card_class  = $bento_card_classes[$i] ?? '';
                $cat_nome    = $cat['nome'];
                $cat_url     = 'produtos.php?q=&categorias%5B%5D=' . urlencode($cat_nome);
                $cat_foto    = $cat['foto_capa'] ? '/public/' . htmlspecialchars($cat['foto_capa']) : '';
                $cat_pos     = $cat['foto_posicao'] ?? '50% 50%';
                $card_tag    = $isGlobalEditMode ? 'div' : 'a';
                $href_attr   = $isGlobalEditMode ? '' : ' href="' . htmlspecialchars($cat_url, ENT_QUOTES, 'UTF-8') . '"';
            ?>
            <<?php echo $card_tag . $href_attr; ?>
               class="category-card <?php echo $card_class; ?>"
               data-cat-id="<?php echo (int)$cat['id']; ?>"
               data-pos="<?php echo htmlspecialchars($cat_pos, ENT_QUOTES, 'UTF-8'); ?>"
               data-zoom="<?php echo htmlspecialchars($cat['foto_zoom'] ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
                <style>
                    @media (max-width: 768px) {
                        .cat-img-<?php echo $i; ?> {
                            object-position: <?php echo $cat['foto_mobile_posicao'] ?? '50% 50%'; ?> !important;
                            transform: scale(<?php echo $cat['foto_mobile_zoom'] ?? '1.0'; ?>) !important;
                        }
                    }
                </style>
                <?php if ($isGlobalEditMode): ?>
                    <button type="button" class="btn-edit-framing" data-cat-id="<?php echo (int)$cat['id']; ?>">Ajustar</button>
                <?php endif; ?>
                <?php if ($cat_foto): ?>
                    <img src="<?php echo $cat_foto; ?>"
                         alt="<?php echo htmlspecialchars($cat_nome); ?>"
                         loading="lazy"
                         width="800" height="600"
                         class="cat-img-<?php echo $i; ?>"
                         style="object-position: <?php echo $cat_pos; ?>; transform: scale(<?php echo $cat['foto_zoom'] ?? '1.0'; ?>);">
                <?php else: ?>
                    <div style="width:100%;height:100%;background:#e2e8f0;"></div>
                <?php endif; ?>
                <div class="category-title <?php echo $isGlobalEditMode ? 'category-name-editable' : ''; ?>" data-cat-id="<?php echo (int)$cat['id']; ?>">
                    <h3><?php echo htmlspecialchars($cat_nome); ?></h3>
                </div>
            </<?php echo $card_tag; ?>>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ÚLTIMAS NOVIDADES -->
    <?php if(!empty($recentes)): ?>
    <section class="new-arrivals">
        <div class="home-section-head" data-reveal>
            <p class="section-kicker">Acabado de Chegar</p>
            <h2 class="section-title home-editable" data-chave="home_new_title"><?php echo htmlspecialchars(getLojaConfig('home_new_title', 'Últimas Novidades')); ?></h2>
        </div>
        <div class="produtos-grid">
            <?php foreach($recentes as $row): 
                $foto_principal = $row['foto_exibicao'] ?? 'default.jpg';
                $slug = criar_slug($row['nome'] . '-' . $row['id']);
                $stock_para_js = (int) ($row['stock_total'] ?? 0);
                $esgotado = ($stock_para_js <= 0);
                $tag_principal = $esgotado ? 'div' : 'a';
                $link_atributo = !$esgotado ? 'href="/produto/' . $slug . '"' : '';
            ?>
                <<?php echo $tag_principal; ?> <?php echo $link_atributo; ?> class="produto <?php echo $esgotado ? 'esgotado' : ''; ?>">
                    
                    <?php if (!empty($row['preco_promocional']) && $row['preco_promocional'] > 0): ?>
                        <span class="badge promocao">Promoção</span>
                    <?php endif; ?>
                    
                    <div class="produto-imagem-container">
                        <img src="/public/images/<?php echo htmlspecialchars($foto_principal); ?>" alt="<?php echo htmlspecialchars($row['nome']); ?>" class="imagem-principal" loading="lazy" width="600" height="800">
                        <?php if ($esgotado): ?>
                            <div class="badge-esgotado-overlay">Esgotado</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="produto-info">
                        <h4><?php echo htmlspecialchars($row['nome']); ?></h4>
                        <?php if (!empty($row['preco_promocional']) && $row['preco_promocional'] > 0): ?>
                            <p class="preco-promocao">
                                <del><?php echo format_money($row['preco']); ?></del> 
                                <strong><?php echo format_money($row['preco_promocional']); ?></strong>
                            </p>
                        <?php else: ?>
                            <p><?php echo format_money($row['preco']); ?></p>
                        <?php endif; ?>
                    </div>

                </<?php echo $tag_principal; ?>>
            <?php endforeach; ?>
        </div>
        <div class="btn-ver-mais-container" data-reveal>
            <a href="produtos.php" class="btn-ver-mais">Ver Todos os Produtos</a>
        </div>
    </section>
    <?php endif; ?>

</main>

<script>
// Reveal on scroll — apenas design (progressive enhancement, desativado no modo de edição)
(function () {
    var main = document.querySelector('.main-home');
    if (!main) return;
    if (document.body.classList.contains('global-edit-mode-active')) return;
    if (!('IntersectionObserver' in window)) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    main.classList.add('reveal-armed');
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });

    main.querySelectorAll('[data-reveal]').forEach(function (el) { io.observe(el); });
})();
</script>

<?php if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true && in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor']) && isset($_SESSION['global_edit_mode']) && $_SESSION['global_edit_mode'] === true): ?>
    <div id="modalEditorHome" class="qe-modal footer-editor-modal">
        <div class="qe-card">
            <button type="button" class="btn-close-unified qe-close" onclick="fecharEditorHome()">&times;</button>
            <div class="form-card-header">
                <div class="card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4L18.5 2.5z"></path></svg></div>
                <h3>Editar Texto Inicial</h3>
            </div>
            <div class="form-card-body">
                <input type="hidden" id="home-edit-chave">
                <div id="home-edit-text-fields">
                    <div class="form-group">
                        <label>Conteúdo</label>
                        <textarea id="home-edit-conteudo" rows="4" class="admin-textarea"></textarea>
                    </div>
                </div>

                <div id="home-edit-image-fields" style="display: none;">
                    <div class="header-logo-preview" id="home-hero-preview-container" style="height: 120px; background-size: cover; background-position: center; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e2e8f0;">
                    </div>
                    <div class="form-group">
                        <label class="custom-file-upload" style="display: block; border: 2px dashed #cbd5e1; border-radius: 8px; padding: 25px 20px; text-align: center; cursor: pointer; background: #f8fafc; transition: all 0.2s ease;">
                            <input type="file" id="home-edit-imagem" accept="image/jpeg,image/png,image/webp,image/avif" style="display: none;">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" style="margin-bottom: 10px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                            <div style="font-weight: 500; color: #334155; font-size: 0.95rem;">Clique para escolher a nova imagem</div>
                            <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 6px;">Formatos suportados: JPG, PNG, WebP, AVIF. Max: 5MB</div>
                        </label>
                    </div>
                </div>

                <div class="form-footer-actions">
                    <button type="button" class="button voltar-btn" onclick="fecharEditorHome()">Cancelar</button>
                    <button type="button" class="button add-btn" onclick="guardarAlteracaoHome()">Guardar Alterações</button>
                </div>
            </div>
        </div>
    </div>

    <div id="modalEditorCategoriaHome" class="qe-modal footer-editor-modal">
        <div class="qe-card">
            <button type="button" class="btn-close-unified qe-close" onclick="fecharEditorCategoriaHome()">&times;</button>
            <div class="form-card-header">
                <div class="card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4L18.5 2.5z"></path></svg></div>
                <h3>Editar Categoria</h3>
            </div>
            <div class="form-card-body">
                <input type="hidden" id="home-category-edit-id">
                <div class="form-group">
                    <label>Nome da categoria</label>
                    <input type="text" id="home-category-edit-nome" class="admin-input-style" maxlength="80">
                </div>
                <div class="form-footer-actions">
                    <button type="button" class="button voltar-btn" onclick="fecharEditorCategoriaHome()">Cancelar</button>
                    <button type="button" class="button add-btn" onclick="guardarCategoriaHome()">Guardar Alterações</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentHomeElement = null;
        let currentHomeCategoryTitle = null;

        document.addEventListener('click', (e) => {
            const editable = e.target.closest('.home-editable');
            if (editable && editable.classList.contains('inline-editavel')) return; // edição inline trata disto (sem modal)
            if (editable) {
                const rect = editable.getBoundingClientRect();
                const clickX = e.clientX - rect.left;
                const clickY = e.clientY - rect.top;

                // O ícone está em posições diferentes no Hero vs outros elementos
                const isHeroBg = editable.classList.contains('hero-section');
                const hitAreaSize = isHeroBg ? 60 : 35; // Hero icon está a 30px da borda, outros a -10px
                
                if (clickX >= rect.width - hitAreaSize && clickY <= hitAreaSize) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    currentHomeElement = editable;
                    const chave = editable.dataset.chave;
                    document.getElementById('home-edit-chave').value = chave;

                    if (chave === 'home_hero_bg') {
                        document.getElementById('home-edit-text-fields').style.display = 'none';
                        document.getElementById('home-edit-image-fields').style.display = 'block';
                        document.getElementById('home-edit-imagem').value = '';
                        
                        // Preview da imagem atual
                        const bgElement = editable.querySelector('.hero-bg') || editable;
                        const currentBg = window.getComputedStyle(bgElement).backgroundImage;
                        document.getElementById('home-hero-preview-container').style.backgroundImage = currentBg;
                    } else {
                        document.getElementById('home-edit-text-fields').style.display = 'block';
                        document.getElementById('home-edit-image-fields').style.display = 'none';
                        document.getElementById('home-edit-conteudo').value = editable.innerText.trim();
                    }

                    document.querySelectorAll('.home-editable.editing-current, .header-editable.editing-current, .footer-editable.editing-current').forEach(el => el.classList.remove('editing-current'));
                    editable.classList.add('editing-current');

                    document.getElementById('modalEditorHome').classList.add('active');
                    document.getElementById('modalEditorHome').style.display = 'flex';
                }
            }
        });

        function fecharEditorHome() {
            const modal = document.getElementById('modalEditorHome');
            if (modal) {
                modal.classList.remove('active');
                modal.style.display = 'none';
            }
            document.querySelectorAll('.home-editable.editing-current').forEach(el => el.classList.remove('editing-current'));
            currentHomeElement = null;
        }

        function fecharEditorCategoriaHome() {
            const modal = document.getElementById('modalEditorCategoriaHome');
            if (modal) {
                modal.classList.remove('active');
                modal.style.display = 'none';
            }
            document.querySelectorAll('.category-name-editable.editing-current').forEach(el => el.classList.remove('editing-current'));
            currentHomeCategoryTitle = null;
        }

        async function guardarCategoriaHome() {
            const id = parseInt(document.getElementById('home-category-edit-id').value, 10);
            const nome = document.getElementById('home-category-edit-nome').value.trim();
            const btn = document.querySelector('#modalEditorCategoriaHome .add-btn');
            const origHTML = btn.innerHTML;

            if (!nome) {
                if (typeof mostrarPopup === 'function') mostrarPopup('O nome não pode estar vazio.', 'erro');
                return;
            }

            btn.innerHTML = 'A Guardar...';
            btn.disabled = true;

            try {
                const data = await homeAjaxCat({ acao: 'editar', id, nome });
                if (data.sucesso) {
                    currentHomeCategoryTitle?.querySelector('h3') && (currentHomeCategoryTitle.querySelector('h3').textContent = nome);
                    fecharEditorCategoriaHome();
                    if (typeof mostrarPopup === 'function') mostrarPopup('Categoria atualizada.', 'sucesso');
                } else if (typeof mostrarPopup === 'function') {
                    mostrarPopup(data.mensagem || 'Erro ao atualizar categoria.', 'erro');
                }
            } catch (err) {
                if (typeof mostrarPopup === 'function') mostrarPopup('Erro de ligação.', 'erro');
            } finally {
                btn.innerHTML = origHTML;
                btn.disabled = false;
            }
        }

        async function guardarAlteracaoHome() {
            const chave = document.getElementById('home-edit-chave').value;
            const csrf_token = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
            const btn = document.querySelector('#modalEditorHome .add-btn');
            const origHTML = btn.innerHTML;

            btn.innerHTML = 'A Guardar...';
            btn.disabled = true;

            try {
                let response;
                if (chave === 'home_hero_bg') {
                    const input = document.getElementById('home-edit-imagem');
                    if (!input.files || !input.files[0]) {
                        fecharEditorHome();
                        btn.innerHTML = origHTML;
                        btn.disabled = false;
                        return;
                    }

                    const formData = new FormData();
                    formData.append('chave', 'home_hero_bg');
                    formData.append('csrf_token', csrf_token);
                    formData.append('imagem', input.files[0]);

                    response = await fetch('/dev/ajax_save_home.php', { method: 'POST', body: formData });
                } else {
                    const conteudo = document.getElementById('home-edit-conteudo').value.trim();
                    response = await fetch('/dev/ajax_save_home.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ chave, conteudo, csrf_token })
                    });
                }

                const data = await response.json();

                if (data.sucesso) {
                    if (currentHomeElement) {
                        if (chave === 'home_hero_bg') {
                            const bg = currentHomeElement.querySelector('.hero-bg') || currentHomeElement;
                            bg.style.backgroundImage = `url('${data.url}?v=${Date.now()}')`;
                        } else {
                            currentHomeElement.innerText = document.getElementById('home-edit-conteudo').value.trim();
                        }
                    }
                    fecharEditorHome();
                    if(typeof mostrarPopup === 'function') mostrarPopup(data.mensagem, 'sucesso');
                } else {
                    if(typeof mostrarPopup === 'function') mostrarPopup(data.mensagem, 'erro');
                }
            } catch (err) {
                if(typeof mostrarPopup === 'function') mostrarPopup('Erro de comunicação.', 'erro');
            } finally {
                btn.innerHTML = origHTML;
                btn.disabled = false;
            }
        }

        // Preview de imagem ao selecionar ficheiro
        document.getElementById('home-edit-imagem')?.addEventListener('change', function(e) {
            const file = e.target.files && e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    document.getElementById('home-hero-preview-container').style.backgroundImage = `url('${ev.target.result}')`;
                };
                reader.readAsDataURL(file);
            }
        });

        const homeBentoGrid = document.getElementById('home-bento-grid');
        const homeLayoutSelector = document.getElementById('home-layout-selector');
        const homeBentoDesktop = window.matchMedia('(min-width: 993px)');
        let homeBentoSortable = null;
        let homeActivePicker = null;
        let homeBentoInitRetry = null;
        let homeSavedLayouts = <?php echo json_encode($saved_layouts ?: (object)[]); ?>;

        const homeBentoVariants = <?php echo json_encode($bento_variants); ?>;

        function homeAjaxCat(dados) {
            return fetch('/admin/ajax_categoria.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...dados, csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>' })
            }).then(r => r.json());
        }

        function homeBuildBentoClasses(n) {
            if (n === 1) return { cls: 'count-1', cards: [''] };
            if (n === 2) return { cls: 'count-2', cards: ['', ''] };
            if (n === 3) return { cls: 'count-3', cards: ['', '', ''] };
            if (n >= 4 && n <= 8) {
                const variant = parseInt(homeSavedLayouts[n] || 1, 10);
                const variants = homeBentoVariants[n] || homeBentoVariants[String(n)] || [];
                return {
                    cls: 'count-' + n,
                    cards: variants[variant - 1] || variants[0] || Array(n).fill('')
                };
            }
            return { cls: 'count-many', cards: Array(n).fill('') };
        }

        function homeUpdateLayoutSelector(n) {
            if (!homeLayoutSelector) return;
            const editbar = document.getElementById('home-bento-editbar');
            if (n >= 4 && n <= 8 && homeBentoDesktop.matches) {
                if (editbar) editbar.style.display = 'flex';
                homeLayoutSelector.style.display = 'flex';
                const variant = parseInt(homeSavedLayouts[n] || 1, 10);
                homeLayoutSelector.querySelectorAll('.layout-btn').forEach(btn => {
                    btn.classList.toggle('active', parseInt(btn.dataset.variant, 10) === variant);
                });
            } else {
                if (editbar) editbar.style.display = 'none';
                homeLayoutSelector.style.display = 'none';
            }
        }

        function homeApplyBentoClasses() {
            if (!homeBentoGrid) return;
            const cards = Array.from(homeBentoGrid.querySelectorAll('.category-card'));
            const layout = homeBuildBentoClasses(cards.length);
            homeBentoGrid.classList.remove('count-1', 'count-2', 'count-3', 'count-4', 'count-5', 'count-6', 'count-7', 'count-8', 'count-many');
            homeBentoGrid.classList.add(layout.cls);
            cards.forEach((card, i) => {
                card.classList.remove('large', 'wide');
                if (layout.cards[i]) card.classList.add(layout.cards[i]);
            });
            homeUpdateLayoutSelector(cards.length);
        }

        function homeSaveLayout(variant) {
            const count = homeBentoGrid ? homeBentoGrid.querySelectorAll('.category-card').length : 0;
            if (count < 4 || count > 8) return;

            homeSavedLayouts[count] = variant;
            homeApplyBentoClasses();

            const fd = new FormData();
            fd.append('home_bento_layouts', JSON.stringify(homeSavedLayouts));
            fd.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');

            fetch('/admin/ajax_salvar_layouts.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (typeof mostrarPopup === 'function') {
                        mostrarPopup(data.sucesso ? 'Layout atualizado.' : (data.mensagem || 'Erro ao guardar layout.'), data.sucesso ? 'sucesso' : 'erro');
                    }
                })
                .catch(() => {
                    if (typeof mostrarPopup === 'function') mostrarPopup('Erro de ligação.', 'erro');
                });
        }

        homeLayoutSelector?.querySelectorAll('.layout-btn').forEach(btn => {
            btn.addEventListener('click', () => homeSaveLayout(parseInt(btn.dataset.variant, 10)));
        });

        function homeInitBentoEditor() {
            if (!homeBentoGrid) return false;
            homeApplyBentoClasses();
            if (!homeBentoDesktop.matches) return false;
            if (typeof Sortable === 'undefined') return false;
            if (homeBentoSortable) return true;

            homeBentoSortable = Sortable.create(homeBentoGrid, {
                animation: 150,
                ghostClass: 'preview-sortable-ghost',
                filter: '.btn-edit-framing, .category-name-editable, .focal-picker-overlay',
                preventOnFilter: true,
                onStart: () => homeCloseFocalPicker(),
                onChange: homeApplyBentoClasses,
                onEnd: () => {
                    const ids = Array.from(homeBentoGrid.querySelectorAll('.category-card')).map(card => parseInt(card.dataset.catId, 10));
                    homeApplyBentoClasses();
                    homeAjaxCat({ acao: 'reordenar', ids })
                        .then(data => {
                            if (typeof mostrarPopup === 'function') {
                                mostrarPopup(data.sucesso ? 'Ordem das categorias guardada.' : (data.mensagem || 'Erro ao guardar ordem.'), data.sucesso ? 'sucesso' : 'erro');
                            }
                        })
                        .catch(() => {
                            if (typeof mostrarPopup === 'function') mostrarPopup('Erro de ligação.', 'erro');
                        });
                }
            });
            return true;
        }

        function homeQueueBentoEditorInit() {
            if (!homeBentoGrid) return;
            homeApplyBentoClasses();
            if (!homeBentoDesktop.matches || homeInitBentoEditor() || homeBentoInitRetry) return;

            let attempts = 0;
            const retryInit = () => {
                homeBentoInitRetry = null;
                if (!homeBentoGrid || !homeBentoDesktop.matches || homeInitBentoEditor()) return;
                attempts += 1;
                if (attempts < 20) {
                    homeBentoInitRetry = window.setTimeout(retryInit, 150);
                }
            };

            if (document.readyState === 'complete') {
                retryInit();
            } else {
                window.addEventListener('load', retryInit, { once: true });
            }
        }

        function homeDestroyBentoEditorOnMobile() {
            if (homeBentoDesktop.matches) {
                homeQueueBentoEditorInit();
                return;
            }
            homeCloseFocalPicker();
            if (homeBentoInitRetry) {
                window.clearTimeout(homeBentoInitRetry);
                homeBentoInitRetry = null;
            }
            if (homeBentoSortable) {
                homeBentoSortable.destroy();
                homeBentoSortable = null;
            }
            homeUpdateLayoutSelector(0);
        }

        function homeOpenFocalPicker(card) {
            if (!homeBentoDesktop.matches) return;
            homeCloseFocalPicker();

            const img = card.querySelector('img');
            if (!img) {
                if (typeof mostrarPopup === 'function') mostrarPopup('Adiciona primeiro uma foto de capa a esta categoria.', 'info');
                return;
            }

            const catId = parseInt(card.dataset.catId, 10);
            const originalPos = card.dataset.pos || img.style.objectPosition || '50% 50%';
            const originalZoom = parseFloat(card.dataset.zoom || '1') || 1;
            const parts = originalPos.split(' ');
            let curX = parseFloat(parts[0]) || 50;
            let curY = parseFloat(parts[1]) || 50;
            let curZoom = originalZoom;

            const overlay = document.createElement('div');
            overlay.className = 'focal-picker-overlay';
            overlay.innerHTML = `
                <div class="focal-label">Arrasta para enquadrar · Roda p/ Zoom</div>
                <div class="focal-crosshair" style="left:${curX}%;top:${curY}%;">
                    <div class="focal-dot"></div>
                </div>`;

            const controls = document.createElement('div');
            controls.className = 'focal-controls-global';
            controls.innerHTML = `
                <div class="zoom-control">
                    <input type="range" class="zoom-slider" min="1" max="3" step="0.01" value="${curZoom}">
                    <span class="zoom-val">${curZoom.toFixed(2)}x</span>
                </div>
                <div class="focal-btns">
                    <button type="button" class="focal-btn-cancel">Cancelar</button>
                    <button type="button" class="focal-btn-save">Guardar</button>
                </div>`;

            card.appendChild(overlay);
            document.body.appendChild(controls);
            card.classList.add('focal-adjusting');
            homeActivePicker = { card, overlay, controls };
            if (homeBentoSortable) homeBentoSortable.option('disabled', true);

            const crosshair = overlay.querySelector('.focal-crosshair');
            const slider = controls.querySelector('.zoom-slider');
            const zoomVal = controls.querySelector('.zoom-val');
            let rafId = null;

            function applyState(fromSlider = false) {
                if (rafId) cancelAnimationFrame(rafId);
                rafId = requestAnimationFrame(() => {
                    crosshair.style.left = curX + '%';
                    crosshair.style.top = curY + '%';
                    img.style.objectPosition = `${curX}% ${curY}%`;
                    img.style.transform = `scale(${curZoom})`;
                    if (!fromSlider) slider.value = curZoom;
                    zoomVal.textContent = curZoom.toFixed(2) + 'x';
                });
            }

            function getXY(e) {
                const rect = overlay.getBoundingClientRect();
                return {
                    x: Math.max(0, Math.min(100, Math.round(((e.clientX - rect.left) / rect.width) * 100))),
                    y: Math.max(0, Math.min(100, Math.round(((e.clientY - rect.top) / rect.height) * 100)))
                };
            }

            slider.addEventListener('input', e => {
                curZoom = parseFloat(e.target.value);
                applyState(true);
            });

            const handleWheel = e => {
                e.preventDefault();
                curZoom = Math.max(1, Math.min(3, curZoom + (e.deltaY > 0 ? -0.1 : 0.1)));
                applyState();
            };
            overlay.addEventListener('wheel', handleWheel, { passive: false });
            controls.addEventListener('wheel', handleWheel, { passive: false });
            overlay.addEventListener('contextmenu', e => {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                isDragging = false;
            });

            let isDragging = false;
            let activePointerId = null;
            const startDrag = e => {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                if (e.button !== 0 && e.buttons !== 1) {
                    isDragging = false;
                    return;
                }
                isDragging = true;
                activePointerId = e.pointerId ?? null;
                if (activePointerId !== null && overlay.setPointerCapture) {
                    try { overlay.setPointerCapture(activePointerId); } catch (err) {}
                }
                const xy = getXY(e);
                curX = xy.x;
                curY = xy.y;
                applyState();
            };
            overlay.addEventListener('pointerdown', startDrag, true);
            overlay.addEventListener('auxclick', e => {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                isDragging = false;
            }, true);

            const onMove = e => {
                if (!isDragging) return;
                if (activePointerId !== null && e.pointerId !== undefined && e.pointerId !== activePointerId) return;
                if (e.buttons !== undefined && (e.buttons & 1) !== 1) {
                    isDragging = false;
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                const xy = getXY(e);
                curX = xy.x;
                curY = xy.y;
                applyState();
            };
            const onUp = e => {
                if (activePointerId !== null && e?.pointerId !== undefined && e.pointerId !== activePointerId) return;
                isDragging = false;
                activePointerId = null;
            };
            const onKey = e => {
                if (e.key === 'Escape') {
                    img.style.objectPosition = originalPos;
                    img.style.transform = `scale(${originalZoom})`;
                    homeCloseFocalPicker();
                }
            };

            document.addEventListener('pointermove', onMove, { passive: false });
            document.addEventListener('pointerup', onUp);
            document.addEventListener('pointercancel', onUp);
            document.addEventListener('keydown', onKey);

            overlay._cleanup = () => {
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
                document.removeEventListener('pointercancel', onUp);
                document.removeEventListener('keydown', onKey);
            };

            controls.querySelector('.focal-btn-cancel').addEventListener('click', () => {
                img.style.objectPosition = originalPos;
                img.style.transform = `scale(${originalZoom})`;
                homeCloseFocalPicker();
            });

            controls.querySelector('.focal-btn-save').addEventListener('click', () => {
                const posicao = `${curX}% ${curY}%`;
                card.dataset.pos = posicao;
                card.dataset.zoom = curZoom;
                homeAjaxCat({ acao: 'set_posicao', id: catId, posicao, zoom: curZoom, device: 'desktop' })
                    .then(data => {
                        if (typeof mostrarPopup === 'function') {
                            mostrarPopup(data.sucesso ? 'Enquadramento e zoom guardados.' : (data.mensagem || 'Erro ao guardar enquadramento.'), data.sucesso ? 'sucesso' : 'erro');
                        }
                    })
                    .catch(() => {
                        if (typeof mostrarPopup === 'function') mostrarPopup('Erro de ligação.', 'erro');
                    });
                homeCloseFocalPicker();
            });
        }

        function homeCloseFocalPicker() {
            if (!homeActivePicker) return;
            const { card, overlay, controls } = homeActivePicker;
            card?.classList.remove('focal-adjusting');
            if (overlay?._cleanup) overlay._cleanup();
            overlay?.remove();
            controls?.remove();
            homeActivePicker = null;
            if (homeBentoSortable) homeBentoSortable.option('disabled', false);
        }

        homeBentoGrid?.addEventListener('click', e => {
            const btn = e.target.closest('.btn-edit-framing');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            homeOpenFocalPicker(btn.closest('.category-card'));
        });

        homeBentoGrid?.addEventListener('click', e => {
            const editable = e.target.closest('.category-name-editable');
            if (!editable || !homeBentoDesktop.matches) return;

            const rect = editable.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const clickY = e.clientY - rect.top;
            if (clickX < rect.width - 36 || clickY > 36) return;

            e.preventDefault();
            e.stopPropagation();
            abrirEditorCategoriaHome(editable);
        });

        function abrirEditorCategoriaHome(editable) {
            currentHomeCategoryTitle = editable;
            document.querySelectorAll('.category-name-editable.editing-current').forEach(el => el.classList.remove('editing-current'));
            editable.classList.add('editing-current');

            document.getElementById('home-category-edit-id').value = editable.dataset.catId;
            document.getElementById('home-category-edit-nome').value = editable.querySelector('h3')?.textContent.trim() || '';
            const modal = document.getElementById('modalEditorCategoriaHome');
            modal.classList.add('active');
            modal.style.display = 'flex';
            setTimeout(() => document.getElementById('home-category-edit-nome').focus(), 50);
        }

        homeQueueBentoEditorInit();
        homeBentoDesktop.addEventListener?.('change', homeDestroyBentoEditorOnMobile);
    </script>
<?php endif; ?>

<?php include 'templates/footer.php'; ?>
