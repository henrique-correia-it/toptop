<?php
// config/logger.php
// Sistema centralizado de logging do TopTop.
date_default_timezone_set('Europe/Lisbon');
// Incluído automaticamente por config/database.php em todas as páginas.

if (!defined('LOG_DIR')) {
    define('LOG_DIR', __DIR__ . '/../logs/');
}

// Cria a pasta de logs se não existir
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

// Redireciona error_log() do PHP para o nosso ficheiro principal
ini_set('error_log', LOG_DIR . 'app.log');

// ─────────────────────────────────────────────
// Funções públicas de logging
// ─────────────────────────────────────────────

/**
 * Escreve uma linha num ficheiro de log com timestamp, nível, contexto e IP.
 */
function _tt_log(string $ficheiro, string $nivel, string $mensagem, string $contexto = ''): void {
    $dir = LOG_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        error_log("[{$nivel}] {$mensagem}");
        return;
    }
    $timestamp    = date('Y-m-d H:i:s');
    $contexto_str = $contexto ? " [{$contexto}]" : '';
    $ip           = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $linha        = "[{$timestamp}] [{$nivel}]{$contexto_str} {$mensagem} (IP:{$ip})" . PHP_EOL;
    if (@file_put_contents($dir . $ficheiro, $linha, FILE_APPEND | LOCK_EX) === false) {
        error_log("[{$nivel}] {$mensagem}");
    }
}

/** Erros gerais da aplicação */
function log_app(string $mensagem, string $nivel = 'ERROR', string $contexto = ''): void {
    _tt_log('app.log', $nivel, $mensagem, $contexto);
}

/** Erros de base de dados — escreve em sql.log. O error_log() chamado no código vai para app.log via ini_set. */
function log_sql(string $mensagem, string $contexto = ''): void {
    _tt_log('sql.log', 'SQL', $mensagem, $contexto);
}

/** Falhas de email — escreve em email.log. */
function log_email(string $mensagem, string $contexto = ''): void {
    _tt_log('email.log', 'EMAIL', $mensagem, $contexto);
}

/** Eventos e erros do Stripe — escreve em stripe.log. */
function log_stripe(string $mensagem, string $nivel = 'INFO'): void {
    _tt_log('stripe.log', $nivel, $mensagem);
}

/** Eventos de segurança — escreve em seguranca.log. */
function log_seguranca(string $mensagem, string $contexto = ''): void {
    _tt_log('seguranca.log', 'SECURITY', $mensagem, $contexto);
}

// ─────────────────────────────────────────────
// Handlers globais de erros PHP
// ─────────────────────────────────────────────

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    $tipos_fatais  = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE];
    $tipos_warning = [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING];
    $tipos_notice  = [E_NOTICE, E_USER_NOTICE];
    $tipos_depr    = [E_DEPRECATED, E_USER_DEPRECATED];

    if (in_array($errno, $tipos_fatais))       $nivel = 'FATAL';
    elseif (in_array($errno, $tipos_warning))  $nivel = 'WARNING';
    elseif (in_array($errno, $tipos_notice))   $nivel = 'NOTICE';
    elseif (in_array($errno, $tipos_depr))     $nivel = 'DEPRECATED';
    else                                        $nivel = 'PHP-ERROR';

    _tt_log('app.log', $nivel, "{$errstr} em " . basename($errfile) . ":{$errline}");
    return false;
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        _tt_log('app.log', 'FATAL-SHUTDOWN', $err['message'] . ' em ' . basename($err['file']) . ':' . $err['line']);
    }
});
