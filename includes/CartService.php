<?php
// includes/CartService.php

if (!function_exists('resolve_cart_unit_price')) {
    function resolve_cart_unit_price(array $productRow): float
    {
        if (!empty($productRow['preco_variacao']) && (float)$productRow['preco_variacao'] > 0) {
            return (float)$productRow['preco_variacao'];
        }

        if (!empty($productRow['promo_base']) && (float)$productRow['promo_base'] > 0) {
            return (float)$productRow['promo_base'];
        }

        return (float)$productRow['preco_base'];
    }
}

if (!function_exists('validate_cart_items')) {
    function validate_cart_items(mysqli $conn, array $cart, ?string $defaultImage = null): array
    {
        $stmtWithVariation = $conn->prepare(
            "SELECT p.nome, p.preco as preco_base, p.preco_promocional as promo_base, p.peso_gramas, p.foto_principal,
                    pv.id as variacao_id, pv.quantidade as stock, pv.preco as preco_variacao
             FROM produtos p
             JOIN produto_variacoes pv ON p.id = pv.produto_id
             WHERE p.id = ? AND pv.id = ? AND p.ativo = 1 FOR UPDATE"
        );

        $stmtWithoutVariation = $conn->prepare(
            "SELECT p.nome, p.preco as preco_base, p.preco_promocional as promo_base, p.peso_gramas, p.foto_principal,
                    pv.id as variacao_id, pv.quantidade as stock, pv.preco as preco_variacao
             FROM produtos p
             JOIN produto_variacoes pv ON p.id = pv.produto_id
             WHERE p.id = ? AND pv.atributos = '{}' AND p.ativo = 1 FOR UPDATE"
        );

        if (!$stmtWithVariation || !$stmtWithoutVariation) {
            throw new RuntimeException("Erro interno de base de dados (prepare carrinho): " . $conn->error);
        }

        $total = 0.0;
        $weight = 0;
        $items = [];

        foreach ($cart as $item) {
            $productId = (int)($item['id'] ?? 0);
            $variationId = $item['variacao_id'] ?? null;
            $quantity = (int)($item['quantidade'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                throw new Exception("Dados de um item do carrinho invalidos.");
            }

            if (!empty($variationId) && (int)$variationId > 0) {
                $variationId = (int)$variationId;
                $stmtWithVariation->bind_param('ii', $productId, $variationId);
                $stmtWithVariation->execute();
                $product = $stmtWithVariation->get_result()->fetch_assoc();
            } else {
                $stmtWithoutVariation->bind_param('i', $productId);
                $stmtWithoutVariation->execute();
                $product = $stmtWithoutVariation->get_result()->fetch_assoc();
            }

            if (!$product) {
                throw new Exception("O produto '" . htmlspecialchars($item['nome'] ?? '') . "' ja nao esta disponivel.");
            }

            if ($quantity > (int)$product['stock']) {
                throw new Exception("Stock insuficiente para '" . htmlspecialchars($product['nome']) . "'. Disponivel: {$product['stock']}.");
            }

            $unitPrice = resolve_cart_unit_price($product);
            $total += $unitPrice * $quantity;
            $weight += (int)($product['peso_gramas'] ?? 0) * $quantity;

            $snapshot = !empty($product['foto_principal']) ? $product['foto_principal'] : $defaultImage;

            $items[] = [
                'produto_id' => $productId,
                'variacao_id_db' => (int)$product['variacao_id'],
                'nome_produto' => $product['nome'],
                'foto_snapshot' => $snapshot,
                'selecoes_atributos' => json_encode($item['selecoes'] ?? []),
                'quantidade' => $quantity,
                'preco_unitario' => $unitPrice,
            ];
        }

        $stmtWithVariation->close();
        $stmtWithoutVariation->close();

        return [
            'total' => $total,
            'weight' => $weight,
            'items' => $items,
        ];
    }
}
