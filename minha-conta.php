<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cliente_auth.php';
require_once __DIR__ . '/config/formatters.php';

require_cliente();

$cliente = cliente_atual($conn);
if (!$cliente) {
    header('Location: /entrar');
    exit;
}
$orders = customer_orders($conn, (int)$cliente['id'], 5);
$addresses = customer_addresses($conn, (int)$cliente['id']);
$titulo_pagina = 'Minha Conta';

include __DIR__ . '/templates/header.php';
?>

<main class="cliente-area-page">
    <div class="cliente-shell">
        <?php 
        $active_page = 'resumo';
        include __DIR__ . '/templates/cliente-sidebar.php'; 
        ?>

        <section class="cliente-content">
            <div class="cliente-page-head">
                <h1>Minha conta</h1>
                <p>Bem-vindo de volta, <?php echo htmlspecialchars(explode(' ', $cliente['nome'])[0]); ?>. Aqui está o resumo da tua conta.</p>
            </div>

            <div class="cliente-grid">
                <a class="cliente-card" href="/minha-conta/encomendas">
                    <div class="cliente-card-header">
                        <span class="cliente-card-number"><?php echo count($orders); ?></span>
                        <div class="cliente-card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>
                        </div>
                    </div>
                    <strong>Encomendas recentes</strong>
                    <small>Vê o histórico e segue o envio</small>
                </a>
                <a class="cliente-card" href="/minha-conta/moradas">
                    <div class="cliente-card-header">
                        <span class="cliente-card-number"><?php echo count($addresses); ?></span>
                        <div class="cliente-card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        </div>
                    </div>
                    <strong>Moradas guardadas</strong>
                    <small>Gere as tuas moradas de entrega</small>
                </a>
            </div>

            <div class="cliente-panel">
                <div class="cliente-panel-head">
                    <h2>Últimas encomendas</h2>
                    <a href="/minha-conta/encomendas">Ver todas</a>
                </div>
                <?php echo ui_customer_orders_table($orders, [
                    'empty_message' => 'Ainda não tens encomendas associadas a esta conta.',
                ]); ?>
<?php if (isset($_GET['verificado']) && $_GET['verificado'] === '1'): ?>
<script>document.addEventListener('DOMContentLoaded', () => { if (typeof mostrarPopup === 'function') mostrarPopup('Email verificado com sucesso! Bem-vindo à tua conta.', 'sucesso'); });</script>
<?php endif; ?>
            </div>
        </section>
    </div>
</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
