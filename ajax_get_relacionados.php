<?php
ob_start();

include __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/http.php';

$categoria = htmlspecialchars($_GET['categoria'] ?? '', ENT_QUOTES, 'UTF-8');
$id_atual = filter_input(INPUT_GET, 'id_atual', FILTER_VALIDATE_INT);

$produtos_relacionados = [];

try {
    if (!empty($categoria) && $id_atual > 0) {
        $stmt = $conn->prepare(
            "SELECT id, nome, preco, preco_promocional, foto_principal
            FROM produtos
            WHERE categoria = ? AND id != ? AND ativo = 1
            ORDER BY id DESC
            LIMIT 4"
        );
        $stmt->bind_param("si", $categoria, $id_atual);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $produtos_relacionados[] = $row;
        }

        $stmt->close();
    }
} catch (Exception $e) {
    // Silencia erros para manter a resposta JSON limpa.
}

ob_end_clean();

send_no_cache_headers();

json_response($produtos_relacionados);
?>
