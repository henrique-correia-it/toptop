<?php
require_once __DIR__ . '/../config/session.php';

// --- VALIDAÇÃO DE SEGURANÇA (CSRF e MÉTODO) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Acesso inválido.');
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    exit('Erro de validação de segurança. Ação não permitida.');
}

// --- VALIDAÇÃO DE SESSÃO E PERMISSÕES ---
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    exit('Acesso não autorizado.');
}

include '../config/database.php';

// Validar o ID do produto
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: admin_produtos.php");
    exit;
}

// Inicia a transação para garantir que a operação é "tudo ou nada"
$conn->begin_transaction();
try {
    // --- INÍCIO DA CORREÇÃO ---
    // 1. Obter a lista de TODOS os nomes de ficheiro de imagem a partir de UMA SÓ FONTE (`produto_imagens`)
    $stmt_imgs = $conn->prepare("SELECT nome_ficheiro FROM produto_imagens WHERE produto_id = ?");
    $stmt_imgs->bind_param("i", $id);
    $stmt_imgs->execute();
    $result_imgs = $stmt_imgs->get_result();
    
    $imagens_para_apagar = [];
    while ($row = $result_imgs->fetch_assoc()) {
        if (!empty($row['nome_ficheiro'])) {
            $imagens_para_apagar[] = $row['nome_ficheiro'];
        }
    }
    $stmt_imgs->close();
    // A lista agora é única por natureza, pois vem de uma única consulta.
    // --- FIM DA CORREÇÃO ---

    // 2. Apagar os ficheiros de imagem do servidor (APENAS SE NÃO FOREM USADOS EM OUTRO LUGAR)
    // Preparamos as queries de verificação cruzada
    $stmt_check_orders = $conn->prepare("SELECT COUNT(*) as total FROM encomenda_itens WHERE foto_snapshot = ?");
    $stmt_check_products = $conn->prepare("SELECT COUNT(*) as total FROM produtos WHERE foto_principal = ? AND id != ?");
    $stmt_check_galeria = $conn->prepare("SELECT COUNT(*) as total FROM produto_imagens WHERE nome_ficheiro = ? AND produto_id != ?");
    
    foreach ($imagens_para_apagar as $ficheiro) {
        if (empty($ficheiro) || $ficheiro === 'default.jpg') continue;

        // Verificar uso em encomendas
        $stmt_check_orders->bind_param("s", $ficheiro);
        $stmt_check_orders->execute();
        $uso_encomendas = $stmt_check_orders->get_result()->fetch_assoc()['total'];

        // Verificar uso noutros produtos (foto principal)
        $stmt_check_products->bind_param("si", $ficheiro, $id);
        $stmt_check_products->execute();
        $uso_produtos = $stmt_check_products->get_result()->fetch_assoc()['total'];

        // Verificar uso noutras galerias
        $stmt_check_galeria->bind_param("si", $ficheiro, $id);
        $stmt_check_galeria->execute();
        $uso_galeria = $stmt_check_galeria->get_result()->fetch_assoc()['total'];
        
        // Só apaga se uso total for ZERO em todo o lado
        if ($uso_encomendas == 0 && $uso_produtos == 0 && $uso_galeria == 0) {
            $caminho = '../public/images/' . $ficheiro;
            if (file_exists($caminho) && is_file($caminho)) {
                unlink($caminho);
            }
        }
    }
    $stmt_check_orders->close();
    $stmt_check_products->close();
    $stmt_check_galeria->close();

    // 3. Apagar o registo do produto da base de dados.
    // A regra "ON DELETE CASCADE" na base de dados deverá tratar de apagar os registos
    // das tabelas 'produto_imagens', 'produto_variacoes', etc.
    $stmt_delete = $conn->prepare("DELETE FROM produtos WHERE id = ?");
    $stmt_delete->bind_param("i", $id);
    $stmt_delete->execute();
    
    // Verifica se a linha foi realmente apagada
    if ($stmt_delete->affected_rows === 0) {
        throw new Exception("O produto com ID " . $id . " não foi encontrado para apagar.");
    }
    $stmt_delete->close();

    // Se tudo correu bem, confirma as alterações na base de dados
    $conn->commit();

} catch (Exception $e) {
    // Se algo correu mal em qualquer ponto, desfaz todas as alterações
    $conn->rollback();
    log_app($e->getMessage(), 'ERROR', 'apagar.php produto#' . ($id ?? 0));
    error_log($e->getMessage());
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Ocorreu um erro ao apagar o produto. Verifique as permissões da pasta de imagens.'];
    header("Location: admin_produtos.php");
    exit;
}

// Redireciona de volta para a lista de produtos com sucesso
$_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => 'Produto apagado com sucesso!'];
header("Location: admin_produtos.php");
exit;
?>
