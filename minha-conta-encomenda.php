<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cliente_auth.php';
require_once __DIR__ . '/config/formatters.php';

require_cliente();

$cliente = cliente_atual($conn);
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId) {
    header('Location: /minha-conta/encomendas');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM encomendas WHERE id = ? AND cliente_id = ? LIMIT 1");
$stmt->bind_param('ii', $orderId, $cliente['id']);
$stmt->execute();
$encomenda = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$encomenda) {
    http_response_code(404);
    $titulo_pagina = 'Encomenda nao encontrada';
    include __DIR__ . '/templates/header.php';
    echo "<main class='cliente-area-page'><div class='cliente-shell'><section class='cliente-content'><p>Encomenda nao encontrada nesta conta.</p></section></div></main>";
    include __DIR__ . '/templates/footer.php';
    exit;
}

$stmt = $conn->prepare(
    "SELECT ei.*, COALESCE(ei.foto_snapshot, p.foto_principal) AS foto_exibicao
     FROM encomenda_itens ei
     LEFT JOIN produtos p ON p.id = ei.produto_id
     WHERE ei.encomenda_id = ?"
);
$stmt->bind_param('i', $orderId);
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$titulo_pagina = 'Encomenda #' . $orderId;
include __DIR__ . '/templates/header.php';
?>

<main class="cliente-area-page">
    <div class="cliente-shell">
        <?php 
        $active_page = 'encomendas';
        include __DIR__ . '/templates/cliente-sidebar.php'; 
        ?>
        <section class="cliente-content">
            <div class="cliente-back-link" style="margin-bottom: 16px;">
                <a href="/minha-conta/encomendas">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    Voltar às encomendas
                </a>
            </div>
            <div class="cliente-page-head">
                <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                    <h1 style="margin:0;">Encomenda #<?php echo (int)$encomenda['id']; ?></h1>
                    <span class="order-status status-<?php echo str_replace(' ', '-', $encomenda['estado']); ?>" style="font-size:0.8rem;">
                        <?php echo htmlspecialchars($encomenda['estado']); ?>
                    </span>
                </div>
                <p><?php echo date('d/m/Y \à\s H:i', strtotime($encomenda['data_encomenda'])); ?></p>
            </div>

            <div class="cliente-panel">
                <div class="cliente-panel-head">
                    <h2>Artigos</h2>
                    <a href="/estado_encomenda.php?id=<?php echo (int)$encomenda['id']; ?>&token=<?php echo urlencode($encomenda['token']); ?>" class="btn-track-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        Acompanhar Encomenda (Tracking)
                    </a>
                </div>
                <?php foreach ($itens as $item): ?>
                    <?php $attrs = json_decode($item['selecoes_atributos'] ?? '{}', true) ?: []; ?>
                    <div class="cliente-order-item">
                        <img src="/public/images/<?php echo htmlspecialchars($item['foto_exibicao'] ?: 'default.jpg'); ?>" alt="<?php echo htmlspecialchars($item['nome_produto']); ?>">
                        <div>
                            <strong><?php echo htmlspecialchars($item['nome_produto']); ?></strong>
                            <span>
                                <?php if ($attrs): echo htmlspecialchars(implode(' · ', array_values($attrs))) . ' · '; endif; ?>
                                Qtd: <?php echo (int)$item['quantidade']; ?>
                            </span>
                        </div>
                        <b><?php echo format_money((float)$item['preco_unitario'] * (int)$item['quantidade']); ?></b>
                    </div>
                <?php endforeach; ?>
                <div class="cliente-totals">
                    <span>Subtotal <strong><?php echo format_money((float)$encomenda['total']); ?></strong></span>
                    <span>Portes <strong><?php echo format_money((float)$encomenda['portes_envio']); ?></strong></span>
                    <span>Total <strong><?php echo format_money((float)$encomenda['total'] + (float)$encomenda['portes_envio']); ?></strong></span>
                </div>
            </div>

            <div class="cliente-panel">
                <div class="cliente-panel-head">
                    <h2><?php echo ($encomenda['metodo_entrega'] === 'recolha') ? 'Recolha' : 'Entrega'; ?></h2>
                    <?php if (!empty($encomenda['codigo_tracking'])): ?>
                        <a href="https://gls-group.eu/PT/pt/seguimento-de-encomendas" target="_blank" rel="noopener">
                            Rastrear com GLS
                        </a>
                    <?php endif; ?>
                </div>
                <div class="cliente-delivery-body">
                    <?php if ($encomenda['metodo_entrega'] === 'recolha'): ?>
                        <p class="cliente-morada-text">
                            <strong>Ponto de Recolha:</strong><br>
                            Edifício Chafariz<br>
                            Rua dos Fontenários<br>
                            4535-221 Lourosa, Portugal
                        </p>
                    <?php else: ?>
                        <p class="cliente-morada-text"><?php echo nl2br(htmlspecialchars($encomenda['cliente_morada'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($encomenda['codigo_tracking'])): ?>
                        <div class="cliente-tracking-box">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                            <div>
                                <div class="tracking-label">Código de tracking</div>
                                <div class="tracking-value"><?php echo htmlspecialchars($encomenda['codigo_tracking']); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </section>
    </div>
</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
