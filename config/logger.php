<?php
// config/logger.php
// Sistema centralizado de logging e deteção de erros do TopTop.
date_default_timezone_set('Europe/Lisbon');

if (!defined('LOG_DIR')) {
    define('LOG_DIR', __DIR__ . '/../logs/');
}

if (!defined('LOG_MAX_BYTES')) {
    define('LOG_MAX_BYTES', 5 * 1024 * 1024); // 5 MB por ficheiro antes de rodar (.1)
}

if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

// Redireciona error_log() nativo do PHP para o ficheiro principal
@ini_set('error_log', LOG_DIR . 'app.log');

// Flag por pedido para detetar HTTP >= 500 silenciosos no shutdown
if (!isset($GLOBALS['_tt_error_logged'])) {
    $GLOBALS['_tt_error_logged'] = false;
}

/** Converte caminho absoluto para caminho relativo à raiz do projeto */
function _tt_rel_path(string $file): string {
    static $root = null;
    if ($root === null) {
        $root = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
        $root = rtrim($root, '/') . '/';
    }
    $norm = str_replace('\\', '/', $file);
    if (stripos($norm, $root) === 0) {
        return substr($norm, strlen($root));
    }
    return basename($file);
}

/** ID curto por pedido para correlacionar múltiplas linhas de log */
function _tt_request_id(): string {
    static $id = null;
    if ($id === null) {
        try {
            $id = bin2hex(random_bytes(3));
        } catch (\Throwable $e) {
            $id = substr(md5((string)microtime(true)), 0, 6);
        }
    }
    return $id;
}

/** Identifica quem originou o pedido sem iniciar sessão */
function _tt_actor(): string {
    if (PHP_SAPI === 'cli') {
        return 'cli';
    }
    if (isset($_SESSION) && is_array($_SESSION)) {
        if (!empty($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true) {
            $u = preg_replace('/[^a-zA-Z0-9._-]/', '', (string)($_SESSION['admin_username'] ?? 'admin'));
            return 'admin:' . ($u !== '' ? $u : 'admin');
        }
        if (!empty($_SESSION['cliente_logado']) && $_SESSION['cliente_logado'] === true && !empty($_SESSION['cliente_id'])) {
            return 'cliente:#' . (int)$_SESSION['cliente_id'];
        }
    }
    return 'guest';
}

/** Método e URI sanitizada (oculta tokens/senhas na query string) */
function _tt_request_meta(): string {
    if (PHP_SAPI === 'cli') {
        $script = isset($_SERVER['argv'][0]) ? basename((string)$_SERVER['argv'][0]) : 'cli';
        return 'CLI ' . $script;
    }
    $method = strtoupper(preg_replace('/[^A-Z]/', '', (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'))) ?: 'GET';
    $uri    = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $uri    = preg_replace('/([?&](?:token|password|senha|pass|secret|csrf_token|client_secret|key)=)[^&#]*/i', '$1***', $uri);
    $uri    = str_replace(["\r", "\n"], '', $uri);
    if (strlen($uri) > 180) {
        $uri = substr($uri, 0, 177) . '...';
    }
    return $method . ' ' . $uri;
}

/**
 * Escreve uma linha num ficheiro de log com rotação automática (5 MB),
 * timestamp, nível, contexto, pedido, ator e IP.
 */
function _tt_log(string $ficheiro, string $nivel, string $mensagem, string $contexto = ''): void {
    $error_levels = ['ERROR', 'FATAL', 'FATAL-SHUTDOWN', 'FATAL-EXCEPTION', 'PHP-ERROR', 'SQL', 'JS-ERROR', 'JS-PROMISE', 'JS-FETCH', 'JS-RESOURCE'];
    if (in_array(strtoupper($nivel), $error_levels, true) || $ficheiro === 'sql.log' || $ficheiro === 'email.log') {
        if (strtoupper($nivel) !== 'INFO' && strtoupper($nivel) !== '404') {
            $GLOBALS['_tt_error_logged'] = true;
        }
    }

    $dir = LOG_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        @error_log("[{$nivel}] {$mensagem}");
        return;
    }

    $caminho = $dir . $ficheiro;
    if (@is_file($caminho) && @filesize($caminho) >= LOG_MAX_BYTES) {
        @rename($caminho, $caminho . '.1');
    }

    $timestamp    = date('Y-m-d H:i:s');
    $msg_limpa    = str_replace(["\r", "\n"], ['\\r', '\\n'], $mensagem);
    $ctx_limpo    = str_replace(["\r", "\n"], ['\\r', '\\n'], $contexto);
    $contexto_str = $ctx_limpo !== '' ? " [{$ctx_limpo}]" : '';
    $raw_ip       = (string)($_SERVER['REMOTE_ADDR'] ?? (PHP_SAPI === 'cli' ? 'cli' : 'unknown'));
    $ip           = preg_replace('/[^0-9a-fA-F:., ]/', '', $raw_ip) ?: 'unknown';
    $meta         = _tt_request_meta();
    $actor        = _tt_actor();
    $req          = _tt_request_id();

    $linha = "[{$timestamp}] [{$nivel}]{$contexto_str} {$msg_limpa} | {$meta} | actor:{$actor} | req:{$req} | IP:{$ip}" . PHP_EOL;
    if (@file_put_contents($caminho, $linha, FILE_APPEND | LOCK_EX) === false) {
        @error_log("[{$nivel}] {$mensagem}");
    }
}

// ─────────────────────────────────────────────
// Funções públicas de logging
// ─────────────────────────────────────────────

/** Eventos e erros gerais da aplicação -> app.log */
function log_app(string $mensagem, string $nivel = 'ERROR', string $contexto = ''): void {
    _tt_log('app.log', $nivel, $mensagem, $contexto);
}

/** Erros de base de dados -> sql.log */
function log_sql(string $mensagem, string $contexto = '', string $nivel = 'SQL'): void {
    _tt_log('sql.log', $nivel, $mensagem, $contexto);
}

/** Envios e falhas de email -> email.log */
function log_email(string $mensagem, string $contexto = '', string $nivel = 'EMAIL'): void {
    _tt_log('email.log', $nivel, $mensagem, $contexto);
}

/** Eventos, chamadas API e erros do Stripe -> stripe.log */
function log_stripe(string $mensagem, string $nivel = 'INFO', string $contexto = ''): void {
    _tt_log('stripe.log', $nivel, $mensagem, $contexto);
}

/** Eventos e tentativas suspeitas de segurança -> seguranca.log */
function log_seguranca(string $mensagem, string $contexto = '', string $nivel = 'SECURITY'): void {
    _tt_log('seguranca.log', $nivel, $mensagem, $contexto);
}

/** Erros JavaScript / rede no browser do utilizador -> js.log */
function log_js(string $mensagem, string $nivel = 'JS-ERROR', string $contexto = ''): void {
    _tt_log('js.log', $nivel, $mensagem, $contexto);
}

/**
 * Regista qualquer exceção/Throwable com classe, ficheiro:linha e resumo da stack trace.
 * Exceções MySQLi são registadas automaticamente em sql.log e app.log.
 */
function log_exception(\Throwable $e, string $contexto = '', string $nivel = 'ERROR'): void {
    $frames = [];
    foreach (array_slice($e->getTrace(), 0, 4) as $f) {
        $loc = isset($f['file']) ? (_tt_rel_path((string)$f['file']) . ':' . ($f['line'] ?? '?')) : '[internal]';
        $fn  = ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '');
        $frames[] = "{$fn}@{$loc}";
    }
    $traceStr = $frames ? (' [trace: ' . implode(' <- ', $frames) . ']') : '';
    $msg      = get_class($e) . ': ' . $e->getMessage() . ' em ' . _tt_rel_path($e->getFile()) . ':' . $e->getLine() . $traceStr;

    if ($e instanceof \mysqli_sql_exception) {
        log_sql($msg, $contexto);
    }
    log_app($msg, $nivel, $contexto);
}

// ─────────────────────────────────────────────
// Handlers globais de erros, exceções e shutdown
// ─────────────────────────────────────────────

if (empty($GLOBALS['_tt_handlers_registered'])) {
    $GLOBALS['_tt_handlers_registered'] = true;

    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        $tipos_warning = [E_WARNING, E_USER_WARNING];
        $tipos_notice  = [E_NOTICE, E_USER_NOTICE];
        $tipos_depr    = [E_DEPRECATED, E_USER_DEPRECATED];

        if (in_array($errno, $tipos_warning, true))    $nivel = 'WARNING';
        elseif (in_array($errno, $tipos_notice, true)) $nivel = 'NOTICE';
        elseif (in_array($errno, $tipos_depr, true))   $nivel = 'DEPRECATED';
        else                                           $nivel = 'PHP-ERROR';

        _tt_log('app.log', $nivel, "{$errstr} em " . _tt_rel_path($errfile) . ":{$errline}", 'php');
        return true;
    });

    set_exception_handler(function (\Throwable $e): void {
        log_exception($e, 'uncaught', 'FATAL-EXCEPTION');
        $GLOBALS['_tt_fatal_logged'] = true;

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "[FATAL-EXCEPTION] " . $e->getMessage() . PHP_EOL);
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        $accept      = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
        $isAjax      = str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json')
            || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
            || str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), 'ajax_');

        if ($isAjax) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['sucesso' => false, 'mensagem' => 'Ocorreu um erro interno inesperado.']);
        } else {
            echo 'Ocorreu um erro interno inesperado. Por favor, tente novamente mais tarde.';
        }
        exit(1);
    });

    register_shutdown_function(function (): void {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            $GLOBALS['_tt_fatal_logged'] = true;
            _tt_log('app.log', 'FATAL-SHUTDOWN', $err['message'] . ' em ' . _tt_rel_path($err['file']) . ':' . $err['line'], 'shutdown');
            return;
        }

        if (PHP_SAPI !== 'cli' && empty($GLOBALS['_tt_fatal_logged']) && empty($GLOBALS['_tt_error_logged'])) {
            $status = http_response_code();
            if (is_int($status) && $status >= 500) {
                _tt_log('app.log', 'HTTP-' . $status, "Resposta HTTP {$status} terminou sem log de erro prévio.", 'shutdown');
            }
        }
    });
}
