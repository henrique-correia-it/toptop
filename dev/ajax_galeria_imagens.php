<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/includes/storage_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || $_SESSION['admin_role'] !== 'desenvolvedor') {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);

if (!is_array($dados) || !csrf_from_array($dados)) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de segurança (CSRF).']);
    exit;
}

$action = $dados['action'] ?? '';

// ── ANALISAR: encontrar fotos fantasma ────────────────────────────────────
if ($action === 'analisar') {
    include '../config/database.php';

    $fantasmas = dev_listar_fantasmas($conn);
    echo json_encode(['sucesso' => true, 'fantasmas' => $fantasmas, 'total' => count($fantasmas)]);
    exit;
}

// ── APAGAR: apagar uma foto fantasma ─────────────────────────────────────
if ($action === 'apagar') {
    include '../config/database.php';

    $relInput = ltrim(str_replace('\\', '/', trim((string)($dados['rel'] ?? ''))), '/');
    $ficheiro = basename(str_replace('\\', '/', (string)($dados['ficheiro'] ?? '')));
    $tipo     = (string)($dados['tipo'] ?? '');

    if ($relInput !== '') {
        if (str_contains($relInput, '..')) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Caminho inválido.']);
            exit;
        }
        $ficheiro = basename($relInput);
    }

    $protegidos = dev_galeria_protegidos();
    if ($ficheiro === '' || in_array($ficheiro, $protegidos, true)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Este ficheiro está protegido ou é inválido.']);
        exit;
    }

    $ext = strtolower(pathinfo($ficheiro, PATHINFO_EXTENSION));
    if (!in_array($ext, dev_galeria_extensoes(), true)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Tipo de ficheiro não permitido.']);
        exit;
    }

    $referenciados = dev_recolher_referencias_imagens($conn);
    $esta_ref = ($tipo !== '' && isset($referenciados[$tipo][$ficheiro])) || isset($referenciados['any'][$ficheiro]);
    if ($esta_ref) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'A imagem está referenciada na base de dados e não pode ser apagada.']);
        exit;
    }

    $pastasPermitidas = dev_galeria_pastas_permitidas();
    $candidatos = [];

    if ($relInput !== '') {
        foreach ($pastasPermitidas as $prefix => $absDir) {
            // Verifica match exato de prefixo + nome direto (sem subpastas extras)
            if ($relInput === $prefix . $ficheiro) {
                $candidatos[$prefix] = $absDir;
                break;
            }
        }
    } else {
        $tipoToPrefix = [
            'produto'   => ['images/'],
            'categoria' => ['assets/img_categorias/'],
            'logo'      => ['assets/header/', 'assets/'],
            'hero'      => ['assets/hero/', 'assets/'],
            'outros'    => ['assets/'],
        ];
        $prefixes = $tipoToPrefix[$tipo] ?? array_keys($pastasPermitidas);
        foreach ($prefixes as $prefix) {
            if (isset($pastasPermitidas[$prefix])) {
                $candidatos[$prefix] = $pastasPermitidas[$prefix];
            }
        }
    }

    $apagado = false;
    $deletedRel = '';
    foreach ($candidatos as $prefix => $dir) {
        $dir_real = realpath($dir);
        $caminho  = $dir . $ficheiro;
        if (!$dir_real || !file_exists($caminho) || !is_file($caminho) || is_link($caminho)) {
            continue;
        }
        $file_real = realpath($caminho);
        if (!$file_real || dirname($file_real) !== $dir_real || !str_starts_with($file_real, $dir_real . DIRECTORY_SEPARATOR)) {
            continue;
        }
        if (unlink($file_real)) {
            $apagado = true;
            $deletedRel = $prefix . $ficheiro;
            break;
        }
    }

    if (!$apagado) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Ficheiro não encontrado ou erro ao apagar.']);
        exit;
    }

    log_app("Imagem fantasma apagada: {$deletedRel}", 'INFO', 'galeria');
    echo json_encode(['sucesso' => true]);
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => 'Ação desconhecida.']);
