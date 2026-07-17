<?php
// config/cliente_auth.php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/CustomerService.php';

if (!function_exists('is_cliente_logged_in')) {
    function is_cliente_logged_in(): bool
    {
        return isset($_SESSION['cliente_logado']) && $_SESSION['cliente_logado'] === true && !empty($_SESSION['cliente_id']);
    }
}

if (!function_exists('cliente_id')) {
    function cliente_id(): ?int
    {
        return is_cliente_logged_in() ? (int)$_SESSION['cliente_id'] : null;
    }
}

if (!function_exists('cliente_atual')) {
    function cliente_atual(mysqli $conn): ?array
    {
        $id = cliente_id();
        if (!$id) return null;
        $customer = customer_find_by_id($conn, $id);
        if (!$customer || (int)$customer['ativo'] !== 1) {
            logout_cliente();
            return null;
        }
        return $customer;
    }
}

if (!function_exists('login_cliente_session')) {
    function login_cliente_session(array $customer): void
    {
        session_regenerate_id(true);
        $_SESSION['cliente_logado'] = true;
        $_SESSION['cliente_id'] = (int)$customer['id'];
        $_SESSION['cliente_nome'] = $customer['nome'];
        $_SESSION['cliente_email'] = $customer['email'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

if (!function_exists('logout_cliente')) {
    function logout_cliente(): void
    {
        unset(
            $_SESSION['cliente_logado'],
            $_SESSION['cliente_id'],
            $_SESSION['cliente_nome'],
            $_SESSION['cliente_email']
        );
    }
}

if (!function_exists('require_cliente')) {
    function require_cliente(string $redirect = '/entrar'): void
    {
        if (is_cliente_logged_in()) return;

        // Admin logged in — try to access via linked client account (same email)
        if (!empty($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true) {
            global $conn;
            if (isset($conn) && !empty($_SESSION['admin_email'])) {
                $email = $_SESSION['admin_email'];
                $stmt = $conn->prepare("SELECT id, nome, email FROM clientes WHERE email = ? AND ativo = 1 LIMIT 1");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $customer = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($customer) {
                    // Set client session without regenerating ID (preserves admin session)
                    $_SESSION['cliente_logado'] = true;
                    $_SESSION['cliente_id']     = (int)$customer['id'];
                    $_SESSION['cliente_nome']   = $customer['nome'];
                    $_SESSION['cliente_email']  = $customer['email'];
                    return;
                }
            }
            // Admin has no linked client account — avoid /entrar loop
            header('Location: /admin');
            exit;
        }

        $next = $_SERVER['REQUEST_URI'] ?? '/minha-conta';
        header('Location: ' . $redirect . '?next=' . urlencode($next));
        exit;
    }
}
