<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/rate_limit.php';
require_once __DIR__ . '/config/cliente_auth.php';
require_once __DIR__ . '/admin/includes/email_handler.php';


$titulo_pagina = 'Recuperar Conta';
$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_from_post()) {
        $mensagem = 'Erro de segurança. Recarregue a página e tente novamente.';
        $tipo = 'erro';
    } elseif (!rate_limit_allow('recuperar_conta', 8, 600)) {
        $mensagem = 'Demasiados pedidos de recuperação. Aguarde alguns minutos e tente novamente.';
        $tipo = 'erro';
    } else {
        $email = customer_clean_email($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = 'Insira um email válido.';
            $tipo = 'erro';
        } else {
            $customer = customer_find_by_email($conn, $email);

            // Verificar admin
            $stmt = $conn->prepare("SELECT id, username FROM administradores WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($customer && (int)$customer['ativo'] === 1) {
                try {
                    $token = customer_create_reset_token($conn, (int)$customer['id']);
                    $url = rtrim($base_url, '/') . '/redefinir-conta?token=' . urlencode($token);
                    enviarEmailTemplate('recuperacao_cliente', $customer['email'], $customer['nome'], [
                        '{nome_cliente}' => htmlspecialchars($customer['nome'], ENT_QUOTES, 'UTF-8'),
                        '{link_recuperacao}' => $url
                    ]);
                } catch (Throwable $e) {
                    log_app($e->getMessage(), 'ERROR', 'recuperar-conta.php');
                }
            }

            if ($admin) {
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

                    $link_recuperacao = rtrim($base_url, '/') . '/redefinir-conta?token=' . urlencode($token);

                    enviarEmailTemplate('recuperacao_admin', $email, $admin['username'], [
                        '{nome_admin}' => htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8'),
                        '{link_recuperacao}' => $link_recuperacao
                    ]);
                } catch (Throwable $e) {
                    log_app($e->getMessage(), 'ERROR', 'recuperar-conta.php (admin)');
                }
            }

            $mensagem = 'Se existir uma conta associada a esse email, receberás um link para recuperar a palavra-passe.';
            $tipo = 'sucesso';
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
                <?php echo ui_alert('Verifica a tua caixa de entrada (e pasta de spam) para o link de recuperação.', 'success'); ?>
            <?php else: ?>
                <?php if ($tipo === 'erro' && $mensagem !== ''): ?>
                    <?php echo ui_alert($mensagem, 'danger'); ?>
                <?php endif; ?>
                <p class="cliente-auth-copy">Insere o email da tua conta para receberes um link de recuperação.</p>
                <form method="post" action="/recuperar-conta">
                    <?php echo csrf_input(); ?>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <?php echo ui_input('email', ['type' => 'email', 'id' => 'email', 'required' => true, 'autocomplete' => 'email', 'placeholder' => 'O seu email']); ?>
                    </div>
                    <?php echo ui_button('Enviar link', ['type' => 'submit', 'variant' => 'primary', 'full' => true]); ?>
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
