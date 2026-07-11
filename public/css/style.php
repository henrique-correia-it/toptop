<?php
header("Content-type: text/css; charset: UTF-8");
header("Cache-Control: public, max-age=31536000, immutable");

// Versão mantida por compatibilidade (cache-busting feito via ?v= no <link> do header).
$v = isset($_GET['v']) ? preg_replace('/[^0-9]/', '', $_GET['v']) : '1';

/* ==========================================================================
   FICHEIRO MESTRE - TOPTOP (BUNDLE)
   Os parciais são concatenados num ÚNICO ficheiro: 1 pedido, sem a cascata
   de @import (que era render-blocking). A ordem é preservada para a cascata
   correcta — _button-system.css fica sempre em último.
   ========================================================================== */

$partials = [
    // 1. Base
    'base/_variables.css',
    'base/_reset.css',
    'base/_typography.css',
    'base/_utilities.css',
    // 2. Layout
    'layout/_main.css',
    'layout/_grid.css',
    'layout/_header.css',
    'layout/_footer.css',
    'layout/_forms.css',
    'layout/_admin-layout.css',
    // 3. Components
    'components/_buttons.css',
    'components/_close-button.css',
    'components/_product-card.css',
    'components/_modal.css',
    'components/_popup.css',
    'components/_admin-table.css',
    'components/_side-cart.css',
    'components/_back-to-top.css',
    'components/_pagination.css',
    'components/_form-components.css',
    'components/_admin-ui.css',
    'components/_admin-buttons.css',
    'components/_quick-edit.css',
    'components/_context-menu.css',
    'components/_editable-content.css',
    'components/_switch.css',
    'components/_cart-ui.css',
    'components/_unified-cart-item.css',
    // 4. Pages
    'pages/_home.css',
    'pages/_products.css',
    'pages/_contact.css',
    'pages/_cart.css',
    'pages/_gerir-atributos.css',
    'pages/_dashboard.css',
    'pages/_info-page.css',
    'pages/_product-page.css',
    'pages/_encomendas.css',
    'pages/_gestor-emails.css',
    'pages/_form-produto.css',
    'pages/_mensagens.css',
    'pages/_uso-armazenamento.css',
    'pages/_guia-tamanhos.css',
    'pages/_editar-admin.css',
    'pages/_login_animation.css',
    'pages/_login-logs.css',
    'pages/_portes.css',
    'pages/_paleta-cores.css',
    'pages/_admin-produtos.css',
    'pages/_galeria-imagens.css',
    'pages/_gerir_categorias.css',
    'pages/_ver-logs.css',
    'pages/_listar-admins.css',
    'pages/_estado-encomenda.css',
    'pages/_sucesso.css',
    'pages/_404.css',
    'pages/_checkout.css',
    'pages/_editar-encomenda.css',
    'pages/_cliente.css',
    // 5. Sistema final de botoes: normaliza estilos antigos das paginas (em último)
    'components/_button-system.css',
];

$baseDir = __DIR__ . '/';
foreach ($partials as $rel) {
    $path = $baseDir . $rel;
    if (is_file($path)) {
        echo "\n/* === " . $rel . " === */\n";
        readfile($path);
    }
}
?>

/*
 * Sistema de contraste para superfícies elevadas.
 * As caixas permanecem claras e legíveis, independentemente
 * da cor global escolhida para o fundo da página.
 */
.produto,
.cart-summary-card,
.stat-card,
.nav-card,
.info-bloco,
.contacto-grid,
.cliente-card,
.form-card,
.palette-card,
.palette-preview-card,
.country-card,
.tracking-box,
.entrega-info,
.msg-item {
    border-color: var(--cor-borda-cartao);
    color: var(--cor-texto);
}

/* CSS GERAL E UTILITÁRIOS EXTRA */

/* TEMA TOOLTIP */
.tippy-box[data-theme~='toptop-professional'] {
    background-color: #1C1C1C;
    color: #FFFFFF;
    font-family: 'Inter', sans-serif;
    font-size: 0.9rem;
    font-weight: 500;
    line-height: 1.5;
    border-radius: 6px;
    padding: 10px 14px;
    max-width: 300px;
    text-align: center;
}

.tippy-box[data-theme~='toptop-professional'] > .tippy-arrow {
    color: #1C1C1C;
}

.tippy-box[data-theme~='toptop-professional'] .tippy-content {
    white-space: normal;
    word-wrap: break-word;
}
