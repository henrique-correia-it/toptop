<?php
// dev/ajax_ver_logs.php
// Endpoint para leitura, filtragem, download e limpeza dos ficheiros de log do sistema.
// Acesso restrito a administradores com role 'desenvolvedor'.

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/http.php';

send_no_cache_headers();

if (!is_developer()) {
    json_error('Não autorizado.', 403);
}

$logs_permitidos = ['app', 'sql', 'email', 'stripe', 'seguranca', 'js'];
$method          = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    json_error('Método não permitido.', 405);
}

/**
 * Lê as últimas $maxLinhas de um ficheiro usando blocos de 64KB a partir do fim.
 */
function tt_tail_lines(string $ficheiro, int $maxLinhas): ?array
{
    $fp = @fopen($ficheiro, 'rb');
    if ($fp === false) {
        return null;
    }
    $size = filesize($ficheiro);
    if ($size === false || $size === 0) {
        fclose($fp);
        return [];
    }
    $chunkSize = 65536;
    $pos = $size;
    $buffer = '';
    $newlineCount = 0;

    while ($pos > 0 && $newlineCount <= $maxLinhas) {
        $read = min($chunkSize, $pos);
        $pos -= $read;
        fseek($fp, $pos, SEEK_SET);
        $chunk = fread($fp, $read);
        if ($chunk === false) {
            break;
        }
        $buffer = $chunk . $buffer;
        $newlineCount += substr_count($chunk, "\n");
    }
    fclose($fp);

    $lines = preg_split('/\r\n|\n|\r/', $buffer);
    if (!is_array($lines)) {
        return [];
    }
    $lines = array_values(array_filter($lines, static fn($l) => $l !== ''));
    if (count($lines) > $maxLinhas) {
        $lines = array_slice($lines, -$maxLinhas);
    }
    return $lines;
}

/** Classifica o nível de uma linha de log em 'erros', 'avisos' ou 'info' */
function tt_classify_log_line(string $line): string
{
    if (preg_match('/^\[\d{4}-\d{2}-\d{2} [^\]]+\]\s*\[([^\]]+)\]/', $line, $m)) {
        $lvl = strtoupper(trim($m[1]));
        if (
            in_array($lvl, ['ERROR', 'FATAL', 'FATAL-SHUTDOWN', 'FATAL-EXCEPTION', 'PHP-ERROR', 'SQL', 'EMAIL', 'JS-ERROR', 'JS-PROMISE', 'JS-FETCH', 'JS-RESOURCE'], true)
            || str_starts_with($lvl, 'HTTP-5')
        ) {
            return 'erros';
        }
        if (in_array($lvl, ['WARNING', 'NOTICE', 'DEPRECATED', '404', 'SECURITY'], true)) {
            return 'avisos';
        }
        return 'info';
    }
    if (stripos($line, 'PHP Fatal') !== false || stripos($line, 'PHP Parse') !== false || stripos($line, 'Uncaught ') !== false) {
        return 'erros';
    }
    if (stripos($line, 'PHP Warning') !== false || stripos($line, 'PHP Notice') !== false) {
        return 'avisos';
    }
    return 'info';
}

// ── GET: tamanhos, leitura ou download ──────────────────────────────────────
if ($method === 'GET') {
    $acao   = (string)($_GET['acao'] ?? '');
    $log    = (string)($_GET['log'] ?? '');
    $linhas = max(50, min(2000, (int)($_GET['linhas'] ?? 500)));

    if ($acao === 'tamanhos') {
        $tamanhos = [];
        $erros    = [];
        foreach ($logs_permitidos as $nome) {
            $f = LOG_DIR . $nome . '.log';
            $tam = file_exists($f) ? (int)filesize($f) : 0;
            $tamanhos[$nome] = $tam;
            $errCount = 0;
            if ($tam > 0) {
                $recent = tt_tail_lines($f, 300) ?: [];
                foreach ($recent as $ln) {
                    $cat = tt_classify_log_line($ln);
                    if ($cat === 'erros' || ($nome === 'seguranca' && $cat === 'avisos')) {
                        $errCount++;
                    }
                }
            }
            $erros[$nome] = $errCount;
        }
        json_success(['tamanhos' => $tamanhos, 'erros' => $erros]);
    }

    if ($acao === 'download') {
        if (!in_array($log, $logs_permitidos, true)) {
            json_error('Log inválido.', 400);
        }
        $ficheiro = LOG_DIR . $log . '.log';
        $nomeDl   = 'toptop_' . $log . '_' . date('Y-m-d_H-i') . '.log';
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nomeDl . '"');
        if (is_file($ficheiro) && filesize($ficheiro) > 0) {
            readfile($ficheiro);
        } else {
            echo "(log vazio)\n";
        }
        exit;
    }

    if ($acao === 'ler') {
        if (!in_array($log, $logs_permitidos, true)) {
            json_error('Log inválido.', 400);
        }

        $nivel = (string)($_GET['nivel'] ?? '');
        $q     = trim((string)($_GET['q'] ?? ''));

        $ficheiro = LOG_DIR . $log . '.log';
        if (!file_exists($ficheiro) || filesize($ficheiro) === 0) {
            json_success([
                'conteudo'         => '',
                'tamanho'          => 0,
                'total_linhas'     => 0,
                'linhas_filtradas' => 0,
            ]);
        }

        $rawLines = tt_tail_lines($ficheiro, $linhas);
        if ($rawLines === null) {
            json_error('Não foi possível abrir o ficheiro de log.', 500);
        }

        $totalLinhas = count($rawLines);
        $filtradas = array_values(array_filter($rawLines, static function (string $line) use ($nivel, $q): bool {
            if ($nivel !== '' && tt_classify_log_line($line) !== $nivel) {
                return false;
            }
            if ($q !== '' && stripos($line, $q) === false) {
                return false;
            }
            return true;
        }));

        json_success([
            'conteudo'         => implode("\n", $filtradas),
            'tamanho'          => (int)filesize($ficheiro),
            'total_linhas'     => $totalLinhas,
            'linhas_filtradas' => count($filtradas),
        ]);
    }

    json_error('Ação inválida.', 400);
}

// ── POST: limpar um log ou limpar todos ─────────────────────────────────────
if ($method === 'POST') {
    $dados = json_input();
    $acao  = (string)($dados['acao'] ?? '');
    $log   = (string)($dados['log'] ?? '');

    require_once __DIR__ . '/../config/csrf.php';
    if (empty($dados) || !csrf_from_array($dados)) {
        json_error('CSRF inválido.', 403);
    }

    $adminUser = $_SESSION['admin_username'] ?? 'dev';

    if ($acao === 'limpar_todos') {
        foreach ($logs_permitidos as $nome) {
            $ficheiro = LOG_DIR . $nome . '.log';
            @file_put_contents($ficheiro, '', LOCK_EX);
        }
        log_app("Todos os logs foram limpos pelo admin '{$adminUser}'.", 'INFO', 'ajax_ver_logs.php');
        json_success(['mensagem' => 'Todos os registos de log foram limpos.']);
    }

    if (!in_array($log, $logs_permitidos, true)) {
        json_error('Log inválido.', 400);
    }

    if ($acao === 'limpar') {
        $ficheiro = LOG_DIR . $log . '.log';
        if (@file_put_contents($ficheiro, '', LOCK_EX) === false) {
            json_error('Não foi possível limpar o ficheiro de log.', 500);
        }
        log_app("Log '{$log}' limpo pelo admin '{$adminUser}'.", 'INFO', 'ajax_ver_logs.php');
        json_success(['mensagem' => "Log '{$log}' limpo com sucesso."]);
    }
}

json_error('Ação inválida.', 400);
