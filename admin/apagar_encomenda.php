<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

// --- VALIDAÇÃO DE SEGURANÇA (CSRF, MÉTODO, SESSÃO) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Acesso inválido.');
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    exit('Erro de validação de segurança. Ação não permitida.');
}
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    exit('Acesso não autorizado.');
}

// Validar o ID da encomenda
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: encomendas.php");
    exit;
}

// AVISO: Esta operação apaga a encomenda mas NÃO repõe o stock dos produtos.
// Isto é intencional para manter um registo, mas pode ser alterado se necessário.
$conn->begin_transaction();
try {
    // 1. Obter a lista de fotos usadas nesta encomenda antes de apagar os itens
    $stmt_get_photos = $conn->prepare("SELECT DISTINCT foto_snapshot FROM encomenda_itens WHERE encomenda_id = ? AND foto_snapshot IS NOT NULL");
    $stmt_get_photos->bind_param("i", $id);
    $stmt_get_photos->execute();
    $result_photos = $stmt_get_photos->get_result();
    $fotos_da_encomenda = [];
    while ($row = $result_photos->fetch_assoc()) {
        $fotos_da_encomenda[] = $row['foto_snapshot'];
    }
    $stmt_get_photos->close();

    // 2. Apaga os itens da encomenda primeiro
    $stmt_delete_itens = $conn->prepare("DELETE FROM encomenda_itens WHERE encomenda_id = ?");
    $stmt_delete_itens->bind_param("i", $id);
    $stmt_delete_itens->execute();
    $stmt_delete_itens->close();

    // 3. Limpeza Inteligente de Ficheiros
    // Preparamos as queries de verificação
    $stmt_check_orders = $conn->prepare("SELECT COUNT(*) as total FROM encomenda_itens WHERE foto_snapshot = ?");
    $stmt_check_products = $conn->prepare("SELECT COUNT(*) as total FROM produtos WHERE foto_principal = ?");
    $stmt_check_galeria = $conn->prepare("SELECT COUNT(*) as total FROM produto_imagens WHERE nome_ficheiro = ?");

    foreach ($fotos_da_encomenda as $foto) {
        if (empty($foto) || $foto === 'default.jpg') continue;

        // Verificar uso em outras encomendas
        $stmt_check_orders->bind_param("s", $foto);
        $stmt_check_orders->execute();
        $uso_encomendas = $stmt_check_orders->get_result()->fetch_assoc()['total'];

        // Verificar uso em produtos (foto principal)
        $stmt_check_products->bind_param("s", $foto);
        $stmt_check_products->execute();
        $uso_produtos = $stmt_check_products->get_result()->fetch_assoc()['total'];

        // Verificar uso em galeria de imagens
        $stmt_check_galeria->bind_param("s", $foto);
        $stmt_check_galeria->execute();
        $uso_galeria = $stmt_check_galeria->get_result()->fetch_assoc()['total'];

        // Só apaga se não for usada em NENHUM outro lugar
        if ($uso_encomendas == 0 && $uso_produtos == 0 && $uso_galeria == 0) {
            $caminho = '../public/images/' . $foto;
            if (file_exists($caminho) && is_file($caminho)) {
                unlink($caminho);
            }
        }
    }
    $stmt_check_orders->close();
    $stmt_check_products->close();
    $stmt_check_galeria->close();

    // 4. Apaga a encomenda principal
    $stmt_delete_enc = $conn->prepare("DELETE FROM encomendas WHERE id = ?");
    $stmt_delete_enc->bind_param("i", $id);
    $stmt_delete_enc->execute();

    if ($stmt_delete_enc->affected_rows === 0) {
        throw new Exception("A encomenda com ID " . $id . " não foi encontrada.");
    }
    $stmt_delete_enc->close();

    $conn->commit();
    $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => 'Encomenda #' . $id . ' apagada permanentemente com sucesso!'];

} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage());
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Ocorreu um erro ao apagar a encomenda.'];
}

$return_to = $_POST['return_to'] ?? 'encomendas.php';
header("Location: " . $return_to);
exit;
?>
