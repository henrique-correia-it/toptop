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
    if (is_string($val)) {
        $trim = trim($val);
        if ($trim !== '' && $trim[0] !== '{' && $trim[0] !== '[') {
            $val = preg_replace('/&lt;(\/?(?:p|br)\b[^&]*?)&gt;/i', '<$1>', $val);
            $val = preg_replace('#</p>\s*<p\b[^>]*>#i', "\n", $val);
            $val = preg_replace('#<br\s*/?>#i', "\n", $val);
            $val = trim(preg_replace('#</?p\b[^>]*>#i', '', $val));
        }
    }
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

if (!function_exists('getHeroBgUrl')) {
    function getHeroBgUrl(string $fallback = '/public/assets/hero.avif'): string {
        $cfg  = trim((string)getLojaConfig('home_hero_bg', $fallback));
        $path = parse_url($cfg, PHP_URL_PATH);
        if (is_string($path) && $path !== '' && str_starts_with($path, '/public/')) {
            $abs = __DIR__ . '/../' . ltrim($path, '/');
            if (is_file($abs)) {
                return $path;
            }
        }
        return $fallback;
    }
}
