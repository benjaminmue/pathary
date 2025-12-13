/**
 * Popcorn Rating Component - Interactive rating input
 * Supports click selection, hover preview (desktop), and keyboard navigation
 */
(function() {
    'use strict';

    function initPopcornRating() {
        document.querySelectorAll('[data-popcorn-rating]').forEach(function(container) {
            var buttons = container.querySelectorAll('button[data-value]');
            var hiddenInput = container.querySelector('.popcorn-rating__value');

            if (!buttons.length || !hiddenInput) return;

            // Store the selected value on the container
            var initialValue = parseInt(hiddenInput.value, 10) || 0;
            container.setAttribute('data-selected', initialValue);

            buttons.forEach(function(button) {
                // Click to select rating
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    var value = parseInt(this.getAttribute('data-value'), 10);
                    var currentSelected = parseInt(container.getAttribute('data-selected'), 10) || 0;

                    // Toggle off if clicking same value, otherwise set new value
                    var newValue = (currentSelected === value) ? 0 : value;

                    container.setAttribute('data-selected', newValue);
                    hiddenInput.value = newValue;
                    updateDisplay(buttons, newValue);

                    // Dispatch change event for form integration
                    hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                });

                // Hover preview (desktop)
                button.addEventListener('mouseenter', function() {
                    var hoverValue = parseInt(this.getAttribute('data-value'), 10);
                    updateDisplay(buttons, hoverValue);
                });

                // Keyboard support
                button.addEventListener('keydown', function(e) {
                    var currentSelected = parseInt(container.getAttribute('data-selected'), 10) || 0;
                    var newValue = currentSelected;

                    if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        newValue = Math.min(currentSelected + 1, 7);
                    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        newValue = Math.max(currentSelected - 1, 0);
                    }

                    if (newValue !== currentSelected) {
                        container.setAttribute('data-selected', newValue);
                        hiddenInput.value = newValue;
                        updateDisplay(buttons, newValue);
                        focusButton(buttons, newValue || 1);
                        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            });

            // Restore selected value when leaving the widget
            container.addEventListener('mouseleave', function() {
                var selectedValue = parseInt(container.getAttribute('data-selected'), 10) || 0;
                updateDisplay(buttons, selectedValue);
            });

            // Initial display
            updateDisplay(buttons, initialValue);
        });
    }

    function updateDisplay(buttons, value) {
        buttons.forEach(function(btn) {
            var btnValue = parseInt(btn.getAttribute('data-value'), 10);
            var isOn = btnValue <= value && value > 0;

            btn.classList.toggle('popcorn-on', isOn);
            btn.classList.toggle('popcorn-off', !isOn);
            btn.setAttribute('aria-pressed', isOn ? 'true' : 'false');
        });
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
