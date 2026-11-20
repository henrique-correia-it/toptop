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
$orders = customer_orders($conn, (int)$cliente['id'], 100);
$titulo_pagina = 'As Minhas Encomendas';

include __DIR__ . '/templates/header.php';
?>

<main class="cliente-area-page">
    <div class="cliente-shell">
        <?php 
        $active_page = 'encomendas';
        include __DIR__ . '/templates/cliente-sidebar.php'; 
        ?>
        <section class="cliente-content">
            <div class="cliente-page-head">
                <h1>Encomendas</h1>
                <p>Acompanha o estado, tracking e detalhes das tuas compras.</p>
            </div>
            <div class="cliente-panel">
                <div class="cliente-panel-head">
                    <h2>Histórico de Encomendas</h2>
                </div>
                <?php echo ui_customer_orders_table($orders, [
                    'empty_message' => 'Ainda não tens encomendas nesta conta.',
                    'cta_label'     => 'Explorar produtos',
                    'cta_href'      => '/produtos',
                ]); ?>
            </div>
        </section>
    </div>
</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
