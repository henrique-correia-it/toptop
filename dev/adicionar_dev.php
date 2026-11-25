<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

require_developer('/admin');

$mensagem       = '';
$username_valor = '';
$email_valor    = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_from_post()) {
        $mensagem = 'Erro de segurança. Ação não permitida.';
        goto fim_adicionar_dev;
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $username_valor = $username;
    $email_valor    = $email;

    $erros_validacao = [];
    if ($username === '' || $email === '' || $password === '') {
        $erros_validacao[] = 'Todos os campos são obrigatórios.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros_validacao[] = 'O formato do email é inválido.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $erros_validacao[] = 'A senha deve ter pelo menos 8 caracteres.';
    }

    if (empty($erros_validacao)) {
        $stmt = $conn->prepare("SELECT id FROM administradores WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $erros_validacao[] = 'Este nome de utilizador já está a ser utilizado.';
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM administradores WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $erros_validacao[] = 'Este email já está registado.';
        }
        $stmt->close();
    }

    if (empty($erros_validacao)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $role          = 'desenvolvedor';
        $data_agora    = date('Y-m-d H:i:s');

        $stmt_insert = $conn->prepare("INSERT INTO administradores (username, email, password_hash, role, data_criacao) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("sssss", $username, $email, $password_hash, $role, $data_agora);

        if ($stmt_insert->execute()) {
            log_seguranca("Novo desenvolvedor criado: '{$username}'.", 'dev/adicionar_dev.php');
            $stmt_insert->close();
            header("Location: listar_devs.php?sucesso=1");
            exit;
        } else {
            $mensagem = 'Ocorreu um erro inesperado ao criar o desenvolvedor.';
            log_sql("Erro ao adicionar dev: " . $conn->error, 'dev/adicionar_dev.php');
        }
        $stmt_insert->close();
    } else {
        $mensagem = implode('<br>', $erros_validacao);
    }
}
fim_adicionar_dev:
$titulo_pagina = 'Novo Desenvolvedor';
include '../templates/header.php';
?>

<main class="dashboard-container animate-entry">

    <?php echo ui_page_header('Novo Desenvolvedor', ['layout' => 'admin', 'back_url' => 'listar_devs.php', 'back_title' => 'Voltar']); ?>

    <div class="admin-container large">

        <?php if ($mensagem): ?>
            <?php echo ui_alert(strip_tags(str_replace('<br>', ' · ', $mensagem)), 'danger'); ?>
        <?php endif; ?>

        <form action="adicionar_dev.php" method="post" class="profile-edit-grid" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="profile-column">
                <?php ob_start(); ?>
                <div class="form-group">
                    <label for="username">Nome de Utilizador</label>
                    <?php echo ui_input('username', ['id' => 'username', 'value' => $username_valor, 'required' => true, 'placeholder' => 'Ex: kiko_dev', 'autocomplete' => 'off']); ?>
                </div>
                <div class="form-group">
                    <label for="email">Endereço de Email</label>
                    <?php echo ui_input('email', ['type' => 'email', 'id' => 'email', 'value' => $email_valor, 'required' => true, 'placeholder' => 'dev@exemplo.com', 'autocomplete' => 'off']); ?>
                </div>
                <?php
                echo ui_card(ob_get_clean(), [
                    'variant'    => 'form',
                    'title_html' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Identidade',
                ]);
                ?>
            </div>

            <div class="profile-column">
                <?php ob_start(); ?>
                <p class="helper-text">Defina uma senha provisória para a nova conta de desenvolvedor.</p>
                <div class="form-group">
                    <label for="password">Senha Provisória (mín. 8 caracteres)</label>
                    <div class="password-wrapper">
                        <?php echo ui_input('password', ['type' => 'password', 'id' => 'password', 'required' => true, 'placeholder' => 'Crie uma senha forte', 'autocomplete' => 'new-password']); ?>
                        <button type="button" class="toggle-senha" aria-label="Mostrar ou ocultar palavra-passe" data-target="password" style="background:none;border:none;padding:0;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>
                <?php
                echo ui_card(ob_get_clean(), [
                    'variant'    => 'form',
                    'title_html' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Segurança Inicial',
                ]);
                ?>

                <div class="form-footer-actions">
                    <?php
                    echo ui_button('Cancelar', ['href' => 'listar_devs.php']);
                    echo ui_button('Criar Desenvolvedor', ['type' => 'submit', 'variant' => 'primary']);
                    ?>
                </div>
            </div>
        </form>
    </div>
</main>

<?php include '../templates/footer.php'; ?>
