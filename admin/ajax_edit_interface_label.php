<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=UTF-8');
http_response_code(410);
echo json_encode([
    'sucesso' => false,
    'mensagem' => 'A personalização de rótulos do painel foi desativada.'
]);
exit;
