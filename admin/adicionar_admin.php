<?php

require_once __DIR__ . '/../config/session.php';
include '../config/database.php';


// Apenas superadmins e desenvolvedores podem aceder
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    header("Location: /admin");
    exit;
}

$mensagem = "";
$username_valor = "";
$email_valor = "";
$role_valor = "admin";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$roles_validos = ['admin', 'superadmin', 'desenvolvedor'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $mensagem = "Erro de segurança. Ação não permitida.";
        goto fim_adicionar_admin;
    }

    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $role     = $_POST['role'] ?? '';

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
            header("Location: listar_admins.php?sucesso=1");
            exit;
        } else {
            $mensagem = "Ocorreu um erro inesperado ao criar o administrador.";
            error_log("Erro ao adicionar admin: " . $conn->error); 
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

<!-- Bloquear scroll automático no refresh -->
<script>
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    window.scrollTo(0, 0);
</script>
    <div class="admin-container large">
        <div class="admin-page-header">
            <?php renderBackButton('listar_admins.php', 'Voltar'); ?>
            <h2>Novo Administrador</h2>
        </div>

        <?php if ($mensagem): ?>
            <div class="auth-message error" style="margin-bottom: 30px;"><?php echo $mensagem; ?></div>
        <?php endif; ?>

        <form action="adicionar_admin.php" method="post" class="profile-edit-grid" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <!-- Coluna Esquerda: Info Básica -->
            <div class="profile-column">
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>
                        <h3>Identidade</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="form-group">
    <label for="username">Nome de Utilizador</label>
    <input type="text" id="username" name="username" value="<?php echo $username_valor; ?>" required placeholder="Ex: joao_admin" autocomplete="off">
</div>
                        
                        <div class="form-group">
    <label for="email">Endereço de Email</label>
    <input type="email" id="email" name="email" value="<?php echo $email_valor; ?>" required placeholder="joao@exemplo.com" autocomplete="off">
</div>

                        <div class="form-group">
                            <label for="role">Função no Sistema</label>
                            <div class="select-wrapper">
                                <select name="role" id="role" class="select-estilizado">
                                    <option value="admin" <?php echo ($role_valor == 'admin') ? 'selected' : ''; ?>>Administrador Normal</option>
                                    <option value="superadmin" <?php echo ($role_valor == 'superadmin') ? 'selected' : ''; ?>>Super-Administrador</option>
                                    <?php if ($_SESSION['admin_role'] === 'desenvolvedor'): ?>
                                        <option value="desenvolvedor" <?php echo ($role_valor == 'desenvolvedor') ? 'selected' : ''; ?>>Desenvolvedor</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita: Segurança -->
            <div class="profile-column">
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></div>
                        <h3>Segurança Inicial</h3>
                    </div>
                    <div class="form-card-body">
                        <p class="helper-text">Defina uma senha provisória para o novo utilizador.</p>
                        <div class="form-group">
    <label for="password">Senha Provisória (mín. 8 caracteres)</label>
    <div class="password-wrapper">
        <input type="password" id="password" name="password" required placeholder="Crie uma senha forte" autocomplete="new-password">
        <span class="toggle-senha" data-target="password">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        </span>
    </div>
</div>
                    </div>
                </div>

                <div class="form-footer-actions">
                    <a href="listar_admins.php" class="button-secondary">Cancelar</a>
                    <button type="submit" class="button-primary">Criar Administrador</button>
                </div>
            </div>
        </form>
    </div>
</main>

<?php include '../templates/footer.php'; ?>
