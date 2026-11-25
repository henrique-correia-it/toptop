<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

require_developer('/admin/admin.php');

$titulo_pagina = 'Developer Previews';
$previewsCssVer = @filemtime(__DIR__ . '/../public/css/pages/_previews.css') ?: time();
$head_extra = '<link rel="stylesheet" href="/public/css/pages/_previews.css?v=' . $previewsCssVer . '">';
include '../templates/header.php';

$renderPreviewCard = static function (array $section): string {
    $count = count($section['items']);
    $countLabel = $count . ($count === 1 ? ' estado' : ' estados');
    $headerHtml = '<div class="previews-card-title">'
        . $section['icon']
        . '<h3>' . ui_e($section['title']) . '</h3>'
        . '</div>'
        . ui_badge($countLabel, 'neutral');

    $arrowSvg = '<svg class="dev-preview-row-arrow" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<line x1="7" y1="17" x2="17" y2="7"></line>'
        . '<polyline points="7 7 17 7 17 17"></polyline>'
        . '</svg>';

    $bodyHtml = '<div class="previews-list">';
    foreach ($section['items'] as $item) {
        $bodyHtml .= '<a href="' . ui_e($item['href']) . '" target="_blank" rel="noopener" class="dev-preview-row">'
            . '<div class="dev-preview-row-copy">'
            . '<strong>' . ui_e($item['label']) . '</strong>'
            . '<span>' . ui_e($item['desc']) . '</span>'
            . '</div>'
            . '<div class="dev-preview-row-meta">'
            . ui_badge($item['badge'], $item['tone'])
            . $arrowSvg
            . '</div>'
            . '</a>';
    }
    $bodyHtml .= '</div>';

    return ui_card($bodyHtml, [
        'variant'     => 'form',
        'class'       => 'previews-card',
        'header_html' => $headerHtml,
    ]);
};

$leftSections = [
    [
        'title' => 'Estados de Rastreio',
        'icon'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
        'items' => [
            [
                'label' => 'Pendente',
                'desc'  => 'A confirmar pagamento',
                'href'  => '../estado_encomenda.php?dev_preview=pendente',
                'badge' => 'Pendente',
                'tone'  => 'warning',
            ],
            [
                'label' => 'Pago',
                'desc'  => 'Pagamento confirmado',
                'href'  => '../estado_encomenda.php?dev_preview=pago',
                'badge' => 'Pago',
                'tone'  => 'success',
            ],
            [
                'label' => 'Enviada (Tracking)',
                'desc'  => 'Com código GLS ativo',
                'href'  => '../estado_encomenda.php?dev_preview=enviada',
                'badge' => 'Envio',
                'tone'  => 'info',
            ],
            [
                'label' => 'Pronta para Recolha',
                'desc'  => 'Levantamento em loja',
                'href'  => '../estado_encomenda.php?dev_preview=pronta+para+levantamento&metodo_entrega=recolha',
                'badge' => 'Recolha',
                'tone'  => 'info',
            ],
            [
                'label' => 'Concluída',
                'desc'  => 'Encomenda entregue',
                'href'  => '../estado_encomenda.php?dev_preview=concluida',
                'badge' => 'Concluída',
                'tone'  => 'success',
            ],
            [
                'label' => 'Cancelada',
                'desc'  => 'Encomenda cancelada',
                'href'  => '../estado_encomenda.php?dev_preview=cancelada',
                'badge' => 'Cancelada',
                'tone'  => 'danger',
            ],
        ],
    ],
];

$rightSections = [
    [
        'title' => 'Checkout e Pagamento',
        'icon'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>',
        'items' => [
            [
                'label' => 'Pagamento Aprovado',
                'desc'  => 'Página de confirmação',
                'href'  => '../sucesso.php?dev_preview=sucesso',
                'badge' => 'Sucesso',
                'tone'  => 'success',
            ],
            [
                'label' => 'Pagamento Falhado',
                'desc'  => 'Erro no pagamento',
                'href'  => '../sucesso.php?dev_preview=falhou',
                'badge' => 'Erro',
                'tone'  => 'danger',
            ],
        ],
    ],
    [
        'title' => 'Autenticação',
        'icon'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M17 11l2 2 4-4"/></svg>',
        'items' => [
            [
                'label' => 'Login Normal',
                'desc'  => 'Formulário padrão',
                'href'  => '/entrar?dev_preview=normal',
                'badge' => 'Padrão',
                'tone'  => 'neutral',
            ],
            [
                'label' => 'Credenciais Inválidas',
                'desc'  => 'Erro e tentativas restantes',
                'href'  => '/entrar?dev_preview=erro',
                'badge' => 'Aviso',
                'tone'  => 'warning',
            ],
            [
                'label' => 'Conta Bloqueada',
                'desc'  => 'Bloqueio brute force',
                'href'  => '/entrar?dev_preview=bloqueada',
                'badge' => 'Bloqueio',
                'tone'  => 'danger',
            ],
        ],
    ],
    [
        'title' => 'Páginas de Erro',
        'icon'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'items' => [
            [
                'label' => 'Página 404',
                'desc'  => 'Recurso não encontrado',
                'href'  => '../404.php',
                'badge' => '404',
                'tone'  => 'danger',
            ],
        ],
    ],
];
?>

<main class="dashboard-container animate-entry">
    <div class="previews-page">
        <?php echo ui_page_header('Developer Previews', [
            'layout'     => 'admin',
            'back_url'   => '/dev',
            'back_title' => 'Voltar ao Painel Dev',
        ]); ?>

        <div class="previews-columns">
            <div class="previews-col">
                <?php foreach ($leftSections as $section) {
                    echo $renderPreviewCard($section);
                } ?>
            </div>
            <div class="previews-col">
                <?php foreach ($rightSections as $section) {
                    echo $renderPreviewCard($section);
                } ?>
            </div>
        </div>
    </div>
</main>

<?php include '../templates/footer.php'; ?>
