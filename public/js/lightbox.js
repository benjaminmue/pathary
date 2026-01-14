/**
 * Poster Lightbox JavaScript
 * Handles opening/closing lightbox for movie posters
 */

(function() {
    'use strict';

    /**
     * Initialize lightbox functionality
     * Called when DOM is ready
     */
    function initLightbox() {
        const lightbox = document.getElementById('posterLightbox');
        const lightboxImage = document.getElementById('posterLightboxImage');
        const closeButton = document.querySelector('.lightbox-close');

        if (!lightbox || !lightboxImage || !closeButton) {
            console.warn('Lightbox elements not found - lightbox disabled');
            return;
        }

        // Close button click
        closeButton.addEventListener('click', closePosterLightbox);

        // Click outside image to close
        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) {
                closePosterLightbox();
            }
        });

        // Escape key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && lightbox.style.display === 'flex') {
                closePosterLightbox();
            }
        });

        // Prevent clicks on image from closing lightbox
        lightboxImage.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    /**
     * Open lightbox with poster image
     * @param {string} posterPath - TMDB poster path (e.g., /w342/abc123.jpg)
     * @param {string} altText - Alt text for image (movie title)
     */
    window.openPosterLightbox = function(posterPath, altText) {
        if (!posterPath) {
            return; // Don't open lightbox for movies without posters
        }

        const lightbox = document.getElementById('posterLightbox');
        const lightboxImage = document.getElementById('posterLightboxImage');

        if (!lightbox || !lightboxImage) {
            console.error('Lightbox elements not found');
            return;
        }

        // Convert poster path to w780 size for good quality without being too large
        const fullSizePoster = posterPath.replace(/\/w\d+\//, '/w780/');

        // Set image source and alt text
        lightboxImage.src = fullSizePoster;
        lightboxImage.alt = altText || 'Movie Poster';

        // Show lightbox
        lightbox.style.display = 'flex';

        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    };

    /**
     * Close lightbox
     */
    window.closePosterLightbox = function() {
        const lightbox = document.getElementById('posterLightbox');

        if (!lightbox) {
            return;
        }

        // Hide lightbox
        lightbox.style.display = 'none';

        // Restore body scroll
        document.body.style.overflow = '';
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLightbox);
    } else {
        initLightbox();
    }

})();
