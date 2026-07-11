<?php
// config/loja_config.php
// Helper centralizado para ler/escrever configurações da loja na BD.
// Incluído automaticamente via database.php.

function getLojaConfig(string $chave, $default = null) {
    global $conn;
    static $cache = [];
    if (array_key_exists($chave, $cache)) return $cache[$chave];
    $stmt = $conn->prepare("SELECT valor FROM loja_configuracoes WHERE chave = ?");
    $stmt->bind_param("s", $chave);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $cache[$chave] = ($row !== null) ? $row['valor'] : $default;
    return $cache[$chave];
}

function setLojaConfig(string $chave, string $valor): bool {
    global $conn;
    $stmt = $conn->prepare(
        "INSERT INTO loja_configuracoes (chave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = CURRENT_TIMESTAMP"
    );
    $stmt->bind_param("ss", $chave, $valor);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}
