<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/http.php';
require_once __DIR__ . '/includes/validacao_produto.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

if (!is_admin_logged_in()) {
    json_error('Não autorizado.', 403);
}

if (!csrf_from_post()) {
    json_error('Erro de segurança (CSRF).', 403);
}

$id         = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$nome       = trim((string)($_POST['nome'] ?? ''));
$referencia = trim((string)($_POST['referencia'] ?? ''));
$stock      = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
$preco      = parsePrecoProduto($_POST['preco'] ?? null);

$promo_raw = trim((string)($_POST['preco_promocional'] ?? ''));
$preco_promocional = null;
if ($promo_raw !== '' && $promo_raw !== '0' && $promo_raw !== '0.00' && $promo_raw !== '0,00') {
    $preco_promocional = parsePrecoProduto($promo_raw);
    if ($preco_promocional === null || $preco_promocional <= 0) {
        json_error('O preço promocional introduzido é inválido.', 400);
    }
}

if ($id === false || $id === null || $id <= 0 || $nome === '' || $referencia === '' || $preco === null || $preco <= 0 || $stock === false || $stock === null || $stock < 0) {
    json_error('Dados inválidos ou incompletos.', 400);
}

if ($preco_promocional !== null && $preco_promocional >= $preco) {
    json_error('O preço promocional deve ser inferior ao preço normal.', 400);
}

require_once __DIR__ . '/../config/database.php';

$conn->begin_transaction();

try {
    $validacao = validarProduto($conn, $nome, $referencia, $id);
    if (empty($validacao['valido'])) {
        throw new \DomainException($validacao['mensagem'] ?? 'Nome ou referência duplicados.');
    }

    // Bloquear variações do produto primeiro para validar existência
    $stmt_vars = $conn->prepare("SELECT id, atributos FROM produto_variacoes WHERE produto_id = ? FOR UPDATE");
    $stmt_vars->bind_param("i", $id);
    $stmt_vars->execute();
    $variacoes = $stmt_vars->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_vars->close();

    if (count($variacoes) === 0) {
        try { $conn->rollback(); } catch (\Throwable $ignored) {}
        json_error('Produto ou variação não encontrada.', 404);
    }

    if (count($variacoes) > 1) {
        throw new \DomainException("Este produto tem múltiplas variações. Edite o stock individualmente no painel de edição completo.");
    }

    // 1. Atualizar produto
    $stmt = $conn->prepare("UPDATE produtos SET nome = ?, referencia = ?, preco = ?, preco_promocional = ? WHERE id = ?");
    $stmt->bind_param("ssddi", $nome, $referencia, $preco, $preco_promocional, $id);
    $stmt->execute();
    $stmt->close();

    // 2. Atualizar Stock da única variação
    $var_id = (int)$variacoes[0]['id'];
    $stmt_stock = $conn->prepare("UPDATE produto_variacoes SET quantidade = ? WHERE id = ?");
    $stmt_stock->bind_param("ii", $stock, $var_id);
    $stmt_stock->execute();
    $stmt_stock->close();

    $conn->commit();

    json_success([
        'id'                => $id,
        'nome'              => $nome,
        'referencia'        => $referencia,
        'stock'             => $stock,
        'preco'             => $preco,
        'preco_promocional' => $preco_promocional
    ]);

} catch (\DomainException $e) {
    try { $conn->rollback(); } catch (\Throwable $ignored) {}
    json_error($e->getMessage(), 400);
} catch (\Throwable $e) {
    try { $conn->rollback(); } catch (\Throwable $ignored) {}
    if (function_exists('log_app')) {
        log_app($e->getMessage(), 'ERROR', 'ajax_quick_edit_product.php produto#' . ($id ?? 0));
    }
    json_error('Erro interno ao atualizar produto.', 500);
}
