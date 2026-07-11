<?php
// ajax_libertar_reserva_checkout.php

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/stripe.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/http.php';
require_once __DIR__ . '/includes/OrderService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metodo invalido.', 405);
}

$data = json_input();

if (!csrf_from_array($data)) {
    json_error('Erro de seguranca. Recarregue a pagina e tente novamente.', 403);
}

$orderId = (int)($data['order_id'] ?? 0);
$token = (string)($data['order_token'] ?? '');

if ($orderId <= 0 || $token === '') {
    json_error('Reserva invalida.', 400);
}

try {
    $stmt = $conn->prepare("SELECT id, estado, stripe_payment_intent_id FROM encomendas WHERE id = ? AND token = ? LIMIT 1");
    $stmt->bind_param('is', $orderId, $token);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        json_success(['mensagem' => 'Reserva ja libertada.']);
    }

    if ($order['estado'] !== 'incompleta') {
        json_error('Esta reserva ja nao pode ser editada porque o estado mudou.', 409);
    }

    $stripeKeys = getStripeKeys();
    $secret = $stripeKeys['secret'] ?? '';
    $paymentIntentId = $order['stripe_payment_intent_id'] ?? '';

    if ($paymentIntentId && $secret) {
        $paymentIntent = stripeRequest('GET', "payment_intents/{$paymentIntentId}", [], $secret);
        $status = $paymentIntent['status'] ?? '';

        if ($status === 'succeeded') {
            json_error('O pagamento ja foi concluido. A encomenda nao foi alterada.', 409);
        }

        if ($status && !in_array($status, ['canceled', 'succeeded'], true)) {
            $cancelResult = stripeRequest('POST', "payment_intents/{$paymentIntentId}/cancel", [], $secret);
            $cancelStatus = $cancelResult['status'] ?? '';

            if (!empty($cancelResult['error'])) {
                $paymentIntent = stripeRequest('GET', "payment_intents/{$paymentIntentId}", [], $secret);
                if (($paymentIntent['status'] ?? '') === 'succeeded') {
                    json_error('O pagamento ja foi concluido. A encomenda nao foi alterada.', 409);
                }

                throw new Exception($cancelResult['error']['message'] ?? 'Nao foi possivel cancelar o pagamento temporario.');
            }

            if ($cancelStatus && !in_array($cancelStatus, ['canceled', 'requires_payment_method'], true)) {
                throw new Exception('Nao foi possivel confirmar o cancelamento do pagamento temporario.');
            }
        }
    }

    release_incomplete_order_reservation($conn, $orderId, $token);
    json_success(['mensagem' => 'Reserva libertada.']);
} catch (Throwable $e) {
    log_app($e->getMessage(), 'ERROR', 'ajax_libertar_reserva_checkout.php enc#' . $orderId);
    json_error('Nao foi possivel libertar a reserva neste momento.', 500);
}
