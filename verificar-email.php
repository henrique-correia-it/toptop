<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/cliente_auth.php';

$token_raw = trim((string)($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? '')));
$erro = '';
$sucesso = false;
$token_valido = false;

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
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_from_post()) {
            $erro = 'Erro de segurança. Recarrega a página e tenta novamente.';
            $token_valido = true;
        } else {
            $cliente_id = (int)$row['cliente_id'];

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("DELETE FROM cliente_email_verifications WHERE id = ?");
                $stmt->bind_param('i', $row['id']);
                $stmt->execute();
                $consumido = $stmt->affected_rows === 1;
                $stmt->close();

                if (!$consumido) {
                    throw new RuntimeException('Token de verificação já utilizado.');
                }

                $stmt = $conn->prepare("UPDATE clientes SET ativo = 1 WHERE id = ?");
                $stmt->bind_param('i', $cliente_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                log_app($e->getMessage(), 'ERROR', 'verificar-email.php');
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
    } else {
        // Pedido GET válido: apenas mostra o botão de confirmação (protege contra scanners de email)
        $token_valido = true;
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
            <?php if ($token_valido): ?>
                <?php if (!empty($erro)): ?>
                    <?php echo ui_alert($erro, 'danger'); ?>
                <?php else: ?>
                    <p class="cliente-auth-copy" style="text-align:center;">Clica no botão abaixo para confirmar o teu email e ativar a tua conta.</p>
                <?php endif; ?>
                <form method="post" action="/verificar-email.php">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token_raw, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo ui_button('Confirmar a minha conta', ['type' => 'submit', 'variant' => 'primary', 'full' => true]); ?>
                </form>
            <?php else: ?>
                <?php echo ui_alert($erro, 'danger'); ?>
                <div class="form-actions-footer">
                    <a href="/registar" class="link-recuperar">Criar nova conta</a>
                    <a href="/entrar" class="link-recuperar">Entrar</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
<?php if (!empty($erro)): ?>
document.addEventListener('DOMContentLoaded', function() {
    mostrarPopup(<?php echo json_encode($erro, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'erro');
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
