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
