<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

require_admin_manager('/admin/admin.php');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../config/database.php';

$email_scope   = 'loja';
$page_title    = 'Templates de Email';
$back_url      = '/admin';
$back_title    = 'Voltar ao Painel';
$can_edit_meta = false;
$save_url      = '/admin/ajax_salvar_template.php';

require __DIR__ . '/../includes/gestor_emails_view.php';
