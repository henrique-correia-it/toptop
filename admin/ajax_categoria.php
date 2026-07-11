<?php
require_once __DIR__ . '/../config/session.php';
ob_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true ||
    !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']); exit;
}

include '../config/database.php';
require_once __DIR__ . '/../config/url_helpers.php';

// ── Deteta se é multipart (upload) ou JSON ──────────────────────────────────
$is_multipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart') !== false;

if ($is_multipart) {
    $acao      = $_POST['acao'] ?? '';
    $csrf_post = $_POST['csrf_token'] ?? '';
} else {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Método inválido']); exit;
    }
    $dados     = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao      = $dados['acao'] ?? '';
    $csrf_post = $dados['csrf_token'] ?? '';
}

if (!hash_equals($_SESSION['csrf_token'], $csrf_post)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'CSRF inválido']); exit;
}

// ════════════════════════════════════════════════════════════════════════════
switch ($acao) {

    // ── CRIAR ─────────────────────────────────────────────────────────────
    case 'criar':
        $nome = trim($dados['nome'] ?? '');
        if ($nome === '') { echo json_encode(['sucesso' => false, 'mensagem' => 'Nome obrigatório']); exit; }

        $slug = criar_slug($nome);

        // Verificar duplicado
        $chk = $conn->prepare("SELECT id FROM categorias WHERE nome = ?");
        $chk->bind_param("s", $nome);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Já existe uma categoria com esse nome']); exit;
        }
        $chk->close();

        $max = $conn->query("SELECT COALESCE(MAX(ordem),0)+1 FROM categorias")->fetch_row()[0];
        $stmt = $conn->prepare("INSERT INTO categorias (nome, slug, ordem, visivel, foto_zoom, foto_mobile_posicao, foto_mobile_zoom) VALUES (?, ?, ?, 1, 1.00, '50% 50%', 1.00)");
        $stmt->bind_param("ssi", $nome, $slug, $max);
        $stmt->execute();
        $novo_id = $conn->insert_id;
        $stmt->close();

        echo json_encode(['sucesso' => true, 'id' => $novo_id, 'mensagem' => 'Categoria criada']);
        break;

    // ── EDITAR ────────────────────────────────────────────────────────────
    case 'editar':
        $id   = (int)($dados['id'] ?? 0);
        $nome = trim($dados['nome'] ?? '');
        if ($id <= 0 || $nome === '') { echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos']); exit; }

        $slug = criar_slug($nome);

        // Verificar duplicado (excluindo o próprio)
        $chk = $conn->prepare("SELECT id FROM categorias WHERE nome = ? AND id != ?");
        $chk->bind_param("si", $nome, $id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Já existe uma categoria com esse nome']); exit;
        }
        $chk->close();

        $stmt = $conn->prepare("UPDATE categorias SET nome = ?, slug = ? WHERE id = ?");
        $stmt->bind_param("ssi", $nome, $slug, $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['sucesso' => true, 'mensagem' => 'Categoria actualizada']);
        break;

    // ── APAGAR ────────────────────────────────────────────────────────────
    case 'apagar':
        $id = (int)($dados['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']); exit; }

        // Verificar foto para apagar ficheiro
        $row = $conn->prepare("SELECT foto_capa FROM categorias WHERE id = ?");
        $row->bind_param("i", $id);
        $row->execute();
        $cat = $row->get_result()->fetch_assoc();
        $row->close();

        if ($cat && $cat['foto_capa'] && strpos($cat['foto_capa'], 'img_categorias/') !== false) {
            $path = $_SERVER['DOCUMENT_ROOT'] . '/public/' . $cat['foto_capa'];
            if (file_exists($path)) { @unlink($path); }
        }

        $stmt = $conn->prepare("DELETE FROM categorias WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        ob_clean();
        echo json_encode(['sucesso' => true, 'mensagem' => 'Categoria apagada']);
        exit;

    // ── TOGGLE VISÍVEL ────────────────────────────────────────────────────
    case 'toggle_visivel':
        $id = (int)($dados['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']); exit; }

        $stmt = $conn->prepare("UPDATE categorias SET visivel = 1 - visivel WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $novo = $conn->prepare("SELECT visivel FROM categorias WHERE id = ?");
        $novo->bind_param("i", $id);
        $novo->execute();
        $visivel = (int)$novo->get_result()->fetch_assoc()['visivel'];
        $novo->close();

        echo json_encode(['sucesso' => true, 'visivel' => $visivel, 'mensagem' => $visivel ? 'Categoria visível' : 'Categoria oculta']);
        break;

    // ── REORDENAR ─────────────────────────────────────────────────────────
    case 'reordenar':
        $ids = $dados['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) { echo json_encode(['sucesso' => false, 'mensagem' => 'IDs inválidos']); exit; }

        $stmt = $conn->prepare("UPDATE categorias SET ordem = ? WHERE id = ?");
        foreach ($ids as $i => $cat_id) {
            $ordem = $i + 1;
            $cat_id = (int)$cat_id;
            $stmt->bind_param("ii", $ordem, $cat_id);
            $stmt->execute();
        }
        $stmt->close();

        echo json_encode(['sucesso' => true, 'mensagem' => 'Ordem actualizada']);
        break;

    // ── UPLOAD CAPA ───────────────────────────────────────────────────────
    case 'upload_capa':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']); exit; }

        if (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro no ficheiro']); exit;
        }

        $file     = $_FILES['foto'];
        $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mime     = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Formato inválido (JPG, PNG, WebP, AVIF)']); exit;
        }

        $ext      = match($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default      => 'jpg',
        };

        $dir = $_SERVER['DOCUMENT_ROOT'] . '/public/assets/img_categorias/';
        if (!is_dir($dir)) { mkdir($dir, 0755, true); }

        // Apagar foto antiga se existir (só da pasta img_categorias)
        $old = $conn->prepare("SELECT foto_capa FROM categorias WHERE id = ?");
        $old->bind_param("i", $id);
        $old->execute();
        $old_row = $old->get_result()->fetch_assoc();
        $old->close();
        if ($old_row && $old_row['foto_capa'] && strpos($old_row['foto_capa'], 'img_categorias/') !== false) {
            $old_path = $_SERVER['DOCUMENT_ROOT'] . '/public/' . $old_row['foto_capa'];
            if (file_exists($old_path)) { @unlink($old_path); }
        }

        $filename = 'cat_' . $id . '_' . time() . '.' . $ext;
        $dest     = $dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar ficheiro']); exit;
        }

        $rel  = 'assets/img_categorias/' . $filename;
        $stmt = $conn->prepare("UPDATE categorias SET foto_capa = ? WHERE id = ?");
        $stmt->bind_param("si", $rel, $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['sucesso' => true, 'url' => '/public/' . $rel, 'mensagem' => 'Foto actualizada']);
        break;
        
    case 'set_posicao':
        $id      = (int)($dados['id'] ?? 0);
        $posicao = trim($dados['posicao'] ?? '50% 50%');
        $zoom    = (float)($dados['zoom'] ?? 1.0);
        $device  = ($dados['device'] === 'mobile') ? 'mobile' : 'desktop';

        if ($id <= 0) { echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']); exit; }
        if (!preg_match('/^\d{1,3}(\.\d+)?% \d{1,3}(\.\d+)?%$/', $posicao)) { $posicao = '50% 50%'; }
        if ($zoom < 1.0) $zoom = 1.0;

        $colPos  = ($device === 'mobile') ? 'foto_mobile_posicao' : 'foto_posicao';
        $colZoom = ($device === 'mobile') ? 'foto_mobile_zoom' : 'foto_zoom';

        $stmt = $conn->prepare("UPDATE categorias SET $colPos = ?, $colZoom = ? WHERE id = ?");
        $stmt->bind_param("sdi", $posicao, $zoom, $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['sucesso' => true, 'mensagem' => 'Enquadramento guardado']);
        break;

    default:
        echo json_encode(['sucesso' => false, 'mensagem' => 'Acção desconhecida']);
}
