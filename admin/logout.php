<?php
require_once __DIR__ . '/../config/session.php';

// Limpa todas as variáveis da sessão
session_unset();

// Destrói a sessão completamente
session_destroy();

// Redireciona para a página de login para uma saída limpa
header("Location: /entrar");
exit;
?>
