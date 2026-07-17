<?php
require_once __DIR__ . '/../config/session.php';

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

// Verifica se foram enviados IDs para apagar
if (!isset($_POST['ids_produtos']) || !is_array($_POST['ids_produtos']) || empty($_POST['ids_produtos'])) {
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Nenhum produto foi selecionado.'];
    header("Location: admin_produtos.php");
    exit;
}

include '../config/database.php';

// Limpa os IDs para garantir que são apenas números inteiros
$ids_para_apagar = array_map('intval', $_POST['ids_produtos']);
$placeholders = implode(',', array_fill(0, count($ids_para_apagar), '?'));
$types = str_repeat('i', count($ids_para_apagar));

$erros = [];
$conn->begin_transaction();
try {
    // 1. Obter todos os nomes de ficheiro de imagem para os produtos selecionados
    $stmt_imgs = $conn->prepare("SELECT DISTINCT nome_ficheiro FROM produto_imagens WHERE produto_id IN ($placeholders)");
    $stmt_imgs->bind_param($types, ...$ids_para_apagar);
    $stmt_imgs->execute();
    $result_imgs = $stmt_imgs->get_result();
    
    $imagens_para_apagar = [];
    while ($row = $result_imgs->fetch_assoc()) {
        if (!empty($row['nome_ficheiro'])) {
            $imagens_para_apagar[] = $row['nome_ficheiro'];
        }
    }
    $stmt_imgs->close();

    // Guardar esta informação antes do DELETE. Assim, mesmo que alguma relação
    // da base de dados use ON DELETE, as imagens históricas ficam protegidas.
    $imagens_usadas_encomendas = [];
    $stmt_check_orders = $conn->prepare("SELECT COUNT(*) AS total FROM encomenda_itens WHERE foto_snapshot = ?");
    foreach ($imagens_para_apagar as $ficheiro) {
        $stmt_check_orders->bind_param("s", $ficheiro);
        $stmt_check_orders->execute();
        if ((int)$stmt_check_orders->get_result()->fetch_assoc()['total'] > 0) {
            $imagens_usadas_encomendas[$ficheiro] = true;
        }
    }
    $stmt_check_orders->close();

    // 2. Apagar os registos dos produtos da base de dados
    $stmt_delete = $conn->prepare("DELETE FROM produtos WHERE id IN ($placeholders)");
    $stmt_delete->bind_param($types, ...$ids_para_apagar);
    $stmt_delete->execute();

    $linhas_afetadas = $stmt_delete->affected_rows;
    $stmt_delete->close();

    if ($linhas_afetadas === 0) {
        throw new Exception("Nenhum dos produtos selecionados foi encontrado para apagar.");
    }

    // 3. Confirmar a eliminação antes de tratar dos ficheiros no disco
    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage()); 
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Ocorreu um erro ao apagar os produtos. Tente novamente.'];
    header("Location: admin_produtos.php");
    exit;
}

// 4. Apagar apenas imagens que já não sejam usadas em nenhum local.
// Esta limpeza ocorre depois do commit: uma falha no disco nunca contradiz
// uma eliminação que a base de dados já confirmou.
try {
    $stmt_check_products = $conn->prepare("SELECT COUNT(*) AS total FROM produtos WHERE foto_principal = ?");
    $stmt_check_gallery = $conn->prepare("SELECT COUNT(*) AS total FROM produto_imagens WHERE nome_ficheiro = ?");

    foreach ($imagens_para_apagar as $ficheiro) {
        if (empty($ficheiro) || $ficheiro === 'default.jpg' || basename($ficheiro) !== $ficheiro) {
            continue;
        }

        $stmt_check_products->bind_param("s", $ficheiro);
        $stmt_check_products->execute();
        $uso_produtos = (int)$stmt_check_products->get_result()->fetch_assoc()['total'];

        $stmt_check_gallery->bind_param("s", $ficheiro);
        $stmt_check_gallery->execute();
        $uso_galeria = (int)$stmt_check_gallery->get_result()->fetch_assoc()['total'];

        if (!isset($imagens_usadas_encomendas[$ficheiro]) && $uso_produtos === 0 && $uso_galeria === 0) {
            $caminho = __DIR__ . '/../public/images/' . $ficheiro;
            if (is_file($caminho) && !unlink($caminho)) {
                log_app('Não foi possível remover a imagem: ' . $ficheiro, 'WARNING', 'apagar_massa.php');
            }
        }
    }

    $stmt_check_products->close();
    $stmt_check_gallery->close();
} catch (Throwable $e) {
    // Na dúvida, conservar os ficheiros. O produto já foi removido com sucesso.
    log_app($e->getMessage(), 'WARNING', 'apagar_massa.php limpeza de imagens');
}

$_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => $linhas_afetadas . ' produto(s) apagado(s) com sucesso!'];

header("Location: admin_produtos.php");
exit;
?>
