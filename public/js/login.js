const PATHARY_CLIENT_IDENTIFIER = 'Pathary Web';

// Switch between authenticator and recovery code modes
function switchVerificationMode(mode) {
    const authenticatorContent = document.getElementById('authenticatorModeContent');
    const recoveryContent = document.getElementById('recoveryModeContent');
    const totpInput = document.getElementById('totpCode');
    const recoveryInput = document.getElementById('recoveryCode');

    if (mode === 'totp') {
        authenticatorContent.classList.remove('d-none');
        recoveryContent.classList.add('d-none');
        totpInput.value = '';
        totpInput.focus();
    } else {
        authenticatorContent.classList.add('d-none');
        recoveryContent.classList.remove('d-none');
        recoveryInput.value = '';
        recoveryInput.focus();
    }

    // Clear any errors
    const errorsDiv = document.getElementById('totpErrors');
    if (errorsDiv) {
        errorsDiv.innerHTML = '';
    }
}

async function submitCredentials() {
    const urlParams = new URLSearchParams(window.location.search);
    const safeRedirect = getSafeRedirect(urlParams.get('redirect'), APPLICATION_URL);

    const response = await loginRequest();

    if (response.status === 200) {
        window.location.href = safeRedirect
        return;
    }

    const forbiddenPageAlert = document.getElementById('forbiddenPageAlert');
    if (forbiddenPageAlert) {
        forbiddenPageAlert.classList.add('d-none');
    }

    if (response.status === 400) {
        const error = await response.json();

        if (error['error'] === 'MissingTotpCode') {
            document.getElementById('loginForm').classList.add('d-none');
            document.getElementById('totpForm').classList.remove('d-none');
            return
        }

        addAlert('loginErrors', error['message'], 'danger', false);
        return;
    }

    if (response.status === 401) {
        const error = await response.json();

        if (error['error'] === 'InvalidTotpCode') {
            const mode = getVerificationMode();
            const errorMessage = mode === 'recovery'
                ? 'Recovery code invalid or already used'
                : 'Two-factor authentication code wrong';
            addAlert('totpErrors', errorMessage, 'danger', false);
            return
        }

        if (error['error'] === 'InvalidCredentials') {
            addAlert('loginErrors', error['message'], 'danger', false);
            return
        }
    }

    addAlert('loginErrors', 'Unexpected server error', 'danger', false);
}

function getVerificationMode() {
    const modeRecovery = document.getElementById('modeRecovery');
    return (modeRecovery && modeRecovery.checked) ? 'recovery' : 'totp';
}

function loginRequest() {
    const mode = getVerificationMode();
    const requestBody = {
        'email': document.getElementById('email').value,
        'password': document.getElementById('password').value,
        'rememberMe': document.getElementById('rememberMe').checked,
    };

    // Add either totpCode or recoveryCode based on mode
    if (mode === 'recovery') {
        const recoveryCodeInput = document.getElementById('recoveryCode');
        requestBody['recoveryCode'] = recoveryCodeInput ? recoveryCodeInput.value : '';
    } else {
        const totpCodeInput = document.getElementById('totpCode');
        requestBody['totpCode'] = totpCodeInput ? totpCodeInput.value : '';

        // Add trustDevice flag if checkbox is checked (only in TOTP mode)
        const trustDeviceCheckbox = document.getElementById('trustDevice');
        if (trustDeviceCheckbox && trustDeviceCheckbox.checked) {
            requestBody['trustDevice'] = true;
        }
    }

    return fetch(APPLICATION_URL + '/api/authentication/token', {
        method: 'POST',
        headers: {
            'Content-type': 'application/json',
            'X-Movary-Client': PATHARY_CLIENT_IDENTIFIER
        },
        credentials: 'include',
        body: JSON.stringify(requestBody)
    });
}

function submitCredentialsOnEnter(event) {
    if (event.keyCode === 13) {
        submitCredentials()
    }
}

function getSafeRedirect(redirectGetParameter, baseUrl) {
    if (!redirectGetParameter) {
        return baseUrl + '/';
    }

    try {
        const parsed = new URL(redirectGetParameter, baseUrl);
        const path = parsed.pathname + parsed.search + parsed.hash;

        return baseUrl + path;
    } catch {
        return baseUrl + '/';
    }
}

// Format recovery code input as XXXX-XXXX-XX
function formatRecoveryCodeInput(event) {
    const input = event.target;
    const cursorPosition = input.selectionStart;

    // Get raw input value
    let value = input.value;

    // Remove all non-alphanumeric characters
    let cleaned = value.replace(/[^A-Za-z0-9]/g, '');

    // Convert to uppercase
    cleaned = cleaned.toUpperCase();

    // Limit to 10 characters
    cleaned = cleaned.substring(0, 10);

    // Insert dashes at positions 4 and 8
    let formatted = '';
    for (let i = 0; i < cleaned.length; i++) {
        if (i === 4 || i === 8) {
            formatted += '-';
        }
        formatted += cleaned[i];
    }

    // Calculate new cursor position
    const oldLength = value.length;
    const newLength = formatted.length;
    const wasAtEnd = cursorPosition === oldLength;

    // Update input value
    input.value = formatted;

    // Restore cursor position
    if (!wasAtEnd) {
        // Count how many dashes are before cursor in old vs new value
        const dashesBeforeOld = (value.substring(0, cursorPosition).match(/-/g) || []).length;
        const dashesBeforeNew = (formatted.substring(0, cursorPosition).match(/-/g) || []).length;
        const newPosition = cursorPosition + (dashesBeforeNew - dashesBeforeOld);
        input.setSelectionRange(newPosition, newPosition);
    }
}
