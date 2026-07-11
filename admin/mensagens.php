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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        exit('Erro de validação de segurança.');
    }

    $action = $_POST['action'] ?? '';
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if ($id && $action) {
        $stmt = null;
        switch ($action) {
            case 'apagar':
                $stmt = $conn->prepare("DELETE FROM contactos WHERE id = ?");
                $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => 'Mensagem apagada com sucesso!'];
                break;
            case 'responder':
                $stmt = $conn->prepare("UPDATE contactos SET respondida = 1 WHERE id = ?");
                $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => 'Mensagem marcada como respondida!'];
                break;
            case 'desmarcar':
                $stmt = $conn->prepare("UPDATE contactos SET respondida = 0 WHERE id = ?");
                $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => 'Mensagem movida para recebidas!'];
                break;
        }
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
        $return_to = $_POST['return_to'] ?? 'mensagens.php';
        header("Location: " . $return_to);
        exit;
    }
}

$result_pendentes = $conn->query("SELECT * FROM contactos WHERE respondida = 0 ORDER BY data_hora DESC");
$mensagens_pendentes = $result_pendentes->fetch_all(MYSQLI_ASSOC);

$result_respondidas = $conn->query("SELECT * FROM contactos WHERE respondida = 1 ORDER BY data_hora DESC");
$mensagens_respondidas = $result_respondidas->fetch_all(MYSQLI_ASSOC);

include '../templates/header.php';
?>

<main class="dashboard-container animate-entry">

<!-- Bloquear scroll automático no refresh -->
<script>
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    window.scrollTo(0, 0);
</script>

    <div class="admin-page-header">
        <div class="header-title-container">
            <?php renderBackButton('/admin', 'Voltar ao Painel'); ?>
            <h2>Mensagens de Contacto</h2>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                mostrarPopup("<?php echo addslashes($_SESSION['flash_message']['texto']); ?>", "<?php echo addslashes($_SESSION['flash_message']['tipo']); ?>");
            });
        </script>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <div class="mensagens-container">
        <div class="mensagens-coluna">
            <div class="coluna-header">
                <h3>Recebidas</h3>
                <span class="contador"><?php echo count($mensagens_pendentes); ?></span>
            </div>
            <div class="coluna-body" data-status="0" data-empty-text="Não há mensagens recebidas.">
                <?php if (!empty($mensagens_pendentes)): ?>
                    <?php foreach($mensagens_pendentes as $msg): ?>
                        <div class="mensagem-card" draggable="true" data-id="<?php echo $msg['id']; ?>" data-nome="<?php echo htmlspecialchars($msg['nome']); ?>" data-email="<?php echo htmlspecialchars($msg['email']); ?>" data-status="0">
                            <div class="card-header">
                                <div class="remetente">
                                    <strong><?php echo htmlspecialchars($msg['nome']); ?></strong>
                                    <a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>"><?php echo htmlspecialchars($msg['email']); ?></a>
                                </div>
                                <span class="data"><?php echo date('d/m/y H:i', strtotime($msg['data_hora'])); ?></span>
                            </div>
                            <div class="card-body">
                                <p><?php echo nl2br(htmlspecialchars($msg['mensagem'])); ?></p>
                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn-reply-single btn-responder-email" title="Responder por Email"></button>
                                <form action="mensagens.php" method="POST" style="display:inline-flex; gap: 8px; margin: 0;">
                                    <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <button type="submit" name="action" value="responder" class="btn-archive-single" title="Arquivar"></button>
                                    <button type="submit" name="action" value="apagar" class="btn-del-single btn-apagar-confirmado" data-mensagem-confirmacao="Tem a certeza que quer apagar esta mensagem permanentemente?" title="Apagar"></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="sem-mensagens">Não há mensagens recebidas.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mensagens-coluna">
            <div class="coluna-header">
                <h3>Arquivadas</h3>
                <span class="contador"><?php echo count($mensagens_respondidas); ?></span>
            </div>
            <div class="coluna-body" data-status="1" data-empty-text="Não há mensagens arquivadas.">
                <?php if (!empty($mensagens_respondidas)): ?>
                    <?php foreach($mensagens_respondidas as $msg): ?>
                        <div class="mensagem-card respondida" draggable="true" data-id="<?php echo $msg['id']; ?>" data-nome="<?php echo htmlspecialchars($msg['nome']); ?>" data-email="<?php echo htmlspecialchars($msg['email']); ?>" data-status="1">
                             <div class="card-header">
                                <div class="remetente">
                                    <strong><?php echo htmlspecialchars($msg['nome']); ?></strong>
                                    <span><?php echo htmlspecialchars($msg['email']); ?></span>
                                </div>
                                <span class="data"><?php echo date('d/m/y H:i', strtotime($msg['data_hora'])); ?></span>
                            </div>
                            <div class="card-body">
                                <p><?php echo nl2br(htmlspecialchars($msg['mensagem'])); ?></p>
                            </div>
                            <div class="card-footer">
                                <form action="mensagens.php" method="POST" style="display:inline-flex; gap: 8px; margin: 0;">
                                    <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <button type="submit" name="action" value="desmarcar" class="btn-edit-single" title="Mover para Recebidas"></button>
                                    <button type="submit" name="action" value="apagar" class="btn-del-single btn-apagar-confirmado" data-mensagem-confirmacao="Tem a certeza que quer apagar esta mensagem permanentemente?" title="Apagar"></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="sem-mensagens">Não há mensagens arquivadas.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Modal Resposta Email -->
<?php renderQuickEditModal('modal-resposta-email', 'Responder a...'); ?>

<template id="tpl-modal-resposta">
    <input type="hidden" id="resposta-id-contacto">
    <input type="hidden" id="resposta-email-cliente">
    <input type="hidden" id="resposta-nome-cliente">
    <div class="qe-body">
        <div class="qe-f">
            <label>Assunto</label>
            <input type="text" id="resposta-assunto" class="qe-in" required>
        </div>
        <div class="qe-f">
            <label>Mensagem</label>
            <textarea id="resposta-mensagem" class="qe-in" rows="8" required></textarea>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modal-resposta-email');
    const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    let draggedCard = null;

    const abrirModalResposta = (card) => {
        const form = modal.querySelector('form');
        form.innerHTML = document.getElementById('tpl-modal-resposta').innerHTML + form.querySelector('.qe-btns').outerHTML;

        modal.querySelector('h3').textContent = `Responder a ${card.dataset.nome}`;
        modal.querySelector('#resposta-id-contacto').value = card.dataset.id;
        modal.querySelector('#resposta-nome-cliente').value = card.dataset.nome;
        modal.querySelector('#resposta-email-cliente').value = card.dataset.email;
        modal.querySelector('#resposta-assunto').value = `Re: Contacto via TopTop`;
        modal.querySelector('#resposta-mensagem').value = `\n\n---\nMensagem Original:\n${card.querySelector('.card-body p').textContent}`;

        modal.style.display = 'flex';
        form.onsubmit = enviarResposta;
    };

    document.addEventListener('click', (e) => {
        const replyBtn = e.target.closest('.btn-responder-email');
        if (!replyBtn) return;
        const card = replyBtn.closest('.mensagem-card');
        if (card) abrirModalResposta(card);
    });

    const footerHtml = (status, id) => {
        if (status === '1') {
            return `
                <form action="mensagens.php" method="POST" style="display:inline-flex; gap: 8px; margin: 0;">
                    <input type="hidden" name="id" value="${id}">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <button type="submit" name="action" value="desmarcar" class="btn-edit-single" title="Mover para Recebidas"></button>
                    <button type="submit" name="action" value="apagar" class="btn-del-single btn-apagar-confirmado" data-mensagem-confirmacao="Tem a certeza que quer apagar esta mensagem permanentemente?" title="Apagar"></button>
                </form>
            `;
        }

        return `
            <button type="button" class="btn-reply-single btn-responder-email" title="Responder por Email"></button>
            <form action="mensagens.php" method="POST" style="display:inline-flex; gap: 8px; margin: 0;">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <button type="submit" name="action" value="responder" class="btn-archive-single" title="Arquivar"></button>
                <button type="submit" name="action" value="apagar" class="btn-del-single btn-apagar-confirmado" data-mensagem-confirmacao="Tem a certeza que quer apagar esta mensagem permanentemente?" title="Apagar"></button>
            </form>
        `;
    };

    const atualizarVaziosEContadores = () => {
        document.querySelectorAll('.mensagens-coluna').forEach(coluna => {
            const body = coluna.querySelector('.coluna-body');
            const cards = body.querySelectorAll('.mensagem-card');
            const empty = body.querySelector('.sem-mensagens');
            const contador = coluna.querySelector('.contador');

            if (contador) contador.textContent = cards.length;

            if (cards.length === 0 && !empty) {
                const p = document.createElement('p');
                p.className = 'sem-mensagens';
                p.textContent = body.dataset.emptyText || 'Sem mensagens.';
                body.appendChild(p);
            } else if (cards.length > 0 && empty) {
                empty.remove();
            }
        });
    };

    const aplicarEstadoCard = (card, status) => {
        card.dataset.status = status;
        card.classList.toggle('respondida', status === '1');
        card.querySelector('.card-footer').innerHTML = footerHtml(status, card.dataset.id);
    };

    const moverMensagem = async (card, destino) => {
        const novoStatus = destino.dataset.status;
        if (!card || !novoStatus || card.dataset.status === novoStatus) return;

        const origem = card.closest('.coluna-body');
        const statusAnterior = card.dataset.status;

        destino.appendChild(card);
        aplicarEstadoCard(card, novoStatus);
        atualizarVaziosEContadores();

        try {
            const response = await fetch('ajax_mensagem_estado.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    id: card.dataset.id,
                    respondida: parseInt(novoStatus, 10)
                })
            });
            const result = await response.json();
            if (!response.ok || !result.sucesso) {
                throw new Error(result.mensagem || 'Erro ao atualizar mensagem.');
            }
            mostrarPopup(result.mensagem, 'sucesso');
        } catch (error) {
            origem.appendChild(card);
            aplicarEstadoCard(card, statusAnterior);
            atualizarVaziosEContadores();
            mostrarPopup(error.message, 'erro');
        }
    };

    document.querySelectorAll('.mensagem-card').forEach(card => {
        card.addEventListener('dragstart', (e) => {
            draggedCard = card;
            card.classList.add('a-arrastar');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', card.dataset.id);
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('a-arrastar');
            draggedCard = null;
            document.querySelectorAll('.coluna-body.drag-over').forEach(col => col.classList.remove('drag-over'));
        });
    });

    document.querySelectorAll('.coluna-body').forEach(body => {
        body.addEventListener('dragover', (e) => {
            if (!draggedCard) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            body.classList.add('drag-over');
        });

        body.addEventListener('dragleave', (e) => {
            if (!body.contains(e.relatedTarget)) body.classList.remove('drag-over');
        });

        body.addEventListener('drop', (e) => {
            e.preventDefault();
            body.classList.remove('drag-over');
            moverMensagem(draggedCard, body);
        });
    });

    async function enviarResposta(e) {
        e.preventDefault();
        const btn = e.target.querySelector('.qe-btn-save');
        btn.disabled = true; btn.textContent = 'A Enviar...';

        const dados = {
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>',
            id_contacto: document.getElementById('resposta-id-contacto').value,
            nome_cliente: document.getElementById('resposta-nome-cliente').value,
            email_cliente: document.getElementById('resposta-email-cliente').value,
            assunto: document.getElementById('resposta-assunto').value,
            mensagem: document.getElementById('resposta-mensagem').value
        };

        try {
            const response = await fetch('ajax_responder_contacto.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dados)
            });
            const result = await response.json();
            if (result.sucesso) {
                mostrarPopup(result.mensagem, 'sucesso');
                setTimeout(() => window.location.reload(), 1000);
            }
        } finally { btn.disabled = false; btn.textContent = 'Enviar Resposta'; }
    }
});
</script>

<?php include '../templates/footer.php'; ?>
