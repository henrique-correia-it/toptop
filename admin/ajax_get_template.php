<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json; charset=UTF-8');

if (!is_admin_logged_in()) {
    http_response_code(403);
    echo json_encode(null);
    exit;
}

$key = trim((string)($_GET['key'] ?? ''));
if ($key === '') {
    echo json_encode(null);
    exit;
}

$system_keys = [
    'verificacao_email',
    'recuperacao_cliente',
    'recuperacao_admin',
    'password_alterada',
    'conta_eliminada',
    'nova_encomenda_loja',
    'novo_contacto_loja',
];

if (in_array($key, $system_keys, true)) {
    http_response_code(403);
    echo json_encode(null);
    exit;
}

include '../config/database.php';

$has_scope_col = ($colRes = $conn->query("SHOW COLUMNS FROM email_templates LIKE 'scope'")) && $colRes->num_rows > 0;
if ($has_scope_col) {
    $stmt = $conn->prepare("SELECT subject, body, scope FROM email_templates WHERE template_key = ? LIMIT 1");
} else {
    $stmt = $conn->prepare("SELECT subject, body FROM email_templates WHERE template_key = ? LIMIT 1");
}

$stmt->bind_param('s', $key);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo json_encode(null);
    exit;
}

if ($has_scope_col && ($row['scope'] ?? '') !== 'loja') {
    http_response_code(403);
    echo json_encode(null);
    exit;
}

echo json_encode([
    'subject' => $row['subject'],
    'body' => $row['body'],
]);
