/**
 * Popcorn Bucket Easter Egg
 * Click the bucket (sitting on footer) to spawn popcorn kernels that arc down.
 * Each click spawns 1-4 random kernels. Click 30+ times for an explosion!
 */
(function() {
    'use strict';

    // =========================================================================
    // Configuration
    // =========================================================================
    const CONFIG = {
        // Kernel spawn settings
        kernelsPerClickMin: 1,
        kernelsPerClickMax: 4,
        explosionKernelCount: 30,
        explosionClickThreshold: 30,

        // Physics
        gravity: 0.4,
        initialVelocityY: -12,      // Upward (negative)
        initialVelocityYVariance: 4,
        initialVelocityX: 0,
        initialVelocityXVariance: 6,
        explosionVelocityXVariance: 12,

        // Timing
        kernelLifetimeMs: 10000,     // 10 seconds on footer
        fadeOutDurationMs: 500,
        animationInterval: 16,       // ~60fps

        // Limits
        maxActiveKernels: 100,

        // Sizing
        kernelSize: 12,
        kernelSizeVariance: 4
    };

    // =========================================================================
    // State
    // =========================================================================
    let clickCount = 0;
    let activeKernels = [];
    let kernelContainer = null;
    let bucketButton = null;
    let footerElement = null;

    // =========================================================================
    // Initialization
    // =========================================================================
    function init() {
        // Wait for DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setup);
        } else {
            setup();
        }
    }

    function setup() {
        // Find footer and bucket button (bucket is now in footer template)
        footerElement = document.querySelector('footer.footer');
        bucketButton = document.getElementById('popcorn-bucket-btn');

        if (!footerElement || !bucketButton) {
            // No footer or bucket on this page (e.g., login)
            return;
        }

        createKernelContainer();
        attachBucketListeners();
    }

    function createKernelContainer() {
        kernelContainer = document.createElement('div');
        kernelContainer.id = 'popcorn-kernel-container';
        kernelContainer.setAttribute('aria-hidden', 'true');
        document.body.appendChild(kernelContainer);
    }

    function attachBucketListeners() {
        bucketButton.addEventListener('click', handleBucketClick);
        bucketButton.addEventListener('touchend', function(e) {
            e.preventDefault();
            handleBucketClick(e);
        });
    }

    // =========================================================================
    // Helpers
    // =========================================================================
    function getRandomKernelCount() {
        return Math.floor(Math.random() * (CONFIG.kernelsPerClickMax - CONFIG.kernelsPerClickMin + 1)) + CONFIG.kernelsPerClickMin;
    }

    // =========================================================================
    // Click Handler
    // =========================================================================
    function handleBucketClick(e) {
        clickCount++;

        // Check for explosion
        if (clickCount >= CONFIG.explosionClickThreshold) {
            triggerExplosion();
            clickCount = 0;
        } else {
            const count = getRandomKernelCount();
            spawnKernels(count, false);
        }

        // Add click animation to bucket
        bucketButton.classList.add('popcorn-bucket-clicked');
        setTimeout(() => {
            bucketButton.classList.remove('popcorn-bucket-clicked');
        }, 150);
    }

    function triggerExplosion() {
        spawnKernels(CONFIG.explosionKernelCount, true);

        // Add explosion animation to bucket
        bucketButton.classList.add('popcorn-bucket-explosion');
        setTimeout(() => {
            bucketButton.classList.remove('popcorn-bucket-explosion');
        }, 500);
    }

    // =========================================================================
    // Kernel Spawning
    // =========================================================================
    function spawnKernels(count, isExplosion) {
        const bucketRect = bucketButton.getBoundingClientRect();
        const spawnX = bucketRect.left + bucketRect.width / 2;
        const spawnY = bucketRect.top + 10; // Near top of bucket

        for (let i = 0; i < count; i++) {
            // Enforce max kernel limit
            if (activeKernels.length >= CONFIG.maxActiveKernels) {
                removeKernel(activeKernels[0]);
            }

            createKernel(spawnX, spawnY, isExplosion);
        }
    }

    function createKernel(startX, startY, isExplosion) {
        const kernel = document.createElement('div');
        kernel.className = 'popcorn-kernel';

        // Random size variation
        const size = CONFIG.kernelSize + (Math.random() - 0.5) * CONFIG.kernelSizeVariance * 2;
        kernel.style.setProperty('--kernel-size', size + 'px');

        // Random rotation
        const rotation = Math.random() * 360;
        kernel.style.setProperty('--kernel-rotation', rotation + 'deg');

        // Random scale variation
        const scale = 0.8 + Math.random() * 0.4;
        kernel.style.setProperty('--kernel-scale', scale);

        // Initial position
        kernel.style.left = startX + 'px';
        kernel.style.top = startY + 'px';

        kernelContainer.appendChild(kernel);

        // Physics state
        const velocityXVariance = isExplosion ? CONFIG.explosionVelocityXVariance : CONFIG.initialVelocityXVariance;
        const kernelState = {
            element: kernel,
            x: startX,
            y: startY,
            vx: CONFIG.initialVelocityX + (Math.random() - 0.5) * velocityXVariance * 2,
            vy: CONFIG.initialVelocityY + (Math.random() - 0.5) * CONFIG.initialVelocityYVariance * 2,
            landed: false,
            createdAt: Date.now()
        };

        activeKernels.push(kernelState);
        animateKernel(kernelState);
    }

    // =========================================================================
    // Physics Animation
    // =========================================================================
    function animateKernel(kernelState) {
        const footerRect = footerElement.getBoundingClientRect();
        const footerTop = footerRect.top;
        const viewportHeight = window.innerHeight;

        function step() {
            if (!kernelState.element.parentNode) return; // Already removed

            if (!kernelState.landed) {
                // Apply gravity
                kernelState.vy += CONFIG.gravity;

                // Update position
                kernelState.x += kernelState.vx;
                kernelState.y += kernelState.vy;

                // Check landing on footer
                const landingY = Math.min(footerTop - 10, viewportHeight - 20);
                if (kernelState.y >= landingY && kernelState.vy > 0) {
                    kernelState.y = landingY;
                    kernelState.landed = true;
                    kernelState.landedAt = Date.now();

                    // Schedule removal
                    setTimeout(() => {
                        fadeOutAndRemove(kernelState);
                    }, CONFIG.kernelLifetimeMs);
                }

                // Keep horizontal position in bounds
                kernelState.x = Math.max(10, Math.min(window.innerWidth - 10, kernelState.x));

                // Update DOM
                kernelState.element.style.left = kernelState.x + 'px';
                kernelState.element.style.top = kernelState.y + 'px';

                // Continue animation
                requestAnimationFrame(step);
            }
        }

        requestAnimationFrame(step);
    }

    // =========================================================================
    // Kernel Removal
    // =========================================================================
    function fadeOutAndRemove(kernelState) {
        if (!kernelState.element.parentNode) return;

        kernelState.element.classList.add('popcorn-kernel-fadeout');

        setTimeout(() => {
            removeKernel(kernelState);
        }, CONFIG.fadeOutDurationMs);
    }

    function removeKernel(kernelState) {
        if (kernelState.element.parentNode) {
            kernelState.element.parentNode.removeChild(kernelState.element);
        }

        const index = activeKernels.indexOf(kernelState);
        if (index > -1) {
            activeKernels.splice(index, 1);
        }
    }

    // =========================================================================
    // Start
    // =========================================================================
    init();
})();
