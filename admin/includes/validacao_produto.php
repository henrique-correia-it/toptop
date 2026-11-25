<?php
// admin/includes/validacao_produto.php

if (!function_exists('parsePrecoProduto')) {
    /**
     * Converte string/número de preço (aceitando vírgula ou ponto) em float positivo finito, ou null se inválido.
     */
    function parsePrecoProduto($raw): ?float
    {
        if ($raw === null || is_array($raw) || is_object($raw)) {
            return null;
        }
        $clean = str_replace(',', '.', trim((string)$raw));
        if ($clean === '' || !is_numeric($clean)) {
            return null;
        }
        $val = (float)$clean;
        if (!is_finite($val)) {
            return null;
        }
        return round($val, 2);
    }
}

/**
 * Valida o nome e a referência de um produto na base de dados.
 *
 * @param mysqli $conn         A conexão com a base de dados.
 * @param string $nome         O nome do produto a validar.
 * @param string $referencia   A referência do produto a validar.
 * @param int    $id_a_ignorar O ID do produto a ignorar na validação (útil para edições).
 * @return array Um array com 'valido' (bool), 'mensagem' (string) e 'campo' (string).
 */
function validarProduto($conn, $nome, $referencia, $id_a_ignorar = 0) {
    $nome = trim((string)$nome);
    $referencia = trim((string)$referencia);
    $id_a_ignorar = (int)$id_a_ignorar;

    if ($nome === '') {
        return ['valido' => false, 'mensagem' => 'O nome do produto é obrigatório.', 'campo' => 'nome'];
    }
    if ($referencia === '') {
        return ['valido' => false, 'mensagem' => 'A referência (SKU) é obrigatória.', 'campo' => 'referencia'];
    }

    // 1. Validar Nome
    $stmt_nome = $conn->prepare("SELECT id FROM produtos WHERE nome = ? AND id != ? LIMIT 1");
    $stmt_nome->bind_param("si", $nome, $id_a_ignorar);
    $stmt_nome->execute();
    if ($stmt_nome->get_result()->num_rows > 0) {
        $stmt_nome->close();
        return ['valido' => false, 'mensagem' => 'Este nome de produto já está a ser utilizado.', 'campo' => 'nome'];
    }
    $stmt_nome->close();

    // 2. Validar Referência
    $stmt_ref = $conn->prepare("SELECT id FROM produtos WHERE referencia = ? AND id != ? LIMIT 1");
    $stmt_ref->bind_param("si", $referencia, $id_a_ignorar);
    $stmt_ref->execute();
    if ($stmt_ref->get_result()->num_rows > 0) {
        $stmt_ref->close();
        return ['valido' => false, 'mensagem' => 'Esta referência (SKU) já está a ser utilizada.', 'campo' => 'referencia'];
    }
    $stmt_ref->close();

    return ['valido' => true];
}
