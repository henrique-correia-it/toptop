<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/http.php';

// Segurança: apenas Superadmin ou Desenvolvedor
if (!admin_has_role(['superadmin', 'desenvolvedor'])) {
    json_error('Acesso negado.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

$data = json_input();

if (!$data || !isset($data['conteudo']) || !isset($data['csrf_token'])) {
    json_error('Dados incompletos.', 400);
}

// Validar CSRF
if (!csrf_from_array($data)) {
    json_error('Erro de segurança (CSRF).', 403);
}

$conteudo = $data['conteudo'];
if (!is_array($conteudo)) {
    json_error('Estrutura inválida.', 400);
}

// Ícones permitidos — tem de coincidir com o mapa em trocas.php.
$icones_validos = ['exchange', 'clock', 'shield', 'package', 'card', 'truck'];

// Normalizar/sanitizar a estrutura (guardamos texto simples — escapado no output).
$limpo = [
    'kicker' => trim((string) ($conteudo['kicker'] ?? '')),
    'titulo' => trim((string) ($conteudo['titulo'] ?? '')),
    'intro'  => trim((string) ($conteudo['intro'] ?? '')),
    'cards'  => [],
];

foreach (($conteudo['cards'] ?? []) as $card) {
    if (!is_array($card)) {
        continue;
    }

    $itens = [];
    foreach (($card['itens'] ?? []) as $item) {
        $item = trim((string) $item);
        if ($item !== '') {
            $itens[] = $item;
        }
    }

    $titulo_card = trim((string) ($card['titulo'] ?? ''));

    // Ignora cards completamente vazios.
    if ($titulo_card === '' && empty($itens)) {
        continue;
    }

    $icone = (string) ($card['icon'] ?? 'exchange');
    if (!in_array($icone, $icones_validos, true)) {
        $icone = 'exchange';
    }

    $limpo['cards'][] = [
        'titulo' => $titulo_card,
        'icon'   => $icone,
        'itens'  => $itens,
    ];
}

$json = json_encode($limpo, JSON_UNESCAPED_UNICODE);

if ($json !== false && setLojaConfig('trocas_content', $json)) {
    json_success(['mensagem' => 'Política de trocas atualizada com sucesso!']);
} else {
    json_error('Erro ao guardar na base de dados.', 500);
}
