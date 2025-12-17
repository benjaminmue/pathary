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
                    <div class="modal-body">
                        <div id="changePasswordAlert"></div>
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" required>
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="newPassword" required minlength="8">
                            <small class="text-muted">At least 8 characters</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirmPassword" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="submitPasswordChange()">Change Password</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
    modal.show();

    document.getElementById('changePasswordModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

async function submitPasswordChange() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const alertDiv = document.getElementById('changePasswordAlert');

    if (newPassword !== confirmPassword) {
        alertDiv.innerHTML = '<div class="alert alert-danger">Passwords do not match.</div>';
        return;
    }

    if (newPassword.length < 8) {
        alertDiv.innerHTML = '<div class="alert alert-danger">Password must be at least 8 characters.</div>';
        return;
    }

    try {
        const response = await fetch(APPLICATION_URL + '/profile/security/password', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({currentPassword, newPassword})
        });

        const data = await response.json();

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
            showAlert('Password changed successfully.', 'success');
            loadSecurityTab();
        } else {
            alertDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
        }
    } catch (error) {
        alertDiv.innerHTML = '<div class="alert alert-danger">Failed to change password.</div>';
    }
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
            bootstrap.Modal.getInstance(document.getElementById('setup2FAModal')).hide();
            showRecoveryCodesModal(data.recoveryCodes);
            loadSecurityTab();
        } else {
            alertDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
        }
    } catch (error) {
        alertDiv.innerHTML = '<div class="alert alert-danger">Failed to verify code.</div>';
    }
}

function showRecoveryCodesModal(codes) {
    const codesHtml = codes.map(code => `<li><code>${code}</code></li>`).join('');
    const modalHtml = `
        <div class="modal fade" id="recoveryCodesModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Save Your Recovery Codes</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <strong>Important:</strong> Save these recovery codes in a safe place. Each code can be used once if you lose access to your authenticator app.
                        </div>
                        <ul class="list-unstyled">
                            ${codesHtml}
                        </ul>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyRecoveryCodes()">
                            <i class="bi bi-clipboard"></i> Copy All
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I've Saved My Codes</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('recoveryCodesModal'));
    modal.show();

    // Store codes globally for copying
    window.currentRecoveryCodes = codes;

    document.getElementById('recoveryCodesModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
        delete window.currentRecoveryCodes;
    });
}

function copyRecoveryCodes() {
    if (window.currentRecoveryCodes) {
        const text = window.currentRecoveryCodes.join('\n');
        navigator.clipboard.writeText(text).then(() => {
            showAlert('Recovery codes copied to clipboard.', 'success');
        });
    }
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

// Show alert in security tab
function showAlert(message, type) {
    const container = document.getElementById('securityTabContent');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    container.insertBefore(alert, container.firstChild);

    setTimeout(() => {
        alert.remove();
    }, 5000);
}
