<?php
require_once __DIR__ . '/../config/auth.php';
include '../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/http.php';

if (!admin_has_role(['superadmin', 'desenvolvedor'])) {
    json_error('Acesso não autorizado.', 403);
}

if (!csrf_from_post()) {
    json_error('Erro de validação CSRF.', 403);
}

$portes = json_decode($_POST['portes_por_pais'] ?? '[]', true);
$limite_raw = str_replace(',', '.', trim((string)($_POST['portes_gratis_minimo'] ?? '')));
$portes_gratis_ativo_raw = (string)($_POST['portes_gratis_ativo'] ?? '');
$portes_gratis_ativo = $portes_gratis_ativo_raw === '1';

if (!is_array($portes)) {
    json_error('Configuração de portes inválida.', 400);
}

if (!is_numeric($limite_raw) || (float)$limite_raw <= 0) {
    json_error('O valor mínimo para portes grátis deve ser superior a zero.', 400);
}

$limite_portes_gratis = round((float)$limite_raw, 2);

if (!in_array($portes_gratis_ativo_raw, ['0', '1'], true)) {
    json_error('O estado dos portes grátis é inválido.', 400);
}

$conn->begin_transaction();
try {
    // 1. Limpar tabela para reinserir (abordagem mais simples para sincronizar o objeto JS com SQL)
    $conn->query("DELETE FROM shipping_rates");

    // 2. Inserir novos dados
    $stmt = $conn->prepare("INSERT INTO shipping_rates (country_code, min_weight, max_weight, price) VALUES (?, ?, ?, ?)");
    
    foreach ($portes as $pais => $regras) {
        foreach ($regras as $regra) {
            $pais_limpo = strtoupper(substr($pais, 0, 5));
            $min   = (float)$regra['min'];
            $max   = (float)$regra['max'];
            $preco = (float)$regra['preco'];
            
            $stmt->bind_param("sddd", $pais_limpo, $min, $max, $preco);
            $stmt->execute();
        }
    }
    $stmt->close();

    if (!setLojaConfig('portes_gratis_minimo_pt_continental', number_format($limite_portes_gratis, 2, '.', ''))) {
        throw new RuntimeException('Não foi possível guardar o valor dos portes grátis.');
    }

    if (!setLojaConfig('portes_gratis_ativo', $portes_gratis_ativo ? '1' : '0')) {
        throw new RuntimeException('Não foi possível guardar o estado dos portes grátis.');
    }

    $conn->commit();
    json_success(['mensagem' => 'Configurações de portes guardadas com sucesso!']);

} catch (Exception $e) {
    $conn->rollback();
    log_app($e->getMessage(), 'ERROR', 'ajax_salvar_portes.php');
    json_error('Nao foi possivel guardar a configuracao de portes.', 500);
}
