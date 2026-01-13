/**
 * Falling Popcorn Background Animation
 * Creates a snowfall-like effect with blurred popcorn emojis
 */
(function() {
    'use strict';

    // Check for reduced motion preference
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (prefersReducedMotion) {
        // Don't create animated particles for users who prefer reduced motion
        // CSS handles static fallback via ::before and ::after
        return;
    }

    // Configuration
    const CONFIG = {
        particleCount: 20,
        minSize: 16,
        maxSize: 32,
        minOpacity: 0.10,
        maxOpacity: 0.30,
        minBlur: 1,
        maxBlur: 4,
        minFallDuration: 12,
        maxFallDuration: 28,
        minSwayDuration: 3,
        maxSwayDuration: 8,
        emoji: '🍿'
    };

    /**
     * Generate a random number between min and max
     */
    function random(min, max) {
        return Math.random() * (max - min) + min;
    }

    /**
     * Generate a random integer between min and max (inclusive)
     */
    function randomInt(min, max) {
        return Math.floor(random(min, max + 1));
    }

    /**
     * Create the background container
     */
    function createContainer() {
        const container = document.createElement('div');
        container.className = 'popcorn-bg';
        container.setAttribute('aria-hidden', 'true');
        return container;
    }

    /**
     * Create a single popcorn particle
     */
    function createParticle(index, total) {
        const particle = document.createElement('span');
        particle.className = 'popcorn';
        particle.textContent = CONFIG.emoji;

        // Distribute particles evenly across the viewport width
        const leftPosition = (index / total) * 100;
        // Add some randomness to the position
        const leftOffset = random(-3, 3);

        // Randomize properties
        const size = randomInt(CONFIG.minSize, CONFIG.maxSize);
        const opacity = random(CONFIG.minOpacity, CONFIG.maxOpacity);
        const blur = random(CONFIG.minBlur, CONFIG.maxBlur);
        const fallDuration = random(CONFIG.minFallDuration, CONFIG.maxFallDuration);
        const swayDuration = random(CONFIG.minSwayDuration, CONFIG.maxSwayDuration);
        const fallDelay = random(0, CONFIG.maxFallDuration);
        const swayDelay = random(0, CONFIG.maxSwayDuration);

        // Apply styles
        particle.style.cssText = `
            left: ${leftPosition + leftOffset}%;
            font-size: ${size}px;
            opacity: ${opacity};
            filter: blur(${blur}px);
            animation-duration: ${fallDuration}s, ${swayDuration}s;
            animation-delay: -${fallDelay}s, -${swayDelay}s;
        `;

        return particle;
    }

    /**
     * Initialize the popcorn background
     */
    function init() {
        const container = createContainer();

        // Create particles distributed across viewport
        for (let i = 0; i < CONFIG.particleCount; i++) {
            container.appendChild(createParticle(i, CONFIG.particleCount));
        }

        // Insert at the beginning of body
        document.body.insertBefore(container, document.body.firstChild);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
