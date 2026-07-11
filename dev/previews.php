<?php
require_once __DIR__ . '/../config/session.php';

// Segurança: Apenas DEVs
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || $_SESSION['admin_role'] !== 'desenvolvedor') {
    header("Location: /admin/admin.php");
    exit;
}

$titulo_pagina = 'Developer Previews';
include '../templates/header.php';
?>

<!-- Bloquear scroll automático no refresh -->
<script>
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    window.scrollTo(0, 0);
</script>

<!-- Carregar CSS Próprio -->
<link rel="stylesheet" href="/public/css/pages/_previews.css?v=<?php echo time(); ?>">

<main class="dashboard-container animate-entry">
    <div class="admin-page-header">
        <div class="header-title-container">
            <?php renderBackButton('/dev', 'Voltar ao Painel Dev'); ?>
            <h2>Developer Previews</h2>
        </div>
    </div>
    <div class="dashboard-header" style="margin-top: -10px; margin-bottom: 30px;">
        <div class="header-welcome">
            <p>Lista completa de templates e estados do sistema para testes visuais e depuração.</p>
        </div>
    </div>

    <div class="previews-grid">
        
        <!-- SECÇÃO: Checkout & Sucesso -->
        <div class="previews-card theme-green">
            <div class="previews-card-header">
                <div class="previews-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h3>Fluxo de Encomenda</h3>
            </div>
            <div class="previews-links">
                <a href="../sucesso.php?dev_preview=sucesso" target="_blank" class="preview-link">
                    <span>Pág. Sucesso (Pago)</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
                <a href="../sucesso.php?dev_preview=falhou" target="_blank" class="preview-link">
                    <span>Pág. Erro (Pagamento)</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>
        </div>

        <!-- SECÇÃO: Tracking -->
        <div class="previews-card theme-blue">
            <div class="previews-card-header">
                <div class="previews-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </div>
                <h3>Estados de Rastreio</h3>
            </div>
            <div class="previews-links">
                <a href="../estado_encomenda.php?dev_preview=pendente" target="_blank" class="preview-link">
                    <span>Track: Pendente</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
                <a href="../estado_encomenda.php?dev_preview=enviada" target="_blank" class="preview-link">
                    <span>Track: Enviada</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
                <a href="../estado_encomenda.php?dev_preview=concluida" target="_blank" class="preview-link">
                    <span>Track: Concluída</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
                <a href="../estado_encomenda.php?dev_preview=cancelada" target="_blank" class="preview-link">
                    <span>Track: Cancelada</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>
        </div>

        <!-- SECÇÃO: Autenticação -->
        <div class="previews-card theme-amber">
            <div class="previews-card-header">
                <div class="previews-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M17 11l2 2 4-4"/></svg>
                </div>
                <h3>Autenticação Admin</h3>
            </div>
            <div class="previews-links">
                <a href="/entrar?dev_preview=normal" target="_blank" class="preview-link">
                    <span>Login: Normal</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
                <a href="/entrar?dev_preview=bloqueada" target="_blank" class="preview-link">
                    <span>Login: Bloqueada (Brute Force)</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>
        </div>

        <!-- SECÇÃO: Erros & Segurança -->
        <div class="previews-card theme-red">
            <div class="previews-card-header">
                <div class="previews-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <h3>Páginas de Erro</h3>
            </div>
            <div class="previews-links">
                <a href="../404.php" target="_blank" class="preview-link">
                    <span>Página 404 (Not Found)</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>

            </div>
        </div>

    </div>
</main>

<?php include '../templates/footer.php'; ?>
