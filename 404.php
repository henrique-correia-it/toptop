<?php
http_response_code(404);
$titulo_pagina = 'Página Não Encontrada';
$descricao_pagina = 'A página que procuras não existe. Volta à loja TopTop e encontra o teu estilo.';
require_once __DIR__ . '/templates/header.php';

$req404 = (string)($_SERVER['REQUEST_URI'] ?? '');
$path404 = (string)(parse_url($req404, PHP_URL_PATH) ?? '');
$ignorar404 = $path404 === '' || (bool)preg_match(
    '#^/(?:\.well-known/|apple-touch-icon[^/]*\.png$|security\.txt$|llms\.txt$|ads\.txt$|sitemap_index\.xml$|wp-(?:sitemap\.xml|login\.php|admin|content/|includes/)|xmlrpc\.php$)|\.(?:ico|map)$#i',
    $path404
);
if (!$ignorar404 && function_exists('log_app')) {
    $ref404 = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    log_app('Página não encontrada: ' . $req404 . ($ref404 !== '' ? " (referer: {$ref404})" : ''), '404', '404.php');
}
?>

<main class="pagina-404 clean-version">
    <div class="container-404">
        <div class="hanger-wrapper">
            <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" class="gold-hanger" aria-hidden="true">
                <!-- Gancho elegante em dourado -->
                <path d="M50 35 L50 20 Q50 8 62 8 Q74 8 74 20 Q74 30 62 30"
                      fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                <!-- Barra do cabide -->
                <path d="M10 65 L50 35 L90 65 Z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>
                <line x1="15" y1="65" x2="85" y2="65" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
        </div>

        <div class="content-wrapper">
            <span class="error-code">404</span>
            <h1 class="titulo-404">Peça Não Encontrada</h1>
            <p class="texto-404">Parece que este item já saiu da nossa passarela.<br>Explore a nossa coleção atual e encontre o seu próximo favorito.</p>

            <div class="acoes-404">
                <?php
                echo ui_button('Ir para a Home', ['href' => '/', 'class' => 'btn-boutique primary']);
                echo ui_button('Ver Coleção', ['href' => '/produtos', 'class' => 'btn-boutique secondary']);
                ?>
            </div>
        </div>

        <!-- Jogo da Memória -->
        <div class="game-section">
            <h2 class="game-title">Enquanto esperas, encontra os pares:</h2>
            <div id="memory-game" class="memory-grid">
                <!-- As cartas serão geradas por JS -->
            </div>
            <div class="game-info">
                <span id="game-stats">Tentativas: 0</span>
                <?php echo ui_button('Reiniciar Jogo', ['id' => 'reset-game', 'class' => 'btn-reset']); ?>
            </div>
        </div>
        <!-- Modal de Vitória -->
        <div id="win-modal" class="win-modal" role="dialog" aria-modal="true" aria-labelledby="win-modal-title">
            <div class="win-modal-content">
                <span class="win-icon" aria-hidden="true">✨</span>
                <h2 id="win-modal-title" class="win-title">Estilo Impecável!</h2>
                <p class="win-text">Encontraste todos os pares em <span id="final-attempts">0</span> tentativas.</p>
                <?php echo ui_button('Continuar a Compras', ['id' => 'close-win-modal', 'class' => 'btn-boutique primary']); ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js" crossorigin="anonymous" defer></script>
<script src="/public/js/game404.js?v=<?php echo htmlspecialchars((string)($versao_global ?? '1'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>


<?php require_once __DIR__ . '/templates/footer.php'; ?>
