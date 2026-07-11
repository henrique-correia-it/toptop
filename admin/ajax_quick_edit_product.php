<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/http.php';

if (!is_admin_logged_in()) {
    json_error('Não autorizado.', 403);
}

$id                = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$nome              = trim($_POST['nome'] ?? '');
$referencia        = trim($_POST['referencia'] ?? '');
$stock             = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
$preco             = filter_input(INPUT_POST, 'preco', FILTER_VALIDATE_FLOAT);
$preco_promocional = (!empty($_POST['preco_promocional']) && (float)$_POST['preco_promocional'] > 0) ? (float)$_POST['preco_promocional'] : NULL;
$csrf_token        = $_POST['csrf_token'] ?? '';

if (!csrf_verify($csrf_token)) {
    json_error('Erro de segurança (CSRF).', 403);
}

if (!$id || empty($nome) || empty($referencia) || $preco === false) {
    json_error('Dados inválidos ou incompletos.', 400);
}

if ($preco_promocional !== NULL && $preco_promocional >= $preco) {
    json_error('O preço promocional deve ser inferior ao preço normal.', 400);
}

$conn->begin_transaction();

try {
    // 1. Atualizar produto
    $stmt = $conn->prepare("UPDATE produtos SET nome = ?, referencia = ?, preco = ?, preco_promocional = ? WHERE id = ?");
    $stmt->bind_param("ssddi", $nome, $referencia, $preco, $preco_promocional, $id);
    $stmt->execute();
    $stmt->close();

    // 2. Atualizar Stock
    // Verificamos quantas variações o produto tem
    $res_vars = $conn->query("SELECT id, atributos FROM produto_variacoes WHERE produto_id = $id");
    $variacoes = $res_vars->fetch_all(MYSQLI_ASSOC);

    if (count($variacoes) === 1) {
        // Produto simples: atualizamos a única variação
        $var_id = $variacoes[0]['id'];
        $stmt_stock = $conn->prepare("UPDATE produto_variacoes SET quantidade = ? WHERE id = ?");
        $stmt_stock->bind_param("ii", $stock, $var_id);
        $stmt_stock->execute();
        $stmt_stock->close();
    } else {
        // Produto complexo: não permitimos editar stock total via quick edit se houver ambiguidade
        // No entanto, para não dar erro, vamos apenas ignorar a atualização do stock ou avisar.
        // A pedido do utilizador ("Stock"), vamos assumir que ele quer editar.
        // Se houver múltiplas, vamos dar erro para evitar corromper dados de inventário.
        throw new Exception("Este produto tem múltiplas variações. Edite o stock individualmente no painel de edição completo.");
    }

    $conn->commit();

    json_success([
        'id'                => $id,
        'nome'              => $nome,
        'referencia'        => $referencia,
        'stock'             => $stock,
        'preco'             => $preco,
        'preco_promocional' => $preco_promocional
    ]);

} catch (Exception $e) {
    $conn->rollback();
    log_app($e->getMessage(), 'ERROR', 'ajax_quick_edit_product.php produto#' . ($id ?? 0));
    json_error($e->getMessage(), 500);
}
