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
    $stmt_imgs = $conn->prepare("SELECT nome_ficheiro FROM produto_imagens WHERE produto_id IN ($placeholders)");
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

    // 2. Apagar os registos dos produtos da base de dados
    $stmt_delete = $conn->prepare("DELETE FROM produtos WHERE id IN ($placeholders)");
    $stmt_delete->bind_param($types, ...$ids_para_apagar);
    $stmt_delete->execute();

    $linhas_afetadas = $stmt_delete->affected_rows;
    $stmt_delete->close();

    if ($linhas_afetadas === 0) {
        throw new Exception("Nenhum dos produtos selecionados foi encontrado para apagar.");
    }

    // 3. Confirmar e só depois apagar ficheiros do disco
    $conn->commit();

    foreach ($imagens_para_apagar as $ficheiro) {
        $caminho = __DIR__ . '/../public/images/' . $ficheiro;
        if (file_exists($caminho) && is_file($caminho)) {
            @unlink($caminho);
        }
    }

    $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => $linhas_afetadas . ' produto(s) apagado(s) com sucesso!'];

} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage()); 
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Ocorreu um erro ao apagar os produtos. Tente novamente.'];
}

header("Location: admin_produtos.php");
exit;
?>
