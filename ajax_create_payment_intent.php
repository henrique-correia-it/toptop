<?php
// ajax_create_payment_intent.php
// Valida o carrinho, cria a encomenda (estado "a aguardar pagamento")
// e devolve o client_secret do Stripe PaymentIntent ao frontend.

require_once __DIR__ . '/config/session.php';
ob_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/stripe.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/http.php';
require_once __DIR__ . '/config/cliente_auth.php';
require_once __DIR__ . '/includes/CartService.php';
require_once __DIR__ . '/includes/OrderService.php';
require_once __DIR__ . '/includes/ShippingService.php';

$dados = json_input();

// Validação CSRF: token enviado pelo frontend no corpo JSON
if (
    !$dados
    || !csrf_from_array($dados)
) {
    ob_end_clean();
    json_error('Erro de segurança. Recarregue a página e tente novamente.', 403);
}

// Validação dos dados recebidos
if (
    empty($dados['carrinho'])
    || empty($dados['cliente']['nome'])
    || empty($dados['cliente']['email'])
    || empty($dados['cliente']['telefone'])
    || !filter_var($dados['cliente']['email'], FILTER_VALIDATE_EMAIL)
) {
    ob_end_clean();
    json_error('Dados inválidos ou carrinho vazio.', 400);
}

$carrinho_cliente = $dados['carrinho'];
$dados_cliente    = $dados['cliente'];
$metodo_entrega   = $dados['metodo_entrega'] ?? 'envio';
$cliente_conta = is_cliente_logged_in() ? cliente_atual($conn) : null;
$cliente_id = $cliente_conta ? (int)$cliente_conta['id'] : null;
$origem_encomenda = $cliente_conta ? 'cliente' : 'guest';

if ($cliente_conta) {
    $dados_cliente['email'] = $cliente_conta['email'];
    $dados_cliente['nome'] = trim((string)($dados_cliente['nome'] ?? '')) ?: $cliente_conta['nome'];
    $dados_cliente['telefone'] = trim((string)($dados_cliente['telefone'] ?? '')) ?: ($cliente_conta['telefone'] ?? '');
    $dados_cliente['cliente_nif'] = trim((string)($dados_cliente['cliente_nif'] ?? '')) ?: ($cliente_conta['nif'] ?? '');
} else {
    $conta_existente = customer_find_by_email($conn, $dados_cliente['email']);
    $is_dev = isset($_SESSION['admin_logado']) && $_SESSION['admin_role'] === 'desenvolvedor';
    
    if ($conta_existente && (int)$conta_existente['ativo'] === 1 && !$is_dev) {
        ob_end_clean();
        json_error('Este email ja tem conta. Inicie sessao para continuar o checkout.', 409, [
            'requires_login' => true,
            'login_url' => '/entrar?next=/checkout',
        ]);
    }
}

// Chaves Stripe
$stripe_keys = getStripeKeys();
if (empty($stripe_keys['secret'])) {
    ob_end_clean();
    json_error('Sistema de pagamento não configurado.', 500);
}

$token     = bin2hex(random_bytes(32));
$data_agora = date('Y-m-d H:i:s');

$conn->begin_transaction();
try {
    // ── 1. Validação e cálculo server-side do carrinho ─────────────────────
    $cart_validation = validate_cart_items($conn, $carrinho_cliente);
    $total_encomenda = $cart_validation['total'];
    $total_peso = $cart_validation['weight'];
    $carrinho_verificado = $cart_validation['items'];
    
    $pais_cliente = $dados_cliente['pais_regiao'] ?? 'PT';
    $portes_valor = ($metodo_entrega === 'recolha')
        ? 0
        : calculate_shipping(
            $total_peso,
            $conn,
            $pais_cliente,
            $total_encomenda,
            $dados_cliente['codigo_postal'] ?? null
        );

    // ── 2. Cria o PaymentIntent no Stripe ──────────────────────────────────
    $total_com_portes = $total_encomenda + $portes_valor;
    $amount_cents     = (int)round($total_com_portes * 100); // em cêntimos

    $morada_completa = format_address($dados_cliente);

    $pi_response = stripeRequest('POST', 'payment_intents', [
        'amount'                          => $amount_cents,
        'currency'                        => 'eur',
        'payment_method_types'            => ['card', 'mb_way', 'klarna'],
        'metadata[cliente_email]'         => $dados_cliente['email'],
        'metadata[cliente_nome]'          => $dados_cliente['nome'],
        'description'                     => 'Encomenda TopTop.pt',
        'shipping[name]'                  => $dados_cliente['nome'],
        'shipping[address][line1]'        => $dados_cliente['rua'],
        'shipping[address][city]'         => $dados_cliente['localidade'],
        'shipping[address][postal_code]'  => $dados_cliente['codigo_postal'],
        'shipping[address][country]'      => $dados_cliente['pais_regiao'],
        'shipping[address][state]'        => $dados_cliente['provincia'] ?? '',
    ], $stripe_keys['secret']);

    if (!empty($pi_response['error']) || empty($pi_response['client_secret'])) {
        $err_msg = $pi_response['error']['message'] ?? 'Erro desconhecido ao contactar o Stripe.';
        throw new Exception("Falha ao iniciar o pagamento: " . $err_msg);
    }

    $payment_intent_id = $pi_response['id'];
    $client_secret     = $pi_response['client_secret'];

    // ── 3. Insere a encomenda na DB (estado: "a aguardar pagamento") ────────
    $stmt_enc = $conn->prepare(
        "INSERT INTO encomendas
            (cliente_id, origem, cliente_nome, cliente_email, cliente_telefone, cliente_morada, cliente_nif,
             metodo_entrega, metodo_pagamento, total, portes_envio, token,
             estado, stripe_payment_intent_id, data_encomenda)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Stripe', ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt_enc) {
        throw new \RuntimeException("Erro interno de base de dados (prepare encomenda): " . $conn->error);
    }
    $estado_inicial = 'incompleta';
    $stmt_enc->bind_param("isssssssddssss",
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
        $estado_inicial,
        $payment_intent_id,
        $data_agora
    );
    $stmt_enc->execute();
    $encomenda_id = $conn->insert_id;
    $stmt_enc->close();

    // Atualiza o PaymentIntent com o ID da encomenda (para o webhook)
    stripeRequest('POST', "payment_intents/{$payment_intent_id}", [
        'metadata[encomenda_id]' => $encomenda_id,
    ], $stripe_keys['secret']);

    // ── 4. Insere itens e abate stock ─────────────────────────────────────
    insert_order_items_and_decrement_stock($conn, $encomenda_id, $carrinho_verificado);


    $conn->commit();

    // Email imediato de encomenda pendente (processamento) removido a pedido.

    ob_end_clean();
    json_success([
        'client_secret'=> $client_secret,
        'order_id'     => $encomenda_id,
        'order_token'  => $token,
        'total_cents'  => $amount_cents,
    ]);

} catch (\Throwable $e) {
    $conn->rollback();
    log_app($e->getMessage(), 'ERROR', 'ajax_create_payment_intent.php');
    ob_end_clean();
    json_error('Nao foi possivel iniciar o pagamento. Tente novamente.', 400);
}
