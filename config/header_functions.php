<?php
/**
 * Obtem o conteudo de uma seccao do cabecalho com fallback.
 */
function getHeaderText($seccao, $fallback = '') {
    global $conn;

    if (!$conn) {
        return $fallback;
    }

    try {
        $stmt = $conn->prepare("SELECT conteudo FROM header_config WHERE seccao = ? LIMIT 1");
    } catch (Throwable $e) {
        return $fallback;
    }

    if (!$stmt) return $fallback;

    $stmt->bind_param("s", $seccao);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['conteudo'];
    }

    $stmt->close();
    return $fallback;
}

function getHeaderLogo($fallback = '/public/assets/logo1.jpg') {
    $logo = getHeaderText('logo_src', $fallback);
    $logo = trim($logo);

    if ($logo === '') {
        return $fallback;
    }

    return $logo;
}
