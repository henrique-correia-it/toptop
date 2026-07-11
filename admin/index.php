<?php
// htdocs/admin/index.php

// Redireciona imediatamente para o painel principal.
// Nota: O próprio admin.php já tem a proteção que verifica se estás logado.
// Se não estiveres, ele manda-te para o login automaticamente.
header("Location: admin.php");
exit;
?>
