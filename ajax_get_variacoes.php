<?php
// --- INÍCIO DA PROTEÇÃO ---
// Inicia o buffer de saída. Tudo o que for impresso (echo, erros, HTML) fica guardado na memória
// e não é enviado imediatamente para o navegador.
ob_start();

include __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/http.php';

// 1. Obter e validar o ID do produto a partir do URL
$produto_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Se falhar a validação
if (!$produto_id) {
    ob_end_clean(); // Limpa qualquer lixo anterior
    json_response(['erro' => 'ID de produto inválido.'], 400);
}

$response = [
    'variacoes' => [],
];

try {
    // 2. Ir buscar todas as variações
    $stmt_variacoes = $conn->prepare("SELECT id, atributos, quantidade, preco, referencia FROM produto_variacoes WHERE produto_id = ?");
    $stmt_variacoes->bind_param("i", $produto_id);
    $stmt_variacoes->execute();
    $result_variacoes = $stmt_variacoes->get_result();
    
    $variacoes = [];
    while ($row = $result_variacoes->fetch_assoc()) {
        $row['atributos'] = json_decode($row['atributos'], true);
        $variacoes[$row['id']] = $row; 
    }
    $stmt_variacoes->close();

    // 3. Ir buscar imagens se houver variações
    if (!empty($variacoes)) {
        $variacao_ids = array_keys($variacoes);
        // Verifica se realmente temos IDs antes de criar a query dinâmica
        if (count($variacao_ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($variacao_ids), '?'));
            $types = str_repeat('i', count($variacao_ids));

            $sql_imagens = "SELECT vi.variacao_id, pi.nome_ficheiro 
                            FROM variacao_imagens vi 
                            JOIN produto_imagens pi ON vi.imagem_id = pi.id 
                            WHERE vi.variacao_id IN ($placeholders)";
            
            $stmt_imagens = $conn->prepare($sql_imagens);
            $stmt_imagens->bind_param($types, ...$variacao_ids);
            $stmt_imagens->execute();
            $result_imagens = $stmt_imagens->get_result();

            while ($row_img = $result_imagens->fetch_assoc()) {
                $variacao_id = $row_img['variacao_id'];
                if (isset($variacoes[$variacao_id])) {
                    if (!isset($variacoes[$variacao_id]['imagens'])) {
                        $variacoes[$variacao_id]['imagens'] = [];
                    }
                    $variacoes[$variacao_id]['imagens'][] = $row_img['nome_ficheiro'];
                }
            }
            $stmt_imagens->close();
        }
    }

    $response['variacoes'] = array_values($variacoes);
    $response['sucesso'] = true; // Flag útil para o JS

} catch (Exception $e) {
    // Captura erros silenciosamente
    $response['erro'] = 'Erro interno ao processar variações.';
}

// --- FIM DA PROTEÇÃO ---
// Limpa qualquer aviso, erro de PHP ou espaço em branco que tenha ocorrido antes daqui
ob_end_clean(); 

// Cabeçalhos de Cache (Copiados do teu ficheiro original)
send_no_cache_headers();

json_response($response);
?>
