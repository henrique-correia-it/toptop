<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';
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
    if (!csrf_from_post()) {
        $popupMensagem = "Erro de segurança. Ação não permitida.";
        $popupTipo = "erro";
    } else {
        $ficheiros_novos_criados = [];
        $conn->begin_transaction();
        try {
            $nome = trim($_POST['nome'] ?? '');
            $referencia = trim($_POST['referencia'] ?? '');
            $preco_post = trim($_POST['preco'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');

            if (empty($nome) || empty($referencia) || empty($preco_post) || empty($descricao)) {
                throw new DomainException("Os campos Nome, Referência, Preço e Descrição são de preenchimento obrigatório.");
            }

            $preco = parsePrecoProduto($preco_post);
            if ($preco === null || $preco <= 0) {
                throw new DomainException("O preço deve ser um valor numérico superior a zero.");
            }
            $promo_raw = trim((string)($_POST['preco_promocional'] ?? ''));
            $preco_promocional = null;
            if ($promo_raw !== '') {
                $preco_promocional = parsePrecoProduto($promo_raw);
                if ($preco_promocional === null || $preco_promocional <= 0 || $preco_promocional >= $preco) {
                    throw new DomainException("O preço promocional deve ser positivo e inferior ao preço normal.");
                }
            }
            
            $resultado_validacao = validarProduto($conn, $nome, $referencia, $id);
            if (!$resultado_validacao['valido']) {
                throw new DomainException($resultado_validacao['mensagem']);
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

            // 2. Processar imagens marcadas para apagar (só colecionar - unlink após commit)
            $ficheiros_para_unlink = [];
            $ids_para_apagar = json_decode($_POST['imagens_a_apagar_json'] ?? '[]', true);
            if (!empty($ids_para_apagar)) {
                foreach ($ids_para_apagar as $img_id) {
                    if (isset($mapa_id_para_nome_antigo[$img_id])) {
                        $ficheiros_para_unlink[] = basename($mapa_id_para_nome_antigo[$img_id]);
                        unset($mapa_id_para_nome_antigo[$img_id]);
                    }
                }
            }

            // 3. Processar novas imagens (helper DRY)
            require_once __DIR__ . '/includes/image_upload.php';
            $novas_imagens_info = json_decode($_POST['imagens_cortadas_json'] ?? '[]', true);
            $ficheiros_novos_criados = [];
            $mapa_placeholder_para_nome_novo = is_array($novas_imagens_info)
                ? salvar_imagens_base64_produto($novas_imagens_info, __DIR__ . '/../public/images', $ficheiros_novos_criados)
                : [];

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

            // 5. Garantir pelo menos 1 imagem e no máximo 5, e sincronizar `produto_imagens`
            if (empty($nomes_ficheiros_finais_ordenados)) {
                throw new Exception("O produto deve ter pelo menos uma imagem.");
            }
            if (count($nomes_ficheiros_finais_ordenados) > 5) {
                throw new Exception("O produto pode ter no máximo 5 imagens.");
            }
            // Colecionar também imagens antigas omitidas da ordem final para evitar órfãos no disco
            $nomes_finais_set = array_flip($nomes_ficheiros_finais_ordenados);
            foreach ($mapa_id_para_nome_antigo as $nome_antigo) {
                if (!isset($nomes_finais_set[$nome_antigo])) {
                    $ficheiros_para_unlink[] = basename($nome_antigo);
                }
            }
            $ficheiros_para_unlink = array_values(array_unique($ficheiros_para_unlink));
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

            $variacoes_js = json_decode($_POST['variacoes_json'] ?? '[]', true);
            if (empty($variacoes_js) || !is_array($variacoes_js)) {
                throw new DomainException("O produto deve ter pelo menos uma variação ou stock definido.");
            }
            foreach ($variacoes_js as $v) {
                if (!is_array($v) || !isset($v['atributos']) || !is_array($v['atributos'])) {
                    throw new DomainException("Dados de variação inválidos.");
                }
                $qtd_check = filter_var($v['quantidade'] ?? null, FILTER_VALIDATE_INT);
                if ($qtd_check === false || $qtd_check < 0) {
                    throw new DomainException("A quantidade de stock deve ser um número inteiro igual ou superior a zero.");
                }
            }

            $stmt_update_prod = $conn->prepare("UPDATE produtos SET nome=?, referencia=?, ativo=?, preco=?, preco_promocional=?, categoria=?, descricao=?, atributos=?, foto_principal=?, guia_tamanho_id=?, peso_gramas=? WHERE id=?");
            $stmt_update_prod->bind_param("ssiddssssiii", $nome, $referencia, $ativo_post, $preco, $preco_promocional, $categoria_post, $descricao, $atributos_json_post, $foto_principal_nova, $guia_tamanho_id, $peso_gramas, $id);
            $stmt_update_prod->execute();
            $stmt_update_prod->close();

            // 7. Sincronizar as variações e associações de imagens
            $stmt_del_var = $conn->prepare("DELETE FROM produto_variacoes WHERE produto_id = ?");
            $stmt_del_var->bind_param("i", $id);
            $stmt_del_var->execute();
            $stmt_del_var->close();

            $stmt_insert_var = $conn->prepare("INSERT INTO produto_variacoes (produto_id, atributos, quantidade, referencia) VALUES (?, ?, ?, ?)");
            $stmt_assoc_img = $conn->prepare("INSERT INTO variacao_imagens (variacao_id, imagem_id) VALUES (?, ?)");

            foreach ($variacoes_js as $v) {
                $json_v_atributos = json_encode($v['atributos'], JSON_UNESCAPED_UNICODE);
                $qtd_var = (int)$v['quantidade'];
                $ref_var = !empty($v['referencia']) ? trim((string)$v['referencia']) : null;
                $stmt_insert_var->bind_param("isis", $id, $json_v_atributos, $qtd_var, $ref_var);
                $stmt_insert_var->execute();
                $nova_variacao_id = $stmt_insert_var->insert_id;

                if (!empty($v['imagens_associadas']) && is_array($v['imagens_associadas'])) {
                    foreach ($v['imagens_associadas'] as $js_id) {
                        if (is_string($js_id) && isset($mapa_js_id_para_db_id_novo[$js_id])) {
                            $db_imagem_id = $mapa_js_id_para_db_id_novo[$js_id];
                            $stmt_assoc_img->bind_param("ii", $nova_variacao_id, $db_imagem_id);
                            $stmt_assoc_img->execute();
                        }
                    }
                }
            }
            $stmt_insert_var->close();
            $stmt_assoc_img->close();
            
            $conn->commit();

            // Apagar ficheiros antigos só DEPOIS do commit bem-sucedido e apenas se não referenciados noutras tabelas
            $images_dir_real = realpath(__DIR__ . '/../public/images');
            $stmt_ref_prod = $conn->prepare("SELECT COUNT(*) FROM produtos WHERE foto_principal = ?");
            $stmt_ref_imgs = $conn->prepare("SELECT COUNT(*) FROM produto_imagens WHERE nome_ficheiro = ?");
            $stmt_ref_snap = $conn->prepare("SELECT COUNT(*) FROM encomenda_itens WHERE foto_snapshot = ?");
            foreach ($ficheiros_para_unlink as $nome_arq) {
                $base_arq = basename((string)$nome_arq);
                if ($base_arq === '' || $base_arq === 'default.jpg' || $base_arq !== $nome_arq) {
                    continue;
                }
                $em_uso = false;
                foreach ([$stmt_ref_prod, $stmt_ref_imgs, $stmt_ref_snap] as $st_ref) {
                    if ($st_ref) {
                        $st_ref->bind_param("s", $base_arq);
                        $st_ref->execute();
                        $cnt = (int)$st_ref->get_result()->fetch_row()[0];
                        if ($cnt > 0) { $em_uso = true; break; }
                    }
                }
                if (!$em_uso && $images_dir_real !== false) {
                    $caminho = $images_dir_real . DIRECTORY_SEPARATOR . $base_arq;
                    $caminho_real = realpath($caminho);
                    if ($caminho_real !== false && strpos($caminho_real, $images_dir_real . DIRECTORY_SEPARATOR) === 0 && is_file($caminho_real)) {
                        @unlink($caminho_real);
                    }
                }
            }
            // Limpa também ficheiros novos que foram gravados mas omitidos da ordem final
            foreach ($ficheiros_novos_criados as $caminho_novo_criado) {
                if (!isset($nomes_finais_set[basename($caminho_novo_criado)]) && is_file($caminho_novo_criado)) {
                    @unlink($caminho_novo_criado);
                }
            }
            if ($stmt_ref_prod) $stmt_ref_prod->close();
            if ($stmt_ref_imgs) $stmt_ref_imgs->close();
            if ($stmt_ref_snap) $stmt_ref_snap->close();

            $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => 'Produto atualizado com sucesso!'];
            log_app("Produto #{$id} atualizado ('{$nome}').", 'INFO', 'editar.php');
            header("Location: " . $return_url);
            exit;

        } catch (DomainException $e) {
            $conn->rollback();
            foreach ($ficheiros_novos_criados ?? [] as $caminho) {
                if (file_exists($caminho)) @unlink($caminho);
            }
            $popupMensagem = "Erro ao atualizar: " . $e->getMessage();
            $popupTipo = "erro";
        } catch (\Throwable $e) {
            $conn->rollback();
            // Apagar novos ficheiros criados durante esta tentativa falhada
            foreach ($ficheiros_novos_criados ?? [] as $caminho) {
                if (file_exists($caminho)) @unlink($caminho);
            }
            log_exception($e, "editar.php produto#{$id}");
            $popupMensagem = ($e instanceof Exception && !($e instanceof mysqli_sql_exception))
                ? "Erro ao atualizar: " . $e->getMessage()
                : "Ocorreu um erro interno ao atualizar o produto. Verifique os dados e tente novamente.";
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
    $row_v['atributos'] = json_decode((string)($row_v['atributos'] ?? '{}'), true);
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
