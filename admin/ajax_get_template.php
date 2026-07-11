<?php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    echo json_encode(null);
    exit;
}

$key = $_GET['key'] ?? '';
if (empty($key)) {
    echo json_encode(null);
    exit;
}

include '../config/database.php';

$stmt = $conn->prepare("SELECT subject, body FROM email_templates WHERE template_key = ? LIMIT 1");
$stmt->bind_param("s", $key);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(null);
    exit;
}

echo json_encode(['assunto' => $row['subject'], 'corpo' => $row['body']]);
