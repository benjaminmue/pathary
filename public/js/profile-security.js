// Global APPLICATION_URL is set in the base template

let securityData = null;

// Load security tab content
async function loadSecurityTab() {
    console.log('loadSecurityTab called');
    const container = document.getElementById('securityTabContent');
    console.log('Container:', container);

    if (!container) {
        console.error('Security tab content container not found');
        return;
    }

    try {
        console.log('Fetching from:', APPLICATION_URL + '/profile/security');
        const response = await fetch(APPLICATION_URL + '/profile/security');
        console.log('Response status:', response.status);

        if (!response.ok) {
            throw new Error('Failed to load security settings');
        }

        const html = await response.text();
        console.log('Received HTML, length:', html.length);
        container.innerHTML = html;

        // Initialize event listeners after content is loaded
        initializeSecurityListeners();
        console.log('Security listeners initialized');
    } catch (error) {
        console.error('Error loading security tab:', error);
        container.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i> Failed to load security settings. Error: ${error.message}
            </div>
        `;
    }
}

function initializeSecurityListeners() {
    // Change Password
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    if (changePasswordBtn) {
        changePasswordBtn.addEventListener('click', showChangePasswordModal);
    }

    // Enable 2FA
    const enable2FABtn = document.getElementById('enable2FABtn');
    if (enable2FABtn) {
        enable2FABtn.addEventListener('click', enable2FA);
    }

    // Disable 2FA
    const disable2FABtn = document.getElementById('disable2FABtn');
    if (disable2FABtn) {
        disable2FABtn.addEventListener('click', showDisable2FAModal);
    }

    // Regenerate Recovery Codes
    const regenerateCodesBtn = document.getElementById('regenerateCodesBtn');
    if (regenerateCodesBtn) {
        regenerateCodesBtn.addEventListener('click', regenerateRecoveryCodes);
    }

    // Revoke Trusted Devices
    const revokeButtons = document.querySelectorAll('.revoke-device-btn');
    revokeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const deviceId = this.dataset.deviceId;
            revokeTrustedDevice(deviceId);
        });
    });

    // Revoke All Trusted Devices
    const revokeAllBtn = document.getElementById('revokeAllDevicesBtn');
    if (revokeAllBtn) {
        revokeAllBtn.addEventListener('click', revokeAllTrustedDevices);
    }
}

// Change Password
function showChangePasswordModal() {
    const modalHtml = `
        <div class="modal fade" id="changePasswordModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Change Password</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="changePasswordForm" novalidate>
                        <div class="modal-body">
                            <div id="changePasswordAlert"></div>
                            <div class="mb-3">
                                <label for="currentPassword" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="currentPassword" required>
                                <div class="invalid-feedback">
                                    Current password is required.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="newPassword" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="newPassword" required minlength="10">
                                <div id="passwordRequirements" class="small mt-1">
                                    <div class="text-muted mb-1">Password must contain:</div>
                                    <div id="req-length" class="text-muted"><i class="bi bi-circle"></i> At least 10 characters</div>
                                    <div id="req-uppercase" class="text-muted"><i class="bi bi-circle"></i> One uppercase letter</div>
                                    <div id="req-lowercase" class="text-muted"><i class="bi bi-circle"></i> One lowercase letter</div>
                                    <div id="req-number" class="text-muted"><i class="bi bi-circle"></i> One number</div>
                                    <div id="req-special" class="text-muted"><i class="bi bi-circle"></i> One special character</div>
                                </div>
                                <div class="invalid-feedback">
                                    Password does not meet policy requirements.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirmPassword" required>
                                <div class="invalid-feedback" id="confirmPasswordInvalid">
                                    Confirm password is required.
                                </div>
                                <div class="valid-feedback" id="confirmPasswordValid">
                                    Passwords match.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
    modal.show();

    // Initialize password validation listeners
    initializePasswordValidation();

    document.getElementById('changePasswordModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

// Initialize password validation and Enter key prevention
function initializePasswordValidation() {
    const form = document.getElementById('changePasswordForm');
    const currentPassword = document.getElementById('currentPassword');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');

    // Prevent Enter key from submitting the form when in password inputs
    const passwordInputs = [currentPassword, newPassword, confirmPassword];
    passwordInputs.forEach(input => {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                // Optionally focus next field or do nothing
                const nextInput = passwordInputs[passwordInputs.indexOf(input) + 1];
                if (nextInput) {
                    nextInput.focus();
                }
            }
        });
    });

    // Live validation for password policy
    function validatePasswordPolicy() {
        const password = newPassword.value;

        // Check each requirement
        const hasLength = password.length >= 10;
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[^a-zA-Z0-9]/.test(password);

        // Update visual indicators
        updateRequirement('req-length', hasLength);
        updateRequirement('req-uppercase', hasUppercase);
        updateRequirement('req-lowercase', hasLowercase);
        updateRequirement('req-number', hasNumber);
        updateRequirement('req-special', hasSpecial);

        // Return true if all requirements met
        return hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial;
    }

    function updateRequirement(elementId, isMet) {
        const element = document.getElementById(elementId);
        if (isMet) {
            element.classList.remove('text-muted', 'text-danger');
            element.classList.add('text-success');
            element.querySelector('i').className = 'bi bi-check-circle-fill';
        } else {
            element.classList.remove('text-success');
            element.classList.add('text-muted');
            element.querySelector('i').className = 'bi bi-circle';
        }
    }

    // Live validation for password matching
    function validatePasswordMatch() {
        const newPass = newPassword.value;
        const confirmPass = confirmPassword.value;

        // Only show match feedback if both fields have values
        if (newPass && confirmPass) {
            if (newPass === confirmPass) {
                // Passwords match
                confirmPassword.classList.remove('is-invalid');
                confirmPassword.classList.add('is-valid');
                document.getElementById('confirmPasswordInvalid').textContent = 'Confirm password is required.';
            } else {
                // Passwords don't match
                confirmPassword.classList.remove('is-valid');
                confirmPassword.classList.add('is-invalid');
                document.getElementById('confirmPasswordInvalid').textContent = 'Passwords do not match.';
            }
        } else {
            // Clear validation if fields are empty
            confirmPassword.classList.remove('is-valid', 'is-invalid');
        }
    }

    // Add input listeners for live validation
    newPassword.addEventListener('input', function() {
        validatePasswordPolicy();
        validatePasswordMatch();
    });
    confirmPassword.addEventListener('input', validatePasswordMatch);

    // Form submit handler
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const alertDiv = document.getElementById('changePasswordAlert');
        alertDiv.innerHTML = '';

        // Validate form
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        const currentPass = currentPassword.value;
        const newPass = newPassword.value;
        const confirmPass = confirmPassword.value;

        // Final check: passwords must match
        if (newPass !== confirmPass) {
            confirmPassword.classList.add('is-invalid');
            document.getElementById('confirmPasswordInvalid').textContent = 'Passwords do not match.';
            return;
        }

        // Final check: password policy
        if (!validatePasswordPolicy()) {
            newPassword.classList.add('is-invalid');
            alertDiv.innerHTML = '<div class="alert alert-danger">Password does not meet policy requirements.</div>';
            return;
        }

        try {
            const response = await fetch(APPLICATION_URL + '/profile/security/password', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({currentPassword: currentPass, newPassword: newPass})
            });

            const data = await response.json();

            if (data.success) {
                // Remove focus from submit button before closing modal
                if (document.activeElement) {
                    document.activeElement.blur();
                }

                bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
                showAlert('Password changed successfully.', 'success');
                loadSecurityTab();
            } else {
                alertDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
            }
        } catch (error) {
            alertDiv.innerHTML = '<div class="alert alert-danger">Failed to change password.</div>';
        }
    });
}

// Enable 2FA
async function enable2FA() {
    try {
        const response = await fetch(APPLICATION_URL + '/profile/security/totp/enable', {
            method: 'POST'
        });

        const data = await response.json();

        if (data.totpUri) {
            show2FASetupModal(data.totpUri, data.secret);
        } else {
            showAlert(data.error || 'Failed to enable 2FA.', 'danger');
        }
    } catch (error) {
        showAlert('Failed to enable 2FA.', 'danger');
    }
}

function show2FASetupModal(totpUri, secret) {
    const modalHtml = `
        <div class="modal fade" id="setup2FAModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Enable Two-Factor Authentication</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="setup2FAAlert"></div>
                        <p>Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.):</p>
                        <div class="text-center mb-3">
                            <div id="qrcode" style="display: inline-block;"></div>
                        </div>
                        <p class="text-muted small">Or enter this secret manually: <code>${secret}</code></p>
                        <div class="mb-3">
                            <label for="verificationCode" class="form-label">Enter verification code from your app:</label>
                            <input type="text" class="form-control" id="verificationCode" required pattern="[0-9]{6}" maxlength="6">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="verify2FACode()">Verify & Enable</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('setup2FAModal'));
    modal.show();

    // Generate QR code after modal is shown
    setTimeout(() => {
        const qrcodeContainer = document.getElementById('qrcode');
        if (qrcodeContainer && typeof QRCode !== 'undefined') {
            new QRCode(qrcodeContainer, {
                text: totpUri,
                width: 200,
                height: 200,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    }, 100);

    document.getElementById('setup2FAModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

async function verify2FACode() {
    const code = document.getElementById('verificationCode').value;
    const alertDiv = document.getElementById('setup2FAAlert');

    if (!/^\d{6}$/.test(code)) {
        alertDiv.innerHTML = '<div class="alert alert-danger">Please enter a valid 6-digit code.</div>';
        return;
    }

    try {
        const response = await fetch(APPLICATION_URL + '/profile/security/totp/verify', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({code})
        });

        const data = await response.json();

        if (data.success) {
            // Remove focus from the verify button before closing modal
            if (document.activeElement) {
                document.activeElement.blur();
            }

            bootstrap.Modal.getInstance(document.getElementById('setup2FAModal')).hide();

            // Small delay to ensure first modal is fully hidden before showing next
            setTimeout(() => {
                showRecoveryCodesModal(data.recoveryCodes);
                loadSecurityTab();
            }, 100);
        } else {
            alertDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
        }
    } catch (error) {
        alertDiv.innerHTML = '<div class="alert alert-danger">Failed to verify code.</div>';
    }
}

function showRecoveryCodesModal(codes) {
    const codesHtml = codes.map(code => `<li><code>${code}</code></li>`).join('');

    // Detect if dark mode is active
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const headerBg = isDarkMode ? 'background-color: var(--bs-secondary-bg); border-bottom: 2px solid var(--pathe-yellow);' : 'background-color: var(--pathe-yellow);';
    const titleColor = isDarkMode ? 'color: #ffffff !important;' : 'color: var(--pathe-dark) !important;';
    const iconColor = isDarkMode ? 'color: var(--pathe-yellow) !important;' : 'color: var(--pathe-dark) !important;';

    const modalHtml = `
        <div class="modal fade" id="recoveryCodesModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header" style="${headerBg}">
                        <h5 class="modal-title" style="${titleColor}"><i class="bi bi-exclamation-triangle" style="${iconColor}"></i> Save Your Recovery Codes</h5>
                        <button type="button" class="btn-close" id="recoveryCodesCloseBtn" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="criticalWarning" class="alert alert-danger">
                            <strong><i class="bi bi-exclamation-triangle-fill"></i> Critical:</strong> These codes will only be shown once. If you lose them, you may lose access to your account.
                        </div>
                        <ul class="list-unstyled" id="recoveryCodesList" style="background: var(--bs-tertiary-bg); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                            ${codesHtml}
                        </ul>
                        <div id="codesHiddenMessage" class="alert alert-info mb-3 d-none">
                            <i class="bi bi-eye-slash"></i> Codes hidden. Uncheck to show them again.
                        </div>
                        <div id="copyFeedback" style="margin-bottom: 1rem;"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="copyAllBtn">
                            <i class="bi bi-clipboard"></i> Copy All Codes
                        </button>

                        <div id="checkboxSection" class="mb-3 p-3" style="background: var(--bs-tertiary-bg); border-radius: 0.5rem;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmSavedCheckbox">
                                <label class="form-check-label" for="confirmSavedCheckbox">
                                    <strong>I have saved these recovery codes in a secure location</strong>
                                </label>
                            </div>
                        </div>

                        <div id="verificationSection" class="d-none">
                            <div class="alert alert-info">
                                <strong>Verification:</strong> To confirm you've saved the codes, please enter code #<span id="verifyCodeNumber"></span>:
                            </div>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" id="verifyCodeInput" placeholder="Enter recovery code" autocomplete="off">
                                <button class="btn btn-outline-primary" type="button" id="verifyCodeBtn">Verify</button>
                            </div>
                            <div id="verificationFeedback"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="confirmSavedBtn" disabled aria-disabled="true">I've Saved My Codes</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modalElement = document.getElementById('recoveryCodesModal');
    const modal = new bootstrap.Modal(modalElement);
    modal.show();

    // Store codes globally for copying and verification
    window.currentRecoveryCodes = codes;
    window.recoveryCodesState = {
        copied: false,
        confirmed: false,
        verified: false,
        verifyAttempts: 0,
        selectedCodeIndex: Math.floor(Math.random() * codes.length)
    };

    // Initialize event listeners
    initializeRecoveryCodesListeners(modal);

    modalElement.addEventListener('hidden.bs.modal', function() {
        this.remove();
        delete window.currentRecoveryCodes;
        delete window.recoveryCodesState;
    });
}

function initializeRecoveryCodesListeners(modal) {
    const copyAllBtn = document.getElementById('copyAllBtn');
    const checkbox = document.getElementById('confirmSavedCheckbox');
    const confirmBtn = document.getElementById('confirmSavedBtn');
    const closeBtn = document.getElementById('recoveryCodesCloseBtn');
    const verifyBtn = document.getElementById('verifyCodeBtn');
    const verifyInput = document.getElementById('verifyCodeInput');

    // Copy All button - track copy action
    if (copyAllBtn) {
        copyAllBtn.addEventListener('click', function() {
            copyRecoveryCodes();
            window.recoveryCodesState.copied = true;
            updateConfirmButtonState();
        });
    }

    // Checkbox acknowledgment
    if (checkbox) {
        checkbox.addEventListener('change', function() {
            window.recoveryCodesState.confirmed = this.checked;
            updateConfirmButtonState();

            // Toggle codes visibility
            const codesList = document.getElementById('recoveryCodesList');
            const copyBtn = document.getElementById('copyAllBtn');
            const hiddenMessage = document.getElementById('codesHiddenMessage');
            const verifySection = document.getElementById('verificationSection');

            if (this.checked) {
                // Hide codes and copy button, show message
                if (codesList) codesList.classList.add('d-none');
                if (copyBtn) copyBtn.classList.add('d-none');
                if (hiddenMessage) hiddenMessage.classList.remove('d-none');

                // Show verification challenge
                showVerificationChallenge();

                // Ensure verification section is accessible
                if (verifySection) {
                    verifySection.setAttribute('aria-hidden', 'false');
                }
            } else {
                // Show codes and copy button, hide message
                if (codesList) codesList.classList.remove('d-none');
                if (copyBtn) copyBtn.classList.remove('d-none');
                if (hiddenMessage) hiddenMessage.classList.add('d-none');

                // Hide and reset verification section
                hideVerificationSection();
            }
        });
    }

    // Confirm button - validate before closing
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            if (!window.recoveryCodesState.verified) {
                // Verification not complete
                const verifySection = document.getElementById('verificationSection');
                if (verifySection && verifySection.classList.contains('d-none')) {
                    showVerificationChallenge();
                }
                return;
            }

            // All checks passed, allow modal to close
            modal.hide();
            showAlert('success', 'Recovery codes confirmed. Your 2FA setup is complete!');
        });
    }

    // Close button - warn user
    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showCloseWarningDialog(modal);
        });
    }

    // Verification submit
    if (verifyBtn) {
        verifyBtn.addEventListener('click', function() {
            verifyRecoveryCode();
        });
    }

    // Allow Enter key in verification input
    if (verifyInput) {
        verifyInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyRecoveryCode();
            }
        });
    }
}

function updateConfirmButtonState() {
    const confirmBtn = document.getElementById('confirmSavedBtn');
    if (!confirmBtn) return;

    const state = window.recoveryCodesState;

    // Enable button only if checkbox is checked AND verification succeeded
    if (state.confirmed && state.verified) {
        confirmBtn.disabled = false;
        confirmBtn.removeAttribute('aria-disabled');
    } else {
        confirmBtn.disabled = true;
        confirmBtn.setAttribute('aria-disabled', 'true');
    }
}

function showVerificationChallenge() {
    const verifySection = document.getElementById('verificationSection');
    const verifyNumberSpan = document.getElementById('verifyCodeNumber');
    const verifyInput = document.getElementById('verifyCodeInput');
    const verifyBtn = document.getElementById('verifyCodeBtn');
    const confirmBtn = document.getElementById('confirmSavedBtn');

    if (!verifySection || !verifyNumberSpan) return;

    const codeIndex = window.recoveryCodesState.selectedCodeIndex;
    const displayNumber = codeIndex + 1; // 1-indexed for user

    verifyNumberSpan.textContent = displayNumber;
    verifySection.classList.remove('d-none');
    verifySection.setAttribute('aria-hidden', 'false');

    // Enable and reset input
    if (verifyInput) {
        verifyInput.value = '';
        verifyInput.disabled = false;
        verifyInput.removeAttribute('aria-disabled');
        verifyInput.classList.remove('is-invalid');
        verifyInput.focus();
    }

    // Enable verify button
    if (verifyBtn) {
        verifyBtn.disabled = false;
        verifyBtn.removeAttribute('aria-disabled');
    }

    // Change button text to indicate verification is needed and ensure it's disabled
    if (confirmBtn) {
        confirmBtn.textContent = 'Verify & Close';
        // Button will be disabled until verification succeeds
        confirmBtn.disabled = true;
        confirmBtn.setAttribute('aria-disabled', 'true');
        confirmBtn.classList.remove('btn-success');
        confirmBtn.classList.add('btn-primary');
    }
}

function hideVerificationSection() {
    const verifySection = document.getElementById('verificationSection');
    const verifyInput = document.getElementById('verifyCodeInput');
    const verifyBtn = document.getElementById('verifyCodeBtn');
    const confirmBtn = document.getElementById('confirmSavedBtn');

    if (!verifySection) return;

    // Hide the section
    verifySection.classList.add('d-none');
    verifySection.setAttribute('aria-hidden', 'true');

    // Reset verification input and remove validation state
    if (verifyInput) {
        verifyInput.value = '';
        verifyInput.classList.remove('is-invalid');
        verifyInput.disabled = true;
        verifyInput.setAttribute('aria-disabled', 'true');

        // Remove any invalid feedback
        const feedback = verifyInput.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.style.display = 'none';
        }
    }

    // Disable verify button
    if (verifyBtn) {
        verifyBtn.disabled = true;
        verifyBtn.setAttribute('aria-disabled', 'true');
    }

    // Reset confirm button to original state if it was changed
    if (confirmBtn && !window.recoveryCodesState.verified) {
        confirmBtn.textContent = "I've Saved My Codes";
        confirmBtn.classList.remove('btn-success');
        confirmBtn.classList.add('btn-primary');
        confirmBtn.disabled = true;
    }

    // Reset verification state (but keep other state)
    if (window.recoveryCodesState) {
        window.recoveryCodesState.verified = false;
        window.recoveryCodesState.verifyAttempts = 0;
    }

    // Restore initial UI elements when unchecking
    showInitialUIElements();
}

function verifyRecoveryCode() {
    const verifyInput = document.getElementById('verifyCodeInput');
    const verifySection = document.getElementById('verificationSection');
    const confirmBtn = document.getElementById('confirmSavedBtn');

    if (!verifyInput || !window.currentRecoveryCodes) return;

    const userInput = verifyInput.value.trim();
    const expectedCode = window.currentRecoveryCodes[window.recoveryCodesState.selectedCodeIndex];

    window.recoveryCodesState.verifyAttempts++;

    // Check if input matches
    if (userInput === expectedCode) {
        // Success
        window.recoveryCodesState.verified = true;

        // Show success feedback
        const successHtml = `
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i> <strong>Verified!</strong> You're all set.
            </div>
        `;
        verifySection.innerHTML = successHtml;

        // Update confirm button state (will be enabled now that verified = true)
        updateConfirmButtonState();

        // Change button style to success
        if (confirmBtn) {
            confirmBtn.classList.remove('btn-primary');
            confirmBtn.classList.add('btn-success');
            confirmBtn.innerHTML = '<i class="bi bi-check-lg"></i> Close';
        }

        // Hide unneeded UI elements after successful verification
        hideUnneededElementsAfterVerification();
    } else {
        // Failed verification
        if (window.recoveryCodesState.verifyAttempts >= 3) {
            // Too many attempts - show error
            const errorHtml = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i> <strong>Too many incorrect attempts.</strong> Please copy your codes again and try a different code.
                </div>
                <button class="btn btn-outline-primary" type="button" onclick="resetVerificationChallenge()">Try Again</button>
            `;
            verifySection.innerHTML = errorHtml;
            if (confirmBtn) confirmBtn.disabled = true;
        } else {
            // Show error but allow retry
            verifyInput.classList.add('is-invalid');
            const remainingAttempts = 3 - window.recoveryCodesState.verifyAttempts;

            // Add or update invalid feedback
            let feedback = verifyInput.nextElementSibling;
            if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                verifyInput.parentNode.insertBefore(feedback, verifyInput.nextSibling);
            }
            feedback.textContent = `Incorrect code. ${remainingAttempts} attempt(s) remaining.`;
            feedback.style.display = 'block';
        }
    }
}

function resetVerificationChallenge() {
    // Reset state
    window.recoveryCodesState.verifyAttempts = 0;
    window.recoveryCodesState.selectedCodeIndex = Math.floor(Math.random() * window.currentRecoveryCodes.length);
    window.recoveryCodesState.verified = false;

    // Restore initial UI elements
    showInitialUIElements();

    // Re-show challenge
    showVerificationChallenge();
}

function hideUnneededElementsAfterVerification() {
    // Hide critical warning
    const criticalWarning = document.getElementById('criticalWarning');
    if (criticalWarning) {
        criticalWarning.classList.add('d-none');
        criticalWarning.setAttribute('aria-hidden', 'true');
    }

    // Hide codes-hidden message
    const codesHiddenMessage = document.getElementById('codesHiddenMessage');
    if (codesHiddenMessage) {
        codesHiddenMessage.classList.add('d-none');
        codesHiddenMessage.setAttribute('aria-hidden', 'true');
    }

    // Hide checkbox section
    const checkboxSection = document.getElementById('checkboxSection');
    if (checkboxSection) {
        checkboxSection.classList.add('d-none');
        checkboxSection.setAttribute('aria-hidden', 'true');
    }
}

function showInitialUIElements() {
    // Show critical warning
    const criticalWarning = document.getElementById('criticalWarning');
    if (criticalWarning) {
        criticalWarning.classList.remove('d-none');
        criticalWarning.setAttribute('aria-hidden', 'false');
    }

    // Show checkbox section
    const checkboxSection = document.getElementById('checkboxSection');
    if (checkboxSection) {
        checkboxSection.classList.remove('d-none');
        checkboxSection.setAttribute('aria-hidden', 'false');
    }

    // Note: codesHiddenMessage visibility is controlled by checkbox state
}

function showCloseWarningDialog(modal) {
    const state = window.recoveryCodesState;

    // If already verified, allow close
    if (state.verified) {
        modal.hide();
        return;
    }

    // Show warning
    const confirmed = confirm(
        'WARNING: You have not verified that you saved your recovery codes.\n\n' +
        'If you close this window without saving these codes, you may lose access to your account if you lose your authenticator device.\n\n' +
        'Are you ABSOLUTELY SURE you want to close without verifying?'
    );

    if (confirmed) {
        modal.hide();
    }
}

function copyRecoveryCodes() {
    const feedbackDiv = document.getElementById('copyFeedback');

    if (!window.currentRecoveryCodes) {
        showCopyFeedback(feedbackDiv, 'error', 'No recovery codes available.');
        return;
    }

    const text = window.currentRecoveryCodes.join('\n');

    // Try modern Clipboard API first
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
            .then(() => {
                showCopyFeedback(feedbackDiv, 'success', 'Recovery codes copied to clipboard.');
            })
            .catch((err) => {
                console.error('Clipboard API failed:', err);
                // Try fallback method
                fallbackCopyToClipboard(text, feedbackDiv);
            });
    } else {
        // Clipboard API not available, use fallback
        fallbackCopyToClipboard(text, feedbackDiv);
    }
}

function fallbackCopyToClipboard(text, feedbackDiv) {
    try {
        // Create temporary textarea
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);

        textarea.focus();
        textarea.select();

        const successful = document.execCommand('copy');
        document.body.removeChild(textarea);

        if (successful) {
            showCopyFeedback(feedbackDiv, 'success', 'Recovery codes copied to clipboard.');
        } else {
            showCopyFeedback(feedbackDiv, 'error', 'Copy failed. Please select and copy manually.');
        }
    } catch (err) {
        console.error('Fallback copy failed:', err);
        showCopyFeedback(feedbackDiv, 'error', 'Copy failed. Please select and copy manually.');
    }
}

function showCopyFeedback(feedbackDiv, type, message) {
    if (!feedbackDiv) return;

    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const iconClass = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';

    feedbackDiv.innerHTML = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="bi ${iconClass}"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    // Auto-remove after 3 seconds
    setTimeout(() => {
        const alert = feedbackDiv.querySelector('.alert');
        if (alert) {
            alert.classList.remove('show');
            setTimeout(() => {
                feedbackDiv.innerHTML = '';
            }, 150);
        }
    }, 3000);
}

// Disable 2FA
function showDisable2FAModal() {
    const modalHtml = `
        <div class="modal fade" id="disable2FAModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Disable Two-Factor Authentication</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="disable2FAAlert"></div>
                        <div class="alert alert-warning">
                            <strong>Warning:</strong> Disabling 2FA will also remove all recovery codes and trusted devices.
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Enter your password to confirm:</label>
                            <input type="password" class="form-control" id="confirmPassword" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="submitDisable2FA()">Disable 2FA</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('disable2FAModal'));
    modal.show();

    document.getElementById('disable2FAModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

async function submitDisable2FA() {
    const password = document.getElementById('confirmPassword').value;
    const alertDiv = document.getElementById('disable2FAAlert');

    try {
        const response = await fetch(APPLICATION_URL + '/profile/security/totp/disable', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({password})
        });

        const data = await response.json();

        if (data.success) {
            // Remove focus from button before closing modal
            if (document.activeElement) {
                document.activeElement.blur();
            }

            bootstrap.Modal.getInstance(document.getElementById('disable2FAModal')).hide();
            showAlert('2FA has been disabled.', 'success');
            loadSecurityTab();
        } else {
            alertDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
        }
    } catch (error) {
        alertDiv.innerHTML = '<div class="alert alert-danger">Failed to disable 2FA.</div>';
    }
}

// Regenerate Recovery Codes
async function regenerateRecoveryCodes() {
    if (!confirm('This will invalidate all existing recovery codes. Continue?')) {
        return;
    }

    try {
        const response = await fetch(APPLICATION_URL + '/profile/security/recovery-codes/regenerate', {
            method: 'POST'
        });

        const data = await response.json();

        if (data.success) {
            showRecoveryCodesModal(data.recoveryCodes);
            loadSecurityTab();
        } else {
            showAlert(data.error || 'Failed to regenerate recovery codes.', 'danger');
        }
    } catch (error) {
        showAlert('Failed to regenerate recovery codes.', 'danger');
    }
}

// Revoke Trusted Device
async function revokeTrustedDevice(deviceId) {
    if (!confirm('Remove this trusted device? You will need to enter a 2FA code next time you log in from this device.')) {
        return;
    }

    try {
        const response = await fetch(APPLICATION_URL + `/profile/security/trusted-devices/${deviceId}/revoke`, {
            method: 'POST'
        });

        const data = await response.json();

        if (data.success) {
            showAlert('Trusted device removed.', 'success');
            loadSecurityTab();
        } else {
            showAlert(data.error || 'Failed to remove trusted device.', 'danger');
        }
    } catch (error) {
        showAlert('Failed to remove trusted device.', 'danger');
    }
}

// Revoke All Trusted Devices
async function revokeAllTrustedDevices() {
    if (!confirm('Remove ALL trusted devices? You will need to enter a 2FA code next time you log in from any device.')) {
        return;
    }

    try {
        const response = await fetch(APPLICATION_URL + '/profile/security/trusted-devices/revoke-all', {
            method: 'POST'
        });

        const data = await response.json();

        if (data.success) {
            showAlert('All trusted devices removed.', 'success');
            loadSecurityTab();
        } else {
            showAlert(data.error || 'Failed to remove trusted devices.', 'danger');
        }
    } catch (error) {
        showAlert('Failed to remove trusted devices.', 'danger');
    }
}

// Show centered notification popup
let currentNotification = null;

function showAlert(message, type) {
    // Remove existing notification if present
    if (currentNotification) {
        closeNotification();
    }

    // Determine icon based on type
    const iconClass = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
    const iconTypeClass = type === 'success' ? 'notification-popup__icon--success' : 'notification-popup__icon--error';

    // Create overlay
    const overlay = document.createElement('div');
    overlay.className = 'notification-overlay';
    overlay.innerHTML = `
        <div class="notification-popup" role="alert" aria-live="polite">
            <button type="button" class="notification-popup__close" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="notification-popup__content">
                <div class="notification-popup__icon ${iconTypeClass}">
                    <i class="bi ${iconClass}"></i>
                </div>
                <div class="notification-popup__text">${message}</div>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    currentNotification = overlay;

    // Trigger animation after a brief delay
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            overlay.classList.add('show');
        });
    });

    // Setup event listeners
    const closeBtn = overlay.querySelector('.notification-popup__close');
    closeBtn.addEventListener('click', closeNotification);

    // Close on overlay click
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closeNotification();
        }
    });

    // Close on ESC key
    const handleEscape = function(e) {
        if (e.key === 'Escape') {
            closeNotification();
            document.removeEventListener('keydown', handleEscape);
        }
    };
    document.addEventListener('keydown', handleEscape);

    // Auto-close after 3 seconds
    setTimeout(() => {
        closeNotification();
    }, 3000);
}

function closeNotification() {
    if (!currentNotification) return;

    currentNotification.classList.remove('show');
    setTimeout(() => {
        if (currentNotification && currentNotification.parentNode) {
            currentNotification.remove();
        }
        currentNotification = null;
    }, 300);
}
