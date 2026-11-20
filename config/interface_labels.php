<?php
/**
 * Utility to fetch and manage interface labels (titles and descriptions)
 */

require_once __DIR__ . '/database.php';

function &_interfaceStringsCache(): array {
    static $strings_cache = null;
    if ($strings_cache === null) {
        global $conn;
        $strings_cache = [];
        if (isset($conn) && $conn) {
            try {
                $res = $conn->query("SELECT string_key, title, description FROM admin_interface_strings");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $strings_cache[$row['string_key']] = [
                            'title' => $row['title'],
                            'description' => $row['description'],
                        ];
                    }
                }
            } catch (Throwable $e) {
                // Mantém cache vazio para fallback seguro
            }
        }
    }
    return $strings_cache;
}

function getInterfaceString($key, $defaultTitle = '', $defaultDesc = '') {
    $strings_cache = &_interfaceStringsCache();

    if (isset($strings_cache[$key])) {
        return $strings_cache[$key];
    }

    return [
        'title' => $defaultTitle,
        'description' => $defaultDesc,
    ];
}

function saveInterfaceString($key, $title, $description) {
    global $conn;
    if (!isset($conn) || !$conn) return false;

    $stmt = $conn->prepare("INSERT INTO admin_interface_strings (string_key, title, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description)");
    if (!$stmt) return false;
    $stmt->bind_param("sss", $key, $title, $description);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $strings_cache = &_interfaceStringsCache();
        $strings_cache[$key] = ['title' => $title, 'description' => $description];
    }
    return $ok;
}
