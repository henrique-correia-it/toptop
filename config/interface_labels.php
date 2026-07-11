<?php
/**
 * Utility to fetch and manage interface labels (titles and descriptions)
 */

require_once __DIR__ . '/database.php';

function getInterfaceString($key, $defaultTitle = '', $defaultDesc = '') {
    global $conn;
    static $strings_cache = null;

    // Load all strings into cache on first call to minimize queries
    if ($strings_cache === null) {
        $strings_cache = [];
        $res = $conn->query("SELECT string_key, title, description FROM admin_interface_strings");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $strings_cache[$row['string_key']] = [
                    'title' => $row['title'],
                    'description' => $row['description']
                ];
            }
        }
    }

    if (isset($strings_cache[$key])) {
        return $strings_cache[$key];
    }

    // Fallback to default if not found
    return [
        'title' => $defaultTitle,
        'description' => $defaultDesc
    ];
}

function saveInterfaceString($key, $title, $description) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO admin_interface_strings (string_key, title, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description)");
    $stmt->bind_param("sss", $key, $title, $description);
    return $stmt->execute();
}
?>
