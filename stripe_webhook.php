<?php
// stripe_webhook.php
// Recebe eventos do Stripe e atualiza o estado das encomendas.
// Deve ser apontado no Dashboard Stripe → Developers → Webhooks.
// Eventos a subscrever: payment_intent.succeeded | payment_intent.payment_failed | payment_intent.canceled

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/stripe.php';
require_once __DIR__ . '/admin/includes/email_handler.php';

header('Content-Type: application/json');

function log_wh(string $msg, string $nivel = 'INFO'): void {
    log_stripe($msg, $nivel);
    if ($nivel !== 'INFO') {
        error_log("[Stripe-{$nivel}] {$msg}");
    }
}

log_wh("--- Webhook invocado ---");

$payload    = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$stripe_keys    = getStripeKeys();
$webhook_secret = $stripe_keys['webhook_secret'];

if (empty($webhook_secret)) {
    log_wh("STRIPE_WEBHOOK_SECRET não configurado no ficheiro .env!", 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Webhook não configurado.']);
    exit;
}

$event = stripeVerifyWebhookSignature($payload, $sig_header, $webhook_secret);
if (!$event) {
    log_wh("ERRO: Assinatura inválida. Pode ser secret errado ou relógio do servidor dessincronizado (mais de 5 min de diferença).");
    http_response_code(400);
    echo json_encode(['error' => 'Assinatura inválida ou evento expirado.']);
    exit;
}

$event_type     = $event['type'] ?? '';
$payment_intent = $event['data']['object'] ?? [];
$pi_id          = $payment_intent['id'] ?? '';

log_wh("Evento recebido com sucesso: {$event_type} para PaymentIntent {$pi_id}");

if (empty($pi_id)) {
    log_wh("Aviso: PaymentIntent ID vazio, a ignorar.");
    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}

// Encontra a encomenda associada ao PaymentIntent
$stmt = $conn->prepare("SELECT * FROM encomendas WHERE stripe_payment_intent_id = ? LIMIT 1");
$stmt->bind_param("s", $pi_id);
$stmt->execute();
$encomenda = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$encomenda) {
    // PI não pertence a nenhuma encomenda conhecida — ignora sem erro
    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}

switch ($event_type) {

    // ── Pagamento confirmado ─────────────────────────────────────────────────
    case 'payment_intent.succeeded':
        if ($encomenda['estado'] !== 'pago') {

            // Detectar método de pagamento real (Cartão vs MB WAY)
            $metodo_real = 'Stripe';
            $tipo_pm = '';

            // Tentativa 1: charges array (API antiga)
            $charges = $payment_intent['charges']['data'] ?? [];
            if (!empty($charges)) {
                $tipo_pm = $charges[0]['payment_method_details']['type'] ?? '';
            }

            // Tentativa 2: buscar diretamente o PaymentMethod via API
            if (empty($tipo_pm) && !empty($payment_intent['payment_method'])) {
                $pm_data = stripeRequest('GET', 'payment_methods/' . $payment_intent['payment_method'], [], $stripe_keys['secret']);
                $tipo_pm = $pm_data['type'] ?? '';
            }

            if ($tipo_pm === 'card') {
                $metodo_real = 'Cartão';
            } elseif ($tipo_pm === 'mb_way') {
                $metodo_real = 'MB WAY';
            } elseif ($tipo_pm === 'klarna') {
                $metodo_real = 'Klarna';
            }

            $stmt_up = $conn->prepare("UPDATE encomendas SET estado = 'pago', metodo_pagamento = ? WHERE id = ? AND estado IN ('pendente', 'incompleta')");
            $stmt_up->bind_param("si", $metodo_real, $encomenda['id']);
            $stmt_up->execute();
            $foi_primeiro = $stmt_up->affected_rows > 0;
            $stmt_up->close();

            if ($foi_primeiro) {
                try {
                    $template_email = ($encomenda['metodo_entrega'] === 'recolha') ? 'recolha_paga' : 'confirmacao_stripe';
                    enviarEmailEncomenda($template_email, [
                        'id'               => $encomenda['id'],
                        'token'            => $encomenda['token'],
                        'cliente_nome'     => $encomenda['cliente_nome'],
                        'cliente_email'    => $encomenda['cliente_email'],
                        'total'            => (float)$encomenda['total'],
                        'portes_envio'     => (float)$encomenda['portes_envio'],
                        'metodo_entrega'   => $encomenda['metodo_entrega'],
                        'metodo_pagamento' => $metodo_real,
                    ]);
                    log_wh("SUCESSO: Email de confirmação ($template_email) enviado ao cliente (Enc #{$encomenda['id']}).");
                    $total_enc = (float)$encomenda['total'] + (($encomenda['metodo_entrega'] === 'recolha') ? 0 : (float)$encomenda['portes_envio']);
                    enviarEmailNotificacaoLoja('nova_encomenda_loja', [
                        'id'               => $encomenda['id'],
                        'nome_cliente'     => $encomenda['cliente_nome'],
                        'email_cliente'    => $encomenda['cliente_email'],
                        'total_final'      => $total_enc,
                        'metodo_pagamento' => $metodo_real,
                    ]);
                } catch (\Throwable $e) {
                    log_wh("Falha no email para Enc #{$encomenda['id']}: " . $e->getMessage(), 'WARNING');
                    log_email("Webhook: falha no email para encomenda #{$encomenda['id']}: " . $e->getMessage(), 'stripe_webhook.php');
                }
            } else {
                log_wh("INFO: Email não enviado pelo webhook — outro processo já atualizou o estado (Enc #{$encomenda['id']}).");
            }
        } else {
            log_wh("INFO: Encomenda #{$encomenda['id']} já estava com estado '{$encomenda['estado']}', ignorado.");
        }
        break;

    // ── Pagamento falhou ou foi cancelado ────────────────────────────────────
    case 'payment_intent.payment_failed':
    case 'payment_intent.canceled':
        if (!in_array($encomenda['estado'], ['pago', 'cancelada', 'concluida', 'enviada'], true)) {
            $conn->begin_transaction();
            try {
                $stmt_can = $conn->prepare("UPDATE encomendas SET estado = 'cancelada' WHERE id = ?");
                $stmt_can->bind_param("i", $encomenda['id']);
                $stmt_can->execute();
                $stmt_can->close();

                // Devolve stock
                $stmt_itens = $conn->prepare("SELECT variacao_id, quantidade FROM encomenda_itens WHERE encomenda_id = ?");
                $stmt_itens->bind_param("i", $encomenda['id']);
                $stmt_itens->execute();
                $itens = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_itens->close();

                $stmt_stock = $conn->prepare("UPDATE produto_variacoes SET quantidade = quantidade + ? WHERE id = ?");
                foreach ($itens as $it) {
                    if (!$it['variacao_id']) continue;
                    $stmt_stock->bind_param("ii", $it['quantidade'], $it['variacao_id']);
                    $stmt_stock->execute();
                }
                $stmt_stock->close();

                $conn->commit();

                // Notifica o cliente sobre o pagamento falhado
                try {
                    enviarEmailEncomenda('cancelada_por_atraso', [
                        'id'               => $encomenda['id'],
                        'token'            => $encomenda['token'],
                        'cliente_nome'     => $encomenda['cliente_nome'],
                        'cliente_email'    => $encomenda['cliente_email'],
                        'total'            => (float)$encomenda['total'],
                        'portes_envio'     => (float)$encomenda['portes_envio'],
                        'metodo_entrega'   => $encomenda['metodo_entrega'],
                        'metodo_pagamento' => $encomenda['metodo_pagamento'],
                    ]);
                } catch (\Throwable $e_mail) {
                    log_email("Webhook: falha no email de cancelamento para encomenda #{$encomenda['id']}: " . $e_mail->getMessage(), 'stripe_webhook.php');
                    log_wh("Falha no email de cancelamento para Enc #{$encomenda['id']}: " . $e_mail->getMessage(), 'WARNING');
                }

            } catch (\Throwable $e) {
                $conn->rollback();
                log_wh("Erro ao cancelar encomenda #{$encomenda['id']}: " . $e->getMessage(), 'ERROR');
                log_app("Webhook: erro ao cancelar encomenda #{$encomenda['id']}: " . $e->getMessage(), 'ERROR', 'stripe_webhook.php');
            }
        }
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
exit;
