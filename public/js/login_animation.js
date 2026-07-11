// public/js/login_animation.js

document.addEventListener('DOMContentLoaded', () => {
    const bearContainer = document.querySelector('.login-bear-container');
    if (!bearContainer) return; // Só executa se o urso estiver na página

    const inputUser = document.getElementById('usuario');
    const inputPass = document.getElementById('senha');
    const submitButton = document.querySelector('.login-card input[type="submit"]');
    const pupils = document.querySelectorAll('.bear-pupil');
    const hasError = document.querySelector('.auth-message.error');

    let isTyping = false;
    let isMouseFollowing = true;
    let mouseX = 0, mouseY = 0;

    // Estado inicial de erro
    if (hasError) {
        bearContainer.classList.add('login-error');
    }

    // --- Otimização da Animação ---
    // A função que move os olhos só é executada quando o browser está pronto para renderizar um novo frame.
    // Isto evita cálculos desnecessários e torna a animação mais suave.
    function animatePupils() {
        if (!isMouseFollowing) {
            requestAnimationFrame(animatePupils);
            return; // Se não for para seguir o rato, sai da função
        }

        pupils.forEach(pupil => {
            const rect = pupil.getBoundingClientRect();
            const eyeX = rect.left + rect.width / 2;
            const eyeY = rect.top + rect.height / 2;
            
            const deltaX = mouseX - eyeX;
            const deltaY = mouseY - eyeY;
            
            const angle = Math.atan2(deltaY, deltaX);
            
            // Distância máxima do movimento da pupila (raio)
            const radius = 6; 
            
            const moveX = Math.cos(angle) * radius;
            const moveY = Math.sin(angle) * radius;
            
            pupil.style.transform = `translate(${moveX}px, ${moveY}px)`;
        });

        // Continua o loop de animação
        requestAnimationFrame(animatePupils);
    }
    
    // Inicia o loop de animação
    requestAnimationFrame(animatePupils);

    // --- Lógica dos Eventos ---

    // 1. Seguir o Rato
    const onMouseMove = (event) => {
        mouseX = event.clientX;
        mouseY = event.clientY;
    };
    document.addEventListener('mousemove', onMouseMove);

    // 2. Reações aos inputs
    const onFocusInput = (e) => {
        const isPassword = e.target === inputPass;
        bearContainer.classList.remove('login-error', 'hover-button');
        bearContainer.classList.toggle('foco-username', !isPassword);
        bearContainer.classList.toggle('foco-password', isPassword);
    };

    const onBlurInput = () => {
        bearContainer.classList.remove('foco-username', 'foco-password', 'typing');
        isMouseFollowing = true; // Volta a seguir o rato quando sai do input
    };

    // NOVO: Reação ao digitar
    const onTyping = () => {
        isMouseFollowing = false; // Para de seguir o rato
        bearContainer.classList.remove('foco-username', 'foco-password');
        bearContainer.classList.add('typing');
    };

    inputUser.addEventListener('focus', onFocusInput);
    inputPass.addEventListener('focus', onFocusInput);
    inputUser.addEventListener('blur', onBlurInput);
    inputPass.addEventListener('blur', onBlurInput);
    inputUser.addEventListener('input', onTyping);
    inputPass.addEventListener('input', onTyping);


    // 3. Reação ao botão
    const onHoverButton = () => {
        if (!bearContainer.classList.contains('foco-username') && !bearContainer.classList.contains('foco-password')) {
            bearContainer.classList.add('hover-button');
        }
    };

    const onMouseOutButton = () => {
        bearContainer.classList.remove('hover-button');
    };

    submitButton.addEventListener('mouseover', onHoverButton);
    submitButton.addEventListener('mouseout', onMouseOutButton);
});