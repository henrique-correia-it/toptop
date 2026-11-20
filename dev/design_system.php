<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';

// Segurança: Apenas DEVs podem aceder ao Design System Showcase
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || ($_SESSION['admin_role'] ?? '') !== 'desenvolvedor') {
    header('Location: /admin/admin.php');
    exit;
}

$titulo_pagina = 'Design System — TopTop';
include '../templates/header.php';

$tokensCores = [
    ['--cor-fundo-pagina', 'Canvas (Dinâmico)'],
    ['--cor-superficie-cartao', 'Superfície Cartão'],
    ['--cor-texto', 'Texto / Ink'],
    ['--cor-preto-suave', 'Primário Escuro'],
    ['--cor-accent', 'Accent Marca'],
    ['--cor-accent-soft', 'Accent Suave'],
    ['--cor-slate-50', 'Slate 50'],
    ['--cor-slate-100', 'Slate 100'],
    ['--cor-slate-200', 'Slate 200'],
    ['--cor-slate-500', 'Slate 500'],
    ['--cor-sucesso', 'Sucesso'],
    ['--cor-perigo', 'Perigo'],
    ['--cor-info', 'Info'],
];
?>

<main class="dashboard-container" style="max-width:1160px; margin:0 auto; padding:var(--space-6) var(--space-4) var(--space-12);">
    <?php
    echo ui_page_header('TopTop Design System', [
        'kicker'     => 'DESIGN.md · Biblioteca Mínima (9 Primitivos)',
        'subtitle'   => 'Referência viva de tokens, cores, tipografia e componentes reutilizáveis (includes/ui/ui.php).',
        'back_url'   => '/dev',
        'back_title' => 'Voltar ao Painel Dev',
        'actions'    => ui_badge('v1.0 · Agentic Ready', 'accent'),
    ]);
    ?>

    <div style="display:grid; gap:var(--space-6);">
        <?php
        // 1. Cores
        ob_start();
        echo '<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:var(--space-3);">';
        foreach ($tokensCores as [$var, $label]) {
            echo '<div style="border:1px solid var(--cor-slate-200); border-radius:var(--radius-md); overflow:hidden; background:var(--cor-branco);">';
            echo '<div style="height:56px; background:var(' . ui_e($var) . '); border-bottom:1px solid var(--cor-slate-200);"></div>';
            echo '<div style="padding:var(--space-2) var(--space-3);">';
            echo '<div style="font-size:var(--text-xs); font-weight:600; color:var(--cor-texto);">' . ui_e($label) . '</div>';
            echo '<code style="font-size:11px; color:var(--cor-slate-500);">' . ui_e($var) . '</code>';
            echo '</div></div>';
        }
        echo '</div>';
        echo ui_card(ob_get_clean(), ['title' => '1. Tokens de Cor (base/_variables.css)', 'subtitle' => 'Nunca usar hex hardcoded — todas as superfícies herdam daqui.']);

        // 2. Botões & Icon Buttons
        ob_start();
        echo '<div style="display:flex; flex-wrap:wrap; gap:var(--space-3); align-items:center; margin-bottom:var(--space-5);">';
        echo ui_button('Padrão (Default)');
        echo ui_button('Primário', ['variant' => 'primary']);
        echo ui_button('Adicionar', ['plus' => true]);
        echo ui_button('Perigo', ['variant' => 'danger']);
        echo ui_button('Ativo', ['variant' => 'active']);
        echo ui_button('Ghost', ['variant' => 'ghost']);
        echo ui_button('Pequeno', ['size' => 'sm']);
        echo ui_button('Desativado', ['disabled' => true]);
        echo '</div>';
        echo '<div style="display:flex; flex-wrap:wrap; gap:var(--space-2); align-items:center;">';
        foreach (['view', 'edit', 'duplicate', 'archive', 'reply', 'check', 'cancel', 'delete'] as $act) {
            echo ui_icon_button($act, ['title' => ucfirst($act)]);
        }
        echo '</div>';
        echo ui_card(ob_get_clean(), ['title' => '2. Botões & Botões de Ícone', 'subtitle' => 'ui_button() e ui_icon_button()']);

        // 3. Badges & Feedback
        ob_start();
        echo '<div style="display:flex; flex-wrap:wrap; gap:var(--space-2); margin-bottom:var(--space-5);">';
        echo ui_badge('Neutro', 'neutral');
        echo ui_badge('Pago / Ativo', 'success');
        echo ui_badge('A aguardar', 'warning');
        echo ui_badge('Cancelada / Esgotado', 'danger');
        echo ui_badge('Em processamento', 'info');
        echo ui_badge('Destaque Marca', 'accent');
        echo '</div>';
        echo ui_alert('Informação útil apresentada num banner inline.', 'info');
        echo ui_alert('Operação concluída com sucesso!', 'success');
        echo ui_alert('Atenção: verifica os dados antes de continuar.', 'warning');
        echo ui_alert('Ocorreu um erro ao processar o pedido.', 'danger');
        echo ui_empty_state('Exemplo de estado vazio padronizado.', ['cta_label' => 'Ação Sugerida', 'cta_href' => '#']);
        echo ui_card(ob_get_clean(), ['title' => '3. Badges, Alertas & Estado Vazio', 'subtitle' => 'ui_badge(), ui_alert(), ui_empty_state()']);

        // 4. Formulários & Switch
        ob_start();
        echo '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:var(--space-4);">';
        echo ui_field('demo_nome', 'Nome do Produto', ['placeholder' => 'Ex.: Camisa Linho Bege', 'hint' => 'Texto de ajuda discreto']);
        echo ui_field('demo_email', 'Email (com erro)', ['type' => 'email', 'value' => 'invalido@', 'error' => 'Insira um endereço de email válido.']);
        echo ui_field('demo_cat', 'Categoria', ['type' => 'select', 'options' => ['vestidos' => 'Vestidos', 'camisas' => 'Camisas', 'calcas' => 'Calças']]);
        echo '</div>';
        echo ui_switch('demo_visivel', 'Produto visível na loja', true);
        echo ui_card(ob_get_clean(), ['title' => '4. Campos de Formulário & Switch', 'subtitle' => 'ui_field() e ui_switch() com foco de teclado :focus-visible']);

        // 5. Tabela & Modal
        ob_start();
        echo '<div class="ui-table-wrap"><table class="ui-table"><thead><tr><th>Encomenda</th><th>Cliente</th><th>Estado</th><th>Total</th><th>Ações</th></tr></thead><tbody>';
        echo '<tr><td>#1042</td><td>Maria Silva</td><td>' . ui_badge('Pago', 'success') . '</td><td>€49,90</td><td style="display:flex; gap:6px;">' . ui_icon_button('view', ['title' => 'Ver']) . ui_icon_button('edit', ['title' => 'Editar']) . '</td></tr>';
        echo '<tr><td>#1041</td><td>João Santos</td><td>' . ui_badge('Enviada', 'info') . '</td><td>€84,50</td><td style="display:flex; gap:6px;">' . ui_icon_button('view', ['title' => 'Ver']) . ui_icon_button('delete', ['title' => 'Apagar']) . '</td></tr>';
        echo '</tbody></table></div>';
        echo '<div style="margin-top:var(--space-4);">' . ui_button('Abrir Modal de Exemplo', ['attrs' => ['onclick' => "document.getElementById('ds-demo-modal').style.display='flex'"]]) . '</div>';
        echo ui_card(ob_get_clean(), ['title' => '5. Tabela Unificada & Modal', 'subtitle' => '.ui-table-wrap / .ui-table e ui_modal()']);
        ?>
    </div>
</main>

<?php
echo ui_modal(
    'ds-demo-modal',
    'Modal Unificado (ui_modal)',
    '<p style="margin:0; text-align:left;">Este modal usa a estrutura canónica do Design System e fecha automaticamente com Esc ou clique fora.</p>',
    [
        'footer' => ui_button('Cancelar', ['class' => 'qe-btn-cancel']) . ui_button('Confirmar', ['variant' => 'primary', 'attrs' => ['onclick' => "document.getElementById('ds-demo-modal').style.display='none'"]]),
    ]
);
include '../templates/footer.php';
?>
