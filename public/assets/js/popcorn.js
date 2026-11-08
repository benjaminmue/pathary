/**
 * Popcorn Rating Component: accessible rating input (ARIA radiogroup).
 *
 * - One tab stop (roving tabindex); arrow keys, Home and End move the selection.
 * - Click or Space/Enter selects a value; the separate clear button resets to 0.
 * - Hover only previews visually and never changes the ARIA state.
 *
 * Markup: templates/components/popcorn_rating.twig (input mode).
 */
(function () {
    'use strict';

    var MAX_VALUE = 7;

    function initPopcornRating() {
        document.querySelectorAll('[data-popcorn-rating]').forEach(function (field) {
            if (field.dataset.popcornReady === 'true') {
                return;
            }

            var radios = Array.prototype.slice.call(field.querySelectorAll('[role="radio"][data-value]'));
            var hiddenInput = field.querySelector('.popcorn-rating__value');
            var clearButton = field.querySelector('[data-popcorn-clear]');
            var group = field.querySelector('[role="radiogroup"]');

            if (!radios.length || !hiddenInput || !group) {
                return;
            }

            field.dataset.popcornReady = 'true';

            function selectedValue() {
                return parseInt(hiddenInput.value, 10) || 0;
            }

            function select(value, moveFocus) {
                hiddenInput.value = value;
                updateState(radios, value);
                if (clearButton) {
                    clearButton.hidden = value === 0;
                }
                if (moveFocus) {
                    radios[Math.max(value, 1) - 1].focus();
                }
                hiddenInput.dispatchEvent(new Event('change', {bubbles: true}));
            }

            radios.forEach(function (radio) {
                var value = parseInt(radio.dataset.value, 10);

                radio.addEventListener('click', function (event) {
                    event.preventDefault();
                    select(value, false);
                });

                radio.addEventListener('mouseenter', function () {
                    paint(radios, value);
                });

                radio.addEventListener('keydown', function (event) {
                    var current = selectedValue() || value;
                    var next = null;

                    if (event.key === 'ArrowRight' || event.key === 'ArrowUp') {
                        next = Math.min(current + 1, MAX_VALUE);
                    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') {
                        next = Math.max(current - 1, 1);
                    } else if (event.key === 'Home') {
                        next = 1;
                    } else if (event.key === 'End') {
                        next = MAX_VALUE;
                    }

                    if (next !== null) {
                        event.preventDefault();
                        select(next, true);
                    }
                });
            });

            group.addEventListener('mouseleave', function () {
                paint(radios, selectedValue());
            });

            if (clearButton) {
                clearButton.addEventListener('click', function () {
                    select(0, true);
                });
            }

            updateState(radios, selectedValue());
        });
    }

    // Visual state only (filled/empty emoji); used for the hover preview too.
    function paint(radios, value) {
        radios.forEach(function (radio) {
            var isOn = parseInt(radio.dataset.value, 10) <= value;
            radio.classList.toggle('popcorn-on', isOn);
            radio.classList.toggle('popcorn-off', !isOn);
        });
    }

    // Visual plus ARIA state: only the selected radio is checked and focusable.
    function updateState(radios, value) {
        paint(radios, value);
        radios.forEach(function (radio) {
            var radioValue = parseInt(radio.dataset.value, 10);
            radio.setAttribute('aria-checked', radioValue === value ? 'true' : 'false');
            radio.tabIndex = radioValue === Math.max(value, 1) ? 0 : -1;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPopcornRating);
    } else {
        initPopcornRating();
    }

    // Re-initialize for dynamically added content
    window.initPopcornRating = initPopcornRating;
})();
