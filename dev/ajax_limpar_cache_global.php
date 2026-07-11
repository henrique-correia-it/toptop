<?php
// admin/ajax_limpar_cache_global.php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/http.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../config/csrf.php';

// Segurança: Só desenvolvedores podem limpar o cache global
if (!admin_has_role(['desenvolvedor'])) {
    json_error('Acesso negado.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_from_post()) {
    json_error('Erro de validacao CSRF.', 403);
}

try {
    // Gera um novo timestamp (a hora atual em segundos)
    $nova_versao = time();
    
    // Cria o conteúdo do ficheiro PHP
    $conteudo = "<?php\nreturn '" . $nova_versao . "';\n?>";
    
    // Guarda na pasta config
    $caminho = __DIR__ . '/../config/versao_site.php';
    
    if (file_put_contents($caminho, $conteudo)) {
        json_success(['nova_versao' => $nova_versao]);
    } else {
        throw new Exception("Não foi possível escrever no ficheiro de versão.");
    }
} catch (Exception $e) {
    log_app($e->getMessage(), 'ERROR', 'ajax_limpar_cache_global.php');
    json_error('Nao foi possivel limpar o cache.', 500);
}
?>
