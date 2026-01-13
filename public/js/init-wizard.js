// Init Wizard JavaScript
let currentStep = 1;
let totpUri = '';
let recoveryCodes = [];

// Password validation
function validatePassword() {
    const password = document.getElementById('password').value;

    const requirements = {
        'req-length': password.length >= 10,
        'req-uppercase': /[A-Z]/.test(password),
        'req-lowercase': /[a-z]/.test(password),
        'req-number': /[0-9]/.test(password),
        'req-special': /[^A-Za-z0-9]/.test(password)
    };

    for (const [id, valid] of Object.entries(requirements)) {
        const element = document.getElementById(id);
        if (valid) {
            element.classList.add('valid');
            element.querySelector('i').className = 'bi bi-check-circle-fill';
        } else {
            element.classList.remove('valid');
            element.querySelector('i').className = 'bi bi-circle';
        }
    }

    return Object.values(requirements).every(v => v);
}

// Check if passwords match and apply visual feedback
function checkPasswordMatch() {
    const password = document.getElementById('password');
    const passwordConfirmation = document.getElementById('passwordConfirmation');

    // Only validate confirmation field if both fields have content
    if (password.value && passwordConfirmation.value) {
        if (password.value === passwordConfirmation.value) {
            // Passwords match - green outline on confirmation field only
            passwordConfirmation.classList.remove('is-invalid');
            passwordConfirmation.classList.add('is-valid');
        } else {
            // Passwords don't match - red outline on confirmation field only
            passwordConfirmation.classList.remove('is-valid');
            passwordConfirmation.classList.add('is-invalid');
        }
    } else {
        // Clear validation if either field is empty
        passwordConfirmation.classList.remove('is-valid', 'is-invalid');
    }
}

// Show error message
function showError(message) {
    const errorDiv = document.getElementById('wizardErrors');
    errorDiv.textContent = message;
    errorDiv.classList.remove('d-none');

    // Scroll to top to show error
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Hide error message
function hideError() {
    const errorDiv = document.getElementById('wizardErrors');
    errorDiv.classList.add('d-none');
}

// Show success message
function showSuccess(message) {
    const successDiv = document.getElementById('wizardSuccess');
    successDiv.textContent = message;
    successDiv.classList.remove('d-none');

    // Auto-hide after 3 seconds
    setTimeout(() => {
        successDiv.classList.add('d-none');
    }, 3000);

    // Scroll to top to show success message
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Hide success message
function hideSuccess() {
    const successDiv = document.getElementById('wizardSuccess');
    successDiv.classList.add('d-none');
}

// Navigate to step
function goToStep(step) {
    // Hide all steps
    for (let i = 1; i <= 4; i++) {
        document.getElementById(`step-${i}`).classList.remove('active');
        const indicator = document.getElementById(`step-indicator-${i}`);
        indicator.classList.remove('active');
        if (i < step) {
            indicator.classList.add('completed');
        } else {
            indicator.classList.remove('completed');
        }
    }

    // Show current step
    document.getElementById(`step-${step}`).classList.add('active');
    document.getElementById(`step-indicator-${step}`).classList.add('active');

    currentStep = step;
    hideError();
    hideSuccess();
}

// Step 1: Create Account
async function createAccount() {
    hideError();

    const email = document.getElementById('email').value.trim();
    const name = document.getElementById('name').value.trim();
    const password = document.getElementById('password').value;
    const passwordConfirmation = document.getElementById('passwordConfirmation').value;

    if (!email || !name || !password || !passwordConfirmation) {
        showError('All fields are required.');
        return;
    }

    if (!validatePassword()) {
        showError('Password does not meet security requirements.');
        return;
    }

    if (password !== passwordConfirmation) {
        showError('Passwords do not match.');
        return;
    }

    try {
        const response = await fetch('/init/create-admin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                step: 'create_account',
                email,
                name,
                password,
                passwordConfirmation
            })
        });

        const data = await response.json();

        if (!response.ok) {
            showError(data.error || 'Failed to create account. Please try again.');
            return;
        }

        // Move to 2FA setup
        await setupTotp();
    } catch (error) {
        showError('An error occurred. Please try again.');
        console.error('Error:', error);
    }
}

// Step 2: Setup TOTP
async function setupTotp() {
    try {
        const response = await fetch('/init/create-admin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ step: 'setup_totp' })
        });

        const data = await response.json();

        if (!response.ok) {
            showError(data.error || 'Failed to setup 2FA. Please try again.');
            return;
        }

        totpUri = data.totpUri;

        // Display QR code (same approach as profile-security.js)
        const qrcodeContainer = document.getElementById('qrcode');
        if (qrcodeContainer) {
            qrcodeContainer.innerHTML = '';
            if (typeof QRCode !== 'undefined') {
                new QRCode(qrcodeContainer, {
                    text: totpUri,
                    width: 200,
                    height: 200,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            } else {
                console.error('QRCode library not loaded');
                showError('QR code library failed to load. Please refresh the page.');
                return;
            }
        }

        // Display secret
        document.getElementById('totpSecret').textContent = data.secret;

        // Move to step 2
        goToStep(2);
    } catch (error) {
        showError('An error occurred. Please try again.');
        console.error('Error:', error);
    }
}

// Verify TOTP code
async function verifyTotp() {
    hideError();

    const code = document.getElementById('totpCode').value.trim();

    if (!code || code.length !== 6) {
        showError('Please enter a valid 6-digit code.');
        return;
    }

    try {
        const response = await fetch('/init/create-admin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                step: 'verify_totp',
                code
            })
        });

        const data = await response.json();

        if (!response.ok) {
            showError(data.error || 'Invalid verification code. Please try again.');
            return;
        }

        // Store recovery codes
        recoveryCodes = data.recoveryCodes;

        // Display recovery codes
        const grid = document.getElementById('recoveryCodesGrid');
        grid.innerHTML = '';
        recoveryCodes.forEach(code => {
            const div = document.createElement('div');
            div.className = 'recovery-code';
            div.textContent = code;
            grid.appendChild(div);
        });

        // Move to step 3
        goToStep(3);
    } catch (error) {
        showError('An error occurred. Please try again.');
        console.error('Error:', error);
    }
}

// Copy TOTP secret to clipboard
async function copySecret() {
    const secret = document.getElementById('totpSecret').textContent;
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;

    let copied = false;

    // Try modern clipboard API first
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(secret);
            copied = true;
        } catch (err) {
            console.error('Clipboard API failed:', err);
            // Try fallback method
            copied = fallbackCopy(secret);
        }
    } else {
        // Use fallback for older browsers or non-secure contexts
        copied = fallbackCopy(secret);
    }

    // Update button and show feedback in ONE place
    if (copied) {
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Copied!';
        showSuccess('Secret copied to clipboard successfully!');
        setTimeout(() => {
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
            btn.innerHTML = originalHTML;
        }, 2500);
    }
}

// Fallback copy method using temporary textarea - returns true on success
function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    textarea.style.top = '0';
    document.body.appendChild(textarea);

    try {
        textarea.select();
        textarea.setSelectionRange(0, text.length);
        const success = document.execCommand('copy');

        if (!success) {
            showError('Failed to copy to clipboard.');
        }

        return success;
    } catch (err) {
        console.error('Fallback copy failed:', err);
        showError('Failed to copy to clipboard.');
        return false;
    } finally {
        document.body.removeChild(textarea);
    }
}

// Copy recovery codes to clipboard
async function copyRecoveryCodes() {
    const text = recoveryCodes.join('\n');
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;

    let copied = false;

    // Try modern clipboard API first
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            copied = true;
        } catch (err) {
            console.error('Clipboard API failed:', err);
            // Try fallback method
            copied = fallbackCopy(text);
        }
    } else {
        // Use fallback for older browsers or non-secure contexts
        copied = fallbackCopy(text);
    }

    // Update button and show feedback in ONE place
    if (copied) {
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Copied!';
        showSuccess('Recovery codes copied to clipboard successfully!');
        setTimeout(() => {
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
            btn.innerHTML = originalHTML;
        }, 2500);
    }
}

// Enable/disable complete setup button based on checkbox
document.addEventListener('DOMContentLoaded', () => {
    const checkbox = document.getElementById('acknowledgeBackup');
    const button = document.getElementById('completeSetupBtn');

    if (checkbox && button) {
        checkbox.addEventListener('change', () => {
            button.disabled = !checkbox.checked;
        });
    }
});

// Complete setup
async function completeSetup() {
    hideError();

    const acknowledged = document.getElementById('acknowledgeBackup').checked;

    if (!acknowledged) {
        showError('Please confirm that you have saved your recovery codes.');
        return;
    }

    try {
        const response = await fetch('/init/create-admin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ step: 'acknowledge_codes' })
        });

        const data = await response.json();

        if (!response.ok) {
            showError(data.error || 'Failed to complete setup. Please try again.');
            return;
        }

        // Move to success step
        goToStep(4);

        // Optional: Auto-redirect after a few seconds
        // setTimeout(() => {
        //     window.location.href = '/login';
        // }, 3000);
    } catch (error) {
        showError('An error occurred. Please try again.');
        console.error('Error:', error);
    }
}
