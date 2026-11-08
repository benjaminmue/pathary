/**
 * Poster lightbox: an accessible modal dialog for the full-size poster.
 *
 * Markup contract:
 *   <button type="button" data-poster-lightbox data-poster-src="..." data-poster-alt="...">...</button>
 *   <div id="posterLightbox" class="lightbox-overlay" role="dialog" aria-modal="true" aria-label="..." hidden>
 *       <button type="button" class="lightbox-close" aria-label="...">&times;</button>
 *       <img id="posterLightboxImage" class="lightbox-image" src="" alt="">
 *   </div>
 *
 * Escape and the close button close the dialog, focus stays on the close
 * button while it is open (its only control) and returns to the trigger.
 */
(function () {
    'use strict';

    let returnFocusTo = null;

    function elements() {
        const lightbox = document.getElementById('posterLightbox');

        return {
            lightbox: lightbox,
            image: document.getElementById('posterLightboxImage'),
            closeButton: lightbox ? lightbox.querySelector('.lightbox-close') : null,
        };
    }

    function isOpen(lightbox) {
        return lightbox !== null && lightbox.hidden === false;
    }

    function openPosterLightbox(posterPath, altText, trigger) {
        const {lightbox, image, closeButton} = elements();
        if (!posterPath || !lightbox || !image || !closeButton) {
            return;
        }

        // w780 is sharp enough for a full-screen view without loading the original.
        image.src = posterPath.replace(/\/w\d+\//, '/w780/');
        image.alt = altText || '';

        returnFocusTo = trigger || document.activeElement;
        lightbox.hidden = false;
        document.body.style.overflow = 'hidden';
        closeButton.focus();
    }

    function closePosterLightbox() {
        const {lightbox} = elements();
        if (!isOpen(lightbox)) {
            return;
        }

        lightbox.hidden = true;
        document.body.style.overflow = '';

        if (returnFocusTo && typeof returnFocusTo.focus === 'function') {
            returnFocusTo.focus();
        }
        returnFocusTo = null;
    }

    function init() {
        const {lightbox, image, closeButton} = elements();
        if (!lightbox || !image || !closeButton) {
            return;
        }

        document.querySelectorAll('[data-poster-lightbox]').forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                openPosterLightbox(trigger.dataset.posterSrc, trigger.dataset.posterAlt, trigger);
            });
        });

        closeButton.addEventListener('click', closePosterLightbox);

        // A click on the backdrop (not the image) closes the dialog.
        lightbox.addEventListener('click', function (event) {
            if (event.target === lightbox) {
                closePosterLightbox();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (!isOpen(lightbox)) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closePosterLightbox();
            } else if (event.key === 'Tab') {
                // The close button is the only focusable element: trap focus on it.
                event.preventDefault();
                closeButton.focus();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
