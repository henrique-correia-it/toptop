<?php
$titulo_pagina = 'O Meu Carrinho';
$descricao_pagina = 'Revê os artigos do teu carrinho TopTop e finaliza a tua encomenda com pagamento 100% seguro.';
$noindex = true;
include 'templates/header.php';
?> 
<main class="pagina-carrinho">
    <div class="cart-header">
        <h2>O Meu Carrinho</h2>
    </div>

    <div class="cart-container">
        <!-- Lista de Itens -->
        <div class="cart-items-wrapper">
            <div id="itens-carrinho">
                <div class="cart-loading">
                    <div class="spinner"></div>
                    <p>A carregar itens do carrinho...</p>
                </div>
            </div>
            
        </div>

        <!-- Sumário Lateral -->
        <aside class="cart-summary-wrapper">
            <div id="total-carrinho" class="cart-summary-card">
                <!-- Preenchido via JS -->
            </div>
            <div class="cart-checkout-action">
                <a href="/checkout" id="finalizar-encomenda-checkout" class="button add-btn large">Finalizar Encomenda</a>
                <p class="cart-security-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Pagamento 100% Seguro via Stripe
                </p>
            </div>
        </aside>
    </div>
</main>
 
<?php include 'templates/footer.php'; ?>