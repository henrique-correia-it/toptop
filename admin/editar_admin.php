<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
include '../config/database.php';

require_admin_login('/entrar');

// --- LÓGICA DO BOTÃO VOLTAR INTELIGENTE (whitelist estrita contra Open Redirect) ---
$return_to = $_GET['return_to'] ?? 'admin';
$return_map = [
    'admin'         => '/admin',
    'listar_admins' => 'listar_admins.php',
];
$return_url = $return_map[$return_to] ?? '/admin';

$id_a_editar = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_a_editar) {
    header("Location: /admin");
    exit;
}

$logged_in_id   = (int)$_SESSION['admin_id'];
$is_superadmin  = is_admin_manager();
$is_own_profile = ($id_a_editar === $logged_in_id);

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

// Separação /admin vs /dev: contas fora dos roles de loja nunca são geridas no /admin
if (!in_array(($admin['role'] ?? ''), ['admin', 'superadmin'], true)) {
    header('Location: listar_admins.php?erro=1');
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

    $username = trim((string)($_POST['username'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $role     = $is_superadmin ? (string)($_POST['role'] ?? $admin['role']) : $admin['role'];
    
    // Proteção NÍVEL 2: VERIFICAÇÃO NO BACK-END (não despromover o último Super-Admin)
    if ($admin['role'] === 'superadmin' && $role !== 'superadmin') {
        $stmt_count = $conn->prepare("SELECT COUNT(id) FROM administradores WHERE role = 'superadmin'");
        $stmt_count->execute();
        $role_count = $stmt_count->get_result()->fetch_row()[0];
        $stmt_count->close();

        if ($role_count <= 1) {
            $erros_validacao[] = "Não é possível despromover a única conta de Super-Administrador do sistema.";
        }
    }

    if (empty($username) || empty($email)) { $erros_validacao[] = "Utilizador e email são obrigatórios."; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $erros_validacao[] = "O formato do email é inválido."; }

    $stmt_check = $conn->prepare("SELECT id FROM administradores WHERE (username = ? OR email = ?) AND id != ?");
    $stmt_check->bind_param("ssi", $username, $email, $id_a_editar);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) { $erros_validacao[] = "O utilizador ou email já existem noutra conta."; }
    $stmt_check->close();

    // Whitelist estrita de roles da loja
    $roles_validos = ['admin', 'superadmin'];
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

    <?php echo ui_page_header($is_own_profile ? 'O Meu Perfil' : 'Editar Administrador', ['layout' => 'admin', 'back_url' => $return_url, 'back_title' => 'Voltar']); ?>

    <div class="admin-container large">

        <?php if ($mensagem): ?>
            <?php echo ui_alert(strip_tags(str_replace('<br>', ' · ', $mensagem)), 'danger'); ?>
        <?php endif; ?>
        <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 2 && $is_own_profile): ?>
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

        <form action="editar_admin.php?id=<?php echo $id_a_editar; ?>&return_to=<?php echo htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="profile-edit-grid">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <!-- Coluna Esquerda: Info Básica -->
            <div class="profile-column">
                <?php ob_start(); ?>
                <div class="form-group">
                    <label for="username">Nome de Utilizador</label>
                    <?php echo ui_input('username', ['id' => 'username', 'value' => $admin['username'], 'required' => true, 'placeholder' => 'Ex: joao_admin']); ?>
                </div>
                
                <div class="form-group">
                    <label for="email">Endereço de Email</label>
                    <?php echo ui_input('email', ['type' => 'email', 'id' => 'email', 'value' => $admin['email'], 'required' => true, 'placeholder' => 'exemplo@email.com']); ?>
                </div>
                
                <?php if ($is_superadmin): ?>
                <div class="form-group">
                    <label for="role">Função no Sistema</label>
                    <?php
                        $role_count_display = 999;
                        if ($admin['role'] === 'superadmin') {
                            $stmt_count_display = $conn->prepare("SELECT COUNT(id) FROM administradores WHERE role = 'superadmin'");
                            $stmt_count_display->execute();
                            $role_count_display = $stmt_count_display->get_result()->fetch_row()[0];
                            $stmt_count_display->close();
                        }
                        $is_last_critical_role = ($admin['role'] === 'superadmin' && $role_count_display <= 1);
                    ?>
                    <?php if ($is_last_critical_role): ?>
                        <?php echo ui_select('role', ['superadmin' => 'Super-Administrador'], 'superadmin', ['id' => 'role', 'disabled' => true]); ?>
                        <input type="hidden" name="role" value="superadmin">
                        <p class="helper-text warning">Esta função não pode ser alterada por ser a única conta deste tipo.</p>
                    <?php else: ?>
                        <?php
                        $roles_edit_opcoes = [
                            'admin'      => 'Administrador Normal',
                            'superadmin' => 'Super-Administrador',
                        ];
                        echo ui_select('role', $roles_edit_opcoes, $admin['role'], ['id' => 'role']);
                        ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php
                echo ui_card(ob_get_clean(), [
                    'variant'    => 'form',
                    'title_html' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Informação da Conta',
                ]);
                ?>
            </div>

            <!-- Coluna Direita: Segurança -->
            <div class="profile-column">
                <?php ob_start(); ?>
                <p class="helper-text">Deixe em branco se não pretender alterar a senha atual.</p>
                <div class="form-group">
                    <label for="nova_senha">Nova Senha</label>
                    <div class="password-wrapper">
                        <?php echo ui_input('nova_senha', ['type' => 'password', 'id' => 'nova_senha', 'autocomplete' => 'new-password', 'placeholder' => 'Mínimo 8 caracteres']); ?>
                        <button type="button" class="toggle-senha" aria-label="Mostrar ou ocultar palavra-passe" data-target="nova_senha" style="background:none;border:none;padding:0;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirma_senha">Confirmar Nova Senha</label>
                    <div class="password-wrapper">
                        <?php echo ui_input('confirma_senha', ['type' => 'password', 'id' => 'confirma_senha', 'autocomplete' => 'new-password', 'placeholder' => 'Repita a nova senha']); ?>
                        <button type="button" class="toggle-senha" aria-label="Mostrar ou ocultar palavra-passe" data-target="confirma_senha" style="background:none;border:none;padding:0;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>
                <?php
                echo ui_card(ob_get_clean(), [
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
