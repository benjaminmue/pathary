/**
 * Theme Toggle Module
 * Handles dark/light mode switching with localStorage persistence
 * and system preference detection.
 */
(function() {
    'use strict';

    const STORAGE_KEY = 'theme';
    const DARK_THEME = 'dark';
    const LIGHT_THEME = 'light';

    /**
     * Get the current theme from the document
     */
    function getCurrentTheme() {
        return document.documentElement.getAttribute('data-bs-theme') || LIGHT_THEME;
    }

    /**
     * Set the theme on the document and update UI
     */
    function setTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem(STORAGE_KEY, theme);
        updateToggleState(theme);
        updateIcon(theme);
    }

    /**
     * Update the toggle checkbox state
     */
    function updateToggleState(theme) {
        const toggle = document.getElementById('darkModeToggle');
        if (toggle) {
            toggle.checked = theme === DARK_THEME;
        }
    }

    /**
     * Update the theme icon
     */
    function updateIcon(theme) {
        const icon = document.getElementById('themeIcon');
        if (icon) {
            icon.textContent = theme === DARK_THEME ? '☀️' : '🌙';
        }
    }

    /**
     * Toggle between dark and light themes
     */
    function toggleTheme() {
        const current = getCurrentTheme();
        const newTheme = current === DARK_THEME ? LIGHT_THEME : DARK_THEME;
        setTheme(newTheme);
    }

    /**
     * Initialize the theme toggle functionality
     */
    function init() {
        const toggle = document.getElementById('darkModeToggle');

        if (toggle) {
            // Set initial state based on current theme
            const currentTheme = getCurrentTheme();
            updateToggleState(currentTheme);
            updateIcon(currentTheme);

            // Add event listener for toggle changes
            toggle.addEventListener('change', function() {
                toggleTheme();
            });
        }

        // Listen for system preference changes (when no saved preference)
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (!localStorage.getItem(STORAGE_KEY)) {
                setTheme(e.matches ? DARK_THEME : LIGHT_THEME);
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
