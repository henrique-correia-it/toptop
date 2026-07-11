<?php
// config/limpar_abandonadas.php
// Limpa encomendas "incompleta" que tenham mais de 30 minutos

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Executa no máximo a cada 5 minutos para não sobrecarregar
if (!isset($_SESSION['last_cleanup']) || (time() - $_SESSION['last_cleanup'] > 300)) {
    $_SESSION['last_cleanup'] = time();

    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/stripe.php';

    // Procura encomendas 'incompleta' com mais de 30 minutos
    $stmt_ab = $conn->prepare("SELECT * FROM encomendas WHERE estado = 'incompleta' AND data_encomenda < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $stmt_ab->execute();
    $result_ab = $stmt_ab->get_result();

    if ($result_ab->num_rows > 0) {
        require_once __DIR__ . '/../admin/includes/email_handler.php';
        $stripe_keys = getStripeKeys();
        $secret = $stripe_keys['secret'] ?? '';

        while ($row = $result_ab->fetch_assoc()) {
            $enc_id = $row['id'];
            $pi_id = $row['stripe_payment_intent_id'];
            $deve_apagar = true;

            // 1. Verifica estado no Stripe ANTES de apagar
            if ($pi_id && $secret) {
                $pi_data = stripeRequest('GET', "payment_intents/{$pi_id}", [], $secret);
                
                if (!empty($pi_data['status']) && $pi_data['status'] === 'succeeded') {
                    // FOI PAGO! O webhook deve ter falhado, forçamos o estado para PAGO e não apagamos.
                    $deve_apagar = false;
                    
                    $metodo_real = 'Stripe';
                    $tipo_pm = '';

                    $charges = $pi_data['charges']['data'] ?? [];
                    if (!empty($charges)) {
                        $tipo_pm = $charges[0]['payment_method_details']['type'] ?? '';
                    }
                    if (empty($tipo_pm) && !empty($pi_data['payment_method'])) {
                        $pm_data = stripeRequest('GET', 'payment_methods/' . $pi_data['payment_method'], [], $secret);
                        $tipo_pm = $pm_data['type'] ?? '';
                    }

                    if ($tipo_pm === 'card') {
                        $metodo_real = 'Cartão';
                    } elseif ($tipo_pm === 'mb_way') {
                        $metodo_real = 'MB WAY';
                    }

                    $stmt_up = $conn->prepare("UPDATE encomendas SET estado = 'pago', metodo_pagamento = ? WHERE id = ?");
                    $stmt_up->bind_param("si", $metodo_real, $enc_id);
                    $stmt_up->execute();
                    $stmt_up->close();

                    try {
                        enviarEmailEncomenda('confirmacao_stripe', [
                            'id'               => $enc_id,
                            'token'            => $row['token'],
                            'cliente_nome'     => $row['cliente_nome'],
                            'cliente_email'    => $row['cliente_email'],
                            'total'            => (float)$row['total'],
                            'portes_envio'     => (float)$row['portes_envio'],
                            'metodo_entrega'   => $row['metodo_entrega'],
                            'metodo_pagamento' => $metodo_real,
                        ]);
                    } catch (\Throwable $e) {
                        error_log("limpar_abandonadas: falha no email ao cliente enc #{$enc_id}: " . $e->getMessage());
                    }

                    $total_enc = (float)$row['total'] + (($row['metodo_entrega'] === 'levantamento') ? 0 : (float)$row['portes_envio']);
                    enviarEmailNotificacaoLoja('nova_encomenda_loja', [
                        'id'               => $enc_id,
                        'nome_cliente'     => $row['cliente_nome'],
                        'email_cliente'    => $row['cliente_email'],
                        'total_final'      => $total_enc,
                        'metodo_pagamento' => $metodo_real,
                    ]);
                    
                } elseif (!empty($pi_data['status']) && !in_array($pi_data['status'], ['canceled', 'succeeded'])) {
                    // Cancela o PaymentIntent no Stripe para garantir que o cliente não consegue pagar mais tarde
                    stripeRequest('POST', "payment_intents/{$pi_id}/cancel", [], $secret);
                }
            }

            // 2. Se a encomenda não foi paga, repõe stock e apaga-a
            if ($deve_apagar) {
                $stmt_itens = $conn->prepare("SELECT variacao_id, quantidade FROM encomenda_itens WHERE encomenda_id = ?");
                $stmt_itens->bind_param("i", $enc_id);
                $stmt_itens->execute();
                $itens = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_itens->close();

                $conn->begin_transaction();
                try {
                    $stmt_stock = $conn->prepare("UPDATE produto_variacoes SET quantidade = quantidade + ? WHERE id = ?");
                    foreach ($itens as $it) {
                        if (!$it['variacao_id']) continue;
                        $stmt_stock->bind_param("ii", $it['quantidade'], $it['variacao_id']);
                        $stmt_stock->execute();
                    }
                    $stmt_stock->close();

                    $stmt_del_itens = $conn->prepare("DELETE FROM encomenda_itens WHERE encomenda_id = ?");
                    $stmt_del_itens->bind_param("i", $enc_id);
                    $stmt_del_itens->execute();
                    $stmt_del_itens->close();

                    $stmt_del_enc = $conn->prepare("DELETE FROM encomendas WHERE id = ?");
                    $stmt_del_enc->bind_param("i", $enc_id);
                    $stmt_del_enc->execute();
                    $stmt_del_enc->close();

                    $conn->commit();
                } catch (\Throwable $e_cleanup) {
                    $conn->rollback();
                    error_log("limpar_abandonadas: falha ao apagar enc #{$enc_id}: " . $e_cleanup->getMessage());
                }
            }
        }
    }
    $stmt_ab->close();
}
