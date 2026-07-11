document.addEventListener('DOMContentLoaded', () => {
    const gameGrid = document.getElementById('memory-game');
    const statsDisplay = document.getElementById('game-stats');
    const resetBtn = document.getElementById('reset-game');

    const emojis = ['👗', '👕', '👖', '👠', '🎒', '👒', '👟', '🧥'];
    let cards = [...emojis, ...emojis];
    let flippedCards = [];
    let matchedPairs = 0;
    let attempts = 0;
    let isLocked = false;

    function shuffle(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
        return array;
    }

    function createCard(emoji, index) {
        const card = document.createElement('div');
        card.classList.add('memory-card');
        card.dataset.emoji = emoji;
        card.dataset.index = index;

        card.innerHTML = `
            <div class="card-inner">
                <div class="card-front"></div>
                <div class="card-back">${emoji}</div>
            </div>
        `;

        card.addEventListener('click', flipCard);
        return card;
    }

    function flipCard() {
        if (isLocked || this.classList.contains('flipped') || this.classList.contains('matched')) return;

        this.classList.add('flipped');
        flippedCards.push(this);

        if (flippedCards.length === 2) {
            attempts++;
            statsDisplay.textContent = `Tentativas: ${attempts}`;
            checkMatch();
        }
    }

    const winModal = document.getElementById('win-modal');
    const finalAttemptsSpan = document.getElementById('final-attempts');
    const closeWinModalBtn = document.getElementById('close-win-modal');

    function checkMatch() {
        isLocked = true;
        const [card1, card2] = flippedCards;

        if (card1.dataset.emoji === card2.dataset.emoji) {
            card1.classList.add('matched');
            card2.classList.add('matched');
            matchedPairs++;
            flippedCards = [];
            isLocked = false;

            if (matchedPairs === emojis.length) {
                setTimeout(showWinModal, 500);
            }
        } else {
            setTimeout(() => {
                card1.classList.remove('flipped');
                card2.classList.remove('flipped');
                flippedCards = [];
                isLocked = false;
            }, 1000);
        }
    }

    function showWinModal() {
        finalAttemptsSpan.textContent = attempts;
        winModal.classList.add('active');
        
        // Disparar Confetis!
        const duration = 3 * 1000;
        const animationEnd = Date.now() + duration;
        const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 10000 };

        function randomInRange(min, max) {
            return Math.random() * (max - min) + min;
        }

        const interval = setInterval(function() {
            const timeLeft = animationEnd - Date.now();

            if (timeLeft <= 0) {
                return clearInterval(interval);
            }

            const particleCount = 50 * (timeLeft / duration);
            confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } }));
            confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } }));
        }, 250);
    }

    closeWinModalBtn.addEventListener('click', () => {
        winModal.classList.remove('active');
        initGame();
    });

    function initGame() {
        gameGrid.innerHTML = '';
        const shuffledCards = shuffle([...cards]);
        shuffledCards.forEach((emoji, index) => {
            gameGrid.appendChild(createCard(emoji, index));
        });
        matchedPairs = 0;
        attempts = 0;
        flippedCards = [];
        statsDisplay.textContent = `Tentativas: ${attempts}`;
        isLocked = false;
    }

    resetBtn.addEventListener('click', initGame);

    initGame();
});
