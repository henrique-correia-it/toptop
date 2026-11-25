<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
include '../config/database.php';

require_developer('/admin');

$return_to  = $_GET['return_to'] ?? 'dev';
$return_map = [
    'dev'         => '/dev',
    'listar_devs' => 'listar_devs.php',
];
$return_url = $return_map[$return_to] ?? '/dev';
if (!array_key_exists($return_to, $return_map)) {
    $return_to = 'dev';
}

$logged_in_id = (int)($_SESSION['admin_id'] ?? 0);

$stmt_select = $conn->prepare("SELECT username, email, role FROM administradores WHERE id = ? AND role = 'desenvolvedor' LIMIT 1");
$stmt_select->bind_param("i", $logged_in_id);
$stmt_select->execute();
$admin = $stmt_select->get_result()->fetch_assoc();
$stmt_select->close();

if (!$admin) {
    header("Location: /dev");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensagem = "";
$erros_validacao = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $mensagem = "Erro de segurança. Ação não permitida.";
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));

        if ($username === '' || $email === '') {
            $erros_validacao[] = "Utilizador e email são obrigatórios.";
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros_validacao[] = "O formato do email é inválido.";
        }

        if (empty($erros_validacao)) {
            $stmt_check = $conn->prepare("SELECT id FROM administradores WHERE (username = ? OR email = ?) AND id != ?");
            $stmt_check->bind_param("ssi", $username, $email, $logged_in_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $erros_validacao[] = "O utilizador ou email já existem noutra conta.";
            }
            $stmt_check->close();
        }

        $nova_senha      = (string)($_POST['nova_senha'] ?? '');
        $confirma_senha  = (string)($_POST['confirma_senha'] ?? '');
        $atualizar_senha = false;
        if ($nova_senha !== '') {
            if (strlen($nova_senha) < 8) {
                $erros_validacao[] = "A nova senha deve ter pelo menos 8 caracteres.";
            } elseif ($nova_senha !== $confirma_senha) {
                $erros_validacao[] = "As novas senhas não coincidem.";
            } else {
                $atualizar_senha = true;
            }
        }

        if (empty($erros_validacao)) {
            if ($atualizar_senha) {
                $password_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE administradores SET username = ?, email = ?, password_hash = ? WHERE id = ? AND role = 'desenvolvedor'");
                $stmt_update->bind_param("sssi", $username, $email, $password_hash, $logged_in_id);
            } else {
                $stmt_update = $conn->prepare("UPDATE administradores SET username = ?, email = ? WHERE id = ? AND role = 'desenvolvedor'");
                $stmt_update->bind_param("ssi", $username, $email, $logged_in_id);
            }

            if ($stmt_update->execute()) {
                $_SESSION['admin_username'] = $username;
                log_seguranca("Perfil de desenvolvedor #{$logged_in_id} atualizado ('{$username}').", 'dev/perfil.php');
                $stmt_update->close();
                $redirectUrl = ($return_to === 'listar_devs') ? 'listar_devs.php?sucesso=2' : '/dev/perfil.php?sucesso=1';
                header("Location: " . $redirectUrl);
                exit;
            }
            $stmt_update->close();
            $mensagem = "Ocorreu um erro ao atualizar o perfil.";
        } else {
            $mensagem = implode('<br>', $erros_validacao);
        }

        $admin['username'] = $username;
        $admin['email']    = $email;
    }
}

include '../templates/header.php';
?>

<main class="dashboard-container animate-entry">

    <?php echo ui_page_header('O Meu Perfil', ['layout' => 'admin', 'back_url' => $return_url, 'back_title' => $return_to === 'listar_devs' ? 'Voltar' : 'Voltar ao Painel Dev']); ?>

    <div class="admin-container large">

        <?php if ($mensagem): ?>
            <?php echo ui_alert(strip_tags(str_replace('<br>', ' · ', $mensagem)), 'danger'); ?>
        <?php endif; ?>
        <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 1): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof mostrarPopup === 'function') {
                mostrarPopup('Perfil atualizado com sucesso!', 'sucesso');
            }
            if (window.history && typeof window.history.replaceState === 'function') {
                const url = new URL(window.location.href);
                url.searchParams.delete('sucesso');
                window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
            }
        });
        </script>
        <?php endif; ?>

        <form action="perfil.php?return_to=<?php echo htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="profile-edit-grid">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="profile-column">
                <?php
                $infoHtml = ui_field('username', 'Nome de Utilizador', [
                    'id'          => 'username',
                    'value'       => $admin['username'],
                    'required'    => true,
                    'placeholder' => 'Ex: dev_toptop',
                ]) . ui_field('email', 'Endereço de Email', [
                    'type'        => 'email',
                    'id'          => 'email',
                    'value'       => $admin['email'],
                    'required'    => true,
                    'placeholder' => 'dev@exemplo.com',
                ]);
                echo ui_card($infoHtml, [
                    'variant'    => 'form',
                    'title_html' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Informação da Conta',
                ]);
                ?>
            </div>

            <div class="profile-column">
                <?php
                $segurancaHtml = '<p class="helper-text">Deixe em branco se não pretender alterar a senha atual.</p>'
                    . ui_field('nova_senha', 'Nova Senha', [
                        'type'            => 'password',
                        'id'              => 'nova_senha',
                        'autocomplete'    => 'new-password',
                        'placeholder'     => 'Mínimo 8 caracteres',
                        'password_toggle' => true,
                    ]) . ui_field('confirma_senha', 'Confirmar Nova Senha', [
                        'type'            => 'password',
                        'id'              => 'confirma_senha',
                        'autocomplete'    => 'new-password',
                        'placeholder'     => 'Repita a nova senha',
                        'password_toggle' => true,
                    ]);
                echo ui_card($segurancaHtml, [
                    'variant'    => 'form',
                    'title_html' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Segurança e Senha',
                ]);
                ?>

                <div class="form-footer-actions">
                    <?php
                    echo ui_button('Cancelar', ['href' => $return_url]);
                    echo ui_button('Guardar Alterações', ['type' => 'submit', 'variant' => 'primary']);
                    ?>
                </div>
            </div>
        </form>
    </div>
</main>

<?php include '../templates/footer.php'; ?>
