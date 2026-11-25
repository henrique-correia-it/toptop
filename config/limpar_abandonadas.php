<?php
// config/limpar_abandonadas.php
// Limpa encomendas "incompleta" que tenham mais de 30 minutos

require_once __DIR__ . '/session.php';

// Executa no máximo a cada 5 minutos para não sobrecarregar
if (!isset($_SESSION['last_cleanup']) || (time() - $_SESSION['last_cleanup'] > 300)) {
    $_SESSION['last_cleanup'] = time();

    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/stripe.php';
    require_once __DIR__ . '/../includes/OrderService.php';

    // Lock global MySQL (não bloqueante) para evitar concorrência entre sessões
    $lockRes = $conn->query("SELECT GET_LOCK('tt_limpar_abandonadas', 0) AS got_lock");
    $gotLock = $lockRes ? (int)($lockRes->fetch_assoc()['got_lock'] ?? 0) : 0;

    if ($gotLock === 1) {
        try {
            // Procura lote pequeno de encomendas 'incompleta' com mais de 30 minutos
            $stmt_ab = $conn->prepare("SELECT * FROM encomendas WHERE estado = 'incompleta' AND data_encomenda < DATE_SUB(NOW(), INTERVAL 30 MINUTE) ORDER BY id ASC LIMIT 5");
            $stmt_ab->execute();
            $result_ab = $stmt_ab->get_result();

            if ($result_ab->num_rows > 0) {
                require_once __DIR__ . '/../admin/includes/email_handler.php';
                $stripe_keys = getStripeKeys();
                $secret = $stripe_keys['secret'] ?? '';

                while ($row = $result_ab->fetch_assoc()) {
                    $enc_id = (int)$row['id'];
                    $pi_id = $row['stripe_payment_intent_id'];
                    $deve_apagar = true;

                    // 1. Verifica estado no Stripe ANTES de apagar
                    if ($pi_id && $secret) {
                        $pi_data = stripeRequest('GET', "payment_intents/{$pi_id}", [], $secret);
                        $pi_http = (int)($pi_data['_http_code'] ?? 0);
                        $pi_status = $pi_data['status'] ?? '';

                        // Falha de rede/API ou pagamento ainda em processamento: nunca apagar!
                        if ($pi_http !== 200 || $pi_status === '' || $pi_status === 'processing') {
                            continue;
                        }

                        if ($pi_status === 'succeeded') {
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
                            } elseif ($tipo_pm === 'klarna') {
                                $metodo_real = 'Klarna';
                            }

                            $stmt_up = $conn->prepare("UPDATE encomendas SET estado = 'pago', metodo_pagamento = ? WHERE id = ? AND estado = 'incompleta'");
                            $stmt_up->bind_param("si", $metodo_real, $enc_id);
                            $stmt_up->execute();
                            $foi_primeiro = $stmt_up->affected_rows > 0;
                            $stmt_up->close();

                            if ($foi_primeiro) {
                                log_stripe("Encomenda abandonada #{$enc_id} recuperada como PAGA ({$metodo_real}, PI:{$pi_id}).", 'INFO', 'limpar_abandonadas.php');
                                try {
                                    $template_email = ($row['metodo_entrega'] === 'recolha') ? 'recolha_paga' : 'confirmacao_stripe';
                                    enviarEmailEncomenda($template_email, [
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
                                    log_email("limpar_abandonadas: falha no email ao cliente enc #{$enc_id}: " . $e->getMessage(), 'limpar_abandonadas.php', 'ERROR');
                                }

                                try {
                                    $total_enc = (float)$row['total'] + (($row['metodo_entrega'] === 'recolha') ? 0 : (float)$row['portes_envio']);
                                    enviarEmailNotificacaoLoja('nova_encomenda_loja', [
                                        'id'               => $enc_id,
                                        'nome_cliente'     => $row['cliente_nome'],
                                        'email_cliente'    => $row['cliente_email'],
                                        'total_final'      => $total_enc,
                                        'metodo_pagamento' => $metodo_real,
                                    ]);
                                } catch (\Throwable $e) {
                                    log_email("limpar_abandonadas: falha no email a loja enc #{$enc_id}: " . $e->getMessage(), 'limpar_abandonadas.php', 'ERROR');
                                }
                            }
                        } elseif (!in_array($pi_status, ['canceled', 'succeeded'], true)) {
                            // Cancela o PaymentIntent no Stripe para garantir que o cliente não consegue pagar mais tarde
                            stripeRequest('POST', "payment_intents/{$pi_id}/cancel", [], $secret);
                        }
                    }

                    // 2. Se a encomenda não foi paga, repõe stock e apaga-a atomicamente via OrderService (FOR UPDATE)
                    if ($deve_apagar) {
                        try {
                            release_incomplete_order_reservation($conn, $enc_id, (string)$row['token']);
                            log_app("Reserva expirada libertada automaticamente: enc#{$enc_id}.", 'INFO', 'limpar_abandonadas.php');
                        } catch (\Throwable $e_cleanup) {
                            log_exception($e_cleanup, "limpar_abandonadas.php enc#{$enc_id}");
                        }
                    }
                }
            }
            $stmt_ab->close();
        } finally {
            $conn->query("SELECT RELEASE_LOCK('tt_limpar_abandonadas')");
        }
    }
}
