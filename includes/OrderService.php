<?php
// includes/OrderService.php

if (!function_exists('format_address')) {
    function format_address(array $campos): string {
        static $paises = [
            'PT'=>'Portugal','ES'=>'Espanha','FR'=>'França','DE'=>'Alemanha',
            'BE'=>'Bélgica','IT'=>'Itália','NL'=>'Países Baixos','LU'=>'Luxemburgo',
            'CH'=>'Suíça','UK'=>'Reino Unido','US'=>'Estados Unidos','BR'=>'Brasil','BG'=>'Bulgária',
        ];
        $linhas = [];
        if (!empty($campos['rua']))           $linhas[] = trim($campos['rua']);
        if (!empty($campos['provincia']))     $linhas[] = trim($campos['provincia']);
        $cp  = trim($campos['codigo_postal'] ?? '');
        $loc = trim($campos['localidade'] ?? '');
        if ($cp || $loc)                      $linhas[] = trim("$cp $loc");
        $iso = strtoupper(trim($campos['pais_regiao'] ?? ''));
        if ($iso)                             $linhas[] = $paises[$iso] ?? $iso;
        return implode("\n", array_filter($linhas));
    }
}

if (!function_exists('insert_order_items_and_decrement_stock')) {
    function insert_order_items_and_decrement_stock(mysqli $conn, int $orderId, array $items): void
    {
        $stmtItem = $conn->prepare(
            "INSERT INTO encomenda_itens
                (encomenda_id, produto_id, variacao_id, nome_produto, foto_snapshot, selecoes_atributos, quantidade, preco_unitario)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmtStock = $conn->prepare(
            "UPDATE produto_variacoes SET quantidade = quantidade - ? WHERE id = ? AND quantidade >= ?"
        );

        if (!$stmtItem || !$stmtStock) {
            throw new RuntimeException("Erro interno de base de dados (prepare itens): " . $conn->error);
        }

        foreach ($items as $item) {
            $stmtItem->bind_param(
                'iiisssid',
                $orderId,
                $item['produto_id'],
                $item['variacao_id_db'],
                $item['nome_produto'],
                $item['foto_snapshot'],
                $item['selecoes_atributos'],
                $item['quantidade'],
                $item['preco_unitario']
            );
            $stmtItem->execute();

            $stmtStock->bind_param('iii', $item['quantidade'], $item['variacao_id_db'], $item['quantidade']);
            $stmtStock->execute();

            if ($stmtStock->affected_rows === 0) {
                throw new Exception(
                    "Lamentamos, mas o stock do produto '" .
                    htmlspecialchars($item['nome_produto']) .
                    "' esgotou-se enquanto finalizava a compra. Por favor, atualize o carrinho."
                );
            }
        }

        $stmtItem->close();
        $stmtStock->close();
    }
}

if (!function_exists('restore_order_stock')) {
    function restore_order_stock(mysqli $conn, int $orderId): void
    {
        $stmtItems = $conn->prepare("SELECT variacao_id, quantidade FROM encomenda_itens WHERE encomenda_id = ?");
        if (!$stmtItems) {
            throw new RuntimeException("Erro interno de base de dados (prepare itens stock): " . $conn->error);
        }

        $stmtItems->bind_param('i', $orderId);
        $stmtItems->execute();
        $items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtItems->close();

        $stmtStock = $conn->prepare("UPDATE produto_variacoes SET quantidade = quantidade + ? WHERE id = ?");
        if (!$stmtStock) {
            throw new RuntimeException("Erro interno de base de dados (prepare repor stock): " . $conn->error);
        }

        foreach ($items as $item) {
            if (empty($item['variacao_id'])) {
                continue;
            }

            $stmtStock->bind_param('ii', $item['quantidade'], $item['variacao_id']);
            $stmtStock->execute();
        }

        $stmtStock->close();
    }
}

if (!function_exists('delete_order_with_items')) {
    function delete_order_with_items(mysqli $conn, int $orderId): void
    {
        $stmtItems = $conn->prepare("DELETE FROM encomenda_itens WHERE encomenda_id = ?");
        if (!$stmtItems) {
            throw new RuntimeException("Erro interno de base de dados (prepare apagar itens): " . $conn->error);
        }

        $stmtItems->bind_param('i', $orderId);
        $stmtItems->execute();
        $stmtItems->close();

        $stmtOrder = $conn->prepare("DELETE FROM encomendas WHERE id = ?");
        if (!$stmtOrder) {
            throw new RuntimeException("Erro interno de base de dados (prepare apagar encomenda): " . $conn->error);
        }

        $stmtOrder->bind_param('i', $orderId);
        $stmtOrder->execute();

        if ($stmtOrder->affected_rows === 0) {
            throw new Exception("Nao foi possivel eliminar a encomenda.");
        }

        $stmtOrder->close();
    }
}

if (!function_exists('release_incomplete_order_reservation')) {
    function release_incomplete_order_reservation(mysqli $conn, int $orderId, string $token): void
    {
        $conn->begin_transaction();

        try {
            $stmtOrder = $conn->prepare("SELECT id, estado FROM encomendas WHERE id = ? AND token = ? FOR UPDATE");
            if (!$stmtOrder) {
                throw new RuntimeException("Erro interno de base de dados (prepare encomenda): " . $conn->error);
            }

            $stmtOrder->bind_param('is', $orderId, $token);
            $stmtOrder->execute();
            $order = $stmtOrder->get_result()->fetch_assoc();
            $stmtOrder->close();

            if (!$order) {
                throw new Exception("Reserva nao encontrada.");
            }

            if ($order['estado'] !== 'incompleta') {
                throw new Exception("Esta reserva ja nao pode ser libertada.");
            }

            restore_order_stock($conn, $orderId);
            delete_order_with_items($conn, $orderId);

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}
