<?php
// dev/ajax_ver_logs.php
// Endpoint para leitura, filtragem e limpeza dos ficheiros de log do sistema.
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

/** Extrai timestamp Unix de uma linha de log para ordenação cronológica */
function tt_extract_log_timestamp(string $line): int
{
    if (preg_match('/^\[([^\]]+)\]/', $line, $m)) {
        $ts = strtotime(trim($m[1]));
        if ($ts !== false) {
            return $ts;
        }
    }
    return 0;
}

$log_labels = [
    'app'       => 'App',
    'sql'       => 'SQL',
    'email'     => 'Email',
    'stripe'    => 'Stripe',
    'seguranca' => 'Segurança',
    'js'        => 'JS',
];

// ── GET: tamanhos ou leitura ────────────────────────────────────────────────
if ($method === 'GET') {
    $acao      = (string)($_GET['acao'] ?? '');
    $log       = (string)($_GET['log'] ?? '');
    $tipo      = (string)($_GET['tipo'] ?? '');
    $rawLinhas = $_GET['linhas'] ?? 'all';
    $semLimite = ($rawLinhas === '' || $rawLinhas === 'all' || (int)$rawLinhas <= 0);
    $linhas    = $semLimite ? 0 : max(50, min(2000, (int)$rawLinhas));

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

    if ($acao === 'ler') {
        $nivel = (string)($_GET['nivel'] ?? '');
        $q     = trim((string)($_GET['q'] ?? ''));

        if ($log === '' || $log === 'todos') {
            $alvos        = ($tipo !== '' && in_array($tipo, $logs_permitidos, true)) ? [$tipo] : $logs_permitidos;
            $tamanhoTotal = 0;
            $totalLinhas  = 0;
            $entradas     = [];
            $seq          = 0;

            foreach ($alvos as $nome) {
                $ficheiro = LOG_DIR . $nome . '.log';
                if (!file_exists($ficheiro) || filesize($ficheiro) === 0) {
                    continue;
                }
                $tamanhoTotal += (int)filesize($ficheiro);
                $rawLines      = $semLimite
                    ? file($ficheiro, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
                    : tt_tail_lines($ficheiro, $linhas);
                if (!is_array($rawLines)) {
                    continue;
                }
                $totalLinhas += count($rawLines);
                $tipoLabel    = $log_labels[$nome] ?? $nome;

                foreach ($rawLines as $line) {
                    $seq++;
                    if ($nivel !== '' && tt_classify_log_line($line) !== $nivel) {
                        continue;
                    }
                    if (
                        $q !== ''
                        && stripos($line, $q) === false
                        && stripos($nome, $q) === false
                        && stripos($tipoLabel, $q) === false
                    ) {
                        continue;
                    }
                    $entradas[] = [
                        'log'     => $nome,
                        'raw'     => $line,
                        'ts_sort' => tt_extract_log_timestamp($line),
                        'idx'     => $seq,
                    ];
                }
            }

            usort($entradas, static fn($a, $b) => ($b['ts_sort'] <=> $a['ts_sort']) ?: ($b['idx'] <=> $a['idx']));
            if (!$semLimite && count($entradas) > $linhas) {
                $entradas = array_slice($entradas, 0, $linhas);
            }

            $registos = array_map(
                static fn($e) => [
                    'id'  => $e['log'] . ':' . sha1($e['raw']),
                    'log' => $e['log'],
                    'raw' => $e['raw'],
                ],
                $entradas
            );

            json_success([
                'registos'         => $registos,
                'tamanho'          => $tamanhoTotal,
                'total_linhas'     => $totalLinhas,
                'linhas_filtradas' => count($registos),
            ]);
        }

        if (!in_array($log, $logs_permitidos, true)) {
            json_error('Log inválido.', 400);
        }

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

// ── POST: apagar registos selecionados ──────────────────────────────────────
if ($method === 'POST') {
    $dados = json_input();
    $acao  = (string)($dados['acao'] ?? '');
    $log   = (string)($dados['log'] ?? '');

    require_once __DIR__ . '/../config/csrf.php';
    if (empty($dados) || !csrf_from_array($dados)) {
        json_error('CSRF inválido.', 403);
    }

    if ($acao === 'apagar_linhas') {
        $ids    = is_array($dados['ids'] ?? null) ? $dados['ids'] : [];
        $porLog = [];
        foreach ($ids as $id) {
            $idStr = trim((string)$id);
            if (preg_match('/^(app|sql|email|stripe|seguranca|js):([a-f0-9]{40})$/', $idStr, $m)) {
                $nome = $m[1];
                $hash = $m[2];
                $porLog[$nome][$hash] = ($porLog[$nome][$hash] ?? 0) + 1;
            }
        }

        if (empty($porLog)) {
            json_error('Nenhum registo válido selecionado.', 400);
        }

        $removidos = 0;
        foreach ($porLog as $nome => $hashesMap) {
            $ficheiro = LOG_DIR . $nome . '.log';
            if (!is_file($ficheiro)) {
                continue;
            }
            $fp = @fopen($ficheiro, 'c+b');
            if ($fp === false) {
                json_error("Não foi possível abrir o log '{$nome}'.", 500);
            }
            if (!flock($fp, LOCK_EX)) {
                fclose($fp);
                json_error("Não foi possível bloquear o log '{$nome}'.", 500);
            }

            rewind($fp);
            $content = stream_get_contents($fp);
            $lines   = preg_split('/\r\n|\n|\r/', (string)$content);
            $lines   = is_array($lines) ? array_values(array_filter($lines, static fn($l) => $l !== '')) : [];

            $restantesReversed = [];
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = $lines[$i];
                $h    = sha1($line);
                if (!empty($hashesMap[$h])) {
                    $hashesMap[$h]--;
                    $removidos++;
                } else {
                    $restantesReversed[] = $line;
                }
            }
            $restantes = array_reverse($restantesReversed);

            ftruncate($fp, 0);
            rewind($fp);
            if (!empty($restantes)) {
                fwrite($fp, implode("\n", $restantes) . "\n");
            }
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        json_success([
            'removidos' => $removidos,
            'mensagem'  => $removidos === 1
                ? 'Registo eliminado com sucesso.'
                : ($removidos . ' registos eliminados com sucesso.'),
        ]);
    }

    if (!in_array($log, $logs_permitidos, true)) {
        json_error('Log inválido.', 400);
    }
}

json_error('Ação inválida.', 400);
