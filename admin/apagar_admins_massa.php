<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit('Acesso inválido.'); }
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    exit('Não autorizado.');
}
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { exit('CSRF inválido.'); }

$ids = array_values(array_unique(array_filter(array_map('intval', $_POST['ids'] ?? []), fn($id) => $id > 0)));

if (empty($ids)) {
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Nenhum administrador selecionado.'];
    header('Location: listar_admins.php'); exit;
}

$minha_role = $_SESSION['admin_role'];

$stmt = $conn->prepare("SELECT id, role FROM administradores WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();
$admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Contar superadmins e devs totais para proteger o último
$totalSA = (int)$conn->query("SELECT COUNT(*) FROM administradores WHERE role = 'superadmin'")->fetch_row()[0];
$totalDev = (int)$conn->query("SELECT COUNT(*) FROM administradores WHERE role = 'desenvolvedor'")->fetch_row()[0];

$ids_a_apagar = [];
foreach ($admins as $admin) {
    if ($admin['role'] === 'desenvolvedor' && $minha_role !== 'desenvolvedor') continue;
    if ($admin['role'] === 'superadmin' && $totalSA <= 1) continue;
    if ($admin['role'] === 'desenvolvedor' && $totalDev <= 1) continue;
    $ids_a_apagar[] = $admin['id'];
    if ($admin['role'] === 'superadmin') $totalSA--;
    if ($admin['role'] === 'desenvolvedor') $totalDev--;
}

if (empty($ids_a_apagar)) {
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Nenhum administrador pôde ser removido (proteções de segurança ativas).'];
    header('Location: listar_admins.php'); exit;
}

$stmtDel = $conn->prepare("DELETE FROM administradores WHERE id IN (" . implode(',', array_fill(0, count($ids_a_apagar), '?')) . ")");
$stmtDel->bind_param(str_repeat('i', count($ids_a_apagar)), ...$ids_a_apagar);
$stmtDel->execute();
$removidos = $stmtDel->affected_rows;
$stmtDel->close();

$_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => $removidos . ' administrador(es) removido(s) com sucesso.'];
header('Location: listar_admins.php'); exit;
