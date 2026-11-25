<?php
require_once __DIR__ . '/config/session.php'; // Caminho relativo correto
include 'config/database.php';
require_once __DIR__ . '/config/cliente_auth.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/rate_limit.php';

$erro = '';
$encomenda_id_input = '';
$cliente_email_input = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $encomenda_id_input = trim((string)($_POST['encomenda_id'] ?? ''));
    $cliente_email_input = trim((string)($_POST['cliente_email'] ?? ''));
    $encomenda_id = filter_var($encomenda_id_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $cliente_email = filter_var($cliente_email_input, FILTER_VALIDATE_EMAIL);

    if (!csrf_from_post()) {
        $erro = "Sessão expirada. Recarrega a página e tenta novamente.";
    } elseif (!rate_limit_allow('consultar_encomenda', 15, 600)) {
        $erro = "Demasiadas consultas seguidas. Aguarde alguns minutos e tente novamente.";
    } elseif ($encomenda_id && $cliente_email) {
        $stmt = $conn->prepare("SELECT token FROM encomendas WHERE id = ? AND cliente_email = ?");
        $stmt->bind_param("is", $encomenda_id, $cliente_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($encomenda = $result->fetch_assoc()) {
            header("Location: /estado_encomenda?id=" . $encomenda_id . "&token=" . urlencode($encomenda['token']));
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
        <h1>Consultar Encomenda</h1>
        <p>Insira o número da sua encomenda e o email utilizado na compra para aceder à página de acompanhamento.</p>
        <?php if (is_cliente_logged_in()): ?>
            <p><a href="/minha-conta/encomendas">Ver encomendas da minha conta</a></p>
        <?php endif; ?>
    </div>

    <div class="info-bloco info-bloco-estreito">
        <?php if ($erro): ?>
            <?php echo ui_alert($erro, 'danger'); ?>
        <?php endif; ?>

        <form method="POST" action="/consultar-encomenda">
            <?php echo csrf_input(); ?>
            <div class="form-group">
                <label for="encomenda_id">Número da Encomenda:</label>
                <?php echo ui_input('encomenda_id', ['type' => 'number', 'id' => 'encomenda_id', 'min' => '1', 'inputmode' => 'numeric', 'placeholder' => 'Ex: 123', 'value' => $encomenda_id_input, 'required' => true]); ?>
            </div>
            <div class="form-group">
                <label for="cliente_email">Email da Compra:</label>
                <?php echo ui_input('cliente_email', ['type' => 'email', 'id' => 'cliente_email', 'value' => $cliente_email_input, 'autocomplete' => 'email', 'required' => true]); ?>
            </div>
            <div class="form-actions">
                <?php echo ui_button('Consultar', ['type' => 'submit', 'class' => 'button add-btn', 'full' => true]); ?>
            </div>
        </form>
    </div>
</main>

<?php include 'templates/footer.php'; ?>
