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
        'kicker'     => 'DESIGN.md · Biblioteca Completa (16 Primitivos / 31 Funções)',
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
        echo ui_field('demo_cat', 'Categoria (via ui_field)', ['type' => 'select', 'options' => ['vestidos' => 'Vestidos', 'camisas' => 'Camisas', 'calcas' => 'Calças']]);
        echo '<div class="ui-field"><label class="ui-field__label" for="demo_sort">Drop List Direta (ui_select)</label>'
            . ui_select('demo_sort', ['id_desc' => 'Mais recente', 'preco_asc' => 'Preço (↑)', 'preco_desc' => 'Preço (↓)'], 'id_desc', ['id' => 'demo_sort'])
            . '</div>';
        echo '</div>';
        echo '<div style="display:flex; flex-wrap:wrap; gap:var(--space-4); align-items:center;">'
            . ui_switch('demo_visivel', 'Produto visível na loja', true)
            . ui_switch('demo_compact', 'Filtro compacto (toggle-visiveis)', true, ['variant' => 'compact'])
            . '</div>';
        echo ui_card(ob_get_clean(), ['title' => '4. Campos de Formulário, Drop Lists (ui_select) & Switch', 'subtitle' => 'ui_field(), ui_input(), ui_textarea(), ui_select() e ui_switch() com foco de teclado :focus-visible']);

        // 5. Tabela, Paginação & Modal
        ob_start();
        echo ui_table(
            ['Encomenda', 'Cliente', 'Estado', 'Total', 'Ações'],
            [
                ['cells' => ['#1042', 'Maria Silva', ui_badge('Pago', 'success', ['dot' => true]), '€49,90', '<div style="display:flex; gap:6px;">' . ui_icon_button('view', ['title' => 'Ver']) . ui_icon_button('edit', ['title' => 'Editar']) . '</div>']],
                ['cells' => ['#1041', 'João Santos', ui_badge('Enviada', 'info', ['dot' => true]), '€84,50', '<div style="display:flex; gap:6px;">' . ui_icon_button('view', ['title' => 'Ver']) . ui_icon_button('delete', ['title' => 'Apagar']) . '</div>']],
            ],
            ['wrap_class' => 'ui-table-wrap', 'table_class' => 'ui-table']
        );
        echo ui_pagination(2, 5, fn($p) => '#page-' . $p);
        echo '<div style="margin-top:var(--space-4);">' . ui_button('Abrir Modal de Exemplo', ['attrs' => ['onclick' => "document.getElementById('ds-demo-modal').style.display='flex'"]]) . '</div>';
        echo ui_card(ob_get_clean(), ['title' => '5. Tabela Unificada, Paginação & Modal', 'subtitle' => 'ui_table(), ui_pagination(), ui_selection_bar(), ui_bulk_bar() e ui_modal()']);

        // 6. Tabs, Accordion, Progress Bar, Qty Stepper, Checkbox, Search, Spinner & KV List
        ob_start();
        echo '<div style="display:grid; gap:var(--space-5);">';
        echo '<div>' . ui_tabs([
            ['key' => 'todos', 'label' => 'Todos', 'badge' => '24'],
            ['key' => 'ativos', 'label' => 'Ativos', 'badge' => '18', 'badge_tone' => 'success'],
            ['key' => 'ocultos', 'label' => 'Ocultos', 'badge' => '6', 'badge_tone' => 'neutral'],
        ], 'todos') . '</div>';
        echo '<div style="display:flex; flex-wrap:wrap; gap:var(--space-4); align-items:center;">'
            . ui_search_input('demo_q', 'Pesquisar na tabela...')
            . ui_checkbox('demo_chk', 'Definir como principal', true)
            . ui_qty_stepper(2, ['min' => 1, 'max' => 10])
            . ui_spinner('A carregar')
            . ui_close_button(['title' => 'Fechar'])
            . '</div>';
        echo '<div style="display:grid; gap:var(--space-2);">'
            . ui_progress_bar(72, 'success')
            . ui_progress_bar(45, 'warning')
            . ui_progress_bar(90, 'danger')
            . '</div>';
        echo ui_accordion('Como funcionam as trocas e devoluções?', '<p style="margin:0;">Tens 14 dias após a receção da encomenda para efetuar troca ou devolução na loja ou por envio.</p>');
        echo ui_kv_list([
            ['label' => 'Morada da Loja', 'value' => 'Edifício Chafariz, Rua dos Fontenários, 4535-221 Lourosa'],
            ['label' => 'Método de Pagamento', 'value' => 'Cartão / MB WAY / Klarna (Stripe)'],
        ]);
        echo '</div>';
        echo ui_card(ob_get_clean(), ['title' => '6. Tabs, Accordion, Progresso, Quantidade, Pesquisa, Spinner & KV List', 'subtitle' => 'ui_tabs(), ui_accordion(), ui_progress_bar(), ui_qty_stepper(), ui_checkbox(), ui_search_input(), ui_spinner(), ui_kv_list()']);

        // 7. Dashboard Cards, Toolbar, Product Card, Customer Orders Table & Bulk/Context Demos
        ob_start();
        echo '<div style="display:grid; gap:var(--space-5);">';
        echo ui_toolbar(
            ui_button('Adicionar Item', ['plus' => true]),
            ui_search_input('demo_toolbar_q', 'Filtrar...') . ui_button('Selecionar', ['variant' => 'default'])
        );
        echo '<div class="stats-grid" style="margin:0;">'
            . ui_stat_card('128', 'Produtos Ativos', ['href' => '#'])
            . ui_stat_card('14', 'Encomendas Hoje', ['href' => '#'])
            . '</div>';
        echo '<div class="dashboard-grid">'
            . ui_nav_card('Cartão de Navegação', 'Exemplo de ui_nav_card() usado nos painéis Admin e Dev.', '#')
            . '</div>';
        echo '<div style="max-width:260px;">'
            . ui_product_card(['id' => 1, 'nome' => 'Camisa Linho Exemplo', 'preco' => 39.90, 'preco_promocional' => 29.90, 'stock_total' => 5, 'foto_principal' => 'default.jpg'], ['slug' => 'camisa-linho-exemplo'])
            . '</div>';
        echo ui_customer_orders_table([
            ['id' => 1042, 'data_encomenda' => date('Y-m-d'), 'metodo_entrega' => 'Envio', 'estado' => 'pago', 'total' => 45.00, 'portes_envio' => 4.90, 'token' => 'demo'],
        ]);
        echo '<div style="display:flex; gap:var(--space-2); flex-wrap:wrap;">'
            . ui_button('Abrir Quick Edit Modal', ['attrs' => ['onclick' => "document.getElementById('ds-qe-demo').style.display='flex'"]])
            . '</div>';
        echo ui_selection_bar();
        echo '</div>';
        echo ui_card(ob_get_clean(), ['title' => '7. Toolbar, Dashboard Cards, Cartão Produto, Encomendas Cliente & Seleção', 'subtitle' => 'ui_toolbar(), ui_stat_card(), ui_nav_card(), ui_product_card(), ui_customer_orders_table(), ui_selection_bar(), ui_bulk_bar(), ui_quick_edit_modal(), ui_context_menu()']);
        ?>
    </div>
</main>

<?php
echo ui_bulk_bar('ds-demo-bulk-form', '#', ['action_label' => 'Apagar Selecionados']);
echo ui_quick_edit_modal('ds-qe-demo', 'Quick Edit Demo (ui_quick_edit_modal)');
echo ui_context_menu([
    ['href' => '#', 'label' => 'Editar'],
    'separator',
    ['href' => '#', 'class' => 'danger', 'label' => 'Apagar'],
], 'ds-ctx-demo');
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
