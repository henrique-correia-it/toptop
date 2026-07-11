<?php
/**
 * Componente: Botão de Voltar com Seta
 * Centraliza o SVG e o estilo moderno de navegação no painel de admin.
 */
function renderBackButton($url = '/admin', $title = 'Voltar ao Painel') {
    ?>
    <a href="<?php echo htmlspecialchars($url); ?>" class="btn-back-arrow" title="<?php echo htmlspecialchars($title); ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
    </a>
    <?php
}
?>
