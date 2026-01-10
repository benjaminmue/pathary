# Two-Factor Authentication (2FA)

Pathary provides comprehensive Two-Factor Authentication using Time-based One-Time Passwords (TOTP), along with recovery codes and trusted device management.

## Overview

Pathary's 2FA system includes:
- **TOTP Authentication** - Compatible with authenticator apps (Google Authenticator, Authy, 1Password, etc.)
- **Recovery Codes** - 10 single-use backup codes for account recovery
- **Trusted Devices** - Option to trust devices for 30 days
- **Security Audit Log** - Track all security events

## Enabling 2FA

### Step 1: Navigate to Security Settings

```
Profile → Security Tab → Two-Factor Authentication section
```

### Step 2: Generate QR Code

1. Click "Enable 2FA"
2. A QR code will be displayed
3. Scan the QR code with your authenticator app

**File**: `src/HttpController/Web/ProfileSecurityController.php:enableTotp()`

The system generates a secret key using:
```php
$totpSecret = $this->twoFactorAuthenticationApi->generateTotpSecret();
$totpUri = $this->twoFactorAuthenticationApi->buildTotpUri($user->getName(), $totpSecret);
```

### Step 3: Verify TOTP Code

1. Enter the 6-digit code from your authenticator app
2. Click "Verify and Enable"

**File**: `src/HttpController/Web/ProfileSecurityController.php:verifyAndSaveTotp()`

Upon successful verification:
- TOTP is enabled for your account
- 10 recovery codes are automatically generated
- You'll see a confirmation modal with recovery codes

## Recovery Codes

Recovery codes are single-use backup codes that allow you to log in if you lose access to your authenticator app.

### Initial Generation

When you enable 2FA, 10 recovery codes are automatically generated and displayed in a modal with the following features:
- **Confirmation Required**: You must check "I have saved these codes" AND enter one of the codes to confirm
- **Security**: Codes are hashed using bcrypt before storage
- **Progressive UI**: Shows step-by-step progress through confirmation

**File**: `src/Domain/User/Service/RecoveryCodeService.php:generateRecoveryCodes()`

```php
public function generateRecoveryCodes(int $userId) : array
{
    // Generate 10 random codes
    for ($i = 0; $i < 10; $i++) {
        $code = $this->generateRandomCode();
        $codes[] = $code;

        // Hash and store
        $this->repository->create($userId, password_hash($code, PASSWORD_DEFAULT));
    }

    return $codes;
}
```

### Regenerating Recovery Codes

You can regenerate all recovery codes at any time:

```
Profile → Security Tab → Recovery Codes section → Regenerate Codes
```

**Warning**: Regenerating codes will invalidate all previous codes.

**Route**: `POST /profile/security/recovery-codes/regenerate`

### Using Recovery Codes

When logging in with 2FA enabled:
1. Enter your email and password
2. Click "Use Recovery Code" instead of entering authenticator code
3. Enter one of your recovery codes
4. The code will be consumed and cannot be used again

**File**: `src/Domain/User/Service/Authentication.php:login()`

```php
// Check if recovery code is provided
if ($recoveryCode !== null) {
    if ($this->recoveryCodeService->verifyAndConsumeCode($user->getId(), $recoveryCode) === false) {
        throw InvalidRecoveryCode::create();
    }
}
```

### Recovery Code Storage

Recovery codes are stored in the `recovery_codes` table:

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `user_id` | INT | User reference |
| `code_hash` | VARCHAR(255) | Bcrypt hash of code |
| `used` | BOOLEAN | Whether code has been used |
| `created_at` | TIMESTAMP | Creation time |
| `used_at` | DATETIME | When code was used (nullable) |

**Migration**: `db/migrations/*/20251217120000_CreateRecoveryCodesTable.php`

## Trusted Devices

Trusted devices allow you to bypass 2FA for 30 days on specific devices you mark as trusted.

### Trusting a Device

When logging in with 2FA:
1. Enter your authenticator code or recovery code
2. Check "Trust this device for 30 days"
3. Complete login

**File**: `src/Domain/User/Service/TrustedDeviceService.php:createTrustedDevice()`

A secure cookie is set:
```php
setcookie(
    'trusted_device',
    $deviceToken,
    [
        'expires' => time() + (30 * 24 * 60 * 60),  // 30 days
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ],
);
```

### Device Limits

- Maximum of **10 trusted devices** per user
- Oldest devices are automatically removed when limit is reached
- Device tokens are 256-bit random strings, hashed with `PASSWORD_DEFAULT`

### Managing Trusted Devices

View and manage trusted devices in your security settings:

```
Profile → Security Tab → Trusted Devices section
```

You can see:
- Device name (parsed from user agent)
- Last used date
- Creation date

**Actions**:
- **Revoke Single Device**: `POST /profile/security/trusted-devices/{deviceId}/revoke`
- **Revoke All Devices**: `POST /profile/security/trusted-devices/revoke-all`

**File**: `src/Util/DeviceNameParser.php` parses user agents to friendly names:
- Chrome on Windows → "Chrome (Windows)"
- Safari on iPhone → "Safari (iPhone)"
- Firefox on macOS → "Firefox (macOS)"

### Trusted Device Storage

Trusted devices are stored in the `trusted_devices` table:

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `user_id` | INT | User reference |
| `device_token` | VARCHAR(64) | Random token |
| `device_token_hash` | VARCHAR(255) | Password hash of token |
| `device_name` | VARCHAR(256) | Parsed device name |
| `user_agent` | TEXT | Full user agent string |
| `created_at` | TIMESTAMP | When device was trusted |
| `last_used_at` | DATETIME | Last login from device |
| `expires_at` | DATETIME | When trust expires (30 days) |

**Migration**: `db/migrations/*/20251217120001_CreateTrustedDevicesTable.php`

## Security Audit Log

All security events are logged in the `security_audit_log` table for monitoring and forensics.

### Logged Events

| Event Type | Description |
|------------|-------------|
| `2fa_enabled` | User enabled 2FA |
| `2fa_disabled` | User disabled 2FA |
| `2fa_verified` | Successful 2FA login |
| `2fa_failed` | Failed 2FA attempt |
| `recovery_code_used` | Recovery code used for login |
| `recovery_codes_regenerated` | New recovery codes generated |
| `trusted_device_added` | Device marked as trusted |
| `trusted_device_revoked` | Trusted device revoked |
| `password_changed` | Password changed |

### Viewing Audit Log

```
Profile → Security Tab → Security Activity section
```

**Route**: `GET /profile/security/events`

Events are displayed with:
- Event type
- Timestamp
- Device information
- IP address (if available)

**File**: `src/Domain/User/Service/SecurityAuditService.php`

```php
public function logEvent(
    int $userId,
    string $eventType,
    ?string $deviceName = null,
    ?string $ipAddress = null,
    ?array $metadata = null
) : void {
    $this->repository->create(
        $userId,
        $eventType,
        $deviceName,
        $ipAddress,
        $metadata ? json_encode($metadata) : null
    );
}
```

## Login Flow with 2FA

### Without Trusted Device

```
1. Enter email + password
2. Submit login form
3. If 2FA enabled:
   a. Show 2FA code input
   b. Enter authenticator code OR recovery code
   c. Optionally check "Trust this device"
4. Complete login
```

### With Trusted Device

```
1. Enter email + password
2. Submit login form
3. System checks for valid trusted_device cookie
4. If valid and not expired:
   → Skip 2FA verification
5. Complete login immediately
```

**File**: `src/Domain/User/Service/Authentication.php:findUserAndVerifyAuthentication()`

```php
// Check if device is trusted
if ($this->trustedDeviceService->isDeviceTrusted($user->getId(), $deviceToken)) {
    // Skip 2FA verification
    return $user;
}

// Otherwise, require 2FA
if ($totpUri !== null) {
    if ($userTotpCode === null && $recoveryCode === null) {
        throw MissingTotpCode::create();
    }
    // Verify TOTP or recovery code...
}
```

## Disabling 2FA

To disable 2FA:

```
Profile → Security Tab → Two-Factor Authentication section → Disable 2FA
```

**Route**: `POST /profile/security/totp/disable`

**Effects**:
- TOTP secret is removed
- All recovery codes are deleted
- All trusted devices are revoked
- Audit log entry is created

**File**: `src/HttpController/Web/ProfileSecurityController.php:disableTotp()`

## Template Files

| File | Purpose |
|------|---------|
| `templates/page/login.html.twig` | Login form with 2FA code input and mode switching |
| `templates/public/profile-security.twig` | Security settings page with 2FA management |
| `public/js/login.js` | Login form JavaScript with 2FA handling |
| `public/js/profile-security.js` | Security settings JavaScript with recovery code confirmation |

## API Routes

| Method | Route | Purpose |
|--------|-------|---------|
| POST | `/profile/security/totp/enable` | Generate TOTP QR code |
| POST | `/profile/security/totp/verify` | Verify and save TOTP |
| POST | `/profile/security/totp/disable` | Disable 2FA |
| POST | `/profile/security/recovery-codes/regenerate` | Regenerate recovery codes |
| POST | `/profile/security/trusted-devices/{deviceId}/revoke` | Revoke single device |
| POST | `/profile/security/trusted-devices/revoke-all` | Revoke all devices |
| GET | `/profile/security/events` | Get security audit log |

## Best Practices

1. **Enable 2FA** for all accounts, especially admin accounts
2. **Save recovery codes** in a secure location (password manager, encrypted file)
3. **Trust devices carefully** - only trust your personal devices
4. **Review audit log** periodically for suspicious activity
5. **Regenerate recovery codes** if you suspect they've been compromised
6. **Revoke trusted devices** when selling/disposing of hardware

## Related Pages

- [Authentication and Sessions](Authentication-and-Sessions) - Login and session management
- [Password Policy and Security](Password-Policy-and-Security) - Password requirements
- [Database](Database) - Security-related table schemas

---

[← Back to Wiki Home](Home)
