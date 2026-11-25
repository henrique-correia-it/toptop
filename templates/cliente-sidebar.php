<?php
/**
 * Sidebar da Área de Cliente
 * Variáveis esperadas:
 * $active_page (string) - A página atual para destacar no menu
 * $cliente (array) - Dados do cliente logado
 */
?>
<aside class="cliente-sidebar">
    <div class="cliente-profile-info">
        <div class="cliente-avatar">
            <?php echo htmlspecialchars(mb_strtoupper(mb_substr(trim((string)($cliente['nome'] ?? 'U')), 0, 1, 'UTF-8'), 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <div class="cliente-details">
            <strong><?php echo htmlspecialchars((string)($cliente['nome'] ?? 'Utilizador'), ENT_QUOTES, 'UTF-8'); ?></strong>
            <span><?php echo htmlspecialchars((string)($cliente['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>
    <?php $paginaAtivaSidebar = $active_page ?? ''; ?>
    <nav aria-label="Menu da área de cliente">
        <a href="/minha-conta" class="<?php echo ($paginaAtivaSidebar === 'resumo' ? 'active' : ''); ?>"<?php echo ($paginaAtivaSidebar === 'resumo' ? ' aria-current="page"' : ''); ?>>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            Resumo
        </a>
        <a href="/minha-conta/encomendas" class="<?php echo ($paginaAtivaSidebar === 'encomendas' ? 'active' : ''); ?>"<?php echo ($paginaAtivaSidebar === 'encomendas' ? ' aria-current="page"' : ''); ?>>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
            Encomendas
        </a>
        <a href="/minha-conta/moradas" class="<?php echo ($paginaAtivaSidebar === 'moradas' ? 'active' : ''); ?>"<?php echo ($paginaAtivaSidebar === 'moradas' ? ' aria-current="page"' : ''); ?>>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
            Moradas
        </a>
        <a href="/minha-conta/dados" class="<?php echo ($paginaAtivaSidebar === 'dados' ? 'active' : ''); ?>"<?php echo ($paginaAtivaSidebar === 'dados' ? ' aria-current="page"' : ''); ?>>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            Dados pessoais
        </a>
    </nav>
</aside>
