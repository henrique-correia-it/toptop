<?php
// config/auth.php

require_once __DIR__ . '/session.php';

if (!function_exists('is_admin_logged_in')) {
    function is_admin_logged_in(): bool
    {
        return isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true;
    }
}

if (!function_exists('admin_role')) {
    function admin_role(): ?string
    {
        return $_SESSION['admin_role'] ?? null;
    }
}

if (!function_exists('admin_has_role')) {
    function admin_has_role(array $roles): bool
    {
        return is_admin_logged_in() && in_array(admin_role(), $roles, true);
    }
}

if (!function_exists('require_admin')) {
    function require_admin(string $redirect = '/entrar'): void
    {
        if (is_admin_logged_in()) {
            return;
        }

        header('Location: ' . $redirect);
        exit;
    }
}

if (!function_exists('require_admin_roles')) {
    function require_admin_roles(array $roles, string $redirect = '/admin'): void
    {
        if (admin_has_role($roles)) {
            return;
        }

        header('Location: ' . $redirect);
        exit;
    }
}
