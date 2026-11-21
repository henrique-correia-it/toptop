<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['sucesso' => false, 'mensagem' => 'Acesso negado.'];

// Acesso EXCLUSIVO a Desenvolvedores
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_SESSION['admin_logado'])
    || $_SESSION['admin_logado'] !== true
    || ($_SESSION['admin_role'] ?? '') !== 'desenvolvedor'
    || !csrf_from_post()) {
    http_response_code(403);
    echo json_encode($response);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$password_atual = (string)($_POST['current_password'] ?? '');
$admin_id       = (int)($_SESSION['admin_id'] ?? 0);

if ($admin_id <= 0 || $password_atual === '') {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Introduza a sua palavra-passe para confirmar.']);
    exit;
}

$stmt_admin = $conn->prepare("SELECT password_hash, role FROM administradores WHERE id = ? LIMIT 1");
$stmt_admin->bind_param('i', $admin_id);
$stmt_admin->execute();
$admin_atual = $stmt_admin->get_result()->fetch_assoc();
$stmt_admin->close();

if (
    !$admin_atual
    || $admin_atual['role'] !== 'desenvolvedor'
    || !password_verify($password_atual, $admin_atual['password_hash'])
) {
    if (function_exists('log_seguranca')) {
        log_seguranca('Reautenticacao recusada ao tentar alterar o modo do Stripe.', 'ajax_toggle_stripe.php');
    }
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Palavra-passe incorreta.']);
    exit;
}

unset($password_atual, $_POST['current_password']);

// Atualiza o modo do Stripe
if (isset($_POST['stripe_mode'])) {
    $mode = (string)$_POST['stripe_mode'];
    if (in_array($mode, ['test', 'live'], true)) {
        setLojaConfig('stripe_mode', $mode);
        if (function_exists('log_seguranca')) {
            log_seguranca('Modo Stripe alterado para ' . strtoupper($mode) . '.', 'ajax_toggle_stripe.php');
        }
        $response = ['sucesso' => true, 'modo_atual' => $mode];
    } else {
        http_response_code(400);
        $response['mensagem'] = 'Valor inválido para stripe_mode.';
    }
} else {
    http_response_code(400);
    $response['mensagem'] = 'Nenhum dado recebido.';
}

echo json_encode($response);
exit;
