<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/rate_limit.php';

// Segurança: Apenas DEVs
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || $_SESSION['admin_role'] !== 'desenvolvedor') {
    http_response_code(403);
    exit("Acesso negado.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit("Metodo nao permitido.");
}

if (!csrf_from_post()) {
    http_response_code(403);
    exit("Erro de validacao CSRF.");
}

require_once __DIR__ . '/../config/database.php';

$password_atual = $_POST['current_password'] ?? '';
$admin_id = (int) ($_SESSION['admin_id'] ?? 0);

if ($admin_id <= 0 || $password_atual === '') {
    http_response_code(403);
    exit("Reautenticacao obrigatoria.");
}

if (!rate_limit_allow('dev_backup_db', 5, 900, (string)$admin_id)) {
    http_response_code(429);
    exit("Demasiados pedidos de backup. Aguarde alguns minutos.");
}

$stmt_admin = $conn->prepare(
    "SELECT password_hash, role FROM administradores WHERE id = ? LIMIT 1"
);
$stmt_admin->bind_param('i', $admin_id);
$stmt_admin->execute();
$admin_atual = $stmt_admin->get_result()->fetch_assoc();
$stmt_admin->close();

if (
    !$admin_atual
    || $admin_atual['role'] !== 'desenvolvedor'
    || !password_verify($password_atual, $admin_atual['password_hash'])
) {
    log_seguranca('Reautenticacao recusada para backup da base de dados.', 'ajax_backup_db.php');
    http_response_code(403);
    exit("Palavra-passe incorreta.");
}

unset($password_atual, $_POST['current_password']);
log_seguranca('Backup completo da base de dados exportado.', 'ajax_backup_db.php');
session_write_close();

$conn->set_charset('utf8mb4');
$conn->query("START TRANSACTION WITH CONSISTENT SNAPSHOT");

// Configurar cabeçalhos para download de ficheiro
$nome_ficheiro = 'backup_db_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$dbname) . '_' . date('Y-m-d_H-i-s') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nome_ficheiro . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('Expires: 0');

// Abrir output stream
$output = fopen('php://output', 'w');

fwrite($output, "-- TopTop Database Backup\n");
fwrite($output, "-- Gerado em: " . date('Y-m-d H:i:s') . "\n");
fwrite($output, "-- Database: " . $dbname . "\n\n");
fwrite($output, "SET NAMES utf8mb4;\n");
fwrite($output, "SET FOREIGN_KEY_CHECKS=0;\n\n");

// Obter todas as tabelas
$tables = [];
$result = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
if ($result) {
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    $result->free();
}

foreach ($tables as $table) {
    $table_escaped = str_replace('`', '``', (string)$table);

    // 1. Drop table se existir
    fwrite($output, "DROP TABLE IF EXISTS `{$table_escaped}`;\n");

    // 2. Create Table
    $res_create = $conn->query("SHOW CREATE TABLE `{$table_escaped}`");
    if ($res_create) {
        $row_create = $res_create->fetch_row();
        $res_create->free();
        if (!empty($row_create[1])) {
            fwrite($output, $row_create[1] . ";\n\n");
        }
    }

    // 3. Dados (streaming com MYSQLI_USE_RESULT para evitar esgotar memória)
    $res_data = $conn->query("SELECT * FROM `{$table_escaped}`", MYSQLI_USE_RESULT);
    if ($res_data) {
        $fields_meta = $res_data->fetch_fields();
        $num_fields = count($fields_meta);

        while ($row_data = $res_data->fetch_row()) {
            $insert = "INSERT INTO `{$table_escaped}` VALUES(";
            for ($j = 0; $j < $num_fields; $j++) {
                if (isset($row_data[$j])) {
                    $meta = $fields_meta[$j] ?? null;
                    $is_binary = $meta && (($meta->flags & MYSQLI_BINARY_FLAG) && in_array($meta->type, [MYSQLI_TYPE_BLOB, MYSQLI_TYPE_TINY_BLOB, MYSQLI_TYPE_MEDIUM_BLOB, MYSQLI_TYPE_LONG_BLOB], true));
                    if ($is_binary && $row_data[$j] !== '') {
                        $insert .= '0x' . bin2hex($row_data[$j]);
                    } else {
                        $val = $conn->real_escape_string($row_data[$j]);
                        $insert .= "'" . $val . "'";
                    }
                } else {
                    $insert .= 'NULL';
                }
                if ($j < ($num_fields - 1)) {
                    $insert .= ',';
                }
            }
            $insert .= ");\n";
            fwrite($output, $insert);
        }
        $res_data->free();
    }
    fwrite($output, "\n\n");
}

$conn->query("COMMIT");
fwrite($output, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($output);
exit;
