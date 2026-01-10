# Authentication and Sessions

This page covers how Pathary handles user authentication, sessions, and security.

## Overview

Pathary supports two authentication methods:
1. **Web Sessions** - Cookie-based for browser users
2. **API Tokens** - Header-based for API clients

## Web Login Flow

### 1. Login Page Request

```
GET /login
    ↓
AuthenticationController::renderLoginPage()
    ↓
Renders templates/page/login.html.twig
```

### 2. Login Submission

```
POST /api/authentication/token
    ↓
Api\AuthenticationController::createToken()
    ↓
Authentication::login()
    ↓
Sets cookie + returns token
```

### 3. Authentication Service

**File**: `src/Domain/User/Service/Authentication.php`

```php
public function login(
    string $email,
    string $password,
    bool $rememberMe,
    string $deviceName,
    string $userAgent,
    ?int $userTotpInput = null,
) : array {
    // Verify credentials
    $user = $this->findUserAndVerifyAuthentication($email, $password, $userTotpInput);

    // Create expiration (1 day or 10 years for "remember me")
    $authTokenExpirationDate = $this->createExpirationDate();
    if ($rememberMe === true) {
        $authTokenExpirationDate = $this->createExpirationDate(3650); // 10 years
    }

    // Generate and store token
    $token = $this->setAuthenticationToken($user->getId(), $deviceName, $userAgent, $authTokenExpirationDate);

    // Set cookie for web clients
    if ($deviceName === CreateUserController::PATHARY_WEB_CLIENT) {
        $this->setAuthenticationCookieAndNewSession($user->getId(), $token, $authTokenExpirationDate);
    }

    return ['user' => $user, 'token' => $token];
}
```

## Cookie Configuration

**File**: `src/Domain/User/Service/Authentication.php:247-257`

```php
setcookie(
    'id',                              // Cookie name
    $token,                            // Auth token value
    [
        'expires' => $expirationTimestamp,
        'path' => '/',
        'secure' => $isSecure,         // HTTPS only when available
        'httponly' => true,            // No JavaScript access
        'samesite' => 'Lax',           // CSRF protection
    ],
);
```

### Security Properties

| Property | Value | Purpose |
|----------|-------|---------|
| `httponly` | `true` | Prevents XSS token theft |
| `secure` | Auto-detected | HTTPS-only when behind SSL |
| `samesite` | `Lax` | CSRF protection |
| `path` | `/` | Available site-wide |

## Session Management

### Session Start

**File**: `src/HttpController/Web/Middleware/StartSession.php`

Sessions are started automatically for web requests.

### Session Regeneration

On login, session IDs are regenerated to prevent session fixation:

```php
public function setAuthenticationCookieAndNewSession(int $userId, string $token, DateTime $expirationDate) : void
{
    $this->sessionWrapper->destroy();
    $this->sessionWrapper->start();
    $this->sessionWrapper->regenerateId();  // Prevent session fixation

    // Set cookie and session data...
    $this->sessionWrapper->set('userId', $userId);
}
```

## Token Storage

Auth tokens are stored in the `user_auth_token` table:

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `user_id` | INT | User reference |
| `token` | VARCHAR(64) | Random token |
| `token_hash` | VARCHAR(64) | SHA-256 hash of token |
| `expiration_date` | DATETIME | When token expires |
| `device_name` | VARCHAR(256) | Client identifier |
| `user_agent` | TEXT | Browser/client info |
| `created_at` | TIMESTAMP | Creation time |
| `last_used_at` | DATETIME | Last authentication |

### Token Lookup

The system looks up tokens by hash for security:

```php
// UserRepository.php
$this->dbConnection->fetchAssociative(
    'SELECT * FROM user_auth_token WHERE token_hash = ? OR token = ?',
    [$tokenHash, $token],
);
```

## Two-Factor Authentication (2FA)

Pathary provides comprehensive 2FA with TOTP, recovery codes, and trusted device support.

### Overview

The 2FA system includes:
- **TOTP Authentication** - Compatible with authenticator apps
- **Recovery Codes** - 10 single-use backup codes
- **Trusted Devices** - Skip 2FA for 30 days on trusted devices
- **Security Audit Log** - Track all security events

For complete 2FA documentation, see:
- [Two-Factor Authentication](Two-Factor-Authentication) - Detailed guide to all 2FA features

### Enable 2FA

Users can enable 2FA from their security settings:

```
Profile → Security Tab → Enable 2FA
```

**Routes**:
```
POST /profile/security/totp/enable      # Generate QR code
POST /profile/security/totp/verify      # Verify and save TOTP
POST /profile/security/totp/disable     # Disable 2FA
```

### Login with 2FA

When 2FA is enabled, users must provide either:
1. A 6-digit code from their authenticator app, OR
2. A recovery code

**Optional**: Users can check "Trust this device" to skip 2FA for 30 days.

**File**: `src/Domain/User/Service/Authentication.php:findUserAndVerifyAuthentication()`

```php
public function findUserAndVerifyAuthentication(
    string $email,
    string $password,
    ?int $userTotpCode = null,
    ?string $recoveryCode = null,
    ?string $deviceToken = null,
) : UserEntity {
    // Verify password first
    $user = $this->repository->findUserByEmail($email);
    if ($this->userApi->isValidPassword($user->getId(), $password) === false) {
        throw InvalidPassword::create();
    }

    // Check if device is trusted
    if ($deviceToken !== null) {
        if ($this->trustedDeviceService->isDeviceTrusted($user->getId(), $deviceToken)) {
            return $user;  // Skip 2FA
        }
    }

    // Check if TOTP is required
    $totpUri = $this->userApi->findTotpUri($user->getId());
    if ($totpUri !== null) {
        // Require either TOTP code or recovery code
        if ($userTotpCode === null && $recoveryCode === null) {
            throw MissingTotpCode::create();
        }

        // Verify TOTP code
        if ($userTotpCode !== null) {
            if ($this->twoFactorAuthenticationApi->verifyTotpUri($user->getId(), $userTotpCode) === false) {
                throw InvalidTotpCode::create();
            }
        }

        // Verify recovery code
        if ($recoveryCode !== null) {
            if ($this->recoveryCodeService->verifyAndConsumeCode($user->getId(), $recoveryCode) === false) {
                throw InvalidRecoveryCode::create();
            }
        }
    }

    return $user;
}
```

## API Authentication

### Token Creation

```bash
curl -X POST https://pathary.example.com/api/authentication/token \
  -H "Content-Type: application/json" \
  -H "X-Movary-Client: my-app" \
  -d '{"email":"user@example.com","password":"secret"}'
```

### Using API Token

```bash
curl https://pathary.example.com/api/users/john/history/movies \
  -H "X-Movary-Token: your_token_here"
```

### Required Headers

| Header | Required | Description |
|--------|----------|-------------|
| `X-Movary-Client` | Yes (login) | Client identifier |
| `X-Movary-Token` | Yes (API calls) | Authentication token |

## Reverse Proxy Considerations

When running behind a reverse proxy, ensure these headers are forwarded:

```nginx
proxy_set_header Host $host;
proxy_set_header X-Real-IP $remote_addr;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
proxy_set_header X-Forwarded-Proto $scheme;
```

### HTTPS Detection

The application detects HTTPS from:
1. `$_SERVER['HTTPS']` set to `on`
2. `X-Forwarded-Proto: https` header

This affects:
- Cookie `secure` flag
- Generated URLs

## Logout

### Web Logout

```
POST /api/authentication/token (DELETE method)
```

**File**: `src/Domain/User/Service/Authentication.php:209-235`

```php
public function logout() : void
{
    $token = filter_input(INPUT_COOKIE, 'id');

    if ($token !== '') {
        // Delete token from database
        $this->deleteToken($token);

        // Clear cookie
        setcookie('id', '', [
            'expires' => 1,  // Past timestamp
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    // Destroy and restart session
    $this->sessionWrapper->destroy();
    $this->sessionWrapper->start();
}
```

## Security Headers

**File**: `public/index.php:13-20`

Applied to all responses:

```php
$securityHeaders = [
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'SAMEORIGIN',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'accelerometer=(), camera=(), ...',
    'Content-Security-Policy' => "default-src 'self'; ...",
];
```

## Related Pages

- [Two-Factor Authentication](Two-Factor-Authentication) - Comprehensive 2FA guide
- [Password Policy and Security](Password-Policy-and-Security) - Password requirements
- [Routing and Controllers](Routing-and-Controllers) - Route authentication middleware
- [Database](Database) - Token storage schema
- [Deployment](Deployment) - Reverse proxy configuration

---

[← Back to Wiki Home](Home)
