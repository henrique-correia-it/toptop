<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/loja_config.php';

header('Content-Type: application/json');

if (!admin_has_role(['superadmin', 'desenvolvedor'])) {
    http_response_code(403);
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
    $csrfToken = $_POST['csrf_token'] ?? null;
    $chave = $_POST['chave'] ?? '';
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    $csrfToken = is_array($data) ? ($data['csrf_token'] ?? null) : null;
    $chave = is_array($data) ? ($data['chave'] ?? '') : '';
    $conteudo = is_array($data) ? ($data['conteudo'] ?? null) : null;
}

if (!csrf_verify(is_string($csrfToken) ? $csrfToken : null)) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de segurança (CSRF).']);
    exit;
}

if (!is_string($chave) || $chave === '') {
    http_response_code(400);
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

    $dir = __DIR__ . '/../public/assets/hero/';
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
    $oldUrl = getLojaConfig('home_hero_bg', '');

    if (setLojaConfig('home_hero_bg', $url)) {
        // Apagar imagem antiga apenas após gravar na BD, com validação contra path traversal
        if (!empty($oldUrl) && str_starts_with($oldUrl, '/public/assets/hero/')) {
            $oldName = basename($oldUrl);
            $realDir = realpath($dir);
            $oldPath = $realDir ? realpath($dir . $oldName) : false;
            if ($oldName !== $filename && $realDir && $oldPath && str_starts_with($oldPath, $realDir . DIRECTORY_SEPARATOR) && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }
        echo json_encode(['sucesso' => true, 'url' => $url, 'mensagem' => 'Fundo da Hero atualizado com sucesso!']);
    } else {
        @unlink($dest);
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar na base de dados.']);
    }
    exit;
}

// Lógica para Texto (JSON) — whitelist estrita de chaves da homepage
$chaves_home_permitidas = [
    'home_hero_title',
    'home_hero_subtitle',
    'home_hero_cta',
    'home_trust_1_title',
    'home_trust_1_desc',
    'home_trust_2_title',
    'home_trust_2_desc',
    'home_trust_3_title',
    'home_trust_3_desc',
    'home_cat_title',
    'home_new_title',
];

if (!$isMultipart && is_string($conteudo)) {
    if (!in_array($chave, $chaves_home_permitidas, true)) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Chave não permitida.']);
        exit;
    }
    if (setLojaConfig($chave, $conteudo)) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Texto atualizado com sucesso!']);
    } else {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar na base de dados.']);
    }
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos ou inválidos.']);
