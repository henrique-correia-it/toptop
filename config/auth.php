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

if (!function_exists('is_developer')) {
    function is_developer(): bool
    {
        return admin_has_role(['desenvolvedor']);
    }
}

if (!function_exists('is_admin_manager')) {
    function is_admin_manager(): bool
    {
        return admin_has_role(['superadmin', 'desenvolvedor']);
    }
}

if (!function_exists('require_admin_login')) {
    function require_admin_login(string $redirect = '/entrar'): void
    {
        if (!is_admin_logged_in()) {
            header('Location: ' . $redirect);
            exit;
        }
    }
}

if (!function_exists('require_admin_manager')) {
    function require_admin_manager(string $redirect = '/admin'): void
    {
        if (!is_admin_manager()) {
            header('Location: ' . $redirect);
            exit;
        }
    }
}

if (!function_exists('require_developer')) {
    function require_developer(string $redirect = '/admin'): void
    {
        if (!is_developer()) {
            header('Location: ' . $redirect);
            exit;
        }
    }
}

if (!function_exists('is_global_edit_mode')) {
    function is_global_edit_mode(): bool
    {
        return is_admin_manager()
            && isset($_SESSION['global_edit_mode'])
            && $_SESSION['global_edit_mode'] === true;
    }
}
