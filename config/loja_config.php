<?php
// config/loja_config.php
// Helper centralizado para ler/escrever configurações da loja na BD.
// Incluído automaticamente via database.php.

function _lojaConfigCache(?string $setKey = null, $setValue = null) {
    static $cache = [];
    if ($setKey !== null) {
        $cache[$setKey] = $setValue;
    }
    return $cache;
}

function getLojaConfig(string $chave, $default = null) {
    global $conn;
    $cache = _lojaConfigCache();
    if (array_key_exists($chave, $cache)) return $cache[$chave];
    if (!isset($conn) || !$conn) return $default;

    $stmt = $conn->prepare("SELECT valor FROM loja_configuracoes WHERE chave = ?");
    if (!$stmt) return $default;
    $stmt->bind_param("s", $chave);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $val = ($row !== null) ? $row['valor'] : $default;
    _lojaConfigCache($chave, $val);
    return $val;
}

function setLojaConfig(string $chave, string $valor): bool {
    global $conn;
    if (!isset($conn) || !$conn) return false;

    $stmt = $conn->prepare(
        "INSERT INTO loja_configuracoes (chave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = CURRENT_TIMESTAMP"
    );
    if (!$stmt) return false;
    $stmt->bind_param("ss", $chave, $valor);
    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        _lojaConfigCache($chave, $valor);
    }
    return $result;
}
