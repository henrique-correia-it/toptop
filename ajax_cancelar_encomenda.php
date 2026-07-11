<?php
require_once __DIR__ . '/config/session.php';
include 'config/database.php';
require_once __DIR__ . '/config/cliente_auth.php';
// Inclui o gestor de e-mails para podermos enviar a notificação
include 'admin/includes/email_handler.php';

// --- VALIDAÇÕES DE SEGURANÇA (permanecem iguais) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: consultar-encomenda.php?erro=csrf");
    exit;
}

$encomenda_id = filter_input(INPUT_POST, 'encomenda_id', FILTER_VALIDATE_INT);
$cliente_email = filter_input(INPUT_POST, 'cliente_email', FILTER_VALIDATE_EMAIL);
$cliente_cancelar = is_cliente_logged_in() ? cliente_atual($conn) : null;

if (!$encomenda_id || (!$cliente_email && !$cliente_cancelar)) {
    header("Location: consultar-encomenda.php?erro=dados");
    exit;
}

// --- INÍCIO DA CORREÇÃO ---
// VAMOS BUSCAR OS DADOS DA ENCOMENDA ANTES DE A MODIFICAR
try {
    if ($cliente_cancelar) {
        $stmt_dados = $conn->prepare("SELECT * FROM encomendas WHERE id = ? AND cliente_id = ?");
        $cliente_cancelar_id = (int)$cliente_cancelar['id'];
        $stmt_dados->bind_param("ii", $encomenda_id, $cliente_cancelar_id);
    } else {
        $stmt_dados = $conn->prepare("SELECT * FROM encomendas WHERE id = ? AND cliente_email = ?");
        $stmt_dados->bind_param("is", $encomenda_id, $cliente_email);
    }
    $stmt_dados->execute();
    $encomenda_para_email = $stmt_dados->get_result()->fetch_assoc();
    $stmt_dados->close();

    if (!$encomenda_para_email) {
        throw new Exception("Encomenda não encontrada ou não pertence a este cliente.");
    }

    if (!in_array($encomenda_para_email['estado'], ['pendente', 'a aguardar pagamento', 'pagamento na entrega'])) {
        throw new Exception("Esta encomenda já não pode ser cancelada online.");
    }
} catch (Exception $e) {
    header("Location: consultar-encomenda.php?erro=" . urlencode($e->getMessage()));
    exit;
}
// --- FIM DA CORREÇÃO ---


try {
    $conn->begin_transaction();

    // 1. Repor o stock
    $stmt_itens = $conn->prepare("SELECT variacao_id, quantidade FROM encomenda_itens WHERE encomenda_id = ?");
    $stmt_itens->bind_param("i", $encomenda_id);
    $stmt_itens->execute();
    $itens_para_repor = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_itens->close();

    $stmt_repor = $conn->prepare("UPDATE produto_variacoes SET quantidade = quantidade + ? WHERE id = ?");
    foreach ($itens_para_repor as $item) {
        if ($item['variacao_id']) {
             $stmt_repor->bind_param("ii", $item['quantidade'], $item['variacao_id']);
             $stmt_repor->execute();
             
             // --- INÍCIO DA CORREÇÃO DE STOCK ---
             if ($stmt_repor->affected_rows === 0) {
                 // Força uma exceção se a atualização falhar
                 throw new Exception("Erro de stock: A variação #{$item['variacao_id']} não foi encontrada ao tentar repor o stock. Erro MySQL: " . $conn->error);
             }
             // --- FIM DA CORREÇÃO DE STOCK ---
        }
    }
    $stmt_repor->close();

    // 2. Atualizar o estado da encomenda para "cancelada"
    $stmt_cancelar = $conn->prepare("UPDATE encomendas SET estado = 'cancelada' WHERE id = ?");
    $stmt_cancelar->bind_param("i", $encomenda_id);
    $stmt_cancelar->execute();
    $stmt_cancelar->close();
    
    // 3. Se tudo correu bem, confirmar as alterações
    $conn->commit();

    // --- INÍCIO DA CORREÇÃO ---
    // 4. Enviar o e-mail de notificação de cancelamento
    try {
        enviarEmailEncomenda('cancelada', $encomenda_para_email);
    } catch (Exception $e) {
        log_email("Falha ao enviar email de cancelamento para encomenda #{$encomenda_id}: " . $e->getMessage(), 'ajax_cancelar_encomenda.php');
        error_log("Falha ao enviar e-mail de cancelamento para encomenda #" . $encomenda_id . ": " . $e->getMessage());
    }
    // --- FIM DA CORREÇÃO ---

    // Redireciona para a página de consulta com uma mensagem de sucesso
    // (Esta parte pode ser ajustada para mostrar um popup em vez de um parâmetro de URL)
    $redirect_url = 'estado_encomenda.php?id=' . $encomenda_id . '&token=' . $encomenda_para_email['token'] . '&sucesso=cancelada';
    header("Location: " . $redirect_url);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    log_app($e->getMessage(), 'ERROR', 'ajax_cancelar_encomenda.php enc#' . ($encomenda_id ?? 0));
    header("Location: consultar-encomenda.php?erro=" . urlencode($e->getMessage()));
    exit;
}
?>
