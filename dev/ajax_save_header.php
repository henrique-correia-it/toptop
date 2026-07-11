<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

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
    $seccao = $_POST['seccao'] ?? '';
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    $csrfToken = $data['csrf_token'] ?? '';
    $seccao = $data['seccao'] ?? '';
    $conteudo = $data['conteudo'] ?? null;
}

if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de seguranca (CSRF).']);
    exit;
}

function guardarHeaderConfig($conn, $seccao, $conteudo) {
    try {
        $stmt = $conn->prepare("INSERT INTO header_config (seccao, conteudo) VALUES (?, ?) ON DUPLICATE KEY UPDATE conteudo = ?, ultima_atualizacao = CURRENT_TIMESTAMP");
    } catch (Throwable $e) {
        return false;
    }

    if (!$stmt) return false;

    $stmt->bind_param("sss", $seccao, $conteudo, $conteudo);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

if ($isMultipart && $seccao === 'logo_src') {
    if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro no ficheiro do logo.']);
        exit;
    }

    $file = $_FILES['logo'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Formato invalido. Usa JPG, PNG, WebP ou AVIF.']);
        exit;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'O logo deve ter no maximo 2MB.']);
        exit;
    }

    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        default => 'jpg',
    };

    $dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/public/assets/header/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar o logo.']);
        exit;
    }

    $url = '/public/assets/header/' . $filename;

    // Procurar logo atual para apagar depois
    $oldUrl = '';
    $stmtOld = $conn->prepare("SELECT conteudo FROM header_config WHERE seccao = 'logo_src'");
    if ($stmtOld) {
        $stmtOld->execute();
        $resOld = $stmtOld->get_result();
        if ($resOld && $resOld->num_rows > 0) {
            $rowOld = $resOld->fetch_assoc();
            $oldUrl = $rowOld['conteudo'];
        }
        $stmtOld->close();
    }

    if (guardarHeaderConfig($conn, 'logo_src', $url)) {
        if (!empty($oldUrl)) {
            $oldFilename = basename($oldUrl);
            $oldPath = $dir . $oldFilename;
            if (file_exists($oldPath) && is_file($oldPath) && $oldFilename !== $filename) {
                unlink($oldPath);
            }
        }
        echo json_encode(['sucesso' => true, 'url' => $url, 'mensagem' => 'Logo atualizado com sucesso!']);
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar na base de dados.']);
    }

    $conn->close();
    exit;
}

if (!$isMultipart && $seccao && $conteudo !== null) {
    $permitidas = ['nav_home', 'nav_produtos', 'nav_contacto', 'logo_alt', 'nav_admin', 'nav_dev'];
    if (!in_array($seccao, $permitidas)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Seccao invalida.']);
        exit;
    }

    $conteudo = trim((string)$conteudo);
    if ($conteudo === '') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'O conteudo nao pode ficar vazio.']);
        exit;
    }

    if (guardarHeaderConfig($conn, $seccao, $conteudo)) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Cabecalho atualizado com sucesso!']);
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar na base de dados.']);
    }

    $conn->close();
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos.']);
$conn->close();
