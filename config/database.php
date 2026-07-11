<?php
// config/database.php
date_default_timezone_set('Europe/Lisbon');

// Os erros sao registados no servidor e nunca apresentados no browser.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/logger.php';

$env_path = dirname(__DIR__) . '/.env';
if (!is_file($env_path)) {
    http_response_code(503);
    die('Servico temporariamente indisponivel.');
}

$env_vars = parse_ini_file($env_path, false, INI_SCANNER_RAW);
if ($env_vars === false) {
    http_response_code(503);
    die('Servico temporariamente indisponivel.');
}

$host = trim((string) ($env_vars['DB_HOST'] ?? ''));
$user = trim((string) ($env_vars['DB_USER'] ?? ''));
$password = (string) ($env_vars['DB_PASS'] ?? '');
$dbname = trim((string) ($env_vars['DB_NAME'] ?? ''));
$base_url = rtrim((string) ($env_vars['APP_URL'] ?? ''), '/');

if ($host === '' || $user === '' || $dbname === '' || $base_url === '') {
    log_app('Configuracao obrigatoria em falta no ficheiro .env.', 'ERROR', 'database.php');
    http_response_code(503);
    die('Servico temporariamente indisponivel.');
}

try {
    $conn = new mysqli($host, $user, $password, $dbname);
} catch (\mysqli_sql_exception $e) {
    log_sql('Falha na ligacao a base de dados (Excecao): ' . $e->getMessage(), 'database.php');
    error_log('Erro na ligacao a base de dados: ' . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        die('Erro na ligacao a base de dados: ' . $e->getMessage() . "\n");
    }
    http_response_code(503);
    die('Servico temporariamente indisponivel. Por favor, tente mais tarde.');
}

if ($conn->connect_error) {
    log_sql('Falha na ligacao a base de dados: ' . $conn->connect_error, 'database.php');
    error_log('Erro na ligacao a base de dados: ' . $conn->connect_error);
    if (php_sapi_name() === 'cli') {
        die('Erro na ligacao a base de dados: ' . $conn->connect_error . "\n");
    }
    http_response_code(503);
    die('Servico temporariamente indisponivel. Por favor, tente mais tarde.');
}

$conn->set_charset('utf8mb4');

require_once __DIR__ . '/loja_config.php';
