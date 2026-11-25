<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/rate_limit.php';
require_once __DIR__ . '/config/cliente_auth.php';

require_cliente();

$cliente = cliente_atual($conn);
if (!$cliente) {
    header('Location: /entrar');
    exit;
}
$erro = '';
$sucesso = '';

if (!empty($_SESSION['flash_dados_cliente']) && is_array($_SESSION['flash_dados_cliente'])) {
    if (($_SESSION['flash_dados_cliente']['tipo'] ?? '') === 'erro') {
        $erro = (string)($_SESSION['flash_dados_cliente']['msg'] ?? '');
    } else {
        $sucesso = (string)($_SESSION['flash_dados_cliente']['msg'] ?? '');
    }
    unset($_SESSION['flash_dados_cliente']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_from_post()) {
        $erro = 'Erro de segurança. Recarregue a página.';
    } else {
        $acao = $_POST['acao'] ?? 'dados';
        try {
            if ($acao === 'dados') {
                $nome = trim((string)($_POST['nome'] ?? ''));
                $telefone = trim((string)($_POST['telefone'] ?? ''));
                $nif = trim((string)($_POST['nif'] ?? ''));
                if ($nome === '' || mb_strlen($nome, 'UTF-8') > 120) {
                    throw new InvalidArgumentException('Indique um nome válido (máx. 120 caracteres).');
                }
                if (strlen($telefone) > 30 || strlen($nif) > 30) {
                    throw new InvalidArgumentException('Telefone ou NIF demasiado longo.');
                }
                $telDb = $telefone !== '' ? $telefone : null;
                $nifDb = $nif !== '' ? $nif : null;

                $stmt = $conn->prepare("UPDATE clientes SET nome = ?, telefone = ?, nif = ?, data_atualizacao = NOW() WHERE id = ?");
                $stmt->bind_param('sssi', $nome, $telDb, $nifDb, $cliente['id']);
                $stmt->execute();
                $stmt->close();
                $_SESSION['cliente_nome'] = $nome;
                $_SESSION['flash_dados_cliente'] = ['tipo' => 'sucesso', 'msg' => 'Dados atualizados com sucesso.'];
                header('Location: /minha-conta/dados');
                exit;
            } elseif ($acao === 'password') {
                if (!rate_limit_allow('alterar_password_cliente', 5, 600, (string)$cliente['id'])) {
                    throw new InvalidArgumentException('Demasiadas tentativas. Aguarde alguns minutos e tente novamente.');
                }
                $atual = is_string($_POST['password_atual'] ?? null) ? $_POST['password_atual'] : '';
                $nova = is_string($_POST['password_nova'] ?? null) ? $_POST['password_nova'] : '';
                $confirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';
                if (!password_verify($atual, $cliente['password_hash'])) {
                    throw new InvalidArgumentException('A palavra-passe atual não está correta.');
                }
                if (strlen($nova) < 8) {
                    throw new InvalidArgumentException('A nova palavra-passe deve ter pelo menos 8 caracteres.');
                }
                if (strlen($nova) > 72) {
                    throw new InvalidArgumentException('A nova palavra-passe não pode exceder 72 caracteres.');
                }
                if ($nova !== $confirm) {
                    throw new InvalidArgumentException('As novas palavras-passe não coincidem.');
                }
                $hash = password_hash($nova, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE clientes SET password_hash = ?, data_atualizacao = NOW() WHERE id = ?");
                $stmt->bind_param('si', $hash, $cliente['id']);
                $stmt->execute();
                $stmt->close();
                log_seguranca("Palavra-passe alterada na conta pelo cliente #{$cliente['id']} ({$cliente['email']}).", 'minha-conta-dados.php');
                require_once __DIR__ . '/admin/includes/email_handler.php';
                enviarEmailTemplate('password_alterada', (string)$cliente['email'], (string)$cliente['nome'], [
                    '{nome_cliente}' => (string)$cliente['nome'],
                ]);
                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['flash_dados_cliente'] = ['tipo' => 'sucesso', 'msg' => 'Palavra-passe atualizada com sucesso.'];
                header('Location: /minha-conta/dados');
                exit;
            }
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
        } catch (Throwable $e) {
            log_exception($e, 'minha-conta-dados.php');
            $erro = 'Ocorreu um erro interno. Tente novamente.';
        }
    }
}

$titulo_pagina = 'Dados Pessoais';
include __DIR__ . '/templates/header.php';
?>

<main class="cliente-area-page">
    <div class="cliente-shell">
        <?php
        $active_page = 'dados';
        include __DIR__ . '/templates/cliente-sidebar.php';
        ?>
        <section class="cliente-content">
            <div class="cliente-page-head">
                <h1>Dados pessoais</h1>
                <p>Atualiza os dados usados para preencher o checkout.</p>
            </div>

            <?php if ($erro): ?>
                <script>document.addEventListener('DOMContentLoaded', () => mostrarPopup(<?php echo json_encode($erro, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, "erro"));</script>
            <?php endif; ?>
            <?php if ($sucesso): ?>
                <script>document.addEventListener('DOMContentLoaded', () => mostrarPopup(<?php echo json_encode($sucesso, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, "sucesso"));</script>
            <?php endif; ?>

            <div class="cliente-panel">
                <div class="cliente-panel-head"><h2>Perfil</h2></div>
                <div class="cliente-form-wrap">
                    <form method="post" class="cliente-form-grid">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="acao" value="dados">
                        <label for="d-nome">
                            <span>Nome completo</span>
                            <?php echo ui_input('nome', ['id' => 'd-nome', 'required' => true, 'value' => $cliente['nome'], 'attrs' => ['maxlength' => '120']]); ?>
                        </label>
                        <label for="d-email">
                            <span>Email</span>
                            <?php echo ui_input('', ['type' => 'email', 'id' => 'd-email', 'value' => $cliente['email'], 'disabled' => true]); ?>
                        </label>
                        <label for="d-telefone">
                            <span>Telefone</span>
                            <?php echo ui_input('telefone', ['type' => 'tel', 'id' => 'd-telefone', 'value' => $cliente['telefone'] ?? '', 'attrs' => ['maxlength' => '30']]); ?>
                        </label>
                        <label for="d-nif">
                            <span>NIF</span>
                            <?php echo ui_input('nif', ['id' => 'd-nif', 'value' => $cliente['nif'] ?? '', 'attrs' => ['maxlength' => '30']]); ?>
                        </label>
                        <?php echo ui_button('Guardar dados', ['type' => 'submit', 'class' => 'button']); ?>
                    </form>
                </div>
            </div>

            <div class="cliente-panel">
                <div class="cliente-panel-head"><h2>Alterar palavra-passe</h2></div>
                <div class="cliente-form-wrap">
                    <form method="post" class="cliente-form-grid">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="acao" value="password">
                        <label for="d-pw-atual" style="grid-column:1/-1;">
                            <span>Palavra-passe atual</span>
                            <span class="password-wrapper">
                                <?php echo ui_input('password_atual', ['type' => 'password', 'id' => 'd-pw-atual', 'required' => true, 'autocomplete' => 'current-password']); ?>
                                <button type="button" class="toggle-senha" aria-label="Mostrar ou ocultar palavra-passe" data-target="d-pw-atual" style="background:none;border:none;padding:0;"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                            </span>
                        </label>
                        <label for="d-pw-nova">
                            <span>Nova palavra-passe</span>
                            <span class="password-wrapper">
                                <?php echo ui_input('password_nova', ['type' => 'password', 'id' => 'd-pw-nova', 'required' => true, 'autocomplete' => 'new-password', 'attrs' => ['maxlength' => '72']]); ?>
                                <button type="button" class="toggle-senha" aria-label="Mostrar ou ocultar palavra-passe" data-target="d-pw-nova" style="background:none;border:none;padding:0;"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                            </span>
                        </label>
                        <label for="d-pw-confirm">
                            <span>Confirmar nova</span>
                            <span class="password-wrapper">
                                <?php echo ui_input('password_confirm', ['type' => 'password', 'id' => 'd-pw-confirm', 'required' => true, 'autocomplete' => 'new-password', 'attrs' => ['maxlength' => '72']]); ?>
                                <button type="button" class="toggle-senha" aria-label="Mostrar ou ocultar palavra-passe" data-target="d-pw-confirm" style="background:none;border:none;padding:0;"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                            </span>
                        </label>
                        <?php echo ui_button('Alterar palavra-passe', ['type' => 'submit', 'class' => 'button']); ?>
                    </form>
                </div>
            </div>

            <div class="cliente-panel cliente-panel-danger">
                <div class="cliente-panel-head"><h2>Zona de perigo</h2></div>
                <div class="cliente-form-wrap">
                    <p class="danger-zone-text">Ao apagares a conta, todos os teus dados pessoais e moradas são removidos permanentemente. As tuas encomendas ficam guardadas para que possas continuar a acompanhá-las.</p>
                    <?php echo ui_button('Apagar conta', ['id' => 'btn-abrir-apagar-conta', 'class' => 'button delete-btn']); ?>
                </div>
            </div>
        </section>
    </div>
</main>

<script>
window.CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<div id="modal-apagar-conta" class="modal-confirmacao" role="dialog" aria-modal="true" aria-labelledby="modal-apagar-conta-titulo">
    <div class="modal-confirmacao-conteudo" style="max-width:420px;">
        <h3 id="modal-apagar-conta-titulo">Apagar conta</h3>
        <p class="danger-zone-text">Esta ação é permanente e não pode ser desfeita. As tuas encomendas ficam guardadas.</p>
        <div class="cliente-form-grid" style="margin-top:16px;">
            <label for="apagar-conta-password" style="grid-column:1/-1;">
                <span>Palavra-passe atual</span>
                <?php echo ui_input('', ['type' => 'password', 'id' => 'apagar-conta-password', 'placeholder' => 'Confirma a tua palavra-passe', 'autocomplete' => 'current-password']); ?>
            </label>
        </div>
        <div id="apagar-conta-erro" class="dados-inline-erro" role="alert"></div>
        <div class="modal-confirmacao-acoes">
            <?php
            echo ui_button('Cancelar', ['id' => 'apagar-conta-cancelar']);
            echo ui_button('Apagar conta', ['id' => 'apagar-conta-confirmar', 'variant' => 'danger']);
            ?>
        </div>
    </div>
</div>

<script>
(function () {
    const btnAbrir     = document.getElementById('btn-abrir-apagar-conta');
    const modal        = document.getElementById('modal-apagar-conta');
    const btnCancelar  = document.getElementById('apagar-conta-cancelar');
    const btnConfirmar = document.getElementById('apagar-conta-confirmar');
    const inputPw      = document.getElementById('apagar-conta-password');
    const erroEl       = document.getElementById('apagar-conta-erro');

    function abrir() {
        inputPw.value = '';
        erroEl.textContent = '';
        modal.classList.add('ativo');
        setTimeout(() => inputPw.focus(), 50);
    }
    function fechar() {
        modal.classList.remove('ativo');
    }

    btnAbrir.addEventListener('click', abrir);
    btnCancelar.addEventListener('click', fechar);
    modal.addEventListener('click', e => { if (e.target === modal) fechar(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.classList.contains('ativo')) {
            fechar();
        }
    });
    inputPw.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            btnConfirmar.click();
        }
    });

    btnConfirmar.addEventListener('click', function () {
        erroEl.textContent = '';
        btnConfirmar.disabled = true;
        btnConfirmar.textContent = 'A apagar...';

        fetch('/ajax_apagar_conta_cliente.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: window.CSRF_TOKEN,
                password: inputPw.value
            })
        })
        .then(async r => {
            let data = {};
            try { data = await r.json(); } catch (_) {}
            if (r.ok && data.sucesso) {
                window.location.href = data.redirect || '/';
            } else {
                erroEl.textContent = data.mensagem || 'Erro desconhecido.';
                btnConfirmar.disabled = false;
                btnConfirmar.textContent = 'Apagar conta';
            }
        })
        .catch(() => {
            erroEl.textContent = 'Erro de ligação.';
            btnConfirmar.disabled = false;
            btnConfirmar.textContent = 'Apagar conta';
        });
    });
}());
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
