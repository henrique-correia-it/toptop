<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/cliente_auth.php';
require_once __DIR__ . '/config/rate_limit.php';

function finalizar_login_admin(mysqli $conn, array $admin): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $conn->prepare("UPDATE administradores SET failed_login_attempts = 0, last_login_attempt = NULL WHERE id = ?");
    $stmt->bind_param("i", $admin['id']);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO admin_login_logs (admin_id, username, role, ip, user_agent, resultado) VALUES (?, ?, ?, ?, ?, 'sucesso')");
    $stmt->bind_param("issss", $admin['id'], $admin['username'], $admin['role'], $ip, $ua);
    $stmt->execute();
    $stmt->close();

    session_regenerate_id(true);
    $_SESSION['admin_logado'] = true;
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function finalizar_login_cliente(mysqli $conn, array $customer): void
{
    $stmt = $conn->prepare("UPDATE clientes SET failed_login_attempts = 0, last_login_attempt = NULL, ultimo_login = NOW() WHERE id = ?");
    $stmt->bind_param('i', $customer['id']);
    $stmt->execute();
    $stmt->close();

    login_cliente_session($customer);
}

function procurar_admin_login(mysqli $conn, string $login): ?array
{
    $stmt = $conn->prepare("SELECT id, username, email, password_hash, role, failed_login_attempts, last_login_attempt FROM administradores WHERE username = ? OR email = ? LIMIT 1");
    $stmt->bind_param("ss", $login, $login);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $admin;
}

function admin_esta_bloqueado(array $admin, int $lockout): bool
{
    return (int)$admin['failed_login_attempts'] >= 5
        && !empty($admin['last_login_attempt'])
        && strtotime($admin['last_login_attempt']) > time() - $lockout;
}

function cliente_esta_bloqueado(array $customer, int $lockout): bool
{
    return (int)$customer['failed_login_attempts'] >= 5
        && !empty($customer['last_login_attempt'])
        && strtotime($customer['last_login_attempt']) > time() - $lockout;
}

$next = $_GET['next'] ?? ($_POST['next'] ?? '/minha-conta');
if (!is_string($next) || !str_starts_with($next, '/') || str_starts_with($next, '//') || str_starts_with($next, '/\\') || preg_match('/[\r\n]/', $next)) {
    $next = '/minha-conta';
}

if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true && !isset($_GET['dev_preview'])) {
    header('Location: /admin');
    exit;
}
if (is_cliente_logged_in()) {
    header('Location: ' . $next);
    exit;
}

$titulo_pagina = 'Entrar';
$erro = '';
$aviso = '';
$loginValor = '';
$unlock_timestamp = 0;
$mostrarEscolhaPerfil = false;

// --- DEVELOPER PREVIEW MODE ---
$is_dev_preview = false;
if (isset($_GET['dev_preview'])) {
    if (is_developer()) {
        $is_dev_preview = true;
        $estado_preview = $_GET['dev_preview'];
        $loginValor = 'developer';
        if ($estado_preview === 'erro') {
            $erro = "Utilizador ou senha incorretos!";
            $aviso = "Atenção: Restam-lhe 2 tentativas.";
        } elseif ($estado_preview === 'bloqueada') {
            $erro = "Conta temporariamente bloqueada. Tente novamente em 3 minutos.";
        }
    } else {
        header("Location: /admin");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_from_post()) {
        $erro = 'Erro de seguranca. Recarregue a pagina e tente novamente.';
    } elseif (isset($_POST['perfil_escolhido'])) {
        $pendente = $_SESSION['login_perfis_pendentes'] ?? null;
        $perfil = $_POST['perfil_escolhido'];

        if (!$pendente || ($pendente['expira'] ?? 0) < time()) {
            unset($_SESSION['login_perfis_pendentes']);
            $erro = 'A escolha expirou. Faca login novamente.';
        } elseif ($perfil === 'admin' && !empty($pendente['admin_id'])) {
            $stmt = $conn->prepare("SELECT id, username, email, password_hash, role, failed_login_attempts, last_login_attempt FROM administradores WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $pendente['admin_id']);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            unset($_SESSION['login_perfis_pendentes']);

            if ($admin) {
                finalizar_login_admin($conn, $admin);
                session_write_close();
                header('Location: /admin');
                exit;
            }
            $erro = 'Perfil de administrador nao encontrado.';
        } elseif ($perfil === 'cliente' && !empty($pendente['cliente_id'])) {
            $customer = customer_find_by_id($conn, (int)$pendente['cliente_id']);
            unset($_SESSION['login_perfis_pendentes']);

            if ($customer && (int)$customer['ativo'] === 1) {
                finalizar_login_cliente($conn, $customer);
                header('Location: ' . ($pendente['next'] ?? '/minha-conta'));
                exit;
            }
            $erro = 'Perfil de cliente nao encontrado.';
        } else {
            $mostrarEscolhaPerfil = true;
            $erro = 'Escolha um perfil valido.';
        }
    } else {
        unset($_SESSION['login_perfis_pendentes']);
        $login = trim($_POST['usuario'] ?? ($_POST['email'] ?? ''));
        $password = $_POST['senha'] ?? ($_POST['password'] ?? '');
        $loginValor = htmlspecialchars($login, ENT_QUOTES, 'UTF-8');
        $lockout = 300;

        if ($login === '' || $password === '') {
            $erro = 'Preencha o utilizador/email e a palavra-passe.';
        } elseif (!rate_limit_allow('login_ip', 20, 300)) {
            $erro = 'Demasiadas tentativas de início de sessão. Aguarde alguns minutos e tente novamente.';
        } else {
            $admin = procurar_admin_login($conn, $login);
            $email = customer_clean_email($login);
            $customer = filter_var($email, FILTER_VALIDATE_EMAIL) ? customer_find_by_email($conn, $email) : null;

            if (!$admin && !$customer) {
                // Hash dummy constante para mitigar enumeração por timing attack
                password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            }

            $adminBloqueado = $admin ? admin_esta_bloqueado($admin, $lockout) : false;
            $clienteBloqueado = $customer ? cliente_esta_bloqueado($customer, $lockout) : false;
            $adminValido = $admin && !$adminBloqueado && password_verify($password, $admin['password_hash']);
            $clientePasswordOk = $customer && !$clienteBloqueado && password_verify($password, $customer['password_hash']);
            $clienteNaoVerificado = $clientePasswordOk && (int)$customer['ativo'] === 0;
            $clienteValido = $clientePasswordOk && (int)$customer['ativo'] === 1;

            if ($adminValido && $clienteValido) {
                log_app("Login com dupla escolha de perfil para {$login}.", 'INFO', 'entrar.php');
                $_SESSION['login_perfis_pendentes'] = [
                    'admin_id' => (int)$admin['id'],
                    'cliente_id' => (int)$customer['id'],
                    'next' => $next,
                    'expira' => time() + 300,
                ];
                $mostrarEscolhaPerfil = true;
            } elseif ($adminValido) {
                log_app("Login admin '{$admin['username']}' ({$admin['role']}).", 'INFO', 'entrar.php');
                finalizar_login_admin($conn, $admin);
                session_write_close();
                header('Location: /admin');
                exit;
            } elseif ($clienteValido) {
                log_app("Login cliente #{$customer['id']} ({$customer['email']}).", 'INFO', 'entrar.php');
                finalizar_login_cliente($conn, $customer);
                header('Location: ' . $next);
                exit;
            } else {
                $mensagemBloqueio = 'Conta temporariamente bloqueada devido a várias tentativas falhadas. Tente novamente dentro de alguns minutos.';

                if ($admin && !$adminBloqueado && !$clienteValido && !$clienteNaoVerificado) {
                    $stmt = $conn->prepare(
                        "UPDATE administradores
                         SET failed_login_attempts = CASE
                                WHEN last_login_attempt IS NULL OR last_login_attempt <= DATE_SUB(NOW(), INTERVAL ? SECOND) THEN 1
                                ELSE failed_login_attempts + 1
                             END,
                             last_login_attempt = NOW()
                         WHERE id = ?"
                    );
                    $stmt->bind_param("ii", $lockout, $admin['id']);
                    $stmt->execute();
                    $stmt->close();

                    $stmt_chk = $conn->prepare("SELECT failed_login_attempts FROM administradores WHERE id = ? LIMIT 1");
                    $stmt_chk->bind_param("i", $admin['id']);
                    $stmt_chk->execute();
                    $row_chk = $stmt_chk->get_result()->fetch_assoc();
                    $stmt_chk->close();
                    $novas_tentativas = (int)($row_chk['failed_login_attempts'] ?? 1);

                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $motivo = $novas_tentativas >= 5 ? 'Conta bloqueada' : 'Senha incorreta';
                    $stmt = $conn->prepare("INSERT INTO admin_login_logs (admin_id, username, role, ip, user_agent, resultado, motivo_falha) VALUES (?, ?, ?, ?, ?, 'falha', ?)");
                    $stmt->bind_param("isssss", $admin['id'], $admin['username'], $admin['role'], $ip, $ua, $motivo);
                    $stmt->execute();
                    $stmt->close();

                    if ($novas_tentativas >= 5) {
                        $adminBloqueado = true;
                    }
                }

                if ($customer && !$clienteBloqueado && !$adminValido && !$clienteNaoVerificado) {
                    $stmt = $conn->prepare(
                        "UPDATE clientes
                         SET failed_login_attempts = CASE
                                WHEN last_login_attempt IS NULL OR last_login_attempt <= DATE_SUB(NOW(), INTERVAL ? SECOND) THEN 1
                                ELSE failed_login_attempts + 1
                             END,
                             last_login_attempt = NOW()
                         WHERE id = ?"
                    );
                    $stmt->bind_param('ii', $lockout, $customer['id']);
                    $stmt->execute();
                    $stmt->close();

                    $stmt_chkc = $conn->prepare("SELECT failed_login_attempts FROM clientes WHERE id = ? LIMIT 1");
                    $stmt_chkc->bind_param("i", $customer['id']);
                    $stmt_chkc->execute();
                    $row_chkc = $stmt_chkc->get_result()->fetch_assoc();
                    $stmt_chkc->close();
                    if ((int)($row_chkc['failed_login_attempts'] ?? 1) >= 5) {
                        $clienteBloqueado = true;
                    }
                }

                if ($clienteNaoVerificado) {
                    log_seguranca("Tentativa de login em conta não verificada: {$email}.", 'entrar.php');
                    $erro = 'Confirma o teu email antes de entrares. Verifica a caixa de entrada (e spam) para o link de ativação.';
                } elseif ($adminBloqueado || $clienteBloqueado) {
                    log_seguranca("Bloqueio por tentativas falhadas no login: {$login}.", 'entrar.php');
                    $erro = $mensagemBloqueio;
                } else {
                    log_seguranca("Falha de login para '{$login}'.", 'entrar.php');
                    $erro = 'Utilizador/email ou palavra-passe incorretos.';
                }
            }
        }
    }
}

$pendenteAtual = $_SESSION['login_perfis_pendentes'] ?? null;
if ($pendenteAtual && ($pendenteAtual['expira'] ?? 0) >= time()) {
    $mostrarEscolhaPerfil = $mostrarEscolhaPerfil || isset($_POST['perfil_escolhido']);
}

include __DIR__ . '/templates/header.php';
?>

<main style="background-color: transparent;">
    <div class="login-container">
        <div class="login-card">
            <h2><?php echo $mostrarEscolhaPerfil ? 'Escolher Perfil' : 'Entrar'; ?></h2>
            <p style="text-align: center; color: var(--cor-cinza-medio); margin-bottom: 25px; font-size: 0.95rem; margin-top: -25px;">
                <?php echo $mostrarEscolhaPerfil ? 'Estas credenciais correspondem a mais do que um perfil.' : 'Entra na tua conta.'; ?>
            </p>


            <?php if ($mostrarEscolhaPerfil): ?>
                <form method="post" action="/entrar" class="perfil-choice-form">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
                    
                    <button type="submit" name="perfil_escolhido" value="cliente" class="choice-btn">
                        <div class="choice-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div class="choice-content">
                            <strong>Perfil de Cliente</strong>
                            <span>Aceder às minhas encomendas e dados.</span>
                        </div>
                        <div class="choice-chevron">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </div>
                    </button>

                    <button type="submit" name="perfil_escolhido" value="admin" class="choice-btn">
                        <div class="choice-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                        </div>
                        <div class="choice-content">
                            <strong>Perfil de Admin</strong>
                            <span>Gerir a loja, produtos e encomendas.</span>
                        </div>
                        <div class="choice-chevron">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </div>
                    </button>
                </form>
            <?php else: ?>
                <form method="post" action="/entrar">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-group">
                        <label for="usuario">Utilizador ou Email</label>
                        <?php echo ui_input('usuario', ['id' => 'usuario', 'required' => true, 'autocomplete' => 'username', 'value' => html_entity_decode($loginValor, ENT_QUOTES, 'UTF-8'), 'placeholder' => 'Username ou email']); ?>
                    </div>
                    <div class="form-group">
                        <label for="senha">Palavra-passe</label>
                        <div class="password-wrapper">
                            <?php echo ui_input('senha', ['type' => 'password', 'id' => 'senha', 'required' => true, 'autocomplete' => 'current-password', 'placeholder' => 'A sua palavra-passe']); ?>
                            <button type="button" class="toggle-senha" aria-label="Mostrar ou ocultar palavra-passe" data-target="senha" style="background:none;border:none;padding:0;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                        <div id="caps-lock-warning" style="display:none; align-items:center; gap:7px; margin-top:8px; padding:7px 12px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; font-size:0.78rem; font-weight:600; color:#92400e; letter-spacing:0.01em;">
                            Caps Lock ativo
                        </div>
                    </div>
                    <?php echo ui_button('Entrar', ['type' => 'submit', 'class' => 'button add-btn', 'full' => true]); ?>
                </form>
            <?php endif; ?>

            <div class="form-actions-footer">
                <a href="/recuperar-conta" class="link-recuperar">Esqueci-me da palavra-passe</a>
                <a href="/registar" class="link-recuperar">Criar conta</a>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($erro)): ?>
    mostrarPopup(<?php echo json_encode($erro); ?>, 'erro');
    <?php endif; ?>
    <?php if (!empty($aviso)): ?>
    mostrarPopup(<?php echo json_encode($aviso); ?>, 'info');
    <?php endif; ?>


    const senhaInput = document.getElementById('senha');
    const capsLockWarning = document.getElementById('caps-lock-warning');
    if (senhaInput && capsLockWarning) {
        senhaInput.addEventListener('keyup', function(event) {
            capsLockWarning.style.display = event.getModifierState && event.getModifierState('CapsLock') ? 'flex' : 'none';
        });
        senhaInput.addEventListener('blur', function() {
            capsLockWarning.style.display = 'none';
        });
    }
});
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
