<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

// 1. Apenas gestores de admin (superadmin / dev no painel admin) podem apagar contas da loja
if (!is_admin_manager()) {
    http_response_code(403);
    header("Location: listar_admins.php?erro=1");
    exit;
}

// 2. Apenas aceitar pedidos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Acesso inválido.');
}

// 3. Validar o token CSRF
if (!csrf_from_post()) {
    http_response_code(403);
    exit('Erro de validação de segurança. Ação não permitida.');
}

// 2. Valida o ID recebido (agora do POST)
$id_a_apagar = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id_a_apagar) {
    header("Location: listar_admins.php");
    exit;
}

// 3. Impede que um utilizador se apague a si mesmo
if ($id_a_apagar == $_SESSION['admin_id']) {
    header("Location: listar_admins.php?erro=2"); // Erro: Não se pode apagar a si mesmo
    exit;
}

// 4. Verificação atómica (transação + FOR UPDATE) para proteger o último superadmin e contas fora da loja
$conn->begin_transaction();
try {
    $stmt_count = $conn->prepare("SELECT role FROM administradores WHERE id = ? FOR UPDATE");
    $stmt_count->bind_param("i", $id_a_apagar);
    $stmt_count->execute();
    $result_role = $stmt_count->get_result()->fetch_assoc();
    $stmt_count->close();

    if ($result_role) {
        if (!in_array(($result_role['role'] ?? ''), ['admin', 'superadmin'], true)) {
            // Contas fora dos roles de loja nunca são geridas ou apagadas através do /admin
            $conn->rollback();
            header("Location: listar_admins.php?erro=1");
            exit;
        } elseif ($result_role['role'] === 'superadmin') {
            $stmt_total = $conn->prepare("SELECT id FROM administradores WHERE role = 'superadmin' FOR UPDATE");
            $stmt_total->execute();
            $total_superadmins = $stmt_total->get_result()->num_rows;
            $stmt_total->close();
            
            if ($total_superadmins <= 1) {
                $conn->rollback();
                header("Location: listar_admins.php?erro=3"); // Erro: Não pode apagar o último super-admin
                exit;
            }
        }
    }

    // --- EXECUÇÃO DA ELIMINAÇÃO ---
    $stmt_delete = $conn->prepare("DELETE FROM administradores WHERE id = ?");
    $stmt_delete->bind_param("i", $id_a_apagar);
    $ok = $stmt_delete->execute();
    $stmt_delete->close();

    if ($ok) {
        $conn->commit();
        log_seguranca("Administrador #{$id_a_apagar} eliminado.", 'apagar_admin.php');
        header("Location: listar_admins.php?sucesso=3"); // Sucesso: Utilizador apagado
        exit;
    }

    $conn->rollback();
    header("Location: listar_admins.php?erro=4"); // Erro: Falha na base de dados
    exit;
} catch (\Throwable $e) {
    $conn->rollback();
    log_exception($e, 'apagar_admin.php');
    header("Location: listar_admins.php?erro=4");
    exit;
}

?>
