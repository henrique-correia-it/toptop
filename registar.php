<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/cliente_auth.php';


if (is_cliente_logged_in()) {
    header('Location: /minha-conta');
    exit;
}

$titulo_pagina = 'Criar Conta';
$erro = '';
$nome = '';
$email = '';
$telefone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_from_post()) {
        $erro = 'Erro de seguranca. Recarregue a pagina e tente novamente.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $email = customer_clean_email($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $nif = trim($_POST['nif'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $marketing = !empty($_POST['aceita_marketing']) ? 1 : 0;

        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Preencha o nome e um email valido.';
        } elseif (strlen($password) < 8) {
            $erro = 'A palavra-passe deve ter pelo menos 8 caracteres.';
        } elseif ($password !== $passwordConfirm) {
            $erro = 'As palavras-passe nao coincidem.';
        } elseif (customer_find_by_email($conn, $email)) {
            $erro = 'Ja existe uma conta com esse email. Entre ou recupere a palavra-passe.';
        } else {
            try {
                $customerId = customer_create($conn, $nome, $email, $password, $telefone ?: null, $nif ?: null);

                // Conta criada como inativa até verificação de email
                $stmt = $conn->prepare("UPDATE clientes SET ativo = 0" . ($marketing ? ", aceita_marketing = 1" : "") . " WHERE id = ?");
                $stmt->bind_param('i', $customerId);
                $stmt->execute();
                $stmt->close();

                $token = customer_create_email_verification_token($conn, $customerId);

                $env_vars = file_exists(__DIR__ . '/.env') ? parse_ini_file(__DIR__ . '/.env') : [];
                $base_url = rtrim($env_vars['APP_URL'] ?? 'https://toptop.pt', '/');
                $link = $base_url . '/verificar-email.php?token=' . urlencode($token);

                require_once __DIR__ . '/admin/includes/email_handler.php';
                enviarEmailTemplate('verificacao_email', $email, $nome, [
                    '{nome_cliente}'    => htmlspecialchars($nome),
                    '{link_verificacao}' => $link,
                ]);

                header('Location: /registar?pendente=1');
                exit;
            } catch (Throwable $e) {
                log_app($e->getMessage(), 'ERROR', 'registar.php');
                $erro = 'Nao foi possivel criar a conta. Tente novamente.';
            }
        }
    }
}

include __DIR__ . '/templates/header.php';
?>

<main style="background-color: transparent;">
    <div class="login-container">
        <div class="login-card">

            <?php if (!empty($_GET['pendente'])): ?>
                <h2>Verifica o teu email</h2>
                <p class="cliente-auth-copy">Enviámos um link de confirmação para <strong><?php echo htmlspecialchars($email); ?></strong>. Abre o email e clica no link para ativares a conta.</p>
                <div class="form-actions-footer" style="margin-top:1.5rem;">
                    <a href="/entrar" class="link-recuperar">Já ativei — entrar</a>
                </div>
            <?php else: ?>

            <h2>Criar Conta</h2>
            <p class="cliente-auth-copy">Guarda os teus dados e acompanha as tuas encomendas num so lugar.</p>


            <form method="post" action="/registar">
                <?php echo csrf_input(); ?>
                <div class="form-group">
                    <label for="nome">Nome completo</label>
                    <input type="text" id="nome" name="nome" required autocomplete="name" value="<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autocomplete="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="tel" id="telefone" name="telefone" autocomplete="tel" value="<?php echo htmlspecialchars($telefone, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label for="nif">NIF</label>
                    <input type="text" id="nif" name="nif" inputmode="numeric">
                </div>
                <div class="form-group">
                    <label for="password">Palavra-passe</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required autocomplete="new-password">
                        <span class="toggle-senha" data-target="password">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirmar palavra-passe</label>
                    <div class="password-wrapper">
                        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                        <span class="toggle-senha" data-target="password_confirm">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                </div>

                <div style="margin-top: 10px;">
                    <input type="submit" value="Criar conta">
                </div>
            </form>

            <div class="form-actions-footer">
                <a href="/entrar" class="link-recuperar">Ja tenho conta</a>
            </div>

            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($erro)): ?>
    mostrarPopup(<?php echo json_encode($erro); ?>, 'erro');
    <?php endif; ?>

});
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
