<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cliente_auth.php';
require_once __DIR__ . '/config/formatters.php';

require_cliente();

$cliente = cliente_atual($conn);
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <p>Ainda não tens encomendas associadas a esta conta.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
