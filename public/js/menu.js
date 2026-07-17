document.addEventListener('DOMContentLoaded', () => {
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const siteHeader = document.querySelector('.site-header');
    const menuNav = document.getElementById('main-nav-mobile');
    const menuOverlay = document.getElementById('menu-overlay');
    const fecharMenuBtn = document.getElementById('fechar-menu-btn');
    const categoriasDesktopItem = document.querySelector('.nav-item-categorias');
    const categoriasDesktopToggle = document.querySelector('.nav-categorias-toggle');
    const categoriasMobileItem = document.querySelector('.nav-mobile-categorias');
    const categoriasMobileToggle = document.querySelector('.nav-mobile-category-toggle');

    if (categoriasDesktopItem && categoriasDesktopToggle) {
        let fecharCategoriasTimer;

        const abrirCategoriasDesktop = () => {
            clearTimeout(fecharCategoriasTimer);
            categoriasDesktopItem.classList.add('aberto');
            categoriasDesktopToggle.setAttribute('aria-expanded', 'true');
        };

        const fecharCategoriasDesktop = () => {
            categoriasDesktopItem.classList.remove('aberto');
            categoriasDesktopToggle.setAttribute('aria-expanded', 'false');
        };

        categoriasDesktopToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const vaiAbrir = !categoriasDesktopItem.classList.contains('aberto');
            categoriasDesktopItem.classList.toggle('aberto', vaiAbrir);
            categoriasDesktopToggle.setAttribute('aria-expanded', vaiAbrir ? 'true' : 'false');
        });

        categoriasDesktopItem.addEventListener('mouseenter', abrirCategoriasDesktop);
        categoriasDesktopItem.addEventListener('mouseleave', () => {
            fecharCategoriasTimer = setTimeout(fecharCategoriasDesktop, 220);
        });

        document.addEventListener('click', (event) => {
            if (!categoriasDesktopItem.contains(event.target)) fecharCategoriasDesktop();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') fecharCategoriasDesktop();
        });
    }

    if (categoriasMobileItem && categoriasMobileToggle) {
        categoriasMobileToggle.addEventListener('click', () => {
            const vaiAbrir = !categoriasMobileItem.classList.contains('aberto');
            categoriasMobileItem.classList.toggle('aberto', vaiAbrir);
            categoriasMobileToggle.setAttribute('aria-expanded', vaiAbrir ? 'true' : 'false');
        });
    }

    if (hamburgerBtn && menuNav && menuOverlay) {
        const atualizarAlturaHeader = () => {
            if (!siteHeader) return;
            document.documentElement.style.setProperty('--site-header-height', `${siteHeader.offsetHeight}px`);
        };

        const abrirMenu = () => {
            window.dispatchEvent(new CustomEvent('app:close-side-cart'));
            window.dispatchEvent(new CustomEvent('app:close-search'));
            menuNav.classList.add('ativo');
            menuOverlay.classList.add('ativo');
            hamburgerBtn.classList.add('ativo');
            hamburgerBtn.setAttribute('aria-expanded', 'true');
            hamburgerBtn.setAttribute('aria-label', 'Fechar menu');
            document.body.classList.add('app-panel-open');
            document.body.style.overflow = 'hidden';
        };

        const fecharMenu = () => {
            menuNav.classList.remove('ativo');
            menuOverlay.classList.remove('ativo');
            hamburgerBtn.classList.remove('ativo');
            hamburgerBtn.setAttribute('aria-expanded', 'false');
            hamburgerBtn.setAttribute('aria-label', 'Abrir menu');
            if (categoriasMobileItem && categoriasMobileToggle) {
                categoriasMobileItem.classList.remove('aberto');
                categoriasMobileToggle.setAttribute('aria-expanded', 'false');
            }
            document.body.classList.remove('app-panel-open');
            document.body.style.overflow = '';
        };

        const alternarMenu = () => {
            if (menuNav.classList.contains('ativo')) {
                fecharMenu();
            } else {
                abrirMenu();
            }
        };

        window.addEventListener('app:close-main-nav', fecharMenu);
        window.fecharMainNav = fecharMenu;

        atualizarAlturaHeader();
        window.addEventListener('resize', atualizarAlturaHeader);
        window.addEventListener('orientationchange', atualizarAlturaHeader);

        hamburgerBtn.addEventListener('click', alternarMenu);
        if (fecharMenuBtn) fecharMenuBtn.addEventListener('click', fecharMenu);
        menuOverlay.addEventListener('click', fecharMenu);
        menuNav.querySelectorAll('a').forEach((link) => link.addEventListener('click', fecharMenu));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') fecharMenu();
        });
    }

});
