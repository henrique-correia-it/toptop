<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/cliente_auth.php';

require_cliente();

$cliente = cliente_atual($conn);
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_from_post()) {
        $erro = 'Erro de seguranca. Recarregue a pagina.';
    } else {
        $acao = $_POST['acao'] ?? 'dados';
        try {
            if ($acao === 'dados') {
                $nome = trim($_POST['nome'] ?? '');
                $telefone = trim($_POST['telefone'] ?? '');
                $nif = trim($_POST['nif'] ?? '');
                $marketing = !empty($_POST['aceita_marketing']) ? 1 : 0;
                if ($nome === '') throw new InvalidArgumentException('O nome é obrigatório.');

                $stmt = $conn->prepare("UPDATE clientes SET nome = ?, telefone = ?, nif = ?, aceita_marketing = ?, data_atualizacao = NOW() WHERE id = ?");
                $stmt->bind_param('sssii', $nome, $telefone, $nif, $marketing, $cliente['id']);
                $stmt->execute();
                $stmt->close();
                $_SESSION['cliente_nome'] = $nome;
                $sucesso = 'Dados atualizados com sucesso.';
            } elseif ($acao === 'password') {
                $atual = $_POST['password_atual'] ?? '';
                $nova = $_POST['password_nova'] ?? '';
                $confirm = $_POST['password_confirm'] ?? '';
                if (!password_verify($atual, $cliente['password_hash'])) {
                    throw new InvalidArgumentException('A palavra-passe atual não está correta.');
                }
                if (strlen($nova) < 8) {
                    throw new InvalidArgumentException('A nova palavra-passe deve ter pelo menos 8 caracteres.');
                }
                if ($nova !== $confirm) {
                    throw new InvalidArgumentException('As novas palavras-passe não coincidem.');
                }
                $hash = password_hash($nova, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE clientes SET password_hash = ?, data_atualizacao = NOW() WHERE id = ?");
                $stmt->bind_param('si', $hash, $cliente['id']);
                $stmt->execute();
                $stmt->close();
                $sucesso = 'Palavra-passe atualizada com sucesso.';
            }
            $cliente = cliente_atual($conn);
        } catch (Throwable $e) {
            $erro = $e->getMessage();
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
                <script>document.addEventListener('DOMContentLoaded', () => mostrarPopup("<?php echo addslashes($erro); ?>", "erro"));</script>
            <?php endif; ?>
            <?php if ($sucesso): ?>
                <script>document.addEventListener('DOMContentLoaded', () => mostrarPopup("<?php echo addslashes($sucesso); ?>", "sucesso"));</script>
            <?php endif; ?>

            <div class="cliente-panel">
                <div class="cliente-panel-head"><h2>Perfil</h2></div>
                <div class="cliente-form-wrap">
                    <form method="post" class="cliente-form-grid">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="acao" value="dados">
                        <label for="d-nome">
                            <span>Nome completo</span>
                            <input type="text" id="d-nome" name="nome" required value="<?php echo htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label for="d-email">
                            <span>Email</span>
                            <input type="email" id="d-email" value="<?php echo htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
                        </label>
                        <label for="d-telefone">
                            <span>Telefone</span>
                            <input type="tel" id="d-telefone" name="telefone" value="<?php echo htmlspecialchars($cliente['telefone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label for="d-nif">
                            <span>NIF</span>
                            <input type="text" id="d-nif" name="nif" value="<?php echo htmlspecialchars($cliente['nif'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <button type="submit" class="button">Guardar dados</button>
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
                            <div class="password-wrapper">
                                <input type="password" id="d-pw-atual" name="password_atual" required autocomplete="current-password">
                                <span class="toggle-senha" data-target="d-pw-atual"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></span>
                            </div>
                        </label>
                        <label for="d-pw-nova">
                            <span>Nova palavra-passe</span>
                            <div class="password-wrapper">
                                <input type="password" id="d-pw-nova" name="password_nova" required autocomplete="new-password">
                                <span class="toggle-senha" data-target="d-pw-nova"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></span>
                            </div>
                        </label>
                        <label for="d-pw-confirm">
                            <span>Confirmar nova</span>
                            <div class="password-wrapper">
                                <input type="password" id="d-pw-confirm" name="password_confirm" required autocomplete="new-password">
                                <span class="toggle-senha" data-target="d-pw-confirm"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></span>
                            </div>
                        </label>
                        <button type="submit" class="button">Alterar palavra-passe</button>
                    </form>
                </div>
            </div>

            <div class="cliente-panel cliente-panel-danger">
                <div class="cliente-panel-head"><h2>Zona de perigo</h2></div>
                <div class="cliente-form-wrap">
                    <p class="danger-zone-text">Ao apagares a conta, todos os teus dados pessoais e moradas são removidos permanentemente. As tuas encomendas ficam guardadas para que possas continuar a acompanhá-las.</p>
                    <button type="button" id="btn-abrir-apagar-conta" class="button delete-btn">Apagar conta</button>
                </div>
            </div>
        </section>
    </div>
</main>

<script>
window.CSRF_TOKEN = "<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>";
</script>

<div id="modal-apagar-conta" class="modal-confirmacao">
    <div class="modal-confirmacao-conteudo" style="max-width:420px;">
        <h3>Apagar conta</h3>
        <p class="danger-zone-text">Esta ação é permanente e não pode ser desfeita. As tuas encomendas ficam guardadas.</p>
        <div class="cliente-form-grid" style="margin-top:16px;">
            <label for="apagar-conta-password" style="grid-column:1/-1;">
                <span>Palavra-passe atual</span>
                <input type="password" id="apagar-conta-password" placeholder="Confirma a tua palavra-passe" autocomplete="current-password">
            </label>
        </div>
        <div id="apagar-conta-erro" class="dados-inline-erro"></div>
        <div class="modal-confirmacao-acoes">
            <button type="button" class="button voltar-btn" id="apagar-conta-cancelar">Cancelar</button>
            <button type="button" class="button delete-btn" id="apagar-conta-confirmar">Apagar conta</button>
        </div>
    </div>
</div>

<script>
(function () {
    const btnAbrir    = document.getElementById('btn-abrir-apagar-conta');
    const modal       = document.getElementById('modal-apagar-conta');
    const btnCancelar = document.getElementById('apagar-conta-cancelar');
    const btnConfirmar= document.getElementById('apagar-conta-confirmar');
    const inputPw     = document.getElementById('apagar-conta-password');
    const erroEl      = document.getElementById('apagar-conta-erro');

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
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                window.location.href = data.redirect;
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
