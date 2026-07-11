<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

// --- INÍCIO DA CORREÇÃO CSRF ---

// 1. Apenas aceitar pedidos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Acesso inválido.');
}

// 2. Validar o token CSRF
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    exit('Erro de validação de segurança. Ação não permitida.');
}
// --- FIM DA CORREÇÃO CSRF ---


// --- (PASSOS DE SEGURANÇA CRUCIAIS) ---

// 1. Apenas superadmins e desenvolvedores podem apagar
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    header("Location: listar_admins.php?erro=1"); // Erro: Sem permissão
    exit;
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

// 4. Impede que o último super-admin seja apagado
$stmt_count = $conn->prepare("SELECT role FROM administradores WHERE id = ?");
$stmt_count->bind_param("i", $id_a_apagar);
$stmt_count->execute();
$result_role = $stmt_count->get_result()->fetch_assoc();
$stmt_count->close();

if ($result_role) {
    if ($result_role['role'] === 'desenvolvedor') {
        // Proteção: Apenas desenvolvedores podem apagar desenvolvedores
        if ($_SESSION['admin_role'] !== 'desenvolvedor') {
            header("Location: listar_admins.php?erro=protecao_dev");
            exit;
        }
        
        // Não permitir apagar o último desenvolvedor
        $stmt_total = $conn->prepare("SELECT COUNT(id) AS total FROM administradores WHERE role = 'desenvolvedor'");
        $stmt_total->execute();
        $total_devs = $stmt_total->get_result()->fetch_assoc()['total'];
        $stmt_total->close();
        if ($total_devs <= 1) {
            header("Location: listar_admins.php?erro=ultimo_dev");
            exit;
        }
    } elseif ($result_role['role'] === 'superadmin') {
        $stmt_total = $conn->prepare("SELECT COUNT(id) AS total FROM administradores WHERE role = 'superadmin'");
        $stmt_total->execute();
        $total_superadmins = $stmt_total->get_result()->fetch_assoc()['total'];
        $stmt_total->close();
        
        if ($total_superadmins <= 1) {
            header("Location: listar_admins.php?erro=3"); // Erro: Não pode apagar o último super-admin
            exit;
        }
    }
}


// --- EXECUÇÃO DA ELIMINAÇÃO ---

// Se todas as verificações de segurança passarem, apaga o utilizador
$stmt_delete = $conn->prepare("DELETE FROM administradores WHERE id = ?");
$stmt_delete->bind_param("i", $id_a_apagar);

if ($stmt_delete->execute()) {
    header("Location: listar_admins.php?sucesso=3"); // Sucesso: Utilizador apagado
    exit;
} else {
    header("Location: listar_admins.php?erro=4"); // Erro: Falha na base de dados
    exit;
}
$stmt_delete->close();

?>
