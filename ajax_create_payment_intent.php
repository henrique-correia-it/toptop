<?php
// ajax_create_payment_intent.php
// Valida o carrinho, cria a reserva de encomenda (estado "incompleta")
// e devolve o client_secret do Stripe PaymentIntent ao frontend.

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';
ob_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/stripe.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/http.php';
require_once __DIR__ . '/config/rate_limit.php';
require_once __DIR__ . '/config/cliente_auth.php';
require_once __DIR__ . '/includes/CartService.php';
require_once __DIR__ . '/includes/OrderService.php';
require_once __DIR__ . '/includes/ShippingService.php';

send_no_cache_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_error('Método não permitido.', 405);
}

$dados = json_input();

if (!is_array($dados) || !csrf_from_array($dados)) {
    ob_end_clean();
    json_error('Erro de segurança. Recarregue a página e tente novamente.', 403);
}

if (!rate_limit_allow('create_payment_intent', 20, 600)) {
    ob_end_clean();
    json_error('Demasiadas tentativas de pagamento. Aguarde alguns minutos e tente novamente.', 429);
}

if (
    empty($dados['carrinho'])
    || !is_array($dados['carrinho'])
    || count($dados['carrinho']) > 50
    || empty($dados['cliente'])
    || !is_array($dados['cliente'])
    || !is_string($dados['cliente']['nome'] ?? null)
    || trim($dados['cliente']['nome']) === ''
    || !is_string($dados['cliente']['email'] ?? null)
    || !is_string($dados['cliente']['telefone'] ?? null)
    || trim($dados['cliente']['telefone']) === ''
    || !filter_var(trim($dados['cliente']['email']), FILTER_VALIDATE_EMAIL)
) {
    ob_end_clean();
    json_error('Dados inválidos ou carrinho vazio.', 400);
}

$carrinho_cliente = $dados['carrinho'];
$dados_cliente    = $dados['cliente'];
$dados_cliente['nome']     = trim((string)$dados_cliente['nome']);
$dados_cliente['email']    = trim((string)$dados_cliente['email']);
$dados_cliente['telefone'] = trim((string)$dados_cliente['telefone']);

$metodo_entrega = $dados['metodo_entrega'] ?? 'envio';
if (!in_array($metodo_entrega, ['envio', 'recolha'], true)) {
    ob_end_clean();
    json_error('Método de entrega inválido.', 400);
}
if ($metodo_entrega === 'envio') {
    if (
        trim((string)($dados_cliente['rua'] ?? '')) === ''
        || trim((string)($dados_cliente['codigo_postal'] ?? '')) === ''
        || trim((string)($dados_cliente['localidade'] ?? '')) === ''
    ) {
        ob_end_clean();
        json_error('Preencha todos os campos obrigatórios da morada de envio.', 400);
    }
}
$dados_cliente['cliente_nif'] = is_scalar($dados_cliente['cliente_nif'] ?? null) ? trim((string)$dados_cliente['cliente_nif']) : '';
$cliente_conta = is_cliente_logged_in() ? cliente_atual($conn) : null;
$cliente_id = $cliente_conta ? (int)$cliente_conta['id'] : null;
$origem_encomenda = $cliente_conta ? 'cliente' : 'guest';

if ($cliente_conta) {
    $dados_cliente['email'] = $cliente_conta['email'];
    $dados_cliente['nome'] = $dados_cliente['nome'] ?: $cliente_conta['nome'];
    $dados_cliente['telefone'] = $dados_cliente['telefone'] ?: ($cliente_conta['telefone'] ?? '');
    $dados_cliente['cliente_nif'] = $dados_cliente['cliente_nif'] ?: ($cliente_conta['nif'] ?? '');
} else {
    $conta_existente = customer_find_by_email($conn, $dados_cliente['email']);
    $is_dev = is_developer();

    if ($conta_existente && (int)$conta_existente['ativo'] === 1 && !$is_dev) {
        ob_end_clean();
        json_error('Este email já tem conta. Inicie sessão para continuar o checkout.', 409, [
            'requires_login' => true,
            'login_url' => '/entrar?next=/checkout',
        ]);
    }
}

$stripe_keys = getStripeKeys();
if (empty($stripe_keys['secret'])) {
    ob_end_clean();
    json_error('Sistema de pagamento não configurado.', 500);
}

// Liberta reserva anterior da mesma sessão caso o cliente não a tenha libertado explicitamente (anti-DoS de stock)
if (!empty($_SESSION['checkout_active_reservation']) && is_array($_SESSION['checkout_active_reservation'])) {
    $prevRes = $_SESSION['checkout_active_reservation'];
    $prevOrderId = (int)($prevRes['order_id'] ?? 0);
    $prevToken   = (string)($prevRes['token'] ?? '');
    $prevPiId    = (string)($prevRes['pi_id'] ?? '');
    unset($_SESSION['checkout_active_reservation']);

    if ($prevOrderId > 0 && $prevToken !== '') {
        try {
            $canRelease = true;
            if ($prevPiId !== '' && preg_match('/^pi_[A-Za-z0-9_]+$/', $prevPiId)) {
                $prevPi = stripeRequest('GET', 'payment_intents/' . rawurlencode($prevPiId), [], $stripe_keys['secret']);
                $prevStatus = (string)($prevPi['status'] ?? '');
                if (in_array($prevStatus, ['succeeded', 'processing'], true)) {
                    $canRelease = false;
                } elseif ($prevStatus !== '' && $prevStatus !== 'canceled') {
                    $cRes = stripeRequest('POST', 'payment_intents/' . rawurlencode($prevPiId) . '/cancel', [], $stripe_keys['secret']);
                    if (($cRes['status'] ?? '') !== 'canceled') {
                        $canRelease = false;
                    }
                }
            }
            if ($canRelease) {
                release_incomplete_order_reservation($conn, $prevOrderId, $prevToken);
            }
        } catch (\Throwable $ignored) {}
    }
}

$token      = bin2hex(random_bytes(32));
$data_agora = date('Y-m-d H:i:s');
$encomenda_id = 0;
$in_transaction = false;

try {
    // ── FASE 1: Transação curta em BD (validação + abate de stock sem chamadas HTTP externas) ──
    $conn->begin_transaction();
    $in_transaction = true;

    $cart_validation = validate_cart_items($conn, $carrinho_cliente);
    $total_encomenda = $cart_validation['total'];
    $subtotal_cents  = (int)($cart_validation['total_cents'] ?? round($total_encomenda * 100));
    $total_peso      = $cart_validation['weight'];
    $carrinho_verificado = $cart_validation['items'];

    $pais_cliente = normalize_country_code((string)($dados_cliente['pais_regiao'] ?? 'PT'), 'PT');
    $portes_valor = ($metodo_entrega === 'recolha')
        ? 0.0
        : calculate_shipping(
            $total_peso,
            $conn,
            $pais_cliente,
            $total_encomenda,
            $dados_cliente['codigo_postal'] ?? null
        );

    $portes_cents = (int)round($portes_valor * 100);
    $amount_cents = $subtotal_cents + $portes_cents;
    if ($amount_cents < 50) {
        throw new DomainException('O valor mínimo para pagamento é de 0,50 €.');
    }

    $morada_completa = ($metodo_entrega === 'recolha')
        ? 'Recolha na Loja'
        : format_address($dados_cliente);

    $stmt_enc = $conn->prepare(
        "INSERT INTO encomendas
            (cliente_id, origem, cliente_nome, cliente_email, cliente_telefone, cliente_morada, cliente_nif,
             metodo_entrega, metodo_pagamento, total, portes_envio, token,
             estado, stripe_payment_intent_id, data_encomenda)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Stripe', ?, ?, ?, 'incompleta', NULL, ?)"
    );
    if (!$stmt_enc) {
        throw new \RuntimeException("Erro interno de base de dados (prepare encomenda): " . $conn->error);
    }
    $stmt_enc->bind_param(
        "isssssssddss",
        $cliente_id,
        $origem_encomenda,
        $dados_cliente['nome'],
        $dados_cliente['email'],
        $dados_cliente['telefone'],
        $morada_completa,
        $dados_cliente['cliente_nif'],
        $metodo_entrega,
        $total_encomenda,
        $portes_valor,
        $token,
        $data_agora
    );
    $stmt_enc->execute();
    $encomenda_id = (int)$conn->insert_id;
    $stmt_enc->close();

    insert_order_items_and_decrement_stock($conn, $encomenda_id, $carrinho_verificado);

    $conn->commit();
    $in_transaction = false;

    // ── FASE 2: Chamada HTTPS ao Stripe FORA da transação (sem reter locks InnoDB) ──
    $pi_payload = [
        'amount'                  => $amount_cents,
        'currency'                => 'eur',
        'payment_method_types'    => ['card', 'mb_way', 'klarna'],
        'metadata[encomenda_id]'  => $encomenda_id,
        'metadata[cliente_email]' => $dados_cliente['email'],
        'metadata[cliente_nome]'  => $dados_cliente['nome'],
        'description'             => 'Encomenda TopTop.pt #' . $encomenda_id,
    ];
    if ($metodo_entrega === 'envio') {
        $pi_payload['shipping[name]']                 = $dados_cliente['nome'];
        $pi_payload['shipping[address][line1]']       = (string)($dados_cliente['rua'] ?? '');
        $pi_payload['shipping[address][city]']        = (string)($dados_cliente['localidade'] ?? '');
        $pi_payload['shipping[address][postal_code]'] = (string)($dados_cliente['codigo_postal'] ?? '');
        $pi_payload['shipping[address][country]']     = $pais_cliente;
        $pi_payload['shipping[address][state]']       = (string)($dados_cliente['provincia'] ?? '');
    }

    $pi_response = stripeRequest('POST', 'payment_intents', $pi_payload, $stripe_keys['secret']);

    if (!empty($pi_response['error']) || empty($pi_response['client_secret']) || empty($pi_response['id'])) {
        $err_msg = $pi_response['error']['message'] ?? 'Erro desconhecido ao contactar o Stripe.';
        throw new \RuntimeException("Falha ao iniciar o pagamento no Stripe: " . $err_msg);
    }

    $payment_intent_id = (string)$pi_response['id'];
    $client_secret     = (string)$pi_response['client_secret'];

    $stmt_pi = $conn->prepare("UPDATE encomendas SET stripe_payment_intent_id = ? WHERE id = ?");
    if (!$stmt_pi) {
        throw new \RuntimeException("Erro interno ao associar PaymentIntent: " . $conn->error);
    }
    $stmt_pi->bind_param("si", $payment_intent_id, $encomenda_id);
    $stmt_pi->execute();
    $stmt_pi->close();

    $_SESSION['checkout_active_reservation'] = [
        'order_id' => $encomenda_id,
        'token'    => $token,
        'pi_id'    => $payment_intent_id,
    ];

    log_stripe("PaymentIntent {$payment_intent_id} criado para enc#{$encomenda_id} (" . number_format($amount_cents / 100, 2, '.', '') . " EUR).", 'INFO', 'ajax_create_payment_intent.php');

    ob_end_clean();
    json_success([
        'client_secret'  => $client_secret,
        'order_id'       => $encomenda_id,
        'order_token'    => $token,
        'subtotal_cents' => $subtotal_cents,
        'portes_cents'   => $portes_cents,
        'total_cents'    => $amount_cents,
    ]);

} catch (\DomainException $e) {
    if ($in_transaction) {
        try { $conn->rollback(); } catch (\Throwable $ignored) {}
    }
    log_app('Validação checkout: ' . $e->getMessage(), 'WARNING', 'ajax_create_payment_intent.php');
    ob_end_clean();
    json_error($e->getMessage(), 400);
} catch (\Throwable $e) {
    if ($in_transaction) {
        try { $conn->rollback(); } catch (\Throwable $ignored) {}
    } elseif ($encomenda_id > 0) {
        if (!empty($payment_intent_id)) {
            try {
                stripeRequest('POST', 'payment_intents/' . rawurlencode($payment_intent_id) . '/cancel', [], $stripe_keys['secret']);
            } catch (\Throwable $ignored) {}
        }
        try {
            release_incomplete_order_reservation($conn, $encomenda_id, $token);
        } catch (\Throwable $ignored) {}
    }
    log_exception($e, 'ajax_create_payment_intent.php');
    ob_end_clean();
    json_error('Não foi possível iniciar o pagamento. Tente novamente.', 500);
}
