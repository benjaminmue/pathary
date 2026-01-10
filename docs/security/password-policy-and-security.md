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

1. **User Registration** - `/create-user`
2. **Password Change** - `/profile/security/password`
3. **Admin User Creation** - CLI command `bin/console.php user:create`
4. **Profile Updates** - Any password modification

**Files**:
- `src/Domain/User/Service/Validator.php` - Core validation logic
- `src/Domain/User/Exception/PasswordPolicyViolation.php` - Policy violation exception

## Validation Logic

**File**: `src/Domain/User/Service/Validator.php:validatePassword()`

```php
public function validatePassword(string $password) : void
{
    if (strlen($password) < 10) {
        throw PasswordPolicyViolation::create(
            'Password must be at least 10 characters long'
        );
    }

    if (!preg_match('/[A-Z]/', $password)) {
        throw PasswordPolicyViolation::create(
            'Password must contain at least one uppercase letter'
        );
    }

    if (!preg_match('/[a-z]/', $password)) {
        throw PasswordPolicyViolation::create(
            'Password must contain at least one lowercase letter'
        );
    }

    if (!preg_match('/[0-9]/', $password)) {
        throw PasswordPolicyViolation::create(
            'Password must contain at least one number'
        );
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        throw PasswordPolicyViolation::create(
            'Password must contain at least one special character'
        );
    }
}
```

## Frontend Validation

### Real-Time Feedback

Password inputs include real-time validation with visual feedback showing which requirements are met.

**File**: `public/js/profile-security.js`

```javascript
function updatePasswordRequirements(password) {
    const requirements = {
        length: password.length >= 10,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[^A-Za-z0-9]/.test(password)
    };

    // Update UI indicators
    document.getElementById('req-length').classList.toggle('met', requirements.length);
    document.getElementById('req-uppercase').classList.toggle('met', requirements.uppercase);
    // ... etc
}
```

### Visual Indicators

**File**: `templates/page/settings-account-security.html.twig`

Requirements are displayed as a checklist:
```html
<ul class="password-requirements">
    <li id="req-length" class="requirement">
        <i class="bi bi-check-circle"></i> At least 10 characters
    </li>
    <li id="req-uppercase" class="requirement">
        <i class="bi bi-check-circle"></i> One uppercase letter (A-Z)
    </li>
    <li id="req-lowercase" class="requirement">
        <i class="bi bi-check-circle"></i> One lowercase letter (a-z)
    </li>
    <li id="req-number" class="requirement">
        <i class="bi bi-check-circle"></i> One number (0-9)
    </li>
    <li id="req-special" class="requirement">
        <i class="bi bi-check-circle"></i> One special character (!@#$%...)
    </li>
</ul>
```

CSS styling:
```css
.password-requirements .requirement {
    color: var(--bs-secondary);
}

.password-requirements .requirement.met {
    color: var(--bs-success);
}

.password-requirements .requirement.met i {
    color: var(--bs-success);
}
```

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
  --email user@example.com \
  --password "NewSecurePass123!" \
  --name "Username"
```

Password policy is enforced for CLI operations as well.

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

## Failed Login Protection

Pathary includes rate limiting for login attempts:

| Threshold | Lockout Duration |
|-----------|-----------------|
| 5 failed attempts | 15 minutes |

**File**: `src/Domain/User/Service/Authentication.php`

Failed attempts are tracked per email address and reset upon successful login.

## Related Pages

- [Two-Factor Authentication](Two-Factor-Authentication) - TOTP, recovery codes, trusted devices
- [Authentication and Sessions](Authentication-and-Sessions) - Login flow and session management
- [Database](Database) - User table schema

---

[← Back to Wiki Home](Home)
