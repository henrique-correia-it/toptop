<?php
http_response_code(404);
$titulo_pagina = 'Página Não Encontrada';
$descricao_pagina = 'A página que procuras não existe. Volta à loja TopTop e encontra o teu estilo.';
require_once __DIR__ . '/templates/header.php';
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
                <a href="/" class="btn-boutique primary">Ir para a Home</a>
                <a href="/produtos.php" class="btn-boutique secondary">Ver Coleção</a>
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
                <button id="reset-game" class="btn-reset">Reiniciar Jogo</button>
            </div>
        </div>
        <!-- Modal de Vitória -->
        <div id="win-modal" class="win-modal">
            <div class="win-modal-content">
                <span class="win-icon">✨</span>
                <h2 class="win-title">Estilo Impecável!</h2>
                <p class="win-text">Encontraste todos os pares em <span id="final-attempts">0</span> tentativas.</p>
                <button id="close-win-modal" class="btn-boutique primary">Continuar a Compras</button>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script src="/public/js/game404.js?v=<?php echo $versao_global; ?>"></script>


<?php require_once __DIR__ . '/templates/footer.php'; ?>
