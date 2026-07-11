<?php
// admin/ajax_reembolsar_stripe.php
// Reembolsa um pagamento Stripe e cancela a encomenda num único passo.

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/stripe.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// CSRF
if (
    empty($input['csrf_token'])
    || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])
) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Token CSRF inválido.']);
    exit;
}

$encomenda_id = filter_var($input['encomenda_id'] ?? 0, FILTER_VALIDATE_INT);
if (!$encomenda_id) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID de encomenda inválido.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM encomendas WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $encomenda_id);
$stmt->execute();
$encomenda = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$encomenda) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Encomenda não encontrada.']);
    exit;
}

if (!in_array($encomenda['estado'], ['pago', 'em processamento'], true)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Só é possível reembolsar encomendas pagas ou em processamento.']);
    exit;
}

$pi_id = $encomenda['stripe_payment_intent_id'] ?? '';
if (empty($pi_id)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Esta encomenda não tem um PaymentIntent Stripe associado.']);
    exit;
}

$stripe_keys = getStripeKeys();
if (empty($stripe_keys['secret'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Chave Stripe não configurada.']);
    exit;
}

// Solicitar reembolso ao Stripe
$refund = stripeRequest('POST', 'refunds', [
    'payment_intent' => $pi_id,
], $stripe_keys['secret']);

$http_code = $refund['_http_code'] ?? 0;

// Aceita 200 (reembolso criado) e também quando já foi reembolsado anteriormente
$reembolso_ok = ($http_code === 200 && ($refund['status'] ?? '') === 'succeeded')
             || ($http_code === 200 && isset($refund['id']));

if (!$reembolso_ok) {
    $stripe_error = $refund['error']['message'] ?? 'Erro desconhecido na API Stripe.';
    // Se já foi completamente reembolsado, não é um erro real — avança
    if (($refund['error']['code'] ?? '') === 'charge_already_refunded') {
        $reembolso_ok = true;
    } else {
        error_log("Stripe reembolso falhou para encomenda #{$encomenda_id}: " . $stripe_error);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro Stripe: ' . $stripe_error]);
        exit;
    }
}

// Cancelar encomenda + repor stock numa transação
$conn->begin_transaction();
try {
    $stmt_can = $conn->prepare("UPDATE encomendas SET estado = 'cancelada' WHERE id = ?");
    $stmt_can->bind_param("i", $encomenda_id);
    $stmt_can->execute();
    $stmt_can->close();

    $stmt_itens = $conn->prepare("SELECT variacao_id, quantidade FROM encomenda_itens WHERE encomenda_id = ?");
    $stmt_itens->bind_param("i", $encomenda_id);
    $stmt_itens->execute();
    $itens = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_itens->close();

    $stmt_stock = $conn->prepare("UPDATE produto_variacoes SET quantidade = quantidade + ? WHERE id = ?");
    foreach ($itens as $it) {
        $stmt_stock->bind_param("ii", $it['quantidade'], $it['variacao_id']);
        $stmt_stock->execute();
    }
    $stmt_stock->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    error_log("Reembolso Stripe OK mas falha ao cancelar encomenda #{$encomenda_id}: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => 'Reembolso processado mas erro ao atualizar a encomenda. Contacte o suporte.']);
    exit;
}

// Enviar email ao cliente
try {
    require_once __DIR__ . '/includes/email_handler.php';
    enviarEmailEncomenda('cancelada', [
        'id'               => $encomenda['id'],
        'token'            => $encomenda['token'],
        'cliente_nome'     => $encomenda['cliente_nome'],
        'cliente_email'    => $encomenda['cliente_email'],
        'total'            => (float)$encomenda['total'],
        'portes_envio'     => (float)$encomenda['portes_envio'],
        'metodo_entrega'   => $encomenda['metodo_entrega'],
        'metodo_pagamento' => $encomenda['metodo_pagamento'],
    ]);
} catch (\Throwable $e) {
    error_log("Reembolso OK mas falha no email para encomenda #{$encomenda_id}: " . $e->getMessage());
}

echo json_encode(['sucesso' => true, 'mensagem' => 'Reembolso processado e encomenda cancelada com sucesso.']);
