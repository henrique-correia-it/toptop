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
                    pv.id as variacao_id, pv.quantidade as stock, pv.preco as preco_variacao, pv.atributos as variacao_atributos
             FROM produtos p
             JOIN produto_variacoes pv ON p.id = pv.produto_id
             WHERE p.id = ? AND pv.id = ? AND p.ativo = 1 FOR UPDATE"
        );

        $stmtWithoutVariation = $conn->prepare(
            "SELECT p.nome, p.preco as preco_base, p.preco_promocional as promo_base, p.peso_gramas, p.foto_principal,
                    pv.id as variacao_id, pv.quantidade as stock, pv.preco as preco_variacao, pv.atributos as variacao_atributos
             FROM produtos p
             JOIN produto_variacoes pv ON p.id = pv.produto_id
             WHERE p.id = ? AND pv.atributos = '{}' AND p.ativo = 1
             ORDER BY pv.id ASC LIMIT 1 FOR UPDATE"
        );

        if (!$stmtWithVariation || !$stmtWithoutVariation) {
            if ($stmtWithVariation) $stmtWithVariation->close();
            if ($stmtWithoutVariation) $stmtWithoutVariation->close();
            throw new RuntimeException("Erro interno de base de dados (prepare carrinho): " . $conn->error);
        }

        $totalCents = 0;
        $weight = 0;
        $items = [];
        $qtdPorVariacao = [];

        // Ordena por (produto_id, variacao_id) para adquirir locks FOR UPDATE em ordem consistente (anti-deadlock)
        usort($cart, static function ($a, $b) {
            $cmp = ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
            return $cmp !== 0 ? $cmp : (((int)($a['variacao_id'] ?? 0)) <=> ((int)($b['variacao_id'] ?? 0)));
        });

        try {
            foreach ($cart as $item) {
                $productId = (int)($item['id'] ?? 0);
                $variationId = $item['variacao_id'] ?? null;
                $quantity = (int)($item['quantidade'] ?? 0);

                if ($productId <= 0 || $quantity <= 0) {
                    throw new DomainException("Dados de um item do carrinho inválidos.");
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
                    $nomeItem = is_scalar($item['nome'] ?? null) ? trim((string)$item['nome']) : '';
                    throw new DomainException("O produto '" . $nomeItem . "' já não está disponível.");
                }

                $varIdDb = (int)$product['variacao_id'];
                $qtdPorVariacao[$varIdDb] = ($qtdPorVariacao[$varIdDb] ?? 0) + $quantity;

                if ($qtdPorVariacao[$varIdDb] > (int)$product['stock']) {
                    throw new DomainException("Stock insuficiente para '" . $product['nome'] . "'. Disponível: {$product['stock']}.");
                }

                $unitPrice = resolve_cart_unit_price($product);
                $unitCents = (int)round($unitPrice * 100);
                $totalCents += $unitCents * $quantity;
                $weight += max(0, (int)($product['peso_gramas'] ?? 0)) * $quantity;

                $snapshot = !empty($product['foto_principal']) ? $product['foto_principal'] : $defaultImage;

                $dbAttrsRaw = json_decode((string)($product['variacao_atributos'] ?? '{}'), true);
                $selecoesAuth = is_array($dbAttrsRaw) ? $dbAttrsRaw : [];

                $items[] = [
                    'produto_id'         => $productId,
                    'variacao_id_db'     => $varIdDb,
                    'nome_produto'       => $product['nome'],
                    'foto_snapshot'      => $snapshot,
                    'selecoes_atributos' => json_encode($selecoesAuth, JSON_UNESCAPED_UNICODE),
                    'quantidade'         => $quantity,
                    'preco_unitario'     => round($unitCents / 100, 2),
                ];
            }
        } finally {
            $stmtWithVariation->close();
            $stmtWithoutVariation->close();
        }

        return [
            'total'       => round($totalCents / 100, 2),
            'total_cents' => $totalCents,
            'weight'      => $weight,
            'items'       => $items,
        ];
    }
}
