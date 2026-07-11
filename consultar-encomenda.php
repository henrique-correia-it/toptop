<?php
require_once __DIR__ . '/config/session.php'; // Caminho relativo correto
include 'config/database.php';
require_once __DIR__ . '/config/cliente_auth.php';
require_once __DIR__ . '/config/csrf.php';

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $encomenda_id = filter_input(INPUT_POST, 'encomenda_id', FILTER_VALIDATE_INT);
    $cliente_email = filter_input(INPUT_POST, 'cliente_email', FILTER_VALIDATE_EMAIL);

    if (!csrf_from_post()) {
        $erro = "Sessão expirada. Recarrega a página e tenta novamente.";
    } elseif ($encomenda_id && $cliente_email) {
        $stmt = $conn->prepare("SELECT token FROM encomendas WHERE id = ? AND cliente_email = ?");
        $stmt->bind_param("is", $encomenda_id, $cliente_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($encomenda = $result->fetch_assoc()) {
            header("Location: estado_encomenda.php?id=" . $encomenda_id . "&token=" . $encomenda['token']);
            exit;
        } else {
            $erro = "Não foi encontrada nenhuma encomenda com essa combinação de dados.";
        }
        $stmt->close();
    } else {
        $erro = "Por favor, insira dados válidos.";
    }
}

$titulo_pagina = 'Consultar Encomenda';
$descricao_pagina = 'Acompanha a tua encomenda TopTop. Insere o número da encomenda e o email da compra para veres o estado da entrega.';
include 'templates/header.php';
?>

<main class="pagina-info info-premium">
    <div class="pagina-info-header">
        <p class="info-kicker">Acompanhamento</p>
        <h2>Consultar Encomenda</h2>
        <p>Insira o número da sua encomenda e o email utilizado na compra para aceder à página de acompanhamento.</p>
        <?php if (is_cliente_logged_in()): ?>
            <p><a href="/minha-conta/encomendas">Ver encomendas da minha conta</a></p>
        <?php endif; ?>
    </div>

    <div class="info-bloco info-bloco-estreito">
        <?php if ($erro): ?>
            <div class="auth-message error"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="consultar-encomenda.php">
            <?php echo csrf_input(); ?>
            <div class="form-group">
                <label for="encomenda_id">Número da Encomenda:</label>
                <input type="number" id="encomenda_id" name="encomenda_id" min="1" inputmode="numeric" placeholder="Ex: 123" required>
            </div>
            <div class="form-group">
                <label for="cliente_email">Email da Compra:</label>
                <input type="email" id="cliente_email" name="cliente_email" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="button add-btn">Consultar</button>
            </div>
        </form>
    </div>
</main>

<?php include 'templates/footer.php'; ?>
