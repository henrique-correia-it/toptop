<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
ob_start();

header('Content-Type: application/json');

if (!admin_has_role(['superadmin', 'desenvolvedor'])) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método inválido']); exit;
}

include '../config/database.php';
require_once __DIR__ . '/../config/url_helpers.php';

// ── Deteta se é multipart (upload) ou JSON ──────────────────────────────────
$is_multipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart') !== false;
$dados = [];

if ($is_multipart) {
    $acao      = $_POST['acao'] ?? '';
    $csrf_post = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;
} else {
    $decoded   = json_decode(file_get_contents('php://input'), true);
    $dados     = is_array($decoded) ? $decoded : [];
    $acao      = $dados['acao'] ?? '';
    $csrf_post = is_string($dados['csrf_token'] ?? null) ? $dados['csrf_token'] : null;
}

if (!csrf_verify($csrf_post)) {
    http_response_code(403);
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

        $stmt = $conn->prepare("DELETE FROM categorias WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $apagou = $stmt->affected_rows > 0;
        $stmt->close();

        if ($apagou && $cat && !empty($cat['foto_capa']) && strpos($cat['foto_capa'], 'img_categorias/') !== false) {
            $cats_dir = realpath(__DIR__ . '/../public/assets/img_categorias');
            $path = $cats_dir ? realpath($cats_dir . DIRECTORY_SEPARATOR . basename($cat['foto_capa'])) : false;
            if ($cats_dir && $path && str_starts_with($path, $cats_dir . DIRECTORY_SEPARATOR) && is_file($path)) {
                @unlink($path);
            }
        }

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
        $rowVis = $novo->get_result()->fetch_assoc();
        $novo->close();
        if (!$rowVis) {
            http_response_code(404);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Categoria não encontrada']); exit;
        }
        $visivel = (int)$rowVis['visivel'];

        echo json_encode(['sucesso' => true, 'visivel' => $visivel, 'mensagem' => $visivel ? 'Categoria visível' : 'Categoria oculta']);
        break;

    // ── REORDENAR ─────────────────────────────────────────────────────────
    case 'reordenar':
        $ids = $dados['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) { echo json_encode(['sucesso' => false, 'mensagem' => 'IDs inválidos']); exit; }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE categorias SET ordem = ? WHERE id = ?");
            $pos = 1;
            foreach ($ids as $cat_id) {
                $cat_id_int = (int)$cat_id;
                if ($cat_id_int <= 0) continue;
                $stmt->bind_param("ii", $pos, $cat_id_int);
                $stmt->execute();
                $pos++;
            }
            $stmt->close();
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao reordenar categorias']); exit;
        }

        echo json_encode(['sucesso' => true, 'mensagem' => 'Ordem actualizada']);
        break;

    // ── UPLOAD CAPA ───────────────────────────────────────────────────────
    case 'upload_capa':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']); exit; }

        if (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro no ficheiro']); exit;
        }

        $file = $_FILES['foto'];
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'A imagem deve ter no máximo 5MB.']); exit;
        }

        // Confirmar existência da categoria antes de gravar ficheiro em disco
        $old = $conn->prepare("SELECT foto_capa FROM categorias WHERE id = ? LIMIT 1");
        $old->bind_param("i", $id);
        $old->execute();
        $old_row = $old->get_result()->fetch_assoc();
        $old->close();
        if (!$old_row) {
            http_response_code(404);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Categoria não encontrada']); exit;
        }

        $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mime     = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed, true)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Formato inválido (JPG, PNG, WebP, AVIF)']); exit;
        }

        $ext      = match($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default      => 'jpg',
        };

        $dir = __DIR__ . '/../public/assets/img_categorias/';
        if (!is_dir($dir)) { mkdir($dir, 0755, true); }

        $filename = 'cat_' . $id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dest     = $dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar ficheiro']); exit;
        }

        $rel  = 'assets/img_categorias/' . $filename;
        $stmt = $conn->prepare("UPDATE categorias SET foto_capa = ? WHERE id = ?");
        $stmt->bind_param("si", $rel, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            @unlink($dest);
            http_response_code(500);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar na base de dados']); exit;
        }

        // Apagar foto antiga só após confirmar upload + UPDATE, com proteção realpath
        if (!empty($old_row['foto_capa']) && strpos($old_row['foto_capa'], 'img_categorias/') !== false) {
            $cats_dir = realpath($dir);
            $old_name = basename($old_row['foto_capa']);
            $old_path = $cats_dir ? realpath($cats_dir . DIRECTORY_SEPARATOR . $old_name) : false;
            if ($old_name !== $filename && $cats_dir && $old_path && str_starts_with($old_path, $cats_dir . DIRECTORY_SEPARATOR) && is_file($old_path)) {
                @unlink($old_path);
            }
        }

        echo json_encode(['sucesso' => true, 'url' => '/public/' . $rel, 'mensagem' => 'Foto actualizada']);
        break;

    case 'set_posicao':
        $id      = (int)($dados['id'] ?? 0);
        $posicao = trim((string)($dados['posicao'] ?? '50% 50%'));
        $zoom    = (float)($dados['zoom'] ?? 1.0);
        $device  = (($dados['device'] ?? 'desktop') === 'mobile') ? 'mobile' : 'desktop';

        if ($id <= 0) { echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']); exit; }
        if (!preg_match('/^\d{1,3}(\.\d+)?% \d{1,3}(\.\d+)?%$/', $posicao)) { $posicao = '50% 50%'; }
        if ($zoom < 1.0) $zoom = 1.0;
        if ($zoom > 5.0) $zoom = 5.0;

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
