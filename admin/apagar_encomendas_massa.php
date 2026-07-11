<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Acesso invalido.');
}

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    exit('Acesso nao autorizado.');
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    exit('Erro de validacao de seguranca. Acao nao permitida.');
}

$ids = $_POST['ids_encomendas'] ?? [];
if (!is_array($ids)) {
    $ids = [];
}

$ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));

if (empty($ids)) {
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Nenhuma encomenda foi selecionada.'];
    header('Location: encomendas.php');
    exit;
}

$conn->begin_transaction();

try {
    $stmtPhotos = $conn->prepare("SELECT DISTINCT foto_snapshot FROM encomenda_itens WHERE encomenda_id = ? AND foto_snapshot IS NOT NULL");
    $stmtDeleteItems = $conn->prepare("DELETE FROM encomenda_itens WHERE encomenda_id = ?");
    $stmtDeleteOrder = $conn->prepare("DELETE FROM encomendas WHERE id = ?");
    $stmtCheckOrders = $conn->prepare("SELECT COUNT(*) as total FROM encomenda_itens WHERE foto_snapshot = ?");
    $stmtCheckProducts = $conn->prepare("SELECT COUNT(*) as total FROM produtos WHERE foto_principal = ?");
    $stmtCheckGallery = $conn->prepare("SELECT COUNT(*) as total FROM produto_imagens WHERE nome_ficheiro = ?");

    if (!$stmtPhotos || !$stmtDeleteItems || !$stmtDeleteOrder || !$stmtCheckOrders || !$stmtCheckProducts || !$stmtCheckGallery) {
        throw new Exception('Erro interno ao preparar a eliminacao.');
    }

    $deleted = 0;
    $photos = [];

    foreach ($ids as $id) {
        $stmtPhotos->bind_param('i', $id);
        $stmtPhotos->execute();
        $resultPhotos = $stmtPhotos->get_result();
        while ($row = $resultPhotos->fetch_assoc()) {
            if (!empty($row['foto_snapshot'])) {
                $photos[] = $row['foto_snapshot'];
            }
        }

        $stmtDeleteItems->bind_param('i', $id);
        $stmtDeleteItems->execute();

        $stmtDeleteOrder->bind_param('i', $id);
        $stmtDeleteOrder->execute();
        if ($stmtDeleteOrder->affected_rows > 0) {
            $deleted++;
        }
    }

    foreach (array_unique($photos) as $photo) {
        if (empty($photo) || $photo === 'default.jpg') {
            continue;
        }

        $stmtCheckOrders->bind_param('s', $photo);
        $stmtCheckOrders->execute();
        $usedOrders = (int)$stmtCheckOrders->get_result()->fetch_assoc()['total'];

        $stmtCheckProducts->bind_param('s', $photo);
        $stmtCheckProducts->execute();
        $usedProducts = (int)$stmtCheckProducts->get_result()->fetch_assoc()['total'];

        $stmtCheckGallery->bind_param('s', $photo);
        $stmtCheckGallery->execute();
        $usedGallery = (int)$stmtCheckGallery->get_result()->fetch_assoc()['total'];

        if ($usedOrders === 0 && $usedProducts === 0 && $usedGallery === 0) {
            $path = '../public/images/' . $photo;
            if (file_exists($path) && is_file($path)) {
                unlink($path);
            }
        }
    }

    $stmtPhotos->close();
    $stmtDeleteItems->close();
    $stmtDeleteOrder->close();
    $stmtCheckOrders->close();
    $stmtCheckProducts->close();
    $stmtCheckGallery->close();

    $conn->commit();
    $_SESSION['flash_message'] = [
        'tipo' => 'sucesso',
        'texto' => $deleted . ' encomenda(s) apagada(s) permanentemente com sucesso!'
    ];
} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage());
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Ocorreu um erro ao apagar as encomendas selecionadas.'];
}

$returnTo = $_POST['return_to'] ?? 'encomendas.php';
if (strpos($returnTo, '/admin/encomendas.php') === 0) {
    $returnTo = substr($returnTo, strlen('/admin/'));
}
if (!preg_match('/^encomendas\.php(\?.*)?$/', $returnTo)) {
    $returnTo = 'encomendas.php';
}

header('Location: ' . $returnTo);
exit;
