<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

require_developer('/admin/admin.php');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../config/database.php';

$email_scope   = 'sistema';
$page_title    = 'Emails de Sistema';
$back_url      = '/dev';
$back_title    = 'Voltar ao Painel Dev';
$can_edit_meta = true;
$save_url      = '/dev/ajax_salvar_template_sistema.php';

require __DIR__ . '/../includes/gestor_emails_view.php';
