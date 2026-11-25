<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: /entrar");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$filtro_q      = trim((string)($_GET['q'] ?? ''));
$filtro_estado = trim((string)($_GET['estado'] ?? ''));

$stmt = $conn->prepare(
    "SELECT
        e.id AS encomenda_id,
        e.cliente_nome,
        e.cliente_email,
        e.data_encomenda,
        ei.quantidade AS quantidade_reservada,
        GREATEST(0, 1800 - TIMESTAMPDIFF(SECOND, e.data_encomenda, NOW())) AS segundos_restantes
     FROM encomendas e
     INNER JOIN encomenda_itens ei ON ei.encomenda_id = e.id
     WHERE e.estado = 'incompleta'
     ORDER BY e.data_encomenda ASC, e.id ASC"
);
$stmt->execute();
$reservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$encomendas_agrupadas = [];
foreach ($reservas as $reserva) {
    $eid = $reserva['encomenda_id'];
    if (!isset($encomendas_agrupadas[$eid])) {
        $encomendas_agrupadas[$eid] = [
            'id'                => $eid,
            'cliente_nome'      => (string)($reserva['cliente_nome'] ?? ''),
            'cliente_email'     => (string)($reserva['cliente_email'] ?? ''),
            'data_encomenda'    => $reserva['data_encomenda'],
            'segundos_restantes'=> (int)$reserva['segundos_restantes'],
            'total_items'       => 0,
        ];
    }
    $encomendas_agrupadas[$eid]['total_items'] += (int)$reserva['quantidade_reservada'];
}

if ($filtro_estado === 'ativas') {
    $encomendas_agrupadas = array_filter($encomendas_agrupadas, fn($e) => $e['segundos_restantes'] > 0);
} elseif ($filtro_estado === 'expiradas') {
    $encomendas_agrupadas = array_filter($encomendas_agrupadas, fn($e) => $e['segundos_restantes'] <= 0);
}
if ($filtro_q !== '') {
    $qLower = mb_strtolower($filtro_q, 'UTF-8');
    $qNum   = ltrim($filtro_q, '#');
    $encomendas_agrupadas = array_filter($encomendas_agrupadas, function ($e) use ($qLower, $qNum) {
        return (ctype_digit($qNum) && (string)$e['id'] === $qNum)
            || mb_stripos($e['cliente_nome'], $qLower, 0, 'UTF-8') !== false
            || mb_stripos($e['cliente_email'], $qLower, 0, 'UTF-8') !== false;
    });
}

$sort_allowed = ['id', 'cliente', 'unidades', 'tempo'];
$sort_by      = isset($_GET['sort']) && in_array($_GET['sort'], $sort_allowed, true) ? $_GET['sort'] : 'tempo';
$sort_dir     = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'desc') ? 'desc' : 'asc';
$mult         = $sort_dir === 'desc' ? -1 : 1;

usort($encomendas_agrupadas, function ($a, $b) use ($sort_by, $mult) {
    $cmp = match ($sort_by) {
        'id'       => $a['id'] <=> $b['id'],
        'cliente'  => strcasecmp($a['cliente_nome'] ?: $a['cliente_email'], $b['cliente_nome'] ?: $b['cliente_email']),
        'unidades' => $a['total_items'] <=> $b['total_items'],
        default    => $a['segundos_restantes'] <=> $b['segundos_restantes'],
    };
    return ($cmp !== 0 ? $cmp : ($a['id'] <=> $b['id'])) * $mult;
});

function formatarTempoReserva(int $segundos): string {
    $segundos = max(0, $segundos);
    return sprintf('%02d:%02d', intdiv($segundos, 60), $segundos % 60);
}

include '../templates/header.php';
?>

<main class="dashboard-container animate-entry">

    <?php echo ui_page_header("Reservas de Stock", ['layout' => 'admin', 'back_url' => '/admin', 'back_title' => 'Voltar ao Painel']); ?>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <script>document.addEventListener('DOMContentLoaded',function(){mostrarPopup(<?= json_encode($_SESSION['flash_message']['texto'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,<?= json_encode($_SESSION['flash_message']['tipo'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);});</script>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php echo ui_table_toolbar([
        'filter' => ['name' => 'estado', 'options' => ['' => 'Todas as reservas', 'ativas' => 'Ativas', 'expiradas' => 'Expiradas'], 'value' => $filtro_estado, 'title' => 'Filtrar por estado'],
        'search' => ['name' => 'q', 'value' => $filtro_q],
    ]); ?>

    <?php
    $reservaRows = [];
    foreach ($encomendas_agrupadas as $enc) {
        $acoes = '<div class="acoes-tabela">'
            . ui_icon_button('view', ['href' => 'detalhes_encomenda.php?id=' . (int)$enc['id'] . '&return_to=reservas_stock', 'title' => 'Ver encomenda'])
            . ui_icon_button('cancel', ['title' => 'Cancelar reserva e repor stock', 'attrs' => ['data-id' => (int)$enc['id']]])
            . '</div>';

        $seg = (int)$enc['segundos_restantes'];
        if ($seg <= 0) {
            $tempoHtml = ui_badge('Expirada', 'danger', [
                'class' => 'reservation-countdown',
                'attrs' => ['data-seconds' => '0'],
            ]);
        } else {
            $tempoHtml = ui_badge(formatarTempoReserva($seg), $seg <= 300 ? 'warning' : 'info', [
                'class' => 'reservation-countdown',
                'attrs' => ['data-seconds' => (string)$seg],
            ]);
        }

        $clienteInfo = $enc['cliente_nome'] !== ''
            ? '<strong>' . ui_e($enc['cliente_nome']) . '</strong>' . ($enc['cliente_email'] !== '' ? '<br><small style="color:var(--cor-slate-500);">' . ui_e($enc['cliente_email']) . '</small>' : '')
            : ($enc['cliente_email'] !== '' ? ui_e($enc['cliente_email']) : '<span style="color:var(--cor-slate-400);">-</span>');

        $reservaRows[] = [
            'attrs' => ['data-id' => (int)$enc['id']],
            'cells' => [
                '<strong>#' . (int)$enc['id'] . '</strong>',
                $clienteInfo,
                '<strong>' . (int)$enc['total_items'] . '</strong>',
                $tempoHtml,
                $acoes,
            ],
        ];
    }

    echo ui_table([
        ['label' => 'Encomenda', 'sort' => ['key' => 'id', 'default_dir' => 'desc']],
        ['label' => 'Cliente', 'sort' => 'cliente'],
        ['label' => 'Unidades', 'sort' => ['key' => 'unidades', 'default_dir' => 'desc']],
        ['label' => 'Volta ao Stock em', 'sort' => 'tempo'],
        'Ações',
    ], $reservaRows, [
        'selectable'        => true,
        'sel_hidden_inline' => true,
        'wrap_id'           => 'table-wrapper',
        'wrap_class'        => 'table-wrapper reservation-table-wrapper',
        'table_class'       => 'admin-table reservation-table',
        'empty_message'     => ($filtro_q !== '' || $filtro_estado !== '') ? 'Nenhuma reserva encontrada com os filtros selecionados.' : 'Não existem encomendas temporariamente reservadas neste momento.',
        'sort'              => ['by' => isset($_GET['sort']) ? $sort_by : '', 'dir' => $sort_dir],
    ]);
    ?>
</main>

<?php echo ui_bulk_bar('form-cancelar-massa', 'cancelar_reservas_massa.php', [
    'action_id'    => 'bulk-cancel-reservas',
    'action_label' => 'Cancelar e Repor Stock',
]); ?>

<script>
const CSRF = <?= json_encode($_SESSION['csrf_token']) ?>;

document.addEventListener('DOMContentLoaded', function () {
    const bulkSel = window.initAdminBulkSelection({
        bulkActionId: 'bulk-cancel-reservas',
        formId: 'form-cancelar-massa',
        confirmTitle: 'Cancelar Reservas',
        confirmMessage: ids => `Cancelar ${ids.length} reserva(s) e repor stock?`,
    });

    // Botão individual de cancelar
    document.querySelector('.reservation-table tbody')?.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-cancel-single');
        if (!btn || (bulkSel && bulkSel.isSelMode())) return;
        const tr = btn.closest('tr');
        mostrarModalConfirmacao('Cancelar Reserva', 'Cancelar esta reserva e repor o stock?', () => {
            btn.disabled = true;
            fetch('cancelar_reserva.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: CSRF, id: parseInt(tr.dataset.id) })
            })
            .then(r => r.json())
            .then(data => {
                if (data.sucesso) {
                    tr.remove();
                    mostrarPopup(data.mensagem, 'sucesso');
                    if (!document.querySelector('.reservation-table tbody tr[data-id]')) {
                        document.querySelector('.reservation-table tbody').innerHTML =
                            '<tr><td colspan="5" class="reservation-empty">Não existem encomendas temporariamente reservadas neste momento.</td></tr>';
                    }
                } else {
                    mostrarPopup(data.mensagem, 'erro');
                    btn.disabled = false;
                }
            })
            .catch(() => { mostrarPopup('Erro de ligação', 'erro'); btn.disabled = false; });
        });
    });

    // Contador regressivo (consulta elementos vivos no DOM)
    const fmt = s => { s = Math.max(0, s); return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); };
    setInterval(() => {
        document.querySelectorAll('.reservation-countdown').forEach(c => {
            const cur = parseInt(c.dataset.seconds || '0', 10);
            if (cur <= 0) {
                c.textContent = 'Expirada';
                c.classList.remove('ui-badge--info', 'ui-badge--warning');
                c.classList.add('ui-badge--danger');
                return;
            }
            const next = Math.max(0, cur - 1);
            c.dataset.seconds = String(next);
            if (next === 0) {
                c.textContent = 'Expirada';
                c.classList.remove('ui-badge--info', 'ui-badge--warning');
                c.classList.add('ui-badge--danger');
            } else {
                c.textContent = fmt(next);
                c.classList.toggle('ui-badge--warning', next <= 300);
                c.classList.toggle('ui-badge--info', next > 300);
                c.classList.remove('ui-badge--danger');
            }
        });
    }, 1000);
});
</script>

<?php include '../templates/footer.php'; ?>
