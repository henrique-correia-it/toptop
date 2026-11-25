<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
include '../config/database.php';

require_admin_manager('/admin');

$mensagem = "";
$username_valor = "";
$email_valor = "";
$role_valor = "admin";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$roles_validos = ['admin', 'superadmin'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $mensagem = "Erro de segurança. Ação não permitida.";
        goto fim_adicionar_admin;
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role     = (string)($_POST['role'] ?? '');

    $username_valor = htmlspecialchars($username);
    $email_valor = htmlspecialchars($email);
    $role_valor = htmlspecialchars($role);

    // Validação
    $erros_validacao = [];
    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $erros_validacao[] = "Todos os campos são obrigatórios.";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros_validacao[] = "O formato do email é inválido.";
    }
    if (!empty($password) && strlen($password) < 8) {
        $erros_validacao[] = "A senha deve ter pelo menos 8 caracteres.";
    }
    if (!in_array($role, $roles_validos, true)) {
        $erros_validacao[] = "Função (role) inválida.";
    }

    // Se não houver erros básicos, verifica a base de dados
    if (empty($erros_validacao)) {
        $stmt = $conn->prepare("SELECT id FROM administradores WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $erros_validacao[] = "Este nome de utilizador já está a ser utilizado.";
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM administradores WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $erros_validacao[] = "Este email já está registado.";
        }
        $stmt->close();
    }
    
    if (empty($erros_validacao)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // --- INÍCIO DA CORREÇÃO: Define a hora atual e insere-a na BD ---
        $data_agora = date('Y-m-d H:i:s');
        
        $stmt_insert = $conn->prepare("INSERT INTO administradores (username, email, password_hash, role, data_criacao) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("sssss", $username, $email, $password_hash, $role, $data_agora);
        // --- FIM DA CORREÇÃO ---
        
        if ($stmt_insert->execute()) {
            log_seguranca("Novo administrador criado: '{$username}' ({$role}).", 'adicionar_admin.php');
            header("Location: listar_admins.php?sucesso=1");
            exit;
        } else {
            $mensagem = "Ocorreu um erro inesperado ao criar o administrador.";
            log_sql("Erro ao adicionar admin: " . $conn->error, 'adicionar_admin.php');
        }
        $stmt_insert->close();
    } else {
        $mensagem = implode('<br>', $erros_validacao);
    }
}
fim_adicionar_admin:
include '../templates/header.php';
?>

<main class="dashboard-container animate-entry">

    <?php echo ui_page_header('Novo Administrador', ['layout' => 'admin', 'back_url' => 'listar_admins.php', 'back_title' => 'Voltar']); ?>

    <div class="admin-container large">

        <?php if ($mensagem): ?>
            <?php echo ui_alert(strip_tags(str_replace('<br>', ' · ', $mensagem)), 'danger'); ?>
        <?php endif; ?>

        <form action="adicionar_admin.php" method="post" class="profile-edit-grid" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <!-- Coluna Esquerda: Info Básica -->
            <div class="profile-column">
                <?php
                $roles_opcoes = [
                    'admin'      => 'Administrador Normal',
                    'superadmin' => 'Super-Administrador',
                ];
                $identidadeHtml = ui_field('username', 'Nome de Utilizador', [
                    'id'           => 'username',
                    'value'        => $username_valor,
                    'required'     => true,
                    'placeholder'  => 'Ex: joao_admin',
                    'autocomplete' => 'off',
                ]) . ui_field('email', 'Endereço de Email', [
                    'type'         => 'email',
                    'id'           => 'email',
                    'value'        => $email_valor,
                    'required'     => true,
                    'placeholder'  => 'joao@exemplo.com',
                    'autocomplete' => 'off',
                ]) . ui_field('role', 'Função no Sistema', [
                    'type'    => 'select',
                    'id'      => 'role',
                    'options' => $roles_opcoes,
                    'value'   => $role_valor,
                ]);
                echo ui_card($identidadeHtml, [
                    'variant'    => 'form',
                    'title_html' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Identidade',
                ]);
                ?>
            </div>

            <!-- Coluna Direita: Segurança -->
            <div class="profile-column">
                <?php
                $segurancaHtml = '<p class="helper-text">Defina uma senha provisória para o novo utilizador.</p>'
                    . ui_field('password', 'Senha Provisória (mín. 8 caracteres)', [
                        'type'            => 'password',
                        'id'              => 'password',
                        'required'        => true,
                        'placeholder'     => 'Crie uma senha forte',
                        'autocomplete'    => 'new-password',
                        'password_toggle' => true,
                    ]);
                echo ui_card($segurancaHtml, [
                    'variant'    => 'form',
                    'title_html' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Segurança Inicial',
                ]);
                ?>

                <div class="form-footer-actions">
                    <?php
                    echo ui_button('Cancelar', ['href' => 'listar_admins.php']);
                    echo ui_button('Criar Administrador', ['type' => 'submit', 'variant' => 'primary']);
                    ?>
                </div>
            </div>
        </form>
    </div>
</main>

<?php include '../templates/footer.php'; ?>
