<?php
// loja-roupa/admin/ajax_gerir_encomenda.php
ob_start();

header('Content-Type: application/json');
$response = ['sucesso' => false, 'mensagem' => 'Ocorreu um erro inesperado.'];

try {
    require_once __DIR__ . '/../config/session.php';
    include '../config/database.php';
    include 'includes/email_handler.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Acesso inválido.');
    }

    $dados = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Dados mal formados.');
    }

    if (!isset($_SESSION['admin_logado']) || !isset($dados['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $dados['csrf_token'])) {
        throw new Exception('Erro de segurança. Tente novamente.');
    }

    $encomenda_id = (int)($dados['encomenda_id'] ?? 0);
    $acao = $dados['acao'] ?? '';

    if ($encomenda_id <= 0 || empty($acao)) {
        throw new Exception('Dados da ação em falta.');
    }

    $conn->begin_transaction();

    $stmt_enc = $conn->prepare("SELECT * FROM encomendas WHERE id = ?");
    $stmt_enc->bind_param("i", $encomenda_id);
    $stmt_enc->execute();
    $encomenda_db = $stmt_enc->get_result()->fetch_assoc();
    $stmt_enc->close();

    if (!$encomenda_db) {
        throw new Exception("Encomenda não encontrada.");
    }

    $estados_validos = ['pendente', 'pago', 'em processamento', 'pronta para levantamento', 'enviada', 'concluida', 'cancelada', 'incompleta', 'a aguardar pagamento', 'pagamento na entrega'];

    switch ($acao) {
        case 'cancelar_incompleta':
            if ($encomenda_db['estado'] !== 'incompleta') {
                throw new Exception('Esta ação só está disponível para encomendas que aguardam pagamento.');
            }

            $stmt_itens = $conn->prepare("SELECT variacao_id, quantidade FROM encomenda_itens WHERE encomenda_id = ?");
            $stmt_itens->bind_param("i", $encomenda_id);
            $stmt_itens->execute();
            $itens_stock = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_itens->close();

            $stmt_stock = $conn->prepare("UPDATE produto_variacoes SET quantidade = quantidade + ? WHERE id = ?");
            foreach ($itens_stock as $item) {
                if (!empty($item['variacao_id'])) {
                    $stmt_stock->bind_param("ii", $item['quantidade'], $item['variacao_id']);
                    $stmt_stock->execute();
                }
            }
            $stmt_stock->close();

            $stmt_delete_itens = $conn->prepare("DELETE FROM encomenda_itens WHERE encomenda_id = ?");
            $stmt_delete_itens->bind_param("i", $encomenda_id);
            $stmt_delete_itens->execute();
            $stmt_delete_itens->close();

            $stmt_delete_enc = $conn->prepare("DELETE FROM encomendas WHERE id = ?");
            $stmt_delete_enc->bind_param("i", $encomenda_id);
            $stmt_delete_enc->execute();
            if ($stmt_delete_enc->affected_rows === 0) {
                throw new Exception('Não foi possível eliminar a encomenda.');
            }
            $stmt_delete_enc->close();

            $return_to = $dados['return_to'] ?? 'encomendas.php';
            $redirects_permitidos = ['admin.php', 'encomendas.php', '/dev', 'reservas_stock.php'];
            if (!in_array($return_to, $redirects_permitidos, true)) {
                $return_to = 'encomendas.php';
            }

            $response = [
                'sucesso' => true,
                'mensagem' => 'Encomenda cancelada, stock reposto e reserva eliminada.',
                'redirect_url' => $return_to
            ];
            break;

        case 'mudar_estado':
            if ($encomenda_db['estado'] === 'incompleta') {
                throw new Exception('Encomendas que aguardam pagamento não podem ter o estado alterado manualmente.');
            }

            $novo_estado = $dados['novo_estado'] ?? '';
            $notificar_cliente = $dados['notificar_cliente'] ?? false;
            if (empty($novo_estado)) throw new Exception('Novo estado não especificado.');
            if (!in_array($novo_estado, $estados_validos, true)) throw new Exception('Estado inválido.');

            $era_cancelada = $encomenda_db['estado'] === 'cancelada';
            if (($novo_estado === 'cancelada' && !$era_cancelada) || ($era_cancelada && $novo_estado !== 'cancelada')) {
                $operacao_stock = ($novo_estado === 'cancelada') ? '+' : '-';
                $stmt_itens = $conn->prepare("SELECT variacao_id, quantidade FROM encomenda_itens WHERE encomenda_id = ?");
                $stmt_itens->bind_param("i", $encomenda_id);
                $stmt_itens->execute();
                $itens_stock = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_itens->close();
                $stmt_stock = $conn->prepare("UPDATE produto_variacoes SET quantidade = GREATEST(0, quantidade {$operacao_stock} ?) WHERE id = ?");
                foreach ($itens_stock as $item) {
                    if ($item['variacao_id']) {
                        $stmt_stock->bind_param("ii", $item['quantidade'], $item['variacao_id']);
                        $stmt_stock->execute();
                    }
                }
                $stmt_stock->close();
            }

            if (isset($dados['portes_envio'])) {
                $portes = (float)$dados['portes_envio'];
                $stmt_portes = $conn->prepare("UPDATE encomendas SET portes_envio = ? WHERE id = ?");
                $stmt_portes->bind_param("di", $portes, $encomenda_id); $stmt_portes->execute();
            }
            if (isset($dados['codigo_tracking'])) {
                $tracking = $dados['codigo_tracking'];
                $stmt_track = $conn->prepare("UPDATE encomendas SET codigo_tracking = ? WHERE id = ?");
                $stmt_track->bind_param("si", $tracking, $encomenda_id); $stmt_track->execute();
            }

            $stmt = $conn->prepare("UPDATE encomendas SET estado = ? WHERE id = ?");
            $stmt->bind_param("si", $novo_estado, $encomenda_id);
            $stmt->execute();
            $stmt->close();
            $response = ['sucesso' => true, 'mensagem' => 'Estado atualizado com sucesso!'];

            if ($notificar_cliente) {
                $tipo_email = $novo_estado;
                $assunto_email = $dados['assunto'] ?? '';
                $mensagem_personalizada = $dados['mensagem'] ?? '';

                $dados_email = array_merge($encomenda_db, [
                    'id' => $encomenda_id, 'token' => $encomenda_db['token'],
                    'assunto_email' => $assunto_email, 'mensagem_para_cliente' => $mensagem_personalizada,
                    'portes_envio' => $portes ?? $encomenda_db['portes_envio'],
                    'codigo_tracking' => $tracking ?? $encomenda_db['codigo_tracking']
                ]);

                if (!empty($mensagem_personalizada)) {
                    $dados_email['corpo_editado'] = true;
                }

                try {
                    enviarEmailEncomenda($tipo_email, $dados_email);
                    $response['mensagem'] .= ' Email de notificação enviado.';
                } catch (Exception $e) {
                    log_email($e->getMessage(), 'ajax_gerir_encomenda.php');
                    throw new Exception('O estado foi atualizado, mas o envio do email falhou.');
                }
            }
            break;

        case 'enviar_email':
            $assunto_email = $dados['assunto'] ?? '';
            $mensagem_para_cliente = $dados['mensagem'] ?? '';

            if (empty($assunto_email) || empty($mensagem_para_cliente)) {
                throw new Exception('O assunto e a mensagem são obrigatórios.');
            }

            $dados_email = array_merge($encomenda_db, [
                'id' => $encomenda_id, 'token' => $encomenda_db['token'],
                'assunto_email' => $assunto_email,
                'mensagem_para_cliente' => $mensagem_para_cliente
            ]);

            try {
                enviarEmailEncomenda('personalizado', $dados_email);
                $response = ['sucesso' => true, 'mensagem' => 'Email personalizado enviado com sucesso!'];
            } catch (Exception $e) {
                log_email($e->getMessage(), 'ajax_gerir_encomenda.php');
                throw new Exception('Falha ao enviar o email.');
            }
            break;

        case 'guardar_notas':
            $notas_internas = $dados['notas'] ?? '';
            $stmt = $conn->prepare("UPDATE encomendas SET notas_internas = ? WHERE id = ?");
            $stmt->bind_param("si", $notas_internas, $encomenda_id);
            $stmt->execute();
            $stmt->close();
            $response = ['sucesso' => true, 'mensagem' => 'Notas internas guardadas com sucesso!'];
            break;

        default:
            throw new Exception('Ação desconhecida.');
    }

    $conn->commit();

} catch (Exception $e) {
    if ($conn->in_transaction) $conn->rollback();
    log_app($e->getMessage(), 'ERROR', 'ajax_gerir_encomenda.php enc#' . ($encomenda_id ?? 0));
    $response['sucesso'] = false;
    $response['mensagem'] = $e->getMessage();
    http_response_code(400);
}

ob_end_clean();
echo json_encode($response);
exit;
