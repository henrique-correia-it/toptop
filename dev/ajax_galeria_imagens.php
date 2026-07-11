<?php
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || $_SESSION['admin_role'] !== 'desenvolvedor') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);

if (
    !$dados
    || !isset($dados['csrf_token'], $_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $dados['csrf_token'])
) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de segurança.']);
    exit;
}

$action      = $dados['action'] ?? '';
$images_dir  = __DIR__ . '/../public/images/';
$cats_dir    = __DIR__ . '/../public/assets/img_categorias/';
$logos_dir   = __DIR__ . '/../public/assets/header/';
$allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

// ── ANALISAR: encontrar fotos fantasma ────────────────────────────────────
if ($action === 'analisar') {
    include '../config/database.php';

    // 1. Recolher todos os nomes de ficheiro referenciados na BD
    $referenciados = [];

    $queries = [
        "SELECT foto_principal   AS f FROM produtos         WHERE foto_principal  IS NOT NULL AND foto_principal  != ''",
        "SELECT nome_ficheiro    AS f FROM produto_imagens   WHERE nome_ficheiro   IS NOT NULL AND nome_ficheiro   != ''",
        "SELECT foto_snapshot    AS f FROM encomenda_itens   WHERE foto_snapshot   IS NOT NULL AND foto_snapshot   != ''",
        "SELECT foto_capa        AS f FROM categorias        WHERE foto_capa       IS NOT NULL AND foto_capa       != ''",
        "SELECT conteudo         AS f FROM header_config     WHERE seccao = 'logo_src' AND conteudo IS NOT NULL AND conteudo != ''",
    ];

    foreach ($queries as $sql) {
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $nome = basename($row['f']); // só o nome do ficheiro, sem path
                $referenciados[$nome] = true;
            }
        }
    }

    // 2. Comparar com os ficheiros em disco
    $fantasmas = [];
    
    // Scan produtos
    if (is_dir($images_dir)) {
        foreach (scandir($images_dir) as $ficheiro) {
            if ($ficheiro === '.' || $ficheiro === '..') continue;
            if (!is_file($images_dir . $ficheiro)) continue;
            $ext = strtolower(pathinfo($ficheiro, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) continue;
            if (!isset($referenciados[$ficheiro])) $fantasmas[] = $ficheiro;
        }
    }

    // Scan categorias
    if (is_dir($cats_dir)) {
        foreach (scandir($cats_dir) as $ficheiro) {
            if ($ficheiro === '.' || $ficheiro === '..') continue;
            if (!is_file($cats_dir . $ficheiro)) continue;
            $ext = strtolower(pathinfo($ficheiro, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) continue;
            if (!isset($referenciados[$ficheiro])) $fantasmas[] = $ficheiro;
        }
    }

    // Scan logos
    if (is_dir($logos_dir)) {
        foreach (scandir($logos_dir) as $ficheiro) {
            if ($ficheiro === '.' || $ficheiro === '..') continue;
            if (!is_file($logos_dir . $ficheiro)) continue;
            $ext = strtolower(pathinfo($ficheiro, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) continue;
            if (!isset($referenciados[$ficheiro])) $fantasmas[] = $ficheiro;
        }
    }

    echo json_encode(['sucesso' => true, 'fantasmas' => $fantasmas, 'total' => count($fantasmas)]);
    exit;
}

// ── APAGAR: apagar uma foto fantasma ─────────────────────────────────────
if ($action === 'apagar') {
    include '../config/database.php';

    $ficheiro = $dados['ficheiro'] ?? '';

    // Sanitizar: apenas nome do ficheiro, sem slashes nem path traversal
    $ficheiro = basename($ficheiro);
    if (empty($ficheiro)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nome de ficheiro inválido.']);
        exit;
    }

    $ext = strtolower(pathinfo($ficheiro, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Tipo de ficheiro não permitido.']);
        exit;
    }

    // Verificar que ainda não está referenciada em lado nenhum (segurança extra)
    $queries_check = [
        "SELECT COUNT(*) as n FROM produtos        WHERE foto_principal LIKE ?",
        "SELECT COUNT(*) as n FROM produto_imagens WHERE nome_ficheiro  LIKE ?",
        "SELECT COUNT(*) as n FROM encomenda_itens WHERE foto_snapshot  LIKE ?",
        "SELECT COUNT(*) as n FROM categorias      WHERE foto_capa       LIKE ?",
        "SELECT COUNT(*) as n FROM header_config   WHERE seccao = 'logo_src' AND conteudo LIKE ?",
    ];
    $search_term = "%$ficheiro%";
    foreach ($queries_check as $sql) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $search_term);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row['n'] > 0) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'A imagem está referenciada na base de dados e não pode ser apagada.']);
            exit;
        }
    }

    // Tentar apagar de todas as pastas (seguro pois $ficheiro é basename)
    $apagado = false;
    $caminho_prod = $images_dir . $ficheiro;
    $caminho_cat  = $cats_dir . $ficheiro;
    $caminho_logo = $logos_dir . $ficheiro;

    if (file_exists($caminho_prod) && is_file($caminho_prod)) {
        if (unlink($caminho_prod)) $apagado = true;
    }
    if (!$apagado && file_exists($caminho_cat) && is_file($caminho_cat)) {
        if (unlink($caminho_cat)) $apagado = true;
    }
    if (!$apagado && file_exists($caminho_logo) && is_file($caminho_logo)) {
        if (unlink($caminho_logo)) $apagado = true;
    }

    if (!$apagado) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Ficheiro não encontrado ou erro ao apagar.']);
        exit;
    }

    echo json_encode(['sucesso' => true]);
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => 'Ação desconhecida.']);
