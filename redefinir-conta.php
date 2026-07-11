<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/cliente_auth.php';


$titulo_pagina = 'Redefinir Palavra-passe';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$mensagem = '';
$tipo = 'erro';
$reset = null;
$perfil_token = null;

if ($token !== '') {
    // 1. Tentar como Cliente (usando hash sha256)
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare(
        "SELECT r.*, c.nome, c.email, 'cliente' as perfil
         FROM cliente_password_resets r
         JOIN clientes c ON c.id = r.cliente_id
         WHERE r.token_hash = ? AND r.expira_em > NOW() AND r.usado_em IS NULL
         LIMIT 1"
    );
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 2. Se não encontrou, tentar como Admin (token guardado como hash SHA-256)
    if (!$reset) {
        $adminTokenHash = hash('sha256', $token);
        $stmt = $conn->prepare(
            "SELECT email, 'admin' as perfil
             FROM password_resets
             WHERE token = ? AND expira > NOW()
             LIMIT 1"
        );
        $stmt->bind_param("s", $adminTokenHash);
        $stmt->execute();
        $reset = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($reset) {
            $perfil_token = 'admin';
            // Buscar nome do admin
            $stmt_user = $conn->prepare("SELECT username FROM administradores WHERE email = ?");
            $stmt_user->bind_param("s", $reset['email']);
            $stmt_user->execute();
            $row_user = $stmt_user->get_result()->fetch_assoc();
            $reset['nome'] = $row_user['username'] ?? 'Administrador';
            $stmt_user->close();
        }
    } else {
        $perfil_token = 'cliente';
    }
}

if (!$reset) {
    $mensagem = 'Link invalido ou expirado. Peca um novo link de recuperacao.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_from_post()) {
        $mensagem = 'Erro de seguranca. Recarregue a pagina e tente novamente.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        
        if (strlen($password) < 8) {
            $mensagem = 'A palavra-passe deve ter pelo menos 8 caracteres.';
        } elseif ($password !== $confirm) {
            $mensagem = 'As palavras-passe nao coincidem.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $conn->begin_transaction();
            try {
                if ($perfil_token === 'cliente') {
                    $stmt = $conn->prepare("UPDATE clientes SET password_hash = ?, failed_login_attempts = 0, last_login_attempt = NULL, data_atualizacao = NOW() WHERE id = ?");
                    $stmt->bind_param('si', $hash, $reset['cliente_id']);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare("UPDATE cliente_password_resets SET usado_em = NOW() WHERE id = ?");
                    $stmt->bind_param('i', $reset['id']);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Admin
                    $stmt_update = $conn->prepare("UPDATE administradores SET password_hash = ?, failed_login_attempts = 0, last_login_attempt = NULL WHERE email = ?");
                    $stmt_update->bind_param("ss", $hash, $reset['email']);
                    $stmt_update->execute();
                    $stmt_update->close();

                    $stmt_del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                    $stmt_del->bind_param("s", $reset['email']);
                    $stmt_del->execute();
                    $stmt_del->close();
                }
                
                $conn->commit();
                $mensagem = 'Palavra-passe atualizada com sucesso. Ja podes entrar.';
                $tipo = 'sucesso';
                $reset = null;
            } catch (Throwable $e) {
                $conn->rollback();
                log_app($e->getMessage(), 'ERROR', 'redefinir-conta.php');
                $mensagem = 'Nao foi possivel atualizar a palavra-passe.';
            }
        }
    }
}

include __DIR__ . '/templates/header.php';
?>

<main style="background-color: transparent;">
    <div class="login-container">
        <div class="login-card">
            <h2>Redefinir Palavra-passe</h2>
            <?php if ($tipo === 'sucesso'): ?>
                <p class="cliente-auth-copy" style="text-align:center;">Já podes entrar com a tua nova palavra-passe.</p>
            <?php elseif ($mensagem && !$reset): ?>
                <p class="cliente-auth-copy" style="text-align:center; color:var(--cor-erro);">Link inválido ou expirado.</p>
            <?php endif; ?>

            <?php if ($reset && $tipo !== 'sucesso'): ?>
            <p class="cliente-auth-copy">Cria uma nova palavra-passe para <?php echo htmlspecialchars($reset['email']); ?>.</p>
            <form method="post" action="/redefinir-conta">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="password">Nova palavra-passe</label>
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
                <input type="submit" value="Guardar palavra-passe">
            </form>
            <?php endif; ?>

            <div class="form-actions-footer">
                <a href="/entrar" class="link-recuperar">Voltar ao login</a>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($mensagem)): ?>
    mostrarPopup(<?php echo json_encode($mensagem); ?>, '<?php echo $tipo === 'sucesso' ? 'sucesso' : 'erro'; ?>');
    <?php endif; ?>

});
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
