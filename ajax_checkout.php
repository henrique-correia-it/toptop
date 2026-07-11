<?php
// loja-roupa/ajax_checkout.php (VERSÃO CORRIGIDA E FINAL)

require_once __DIR__ . '/config/session.php';
ob_start();

include 'config/database.php';
include 'admin/includes/email_handler.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/http.php';
require_once __DIR__ . '/config/cliente_auth.php';
require_once __DIR__ . '/includes/CartService.php';
require_once __DIR__ . '/includes/OrderService.php';
require_once __DIR__ . '/includes/ShippingService.php';

$response = ['sucesso' => false, 'mensagem' => 'Ocorreu um erro inesperado.'];
$response_status = 200;
$dados = json_input();

// Validação CSRF: o token é enviado pelo frontend no corpo JSON
if (
    !$dados
    || !csrf_from_array($dados)
) {
    ob_end_clean();
    json_error('Erro de segurança. Recarregue a página e tente novamente.', 403);
}

if (!isset($dados['carrinho']) || empty($dados['carrinho']) || empty($dados['cliente']['nome'])) {
    $response['mensagem'] = 'Dados inválidos ou carrinho vazio.';
    $response_status = 400;
} else {
    $carrinho_cliente = $dados['carrinho'];
    $dados_cliente = $dados['cliente'];
    $metodo_entrega = $dados['metodo_entrega'] ?? 'envio';
    $metodo_pagamento = $dados['metodo_pagamento'] ?? 'N/A';
    $token = bin2hex(random_bytes(32));
    $cliente_conta = is_cliente_logged_in() ? cliente_atual($conn) : null;
    $cliente_id = $cliente_conta ? (int)$cliente_conta['id'] : null;
    $origem_encomenda = $cliente_conta ? 'cliente' : 'guest';

    if ($cliente_conta) {
        $dados_cliente['email'] = $cliente_conta['email'];
        $dados_cliente['nome'] = trim((string)($dados_cliente['nome'] ?? '')) ?: $cliente_conta['nome'];
        $dados_cliente['telefone'] = trim((string)($dados_cliente['telefone'] ?? '')) ?: ($cliente_conta['telefone'] ?? '');
    } else {
        $conta_existente = customer_find_by_email($conn, $dados_cliente['email'] ?? '');
        if ($conta_existente && (int)$conta_existente['ativo'] === 1) {
            ob_end_clean();
            json_error('Este email ja tem conta. Inicie sessao para continuar o checkout.', 409, [
                'requires_login' => true,
                'login_url' => '/entrar?next=/checkout',
            ]);
        }
    }

    $data_agora = date('Y-m-d H:i:s');

    $conn->begin_transaction();
    try {
        $cart_validation = validate_cart_items($conn, $carrinho_cliente, 'default.jpg');
        $total_encomenda = $cart_validation['total'];
        $total_peso_gramas = $cart_validation['weight'];
        $carrinho_verificado = $cart_validation['items'];

        $total_portes = ($metodo_entrega === 'envio')
            ? calculate_shipping(
                $total_peso_gramas,
                $conn,
                $dados_cliente['pais_regiao'] ?? 'PT',
                $total_encomenda,
                $dados_cliente['codigo_postal'] ?? null
            )
            : 0;

        $stmt_encomenda = $conn->prepare(
            "INSERT INTO encomendas (cliente_id, origem, cliente_nome, cliente_email, cliente_telefone, cliente_morada, metodo_entrega, metodo_pagamento, total, portes_envio, token, estado, data_encomenda)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?)"
        );
        $stmt_encomenda->bind_param("isssssssddss",
            $cliente_id,
            $origem_encomenda,
            $dados_cliente['nome'], $dados_cliente['email'], $dados_cliente['telefone'], $dados_cliente['morada'],
            $metodo_entrega, $metodo_pagamento, $total_encomenda, $total_portes, $token, $data_agora
        );

        $stmt_encomenda->execute();
        $encomenda_id = $conn->insert_id;
        $stmt_encomenda->close();

        insert_order_items_and_decrement_stock($conn, $encomenda_id, $carrinho_verificado);

        $conn->commit();

        try {
            $dados_para_email = [
                'id' => $encomenda_id, 'token' => $token, 'cliente_nome' => $dados_cliente['nome'],
                'cliente_email' => $dados_cliente['email'], 'total' => $total_encomenda,
                'metodo_entrega' => $metodo_entrega
            ];
            enviarEmailEncomenda('confirmacao', $dados_para_email);
        } catch (Exception $e) {
            log_email("Falha ao enviar email de confirmação para encomenda #{$encomenda_id}: " . $e->getMessage(), 'ajax_checkout.php');
            error_log("Falha ao enviar e-mail de confirmação para encomenda #{$encomenda_id}: " . $e->getMessage());
        }

        $response = [
            'sucesso' => true,
            'mensagem' => "Pedido de encomenda submetido com sucesso!",
            'redirect_url' => 'sucesso.php?id=' . $encomenda_id
        ];

    } catch (Exception $e) {
        $conn->rollback();
        log_app($e->getMessage(), 'ERROR', 'ajax_checkout.php');
        $response_status = 400;
        $response['mensagem'] = $e->getMessage();
    }
}

ob_end_clean();
json_response($response, $response_status);
?>
