// public/js/backToTop.js
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('voltarAoTopoBtn');
    if (!btn) return;

    // Throttle para não correr a cada pixel de scroll
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                if (window.scrollY > 300) {
                    btn.classList.add('ativo');
                } else {
                    btn.classList.remove('ativo');
                }
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    // Scroll suave ao clicar
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
