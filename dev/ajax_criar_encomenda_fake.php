<?php
// admin/ajax_criar_encomenda_fake.php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || $_SESSION['admin_role'] !== 'desenvolvedor') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método inválido.']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!hash_equals($_SESSION['csrf_token'] ?? '', $dados['csrf_token'] ?? '')) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'CSRF inválido.']);
    exit;
}

$estados_permitidos = ['pago', 'a aguardar pagamento', 'incompleta', 'em processamento', 'enviada', 'pronta para levantamento', 'concluida', 'cancelada', 'pagamento na entrega'];
$estado = $dados['estado'] ?? 'pago';
if (!in_array($estado, $estados_permitidos, true)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Estado inválido.']);
    exit;
}

try {
    $conn->begin_transaction();

    // 1. Selecionar uma variação aleatória de um produto ativo
    $sql_prod = "SELECT p.id as produto_id, p.nome, p.preco, p.preco_promocional, p.foto_principal, pv.id as variacao_id, pv.preco as preco_variacao, pv.atributos
                 FROM produtos p 
                 JOIN produto_variacoes pv ON p.id = pv.produto_id 
                 WHERE p.ativo = 1 AND pv.quantidade > 0 
                 ORDER BY RAND() LIMIT 1";
    $res_prod = $conn->query($sql_prod);
    
    if (!$res_prod || $res_prod->num_rows === 0) {
        throw new Exception("Não foram encontrados produtos ativos com stock para criar a encomenda.");
    }
    
    $prod = $res_prod->fetch_assoc();

    $preco_unitario = (!empty($prod['preco_variacao']) && (float)$prod['preco_variacao'] > 0) 
        ? (float)$prod['preco_variacao'] 
        : ((!empty($prod['preco_promocional']) && (float)$prod['preco_promocional'] > 0) 
            ? (float)$prod['preco_promocional'] 
            : (float)$prod['preco']);
            
    $quantidade = 1;
    $portes = 4.50; 
    $total = $preco_unitario * $quantidade;
    $token = bin2hex(random_bytes(32));
    $data_agora = date('Y-m-d H:i:s');

    // 2. Inserir Encomenda
    $stmt_enc = $conn->prepare(
        "INSERT INTO encomendas (cliente_nome, cliente_email, cliente_telefone, cliente_morada, metodo_entrega, metodo_pagamento, total, portes_envio, token, estado, data_encomenda)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    $nome = "Dev Test User " . rand(100, 999);
    $email = "dev_test_" . rand(100, 999) . "@toptop.pt";
    $tel = "912345678";
    $morada = "Rua do Desenvolvedor, 123";
    $entrega = "envio";
    $pagamento = "MB WAY (Dev)";
    
    $stmt_enc->bind_param("ssssssdssss", $nome, $email, $tel, $morada, $entrega, $pagamento, $total, $portes, $token, $estado, $data_agora);
    $stmt_enc->execute();
    $encomenda_id = $conn->insert_id;
    $stmt_enc->close();

    // 3. Inserir Item
    $stmt_item = $conn->prepare(
        "INSERT INTO encomenda_itens (encomenda_id, produto_id, variacao_id, nome_produto, foto_snapshot, selecoes_atributos, quantidade, preco_unitario)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    $foto = $prod['foto_principal'] ?: 'default.jpg';
    $atributos = $prod['atributos'] ?: '{}';
    
    $stmt_item->bind_param("iiisssid", $encomenda_id, $prod['produto_id'], $prod['variacao_id'], $prod['nome'], $foto, $atributos, $quantidade, $preco_unitario);
    $stmt_item->execute();
    $stmt_item->close();

    $conn->commit();
    echo json_encode(['sucesso' => true, 'mensagem' => 'Encomenda fake #' . $encomenda_id . ' criada com sucesso!', 'id' => $encomenda_id]);

} catch (Exception $e) {
    if ($conn && $conn->in_transaction) $conn->rollback();
    log_app($e->getMessage(), 'ERROR', 'ajax_criar_encomenda_fake.php');
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}
