// public/js/backToTop.js
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('voltarAoTopoBtn');
    if (!btn) return;

    const atualizarVisibilidade = () => {
        btn.classList.toggle('ativo', window.scrollY > 300);
    };

    atualizarVisibilidade();

    // Throttle para não correr a cada pixel de scroll
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                atualizarVisibilidade();
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    // Scroll suave ao clicar (respeita prefers-reduced-motion)
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
    });
});
