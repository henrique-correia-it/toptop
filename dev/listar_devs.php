<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

require_developer('/admin');

include '../templates/header.php';
include '../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$codigo_sucesso = $_GET['sucesso'] ?? '';
$codigo_erro    = $_GET['erro'] ?? '';

$mensagem_sucesso = '';
$mensagem_erro    = '';

if ($codigo_sucesso === '1') {
    $mensagem_sucesso = 'Desenvolvedor criado com sucesso!';
} elseif ($codigo_sucesso === '2') {
    $mensagem_sucesso = 'Desenvolvedor atualizado com sucesso!';
} elseif ($codigo_sucesso === '3') {
    $mensagem_sucesso = 'Desenvolvedor removido com sucesso!';
} elseif ($codigo_sucesso !== '') {
    $mensagem_sucesso = 'Operação concluída com sucesso!';
}

if ($codigo_erro === '1') {
    $mensagem_erro = 'Acesso negado ou conta inválida.';
} elseif ($codigo_erro === '2') {
    $mensagem_erro = 'Ação bloqueada: Não é possível apagar a própria conta.';
} elseif ($codigo_erro === '3') {
    $mensagem_erro = 'Ação bloqueada: Não pode apagar o único desenvolvedor do sistema.';
} elseif ($codigo_erro === '4') {
    $mensagem_erro = 'Erro de sistema: Falha ao eliminar da base de dados.';
} elseif ($codigo_erro !== '') {
    $mensagem_erro = 'Ocorreu um erro desconhecido.';
}

$filtro_q = trim((string)($_GET['q'] ?? ''));

$conds  = ["role = 'desenvolvedor'"];
$params = [];
$types  = '';

if ($filtro_q !== '') {
    $like     = '%' . $filtro_q . '%';
    $conds[]  = '(username LIKE ? OR email LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

$sort_map = [
    'username'     => 'username',
    'email'        => 'email',
    'data_criacao' => 'data_criacao',
];
$sort_by   = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map) ? $_GET['sort'] : '';
$sort_dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'desc') ? 'desc' : 'asc';
$order_sql = $sort_by !== '' ? ($sort_map[$sort_by] . ' ' . strtoupper($sort_dir) . ', id ASC') : 'id ASC';

$stmt = $conn->prepare("SELECT id, username, email, data_criacao FROM administradores WHERE " . implode(' AND ', $conds) . " ORDER BY {$order_sql}");
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$logged_in_id = (int)($_SESSION['admin_id'] ?? 0);
?>

<main class="dashboard-container animate-entry">

    <?php echo ui_page_header('Desenvolvedores', ['layout' => 'admin', 'back_url' => '/dev', 'back_title' => 'Voltar ao Painel Dev']); ?>

    <?php if ($mensagem_sucesso !== '' || $mensagem_erro !== ''): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof mostrarPopup === 'function') {
            mostrarPopup(
                <?php echo json_encode($mensagem_sucesso !== '' ? $mensagem_sucesso : $mensagem_erro, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                <?php echo json_encode($mensagem_sucesso !== '' ? 'sucesso' : 'erro', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
            );
        }
        if (window.history && typeof window.history.replaceState === 'function') {
            const url = new URL(window.location.href);
            url.searchParams.delete('sucesso');
            url.searchParams.delete('erro');
            window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
        }
    });
    </script>
    <?php endif; ?>

    <?php echo ui_table_toolbar([
        'primary_action' => ui_button('Adicionar Dev', ['href' => 'adicionar_dev.php', 'class' => 'button btn-with-plus btn-with-plus-text']),
        'search'         => ['name' => 'q', 'value' => $filtro_q, 'placeholder' => 'Pesquisar desenvolvedor...'],
    ]); ?>

    <?php
    $devRows = [];
    if ($result && $result->num_rows > 0) {
        while ($dev = $result->fetch_assoc()) {
            $devId  = (int)$dev['id'];
            $isSelf = ($devId === $logged_in_id);

            $usernameCell = ui_e($dev['username']);

            $editUrl = $isSelf
                ? 'perfil.php?return_to=listar_devs'
                : 'editar_dev.php?id=' . $devId;

            $acoes = '<div class="acoes-tabela">'
                . ui_icon_button('edit', ['href' => $editUrl, 'title' => $isSelf ? 'O meu perfil' : 'Editar desenvolvedor']);

            if (!$isSelf) {
                $acoes .= '<form action="apagar_dev.php" method="POST" style="margin:0;">'
                    . '<input type="hidden" name="id" value="' . $devId . '">'
                    . '<input type="hidden" name="csrf_token" value="' . ui_e($_SESSION['csrf_token']) . '">'
                    . ui_icon_button('delete', [
                        'type'  => 'submit',
                        'class' => 'btn-apagar-confirmado',
                        'title' => 'Apagar desenvolvedor',
                        'attrs' => [
                            'name'                      => 'action',
                            'value'                     => 'apagar',
                            'data-titulo-confirmacao'   => 'Apagar Desenvolvedor',
                            'data-mensagem-confirmacao' => 'Tem a certeza que deseja remover <strong>' . ui_e($dev['username']) . '</strong>?',
                        ],
                    ])
                    . '</form>';
            }

            $acoes .= '</div>';

            $devRows[] = [
                'attrs' => ['data-id' => $devId, 'data-username' => $dev['username']],
                'cells' => [
                    $usernameCell,
                    ui_e($dev['email']),
                    date('d/m/Y', strtotime($dev['data_criacao'])),
                    $acoes,
                ],
            ];
        }
    }
    $stmt->close();

    echo ui_table([
        ['label' => 'Username', 'sort' => 'username'],
        ['label' => 'Email', 'sort' => 'email'],
        ['label' => 'Criado em', 'sort' => ['key' => 'data_criacao', 'default_dir' => 'desc']],
        'Ações',
    ], $devRows, [
        'wrap_id'       => 'table-wrapper',
        'wrap_style'    => 'max-width: 1200px; margin: 0 auto;',
        'empty_message' => 'Nenhum desenvolvedor encontrado.',
        'sort'          => ['by' => $sort_by, 'dir' => $sort_dir],
    ]);
    ?>
</main>

<?php include '../templates/footer.php'; ?>
