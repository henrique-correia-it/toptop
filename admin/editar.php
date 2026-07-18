<?php
require_once __DIR__ . '/../config/session.php';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../config/database.php';
// CORREÇÃO: Usa um caminho absoluto para incluir o ficheiro
include __DIR__ . '/includes/validacao_produto.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: /entrar");
    exit;
}

$paginas_permitidas = ['admin', 'admin_produtos', 'listar_admins'];
$return_to = $_GET['return_to'] ?? 'admin_produtos';
if (!in_array($return_to, $paginas_permitidas)) {
    $return_to = 'admin_produtos';
}
$return_url = htmlspecialchars($return_to) . '.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: admin.php");
    exit;
}

$popupMensagem = "";
$popupTipo = "";

// LÓGICA DE SUBMISSÃO DO FORMULÁRIO (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $popupMensagem = "Erro de segurança. Ação não permitida.";
        $popupTipo = "erro";
    } else {
        $conn->begin_transaction();
        try {
            // --- INÍCIO DA CORREÇÃO DE VALIDAÇÃO ---
            $nome = trim($_POST['nome'] ?? '');
            $referencia = trim($_POST['referencia'] ?? '');
            $preco_post = trim($_POST['preco'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');

            if (empty($nome) || empty($referencia) || empty($preco_post) || empty($descricao)) {
                throw new Exception("Os campos Nome, Referência, Preço e Descrição são de preenchimento obrigatório.");
            }
            // --- FIM DA CORREÇÃO DE VALIDAÇÃO ---

            $preco = (float)$preco_post;
            $preco_promocional = !empty($_POST['preco_promocional']) ? (float)$_POST['preco_promocional'] : NULL;
            if ($preco_promocional !== NULL && $preco_promocional >= $preco) {
                throw new Exception("O preço promocional não pode ser maior ou igual ao preço normal.");
            }
            
            // CORREÇÃO: Utiliza a nova função centralizada para validar
            $resultado_validacao = validarProduto($conn, $nome, $referencia, $id);
            if (!$resultado_validacao['valido']) {
                throw new Exception($resultado_validacao['mensagem']);
            }

            // ===== NOVA LÓGICA DE GESTÃO DE IMAGENS =====

            // 1. Obter imagens atuais da BD para saber os seus IDs e nomes de ficheiro
            $mapa_id_para_nome_antigo = [];
            $stmt_img_antigas = $conn->prepare("SELECT id, nome_ficheiro FROM produto_imagens WHERE produto_id = ?");
            $stmt_img_antigas->bind_param("i", $id);
            $stmt_img_antigas->execute();
            $result_img_antigas = $stmt_img_antigas->get_result();
            while($img = $result_img_antigas->fetch_assoc()) {
                $mapa_id_para_nome_antigo[$img['id']] = $img['nome_ficheiro'];
            }
            $stmt_img_antigas->close();

            // 2. Processar imagens marcadas para apagar (só colecionar — unlink após commit)
            $ficheiros_para_unlink = [];
            $ids_para_apagar = json_decode($_POST['imagens_a_apagar_json'] ?? '[]', true);
            if (!empty($ids_para_apagar)) {
                foreach ($ids_para_apagar as $img_id) {
                    if (isset($mapa_id_para_nome_antigo[$img_id])) {
                        $ficheiros_para_unlink[] = __DIR__ . '/../public/images/' . $mapa_id_para_nome_antigo[$img_id];
                        unset($mapa_id_para_nome_antigo[$img_id]);
                    }
                }
            }

            // 3. Processar novas imagens
            $tipos_imagem_permitidos = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];
            $novas_imagens_info = json_decode($_POST['imagens_cortadas_json'] ?? '[]', true);
            $mapa_placeholder_para_nome_novo = [];
            $ficheiros_novos_criados = [];
            foreach ($novas_imagens_info as $img_info) {
                $placeholder    = $img_info['placeholder'];
                $base64_raw     = preg_replace('#^data:image/\w+;base64,#i', '', $img_info['dados'] ?? '');
                $dados_binarios = base64_decode($base64_raw, true);

                if ($dados_binarios === false || strlen($dados_binarios) < 12) {
                    throw new Exception("Dados de imagem inválidos ou corrompidos.");
                }

                // Valida que o conteúdo binário é realmente uma imagem
                $info_imagem = @getimagesizefromstring($dados_binarios);
                if ($info_imagem === false || !in_array($info_imagem[2], $tipos_imagem_permitidos, true)) {
                    throw new Exception("O ficheiro enviado não é uma imagem válida (JPEG, PNG, WEBP ou GIF).");
                }

                $nome_ficheiro_novo = uniqid('img_', true) . ".jpg";
                $caminho_absoluto   = __DIR__ . '/../public/images/' . $nome_ficheiro_novo;

                if (file_put_contents($caminho_absoluto, $dados_binarios) === false) {
                    throw new Exception("Erro ao guardar a imagem. Verifique as permissões da pasta public/images no servidor.");
                }

                $ficheiros_novos_criados[] = $caminho_absoluto;
                $mapa_placeholder_para_nome_novo[$placeholder] = $nome_ficheiro_novo;
            }

            // 4. Determinar a lista final e a ordem dos nomes de ficheiro
            $ordem_final_js = json_decode($_POST['ordem_imagens_json'] ?? '[]', true);
            $nomes_ficheiros_finais_ordenados = [];
            foreach ($ordem_final_js as $js_id) {
                if (str_starts_with($js_id, 'NEW_')) {
                    if (isset($mapa_placeholder_para_nome_novo[$js_id])) {
                        $nomes_ficheiros_finais_ordenados[] = $mapa_placeholder_para_nome_novo[$js_id];
                    }
                } else {
                    if (isset($mapa_id_para_nome_antigo[$js_id])) {
                        $nomes_ficheiros_finais_ordenados[] = $mapa_id_para_nome_antigo[$js_id];
                    }
                }
            }

            // 5. Sincronizar a tabela `produto_imagens`
            $stmt_del_img = $conn->prepare("DELETE FROM produto_imagens WHERE produto_id = ?");
            $stmt_del_img->bind_param("i", $id);
            $stmt_del_img->execute();
            $stmt_del_img->close();

            // Criar o mapa crucial que liga o ID do JS (antigo ou placeholder) ao novo ID da BD
            $mapa_js_id_para_db_id_novo = [];
            $mapa_nome_para_js_id = [];
            foreach($ordem_final_js as $js_id) {
                $nome_ficheiro = null;
                if (str_starts_with($js_id, 'NEW_')) {
                    $nome_ficheiro = $mapa_placeholder_para_nome_novo[$js_id] ?? null;
                } else {
                    $nome_ficheiro = $mapa_id_para_nome_antigo[$js_id] ?? null;
                }
                if ($nome_ficheiro) {
                    $mapa_nome_para_js_id[$nome_ficheiro] = $js_id;
                }
            }
            
            $stmt_reinsert_img = $conn->prepare("INSERT INTO produto_imagens (produto_id, nome_ficheiro) VALUES (?, ?)");
            foreach ($nomes_ficheiros_finais_ordenados as $nome_ficheiro) {
                $stmt_reinsert_img->bind_param("is", $id, $nome_ficheiro);
                $stmt_reinsert_img->execute();
                $novo_db_id = $stmt_reinsert_img->insert_id;
                
                $js_id_original = $mapa_nome_para_js_id[$nome_ficheiro] ?? null;
                if ($js_id_original) {
                    $mapa_js_id_para_db_id_novo[$js_id_original] = $novo_db_id;
                }
            }
            $stmt_reinsert_img->close();

            // 6. Atualizar a informação principal do produto
            $foto_principal_nova = $nomes_ficheiros_finais_ordenados[0] ?? '';

            // --- INÍCIO DA ALTERAÇÃO ---
            $guia_tamanho_id = !empty($_POST['guia_tamanho_id']) ? (int)$_POST['guia_tamanho_id'] : NULL;
            $peso_gramas = (int)($_POST['peso_gramas'] ?? 0);

            // 6. Atualizar a informação principal do produto
            $stmt_update_prod = $conn->prepare("UPDATE produtos SET nome=?, referencia=?, ativo=?, preco=?, preco_promocional=?, categoria=?, descricao=?, atributos=?, foto_principal=?, guia_tamanho_id=?, peso_gramas=? WHERE id=?");
            $stmt_update_prod->bind_param("ssiddssssiii", $nome, $referencia, $_POST['ativo'], $preco, $preco_promocional, $_POST['categoria'], $descricao, $_POST['atributos_json'], $foto_principal_nova, $guia_tamanho_id, $peso_gramas, $id);
            // --- FIM DA ALTERAÇÃO ---
            $stmt_update_prod->execute();
            $stmt_update_prod->close();

            // 7. Sincronizar as variações e associações de imagens
            $stmt_del_var = $conn->prepare("DELETE FROM produto_variacoes WHERE produto_id = ?");
            $stmt_del_var->bind_param("i", $id);
            $stmt_del_var->execute();
            $stmt_del_var->close();

            $variacoes_js = json_decode($_POST['variacoes_json'] ?? '[]', true);
            if (!empty($variacoes_js)) {
                $stmt_insert_var = $conn->prepare("INSERT INTO produto_variacoes (produto_id, atributos, quantidade, referencia) VALUES (?, ?, ?, ?)");
                $stmt_assoc_img = $conn->prepare("INSERT INTO variacao_imagens (variacao_id, imagem_id) VALUES (?, ?)");

                foreach ($variacoes_js as $v) {
                    $json_v_atributos = json_encode($v['atributos']);
                    $stmt_insert_var->bind_param("isis", $id, $json_v_atributos, $v['quantidade'], $v['referencia']);
                    $stmt_insert_var->execute();
                    $nova_variacao_id = $stmt_insert_var->insert_id;

                    if (!empty($v['imagens_associadas'])) {
                        foreach ($v['imagens_associadas'] as $js_id) {
                            if (isset($mapa_js_id_para_db_id_novo[$js_id])) {
                                $db_imagem_id = $mapa_js_id_para_db_id_novo[$js_id];
                                $stmt_assoc_img->bind_param("ii", $nova_variacao_id, $db_imagem_id);
                                $stmt_assoc_img->execute();
                            }
                        }
                    }
                }
                $stmt_insert_var->close();
                $stmt_assoc_img->close();
            }
            
            $conn->commit();

            // Apagar ficheiros antigos só DEPOIS do commit bem-sucedido
            foreach ($ficheiros_para_unlink as $caminho) {
                if (file_exists($caminho)) @unlink($caminho);
            }

            $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => 'Produto atualizado com sucesso!'];
            header("Location: " . $return_url);
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            // Apagar novos ficheiros criados durante esta tentativa falhada
            foreach ($ficheiros_novos_criados ?? [] as $caminho) {
                if (file_exists($caminho)) @unlink($caminho);
            }
            $popupMensagem = "Erro ao atualizar: " . $e->getMessage();
            $popupTipo = "erro";
        }
    }
}

// LÓGICA PARA MOSTRAR O FORMULÁRIO (GET) - INCLUI A CORREÇÃO ANTERIOR
$stmt_prod = $conn->prepare("SELECT * FROM produtos WHERE id = ?");
$stmt_prod->bind_param("i", $id);
$stmt_prod->execute();
$produto = $stmt_prod->get_result()->fetch_assoc();
if (!$produto) { header("Location: admin.php"); exit; }
$stmt_prod->close();

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($popupMensagem)) {
    // Repõe apenas os campos editáveis do formulário; os restantes (foto_principal,
    // id, atributos, ...) mantêm sempre o valor da base de dados.
    $campos_repopulaveis = ['nome', 'referencia', 'ativo', 'preco', 'preco_promocional', 'categoria', 'descricao', 'peso_gramas', 'guia_tamanho_id'];
    foreach ($campos_repopulaveis as $key) {
        if (isset($_POST[$key]) && is_scalar($_POST[$key])) {
            $produto[$key] = $_POST[$key];
        }
    }
}

$grupos_result = $conn->query("SELECT * FROM atributos_grupos ORDER BY nome ASC");
$grupos_disponiveis = $grupos_result->fetch_all(MYSQLI_ASSOC);

$todas_as_imagens = [];
$foto_principal_atual = (string)($produto['foto_principal'] ?? '');
$stmt_imgs = $conn->prepare("SELECT id, nome_ficheiro FROM produto_imagens WHERE produto_id = ? ORDER BY FIELD(nome_ficheiro, ?) DESC, id ASC");
$stmt_imgs->bind_param("is", $id, $foto_principal_atual);
$stmt_imgs->execute();
$imagens_db = $stmt_imgs->get_result();
while($img = $imagens_db->fetch_assoc()) {
    // O id segue como string para o JS, tal como a query nao-preparada devolvia.
    $todas_as_imagens[] = [ 'id' => (string)$img['id'], 'url' => '/public/images/' . $img['nome_ficheiro'] ];
}
$stmt_imgs->close();
$imagens_iniciais_json = json_encode($todas_as_imagens);

$stmt_variacoes = $conn->prepare("SELECT id, atributos, quantidade, referencia FROM produto_variacoes WHERE produto_id = ?");
$stmt_variacoes->bind_param("i", $id);
$stmt_variacoes->execute();
$result_variacoes = $stmt_variacoes->get_result();
$variacoes_map = [];
while($row_v = $result_variacoes->fetch_assoc()) {
    $row_v['atributos'] = json_decode($row_v['atributos'], true);
    $row_v['imagens_associadas'] = [];
    $variacoes_map[$row_v['id']] = $row_v;
}
$stmt_variacoes->close();

if (!empty($variacoes_map)) {
    $variacao_ids = array_keys($variacoes_map);
    $placeholders = implode(',', array_fill(0, count($variacao_ids), '?'));
    $stmt_assoc = $conn->prepare("SELECT variacao_id, imagem_id FROM variacao_imagens WHERE variacao_id IN ($placeholders)");
    $stmt_assoc->bind_param(str_repeat('i', count($variacao_ids)), ...$variacao_ids);
    $stmt_assoc->execute();
    $result_assoc = $stmt_assoc->get_result();
    while($row_assoc = $result_assoc->fetch_assoc()){
        $variacoes_map[$row_assoc['variacao_id']]['imagens_associadas'][] = (string)$row_assoc['imagem_id'];
    }
    $stmt_assoc->close();
}

$variacoes_guardadas = array_values($variacoes_map);
$variacoes_guardadas_json = json_encode($variacoes_guardadas);
$variacoes_imagens_guardadas_json = '[]';

$titulo_pagina = "Editar Produto: " . htmlspecialchars($produto['nome'] ?? 'Produto não encontrado');
$action_url = "editar.php?id=" . $id . "&return_to=" . htmlspecialchars($return_to);
$texto_botao = "Atualizar Produto";
$atributos_guardados_json = $produto['atributos'] ?? '[]';

include '../templates/header.php';
include '_form_produto.php';
include '../templates/footer.php';
?>
