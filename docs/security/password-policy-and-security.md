# Password Policy and Security

Pathary enforces a comprehensive password policy to ensure account security.

## Password Requirements

All passwords must meet the following criteria:

| Requirement | Value |
|------------|-------|
| **Minimum Length** | 10 characters |
| **Uppercase Letters** | At least 1 (A-Z) |
| **Lowercase Letters** | At least 1 (a-z) |
| **Numbers** | At least 1 (0-9) |
| **Special Characters** | At least 1 (!@#$%^&*()_+-=[]{};\':"\|,.<>/?) |

## Enforcement Points

Password policy is enforced at:

1. **User Registration** - `/admin/users`
2. **Password Change** - `/profile/security/password`
3. **Admin User Creation** - CLI command `bin/console.php user:create`
4. **Profile Updates** - Any password modification

**Files**:
- `src/Domain/User/Service/Validator.php` - Core validation logic
- `src/Domain/User/Exception/PasswordPolicyViolation.php` - Policy violation exception

## Validation Logic

### Password Requirements

All passwords must meet ALL of the following criteria:

- **Minimum length**: 10 characters
- **Uppercase letter**: At least one (A-Z)
- **Lowercase letter**: At least one (a-z)
- **Number**: At least one (0-9)
- **Special character**: At least one (any non-alphanumeric)

**Configuration**: `src/Domain/User/Service/Validator.php`
- `PASSWORD_MIN_LENGTH = 10` (constant)
- `ensurePasswordIsValid()` method enforces all requirements

### Validation Implementation

**Backend**: `Validator::ensurePasswordIsValid()`
- Checks all requirements
- Collects violations into array
- Throws `PasswordPolicyViolation` with all unmet requirements

**Frontend**: `public/js/profile-security.js:validatePasswordPolicy()`
- Provides real-time feedback as user types
- Updates visual indicators for each requirement
- Prevents form submission if policy not met

## Two-Factor Authentication (2FA)

Pathary implements comprehensive 2FA to add an additional security layer beyond passwords.

### Implementation

**Method**: Time-based One-Time Password (TOTP)
- **Standard**: RFC 6238 (compatible with Google Authenticator, Authy, 1Password, etc.)
- **QR Code Setup**: Users scan QR code during enrollment
- **Verification**: 6-digit codes rotate every 30 seconds

**Files**:
- `src/Domain/User/Service/Authentication.php` - Core 2FA authentication logic
- `src/Domain/User/Service/TotpService.php` - TOTP generation and verification
- `src/Domain/User/Service/RecoveryCodeService.php` - Backup code management
- `src/Domain/User/Service/TrustedDeviceService.php` - Trusted device tracking

### Recovery Codes

When 2FA is enabled, 10 single-use recovery codes are automatically generated:

- **Purpose**: Backup access if authenticator app is lost or unavailable
- **Storage**: Bcrypt-hashed in `recovery_codes` table (same security as passwords)
- **Usage**: Single-use only - each code is deleted after successful login
- **Regeneration**: Users can regenerate all codes at any time from Security settings
- **Display**: Shown once during generation with copy/download options

**Important**: Recovery codes should be stored securely (password manager, encrypted backup).

### Trusted Devices

Users can mark devices as trusted to skip 2FA for 30 days:

- **Duration**: 30-day trust period per device
- **Storage**: Cookie-based with secure, httpOnly flags
- **Token**: Cryptographically secure random token stored in `trusted_devices` table
- **Revocation**: Users can remove individual devices or revoke all trusted devices
- **Expiration**: Automatically deleted after 30 days

## Security Activity Logging

All security-related events are logged to the `user_security_audit_log` table and displayed in the user profile.

### Logged Events

**Authentication Events**:
- Login success/failure (password, 2FA, recovery code)
- Logout events
- Rate limit exceeded (when implemented - see Issue #44)

**2FA Events**:
- 2FA enabled/disabled
- Recovery codes generated/used
- Trusted device added/removed

**Password Events**:
- Password changed (by user or admin)

**User Management** (admin only):
- User created/updated/deleted
- Welcome emails sent/failed

**OAuth Email Events** (admin only):
- OAuth configuration changes
- Token refresh warnings/failures
- Authentication mode changes

### Viewing Activity Logs

**User Profile**:
- Location: `Profile → Security Tab → Activity Log`
- Shows: Recent 20 events with timestamp, event type, IP address
- Route: `/profile/security` (embedded in page)

**Admin Panel** (admins only):
- Location: `Admin → Events`
- Route: `/admin/events`
- Features: Filter by event type, user, date range, IP address
- Export: CSV download for compliance/analysis
- Retention: Events older than 90 days are automatically deleted

**Files**:
- `src/Domain/User/Service/SecurityAuditService.php` - Event logging service
- `src/Domain/User/Repository/SecurityAuditRepository.php` - Database operations
- `src/HttpController/Web/ProfileSecurityController.php` - User-facing logs display

For detailed 2FA setup and usage instructions, see [Two-Factor Authentication](two-factor-authentication.md).

## Frontend Validation

### Real-Time Feedback

Password inputs include real-time validation with visual feedback showing which requirements are met.

**Implementation**: `public/js/profile-security.js:validatePasswordPolicy()`
- Tests password against each requirement using regex patterns
- Updates UI indicators (checkmarks) as user types
- Matches the same validation rules as backend
- Prevents form submission if policy not met

### Visual Indicators

**File**: `templates/page/settings-account-security.html.twig`

Requirements are displayed as a checklist with Bootstrap Icons checkmarks that change color when met (gray → green).

## Password Storage

Passwords are hashed using PHP's `password_hash()` with `PASSWORD_DEFAULT`:

```php
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
```

As of PHP 8.0+, `PASSWORD_DEFAULT` uses **bcrypt** with a cost factor of 10.

**Storage**:
- Passwords are never stored in plain text
- Hashes are stored in the `user.password` column as `VARCHAR(255)`
- Password verification uses `password_verify()` with timing-attack resistance

## Changing Your Password

### Via Web Interface

```
Profile → Security Tab → Password section → Change Password
```

**Route**: `POST /profile/security/password`

**File**: `src/HttpController/Web/ProfileSecurityController.php:changePassword()`

Steps:
1. Enter current password
2. Enter new password (must meet policy)
3. Confirm new password
4. Submit form

On success:
- Password is updated in database
- Security audit log entry is created
- Success message is displayed

### Via CLI

Administrators can reset passwords via CLI:

```bash
docker compose exec app php bin/console.php user:create \
  user@example.com \
  "NewSecurePass123!" \
  "Username"
```

Password policy is enforced for CLI operations as well.

> **Note:** A web-based `/init` setup wizard is planned for first-time installation. See [GitHub Issue #45](https://github.com/benjaminmue/pathary/issues/45). The CLI command will remain available for emergency admin creation.

**File**: `src/Command/UserCreate.php`

## Password Hashing Strength

Pathary uses bcrypt with these characteristics:

| Property | Value |
|----------|-------|
| **Algorithm** | bcrypt (Blowfish) |
| **Cost Factor** | 10 (2^10 = 1,024 iterations) |
| **Salt** | Automatically generated (22 characters, base64) |
| **Hash Length** | 60 characters |
| **Format** | `$2y$10$[22-char-salt][31-char-hash]` |

### Upgrade Path

If PHP version is upgraded and `PASSWORD_DEFAULT` changes (e.g., to Argon2), existing passwords will continue to work. On next login, passwords can be re-hashed with the new algorithm using `password_needs_rehash()`.

## Security Best Practices

### For Users

1. **Use a Password Manager** - Generate and store strong, unique passwords
2. **Never Reuse Passwords** - Each service should have its own password
3. **Enable 2FA** - Add an extra layer of security beyond passwords
4. **Change Passwords Periodically** - Especially if you suspect compromise

### For Administrators

1. **Enforce 2FA** - Require 2FA for all users, especially admins
2. **Monitor Audit Logs** - Review security events regularly
3. **Set Strong Example** - Use strong passwords yourself
4. **Educate Users** - Share password best practices with your group

> **Note:** Login rate limiting is not currently implemented but is planned. See [GitHub Issue #44](https://github.com/benjaminmue/pathary/issues/44) for progress. Failed login attempts are logged to the security audit log but not blocked.

## Related Pages

- [Two-Factor Authentication](two-factor-authentication.md) - TOTP, recovery codes, trusted devices
- [Authentication and Sessions](authentication-and-sessions.md) - Login flow and session management
- [Database](../architecture/database.md) - User table schema
