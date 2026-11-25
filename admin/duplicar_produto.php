<?php
// admin/duplicar_produto.php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

// --- VALIDAÇÃO DE SEGURANÇA (MÉTODO, SESSÃO, CSRF) ---
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    http_response_code(403);
    exit('Acesso não autorizado.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Acesso inválido.');
}
if (!csrf_from_post()) {
    http_response_code(403);
    exit('Erro de validação de segurança. Ação não permitida.');
}

$id_original = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id_original) {
    header("Location: admin_produtos.php");
    exit;
}

$conn->begin_transaction();
try {
    // 1. OBTER DADOS DO PRODUTO ORIGINAL
    $stmt_prod = $conn->prepare("SELECT * FROM produtos WHERE id = ?");
    $stmt_prod->bind_param("i", $id_original);
    $stmt_prod->execute();
    $produto_original = $stmt_prod->get_result()->fetch_assoc();
    $stmt_prod->close();

    if (!$produto_original) {
        throw new Exception("Produto original não encontrado.");
    }

    // --- INÍCIO DA CORREÇÃO: LÓGICA PARA NOME E REFERÊNCIA ÚNICOS ---
    
    // Gera um nome base, removendo sufixos "(Cópia...)" existentes
    $nome_base = preg_replace('/ \(Cópia( \d+)?\)$/', '', $produto_original['nome']);
    $novo_nome = '';
    $contador = 1;

    // Loop para encontrar um nome único
    while (true) {
        $nome_tentativa = $nome_base . ' (Cópia' . ($contador > 1 ? ' ' . $contador : '') . ')';
        
        $stmt_check_name = $conn->prepare("SELECT id FROM produtos WHERE nome = ?");
        $stmt_check_name->bind_param("s", $nome_tentativa);
        $stmt_check_name->execute();
        $result_name = $stmt_check_name->get_result();
        $stmt_check_name->close();

        if ($result_name->num_rows == 0) {
            $novo_nome = $nome_tentativa;
            break; // Encontrou um nome único
        }
        $contador++;
    }

    // Garante que a referência também é sempre única (verificada na BD)
    $ref_base = preg_replace('/-COPIA-[A-Za-z0-9]+$/', '', (string)$produto_original['referencia']);
    $nova_referencia = '';
    for ($tentativa = 0; $tentativa < 10; $tentativa++) {
        $candidata = $ref_base . '-COPIA-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt_check_ref = $conn->prepare("SELECT id FROM produtos WHERE referencia = ? LIMIT 1");
        $stmt_check_ref->bind_param("s", $candidata);
        $stmt_check_ref->execute();
        $existe_ref = $stmt_check_ref->get_result()->num_rows > 0;
        $stmt_check_ref->close();
        if (!$existe_ref) {
            $nova_referencia = $candidata;
            break;
        }
    }
    if ($nova_referencia === '') {
        $nova_referencia = $ref_base . '-COPIA-' . strtoupper(bin2hex(random_bytes(6)));
    }

    // --- FIM DA CORREÇÃO ---

    // 2. OBTER IMAGENS ORIGINAIS EXISTENTES EM DISCO E PREPARAR NOVOS NOMES
    $stmt_imgs = $conn->prepare("SELECT * FROM produto_imagens WHERE produto_id = ? ORDER BY id");
    $stmt_imgs->bind_param("i", $id_original);
    $stmt_imgs->execute();
    $imagens_originais = $stmt_imgs->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_imgs->close();

    $images_base_dir = __DIR__ . '/../public/images/';
    $mapa_imagens_antigas_para_novas = [];
    $nova_foto_principal_nome = '';
    $novos_nomes_ficheiros = [];
    $imagens_existentes = [];

    foreach ($imagens_originais as $imagem) {
        $nome_orig = basename((string)$imagem['nome_ficheiro']);
        if ($nome_orig === '' || !is_file($images_base_dir . $nome_orig)) {
            continue;
        }
        $extensao = strtolower(pathinfo($nome_orig, PATHINFO_EXTENSION)) ?: 'jpg';
        $novo_nome_ficheiro = bin2hex(random_bytes(16)) . '.' . $extensao;
        $novos_nomes_ficheiros[$imagem['id']] = $novo_nome_ficheiro;
        $imagens_existentes[] = $imagem;

        if ($imagem['nome_ficheiro'] == $produto_original['foto_principal']) {
            $nova_foto_principal_nome = $novo_nome_ficheiro;
        }
    }
    if (empty($nova_foto_principal_nome) && !empty($novos_nomes_ficheiros)) {
        $nova_foto_principal_nome = reset($novos_nomes_ficheiros);
    }

    // 3. CRIAR O NOVO PRODUTO COM OS DADOS ÚNICOS
    $novo_ativo = 0; // Oculto por defeito

    $guia_tamanho_id = !empty($produto_original['guia_tamanho_id']) ? (int)$produto_original['guia_tamanho_id'] : null;
    $peso_gramas = (int)($produto_original['peso_gramas'] ?? 0);

    $stmt_insert_prod = $conn->prepare("INSERT INTO produtos (nome, referencia, ativo, preco, preco_promocional, categoria, descricao, atributos, foto_principal, guia_tamanho_id, peso_gramas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_insert_prod->bind_param(
        "ssiddssssii",
        $novo_nome, // Nome único
        $nova_referencia, // Referência única
        $novo_ativo,
        $produto_original['preco'],
        $produto_original['preco_promocional'],
        $produto_original['categoria'],
        $produto_original['descricao'],
        $produto_original['atributos'],
        $nova_foto_principal_nome,
        $guia_tamanho_id,
        $peso_gramas
    );
    $stmt_insert_prod->execute();
    $id_novo_produto = $conn->insert_id;
    $stmt_insert_prod->close();

    // 4. COPIAR FICHEIROS E INSERIR REGISTOS DE IMAGENS (apenas ficheiros existentes no disco)
    $ficheiros_copiados = [];
    if (!empty($imagens_existentes)) {
        $stmt_insert_img = $conn->prepare("INSERT INTO produto_imagens (produto_id, nome_ficheiro) VALUES (?, ?)");
        foreach ($imagens_existentes as $imagem) {
            $novo_nome_ficheiro = $novos_nomes_ficheiros[$imagem['id']];
            $caminho_original = $images_base_dir . basename((string)$imagem['nome_ficheiro']);
            $caminho_novo = $images_base_dir . $novo_nome_ficheiro;

            if (!copy($caminho_original, $caminho_novo)) {
                throw new Exception("Erro ao copiar a imagem '{$imagem['nome_ficheiro']}'.");
            }
            $ficheiros_copiados[] = $caminho_novo;

            $stmt_insert_img->bind_param("is", $id_novo_produto, $novo_nome_ficheiro);
            $stmt_insert_img->execute();
            $id_nova_imagem = $stmt_insert_img->insert_id;

            $mapa_imagens_antigas_para_novas[$imagem['id']] = $id_nova_imagem;
        }
        $stmt_insert_img->close();
    }
    
    // 5. DUPLICAR VARIAÇÕES E ASSOCIAÇÕES (lógica inalterada)
    $stmt_vars = $conn->prepare("SELECT * FROM produto_variacoes WHERE produto_id = ?");
    $stmt_vars->bind_param("i", $id_original);
    $stmt_vars->execute();
    $variacoes_originais = $stmt_vars->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_vars->close();

    if (!empty($variacoes_originais)) {
        $stmt_insert_var = $conn->prepare("INSERT INTO produto_variacoes (produto_id, atributos, quantidade, preco, referencia) VALUES (?, ?, ?, ?, ?)");
        $stmt_assoc_img = $conn->prepare("INSERT INTO variacao_imagens (variacao_id, imagem_id) VALUES (?, ?)");

        foreach ($variacoes_originais as $variacao) {
            $stmt_insert_var->bind_param(
                "isids",
                $id_novo_produto,
                $variacao['atributos'],
                $variacao['quantidade'],
                $variacao['preco'],
                $variacao['referencia']
            );
            $stmt_insert_var->execute();
            $id_nova_variacao = $stmt_insert_var->insert_id;
            
            $stmt_find_imgs = $conn->prepare("SELECT imagem_id FROM variacao_imagens WHERE variacao_id = ?");
            $stmt_find_imgs->bind_param("i", $variacao['id']);
            $stmt_find_imgs->execute();
            $imagens_associadas_originais = $stmt_find_imgs->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_find_imgs->close();

            foreach ($imagens_associadas_originais as $assoc) {
                $id_antigo_imagem = $assoc['imagem_id'];
                if (isset($mapa_imagens_antigas_para_novas[$id_antigo_imagem])) {
                    $id_novo_imagem = $mapa_imagens_antigas_para_novas[$id_antigo_imagem];
                    $stmt_assoc_img->bind_param("ii", $id_nova_variacao, $id_novo_imagem);
                    $stmt_assoc_img->execute();
                }
            }
        }
        $stmt_insert_var->close();
        $stmt_assoc_img->close();
    }

    $conn->commit();
    log_app("Produto #{$id} duplicado para #{$novo_produto_id}.", 'INFO', 'duplicar_produto.php');
    $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => 'Produto duplicado com sucesso! O novo produto foi criado como "Oculto".'];

} catch (Exception $e) {
    $conn->rollback();
    foreach ($ficheiros_copiados ?? [] as $caminho) {
        if (file_exists($caminho)) @unlink($caminho);
    }
    log_exception($e, "duplicar_produto.php produto#{$id}");
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Ocorreu um erro ao duplicar o produto.'];
}

header("Location: admin_produtos.php");
exit;
?>
