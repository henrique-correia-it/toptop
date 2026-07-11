<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/formatters.php';

// 1. VERIFICA A SESSÃO PRIMEIRO
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: /entrar");
    exit;
}

// 2. SÓ DEPOIS DE VALIDAR, INCLUI O CABEÇALHO
include '../templates/header.php';

// Função para calcular o tamanho de um diretório
function getDirectorySize($path) {
    $total_size = 0;
    try {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile()) {
                $total_size += $file->getSize();
            }
        }
    } catch (Exception $e) {
        // Ignora erros de permissão, etc.
    }
    return $total_size;
}

// Caminhos
$base_dir = __DIR__ . '/..';
$images_dir = $base_dir . '/public/images';
$assets_dir = $base_dir . '/public/assets';
$code_dirs = [
    $base_dir . '/admin', $base_dir . '/config', $base_dir . '/phpmailer',
    $base_dir . '/public/css', $base_dir . '/public/js', $base_dir . '/templates'
];
$root_files = glob($base_dir . '/*.php');

// Cálculos
$size_images = file_exists($images_dir) ? getDirectorySize($images_dir) : 0;
$size_assets = file_exists($assets_dir) ? getDirectorySize($assets_dir) : 0;
$size_code = 0;
foreach ($code_dirs as $dir) { if (file_exists($dir)) $size_code += getDirectorySize($dir); }
foreach ($root_files as $file) { $size_code += filesize($file); }
$total_size_bytes = $size_images + $size_assets + $size_code;
$limite_gb = 5; // Limite de 5 GB
$limite_bytes = $limite_gb * 1024 * 1024 * 1024;
$percentagem_total = ($limite_bytes > 0) ? ($total_size_bytes / $limite_bytes) * 100 : 0;

$bar_class = 'success';
if ($percentagem_total > 85) $bar_class = 'danger';
elseif ($percentagem_total > 60) $bar_class = 'warning';

?>


<main class="dashboard-container animate-entry">
    
<!-- Bloquear scroll automático no refresh -->
<script>
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    window.scrollTo(0, 0);
</script>
    <div class="admin-page-header">
        <div class="header-title-container">
            <?php renderBackButton('/dev', 'Painel Dev'); ?>
            <h2>Uso de Armazenamento</h2>
        </div>
    </div>

    <div class="storage-container">

        <div class="storage-total-card">
            <p class="total-label">Espaço Total Utilizado</p>
            <div class="total-value"><?php echo format_bytes($total_size_bytes); ?></div>
            <div class="progress-bar-container">
                <div class="progress-bar <?php echo $bar_class; ?>" style="width: <?php echo min(100, $percentagem_total); ?>%;" title="<?php echo number_format($percentagem_total, 2); ?>% Usado"></div>
            </div>
            <p class="progress-info">Ocupa <strong><?php echo number_format($percentagem_total, 2); ?>%</strong> do seu limite de <?php echo $limite_gb; ?> GB.</p>
        </div>

        <h3 class="dashboard-section-title" style="margin-top: 40px; text-align: left;">Detalhes por Categoria</h3>
        <div class="storage-breakdown">
            <div class="breakdown-item">
                <div class="breakdown-icon images"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></div>
                <div class="breakdown-info">
                    <div class="breakdown-info-header">
                        <span class="label">Imagens de Produtos</span>
                        <span class="size"><?php echo format_bytes($size_images); ?></span>
                    </div>
                    <div class="breakdown-bar-container">
                        <div class="breakdown-bar images" style="width: <?php echo $total_size_bytes > 0 ? ($size_images / $total_size_bytes * 100) : 0; ?>%;"></div>
                    </div>
                </div>
            </div>
            <div class="breakdown-item">
                <div class="breakdown-icon code"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg></div>
                <div class="breakdown-info">
                    <div class="breakdown-info-header">
                        <span class="label">Código e Scripts</span>
                        <span class="size"><?php echo format_bytes($size_code); ?></span>
                    </div>
                    <div class="breakdown-bar-container">
                        <div class="breakdown-bar code" style="width: <?php echo $total_size_bytes > 0 ? ($size_code / $total_size_bytes * 100) : 0; ?>%;"></div>
                    </div>
                </div>
            </div>
            <div class="breakdown-item">
                <div class="breakdown-icon assets"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg></div>
                <div class="breakdown-info">
                    <div class="breakdown-info-header">
                        <span class="label">Outros Ativos</span>
                        <span class="size"><?php echo format_bytes($size_assets); ?></span>
                    </div>
                    <div class="breakdown-bar-container">
                        <div class="breakdown-bar assets" style="width: <?php echo $total_size_bytes > 0 ? ($size_assets / $total_size_bytes * 100) : 0; ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<?php include '../templates/footer.php'; ?>
