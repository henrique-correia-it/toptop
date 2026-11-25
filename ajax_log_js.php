<?php
// ajax_log_js.php
// Recebe erros de runtime, promessas rejeitadas, recursos falhados e HTTP 5xx/rede do browser.
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/logger.php';
require_once __DIR__ . '/config/rate_limit.php';
require_once __DIR__ . '/config/http.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

if (!rate_limit_allow('js_error_log', 30, 60)) {
    http_response_code(204);
    exit;
}

$dados = json_input();
if (empty($dados)) {
    http_response_code(204);
    exit;
}

$tipoRaw  = strtolower(trim((string)($dados['type'] ?? 'error')));
$msg      = trim((string)($dados['message'] ?? ''));
$source   = trim((string)($dados['source'] ?? ''));
$line     = (int)($dados['line'] ?? 0);
$col      = (int)($dados['col'] ?? 0);
$page     = trim((string)($dados['page'] ?? ''));
$endpoint = trim((string)($dados['endpoint'] ?? ''));
$status   = (int)($dados['status'] ?? 0);
$stack    = trim((string)($dados['stack'] ?? ''));

if ($msg === '' && $source === '' && $endpoint === '') {
    http_response_code(204);
    exit;
}

$nivel = match ($tipoRaw) {
    'unhandledrejection' => 'JS-PROMISE',
    'fetch_error'        => 'JS-FETCH',
    'resource'           => 'JS-RESOURCE',
    default              => 'JS-ERROR',
};

$trunc = static fn(string $s, int $max): string => mb_strlen($s, 'UTF-8') > $max
    ? (mb_substr($s, 0, $max - 3, 'UTF-8') . '...')
    : $s;

$partes = [];
$partes[] = $trunc($msg !== '' ? $msg : 'Sem mensagem', 300);

if ($endpoint !== '') {
    $partes[] = 'endpoint:' . $trunc($endpoint, 160) . ($status > 0 ? " (HTTP {$status})" : '');
}
if ($source !== '') {
    $loc = $trunc($source, 160);
    if ($line > 0) {
        $loc .= ':' . $line . ($col > 0 ? ':' . $col : '');
    }
    $partes[] = 'src:' . $loc;
}
if ($page !== '') {
    $partes[] = 'page:' . $trunc($page, 140);
}
if ($stack !== '') {
    $firstLine = strtok(str_replace(["\r", "\n"], ' | ', $stack), '') ?: $stack;
    $partes[] = 'stack:' . $trunc($firstLine, 220);
}

$ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
if ($ua !== '') {
    $partes[] = 'ua:' . $trunc($ua, 120);
}

log_js(implode(' | ', $partes), $nivel, 'browser');

http_response_code(204);
exit;
