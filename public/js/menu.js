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
    const accountBtn = document.querySelector('.header-account-link[data-mobile-login="true"]');
    const clienteLoginPanel = document.getElementById('cliente-login-panel');
    const clienteLoginOverlay = document.getElementById('cliente-login-overlay');

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
            window.dispatchEvent(new CustomEvent('app:close-account-login'));
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

    if (accountBtn && clienteLoginPanel && clienteLoginOverlay) {
        const isMobile = () => window.innerWidth <= 768;

        const abrirClienteLogin = () => {
            window.dispatchEvent(new CustomEvent('app:close-side-cart'));
            window.dispatchEvent(new CustomEvent('app:close-search'));
            window.dispatchEvent(new CustomEvent('app:close-main-nav'));
            clienteLoginPanel.classList.add('ativo');
            clienteLoginPanel.setAttribute('aria-hidden', 'false');
            clienteLoginOverlay.classList.add('ativo');
            accountBtn.classList.add('ativo');
            accountBtn.setAttribute('aria-expanded', 'true');
            accountBtn.setAttribute('aria-label', 'Fechar login');
            document.body.classList.add('app-panel-open');
            document.body.style.overflow = 'hidden';
            const firstInput = clienteLoginPanel.querySelector('input[name="usuario"]');
            if (firstInput) setTimeout(() => firstInput.focus(), 220);
        };

        const fecharClienteLogin = () => {
            clienteLoginPanel.classList.remove('ativo');
            clienteLoginPanel.setAttribute('aria-hidden', 'true');
            clienteLoginOverlay.classList.remove('ativo');
            accountBtn.classList.remove('ativo');
            accountBtn.setAttribute('aria-expanded', 'false');
            accountBtn.setAttribute('aria-label', 'Abrir login');
            document.body.classList.remove('app-panel-open');
            document.body.style.overflow = '';
        };

        accountBtn.addEventListener('click', (event) => {
            // Agora o botão segue sempre o link nativo (href), tanto em mobile como PC,
            // conforme pedido pelo utilizador. O painel lateral de login foi desativado.
            return; 
        });

        document.querySelectorAll('[data-mobile-login-menu="true"]').forEach((link) => {
            link.addEventListener('click', (event) => {
                // Segue o link nativo
                return;
            });
        });

        window.addEventListener('app:close-account-login', fecharClienteLogin);
        clienteLoginOverlay.addEventListener('click', fecharClienteLogin);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') fecharClienteLogin();
        });
        window.addEventListener('resize', () => {
            if (!isMobile()) fecharClienteLogin();
        });
    }
});
