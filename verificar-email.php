<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cliente_auth.php';

$token_raw = trim($_GET['token'] ?? '');
$erro = '';
$sucesso = false;

if ($token_raw === '') {
    $erro = 'Link inválido.';
} else {
    $hash = hash('sha256', $token_raw);

    $stmt = $conn->prepare(
        "SELECT v.id, v.cliente_id, v.expira_em
         FROM cliente_email_verifications v
         JOIN clientes c ON c.id = v.cliente_id
         WHERE v.token_hash = ? AND c.ativo = 0
         LIMIT 1"
    );
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $erro = 'Link inválido ou já utilizado.';
    } elseif (strtotime($row['expira_em']) < time()) {
        $erro = 'Este link expirou. Cria uma nova conta ou contacta o suporte.';
    } else {
        $cliente_id = (int)$row['cliente_id'];

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE clientes SET ativo = 1 WHERE id = ?");
            $stmt->bind_param('i', $cliente_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM cliente_email_verifications WHERE id = ?");
            $stmt->bind_param('i', $row['id']);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            $erro = 'Erro interno. Tenta novamente mais tarde.';
        }

        if (!$erro) {
            $customer = customer_find_by_id($conn, $cliente_id);
            if ($customer) {
                login_cliente_session($customer);
            }
            $sucesso = true;
        }
    }
}

if ($sucesso) {
    header('Location: /minha-conta?verificado=1');
    exit;
}

$titulo_pagina = 'Verificar Email';
include __DIR__ . '/templates/header.php';
?>

<main>
    <div class="login-container">
        <div class="login-card">
            <h2>Verificação de email</h2>
            <p class="cliente-auth-copy" style="text-align:center; color:var(--cor-erro);"><?php echo htmlspecialchars($erro); ?></p>
            <div class="form-actions-footer">
                <a href="/registar" class="link-recuperar">Criar nova conta</a>
                <a href="/entrar" class="link-recuperar">Entrar</a>
            </div>
        </div>
    </div>
</main>

<script>
<?php if (!empty($erro)): ?>
document.addEventListener('DOMContentLoaded', function() {
    mostrarPopup(<?php echo json_encode($erro); ?>, 'erro');
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
