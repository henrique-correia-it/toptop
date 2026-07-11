<?php
require_once __DIR__ . '/../config/session.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: /entrar");
    exit;
}

include '../config/database.php';
// Inclui a nova função de validação centralizada
include __DIR__ . '/includes/validacao_produto.php';

$paginas_permitidas = ['admin', 'admin_produtos', 'listar_admins'];
$return_to = $_GET['return_to'] ?? 'admin';
if (!in_array($return_to, $paginas_permitidas)) {
    $return_to = 'admin';
}
$return_url = htmlspecialchars($return_to) . '.php';

$popupMensagem = "";
$popupTipo = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $popupMensagem = "Erro de segurança. Ação não permitida.";
        $popupTipo = "erro";
    } else {
        $ficheiros_criados = [];

        $conn->begin_transaction();
        try {
            // --- INÍCIO DA CORREÇÃO DE VALIDAÇÃO ---
            $nome = trim($_POST['nome'] ?? '');
            $referencia = trim($_POST['referencia'] ?? '');
            $preco = trim($_POST['preco'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');

            if (empty($nome) || empty($referencia) || empty($preco) || empty($descricao)) {
                throw new Exception("Os campos Nome, Referência, Preço e Descrição são de preenchimento obrigatório.");
            }
            // --- FIM DA CORREÇÃO DE VALIDAÇÃO ---

            $preco_float = (float)$preco;
            $preco_promocional_db = !empty($_POST['preco_promocional']) ? (float)$_POST['preco_promocional'] : NULL;
            if ($preco_promocional_db !== NULL && $preco_promocional_db >= $preco_float) {
                throw new Exception("O preço promocional não pode ser maior ou igual ao preço normal.");
            }
            
            // CORREÇÃO: Utiliza a nova função centralizada para validar
            $resultado_validacao = validarProduto($conn, $nome, $referencia);
            if (!$resultado_validacao['valido']) {
                throw new Exception($resultado_validacao['mensagem']);
            }
            
            $ordem_completa = json_decode($_POST['ordem_imagens_json'] ?? '[]', true);
            $novas_imagens_cortadas_info = json_decode($_POST['imagens_cortadas_json'] ?? '[]', true);

            if (empty($ordem_completa) || empty($novas_imagens_cortadas_info)) {
                throw new Exception("Deve enviar pelo menos uma imagem.");
            }
            
            $tipos_imagem_permitidos = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];
            $mapa_placeholder_para_nome = [];
            foreach($novas_imagens_cortadas_info as $img_info) {
                $placeholder = $img_info['placeholder'];
                $base64_raw  = preg_replace('#^data:image/\w+;base64,#i', '', $img_info['dados'] ?? '');
                $dados_binarios = base64_decode($base64_raw, true);

                if ($dados_binarios === false || strlen($dados_binarios) < 12) {
                    throw new Exception("Dados de imagem inválidos ou corrompidos.");
                }

                $info_imagem = @getimagesizefromstring($dados_binarios);
                if ($info_imagem === false || !in_array($info_imagem[2], $tipos_imagem_permitidos, true)) {
                    throw new Exception("O ficheiro enviado não é uma imagem válida (JPEG, PNG, WEBP ou GIF).");
                }

                $nome_ficheiro    = uniqid('img_', true) . ".jpg";
                $caminho_absoluto = __DIR__ . '/../public/images/' . $nome_ficheiro;

                if (file_put_contents($caminho_absoluto, $dados_binarios) === false) {
                    throw new Exception("Erro ao guardar a imagem. Verifique as permissões da pasta public/images no servidor.");
                }

                $ficheiros_criados[] = $caminho_absoluto;
                $mapa_placeholder_para_nome[$placeholder] = $nome_ficheiro;
            }

            $nomes_ficheiros_finais = [];
            foreach($ordem_completa as $placeholder) {
                if(isset($mapa_placeholder_para_nome[$placeholder])) {
                    $nomes_ficheiros_finais[] = $mapa_placeholder_para_nome[$placeholder];
                }
            }
            $foto_principal_nome = $nomes_ficheiros_finais[0] ?? '';

            // --- INÍCIO DA ALTERAÇÃO ---
            $guia_tamanho_id = !empty($_POST['guia_tamanho_id']) ? (int)$_POST['guia_tamanho_id'] : NULL;
            $peso_gramas = (int)($_POST['peso_gramas'] ?? 0);

            // Inserir produto
            $stmt_prod = $conn->prepare("INSERT INTO produtos (nome, referencia, ativo, preco, preco_promocional, categoria, descricao, atributos, foto_principal, guia_tamanho_id, peso_gramas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_prod->bind_param("ssiddssssii", $nome, $referencia, $_POST['ativo'], $preco_float, $preco_promocional_db, $_POST['categoria'], $descricao, $_POST['atributos_json'], $foto_principal_nome, $guia_tamanho_id, $peso_gramas);
            // --- FIM DA ALTERAÇÃO ---
            $stmt_prod->execute();
            $produto_id = $conn->insert_id;
            $stmt_prod->close();
            
            // Inserir TODAS as imagens em produto_imagens para obter IDs
            $mapa_placeholder_para_id = [];
            if (!empty($nomes_ficheiros_finais)) {
                $stmt_img = $conn->prepare("INSERT INTO produto_imagens (produto_id, nome_ficheiro) VALUES (?, ?)");
                foreach ($ordem_completa as $placeholder) {
                    $nome_ficheiro = $mapa_placeholder_para_nome[$placeholder];
                    $stmt_img->bind_param("is", $produto_id, $nome_ficheiro);
                    $stmt_img->execute();
                    $mapa_placeholder_para_id[$placeholder] = $stmt_img->insert_id;
                }
                $stmt_img->close();
            }
            
            // Lógica de Variações
            $variacoes_json = $_POST['variacoes_json'] ?? '[]';
            $variacoes = json_decode($variacoes_json, true);

            if (!empty($variacoes) && is_array($variacoes)) {
                $stmt_variacao = $conn->prepare("INSERT INTO produto_variacoes (produto_id, atributos, quantidade, referencia) VALUES (?, ?, ?, ?)");
                $stmt_assoc_img = $conn->prepare("INSERT INTO variacao_imagens (variacao_id, imagem_id) VALUES (?, ?)");

                foreach ($variacoes as $v) {
                    $atributos_variacao_json = json_encode($v['atributos']);
                    $quantidade_variacao = (int)$v['quantidade'];
                    $referencia_variacao = !empty($v['referencia']) ? trim($v['referencia']) : NULL;

                    $stmt_variacao->bind_param("isis", $produto_id, $atributos_variacao_json, $quantidade_variacao, $referencia_variacao);
                    $stmt_variacao->execute();
                    $variacao_id = $stmt_variacao->insert_id;

                    if (!empty($v['imagens_associadas'])) {
                        foreach ($v['imagens_associadas'] as $placeholder) {
                            $imagem_id_a_associar = $mapa_placeholder_para_id[$placeholder] ?? null;
                            if ($imagem_id_a_associar) {
                                $stmt_assoc_img->bind_param("ii", $variacao_id, $imagem_id_a_associar);
                                $stmt_assoc_img->execute();
                            }
                        }
                    }
                }
                $stmt_variacao->close();
                $stmt_assoc_img->close();
            }

            $conn->commit();
            $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => 'Produto adicionado com sucesso!'];
            header("Location: " . $return_url);
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            foreach ($ficheiros_criados as $caminho) {
                if (file_exists($caminho)) @unlink($caminho);
            }
            $popupMensagem = "Erro ao adicionar: " . $e->getMessage();
            $popupTipo = "erro";
        }
    }
}

$grupos_result = $conn->query("SELECT * FROM atributos_grupos ORDER BY nome ASC");
$grupos_disponiveis = $grupos_result->fetch_all(MYSQLI_ASSOC);
$produto = $_POST;
$titulo_pagina = "Adicionar Novo Produto";
$action_url = "adicionar.php?return_to=" . htmlspecialchars($_GET['return_to'] ?? 'admin');
$texto_botao = "Adicionar Produto";

include '../templates/header.php';
include '_form_produto.php';
include '../templates/footer.php';
?>
