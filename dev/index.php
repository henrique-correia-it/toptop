<?php
/**
 * dev/index.php
 * Proteção de diretório: Redireciona o acesso direto à pasta para o Painel Dev.
 * A lógica de permissões é tratada dentro de dev.php.
 */
header("Location: dev.php");
exit;
