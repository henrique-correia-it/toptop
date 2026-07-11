<?php
// loja-roupa/admin/ajax_atualizar_encomenda.php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

$response = ['sucesso' => false, 'mensagem' => 'Ocorreu um erro inesperado.'];
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Acesso inválido.');
    }

    $dados = json_decode(file_get_contents('php://input'), true);

    if (!isset($_SESSION['admin_logado']) || !isset($dados['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $dados['csrf_token'])) {
        throw new Exception('Erro de validação de segurança.');
    }

    $encomenda_id = (int)($dados['encomenda_id'] ?? 0);
    $novos_itens = $dados['itens'] ?? [];

    if ($encomenda_id <= 0) {
        throw new Exception('ID da encomenda inválido.');
    }

    $conn->begin_transaction();

    // 1. Obter a encomenda e verificar se pode ser editada
    $stmt = $conn->prepare("SELECT estado FROM encomendas WHERE id = ?");
    $stmt->bind_param("i", $encomenda_id);
    $stmt->execute();
    $encomenda = $stmt->get_result()->fetch_assoc();
    if (!$encomenda) {
        throw new Exception('Encomenda não encontrada.');
    }
    if (!in_array($encomenda['estado'], ['pendente', 'a aguardar pagamento'])) {
        throw new Exception('Apenas encomendas pendentes ou a aguardar pagamento podem ser editadas.');
    }

    // 2. Obter os itens atuais para calcular a diferença de stock
    $stmt = $conn->prepare("SELECT variacao_id, quantidade FROM encomenda_itens WHERE encomenda_id = ?");
    $stmt->bind_param("i", $encomenda_id);
    $stmt->execute();
    $itens_atuais_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itens_atuais = array_column($itens_atuais_result, 'quantidade', 'variacao_id');
    
    // 3. Devolver o stock dos itens antigos
    $stmt_stock = $conn->prepare("UPDATE produto_variacoes SET quantidade = quantidade + ? WHERE id = ?");
    foreach ($itens_atuais as $variacao_id => $quantidade) {
        $stmt_stock->bind_param("ii", $quantidade, $variacao_id);
        $stmt_stock->execute();
    }

    // 4. Apagar todos os itens antigos da encomenda
    $stmt = $conn->prepare("DELETE FROM encomenda_itens WHERE encomenda_id = ?");
    $stmt->bind_param("i", $encomenda_id);
    $stmt->execute();

    // 5. Abater o stock e inserir os novos itens
    $novo_total = 0;
    $stmt_stock->bind_param("ii", $quantidade, $variacao_id); // Re-bind para a nova operação
    $stmt_item = $conn->prepare("INSERT INTO encomenda_itens (encomenda_id, produto_id, variacao_id, nome_produto, selecoes_atributos, quantidade, preco_unitario) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($novos_itens as $item) {
        $quantidade = (int)$item['quantidade'];
        $variacao_id = (int)$item['variacao_id'];

        // Verificar stock do novo item
        $stmt_check = $conn->prepare("SELECT quantidade FROM produto_variacoes WHERE id = ?");
        $stmt_check->bind_param("i", $variacao_id);
        $stmt_check->execute();
        $stock_disponivel = $stmt_check->get_result()->fetch_assoc()['quantidade'];
        if ($quantidade > $stock_disponivel) {
            throw new Exception("Stock insuficiente para o produto '" . htmlspecialchars($item['nome']) . "'. Apenas " . $stock_disponivel . " disponíveis.");
        }

        // Abater stock
        $stmt_stock->execute();
        
        // Inserir item
        $stmt_item->bind_param("iiissid", $encomenda_id, $item['produto_id'], $variacao_id, $item['nome'], json_encode($item['selecoes']), $quantidade, $item['preco_unitario']);
        $stmt_item->execute();
        
        $novo_total += (float)$item['preco_unitario'] * $quantidade;
    }

    // 6. Atualizar o total da encomenda e resetar os portes (para serem recalculados)
    $stmt = $conn->prepare("UPDATE encomendas SET total = ?, portes_envio = NULL, estado = 'pendente' WHERE id = ?");
    $stmt->bind_param("di", $novo_total, $encomenda_id);
    $stmt->execute();

    // 7. Adicionar evento ao histórico
    $evento = [
        'data' => date('Y-m-d H:i:s'),
        'mensagem' => 'Itens da encomenda foram editados pela proprietária. O estado foi reposto para "Pendente" para novo cálculo de portes.',
        'tipo' => 'info'
    ];
    $stmt_hist = $conn->prepare("SELECT historico_eventos FROM encomendas WHERE id = ?");
    $stmt_hist->bind_param("i", $encomenda_id);
    $stmt_hist->execute();
    $historico_json = $stmt_hist->get_result()->fetch_assoc()['historico_eventos'];
    $historico = $historico_json ? json_decode($historico_json, true) : [];
    $historico[] = $evento;
    $stmt_update = $conn->prepare("UPDATE encomendas SET historico_eventos = ? WHERE id = ?");
    $stmt_update->bind_param("si", json_encode($historico), $encomenda_id);
    $stmt_update->execute();

    $conn->commit();
    $response['sucesso'] = true;
    $response['mensagem'] = 'Encomenda atualizada com sucesso!';
    $response['redirect_url'] = 'detalhes_encomenda.php?id=' . $encomenda_id;
    $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => $response['mensagem']];

} catch (Exception $e) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    log_app($e->getMessage(), 'ERROR', 'ajax_atualizar_encomenda.php enc#' . ($encomenda_id ?? 0));
    $response['mensagem'] = $e->getMessage();
}

echo json_encode($response);
exit;
