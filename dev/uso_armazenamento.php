<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/formatters.php';
require_once __DIR__ . '/includes/storage_helper.php';

require_developer('/admin');

$titulo_pagina = 'Uso de Armazenamento';
include '../templates/header.php';

// Cálculos centralizados (fonte única de verdade partilhada com /dev e /dev/galeria_imagens.php)
$storage          = dev_calcular_armazenamento();
$total_size_bytes = (int)$storage['total_bytes'];
$size_images      = (int)$storage['imagens_bytes'];
$size_code        = (int)$storage['codigo_bytes'];
$size_other       = (int)$storage['outros_bytes'];
$gal              = $storage['galeria'];

$limite_gb         = 5;
$limite_bytes      = $limite_gb * 1024 * 1024 * 1024;
$livre_bytes       = max(0, $limite_bytes - $total_size_bytes);
$percentagem_total = ($limite_bytes > 0) ? ($total_size_bytes / $limite_bytes) * 100 : 0;

$pct_images = $total_size_bytes > 0 ? ($size_images / $total_size_bytes) * 100 : 0;
$pct_code   = $total_size_bytes > 0 ? ($size_code   / $total_size_bytes) * 100 : 0;
$pct_other  = $total_size_bytes > 0 ? ($size_other  / $total_size_bytes) * 100 : 0;

$rows = [
    [
        'cells' => [
            'Imagens',
            (int)$gal['total_count'] . ' ficheiros',
            '<span class="storage-num">' . ui_e(format_bytes($size_images)) . '</span>',
            '<span class="storage-num">' . number_format($pct_images, 1) . '%</span>',
            '<div class="acoes-tabela">' . ui_icon_button('view', ['href' => 'galeria_imagens.php', 'title' => 'Ver galeria']) . '</div>',
        ],
    ],
    [
        'cells' => [
            'Código e Scripts',
            'PHP, CSS e JS',
            '<span class="storage-num">' . ui_e(format_bytes($size_code)) . '</span>',
            '<span class="storage-num">' . number_format($pct_code, 1) . '%</span>',
            '-',
        ],
    ],
    [
        'cells' => [
            'Logs e Outros',
            'Registos e auxiliares',
            '<span class="storage-num">' . ui_e(format_bytes($size_other)) . '</span>',
            '<span class="storage-num">' . number_format($pct_other, 1) . '%</span>',
            '<div class="acoes-tabela">' . ui_icon_button('view', ['href' => 'ver_logs.php', 'title' => 'Ver logs']) . '</div>',
        ],
    ],
];
?>

<main class="dashboard-container animate-entry">
    <div class="storage-page">
        <?php echo ui_page_header('Uso de Armazenamento', [
            'layout'     => 'admin',
            'back_url'   => '/dev',
            'back_title' => 'Voltar ao Painel Dev',
        ]); ?>

        <div class="stats-grid">
            <?php
            echo ui_stat_card(format_bytes($total_size_bytes), 'Espaço Usado', [
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
            ]);
            echo ui_stat_card(format_bytes($livre_bytes), 'Livre de ' . $limite_gb . ' GB', [
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            ]);
            echo ui_stat_card(number_format($percentagem_total, 2) . '%', 'Ocupação', [
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>',
            ]);
            ?>
        </div>

        <?php
        echo ui_table([
            ['label' => 'Categoria', 'attrs' => ['style' => 'text-align: left;']],
            ['label' => 'Conteúdo', 'attrs' => ['style' => 'text-align: left;']],
            'Tamanho',
            'Peso',
            'Ações',
        ], $rows, [
            'table_class' => 'admin-table storage-table',
            'client_sort' => true,
        ]);
        ?>
    </div>
</main>

<?php include '../templates/footer.php'; ?>
