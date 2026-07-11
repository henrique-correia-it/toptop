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
       AND e.data_encomenda >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
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
            'cliente_nome'      => $reserva['cliente_nome'],
            'data_encomenda'    => $reserva['data_encomenda'],
            'segundos_restantes'=> (int)$reserva['segundos_restantes'],
            'total_items'       => 0,
        ];
    }
    $encomendas_agrupadas[$eid]['total_items'] += (int)$reserva['quantidade_reservada'];
}

function formatarTempoReserva(int $segundos): string {
    $segundos = max(0, $segundos);
    return sprintf('%02d:%02d', intdiv($segundos, 60), $segundos % 60);
}

include '../templates/header.php';
?>

<main class="dashboard-container animate-entry">
<script>if ('scrollRestoration' in history) { history.scrollRestoration = 'manual'; } window.scrollTo(0,0);</script>

    <div class="admin-page-header">
        <div class="header-title-container">
            <?php renderBackButton('/admin', 'Voltar ao Painel'); ?>
            <h2>Reservas de Stock</h2>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <script>document.addEventListener('DOMContentLoaded',function(){mostrarPopup("<?= addslashes($_SESSION['flash_message']['texto']) ?>","<?= addslashes($_SESSION['flash_message']['tipo']) ?>");});</script>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <div class="prod-toolbar">
        <div class="prod-toolbar-right">
            <button class="button btn-sel-mode" id="btn-sel-mode" type="button">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="5" width="4" height="4" rx="1"/><rect x="3" y="13" width="4" height="4" rx="1"/><line x1="11" y1="7" x2="21" y2="7"/><line x1="11" y1="15" x2="21" y2="15"/></svg>
                Selecionar
            </button>
        </div>
    </div>

    <div class="sel-mode-bar" id="sel-mode-bar">
        <span><span id="sel-count">0</span> selecionado(s)</span>
        <button class="btn-sel-all" id="btn-sel-all" type="button">Selecionar todos</button>
    </div>

    <div class="table-wrapper reservation-table-wrapper" id="table-wrapper">
        <table class="admin-table reservation-table">
            <thead>
                <tr>
                    <th class="col-sel" style="width:40px; display:none;"></th>
                    <th>Encomenda</th>
                    <th>Unidades</th>
                    <th>Volta ao Stock em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($encomendas_agrupadas)): ?>
                    <?php foreach ($encomendas_agrupadas as $enc): ?>
                        <tr data-id="<?= (int)$enc['id'] ?>">
                            <td class="col-sel" style="display:none;">
                                <div class="log-check-circle">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                            </td>
                            <td><strong>#<?= (int)$enc['id'] ?></strong></td>
                            <td><strong><?= (int)$enc['total_items'] ?></strong></td>
                            <td>
                                <span class="reservation-countdown" data-seconds="<?= $enc['segundos_restantes'] ?>">
                                    <?= formatarTempoReserva($enc['segundos_restantes']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="acoes-tabela">
                                    <a href="detalhes_encomenda.php?id=<?= (int)$enc['id'] ?>&return_to=reservas_stock" class="btn-view-single" title="Ver encomenda"></a>
                                    <button class="btn-cancel-single" data-id="<?= (int)$enc['id'] ?>" type="button" title="Cancelar reserva e repor stock"></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="reservation-empty">Não existem encomendas temporariamente reservadas neste momento.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="bulk-bar" id="bulk-bar">
    <span class="bulk-bar-count"><span id="bulk-count">0</span> selecionado(s)</span>
    <div class="bulk-bar-spacer"></div>
    <button class="bulk-bar-cancel" id="bulk-cancel" type="button">Cancelar</button>
    <button class="btn-admin-danger" id="bulk-cancel-reservas" type="button">Cancelar e Repor Stock</button>
</div>

<form id="form-cancelar-massa" action="cancelar_reservas_massa.php" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div id="form-ids"></div>
</form>

<script>
const CSRF = <?= json_encode($_SESSION['csrf_token']) ?>;

document.addEventListener('DOMContentLoaded', function () {
    const btnSelMode  = document.getElementById('btn-sel-mode');
    const selModeBar  = document.getElementById('sel-mode-bar');
    const selCountEl  = document.getElementById('sel-count');
    const btnSelAll   = document.getElementById('btn-sel-all');
    const bulkBar     = document.getElementById('bulk-bar');
    const bulkCountEl = document.getElementById('bulk-count');
    const bulkCancel  = document.getElementById('bulk-cancel');
    const bulkAction  = document.getElementById('bulk-cancel-reservas');
    const tableWrap   = document.getElementById('table-wrapper');

    let selMode  = false;
    let selected = new Set();

    function updateUI() {
        const n = selected.size;
        selCountEl.textContent = n;
        bulkCountEl.textContent = n;
        const total = document.querySelectorAll('.reservation-table tbody tr[data-id]').length;
        btnSelAll.textContent = (n === total && total > 0) ? 'Desselecionar todos' : 'Selecionar todos';
        bulkBar.classList.toggle('visible', n > 0);
    }

    btnSelMode.addEventListener('click', () => {
        selMode = !selMode;
        tableWrap.classList.toggle('sel-mode', selMode);
        btnSelMode.classList.toggle('active', selMode);
        selModeBar.classList.toggle('visible', selMode);
        if (!selMode) {
            selected.clear();
            document.querySelectorAll('.reservation-table tbody tr').forEach(r => r.classList.remove('selecionado'));
        }
        updateUI();
    });

    bulkCancel.addEventListener('click', () => btnSelMode.click());

    document.querySelector('.reservation-table tbody')?.addEventListener('click', function (e) {
        if (!selMode) return;
        const tr = e.target.closest('tr[data-id]');
        if (!tr) return;
        const id = tr.dataset.id;
        if (selected.has(id)) { selected.delete(id); tr.classList.remove('selecionado'); }
        else                   { selected.add(id);    tr.classList.add('selecionado'); }
        updateUI();
    });

    btnSelAll.addEventListener('click', () => {
        const rows = [...document.querySelectorAll('.reservation-table tbody tr[data-id]')];
        const allSel = rows.every(r => selected.has(r.dataset.id));
        rows.forEach(r => {
            if (allSel) { selected.delete(r.dataset.id); r.classList.remove('selecionado'); }
            else        { selected.add(r.dataset.id);    r.classList.add('selecionado'); }
        });
        updateUI();
    });

    bulkAction.addEventListener('click', () => {
        const ids = [...selected];
        if (!ids.length) return;
        mostrarModalConfirmacao('Cancelar Reservas', `Cancelar ${ids.length} reserva(s) e repor stock?`, () => {
            const form = document.getElementById('form-cancelar-massa');
            const container = document.getElementById('form-ids');
            container.innerHTML = '';
            ids.forEach(id => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
                container.appendChild(inp);
            });
            form.submit();
        });
    });

    // Botão individual de cancelar
    document.querySelector('.reservation-table tbody')?.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-cancel-single');
        if (!btn || selMode) return;
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

    // Contador regressivo
    const counters = document.querySelectorAll('.reservation-countdown');
    const fmt = s => { s = Math.max(0,s); return String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0'); };
    setInterval(() => {
        counters.forEach(c => {
            const next = Math.max(0, parseInt(c.dataset.seconds||'0',10)-1);
            c.dataset.seconds = String(next);
            c.textContent = fmt(next);
            c.classList.toggle('ending', next <= 300);
        });
    }, 1000);
});
</script>

<?php include '../templates/footer.php'; ?>
