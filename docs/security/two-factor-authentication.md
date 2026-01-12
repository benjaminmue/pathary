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

**Implementation**: `src/HttpController/Web/ProfileSecurityController.php:enableTotp()`

The system generates a TOTP secret and QR code URI using `TwoFactorAuthenticationFactory::createTotp()`, which creates a TOTP instance compatible with RFC 6238 standard authenticator apps.

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

**Implementation**: `src/Domain/User/Service/RecoveryCodeService.php:generateRecoveryCodes()`

The service generates 10 cryptographically secure random codes, normalizes them (removes dashes for consistent hashing), hashes them with bcrypt, and stores the hashes. Returns formatted codes with dashes for display. Existing codes are deleted before generating new ones.

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

**Implementation**: `src/Domain/User/Service/Authentication.php:login()`

The authentication service verifies the recovery code using `RecoveryCodeService::verifyRecoveryCode()`, which normalizes the input, checks it against stored hashes, deletes the code upon successful verification, and logs both success and failure attempts to the security audit log.

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

**Implementation**: `src/Domain/User/Service/TrustedDeviceService.php:createTrustedDevice()`

A secure cookie (`pathary_trusted_device`) is set with httpOnly and SameSite flags. The secure flag is dynamically detected based on HTTPS/X-Forwarded-Proto headers for reverse proxy compatibility. Cookie expires in 30 days. See `src/Util/TrustedDeviceCookie.php` for cookie management.

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
- Chrome on Windows → "Chrome on Windows"
- Safari on iPhone → "Safari on iOS"
- Firefox on macOS → "Firefox on macOS"

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
| `totp_enabled` | User enabled 2FA |
| `totp_disabled` | User disabled 2FA |
| `login_success` | Successful login (with or without 2FA) |
| `login_failed_totp` | Failed 2FA attempt |
| `login_failed_recovery_code` | Failed recovery code attempt |
| `recovery_code_used` | Recovery code used for login |
| `recovery_codes_generated` | New recovery codes generated |
| `trusted_device_added` | Device marked as trusted |
| `trusted_device_removed` | Single trusted device revoked |
| `all_trusted_devices_removed` | All trusted devices revoked |
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

**Implementation**: `src/Domain/User/Service/SecurityAuditService.php`

Events are logged using `SecurityAuditService::log()` with user ID, event type constant, IP address, user agent, and optional metadata array (automatically JSON-encoded). All event type constants are defined in the SecurityAuditService class.

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

**Implementation**: `src/Domain/User/Service/Authentication.php:findUserAndVerifyAuthentication()`

The authentication service checks for a trusted device token cookie and verifies it using `TrustedDeviceService::verifyTrustedDevice()`. If valid and not expired, 2FA is skipped and a login success event is logged with trusted device metadata. Otherwise, TOTP or recovery code verification is required.

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

- [Authentication and Sessions](authentication-and-sessions.md) - Login and session management
- [Password Policy and Security](password-policy-and-security.md) - Password requirements
- [Database](../architecture/database.md) - Security-related table schemas
