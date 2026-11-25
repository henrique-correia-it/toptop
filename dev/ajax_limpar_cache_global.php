<?php
// dev/ajax_limpar_cache_global.php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/http.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../config/csrf.php';

// Segurança: Só desenvolvedores podem limpar o cache global
if (!admin_has_role(['desenvolvedor'])) {
    json_error('Acesso negado.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_error('Método não permitido.', 405);
}
if (!csrf_from_post()) {
    json_error('Erro de validacao CSRF.', 403);
}

try {
    $nova_versao = time();
    $conteudo = "<?php\nreturn '" . $nova_versao . "';\n";
    $caminho = __DIR__ . '/../config/versao_site.php';
    $tmp = $caminho . '.tmp.' . bin2hex(random_bytes(4));

    if (file_put_contents($tmp, $conteudo, LOCK_EX) === false) {
        throw new Exception("Não foi possível escrever no ficheiro temporário de versão.");
    }
    if (!rename($tmp, $caminho)) {
        @unlink($tmp);
        throw new Exception("Não foi possível atualizar atomicamente o ficheiro de versão.");
    }
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($caminho, true);
    }

    json_success(['nova_versao' => $nova_versao]);
} catch (\Throwable $e) {
    log_app($e->getMessage(), 'ERROR', 'ajax_limpar_cache_global.php');
    json_error('Não foi possível limpar o cache.', 500);
}
