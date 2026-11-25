<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/formatters.php';
require_once __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || $_SESSION['admin_role'] !== 'desenvolvedor') {
    header("Location: /admin/admin.php");
    exit;
}

include '../config/database.php';
include '../templates/header.php';

$por_pagina = 50;
$pagina     = max(1, (int)($_GET['p'] ?? 1));

$filtro_resultado = $_GET['resultado'] ?? '';
$filtro_username  = trim($_GET['username'] ?? '');

$where  = [];
$params = [];
$types  = '';

if ($filtro_resultado === 'sucesso' || $filtro_resultado === 'falha') {
    $where[]  = 'resultado = ?';
    $params[] = $filtro_resultado;
    $types   .= 's';
}
if ($filtro_username !== '') {
    $like     = '%' . $filtro_username . '%';
    $where[]  = '(username LIKE ? OR ip LIKE ? OR motivo_falha LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total_stmt = $conn->prepare("SELECT COUNT(*) FROM admin_login_logs $where_sql");
if ($params) { $total_stmt->bind_param($types, ...$params); }
$total_stmt->execute();
$total = (int)$total_stmt->get_result()->fetch_row()[0];
$total_stmt->close();
$total_paginas = max(1, (int)ceil($total / $por_pagina));
$pagina = min($pagina, $total_paginas);
$offset = ($pagina - 1) * $por_pagina;

$sort_map = [
    'data'        => 'created_at',
    'username'    => 'username',
    'role'        => 'role',
    'resultado'   => 'resultado',
    'dispositivo' => 'user_agent',
    'ip'          => 'ip',
];
$sort_by   = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map) ? $_GET['sort'] : 'data';
$sort_dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'asc') ? 'asc' : (isset($_GET['dir']) ? 'desc' : 'desc');
$order_sql = $sort_map[$sort_by] . ' ' . strtoupper($sort_dir) . ', id DESC';

$stmt = $conn->prepare("SELECT * FROM admin_login_logs $where_sql ORDER BY {$order_sql} LIMIT ? OFFSET ?");
$p_exec  = $params;
$t_exec  = $types . 'ii';
$p_exec[] = $por_pagina;
$p_exec[] = $offset;
$stmt->bind_param($t_exec, ...$p_exec);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function parseUserAgent(string $ua): array {
    $browser = 'Desconhecido';
    $os      = 'Desconhecido';

    if (preg_match('/Edg\//i', $ua))          $browser = 'Edge';
    elseif (preg_match('/OPR\//i', $ua))       $browser = 'Opera';
    elseif (preg_match('/Chrome\//i', $ua))    $browser = 'Chrome';
    elseif (preg_match('/Firefox\//i', $ua))   $browser = 'Firefox';
    elseif (preg_match('/Safari\//i', $ua))    $browser = 'Safari';

    if (preg_match('/Android/i', $ua))         $os = 'Android';
    elseif (preg_match('/iPhone|iPad/i', $ua)) $os = 'iOS';
    elseif (preg_match('/Windows/i', $ua))     $os = 'Windows';
    elseif (preg_match('/Mac OS/i', $ua))      $os = 'macOS';
    elseif (preg_match('/Linux/i', $ua))       $os = 'Linux';

    return ['browser' => $browser, 'os' => $os];
}

$qs_base = http_build_query(array_filter([
    'resultado' => $filtro_resultado,
    'username'  => $filtro_username,
    'sort'      => isset($_GET['sort']) ? $sort_by : null,
    'dir'       => isset($_GET['sort']) ? $sort_dir : null,
]));
?>


<main class="dashboard-container animate-entry">
    
    <?php echo ui_page_header("Registos de Login", ['layout' => 'admin', 'back_url' => '/dev', 'back_title' => 'Painel Dev']); ?>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                mostrarPopup(<?php echo json_encode($_SESSION['flash_message']['texto'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($_SESSION['flash_message']['tipo'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
            });
        </script>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php echo ui_table_toolbar([
        'filter' => ['name' => 'resultado', 'options' => ['' => 'Todos os resultados', 'sucesso' => 'Sucesso', 'falha' => 'Falhados'], 'value' => $filtro_resultado, 'title' => 'Filtrar por resultado'],
        'search' => ['name' => 'username', 'value' => $filtro_username],
    ]); ?>

    <?php
        $logRows = [];
        foreach ($logs as $log) {
            $device         = parseUserAgent($log['user_agent'] ?? '');
            $data_formatada = date('d/m/Y H:i:s', strtotime($log['created_at']));

            $roleVal = trim((string)($log['role'] ?? ''));
            if ($roleVal !== '') {
                $roleTone = match ($roleVal) {
                    'superadmin'    => 'danger',
                    'admin'         => 'success',
                    'desenvolvedor' => 'accent',
                    default         => 'neutral',
                };
                $roleCell = ui_badge($roleVal, $roleTone);
            } else {
                $roleCell = '-';
            }

            $resCell = ($log['resultado'] === 'sucesso')
                ? ui_badge('Sucesso', 'success')
                : ui_badge('Falha', 'danger') . ($log['motivo_falha'] ? '<span class="motivo">' . ui_e($log['motivo_falha']) . '</span>' : '');

            $logRows[] = [
                'attrs' => ['data-id' => (int)$log['id']],
                'cells' => [
                    ['style' => 'white-space:nowrap;', 'html' => ui_e($data_formatada)],
                    ui_e($log['username']),
                    $roleCell,
                    $resCell,
                    '<div class="device-info"><span class="browser">' . ui_e($device['browser']) . '</span><span class="os">' . ui_e($device['os']) . '</span></div>',
                    ui_e((string)$log['ip']),
                ],
            ];
        }

        echo ui_table([
            ['label' => 'Data / Hora', 'sort' => ['key' => 'data', 'default_dir' => 'desc']],
            ['label' => 'Utilizador', 'sort' => 'username'],
            ['label' => 'Cargo', 'sort' => 'role'],
            ['label' => 'Resultado', 'sort' => 'resultado'],
            ['label' => 'Dispositivo', 'sort' => 'dispositivo'],
            ['label' => 'IP', 'sort' => 'ip'],
        ], $logRows, [
            'selectable'        => true,
            'sel_hidden_inline' => true,
            'wrap_id'           => 'table-wrapper',
            'wrap_style'        => 'max-width: 1200px; width: 100%;',
            'table_class'       => 'admin-table logs-table',
            'empty_message'     => 'Nenhum registo encontrado para os filtros selecionados.',
            'sort'              => ['by' => $sort_by, 'dir' => $sort_dir, 'page_param' => 'p'],
        ]);
        ?>

        <?php
        echo ui_pagination($pagina, $total_paginas, fn($p) => '?p=' . $p . ($qs_base !== '' ? '&' . $qs_base : ''));
        ?>
</main>

<?php echo ui_bulk_bar('form-delete-logs', 'apagar_logs_massa.php', ['action_label' => 'Apagar Selecionados']); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    window.initAdminBulkSelection({
        formId: 'form-delete-logs',
        confirmTitle: 'Limpar Registos',
        confirmMessage: ids => `Apagar permanentemente ${ids.length} registo(s) selecionado(s)?`,
    });
});
</script>

<?php include '../templates/footer.php'; ?>
