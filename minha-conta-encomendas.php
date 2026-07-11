<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cliente_auth.php';
require_once __DIR__ . '/config/formatters.php';

require_cliente();

$cliente = cliente_atual($conn);
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
                <?php if ($orders): ?>
                    <div class="cliente-orders-table-wrap">
                        <table class="cliente-orders-table">
                            <thead>
                                <tr>
                                    <th>Encomenda</th>
                                    <th>Data</th>
                                    <th>Entrega</th>
                                    <th>Estado</th>
                                    <th class="is-money">Total</th>
                                    <th class="is-action">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <?php $estadoClass = strtolower(str_replace(' ', '-', $order['estado'])); ?>
                                    <tr>
                                        <td data-label="Encomenda"><a href="/minha-conta/encomenda?id=<?php echo (int)$order['id']; ?>" class="order-id">#<?php echo (int)$order['id']; ?></a></td>
                                        <td data-label="Data"><span class="order-date"><?php echo date('d/m/Y', strtotime($order['data_encomenda'])); ?></span></td>
                                        <td data-label="Entrega"><span class="order-delivery"><?php echo htmlspecialchars($order['metodo_entrega'] ?: 'Envio'); ?></span></td>
                                        <td data-label="Estado"><span class="order-status status-<?php echo htmlspecialchars($estadoClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($order['estado']); ?></span></td>
                                        <td data-label="Total" class="is-money"><strong class="order-total"><?php echo format_money((float)$order['total'] + (float)$order['portes_envio']); ?></strong></td>
                                        <td class="is-action">
                                            <div class="order-actions-cell">
                                                <a href="/estado_encomenda.php?id=<?php echo (int)$order['id']; ?>&token=<?php echo urlencode($order['token']); ?>" class="btn-track-mini" title="Rastrear Encomenda">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                    <span>Tracking</span>
                                                </a>
                                                <a href="/minha-conta/encomenda?id=<?php echo (int)$order['id']; ?>" class="order-icon-btn" aria-label="Ver detalhes da encomenda #<?php echo (int)$order['id']; ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="cliente-empty">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                        <p>Ainda não tens encomendas nesta conta.</p>
                        <a href="/produtos" class="button">Explorar produtos</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
