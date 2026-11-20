<?php
ob_start();

include __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/http.php';

$categoria = trim((string)($_GET['categoria'] ?? ''));
$id_atual = filter_input(INPUT_GET, 'id_atual', FILTER_VALIDATE_INT);

$produtos_relacionados = [];

try {
    if ($categoria !== '' && $id_atual > 0) {
        $stmt = $conn->prepare(
            "SELECT p.id, p.nome, p.preco, p.preco_promocional, p.foto_principal
            FROM produtos p
            WHERE p.categoria = ? AND p.id != ? AND p.ativo = 1
              AND EXISTS (SELECT 1 FROM produto_variacoes pv WHERE pv.produto_id = p.id AND pv.quantidade > 0)
            ORDER BY p.id DESC
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
