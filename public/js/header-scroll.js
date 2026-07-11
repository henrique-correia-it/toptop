// Header dinâmico — esconde ao fazer scroll para baixo, revela ao scroll para cima.
// Só ativa quando o <header> tem a classe .header-auto-hide (toggle no painel dev).
(function () {
    var header = document.querySelector('.site-header.header-auto-hide');
    if (!header) return;

    var lastY = window.pageYOffset || document.documentElement.scrollTop || 0;
    var ticking = false;
    var trackingTop = false;
    var releaseFrame = 0;
    var THRESHOLD = 6;    // ignora micro-scrolls
    var REVEAL_AT = 80;   // só começa a esconder depois deste ponto

    function currentTranslateY() {
        var transform = window.getComputedStyle(header).transform;
        if (!transform || transform === 'none') return 0;

        if (typeof window.DOMMatrixReadOnly === 'function') {
            return new window.DOMMatrixReadOnly(transform).m42;
        }

        var values = transform.match(/matrix(?:3d)?\(([^)]+)\)/);
        if (!values) return 0;
        var parts = values[1].split(',').map(Number);
        return parts.length === 16 ? parts[13] : parts[5];
    }

    function cancelRelease() {
        if (!releaseFrame) return;
        window.cancelAnimationFrame(releaseFrame);
        releaseFrame = 0;
    }

    function trackTop(y) {
        cancelRelease();
        trackingTop = true;
        header.classList.add('is-tracking-top');
        header.classList.remove('is-hidden');
        header.style.transform = 'translateY(-' + Math.max(0, y) + 'px)';
    }

    function stopTrackingTop(hidden) {
        cancelRelease();

        if (hidden) {
            header.classList.add('is-hidden');
        } else {
            header.classList.remove('is-hidden');
        }

        header.style.transform = '';
        header.classList.remove('is-tracking-top');
        trackingTop = false;
    }

    function finishAtTop() {
        trackTop(0);

        // Mantém o estado sem transição até o browser pintar a posição final.
        // Só depois repõe a animação normal, já sem qualquer deslocação pendente.
        releaseFrame = window.requestAnimationFrame(function () {
            releaseFrame = window.requestAnimationFrame(function () {
                header.style.transform = '';
                header.classList.remove('is-tracking-top');
                trackingTop = false;
                releaseFrame = 0;
            });
        });
    }

    function update() {
        ticking = false;
        var y = window.pageYOffset || document.documentElement.scrollTop || 0;
        var headerHeight = header.offsetHeight;
        var goingUp = y < lastY;

        // Nunca esconder com um painel aberto (menu mobile, pesquisa, carrinho, login)
        if (document.body.classList.contains('app-panel-open')) {
            if (trackingTop) stopTrackingTop(false);
            header.classList.remove('is-hidden');
            lastY = y;
            return;
        }

        if (y <= 1) {
            finishAtTop();
            lastY = 0;
            return;
        }

        // Perto do topo, acompanha o scroll apenas se a animação estiver atrasada.
        // Assim o header encontra o conteúdo sem saltos nem uma faixa vazia.
        if (trackingTop) {
            if (goingUp && y < headerHeight) {
                trackTop(y);
                lastY = y;
                return;
            }

            stopTrackingTop(y > REVEAL_AT);
        }

        if (goingUp && y < headerHeight && currentTranslateY() < (-y - 0.5)) {
            trackTop(y);
            lastY = y;
            return;
        }

        if (Math.abs(y - lastY) < THRESHOLD) return;

        if (y > lastY && y > REVEAL_AT) {
            header.classList.add('is-hidden');     // scroll para baixo
        } else {
            header.classList.remove('is-hidden');  // scroll para cima
        }
        lastY = y;
    }

    window.addEventListener('scroll', function () {
        if (!ticking) {
            window.requestAnimationFrame(update);
            ticking = true;
        }
    }, { passive: true });
})();
