<?php
// admin/ajax_ver_logs.php
// Endpoint para leitura e limpeza dos ficheiros de log do sistema.
// Acesso restrito a administradores com role 'desenvolvedor'.

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/logger.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || $_SESSION['admin_role'] !== 'desenvolvedor') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado.']);
    exit;
}

$logs_permitidos = ['app', 'sql', 'email', 'stripe', 'seguranca'];
$method          = $_SERVER['REQUEST_METHOD'];

// ── GET: ler log ou obter tamanhos ──────────────────────────────────────────
if ($method === 'GET') {
    $acao   = $_GET['acao'] ?? '';
    $log    = $_GET['log']  ?? '';
    $linhas = max(50, min(2000, (int)($_GET['linhas'] ?? 300)));

    if ($acao === 'tamanhos') {
        $resultado = [];
        foreach ($logs_permitidos as $nome) {
            $f = LOG_DIR . $nome . '.log';
            $resultado[$nome] = file_exists($f) ? filesize($f) : 0;
        }
        echo json_encode(['sucesso' => true, 'tamanhos' => $resultado]);
        exit;
    }

    if ($acao === 'ler') {
        if (!in_array($log, $logs_permitidos, true)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Log inválido.']);
            exit;
        }

        $ficheiro = LOG_DIR . $log . '.log';

        if (!file_exists($ficheiro) || filesize($ficheiro) === 0) {
            echo json_encode(['sucesso' => true, 'conteudo' => '(log vazio)', 'tamanho' => 0]);
            exit;
        }

        // Lê as últimas N linhas de forma eficiente (sem carregar o ficheiro todo)
        $fp     = fopen($ficheiro, 'r');
        $buffer = [];
        while (($linha = fgets($fp)) !== false) {
            $buffer[] = $linha;
            if (count($buffer) > $linhas) {
                array_shift($buffer);
            }
        }
        fclose($fp);

        $tamanho  = filesize($ficheiro);
        $conteudo = implode('', $buffer);

        echo json_encode(['sucesso' => true, 'conteudo' => $conteudo, 'tamanho' => $tamanho]);
        exit;
    }
}

// ── POST: limpar log ────────────────────────────────────────────────────────
if ($method === 'POST') {
    $dados = json_decode(file_get_contents('php://input'), true);
    $acao  = $dados['acao'] ?? '';
    $log   = $dados['log']  ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $dados['csrf_token'] ?? '')) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'CSRF inválido.']);
        exit;
    }

    if (!in_array($log, $logs_permitidos, true)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Log inválido.']);
        exit;
    }

    if ($acao === 'limpar') {
        $ficheiro = LOG_DIR . $log . '.log';
        @file_put_contents($ficheiro, '');
        log_app("Log '{$log}' limpo pelo admin '{$_SESSION['admin_username']}'.", 'INFO', 'ajax_ver_logs.php');
        echo json_encode(['sucesso' => true, 'mensagem' => "Log '{$log}' limpo com sucesso."]);
        exit;
    }
}

echo json_encode(['sucesso' => false, 'mensagem' => 'Ação inválida.']);
