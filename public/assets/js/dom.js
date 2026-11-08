/**
 * Shared DOM helpers for the app layout (templates/layouts/app_base.twig).
 */

/**
 * Escapes a value for use inside HTML text and quoted attribute values.
 *
 * @param {unknown} value
 * @returns {string}
 */
function escapeHtml(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Builds a Bootstrap toast element. The message is always inserted as text,
 * so server or user supplied strings can never inject markup.
 *
 * @param {string} message
 * @param {string} colorClasses e.g. "text-bg-success" or "text-white bg-danger"
 * @param {string} closeLabel accessible name of the close button (translated)
 * @returns {HTMLDivElement}
 */
function createToastElement(message, colorClasses, closeLabel) {
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center border-0 ' + colorClasses;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    const row = document.createElement('div');
    row.className = 'd-flex';

    const body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close btn-close-white me-2 m-auto';
    close.dataset.bsDismiss = 'toast';
    close.setAttribute('aria-label', closeLabel);

    row.append(body, close);
    toast.append(row);

    return toast;
}
