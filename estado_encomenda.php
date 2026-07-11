<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/formatters.php';
require_once __DIR__ . '/config/cliente_auth.php';

// --- DEVELOPER PREVIEW MODE (SEGURANÇA REFORÇADA) ---
// Deve estar no topo para o redirect funcionar antes de qualquer output
if (isset($_GET['dev_preview'])) {
    if (isset($_SESSION['admin_logado']) && $_SESSION['admin_role'] === 'desenvolvedor') {
        $encomenda_id = 9999;
        $token = 'dev_token';
        $mock_estado = $_GET['dev_preview'];
        $mock_entrega = $_GET['metodo_entrega'] ?? 'envio';

        $encomenda = [
            'id' => 9999,
            'token' => 'dev_token',
            'data_encomenda' => date('Y-m-d H:i:s'),
            'estado' => $mock_estado, // 'pendente', 'pago', 'enviada', 'concluida', 'cancelada'
            'cliente_nome' => 'Desenvolvedor Teste',
            'cliente_email' => 'dev@exemplo.com',
            'total' => 125.50,
            'portes_envio' => ($mock_entrega === 'recolha') ? 0 : 5.90,
            'metodo_entrega' => $mock_entrega,
            'cliente_morada' => ($mock_entrega === 'recolha') ? "Recolha Agendada na Loja Física\nRua Principal, 123\nLisboa" : "Rua do Desenvolvedor, 123\n4000-000 Porto\nPortugal",
            'metodo_pagamento' => 'Cartão',
            'codigo_tracking' => ($mock_estado === 'enviada') ? 'GLS123456789' : '',
            'mensagens_cliente' => json_encode([
                ['data' => date('Y-m-d H:i:s'), 'assunto' => 'Informação de Envio', 'mensagem' => 'A sua encomenda está a ser processada com prioridade.']
            ])
        ];
        $itens = [
            [
                'nome_produto' => 'Produto de Teste Premium',
                'quantidade' => 2,
                'preco_unitario' => 60.00,
                'selecoes_atributos' => json_encode(['Cor' => 'Preto', 'Tamanho' => 'XL']),
                'foto_exibicao' => '../assets/logo1.jpg'
            ]
        ];
    } else {
        // Se tentar aceder ao preview sem ser dev, redirecionamos para a home
        header("Location: /");
        exit;
    }
}

$titulo_pagina = 'Estado da Encomenda';
$descricao_pagina = 'Acompanha em tempo real o estado e o rastreio da tua encomenda TopTop.';
$noindex = true;
include 'templates/header.php';
require_once __DIR__ . '/config/stripe.php';

$is_first_visit = isset($_SERVER['HTTP_REFERER']) && (
    strpos($_SERVER['HTTP_REFERER'], 'checkout.php') !== false ||
    strpos($_SERVER['HTTP_REFERER'], 'sucesso.php') !== false
);
if ($is_first_visit) {
    echo "<script>document.addEventListener('DOMContentLoaded',function(){if(typeof mostrarPopup==='function')mostrarPopup('Encomenda submetida com sucesso!','sucesso');});</script>";
}

include 'config/database.php';
$encomenda_id = $encomenda_id ?? filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$token        = $token ?? htmlspecialchars($_GET['token'] ?? '', ENT_QUOTES, 'UTF-8');
$cliente_estado = is_cliente_logged_in() ? cliente_atual($conn) : null;


if (!$encomenda_id || (!$token && !$cliente_estado)) {
    echo "<main class='pagina-info'><div class='info-bloco'><p>Link de encomenda inválido.</p></div></main>";
    include 'templates/footer.php'; exit;
}

if (!isset($encomenda)) {
    if ($token) {
        $stmt = $conn->prepare("SELECT * FROM encomendas WHERE id = ? AND token = ?");
        $stmt->bind_param("is", $encomenda_id, $token);
    } else {
        $stmt = $conn->prepare("SELECT * FROM encomendas WHERE id = ? AND cliente_id = ?");
        $cliente_estado_id = (int)$cliente_estado['id'];
        $stmt->bind_param("ii", $encomenda_id, $cliente_estado_id);
    }
    $stmt->execute();
    $encomenda = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$encomenda) {
    echo "<main class='pagina-info'><div class='info-bloco'><p>Encomenda não encontrada. Verifique o link ou contacte-nos.</p></div></main>";
    include 'templates/footer.php'; exit;
}

// SINCRONIZAÇÃO FORÇADA COM O STRIPE
// Se o webhook falhar (ex: configuração na produção), o tracking sincroniza manualmente
if (!isset($_GET['dev_preview']) && in_array($encomenda['estado'], ['pendente', 'incompleta']) && !empty($encomenda['stripe_payment_intent_id'])) {
    $stripe_keys = getStripeKeys();
    if (!empty($stripe_keys['secret'])) {
        $pi_data = stripeRequest('GET', 'payment_intents/' . $encomenda['stripe_payment_intent_id'], [], $stripe_keys['secret']);
        if (!empty($pi_data['status'])) {
            if ($pi_data['status'] === 'succeeded') {
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

                $stmt_up = $conn->prepare("UPDATE encomendas SET estado = 'pago', metodo_pagamento = ? WHERE id = ? AND estado IN ('pendente', 'incompleta')");
                $stmt_up->bind_param("si", $metodo_real, $encomenda['id']);
                $stmt_up->execute();
                $foi_primeiro = $stmt_up->affected_rows > 0;
                $stmt_up->close();

                $encomenda['estado'] = 'pago';
                $encomenda['metodo_pagamento'] = $metodo_real;

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
                    } catch (Exception $e) {
                        error_log("estado_encomenda: falha no email ao cliente enc #{$encomenda['id']}: " . $e->getMessage());
                    }

                    $total_enc = (float)$encomenda['total'] + (($encomenda['metodo_entrega'] === 'levantamento') ? 0 : (float)$encomenda['portes_envio']);
                    enviarEmailNotificacaoLoja('nova_encomenda_loja', [
                        'id'               => $encomenda['id'],
                        'nome_cliente'     => $encomenda['cliente_nome'],
                        'email_cliente'    => $encomenda['cliente_email'],
                        'total_final'      => $total_enc,
                        'metodo_pagamento' => $metodo_real,
                    ]);
                }
            } elseif ($pi_data['status'] === 'canceled') {
                $stmt_can = $conn->prepare("UPDATE encomendas SET estado = 'cancelada' WHERE id = ?");
                $stmt_can->bind_param("i", $encomenda['id']);
                $stmt_can->execute();
                $stmt_can->close();

                $stmt_itens_stock = $conn->prepare("SELECT variacao_id, quantidade FROM encomenda_itens WHERE encomenda_id = ?");
                $stmt_itens_stock->bind_param("i", $encomenda['id']);
                $stmt_itens_stock->execute();
                $itens_stock = $stmt_itens_stock->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_itens_stock->close();

                $stmt_stock = $conn->prepare("UPDATE produto_variacoes SET quantidade = quantidade + ? WHERE id = ?");
                foreach ($itens_stock as $it) {
                    $stmt_stock->bind_param("ii", $it['quantidade'], $it['variacao_id']);
                    $stmt_stock->execute();
                }
                $stmt_stock->close();

                $encomenda['estado'] = 'cancelada';
            }
        }
    }
}

if (!isset($itens)) {
    $stmt_itens = $conn->prepare(
        "SELECT ei.*, COALESCE(ei.foto_snapshot, p.foto_principal) as foto_exibicao FROM encomenda_itens ei
         LEFT JOIN produtos p ON ei.produto_id = p.id
         WHERE ei.encomenda_id = ?"
    );
    $stmt_itens->bind_param("i", $encomenda_id);
    $stmt_itens->execute();
    $itens = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_itens->close();
}

$mensagens_cliente = json_decode($encomenda['mensagens_cliente'] ?? '[]', true);

// Timeline para o fluxo de envio (padrão agora)
$timeline_passos = [
    'Recebida'         => ['icon' => 'clip', 'estados' => ['pendente', 'a aguardar pagamento', 'pagamento na entrega', 'incompleta']],
    'Em Processamento' => ['icon' => 'package', 'estados' => ['pago', 'em processamento']],
    'Enviada'          => ['icon' => 'truck', 'estados' => ['enviada', 'pronta para levantamento']],
    'Concluída'        => ['icon' => 'check-circle', 'estados' => ['concluida']],
];

// Descrições contextuais por estado
$descricoes_estado = [
    'incompleta'               => 'A aguardar a conclusão do pagamento.',
    'pendente'                 => 'A confirmar o teu pagamento. Receberás um email assim que for processado.',
    'a aguardar pagamento'     => 'A aguardar a confirmação do teu pagamento.',
    'pago'                     => 'Pagamento confirmado! Estamos a preparar a tua encomenda.',
    'em processamento'         => 'A tua encomenda está a ser preparada com cuidado.',
    'enviada'                  => 'A tua encomenda foi entregue à GLS e está a caminho!',
    'pronta para levantamento' => 'A tua encomenda está pronta para levantamento.',
    'concluida'                => 'Encomenda entregue. Obrigado pela tua compra!',
    'cancelada'                => 'Esta encomenda foi cancelada.',
];

$timeline_final = [];
$indice_atual   = -1;
$i_step         = 0;

foreach ($timeline_passos as $nome => $info) {
    if (in_array($encomenda['estado'], $info['estados'])) {
        $indice_atual = $i_step;
    }
    $timeline_final[$nome] = $info['icon'];
    $i_step++;
}

$total_passos = count($timeline_final) - 1;
$progresso    = $total_passos > 0 ? min(100, ($indice_atual / $total_passos) * 100) : 0;
if ($indice_atual >= $total_passos) $progresso = 100;
?>


<main class="pagina-info">
<div class="acomp-wrap">

    <div class="acomp-header">
        <h2>Encomenda #<?php echo $encomenda_id; ?></h2>
        <p>Realizada em <?php echo date('d \d\e F \d\e Y, \à\s H\hi', strtotime($encomenda['data_encomenda'])); ?></p>
    </div>

    <!-- ── TIMELINE ── -->
    <div class="acomp-card">
        <h3>Estado da Encomenda</h3>
        <div class="timeline-wrap">
            <div class="timeline-track" style="--timeline-offset: calc(100% / <?php echo max(1, count($timeline_final) * 2); ?>);">
                <?php if ($encomenda['estado'] === 'cancelada'): ?>
                    <div class="tl-step cancelada" style="flex:1;">
                        <div class="tl-circle">✕</div>
                        <div class="tl-label">Cancelada</div>
                    </div>
                <?php else: ?>
                    <?php $k = 0; foreach ($timeline_final as $nome => $icon_name):
                        $done   = $indice_atual > $k;
                        $active = $indice_atual === $k;
                        $cls    = $done ? 'done' : ($active ? 'active' : '');
                        
                        // Seleção do ícone SVG
                        $svg_icon = match($icon_name) {
                            'clip'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>',
                            'package'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
                            'truck'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
                            'check-circle' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
                            default        => ''
                        };
                    ?>
                    <div class="tl-step <?php echo $cls; ?>">
                        <div class="tl-circle">
                            <?php if ($done): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php else: ?>
                                <?php echo $svg_icon; ?>
                            <?php endif; ?>
                        </div>
                        <div class="tl-label"><?php echo $nome; ?></div>
                    </div>
                    <?php $k++; endforeach; ?>
                    <div class="tl-progress" id="tl-progress"></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Banner contextual -->
        <?php
        $estado = $encomenda['estado'];
        $banner_class = '';
        $svg_banner_icon = '';
        
        if (in_array($estado, ['pago', 'em processamento'])) { 
            $banner_class = 'verde'; 
            $svg_banner_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
        } elseif ($estado === 'enviada') { 
            $banner_class = 'laranja'; 
            $svg_banner_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>';
        } elseif ($estado === 'concluida') { 
            $banner_class = 'verde'; 
            $svg_banner_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
        } elseif ($estado === 'cancelada') { 
            $banner_class = 'vermelho'; 
            $svg_banner_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
        } elseif ($estado === 'pendente') { 
            $svg_banner_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'; 
        }
        
        $descricao = $descricoes_estado[$estado] ?? '';
        ?>
        <?php if ($descricao): ?>
        <div class="estado-banner <?php echo $banner_class; ?>">
            <span class="eb-icon"><?php echo $svg_banner_icon; ?></span>
            <div>
                <strong><?php echo htmlspecialchars($descricao); ?></strong>
                <?php if ($estado === 'enviada' && empty($encomenda['codigo_tracking'])): ?>
                    <span style="color:#888;font-size:.85em;">O código de tracking será adicionado em breve.</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tracking code -->
        <?php if ($estado === 'enviada' && !empty($encomenda['codigo_tracking'])): ?>
        <div class="tracking-box">
            <div>
                <strong>Código de Tracking</strong>
                <span class="tracking-code"><?php echo htmlspecialchars($encomenda['codigo_tracking']); ?></span>
            </div>
            <a href="https://gls-group.eu/PT/pt/seguimento-de-encomendas?match=<?php echo urlencode($encomenda['codigo_tracking']); ?>" target="_blank" rel="noopener">Rastrear na GLS →</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── RESUMO DA ENCOMENDA ── -->
    <div class="acomp-card">
        <h3>Resumo da Encomenda</h3>
        <?php foreach ($itens as $item):
            $selecoes = json_decode($item['selecoes_atributos'], true);
            $attrs    = !empty($selecoes) ? implode(' · ', array_map('htmlspecialchars', array_values($selecoes))) : '';
        ?>
        <div class="item-linha">
            <img src="/public/images/<?php echo htmlspecialchars($item['foto_exibicao'] ?? 'default.jpg'); ?>" alt="<?php echo htmlspecialchars($item['nome_produto']); ?>">
            <div class="item-linha-info">
                <strong><?php echo htmlspecialchars($item['nome_produto']); ?></strong>
                <small><?php echo $attrs ? $attrs . ' · ' : ''; ?>Qtd: <?php echo $item['quantidade']; ?></small>
            </div>
            <span class="item-linha-preco"><?php echo format_money($item['preco_unitario'] * $item['quantidade']); ?></span>
        </div>
        <?php endforeach; ?>

        <div class="sumario-totais">
            <div class="total-linha"><span>Subtotal</span><span><?php echo format_money($encomenda['total']); ?></span></div>
            <div class="total-linha"><span>Portes</span><span><?php echo format_money((float)$encomenda['portes_envio']); ?></span></div>
            <div class="total-linha final"><span>Total</span><span><?php echo format_money((float)$encomenda['total'] + (float)$encomenda['portes_envio']); ?></span></div>
        </div>
    </div>

    <!-- ── DETALHES DE ENTREGA ── -->
    <div class="acomp-card">
        <h3>Entrega e Pagamento</h3>
        <div style="display:flex; flex-direction:column; gap:14px;">
            <?php if (!empty($encomenda['cliente_morada'])): ?>
            <div class="entrega-info">
                <?php if ($encomenda['metodo_entrega'] === 'recolha'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#888" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <div>
                        <strong>Recolha na Loja</strong>
                        <span style="color:#555; display:block; margin-top:2px;">O cliente virá levantar à loja física.</span>
                    </div>
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#888" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    <div>
                        <strong>Envio por Transportadora</strong>
                        <?php echo nl2br(htmlspecialchars($encomenda['cliente_morada'])); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php
            $mp = $encomenda['metodo_pagamento'] ?? '';
            $mp_display = match(true) {
                $mp === 'Cartão'  => '💳 Cartão Bancário',
                $mp === 'MB WAY'  => '📱 MB WAY',
                $mp === 'Stripe'  => '🔒 Stripe',
                !empty($mp)       => $mp,
                default           => ''
            };
            ?>
            <?php if ($mp_display): ?>
            <div class="entrega-info">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#888" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <div>
                    <strong>Método de Pagamento</strong>
                    <?php echo htmlspecialchars($mp_display); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── MENSAGENS DA LOJA ── -->
    <?php if (!empty($mensagens_cliente)): ?>
    <div class="acomp-card">
        <h3>Mensagens da Loja</h3>
        <?php foreach (array_reverse($mensagens_cliente) as $msg): ?>
        <div class="msg-item">
            <div class="msg-meta">
                <span>📧 Email</span>
                <span><?php echo date('d/m/Y H:i', strtotime($msg['data'])); ?></span>
            </div>
            <div class="msg-assunto"><?php echo htmlspecialchars($msg['assunto']); ?></div>
            <div class="msg-corpo"><?php echo $msg['mensagem']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="text-align:center; margin-top:20px;">
        <a href="/produtos.php" class="btn-voltar-compra">Continuar a comprar</a>
    </div>

</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animar a barra de progresso da timeline
    const bar = document.getElementById('tl-progress');
    if (bar) {
        const pct = <?php echo $progresso; ?>;
        const steps = <?php echo count($timeline_final); ?>;
        const offset = (100 / (steps * 2));
        const available = 100 - (offset * 2);
        setTimeout(() => { bar.style.width = (available * pct / 100) + '%'; }, 100);
    }
});
</script>

<?php include 'templates/footer.php'; ?>
