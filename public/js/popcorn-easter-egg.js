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
        kernelSizeVariance: 4,

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
        // Spawn from top quarter of bucket where the popcorn cloud is in the SVG
        const spawnY = bucketRect.top + bucketRect.height * 0.25;

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

                // Check collision with bucket
                checkBucketCollision(kernelState);

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

    /**
     * Checks if kernel collides with bucket and applies bounce physics
     * Only checks when kernel is falling to avoid collision with spawn point
     */
    function checkBucketCollision(kernelState) {
        if (!bucketButton) return;

        // Only check collision when kernel is falling down (past its peak arc)
        if (kernelState.vy < 0) return;

        const bucketRect = bucketButton.getBoundingClientRect();
        const kernelSize = CONFIG.kernelSize;

        // SVG has transparent padding - adjust collision box to match visible bucket
        // Top padding: ~20% (popcorn cloud starts at y=401/2048)
        // Bottom padding: ~19% (bucket ends at y=1665/2048)
        // Horizontal: bucket tapers, so inset sides slightly
        const visibleRect = {
            left: bucketRect.left + bucketRect.width * 0.15,   // 15% inset from sides
            right: bucketRect.right - bucketRect.width * 0.15,
            top: bucketRect.top + bucketRect.height * 0.20,    // 20% from top
            bottom: bucketRect.bottom - bucketRect.height * 0.19  // 19% from bottom
        };

        // Check if kernel intersects with visible bucket area
        const collision = (
            kernelState.x + kernelSize > visibleRect.left &&
            kernelState.x < visibleRect.right &&
            kernelState.y + kernelSize > visibleRect.top &&
            kernelState.y < visibleRect.bottom
        );

        if (collision) {
            // Determine which side of the bucket was hit
            const kernelCenterX = kernelState.x + kernelSize / 2;
            const kernelCenterY = kernelState.y + kernelSize / 2;
            const visibleCenterX = (visibleRect.left + visibleRect.right) / 2;
            const visibleCenterY = (visibleRect.top + visibleRect.bottom) / 2;

            const dx = kernelCenterX - visibleCenterX;
            const dy = kernelCenterY - visibleCenterY;

            // Determine primary collision axis
            if (Math.abs(dx) > Math.abs(dy)) {
                // Horizontal collision (left or right side)
                kernelState.vx = -kernelState.vx * 0.6; // Bounce with damping
                kernelState.vy *= 0.8; // Reduce vertical velocity slightly

                // Add random horizontal velocity for variety
                kernelState.vx += (Math.random() - 0.5) * 4;

                // Push kernel out of collision
                if (dx > 0) {
                    kernelState.x = visibleRect.right;
                } else {
                    kernelState.x = visibleRect.left - kernelSize;
                }
            } else {
                // Vertical collision (top or bottom)
                kernelState.vy = -kernelState.vy * 0.5; // Bounce with damping
                kernelState.vx += (Math.random() - 0.5) * 6; // Add random horizontal spread

                // Push kernel out of collision
                if (dy > 0) {
                    kernelState.y = visibleRect.bottom;
                } else {
                    kernelState.y = visibleRect.top - kernelSize;
                }
            }
        }
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
