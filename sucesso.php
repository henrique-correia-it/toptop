<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/stripe.php';
require_once __DIR__ . '/config/cliente_auth.php';
$encomenda_id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
$token             = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : null;
$redirect_status   = $_GET['redirect_status'] ?? null;
$payment_intent_id = $_GET['payment_intent'] ?? null;

// --- DEVELOPER PREVIEW MODE (SEGURANÇA REFORÇADA) ---
if (isset($_GET['dev_preview'])) {
    if (isset($_SESSION['admin_logado']) && $_SESSION['admin_role'] === 'desenvolvedor') {
        $encomenda_id = 9999;
        $token = 'dev_token';
        $estado = ($_GET['dev_preview'] === 'sucesso') ? 'sucesso' : 'falhou';
        $payment_intent_id = null; // Evitar disparar lógica real do Stripe
    } else {
        // Se tentar aceder ao preview sem ser dev, redirecionamos para a home
        header("Location: /");
        exit;
    }
}

// Fallback: tenta sempre verificar o estado via API do Stripe se a encomenda ainda estiver pendente/incompleta
if ($encomenda_id && $token && $payment_intent_id) {
    $stmt = $conn->prepare("SELECT * FROM encomendas WHERE id = ? AND token = ? LIMIT 1");
    $stmt->bind_param("is", $encomenda_id, $token);
    $stmt->execute();
    $encomenda = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($encomenda && $encomenda['stripe_payment_intent_id'] === $payment_intent_id) {
        $stripe_keys = getStripeKeys();
        if (!empty($stripe_keys['secret'])) {
            $pi_data = stripeRequest('GET', 'payment_intents/' . $payment_intent_id, [], $stripe_keys['secret']);
            $pi_status = $pi_data['status'] ?? '';

            if ($pi_status === 'succeeded' && in_array($encomenda['estado'], ['pendente', 'incompleta'])) {
                $metodo_real = 'Stripe';
                $tipo_pm = '';

                $charges = $pi_data['charges']['data'] ?? [];
                if (!empty($charges)) {
                    $tipo_pm = $charges[0]['payment_method_details']['type'] ?? '';
                }
                if (empty($tipo_pm) && !empty($pi_data['payment_method'])) {
                    $pm_data = stripeRequest('GET', 'payment_methods/' . $pi_data['payment_method'], [], $stripe_keys['secret']);
                    $tipo_pm = $pm_data['type'] ?? '';
                }

                if ($tipo_pm === 'card') $metodo_real = 'Cartão';
                elseif ($tipo_pm === 'mb_way') $metodo_real = 'MB WAY';
                elseif ($tipo_pm === 'klarna') $metodo_real = 'Klarna';

                $stmt_up = $conn->prepare("UPDATE encomendas SET estado = 'pago', metodo_pagamento = ? WHERE id = ? AND estado IN ('pendente', 'incompleta')");
                $stmt_up->bind_param("si", $metodo_real, $encomenda['id']);
                $stmt_up->execute();
                $foi_primeiro = $stmt_up->affected_rows > 0;
                $stmt_up->close();
                $encomenda['estado'] = 'pago';

                if ($foi_primeiro) {
                    try {
                        require_once __DIR__ . '/admin/includes/email_handler.php';
                        $template_email = ($encomenda['metodo_entrega'] === 'recolha') ? 'recolha_paga' : 'confirmacao_stripe';
                        enviarEmailEncomenda($template_email, [
                            'id' => $encomenda['id'], 'token' => $encomenda['token'], 'cliente_nome' => $encomenda['cliente_nome'],
                            'cliente_email' => $encomenda['cliente_email'], 'total' => (float)$encomenda['total'],
                            'portes_envio' => (float)$encomenda['portes_envio'], 'metodo_entrega' => $encomenda['metodo_entrega'],
                            'metodo_pagamento' => $metodo_real,
                        ]);
                        $total_enc = (float)$encomenda['total'] + (($encomenda['metodo_entrega'] === 'recolha') ? 0 : (float)$encomenda['portes_envio']);
                        enviarEmailNotificacaoLoja('nova_encomenda_loja', [
                            'id' => $encomenda['id'], 'nome_cliente' => $encomenda['cliente_nome'], 'email_cliente' => $encomenda['cliente_email'],
                            'total_final' => $total_enc, 'metodo_pagamento' => $metodo_real,
                        ]);
                    } catch (\Throwable $e) {
                        error_log("sucesso.php: falha no email enc #{$encomenda['id']}: " . $e->getMessage());
                    }
                }
            } elseif (in_array($pi_status, ['canceled', 'requires_payment_method'])) {
                $redirect_status = 'failed'; // Força o estado de falha na UI
            }
        }
    }
}

// Determinação do estado visual da página (Apenas Sucesso ou Erro)
if (!isset($_GET['dev_preview'])) {
    $estado = 'falhou'; // Default cauteloso

    // 1. Reforço via API com polling (Especialmente para MBWay)
    if ($payment_intent_id && !empty($stripe_keys['secret'])) {
        $tentativas = 0;
        while ($tentativas < 3) {
            $pi_data = stripeRequest('GET', 'payment_intents/' . $payment_intent_id, [], $stripe_keys['secret']);
            $pi_status = $pi_data['status'] ?? '';
            
            if ($pi_status === 'succeeded' || $pi_status === 'processing') {
                $estado = 'sucesso';
                if ($pi_status === 'succeeded') break; // Se já teve sucesso, não precisamos de esperar mais
            } elseif (in_array($pi_status, ['canceled', 'requires_payment_method'])) {
                $estado = 'falhou';
                break;
            }
            
            // Se requer ação, esperamos para dar tempo ao Webhook ou ao polling de ver o resultado
            if ($pi_status === 'requires_action' || $pi_status === 'requires_confirmation') {
                $tentativas++;
                usleep(2000000); // 2 segundos
                continue;
            }
            break;
        }
    }

    // 2. RE-CONSULTAR A BASE DE DADOS (Fundamental: o Webhook pode ter atualizado o estado enquanto esperávamos)
    if ($encomenda_id) {
        $stmt = $conn->prepare("SELECT estado FROM encomendas WHERE id = ?");
        $stmt->bind_param("i", $encomenda_id);
        $stmt->execute();
        $res_f = $stmt->get_result()->fetch_assoc();
        $estado_db_final = $res_f['estado'] ?? '';
        $stmt->close();

        if ($estado_db_final === 'pago') {
            $estado = 'sucesso';
        } elseif ($estado_db_final === 'cancelada') {
            $estado = 'falhou';
        }
    }

    // 3. Verificações de segurança adicionais via URL
    if ($redirect_status === 'failed' || $redirect_status === 'canceled') {
        $estado = 'falhou';
    }
}

$titulo_pagina = 'Encomenda Confirmada';
$descricao_pagina = 'Obrigado pela tua compra na TopTop! A tua encomenda foi recebida com sucesso.';
include 'templates/header.php';
?>


<main class="sucesso-main">
    <div class="sc-card">

        <?php if ($estado === 'sucesso'): ?>

            <div class="sc-icon-wrap verde">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
                     stroke="#1a7a3a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </div>

            <?php if ($encomenda_id): ?>
                <div class="sc-badge">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                        <path d="M16 3H8L6 7h12l-2-4z"/>
                    </svg>
                    Encomenda #<?php echo $encomenda_id; ?>
                </div>
            <?php endif; ?>

            <h1>Pagamento Confirmado!</h1>
            <p>O seu pagamento foi processado com sucesso. Vai receber um email de confirmação com todos os detalhes.</p>
            <p>Já estamos a preparar a sua encomenda com o maior cuidado.</p>

            <hr class="sc-divider">

            <div class="sc-btns">
                <?php if ($encomenda_id && $token): ?>
                    <a href="estado_encomenda.php?id=<?php echo $encomenda_id; ?>&token=<?php echo urlencode($token); ?>"
                       class="sc-btn-primary">Acompanhar Encomenda</a>
                <?php endif; ?>
                <a href="produtos.php" class="sc-btn-secondary">Continuar a comprar</a>
            </div>

        <?php elseif ($estado === 'aguardar'): ?>

            <div class="sc-icon-wrap laranja">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
                     stroke="#c87f00" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </div>

            <?php if ($encomenda_id): ?>
                <div class="sc-badge">Encomenda #<?php echo $encomenda_id; ?></div>
            <?php endif; ?>

            <h1>A Aguardar Confirmação</h1>
            <p>Se escolheu <strong>MBWay</strong>, verifique a notificação na app MB WAY e aprove o pagamento.</p>
            <p>Receberá um email assim que o pagamento for confirmado.</p>

            <hr class="sc-divider">

            <div class="sc-btns">
                <?php if ($encomenda_id && $token): ?>
                    <a href="estado_encomenda.php?id=<?php echo $encomenda_id; ?>&token=<?php echo urlencode($token); ?>"
                       class="sc-btn-primary">Ver Estado da Encomenda</a>
                <?php endif; ?>
                <a href="produtos.php" class="sc-btn-secondary">Continuar a comprar</a>
            </div>

        <?php else: /* falhou */ ?>

            <div class="sc-icon-wrap vermelho">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
                     stroke="#c0392b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </div>

            <?php if ($encomenda_id): ?>
                <div class="sc-badge" style="color: #c0392b; background: #fff0f0;">
                    Encomenda #<?php echo $encomenda_id; ?> (Não Paga)
                </div>
            <?php endif; ?>

            <h1>Pagamento Recusado</h1>
            <p>O pagamento não foi concluído ou foi recusado. Pode tentar novamente utilizando o mesmo ou outro método de pagamento.</p>

            <hr class="sc-divider">

            <div class="sc-btns">
                <a href="produtos.php" class="sc-btn-primary">Continuar a comprar</a>
            </div>

        <?php endif; ?>

    </div>
</main>

<?php include 'templates/footer.php'; ?>
