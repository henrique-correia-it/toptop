<?php
/**
 * Obtém o conteúdo de uma secção do cabeçalho com cache estático (evita N+1) e fallback.
 */
function getHeaderText($seccao, $fallback = '') {
    global $conn;
    static $cache = null;

    if (!$conn) {
        return $fallback;
    }

    if ($cache === null) {
        $cache = [];
        try {
            $res = $conn->query("SELECT seccao, conteudo FROM header_config");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $val = (string)$row['conteudo'];
                    $val = preg_replace('/&lt;(\/?(?:p|br)\b[^&]*?)&gt;/i', '<$1>', $val);
                    $val = preg_replace('#</?p\b[^>]*>#i', '', $val);
                    $cache[$row['seccao']] = trim($val);
                }
            }
        } catch (Throwable $e) {
            $cache = null;
            return $fallback;
        }
    }

    return array_key_exists($seccao, $cache) ? $cache[$seccao] : $fallback;
}

function getHeaderLogo($fallback = '/public/assets/logo1.jpg') {
    $logo = trim((string)getHeaderText('logo_src', $fallback));

    if ($logo === '' || !preg_match('#^(/|https?://)#i', $logo)) {
        return $fallback;
    }

    return $logo;
}
