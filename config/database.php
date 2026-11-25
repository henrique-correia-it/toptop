<?php
// config/database.php
date_default_timezone_set('Europe/Lisbon');

// Os erros sao registados no servidor e nunca apresentados no browser.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/env.php';

$env_vars = tt_load_env();
if (empty($env_vars)) {
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

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $password, $dbname);
} catch (\mysqli_sql_exception $e) {
    log_sql('Falha na ligacao a base de dados (Excecao): ' . $e->getMessage(), 'database.php');
    if (php_sapi_name() === 'cli') {
        die('Erro na ligacao a base de dados: ' . $e->getMessage() . "\n");
    }
    http_response_code(503);
    die('Servico temporariamente indisponivel. Por favor, tente mais tarde.');
}

if ($conn->connect_error) {
    log_sql('Falha na ligacao a base de dados: ' . $conn->connect_error, 'database.php');
    if (php_sapi_name() === 'cli') {
        die('Erro na ligacao a base de dados: ' . $conn->connect_error . "\n");
    }
    http_response_code(503);
    die('Servico temporariamente indisponivel. Por favor, tente mais tarde.');
}

$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '" . $conn->real_escape_string(date('P')) . "'");

require_once __DIR__ . '/loja_config.php';
