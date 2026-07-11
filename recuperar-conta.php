<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/cliente_auth.php';
require_once __DIR__ . '/admin/includes/email_handler.php';


$titulo_pagina = 'Recuperar Conta';
$mensagem = '';
$tipo = '';
$mostrarEscolhaPerfil = false;
$email_pendente = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_from_post()) {
        $mensagem = 'Erro de seguranca. Recarregue a pagina e tente novamente.';
        $tipo = 'erro';
    } else {
        $email = customer_clean_email($_POST['email'] ?? '');
        $perfil_escolhido = $_POST['perfil_escolhido'] ?? null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = 'Insira um email valido.';
            $tipo = 'erro';
        } else {
            // Se o perfil já foi escolhido ou se estamos no primeiro passo
            $customer = customer_find_by_email($conn, $email);
            
            // Verificar admin
            $stmt = $conn->prepare("SELECT id, username FROM administradores WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$perfil_escolhido && $customer && $admin) {
                $mostrarEscolhaPerfil = true;
                $email_pendente = $email;
            } else {
                $perfil = $perfil_escolhido ?: ($admin ? 'admin' : ($customer ? 'cliente' : null));

                if ($perfil === 'cliente' && $customer && (int)$customer['ativo'] === 1) {
                    try {
                        $token = customer_create_reset_token($conn, (int)$customer['id']);
                        $url = rtrim($base_url ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? '')), '/') . '/redefinir-conta?token=' . urlencode($token);
                        enviarEmailTemplate('recuperacao_cliente', $customer['email'], $customer['nome'], [
                            '{nome_cliente}' => $customer['nome'],
                            '{link_recuperacao}' => $url
                        ]);
                    } catch (Throwable $e) {
                        log_app($e->getMessage(), 'ERROR', 'recuperar-conta.php');
                    }
                } elseif ($perfil === 'admin' && $admin) {
                    try {
                        $token = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $token);
                        $expira = date('Y-m-d H:i:s', time() + 3600);

                        $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                        $stmt_delete->bind_param("s", $email);
                        $stmt_delete->execute();
                        $stmt_delete->close();

                        $stmt_token = $conn->prepare("INSERT INTO password_resets (email, token, expira) VALUES (?, ?, ?)");
                        $stmt_token->bind_param("sss", $email, $tokenHash, $expira);
                        $stmt_token->execute();
                        $stmt_token->close();

                        $link_recuperacao = rtrim($base_url ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? '')), '/') . "/redefinir-conta?token=" . $token;
                        
                        enviarEmailTemplate('recuperacao_admin', $email, $admin['username'], [
                            '{nome_admin}' => $admin['username'],
                            '{link_recuperacao}' => $link_recuperacao
                        ]);
                    } catch (Throwable $e) {
                        log_app($e->getMessage(), 'ERROR', 'recuperar-conta.php (admin)');
                    }
                }
                
                $mensagem = 'Se existir uma conta associada a esse email, receberas um link para recuperar a palavra-passe.';
                $tipo = 'sucesso';
            }
        }
    }
}

include __DIR__ . '/templates/header.php';
?>

<main style="background-color: transparent;">
    <div class="login-container">
        <div class="login-card">
            <h2>Recuperar Conta</h2>
            <?php if ($tipo === 'sucesso'): ?>
                <p class="cliente-auth-copy" style="text-align:center;">Verifica a tua caixa de entrada (e pasta de spam) para o link de recuperação.</p>
            <?php endif; ?>

            <?php if ($mostrarEscolhaPerfil): ?>
                <p class="cliente-auth-copy">Encontramos duas contas associadas a este email. Qual pretendes recuperar?</p>
                <form method="post" action="/recuperar-conta" class="perfil-choice-form">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email_pendente, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" name="perfil_escolhido" value="cliente">Conta de Cliente</button>
                    <button type="submit" name="perfil_escolhido" value="admin">Conta de Administrador</button>
                </form>
            <?php elseif ($tipo !== 'sucesso'): ?>
                <p class="cliente-auth-copy">Insere o email da tua conta para receberes um link de recuperacao.</p>
                <form method="post" action="/recuperar-conta">
                    <?php echo csrf_input(); ?>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required autocomplete="email" placeholder="O seu email">
                    </div>
                    <input type="submit" value="Enviar link">
                </form>
            <?php endif; ?>

            <div class="form-actions-footer">
                <a href="/entrar" class="link-recuperar">Voltar ao login</a>
            </div>
        </div>
    </div>
</main>

<script>
<?php if (!empty($mensagem) && $tipo === 'erro'): ?>
document.addEventListener('DOMContentLoaded', function() {
    mostrarPopup(<?php echo json_encode($mensagem); ?>, 'erro');
});
<?php elseif (!empty($mensagem) && $tipo === 'sucesso'): ?>
document.addEventListener('DOMContentLoaded', function() {
    mostrarPopup(<?php echo json_encode($mensagem); ?>, 'sucesso');
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
