<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: /entrar");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_from_post()) {
        http_response_code(403);
        exit('Erro de validação de segurança.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'apagar_massa') {
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($v) => $v > 0));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $typesDel = str_repeat('i', count($ids));
            $stmt = $conn->prepare("DELETE FROM contactos WHERE id IN ({$placeholders})");
            $stmt->bind_param($typesDel, ...$ids);
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => count($ids) . ' mensagem(ns) apagada(s) com sucesso!'];
            } else {
                $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Erro ao apagar mensagens.'];
            }
            $stmt->close();
        }
        header("Location: mensagens.php");
        exit;
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($id && $action === 'apagar') {
        $stmt = $conn->prepare("DELETE FROM contactos WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => 'Mensagem apagada com sucesso!'];
        } else {
            $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Erro ao apagar a mensagem.'];
        }
        $stmt->close();

        $return_to = $_POST['return_to'] ?? 'mensagens.php';
        $return_safe = in_array($return_to, ['mensagens.php', 'admin.php', '/admin'], true) ? $return_to : 'mensagens.php';
        header("Location: " . $return_safe);
        exit;
    }
}

$filtro_estado = trim((string)($_GET['estado'] ?? ''));
$filtro_q      = trim((string)($_GET['q'] ?? ''));

$where  = [];
$params = [];
$types  = '';

if ($filtro_estado === 'pendentes') {
    $where[] = 'respondida = 0';
} elseif ($filtro_estado === 'respondidas') {
    $where[] = 'respondida = 1';
}
if ($filtro_q !== '') {
    $like     = '%' . $filtro_q . '%';
    $where[]  = '(nome LIKE ? OR email LIKE ? OR mensagem LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

$sort_map = [
    'nome'   => 'nome',
    'data'   => 'data_hora',
    'estado' => 'respondida',
];
$sort_by   = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map) ? $_GET['sort'] : '';
$sort_dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'asc') ? 'asc' : 'desc';
$order_sql = $sort_by !== ''
    ? ($sort_map[$sort_by] . ' ' . strtoupper($sort_dir) . ', data_hora DESC')
    : 'respondida ASC, data_hora DESC';

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $conn->prepare("SELECT * FROM contactos {$where_sql} ORDER BY {$order_sql}");
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$mensagens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include '../templates/header.php';
?>

<main class="dashboard-container animate-entry">

    <?php echo ui_page_header("Mensagens de Contacto", ['layout' => 'admin', 'back_url' => '/admin', 'back_title' => 'Voltar ao Painel']); ?>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                mostrarPopup(<?php echo json_encode($_SESSION['flash_message']['texto'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($_SESSION['flash_message']['tipo'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
            });
        </script>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php echo ui_table_toolbar([
        'filter' => [
            'name'    => 'estado',
            'options' => [
                ''            => 'Todas as mensagens',
                'pendentes'   => 'Por responder',
                'respondidas' => 'Respondidas',
            ],
            'value'   => $filtro_estado,
            'title'   => 'Filtrar por estado',
        ],
        'search' => ['name' => 'q', 'value' => $filtro_q],
    ]); ?>

    <?php
    $msgRows = [];
    foreach ($mensagens as $msg) {
        $id         = (int)$msg['id'];
        $respondida = (int)$msg['respondida'] === 1;

        $remetenteHtml = '<div class="msg-sender"><strong title="' . ui_e($msg['nome']) . '">' . ui_e($msg['nome']) . '</strong>'
            . '<small title="' . ui_e($msg['email']) . '">' . ui_e($msg['email']) . '</small></div>';

        $previewHtml = '<div class="msg-preview" title="Clique para ler e responder">'
            . ui_e($msg['mensagem']) . '</div>';

        $estadoBadge = ui_badge($respondida ? 'Respondida' : 'Por responder', $respondida ? 'success' : 'warning', [
            'tag'         => 'button',
            'interactive' => true,
            'class'       => 'btn-toggle-msg-estado',
            'title'       => $respondida ? 'Clique para marcar como por responder' : 'Clique para marcar como respondida',
            'attrs'       => ['data-id' => $id, 'data-status' => $respondida ? '1' : '0'],
        ]);

        $acoesHtml = '<div class="acoes-tabela">'
            . (!$respondida ? ui_icon_button('reply', ['class' => 'btn-responder-email', 'title' => 'Ver e responder por email']) : '')
            . '<form action="mensagens.php" method="POST" style="margin:0;">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="csrf_token" value="' . ui_e($_SESSION['csrf_token']) . '">'
            . ui_icon_button('delete', [
                'type'  => 'submit',
                'class' => 'btn-apagar-confirmado',
                'title' => 'Apagar mensagem',
                'attrs' => [
                    'name'                      => 'action',
                    'value'                     => 'apagar',
                    'data-mensagem-confirmacao' => 'Tem a certeza que quer apagar esta mensagem permanentemente?',
                ],
            ])
            . '</form></div>';

        $msgRows[] = [
            'attrs' => [
                'data-id'       => $id,
                'data-nome'     => $msg['nome'],
                'data-email'    => $msg['email'],
                'data-mensagem' => $msg['mensagem'],
                'data-data'     => date('d/m/Y H:i', strtotime($msg['data_hora'])),
                'data-status'   => $respondida ? '1' : '0',
            ],
            'cells' => [
                $remetenteHtml,
                $previewHtml,
                ['style' => 'color:var(--cor-slate-500); white-space:nowrap;', 'html' => date('d/m/Y H:i', strtotime($msg['data_hora']))],
                $estadoBadge,
                $acoesHtml,
            ],
        ];
    }

    echo ui_table([
        ['label' => 'Remetente', 'sort' => 'nome'],
        'Mensagem',
        ['label' => 'Data', 'sort' => ['key' => 'data', 'default_dir' => 'desc']],
        ['label' => 'Estado', 'sort' => 'estado'],
        'Ações',
    ], $msgRows, [
        'wrap_id'       => 'table-wrapper',
        'wrap_style'    => 'max-width: 1200px; margin: 0 auto;',
        'table_class'   => 'admin-table tabela-mensagens',
        'empty_message' => ($filtro_q !== '' || $filtro_estado !== '') ? 'Nenhuma mensagem encontrada com os filtros selecionados.' : 'Não existem mensagens de contacto de momento.',
        'sort'          => ['by' => $sort_by, 'dir' => $sort_dir],
    ]);
    ?>
</main>

<?php echo ui_bulk_bar('form-delete-mensagens', 'mensagens.php', [
    'unit'         => 'selecionada(s)',
    'action_label' => 'Apagar Selecionadas',
    'hidden'       => ['action' => 'apagar_massa'],
]); ?>

<!-- Modal Ver & Responder Email (ui_modal da biblioteca) -->
<?php
$modalBodyHtml = '<form id="form-resposta-email" class="msg-reply-form">'
    . '<input type="hidden" id="resposta-id-contacto">'
    . '<input type="hidden" id="resposta-email-cliente">'
    . '<input type="hidden" id="resposta-nome-cliente">'
    . '<div class="msg-original-box">'
    . '  <div class="msg-original-meta" id="resposta-meta-original"></div>'
    . '  <p id="resposta-texto-original"></p>'
    . '</div>'
    . ui_field('assunto', 'Assunto', ['id' => 'resposta-assunto', 'required' => true])
    . ui_field('mensagem', 'Resposta', ['type' => 'textarea', 'id' => 'resposta-mensagem', 'rows' => 5, 'placeholder' => 'Escreve a tua resposta...', 'required' => true])
    . '</form>';

$modalFooterHtml = ui_button('Cancelar', ['class' => 'qe-btn-cancel'])
    . ui_button('Enviar Resposta', ['type' => 'submit', 'variant' => 'primary', 'form' => 'form-resposta-email', 'id' => 'btn-enviar-resposta']);

echo ui_modal('modal-resposta-email', 'Responder', $modalBodyHtml, [
    'class'  => 'msg-modal',
    'footer' => $modalFooterHtml,
]);
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modal-resposta-email');
    const formResposta = document.getElementById('form-resposta-email');
    const csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;

    const bulkSel = window.initAdminBulkSelection({
        formId: 'form-delete-mensagens',
        selectAllLabel: 'Selecionar todas',
        deselectAllLabel: 'Desselecionar todas',
        confirmTitle: 'Apagar Mensagens',
        confirmMessage: ids => `Apagar permanentemente ${ids.length} mensagem(ns)?`,
    });

    function atualizarEstadoLinha(tr, respondida) {
        if (!tr) return;
        tr.dataset.status = respondida ? '1' : '0';
        const badgeBtn = tr.querySelector('.btn-toggle-msg-estado');
        if (badgeBtn) {
            badgeBtn.dataset.status = respondida ? '1' : '0';
            badgeBtn.textContent = respondida ? 'Respondida' : 'Por responder';
            badgeBtn.classList.toggle('ui-badge--success', respondida);
            badgeBtn.classList.toggle('ui-badge--warning', !respondida);
            badgeBtn.title = respondida ? 'Clique para marcar como por responder' : 'Clique para marcar como respondida';
        }
        const acoes = tr.querySelector('.acoes-tabela');
        const replyBtn = acoes ? acoes.querySelector('.btn-responder-email') : null;
        if (respondida && replyBtn) {
            replyBtn.remove();
        } else if (!respondida && acoes && !replyBtn) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-reply-single btn-responder-email';
            btn.title = 'Ver e responder por email';
            btn.setAttribute('aria-label', 'Ver e responder por email');
            acoes.prepend(btn);
        }
    }

    function abrirModalResposta(tr) {
        const jaRespondida = tr.dataset.status === '1';
        document.getElementById('modal-resposta-email-title').textContent = jaRespondida
            ? `Mensagem de ${tr.dataset.nome}`
            : `Responder a ${tr.dataset.nome}`;
        document.getElementById('resposta-id-contacto').value = tr.dataset.id;
        document.getElementById('resposta-nome-cliente').value = tr.dataset.nome;
        document.getElementById('resposta-email-cliente').value = tr.dataset.email;
        document.getElementById('resposta-meta-original').textContent = `${tr.dataset.nome} (${tr.dataset.email}) · ${tr.dataset.data}`;
        document.getElementById('resposta-texto-original').textContent = tr.dataset.mensagem;
        document.getElementById('resposta-assunto').value = 'Re: Contacto via TopTop';
        document.getElementById('resposta-mensagem').value = '';

        formResposta.querySelectorAll('.ui-field').forEach(f => {
            f.style.display = jaRespondida ? 'none' : '';
        });
        const btnEnviar = document.getElementById('btn-enviar-resposta');
        if (btnEnviar) btnEnviar.style.display = jaRespondida ? 'none' : '';
        const btnCancel = modal.querySelector('.qe-btn-cancel');
        if (btnCancel) btnCancel.textContent = jaRespondida ? 'Fechar' : 'Cancelar';

        modal.style.display = 'flex';
        document.documentElement.classList.add('scroll-lock');
        if (!jaRespondida) {
            setTimeout(() => document.getElementById('resposta-mensagem').focus(), 50);
        }
    }

    formResposta?.addEventListener('submit', enviarResposta);

    document.querySelector('.tabela-mensagens tbody')?.addEventListener('click', (e) => {
        if (bulkSel && bulkSel.isSelMode()) return;

        const trigger = e.target.closest('.btn-responder-email, .msg-preview');
        if (trigger) {
            const tr = trigger.closest('tr[data-id]');
            if (tr) abrirModalResposta(tr);
            return;
        }

        const badgeBtn = e.target.closest('.btn-toggle-msg-estado');
        if (badgeBtn) {
            const tr = badgeBtn.closest('tr[data-id]');
            if (!tr || badgeBtn.classList.contains('loading')) return;
            const novoStatus = badgeBtn.dataset.status === '1' ? 0 : 1;
            badgeBtn.classList.add('loading');

            fetch('ajax_mensagem_estado.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: csrfToken, id: tr.dataset.id, respondida: novoStatus })
            })
            .then(r => r.json().then(d => ({ ok: r.ok, d })))
            .then(({ ok, d }) => {
                if (!ok || !d.sucesso) throw new Error(d.mensagem || 'Erro ao atualizar estado.');
                atualizarEstadoLinha(tr, novoStatus === 1);
                mostrarPopup(novoStatus === 1 ? 'Marcada como respondida.' : 'Marcada como por responder.', 'sucesso');
            })
            .catch(err => mostrarPopup(err.message || 'Erro de ligação.', 'erro'))
            .finally(() => badgeBtn.classList.remove('loading'));
        }
    });

    async function enviarResposta(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-enviar-resposta');
        btn.disabled = true;
        btn.textContent = 'A enviar...';

        const idContacto = document.getElementById('resposta-id-contacto').value;
        const textoOriginal = document.getElementById('resposta-texto-original').textContent;
        const respostaTexto = document.getElementById('resposta-mensagem').value.trim();

        try {
            const response = await fetch('ajax_responder_contacto.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    id_contacto: idContacto,
                    nome_cliente: document.getElementById('resposta-nome-cliente').value,
                    email_cliente: document.getElementById('resposta-email-cliente').value,
                    assunto: document.getElementById('resposta-assunto').value,
                    mensagem: respostaTexto + '\n\n---\nMensagem Original:\n' + textoOriginal
                })
            });
            const result = await response.json();
            if (result.sucesso) {
                const tr = document.querySelector(`.tabela-mensagens tbody tr[data-id="${idContacto}"]`);
                atualizarEstadoLinha(tr, true);
                modal.style.display = 'none';
                document.documentElement.classList.remove('scroll-lock');
                mostrarPopup('Resposta enviada e marcada como respondida!', 'sucesso');
            } else {
                mostrarPopup(result.mensagem || 'Erro ao enviar resposta.', 'erro');
            }
        } catch {
            mostrarPopup('Erro de comunicação ao enviar resposta.', 'erro');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Enviar Resposta';
        }
    }
});
</script>

<?php include '../templates/footer.php'; ?>
