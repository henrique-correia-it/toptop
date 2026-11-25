<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
ob_start();

header('Content-Type: application/json');

if (!is_admin_manager()) {
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

function tt_gerar_slug_categoria(mysqli $conn, string $nome, int $excludeId = 0): string {
    $base = criar_slug($nome);
    if ($base === '') {
        $base = 'categoria';
    }
    $slug = $base;
    $suffix = 2;
    while (true) {
        if ($excludeId > 0) {
            $st = $conn->prepare("SELECT id FROM categorias WHERE slug = ? AND id != ? LIMIT 1");
            $st->bind_param("si", $slug, $excludeId);
        } else {
            $st = $conn->prepare("SELECT id FROM categorias WHERE slug = ? LIMIT 1");
            $st->bind_param("s", $slug);
        }
        $st->execute();
        $exists = $st->get_result()->num_rows > 0;
        $st->close();
        if (!$exists) {
            return $slug;
        }
        $slug = $base . '-' . $suffix;
        $suffix++;
    }
}

function tt_apagar_capa_categoria(?string $fotoCapa): void {
    if (empty($fotoCapa) || strpos($fotoCapa, 'img_categorias/') === false) {
        return;
    }
    $cats_dir = realpath(__DIR__ . '/../public/assets/img_categorias');
    $path = $cats_dir ? realpath($cats_dir . DIRECTORY_SEPARATOR . basename($fotoCapa)) : false;
    if ($cats_dir && $path && str_starts_with($path, $cats_dir . DIRECTORY_SEPARATOR) && is_file($path)) {
        @unlink($path);
    }
}

// ════════════════════════════════════════════════════════════════════════════
switch ($acao) {

    // ── CRIAR ─────────────────────────────────────────────────────────────
    case 'criar':
        $nome = trim((string)($dados['nome'] ?? ''));
        if ($nome === '') { http_response_code(400); echo json_encode(['sucesso' => false, 'mensagem' => 'Nome obrigatório']); exit; }

        try {
            $chk = $conn->prepare("SELECT id FROM categorias WHERE nome = ? LIMIT 1");
            $chk->bind_param("s", $nome);
            $chk->execute();
            $duplicado = $chk->get_result()->num_rows > 0;
            $chk->close();
            if ($duplicado) {
                http_response_code(409);
                echo json_encode(['sucesso' => false, 'mensagem' => 'Já existe uma categoria com esse nome']); exit;
            }

            $slug = tt_gerar_slug_categoria($conn, $nome);
            $visiveis_atual = (int)$conn->query("SELECT COUNT(*) FROM categorias WHERE visivel = 1")->fetch_row()[0];
            $visivel_inicial = ($visiveis_atual < 8) ? 1 : 0;
            $max = (int)$conn->query("SELECT COALESCE(MAX(ordem),0)+1 FROM categorias")->fetch_row()[0];

            $stmt = $conn->prepare("INSERT INTO categorias (nome, slug, ordem, visivel, foto_zoom, foto_mobile_posicao, foto_mobile_zoom) VALUES (?, ?, ?, ?, 1.00, '50% 50%', 1.00)");
            $stmt->bind_param("ssii", $nome, $slug, $max, $visivel_inicial);
            $stmt->execute();
            $novo_id = (int)$conn->insert_id;
            $stmt->close();

            $msg = $visivel_inicial
                ? 'Categoria criada.'
                : 'Categoria criada como oculta (limite de 8 visíveis na página inicial atingido).';
            log_app("Categoria criada: '{$nome}' (#{$novo_id}).", 'INFO', 'ajax_categoria.php');
            echo json_encode(['sucesso' => true, 'id' => $novo_id, 'visivel' => $visivel_inicial, 'mensagem' => $msg]);
        } catch (\Throwable $e) {
            log_exception($e, 'ajax_categoria.php criar');
            http_response_code(500);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao criar categoria']);
        }
        break;

    // ── EDITAR (com cascade para produtos.categoria) ──────────────────────
    case 'editar':
        $id   = (int)($dados['id'] ?? 0);
        $nome = trim((string)($dados['nome'] ?? ''));
        if ($id <= 0 || $nome === '') { http_response_code(400); echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos']); exit; }

        $conn->begin_transaction();
        try {
            $stOld = $conn->prepare("SELECT nome FROM categorias WHERE id = ? FOR UPDATE");
            $stOld->bind_param("i", $id);
            $stOld->execute();
            $rowOld = $stOld->get_result()->fetch_assoc();
            $stOld->close();
            if (!$rowOld) {
                $conn->rollback();
                http_response_code(404);
                echo json_encode(['sucesso' => false, 'mensagem' => 'Categoria não encontrada']); exit;
            }
            $nomeAntigo = (string)$rowOld['nome'];

            $chk = $conn->prepare("SELECT id FROM categorias WHERE nome = ? AND id != ? LIMIT 1");
            $chk->bind_param("si", $nome, $id);
            $chk->execute();
            $duplicado = $chk->get_result()->num_rows > 0;
            $chk->close();
            if ($duplicado) {
                $conn->rollback();
                http_response_code(409);
                echo json_encode(['sucesso' => false, 'mensagem' => 'Já existe uma categoria com esse nome']); exit;
            }

            $slug = tt_gerar_slug_categoria($conn, $nome, $id);
            $stmt = $conn->prepare("UPDATE categorias SET nome = ?, slug = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nome, $slug, $id);
            $stmt->execute();
            $stmt->close();

            if ($nomeAntigo !== $nome) {
                $stmtProd = $conn->prepare("UPDATE produtos SET categoria = ? WHERE categoria = ?");
                $stmtProd->bind_param("ss", $nome, $nomeAntigo);
                $stmtProd->execute();
                $stmtProd->close();
            }

            $conn->commit();
            log_app("Categoria #{$id} editada: '{$nomeAntigo}' -> '{$nome}'.", 'INFO', 'ajax_categoria.php');
            echo json_encode(['sucesso' => true, 'mensagem' => 'Categoria actualizada']);
        } catch (\Throwable $e) {
            $conn->rollback();
            log_exception($e, "ajax_categoria.php editar#{$id}");
            http_response_code(500);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao actualizar categoria']);
        }
        break;

    // ── APAGAR (impossível apagar com produtos sem migrar para categoria existente) ─
    case 'apagar':
        $id         = (int)($dados['id'] ?? 0);
        $destino_id = (int)($dados['destino_id'] ?? 0);

        if ($id <= 0) { http_response_code(400); echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']); exit; }

        $conn->begin_transaction();
        try {
            $row = $conn->prepare("SELECT id, nome, ordem, visivel, foto_capa FROM categorias WHERE id = ? FOR UPDATE");
            $row->bind_param("i", $id);
            $row->execute();
            $cat = $row->get_result()->fetch_assoc();
            $row->close();

            if (!$cat) {
                $conn->rollback();
                http_response_code(404);
                echo json_encode(['sucesso' => false, 'mensagem' => 'Categoria não encontrada']); exit;
            }

            $stCount = $conn->prepare("SELECT COUNT(*) FROM produtos WHERE categoria = ?");
            $stCount->bind_param("s", $cat['nome']);
            $stCount->execute();
            $total_produtos = (int)$stCount->get_result()->fetch_row()[0];
            $stCount->close();

            $destino_final_id = 0;
            $destino_final_nome = '';

            if ($total_produtos > 0) {
                if ($destino_id > 0) {
                    if ($destino_id === $id) {
                        $conn->rollback();
                        http_response_code(400);
                        echo json_encode(['sucesso' => false, 'mensagem' => 'Escolhe uma categoria de destino diferente da que vais apagar.']); exit;
                    }
                    $stDest = $conn->prepare("SELECT id, nome FROM categorias WHERE id = ? FOR UPDATE");
                    $stDest->bind_param("i", $destino_id);
                    $stDest->execute();
                    $destRow = $stDest->get_result()->fetch_assoc();
                    $stDest->close();
                    if (!$destRow) {
                        $conn->rollback();
                        http_response_code(404);
                        echo json_encode(['sucesso' => false, 'mensagem' => 'Categoria de destino não encontrada.']); exit;
                    }
                    $destino_final_id   = (int)$destRow['id'];
                    $destino_final_nome = (string)$destRow['nome'];
                } else {
                    // Bloqueio estrito: nunca permite apagar categoria com produtos sem destino
                    $conn->rollback();
                    http_response_code(409);
                    echo json_encode([
                        'sucesso'         => false,
                        'requer_migracao' => true,
                        'total_produtos'  => $total_produtos,
                        'mensagem'        => "Esta categoria tem {$total_produtos} produto(s) associado(s). Escolhe uma categoria de destino antes de a apagar.",
                    ]);
                    exit;
                }

                $stMove = $conn->prepare("UPDATE produtos SET categoria = ? WHERE categoria = ?");
                $stMove->bind_param("ss", $destino_final_nome, $cat['nome']);
                $stMove->execute();
                $stMove->close();
            }

            $stmt = $conn->prepare("DELETE FROM categorias WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $apagou = $stmt->affected_rows > 0;
            $stmt->close();

            $conn->commit();
            log_app("Categoria #{$id} ('{$cat['nome']}') eliminada" . ($total_produtos > 0 ? " ({$total_produtos} produtos -> '{$destino_final_nome}')" : '') . ".", 'INFO', 'ajax_categoria.php');

            if ($apagou) {
                tt_apagar_capa_categoria($cat['foto_capa'] ?? null);
            }

            $msg = $total_produtos > 0
                ? "{$total_produtos} produto(s) movido(s) para \"{$destino_final_nome}\" e categoria apagada."
                : 'Categoria apagada.';

            ob_clean();
            echo json_encode([
                'sucesso'      => true,
                'mensagem'     => $msg,
                'movidos'      => $total_produtos,
                'destino_id'   => $destino_final_id,
                'destino_nome' => $destino_final_nome,
            ]);
            exit;
        } catch (\Throwable $e) {
            $conn->rollback();
            log_exception($e, "ajax_categoria.php apagar#{$id}");
            http_response_code(500);
            ob_clean();
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao apagar categoria']);
            exit;
        }

    // ── TOGGLE VISÍVEL (com validação server-side do limite de 8) ─────────
    case 'toggle_visivel':
        $id = (int)($dados['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']); exit; }

        $conn->begin_transaction();
        try {
            $stCur = $conn->prepare("SELECT visivel FROM categorias WHERE id = ? FOR UPDATE");
            $stCur->bind_param("i", $id);
            $stCur->execute();
            $rowCur = $stCur->get_result()->fetch_assoc();
            $stCur->close();
            if (!$rowCur) {
                $conn->rollback();
                http_response_code(404);
                echo json_encode(['sucesso' => false, 'mensagem' => 'Categoria não encontrada']); exit;
            }

            $novoVisivel = ((int)$rowCur['visivel'] === 1) ? 0 : 1;
            if ($novoVisivel === 1) {
                $visCount = (int)$conn->query("SELECT COUNT(*) FROM categorias WHERE visivel = 1")->fetch_row()[0];
                if ($visCount >= 8) {
                    $conn->rollback();
                    http_response_code(409);
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Máximo de 8 categorias visíveis atingido. Desativa outra primeiro.']); exit;
                }
            }

            $stmt = $conn->prepare("UPDATE categorias SET visivel = ? WHERE id = ?");
            $stmt->bind_param("ii", $novoVisivel, $id);
            $stmt->execute();
            $stmt->close();
            $conn->commit();

            echo json_encode(['sucesso' => true, 'visivel' => $novoVisivel, 'mensagem' => $novoVisivel ? 'Categoria visível' : 'Categoria oculta']);
        } catch (\Throwable $e) {
            $conn->rollback();
            log_exception($e, "ajax_categoria.php toggle_visivel#{$id}");
            http_response_code(500);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao alterar visibilidade']);
        }
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
            log_exception($e, 'ajax_categoria.php reordenar');
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
        try {
            $stmt = $conn->prepare("UPDATE categorias SET foto_capa = ? WHERE id = ?");
            $stmt->bind_param("si", $rel, $id);
            $ok = $stmt->execute();
            $stmt->close();
        } catch (\Throwable $e) {
            $ok = false;
            log_exception($e, "ajax_categoria.php upload_capa#{$id}");
        }

        if (!$ok) {
            @unlink($dest);
            http_response_code(500);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar na base de dados']); exit;
        }

        // Apagar foto antiga só após confirmar upload + UPDATE, com proteção realpath
        if (!empty($old_row['foto_capa']) && basename($old_row['foto_capa']) !== $filename) {
            tt_apagar_capa_categoria($old_row['foto_capa']);
        }

        echo json_encode(['sucesso' => true, 'url' => '/public/' . $rel, 'mensagem' => 'Foto actualizada']);
        break;

    case 'set_posicao':
        $id      = (int)($dados['id'] ?? 0);
        $posicao = trim((string)($dados['posicao'] ?? '50% 50%'));
        $zoom    = (float)($dados['zoom'] ?? 1.0);
        $device  = (($dados['device'] ?? 'desktop') === 'mobile') ? 'mobile' : 'desktop';

        if ($id <= 0) { http_response_code(400); echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']); exit; }
        if (preg_match('/^(\d{1,3}(?:\.\d+)?)%\s+(\d{1,3}(?:\.\d+)?)%$/', $posicao, $pm)) {
            $px = max(0.0, min(100.0, (float)$pm[1]));
            $py = max(0.0, min(100.0, (float)$pm[2]));
            $posicao = round($px, 2) . '% ' . round($py, 2) . '%';
        } else {
            $posicao = '50% 50%';
        }
        $zoom = max(1.0, min(5.0, $zoom));

        $colPos  = ($device === 'mobile') ? 'foto_mobile_posicao' : 'foto_posicao';
        $colZoom = ($device === 'mobile') ? 'foto_mobile_zoom' : 'foto_zoom';

        try {
            $stmt = $conn->prepare("UPDATE categorias SET $colPos = ?, $colZoom = ? WHERE id = ?");
            $stmt->bind_param("sdi", $posicao, $zoom, $id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['sucesso' => true, 'mensagem' => 'Enquadramento guardado']);
        } catch (\Throwable $e) {
            log_exception($e, "ajax_categoria.php set_posicao#{$id}");
            http_response_code(500);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar enquadramento']);
        }
        break;

    default:
        echo json_encode(['sucesso' => false, 'mensagem' => 'Acção desconhecida']);
}
