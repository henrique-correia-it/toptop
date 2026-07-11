<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';
require_once __DIR__ . '/../config/formatters.php';

// --- Validação de Acesso ---
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: /entrar"); exit;
}

// --- Token CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$paginas_permitidas = ['admin', 'encomendas', 'dev', 'reservas_stock'];
$return_to = $_GET['return_to'] ?? 'encomendas';
if (!in_array($return_to, $paginas_permitidas)) {
    $return_to = 'encomendas'; // Garante que o valor é seguro
}

if ($return_to === 'dev') {
    $return_url = '/dev';
} else {
    $return_url = htmlspecialchars($return_to) . '.php';
}

// --- Obter Dados da Encomenda para Exibição ---
$encomenda_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$encomenda_id) {
    header("Location: encomendas.php");
    exit;
}

$stmt_enc = $conn->prepare("SELECT * FROM encomendas WHERE id = ?");
$stmt_enc->bind_param("i", $encomenda_id);
$stmt_enc->execute();
$encomenda = $stmt_enc->get_result()->fetch_assoc();
$stmt_enc->close();
if (!$encomenda) {
    include '../templates/header.php';
    echo "<main class='admin-main-content'><p>Encomenda não encontrada.</p></main>";
    include '../templates/footer.php';
    exit;
}

// Fallback: se a encomenda está pendente, verificar o Stripe por segurança
if ($encomenda['estado'] === 'pendente' && !empty($encomenda['stripe_payment_intent_id'])) {
    require_once __DIR__ . '/../config/stripe.php';
    require_once __DIR__ . '/includes/email_handler.php';

    $stripe_keys = getStripeKeys();
    if (!empty($stripe_keys['secret'])) {
        $pi_data = stripeRequest('GET', 'payment_intents/' . $encomenda['stripe_payment_intent_id'], [], $stripe_keys['secret']);

        if (!empty($pi_data['status']) && $pi_data['status'] === 'succeeded') {
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

            if ($tipo_pm === 'card') {
                $metodo_real = 'Cartão';
            } elseif ($tipo_pm === 'mb_way') {
                $metodo_real = 'MB WAY';
            }

            $stmt_up = $conn->prepare("UPDATE encomendas SET estado = 'pago', metodo_pagamento = ? WHERE id = ?");
            $stmt_up->bind_param("si", $metodo_real, $encomenda['id']);
            $stmt_up->execute();
            $stmt_up->close();

            $encomenda['estado'] = 'pago';
            $encomenda['metodo_pagamento'] = $metodo_real;

            try {
                $template_email = ($encomenda['metodo_entrega'] === 'recolha') ? 'recolha_paga' : 'confirmacao_stripe';
                enviarEmailEncomenda($template_email, [
                    'id'               => $encomenda['id'],
                    'token'            => $encomenda['token'],
                    'cliente_nome'     => $encomenda['cliente_nome'],
                    'cliente_email'    => $encomenda['cliente_email'],
                    'total'            => (float)$encomenda['total'] + (($encomenda['metodo_entrega'] === 'recolha') ? 0 : (float)$encomenda['portes_envio']),
                    'metodo_entrega'   => $encomenda['metodo_entrega'],
                    'metodo_pagamento' => $metodo_real,
                ]);
            } catch (Exception $e) { }

            // Recarregar a encomenda da base de dados para garantir que temos o histórico de mensagens atualizado
            $stmt_refresh = $conn->prepare("SELECT * FROM encomendas WHERE id = ?");
            $stmt_refresh->bind_param("i", $encomenda_id);
            $stmt_refresh->execute();
            $encomenda = $stmt_refresh->get_result()->fetch_assoc();
            $stmt_refresh->close();
        }
    }
}

$stmt_itens = $conn->prepare(
    "SELECT ei.*, COALESCE(ei.foto_snapshot, p.foto_principal) as foto_exibicao, pv.referencia as ref_variacao
     FROM encomenda_itens ei
     LEFT JOIN produtos p ON ei.produto_id = p.id
     LEFT JOIN produto_variacoes pv ON ei.variacao_id = pv.id
     WHERE ei.encomenda_id = ?"
);
$stmt_itens->bind_param("i", $encomenda_id);
$stmt_itens->execute();
$itens = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_itens->close();

// --- Lógica de Estados (fluxo Stripe-only, envio apenas) ---
$todos_estados_possiveis = [
    'pendente', 'pago', 'enviada', 'concluida', 'cancelada'
];

$map_nomes = [
    'incompleta'       => 'A aguardar pagamento',
    'pendente'         => 'Pendente',
    'pago'             => 'Pago',
    'em processamento' => 'Em Processamento',
    'enviada'          => 'Enviada',
    'concluida'        => 'Concluída',
    'cancelada'        => 'Cancelada',
    // Legacy
    'a aguardar pagamento'     => 'A Aguardar Pagamento',
    'pronta para levantamento' => 'Pronta p/ Levantamento',
    'pagamento na entrega'     => 'Pagamento na Entrega',
];

$map_badges = [
    'pendente'                 => 'badge-amarelo',
    'a aguardar pagamento'     => 'badge-amarelo',
    'incompleta'               => 'badge-amarelo',
    'pago'                     => 'badge-verde',
    'em processamento'         => 'badge-azul',
    'enviada'                  => 'badge-azul',
    'pronta para levantamento' => 'badge-azul',
    'concluida'                => 'badge-teal',
    'cancelada'                => 'badge-vermelho',
    'pagamento na entrega'     => 'badge-roxo',
];

$estados_permitidos_para_esta_encomenda = ['pendente', 'pago'];
if ($encomenda['metodo_entrega'] === 'recolha') {
    $estados_permitidos_para_esta_encomenda[] = 'pronta para levantamento';
} else {
    $estados_permitidos_para_esta_encomenda[] = 'enviada';
}
$estados_permitidos_para_esta_encomenda[] = 'concluida';
$estados_permitidos_para_esta_encomenda[] = 'cancelada';

// Suporte a estados legacy: garante que o estado atual está sempre disponível
$estado_atual = $encomenda['estado'];
if ($estado_atual !== 'incompleta' && !in_array($estado_atual, $estados_permitidos_para_esta_encomenda)) {
    $estados_permitidos_para_esta_encomenda[] = $estado_atual;
}

$is_encomenda_incompleta = $estado_atual === 'incompleta';


$mensagens_enviadas = json_decode($encomenda['mensagens_cliente'] ?? '[]', true);

$telefone_whatsapp = preg_replace('/[^0-9]/', '', $encomenda['cliente_telefone']);
if (substr($telefone_whatsapp, 0, 3) !== '351' && strlen($telefone_whatsapp) == 9) {
    $telefone_whatsapp = '351' . $telefone_whatsapp;
}




include '../templates/header.php';
?>

<main class="dashboard-container animate-entry">

<!-- Bloquear scroll automático no refresh -->
<script>
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    window.scrollTo(0, 0);
</script>
    <div class="admin-page-header">
        <div class="header-title-container order-detail-title">
            <a href="<?php echo $return_url; ?>" class="btn-back-arrow" title="Voltar">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
            <div class="order-title-copy">
                <h2>Encomenda #<?php echo htmlspecialchars($encomenda['id']); ?></h2>
                <?php if ($is_encomenda_incompleta): ?>
                    <p>Encomenda criada no checkout. Aguarda pagamento para passar ao fluxo normal.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="header-actions-container order-status-top">
            <span class="badge <?php echo $map_badges[$encomenda['estado']] ?? 'badge-cinzento'; ?>"><?php echo $map_nomes[$encomenda['estado']] ?? ucfirst($encomenda['estado']); ?></span>
        </div>
    </div>

    <div class="admin-form-grid">
        <div class="form-coluna-principal">
            <div class="form-card">
                <div class="form-card-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        Informação do Cliente
                    </h3>
                </div>
                <div class="form-card-body">
                    <div class="info-cliente-item">
                        <div class="info-cliente-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>
                        <div class="info-cliente-texto">
                            <strong>Nome</strong>
                            <span><?php echo htmlspecialchars($encomenda['cliente_nome']); ?></span>
                        </div>
                    </div>
                     <div class="info-cliente-item">
                        <div class="info-cliente-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
                        <div class="info-cliente-texto">
                            <strong>Data do Pedido</strong>
                            <span><?php echo date('d/m/Y, H:i', strtotime($encomenda['data_encomenda'])); ?></span>
                        </div>
                    </div>
                    <div class="info-cliente-item com-acoes">
                        <div class="info-cliente-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></div>
                        <div class="info-cliente-texto">
                            <strong>Email</strong>
                            <a href="mailto:<?php echo htmlspecialchars($encomenda['cliente_email']); ?>"><?php echo htmlspecialchars($encomenda['cliente_email']); ?></a>
                        </div>
                    </div>
                    <div class="info-cliente-item com-acoes">
                       <div class="info-cliente-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg></div>
                       <div class="info-cliente-texto">
                            <strong>Telefone</strong>
                            <a href="tel:<?php echo htmlspecialchars($encomenda['cliente_telefone']); ?>"><?php echo htmlspecialchars($encomenda['cliente_telefone']); ?></a>
                       </div>
                       <div class="botoes-contacto">
                            <a href="https://wa.me/<?php echo $telefone_whatsapp; ?>" target="_blank" class="whatsapp-btn">WhatsApp</a>
                       </div>
                    </div>
                    <?php if(!empty($encomenda['cliente_nif'])): ?>
                    <div class="info-cliente-item">
                        <div class="info-cliente-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg></div>
                        <div class="info-cliente-texto">
                            <strong>NIF / Contribuinte</strong>
                            <span><?php echo htmlspecialchars($encomenda['cliente_nif']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="info-cliente-item info-entrega">
                        <div class="info-cliente-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div>
                        <div class="info-cliente-texto">
                            <strong>Método de Pagamento</strong>
                            <?php
                            $mp = $encomenda['metodo_pagamento'] ?? 'N/D';
                            if ($mp === 'Stripe' && $encomenda['estado'] === 'pendente') {
                                echo '<span style="color:#888;">A aguardar confirmação de pagamento…</span>';
                            } elseif ($mp === 'Stripe') {
                                echo '<span>Stripe</span>';
                            } else {
                                echo '<span>' . htmlspecialchars($mp) . '</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php if (!empty($encomenda['stripe_payment_intent_id'])): ?>
                    <div class="info-cliente-item">
                        <div class="info-cliente-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
                        <div class="info-cliente-texto">
                            <strong>Stripe Payment ID</strong>
                            <span style="font-family:monospace;font-size:.8em;color:#888;"><?php echo htmlspecialchars($encomenda['stripe_payment_intent_id']); ?></span>
                            <small style="color:#aaa;">Use este ID para reembolsos no painel Stripe.</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="info-cliente-item info-entrega">
                        <div class="info-cliente-icon">
                            <?php if ($encomenda['metodo_entrega'] === 'recolha'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                            <?php endif; ?>
                        </div>
                        <div class="info-cliente-texto">
                            <strong>Método de Entrega</strong>
                            <?php if ($encomenda['metodo_entrega'] === 'recolha'): ?>
                                <span style="color:var(--cor-accent); font-weight:700;">RECOLHA NA LOJA</span>
                                <small style="color:#666; display:block; margin-top:2px;">O cliente virá levantar à loja física.</small>
                            <?php else: ?>
                                <span>Envio por Transportadora</span>
                                <div class="morada-envio" style="margin-top:5px; color:#555;"><?php echo nl2br(htmlspecialchars($encomenda['cliente_morada'])); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        Produtos da Encomenda
                    </h3>
                </div>
                <div class="form-card-body" style="padding: 0;">
                    <div class="table-wrapper">
                        <table class="admin-table tabela-itens-encomenda">
                            <thead><tr><th style="text-align: left;">Produto</th><th>Qtd</th><th style="text-align: right;">Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($itens as $item): ?>
                                <tr>
                                    <td style="text-align: left;">
                                        <?php if (!empty($item['foto_exibicao'])): ?><img src="/public/images/<?php echo htmlspecialchars($item['foto_exibicao']); ?>" alt=""><?php endif; ?>
                                        <div class="item-info-cell">
                                            <strong><?php echo htmlspecialchars($item['nome_produto']); ?></strong>
                                            <?php $selecoes = json_decode($item['selecoes_atributos'], true);
                                            if (!empty($selecoes)):
                                                echo '<div class="item-atributos-cell">';
                                                $detalhes_atributos = [];
                                                foreach ($selecoes as $atributo => $valor) { $detalhes_atributos[] = htmlspecialchars($valor); }
                                                echo implode(' / ', $detalhes_atributos);
                                                echo '</div>';
                                            endif; ?>
                                            <?php if(!empty($item['ref_variacao'])): ?>
                                                <div class="item-ref-cell">Ref: <?php echo htmlspecialchars($item['ref_variacao']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['quantidade']); ?></td>
                                    <td style="text-align: right;"><?php echo format_money($item['preco_unitario'] * $item['quantidade']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="detalhes-card-sumario">
                        <div class="sumario-box">
                            <div class="sumario-linha"><span>Subtotal (Produtos)</span><span><?php echo format_money($encomenda['total']); ?></span></div>
                            <div class="sumario-linha"><span>Portes de Envio</span><span id="display-portes"><?php echo format_money((float)$encomenda['portes_envio']); ?></span></div>
                            <div class="sumario-linha total"><span>Total</span><span id="display-total"><?php echo format_money((float)$encomenda['total'] + (float)$encomenda['portes_envio']); ?></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($mensagens_enviadas)): ?>
            <div class="form-card">
                <div class="form-card-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Histórico de Comunicações
                    </h3>
                </div>
                <div class="form-card-body" style="padding-top: 10px;">
                    <div class="historico-mensagens" style="padding: 0;">
                        <?php foreach (array_reverse($mensagens_enviadas) as $msg): ?>
                        <div class="mensagem-item">
                            <div class="mensagem-header">
                                <span class="mensagem-tipo">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                    Email Enviado
                                </span>
                                <span class="mensagem-data"><?php echo date('d/m/Y H:i', strtotime($msg['data'])); ?></span>
                            </div>
                            <div class="mensagem-body">
                                <strong>Assunto:</strong> <?php echo htmlspecialchars($msg['assunto']); ?><br>
                                <?php echo $msg['mensagem']; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-coluna-secundaria">
            <div class="form-card">
                <?php if (!$is_encomenda_incompleta): ?>
                <div class="painel-acoes-tabs">
                    <button class="tab-link ativo" data-tab="tab-acoes">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                        Ações
                    </button>
                    <?php if (!$is_encomenda_incompleta): ?>
                    <button class="tab-link" data-tab="tab-comunicar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        Comunicar
                    </button>
                    <button class="tab-link" data-tab="tab-notas">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        Notas
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div id="tab-acoes" class="tab-content ativo">
                    <?php if (in_array($encomenda['estado'], ['cancelada', 'concluida'])): ?>
                        <p class="aviso-bloqueio">
                            Esta encomenda foi <?php echo $encomenda['estado'] === 'cancelada' ? 'cancelada' : 'concluída'; ?>. Não são permitidas mais ações.
                        </p>
                    <?php elseif ($is_encomenda_incompleta): ?>
                        <div class="incomplete-order-notice">
                            <strong>Encomenda a aguardar pagamento</strong>
                            <p>Esta encomenda foi criada no checkout e reservou stock temporariamente, mas o cliente ainda não concluiu o pagamento. Não altere o estado manualmente.</p>
                        </div>
                        <div class="zona-perigo">
                            <button id="btn-eliminar-incompleta" class="btn-admin-danger" style="width: 100%; margin-bottom: 10px;">Cancelar e Repor Stock</button>
                            <p>Repõe as unidades reservadas no stock e elimina esta encomenda incompleta.</p>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="select-estado">Alterar Estado da Encomenda</label>
                            <div class="select-wrapper">
                                <select id="select-estado" class="select-estilizado">
                                    <?php foreach ($estados_permitidos_para_esta_encomenda as $estado):
                                        $selected = ($estado === $encomenda['estado']) ? 'selected' : '';
                                        $nome_display = $map_nomes[$estado] ?? ucfirst($estado);
                                        echo "<option value='{$estado}' {$selected}>{$nome_display}</option>";
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div id="campos-contexto-tracking" class="form-group" style="display:none;">
                            <label for="codigo_tracking">Código de Tracking (Opcional):</label>
                            <input type="text" id="codigo_tracking" class="variacao-input" placeholder="Ex: PT123456789PT" value="<?php echo htmlspecialchars($encomenda['codigo_tracking'] ?? ''); ?>">
                        </div>
                        <div class="form-actions" style="border-top: none; padding-top: 0;">
                            <button id="btn-atualizar-estado" class="btn-admin-primary" style="width: 100%;">Guardar Alterações</button>
                        </div>
                        <?php if (in_array($encomenda['estado'], ['pendente', 'pago'])): ?>
                        <hr style="margin: 10px 0 25px 0;">
                        <div class="zona-perigo">
                            <?php if ($encomenda['estado'] === 'pendente'): ?>
                                <button id="btn-cancelar-encomenda" class="btn-admin-danger" data-tipo="nao_paga" style="width: 100%; margin-bottom: 10px;">Cancelar Encomenda</button>
                                <p>O pagamento ainda não foi confirmado.</p>
                            <?php elseif ($encomenda['estado'] === 'pago'): ?>
                                <button id="btn-cancelar-reembolsar" class="btn-admin-danger" style="width: 100%; margin-bottom: 10px;">Cancelar e Reembolsar</button>
                                <p>Ação irreversível via Stripe.</p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (!$is_encomenda_incompleta): ?>
                <div id="tab-comunicar" class="tab-content">
                    <div class="form-group">
                        <label for="email-assunto">Assunto do Email:</label>
                        <input type="text" id="email-assunto" placeholder="Assunto da sua mensagem">
                    </div>
                    <div class="form-group">
                        <label for="email-mensagem">Mensagem para o Cliente:</label>
                        <textarea id="email-mensagem" class="auto-resize-textarea" rows="8" placeholder="Escreva aqui a sua mensagem..."></textarea>
                    </div>
                    <button id="btn-enviar-email" class="btn-admin-primary" style="width: 100%;">Enviar Email</button>
                </div>

                <div id="tab-notas" class="tab-content">
                    <div class="form-group">
                        <label for="notas-internas">Notas (apenas visível para si)</label>
                        <textarea id="notas-internas" class="auto-resize-textarea" rows="8"><?php echo htmlspecialchars($encomenda['notas_internas'] ?? ''); ?></textarea>
                    </div>
                    <button id="btn-guardar-notas" class="btn-admin-secondary" style="width: 100%;">Guardar Notas</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<div id="modal-comunicacao" class="modal-confirmacao">
    <div class="modal-confirmacao-conteudo" style="max-width: 600px; text-align: left;">
        <h3 id="modal-titulo">Notificar Cliente</h3>
        <p id="modal-subtitulo">A alterar estado para: <strong id="modal-novo-estado-texto"></strong></p>

        <div class="form-group">
            <label for="modal-assunto">Assunto do Email:</label>
            <input type="text" id="modal-assunto" style="width: 100%;">
        </div>

        <div class="form-group">
            <label for="modal-mensagem">Mensagem a enviar (pode editar):</label>
            <textarea id="modal-mensagem" class="auto-resize-textarea" rows="6"></textarea>
        </div>

        <div class="modal-confirmacao-acoes">
            <button id="modal-btn-cancelar" class="button voltar-btn">Cancelar</button>
            <button id="modal-btn-confirmar" class="button add-btn">Confirmar e Enviar Email</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const encomendaId = <?php echo $encomenda_id; ?>;
    const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    const estadoOriginal = '<?php echo $encomenda['estado']; ?>';
    const encomenda = <?php echo json_encode($encomenda, JSON_HEX_QUOT | JSON_HEX_TAG); ?>;
    const returnUrl = <?php echo json_encode($return_url); ?>;

    const selectEstado = document.getElementById('select-estado');
    const btnAtualizar = document.getElementById('btn-atualizar-estado');
    const campoTracking = document.getElementById('campos-contexto-tracking');
    const modal = document.getElementById('modal-comunicacao');
    const modalAssunto = document.getElementById('modal-assunto');
    const modalMensagem = document.getElementById('modal-mensagem');
    const modalBtnConfirmar = document.getElementById('modal-btn-confirmar');
    const modalBtnCancelar = document.getElementById('modal-btn-cancelar');

    async function getTemplateContent(templateKey) {
        try {
            const safeKey = templateKey.replace(/ /g, '_');
            const response = await fetch(`ajax_get_template.php?key=${encodeURIComponent(safeKey)}`);
            if (!response.ok) return null;
            return await response.json();
        } catch (e) { return null; }
    }

    function toggleCamposContextuais() {
        if (!selectEstado) return;
        campoTracking.style.display = (selectEstado.value === 'enviada') ? 'block' : 'none';
    }

    async function abrirModalNotificacao(novoEstado) {
        const templateKey = novoEstado.replace(/ /g, '_');
        const template = await getTemplateContent(templateKey);

        if (!template) {
            mostrarPopup(`Template de email para "${novoEstado}" não encontrado. Use o separador "Comunicar" para envio manual.`, 'erro');
            return;
        }

        document.getElementById('modal-novo-estado-texto').textContent = selectEstado.options[selectEstado.selectedIndex].text;

        const portes = parseFloat(encomenda.portes_envio) || 0;
        const totalFinal = parseFloat(encomenda.total) + portes;

        const placeholders = {
            '{nome_cliente}':      encomenda.cliente_nome,
            '{id_encomenda}':      encomenda.id,
            '{metodo_pagamento}':  encomenda.metodo_pagamento,
            '{subtotal_produtos}': '€' + parseFloat(encomenda.total).toFixed(2).replace('.', ','),
            '{total_final}':       '€' + totalFinal.toFixed(2).replace('.', ','),
            '{portes_envio}':      '€' + portes.toFixed(2).replace('.', ','),
            '{codigo_tracking}':   (document.getElementById('codigo_tracking')?.value) || 'N/A',
        };

        let corpoProcessado = template.corpo;
        let assuntoProcessado = template.assunto;
        for (const key in placeholders) {
            const re = new RegExp(key.replace(/\{|\}/g, '\\$&'), 'g');
            corpoProcessado   = corpoProcessado.replace(re, placeholders[key]);
            assuntoProcessado = assuntoProcessado.replace(re, placeholders[key]);
        }

        modalAssunto.value  = assuntoProcessado;
        modalMensagem.value = corpoProcessado;

        modal.classList.add('ativo');
        autoResizeTextarea.call(modalMensagem);
    }

    function fecharModalNotificacao() { modal.classList.remove('ativo'); }

    if (selectEstado) {
        selectEstado.addEventListener('change', toggleCamposContextuais);
        toggleCamposContextuais();
    }

    btnAtualizar?.addEventListener('click', function() {
        const novoEstado = selectEstado.value;
        const tracking   = document.getElementById('codigo_tracking')?.value || '';

        const mudouEstado   = novoEstado !== estadoOriginal;
        const mudouTracking = campoTracking.style.display !== 'none' && tracking !== (encomenda.codigo_tracking || '');

        if (!mudouEstado && !mudouTracking) {
            mostrarPopup('Nenhuma alteração para guardar.', 'erro');
            return;
        }

        // Estados que requerem notificação com template de email
        const estadosComEmail = ['enviada', 'concluida', 'cancelada', 'pronta para levantamento'];
        if (mudouEstado && estadosComEmail.includes(novoEstado)) {
            abrirModalNotificacao(novoEstado);
        } else {
            mostrarModalConfirmacao(
                'Guardar Alterações',
                `Alterar estado para "${selectEstado.options[selectEstado.selectedIndex].text}"?`,
                () => fazerAcao('mudar_estado', { novo_estado: novoEstado, notificar_cliente: false, codigo_tracking: tracking }, btnAtualizar)
            );
        }
    });

    modalBtnConfirmar?.addEventListener('click', function() {
        const dados = {
            novo_estado:      selectEstado.value,
            notificar_cliente: true,
            assunto:          modalAssunto.value,
            mensagem:         modalMensagem.value,
            codigo_tracking:  document.getElementById('codigo_tracking')?.value || '',
        };
        fecharModalNotificacao();
        fazerAcao('mudar_estado', dados, btnAtualizar);
    });

    modalBtnCancelar?.addEventListener('click', fecharModalNotificacao);
    modal?.addEventListener('click', (e) => { if (e.target === modal) fecharModalNotificacao(); });

    document.getElementById('btn-eliminar-incompleta')?.addEventListener('click', function() {
        mostrarModalConfirmacao(
            'Cancelar reserva',
            'Cancelar esta encomenda que aguarda pagamento? O stock será reposto e a encomenda será eliminada.',
            () => fazerAcao('cancelar_incompleta', { return_to: returnUrl }, this)
        );
    });

    // Botão cancelar encomenda
    document.getElementById('btn-cancelar-encomenda')?.addEventListener('click', function() {
        const tipoCancelamento = this.dataset.tipo;
        const templateKey = tipoCancelamento === 'paga' ? 'cancelada' : 'cancelada_por_atraso';
        const mensagemAviso = tipoCancelamento === 'paga'
            ? 'O cliente já efetuou o pagamento. Confirma o cancelamento? Lembre-se de emitir reembolso no painel Stripe.'
            : 'Cancelar esta encomenda? O stock será reposto e o cliente será notificado.';

        mostrarModalConfirmacao('Cancelar Encomenda', mensagemAviso, async () => {
            const template = await getTemplateContent(templateKey);
            const dados = {
                novo_estado:       'cancelada',
                notificar_cliente: true,
                assunto:           template ? template.assunto.replace('{id_encomenda}', encomenda.id) : `Encomenda #${encomenda.id} Cancelada`,
                mensagem:          template ? template.corpo.replace('{nome_cliente}', encomenda.cliente_nome).replace('{id_encomenda}', encomenda.id) : `A sua encomenda foi cancelada.`,
            };
            fazerAcao('mudar_estado', dados, document.getElementById('btn-cancelar-encomenda'));
        });
    });

    document.getElementById('btn-cancelar-reembolsar')?.addEventListener('click', function() {
        mostrarModalConfirmacao(
            'Cancelar e Reembolsar',
            'Vai ser emitido um reembolso total ao cliente via Stripe. Esta ação é irreversível. Confirmar?',
            async () => {
                const btn = document.getElementById('btn-cancelar-reembolsar');
                const originalText = btn.textContent;
                btn.disabled = true;
                btn.textContent = 'A processar...';
                try {
                    const response = await fetch('ajax_reembolsar_stripe.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ encomenda_id: encomendaId, csrf_token: csrfToken })
                    });
                    const result = await response.json();
                    if (result.sucesso) {
                        mostrarPopup(result.mensagem, 'sucesso');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        throw new Error(result.mensagem || 'Erro desconhecido.');
                    }
                } catch (error) {
                    mostrarPopup(error.message, 'erro');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            }
        );
    });

    document.getElementById('btn-enviar-email')?.addEventListener('click', function() {
        const assunto  = document.getElementById('email-assunto').value.trim();
        const mensagem = document.getElementById('email-mensagem').value.trim();
        if (!assunto || !mensagem) {
            mostrarPopup('Por favor, preencha o assunto e a mensagem.', 'erro');
            return;
        }
        mostrarModalConfirmacao('Enviar Email', 'Enviar este email ao cliente?', () => {
            fazerAcao('enviar_email', { assunto, mensagem }, this);
        });
    });

    document.getElementById('btn-guardar-notas')?.addEventListener('click', function() {
        fazerAcao('guardar_notas', { notas: document.getElementById('notas-internas').value }, this);
    });

    async function fazerAcao(acao, dadosPayload, botao = null) {
        let originalText = '';
        if (botao) { originalText = botao.textContent; botao.disabled = true; botao.textContent = 'A processar...'; }
        try {
            const response = await fetch('ajax_gerir_encomenda.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ encomenda_id: encomendaId, csrf_token: csrfToken, acao, ...dadosPayload })
            });
            const result = await response.json();
            if (response.ok && result.sucesso) {
                mostrarPopup(result.mensagem, 'sucesso');
                setTimeout(() => {
                    if (result.redirect_url) {
                        window.location.href = result.redirect_url;
                    } else {
                        window.location.reload();
                    }
                }, 1500);
            } else {
                throw new Error(result.mensagem || 'Ocorreu um erro desconhecido.');
            }
        } catch (error) {
            mostrarPopup(error.message, 'erro');
            if (botao) { botao.disabled = false; botao.textContent = originalText; }
        }
    }

    const autoResizeTextarea = function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    };
    document.querySelectorAll('.auto-resize-textarea').forEach(t => {
        t.addEventListener('input', autoResizeTextarea, false);
        autoResizeTextarea.call(t);
    });

    document.querySelectorAll('.tab-link').forEach(link => {
        link.addEventListener('click', () => {
            const tabId = link.dataset.tab;
            document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('ativo'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('ativo'));
            link.classList.add('ativo');
            const activeTab = document.getElementById(tabId);
            activeTab.classList.add('ativo');
            const textarea = activeTab.querySelector('.auto-resize-textarea');
            if (textarea) autoResizeTextarea.call(textarea);
        });
    });
});
</script>

<?php include '../templates/footer.php'; ?>
