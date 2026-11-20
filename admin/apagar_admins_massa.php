<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Acesso inválido.'); }
if (!admin_has_role(['superadmin', 'desenvolvedor'])) {
    http_response_code(403);
    exit('Não autorizado.');
}
if (!csrf_from_post()) { http_response_code(403); exit('CSRF inválido.'); }

$raw_ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
$ids = array_values(array_unique(array_filter(array_map('intval', $raw_ids), fn($id) => $id > 0)));

if (empty($ids)) {
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Nenhum administrador selecionado.'];
    header('Location: listar_admins.php'); exit;
}

$minha_role = $_SESSION['admin_role'];
$meu_id = (int)($_SESSION['admin_id'] ?? 0);

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("SELECT id, role FROM administradores WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ") FOR UPDATE");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Bloquear e contar superadmins e devs totais para proteger o último
    $resSA = $conn->query("SELECT id FROM administradores WHERE role = 'superadmin' FOR UPDATE");
    $totalSA = $resSA ? $resSA->num_rows : 0;
    $resDev = $conn->query("SELECT id FROM administradores WHERE role = 'desenvolvedor' FOR UPDATE");
    $totalDev = $resDev ? $resDev->num_rows : 0;

    $ids_a_apagar = [];
    foreach ($admins as $admin) {
        if ((int)$admin['id'] === $meu_id) continue;
        if ($admin['role'] === 'desenvolvedor' && $minha_role !== 'desenvolvedor') continue;
        if ($admin['role'] === 'superadmin' && $totalSA <= 1) continue;
        if ($admin['role'] === 'desenvolvedor' && $totalDev <= 1) continue;
        $ids_a_apagar[] = (int)$admin['id'];
        if ($admin['role'] === 'superadmin') $totalSA--;
        if ($admin['role'] === 'desenvolvedor') $totalDev--;
    }

    if (empty($ids_a_apagar)) {
        $conn->rollback();
        $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Nenhum administrador pôde ser removido (proteções de segurança ativas).'];
        header('Location: listar_admins.php'); exit;
    }

    $stmtDel = $conn->prepare("DELETE FROM administradores WHERE id IN (" . implode(',', array_fill(0, count($ids_a_apagar), '?')) . ")");
    $stmtDel->bind_param(str_repeat('i', count($ids_a_apagar)), ...$ids_a_apagar);
    $stmtDel->execute();
    $removidos = $stmtDel->affected_rows;
    $stmtDel->close();

    $conn->commit();
    $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => $removidos . ' administrador(es) removido(s) com sucesso.'];
    header('Location: listar_admins.php'); exit;
} catch (\Throwable $e) {
    $conn->rollback();
    error_log('Erro em apagar_admins_massa.php: ' . $e->getMessage());
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Erro ao remover administradores.'];
    header('Location: listar_admins.php'); exit;
}
