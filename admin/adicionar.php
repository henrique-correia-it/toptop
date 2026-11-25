<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';

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
    if (!csrf_from_post()) {
        $popupMensagem = "Erro de segurança. Ação não permitida.";
        $popupTipo = "erro";
    } else {
        $ficheiros_criados = [];

        $conn->begin_transaction();
        try {
            $nome = trim($_POST['nome'] ?? '');
            $referencia = trim($_POST['referencia'] ?? '');
            $preco = trim($_POST['preco'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');

            if (empty($nome) || empty($referencia) || empty($preco) || empty($descricao)) {
                throw new DomainException("Os campos Nome, Referência, Preço e Descrição são de preenchimento obrigatório.");
            }

            $preco_float = parsePrecoProduto($preco);
            if ($preco_float === null || $preco_float <= 0) {
                throw new DomainException("O preço deve ser um valor numérico superior a zero.");
            }
            $promo_raw = trim((string)($_POST['preco_promocional'] ?? ''));
            $preco_promocional_db = null;
            if ($promo_raw !== '') {
                $preco_promocional_db = parsePrecoProduto($promo_raw);
                if ($preco_promocional_db === null || $preco_promocional_db <= 0 || $preco_promocional_db >= $preco_float) {
                    throw new DomainException("O preço promocional deve ser positivo e inferior ao preço normal.");
                }
            }
            
            $resultado_validacao = validarProduto($conn, $nome, $referencia);
            if (!$resultado_validacao['valido']) {
                throw new DomainException($resultado_validacao['mensagem']);
            }
            
            $ordem_completa = json_decode($_POST['ordem_imagens_json'] ?? '[]', true);
            $novas_imagens_cortadas_info = json_decode($_POST['imagens_cortadas_json'] ?? '[]', true);

            if (empty($ordem_completa) || !is_array($ordem_completa) || empty($novas_imagens_cortadas_info) || !is_array($novas_imagens_cortadas_info)) {
                throw new DomainException("Deve enviar pelo menos uma imagem.");
            }
            if (count($novas_imagens_cortadas_info) > 5 || count($ordem_completa) > 5) {
                throw new DomainException("Pode enviar no máximo 5 imagens por produto.");
            }
            
            require_once __DIR__ . '/includes/image_upload.php';
            $mapa_placeholder_para_nome = salvar_imagens_base64_produto(
                $novas_imagens_cortadas_info,
                __DIR__ . '/../public/images',
                $ficheiros_criados
            );

            $nomes_ficheiros_finais = [];
            foreach ($ordem_completa as $placeholder) {
                if (is_string($placeholder) && isset($mapa_placeholder_para_nome[$placeholder])) {
                    $nomes_ficheiros_finais[] = $mapa_placeholder_para_nome[$placeholder];
                }
            }
            if (empty($nomes_ficheiros_finais)) {
                throw new DomainException("Deve enviar pelo menos uma imagem válida.");
            }
            $foto_principal_nome = $nomes_ficheiros_finais[0];

            $guia_tamanho_id = !empty($_POST['guia_tamanho_id']) ? (int)$_POST['guia_tamanho_id'] : null;
            $peso_gramas = filter_var($_POST['peso_gramas'] ?? 0, FILTER_VALIDATE_INT);
            if ($peso_gramas === false || $peso_gramas < 0) {
                throw new DomainException("O peso em gramas deve ser um número inteiro igual ou superior a zero.");
            }
            $ativo_post = in_array((string)($_POST['ativo'] ?? '1'), ['0', '1'], true) ? (int)$_POST['ativo'] : 1;
            $categoria_post = trim((string)($_POST['categoria'] ?? ''));
            if ($categoria_post === '') {
                throw new DomainException("A categoria é obrigatória.");
            }
            $atributos_decoded = json_decode((string)($_POST['atributos_json'] ?? '{}'), true);
            if (!is_array($atributos_decoded)) {
                throw new DomainException("Formato de atributos inválido.");
            }
            $atributos_json_post = json_encode($atributos_decoded ?: new stdClass(), JSON_UNESCAPED_UNICODE);

            $variacoes_json = $_POST['variacoes_json'] ?? '[]';
            $variacoes = json_decode($variacoes_json, true);
            if (empty($variacoes) || !is_array($variacoes)) {
                throw new DomainException("O produto deve ter pelo menos uma variação ou stock definido.");
            }
            foreach ($variacoes as $v) {
                if (!is_array($v) || !isset($v['atributos']) || !is_array($v['atributos'])) {
                    throw new DomainException("Dados de variação inválidos.");
                }
                $qtd_check = filter_var($v['quantidade'] ?? null, FILTER_VALIDATE_INT);
                if ($qtd_check === false || $qtd_check < 0) {
                    throw new DomainException("A quantidade de stock deve ser um número inteiro igual ou superior a zero.");
                }
            }

            // Inserir produto
            $stmt_prod = $conn->prepare("INSERT INTO produtos (nome, referencia, ativo, preco, preco_promocional, categoria, descricao, atributos, foto_principal, guia_tamanho_id, peso_gramas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_prod->bind_param("ssiddssssii", $nome, $referencia, $ativo_post, $preco_float, $preco_promocional_db, $categoria_post, $descricao, $atributos_json_post, $foto_principal_nome, $guia_tamanho_id, $peso_gramas);
            $stmt_prod->execute();
            $produto_id = $conn->insert_id;
            $stmt_prod->close();
            
            // Inserir TODAS as imagens em produto_imagens para obter IDs
            $mapa_placeholder_para_id = [];
            if (!empty($nomes_ficheiros_finais)) {
                $stmt_img = $conn->prepare("INSERT INTO produto_imagens (produto_id, nome_ficheiro) VALUES (?, ?)");
                foreach ($ordem_completa as $placeholder) {
                    if (!is_string($placeholder) || !isset($mapa_placeholder_para_nome[$placeholder])) continue;
                    $nome_ficheiro = $mapa_placeholder_para_nome[$placeholder];
                    $stmt_img->bind_param("is", $produto_id, $nome_ficheiro);
                    $stmt_img->execute();
                    $mapa_placeholder_para_id[$placeholder] = $stmt_img->insert_id;
                }
                $stmt_img->close();
            }
            
            // Lógica de Variações
            $stmt_variacao = $conn->prepare("INSERT INTO produto_variacoes (produto_id, atributos, quantidade, referencia) VALUES (?, ?, ?, ?)");
            $stmt_assoc_img = $conn->prepare("INSERT INTO variacao_imagens (variacao_id, imagem_id) VALUES (?, ?)");

            foreach ($variacoes as $v) {
                $atributos_variacao_json = json_encode($v['atributos'], JSON_UNESCAPED_UNICODE);
                $quantidade_variacao = (int)$v['quantidade'];
                $referencia_variacao = !empty($v['referencia']) ? trim((string)$v['referencia']) : null;

                $stmt_variacao->bind_param("isis", $produto_id, $atributos_variacao_json, $quantidade_variacao, $referencia_variacao);
                $stmt_variacao->execute();
                $variacao_id = $stmt_variacao->insert_id;

                if (!empty($v['imagens_associadas']) && is_array($v['imagens_associadas'])) {
                    foreach ($v['imagens_associadas'] as $placeholder) {
                        $imagem_id_a_associar = is_string($placeholder) ? ($mapa_placeholder_para_id[$placeholder] ?? null) : null;
                        if ($imagem_id_a_associar) {
                            $stmt_assoc_img->bind_param("ii", $variacao_id, $imagem_id_a_associar);
                            $stmt_assoc_img->execute();
                        }
                    }
                }
            }
            $stmt_variacao->close();
            $stmt_assoc_img->close();

            $conn->commit();

            // Remove ficheiros criados neste upload que não entraram em $nomes_ficheiros_finais
            $finais_set = array_flip($nomes_ficheiros_finais);
            foreach ($ficheiros_criados as $caminho_criado) {
                if (!isset($finais_set[basename($caminho_criado)]) && is_file($caminho_criado)) {
                    @unlink($caminho_criado);
                }
            }

            $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => 'Produto adicionado com sucesso!'];
            log_app("Produto criado: '{$nome}'.", 'INFO', 'adicionar.php');
            header("Location: " . $return_url);
            exit;

        } catch (DomainException $e) {
            $conn->rollback();
            foreach ($ficheiros_criados as $caminho) {
                if (file_exists($caminho)) @unlink($caminho);
            }
            $popupMensagem = "Erro ao adicionar: " . $e->getMessage();
            $popupTipo = "erro";
        } catch (\Throwable $e) {
            $conn->rollback();
            foreach ($ficheiros_criados as $caminho) {
                if (file_exists($caminho)) @unlink($caminho);
            }
            log_exception($e, 'adicionar.php');
            $popupMensagem = ($e instanceof Exception && !($e instanceof mysqli_sql_exception))
                ? "Erro ao adicionar: " . $e->getMessage()
                : "Ocorreu um erro interno ao guardar o produto. Verifique os dados e tente novamente.";
            $popupTipo = "erro";
        }
    }
}

$grupos_result = $conn->query("SELECT * FROM atributos_grupos ORDER BY nome ASC");
$grupos_disponiveis = $grupos_result->fetch_all(MYSQLI_ASSOC);
$produto = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['nome', 'referencia', 'ativo', 'preco', 'preco_promocional', 'categoria', 'descricao', 'peso_gramas', 'guia_tamanho_id'] as $_campo) {
        if (isset($_POST[$_campo]) && !is_array($_POST[$_campo])) {
            $produto[$_campo] = $_POST[$_campo];
        }
    }
}
$titulo_pagina = "Adicionar Novo Produto";
$action_url = "adicionar.php?return_to=" . urlencode($return_to);
$texto_botao = "Adicionar Produto";

include '../templates/header.php';
include '_form_produto.php';
include '../templates/footer.php';
?>
