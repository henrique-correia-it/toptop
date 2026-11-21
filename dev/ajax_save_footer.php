<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/http.php';

// Segurança: Apenas Superadmin ou Desenvolvedor
if (!admin_has_role(['superadmin', 'desenvolvedor'])) {
    json_error('Acesso negado.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

$data = json_input();

if (!$data || !isset($data['seccao']) || !isset($data['conteudo']) || !isset($data['csrf_token'])) {
    json_error('Dados incompletos.', 400);
}

// Validar CSRF
if (!csrf_from_array($data)) {
    json_error('Erro de segurança (CSRF).', 403);
}

$seccao = $data['seccao'];
$conteudo = $data['conteudo'];

$seccoes_permitidas = [
    'titulo_sobre',
    'sobre_nos',
    'titulo_info',
    'link_label_trocas',
    'link_label_envios',
    'link_label_encomenda',
    'titulo_contactos',
    'morada_rua',
    'morada_cp',
    'morada_localidade',
    'telefone',
    'email',
    'link_whatsapp',
    'link_instagram',
    'link_facebook',
    'titulo_pagamento',
    'pagamento_seguro',
    'copyright',
    'horario_funcionamento',
];

if (!is_string($seccao) || !is_string($conteudo) || !in_array($seccao, $seccoes_permitidas, true)) {
    json_error('Secção inválida.', 400);
}

if ($seccao !== 'horario_funcionamento') {
    $conteudo = preg_replace('/&lt;(\/?(?:p|br)\b[^&]*?)&gt;/i', '<$1>', $conteudo);
    $conteudo = preg_replace('#</p>\s*<p\b[^>]*>#i', "\n", $conteudo);
    $conteudo = preg_replace('#<br\s*/?>#i', "\n", $conteudo);
    $conteudo = trim(preg_replace('#</?p\b[^>]*>#i', '', $conteudo));
}

// Upsert (Insert ou Update)
$stmt = $conn->prepare("INSERT INTO footer_config (seccao, conteudo) VALUES (?, ?) ON DUPLICATE KEY UPDATE conteudo = ?, ultima_atualizacao = CURRENT_TIMESTAMP");
$stmt->bind_param("sss", $seccao, $conteudo, $conteudo);

if ($stmt->execute()) {
    json_success(['mensagem' => 'Rodapé atualizado com sucesso!']);
} else {
    json_error('Erro ao guardar na base de dados.', 500);
}

$stmt->close();
$conn->close();
