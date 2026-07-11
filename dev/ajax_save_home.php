<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/loja_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

$isMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart') !== false;

if ($isMultipart) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $chave = $_POST['chave'] ?? '';
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    $csrfToken = $data['csrf_token'] ?? '';
    $chave = $data['chave'] ?? '';
    $conteudo = $data['conteudo'] ?? null;
}

if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de segurança (CSRF).']);
    exit;
}

if (empty($chave)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Chave inválida.']);
    exit;
}

// Lógica para Upload de Imagem (Hero Background)
if ($isMultipart && $chave === 'home_hero_bg') {
    if (empty($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro no ficheiro da imagem.']);
        exit;
    }

    $file = $_FILES['imagem'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Formato inválido. Use JPG, PNG, WebP ou AVIF.']);
        exit;
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB para hero images
        echo json_encode(['sucesso' => false, 'mensagem' => 'A imagem deve ter no máximo 5MB.']);
        exit;
    }

    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        default => 'jpg',
    };

    $dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/public/assets/hero/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = 'hero_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar a imagem no servidor.']);
        exit;
    }

    $url = '/public/assets/hero/' . $filename;

    // Opcional: Apagar imagem antiga se não for a padrão
    $oldUrl = getLojaConfig('home_hero_bg', '');
    if (!empty($oldUrl) && strpos($oldUrl, '/public/assets/hero/') !== false) {
        $oldPath = $_SERVER['DOCUMENT_ROOT'] . $oldUrl;
        if (file_exists($oldPath) && is_file($oldPath)) {
            unlink($oldPath);
        }
    }

    if (setLojaConfig('home_hero_bg', $url)) {
        echo json_encode(['sucesso' => true, 'url' => $url, 'mensagem' => 'Fundo da Hero atualizado com sucesso!']);
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar na base de dados.']);
    }
    exit;
}

// Lógica para Texto (JSON)
if (!$isMultipart && $conteudo !== null) {
    if (setLojaConfig($chave, $conteudo)) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Texto atualizado com sucesso!']);
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar na base de dados.']);
    }
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos ou inválidos.']);
