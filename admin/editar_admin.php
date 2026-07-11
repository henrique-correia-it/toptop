<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

// Segurança: Apenas admins logados podem aceder
if (!isset($_SESSION['admin_logado']) || !$_SESSION['admin_logado']) {
    header("Location: /entrar");
    exit;
}

// --- LÓGICA DO BOTÃO VOLTAR INTELIGENTE ---
$return_to = $_GET['return_to'] ?? 'admin';
if ($return_to === 'dev') {
    $return_url = '/dev';
} elseif ($return_to === 'admin') {
    $return_url = '/admin';
} else {
    $return_url = htmlspecialchars($return_to) . '.php';
}

$id_a_editar = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_a_editar) {
    header("Location: /admin");
    exit;
}

$logged_in_id = $_SESSION['admin_id'];
$logged_in_role = $_SESSION['admin_role'];
$is_superadmin = ($logged_in_role === 'superadmin' || $logged_in_role === 'desenvolvedor');
$is_dev = ($logged_in_role === 'desenvolvedor');
$is_own_profile = ($id_a_editar == $logged_in_id);

// Segurança: Verifica se o utilizador tem permissão para editar este perfil
if (!$is_superadmin && !$is_own_profile) {
    header("Location: /admin");
    exit;
}

// Vai buscar os dados atuais do administrador a ser editado
$stmt_select = $conn->prepare("SELECT username, email, role FROM administradores WHERE id = ?");
$stmt_select->bind_param("i", $id_a_editar);
$stmt_select->execute();
$result = $stmt_select->get_result();
if ($result->num_rows === 1) {
    $admin = $result->fetch_assoc();
} else {
    header("Location: listar_admins.php");
    exit;
}
$stmt_select->close();

// Proteção: Superadmin não pode editar Desenvolvedor
if ($admin['role'] === 'desenvolvedor' && !$is_dev) {
    header("Location: listar_admins.php?erro=protecao_dev");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensagem = "";
$erros_validacao = [];

// --- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $mensagem = "Erro de segurança. Ação não permitida.";
        goto fim_editar_admin;
    }

    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $role     = $_POST['role'] ?? $admin['role'];
    
    // Proteção NÍVEL 2: VERIFICAÇÃO NO BACK-END
    if (in_array($admin['role'], ['superadmin', 'desenvolvedor']) && $role !== $admin['role']) {
        $stmt_count = $conn->prepare("SELECT COUNT(id) FROM administradores WHERE role = ?");
        $stmt_count->bind_param("s", $admin['role']);
        $stmt_count->execute();
        $role_count = $stmt_count->get_result()->fetch_row()[0];
        $stmt_count->close();
        
        if ($role_count <= 1) {
            $role_nome = ($admin['role'] === 'superadmin') ? 'Super-Administrador' : 'Desenvolvedor';
            $erros_validacao[] = "Não é possível despromover a única conta de $role_nome do sistema.";
        }
    }

    if (empty($username) || empty($email)) { $erros_validacao[] = "Utilizador e email são obrigatórios."; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $erros_validacao[] = "O formato do email é inválido."; }

    $stmt_check = $conn->prepare("SELECT id FROM administradores WHERE (username = ? OR email = ?) AND id != ?");
    $stmt_check->bind_param("ssi", $username, $email, $id_a_editar);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) { $erros_validacao[] = "O utilizador ou email já existem noutra conta."; }
    $stmt_check->close();

    // Whitelist de roles permitidas
    $roles_validos = ['admin', 'superadmin', 'desenvolvedor'];
    if (!in_array($role, $roles_validos, true)) {
        $erros_validacao[] = "Função (role) inválida.";
    }

    // Validação da nova senha
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';
    $atualizar_senha = false;
    if (!empty($nova_senha)) {
        if (strlen($nova_senha) < 8) { $erros_validacao[] = "A nova senha deve ter pelo menos 8 caracteres."; }
        if ($nova_senha !== $confirma_senha) { $erros_validacao[] = "As novas senhas não coincidem."; }
        else { $atualizar_senha = true; }
    }
    
    if (empty($erros_validacao)) {
        if ($atualizar_senha) {
            $password_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt_update = $conn->prepare("UPDATE administradores SET username = ?, email = ?, role = ?, password_hash = ? WHERE id = ?");
            $stmt_update->bind_param("ssssi", $username, $email, $role, $password_hash, $id_a_editar);
        } else {
            $stmt_update = $conn->prepare("UPDATE administradores SET username = ?, email = ?, role = ? WHERE id = ?");
            $stmt_update->bind_param("sssi", $username, $email, $role, $id_a_editar);
        }
        
        if ($stmt_update->execute()) {
            if ($is_own_profile) {
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_role'] = $role;
            }
            
            header("Location: " . $return_url . "?sucesso=2");
            exit;

        } else {
            $mensagem = "Ocorreu um erro ao atualizar.";
        }
        $stmt_update->close();
    } else {
        $mensagem = implode('<br>', $erros_validacao);
    }
    
    // Mantém os valores submetidos em caso de erro
    $admin['username'] = $username;
    $admin['email'] = $email;
    $admin['role'] = $role;
}
fim_editar_admin:
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
    <div class="admin-page-header">
        <div class="header-title-container">
            <?php renderBackButton($return_url, 'Voltar'); ?>
            <h2><?php echo $is_own_profile ? 'O Meu Perfil' : 'Editar Administrador'; ?></h2>
        </div>
    </div>

    <div class="admin-container large">

        <?php if ($mensagem): ?>
            <div class="auth-message error" style="margin-bottom: 30px;"><?php echo $mensagem; ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 2 && $is_own_profile): ?>
            <div class="auth-message success" style="margin-bottom: 30px;">Perfil atualizado com sucesso!</div>
        <?php endif; ?>

        <form action="editar_admin.php?id=<?php echo $id_a_editar; ?>&return_to=<?php echo htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="profile-edit-grid">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <!-- Coluna Esquerda: Info Básica -->
            <div class="profile-column">
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>
                        <h3>Informação da Conta</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="form-group">
                            <label for="username">Nome de Utilizador</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required placeholder="Ex: kiko_dev">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Endereço de Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required placeholder="exemplo@email.com">
                        </div>
                        
                        <?php if ($is_superadmin): ?>
                        <div class="form-group">
                            <label for="role">Função no Sistema</label>
                            <?php
                                $stmt_count_display = $conn->prepare("SELECT COUNT(id) FROM administradores WHERE role = ?");
                                $stmt_count_display->bind_param("s", $admin['role']);
                                $stmt_count_display->execute();
                                $role_count_display = $stmt_count_display->get_result()->fetch_row()[0];
                                $stmt_count_display->close();
                                $is_last_critical_role = in_array($admin['role'], ['superadmin', 'desenvolvedor']) && $role_count_display <= 1;
                            ?>
                            <?php if ($is_last_critical_role): ?>
                                <div class="select-wrapper">
                                    <select name="role" id="role" disabled class="select-estilizado disabled">
                                        <option value="<?php echo $admin['role']; ?>" selected><?php echo ($admin['role'] === 'superadmin') ? 'Super-Admin' : 'Desenvolvedor'; ?></option>
                                    </select>
                                </div>
                                <input type="hidden" name="role" value="<?php echo $admin['role']; ?>"> 
                                <p class="helper-text warning">Esta função não pode ser alterada por ser a única conta deste tipo.</p>
                            <?php else: ?>
                                <div class="select-wrapper">
                                    <select name="role" id="role" class="select-estilizado">
                                        <option value="admin" <?php echo ($admin['role'] == 'admin') ? 'selected' : ''; ?>>Administrador Normal</option>
                                        <option value="superadmin" <?php echo ($admin['role'] == 'superadmin') ? 'selected' : ''; ?>>Super-Administrador</option>
                                        <?php if ($is_dev): ?>
                                            <option value="desenvolvedor" <?php echo ($admin['role'] == 'desenvolvedor') ? 'selected' : ''; ?>>Desenvolvedor</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita: Segurança -->
            <div class="profile-column">
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></div>
                        <h3>Segurança e Senha</h3>
                    </div>
                    <div class="form-card-body">
                        <p class="helper-text">Deixe em branco se não pretender alterar a senha atual.</p>
                        <div class="form-group">
                            <label for="nova_senha">Nova Senha</label>
                            <div class="password-wrapper">
                                <input type="password" id="nova_senha" name="nova_senha" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                                <span class="toggle-senha" data-target="nova_senha">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirma_senha">Confirmar Nova Senha</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirma_senha" name="confirma_senha" autocomplete="new-password" placeholder="Repita a nova senha">
                                <span class="toggle-senha" data-target="confirma_senha">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-footer-actions">
                    <a href="<?php echo $return_url; ?>" class="button-secondary">Cancelar</a>
                    <button type="submit" class="button-primary">Guardar Alterações</button>
                </div>
            </div>
        </form>
    </div>
</main>

<?php include '../templates/footer.php'; ?>
