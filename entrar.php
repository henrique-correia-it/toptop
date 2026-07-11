<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/cliente_auth.php';

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

if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true) {
    header('Location: /admin');
    exit;
}
if (is_cliente_logged_in()) {
    header('Location: /minha-conta');
    exit;
}

$titulo_pagina = 'Entrar';
$erro = '';
$aviso = '';
$loginValor = '';
$unlock_timestamp = 0;
$mostrarEscolhaPerfil = false;
$next = $_GET['next'] ?? ($_POST['next'] ?? '/minha-conta');
if (!is_string($next) || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
    $next = '/minha-conta';
}

// --- DEVELOPER PREVIEW MODE ---
$is_dev_preview = false;
if (isset($_GET['dev_preview'])) {
    if (isset($_SESSION['admin_logado']) && $_SESSION['admin_role'] === 'desenvolvedor') {
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
        } else {
            $admin = procurar_admin_login($conn, $login);
            $email = customer_clean_email($login);
            $customer = filter_var($email, FILTER_VALIDATE_EMAIL) ? customer_find_by_email($conn, $email) : null;

            $adminBloqueado = $admin ? admin_esta_bloqueado($admin, $lockout) : false;
            $clienteBloqueado = $customer ? cliente_esta_bloqueado($customer, $lockout) : false;
            $adminValido = $admin && !$adminBloqueado && password_verify($password, $admin['password_hash']);
            $clienteNaoVerificado = $customer && !$clienteBloqueado && (int)$customer['ativo'] === 0 && password_verify($password, $customer['password_hash']);
            $clienteValido = $customer && !$clienteBloqueado && (int)$customer['ativo'] === 1 && password_verify($password, $customer['password_hash']);

            if ($adminValido && $clienteValido) {
                $_SESSION['login_perfis_pendentes'] = [
                    'admin_id' => (int)$admin['id'],
                    'cliente_id' => (int)$customer['id'],
                    'next' => $next,
                    'expira' => time() + 300,
                ];
                $mostrarEscolhaPerfil = true;
            } elseif ($adminValido) {
                finalizar_login_admin($conn, $admin);
                session_write_close();
                header('Location: /admin');
                exit;
            } elseif ($clienteValido) {
                finalizar_login_cliente($conn, $customer);
                header('Location: ' . $next);
                exit;
            } else {
                if ($adminBloqueado && !$customer) {
                    $_rem = max(0, strtotime($admin['last_login_attempt']) + $lockout - time());
                    $erro = 'Conta temporariamente bloqueada. Tente novamente em ' . floor($_rem / 60) . ':' . str_pad($_rem % 60, 2, '0', STR_PAD_LEFT) . '.';
                } elseif ($clienteBloqueado && !$admin) {
                    $erro = 'Conta temporariamente bloqueada. Tente novamente dentro de alguns minutos.';
                } elseif ($clienteNaoVerificado) {
                    $erro = 'Confirma o teu email antes de entrares. Verifica a caixa de entrada (e spam) para o link de ativação.';
                } else {
                    $erro = 'Utilizador/email ou palavra-passe incorretos.';
                }

                if ($admin && !$adminBloqueado && !$clienteValido) {
                    $novas_tentativas = (int)$admin['failed_login_attempts'] + 1;
                    $agora = date('Y-m-d H:i:s');
                    $stmt = $conn->prepare("UPDATE administradores SET failed_login_attempts = ?, last_login_attempt = ? WHERE id = ?");
                    $stmt->bind_param("isi", $novas_tentativas, $agora, $admin['id']);
                    $stmt->execute();
                    $stmt->close();

                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $motivo = $novas_tentativas >= 5 ? 'Conta bloqueada' : 'Senha incorreta';
                    $stmt = $conn->prepare("INSERT INTO admin_login_logs (admin_id, username, role, ip, user_agent, resultado, motivo_falha) VALUES (?, ?, ?, ?, ?, 'falha', ?)");
                    $stmt->bind_param("isssss", $admin['id'], $admin['username'], $admin['role'], $ip, $ua, $motivo);
                    $stmt->execute();
                    $stmt->close();

                    $tentativas_restantes = 5 - $novas_tentativas;
                    if ($tentativas_restantes > 0) {
                        $aviso = "Atenção: Restam-lhe $tentativas_restantes tentativas.";
                    } else {
                        $erro = 'Conta temporariamente bloqueada. Tente novamente em ' . floor($lockout / 60) . ' minutos.';
                    }
                }

                if ($customer && !$clienteBloqueado && !$adminValido) {
                    $attempts = (int)$customer['failed_login_attempts'] + 1;
                    $now = date('Y-m-d H:i:s');
                    $stmt = $conn->prepare("UPDATE clientes SET failed_login_attempts = ?, last_login_attempt = ? WHERE id = ?");
                    $stmt->bind_param('isi', $attempts, $now, $customer['id']);
                    $stmt->execute();
                    $stmt->close();
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
                        <input type="text" id="usuario" name="usuario" required autocomplete="username" value="<?php echo $loginValor; ?>" placeholder="Username ou email">
                    </div>
                    <div class="form-group">
                        <label for="senha">Palavra-passe</label>
                        <div class="password-wrapper">
                            <input type="password" id="senha" name="senha" required autocomplete="current-password" placeholder="A sua palavra-passe">
                            <span class="toggle-senha" data-target="senha">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </span>
                        </div>
                        <div id="caps-lock-warning" style="display:none; align-items:center; gap:7px; margin-top:8px; padding:7px 12px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; font-size:0.78rem; font-weight:600; color:#92400e; letter-spacing:0.01em;">
                            Caps Lock ativo
                        </div>
                    </div>
                    <input type="submit" value="Entrar">
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
