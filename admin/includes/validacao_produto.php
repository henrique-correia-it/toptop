<?php
// admin/includes/validacao_produto.php

/**
 * Valida o nome e a referência de um produto na base de dados.
 *
 * @param mysqli $conn A conexão com a base de dados.
 * @param string $nome O nome do produto a validar.
 * @param string $referencia A referência do produto a validar.
 * @param int $id_a_ignorar O ID do produto a ignorar na validação (útil para edições).
 * @return array Um array com 'valido' (bool) e 'mensagem' (string).
 */
function validarProduto($conn, $nome, $referencia, $id_a_ignorar = 0) {
    // 1. Validar Nome
    if (!empty($nome)) {
        $stmt_nome = $conn->prepare("SELECT id FROM produtos WHERE nome = ? AND id != ?");
        $stmt_nome->bind_param("si", $nome, $id_a_ignorar);
        $stmt_nome->execute();
        if ($stmt_nome->get_result()->num_rows > 0) {
            return ['valido' => false, 'mensagem' => 'Este nome de produto já está a ser utilizado.', 'campo' => 'nome'];
        }
        $stmt_nome->close();
    }

    // 2. Validar Referência
    if (!empty($referencia)) {
        $stmt_ref = $conn->prepare("SELECT id FROM produtos WHERE referencia = ? AND id != ?");
        $stmt_ref->bind_param("si", $referencia, $id_a_ignorar);
        $stmt_ref->execute();
        if ($stmt_ref->get_result()->num_rows > 0) {
            return ['valido' => false, 'mensagem' => 'Esta referência (SKU) já está a ser utilizada.', 'campo' => 'referencia'];
        }
        $stmt_ref->close();
    }

    return ['valido' => true];
}
