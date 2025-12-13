/**
 * Popcorn Rating Component - Interactive rating input
 */
(function() {
    'use strict';

    function initPopcornRating() {
        document.querySelectorAll('[data-popcorn-rating]').forEach(function(container) {
            var buttons = container.querySelectorAll('button[data-value]');
            var hiddenInput = container.querySelector('.popcorn-rating__value');

            if (!buttons.length || !hiddenInput) return;

            buttons.forEach(function(button) {
                button.addEventListener('click', function() {
                    var value = parseInt(this.getAttribute('data-value'), 10);
                    setRating(container, hiddenInput, buttons, value);
                });

                // Keyboard support (Enter/Space handled natively by button)
                button.addEventListener('keydown', function(e) {
                    if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        var currentValue = parseInt(hiddenInput.value, 10) || 0;
                        var newValue = Math.min(currentValue + 1, 7);
                        setRating(container, hiddenInput, buttons, newValue);
                        focusButton(buttons, newValue);
                    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        var currentValue = parseInt(hiddenInput.value, 10) || 0;
                        var newValue = Math.max(currentValue - 1, 0);
                        setRating(container, hiddenInput, buttons, newValue);
                        focusButton(buttons, newValue || 1);
                    }
                });
            });
        });
    }

    function setRating(container, hiddenInput, buttons, value) {
        // Toggle off if clicking same value
        var currentValue = parseInt(hiddenInput.value, 10) || 0;
        if (currentValue === value) {
            value = 0;
        }

        hiddenInput.value = value;

        buttons.forEach(function(btn) {
            var btnValue = parseInt(btn.getAttribute('data-value'), 10);
            var isOn = btnValue <= value;

            btn.classList.toggle('popcorn-on', isOn);
            btn.classList.toggle('popcorn-off', !isOn);
            btn.setAttribute('aria-pressed', isOn ? 'true' : 'false');
        });

        container.setAttribute('aria-label', 'Rating: ' + value + ' out of 7');

        // Dispatch change event for form integration
        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function focusButton(buttons, value) {
        buttons.forEach(function(btn) {
            if (parseInt(btn.getAttribute('data-value'), 10) === value) {
                btn.focus();
            }
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPopcornRating);
    } else {
        initPopcornRating();
    }

    // Re-initialize for dynamically added content
    window.initPopcornRating = initPopcornRating;
})();
