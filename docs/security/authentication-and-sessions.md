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

**File**: `src/Domain/User/Service/Authentication.php:login()`

The login service verifies credentials, generates authentication tokens, and optionally sets cookies for web clients. It supports both standard logins and "remember me" functionality (token expiration: 1 day vs 10 years). For web clients, it sets the authentication cookie and regenerates the session ID. Returns the authenticated user and token.

## Cookie Configuration

**File**: `src/Domain/User/Service/Authentication.php:setAuthenticationCookieAndNewSession()`

Pathary uses the `id` cookie to store authentication tokens with security flags: `httponly` (prevents XSS token theft), `secure` (auto-detected for HTTPS), `samesite=Lax` (CSRF protection), and site-wide path (`/`).

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

**File**: `src/Domain/User/Service/Authentication.php:setAuthenticationCookieAndNewSession()`

On login, the session is destroyed and restarted with a new session ID (via `SessionWrapper::regenerateId()`) to prevent session fixation attacks. The user ID is stored in the session after regeneration.

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

**File**: `src/Domain/User/Repository/UserRepository.php`

The system looks up tokens by SHA-256 hash for security. For backwards compatibility, it also checks the plain token field during migration periods.

## Two-Factor Authentication (2FA)

Pathary provides comprehensive 2FA with TOTP, recovery codes, and trusted device support.

### Overview

The 2FA system includes:
- **TOTP Authentication** - Compatible with authenticator apps
- **Recovery Codes** - 10 single-use backup codes
- **Trusted Devices** - Skip 2FA for 30 days on trusted devices
- **Security Audit Log** - Track all security events

For complete 2FA documentation, see:
- [Two-Factor Authentication](two-factor-authentication.md) - Detailed guide to all 2FA features

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

The authentication verification flow:
1. Verifies password using `UserApi::isValidPassword()`
2. If trusted device token provided, checks if device is trusted (skips 2FA if valid)
3. If TOTP enabled, requires either authenticator code OR recovery code
4. Verifies TOTP code via `TwoFactorAuthenticationApi::verifyTotpUri()`
5. Verifies recovery code via `RecoveryCodeService::verifyRecoveryCode()` (consumes code on success)
6. Throws exceptions for invalid credentials, missing 2FA codes, or verification failures

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

**File**: `src/Domain/User/Service/Authentication.php:logout()`

The logout process deletes the authentication token from the database, clears the `id` cookie (by setting expiration to past timestamp), and destroys the current session before starting a new one.

## Security Headers

**File**: `public/index.php`

Pathary applies comprehensive security headers to all HTTP responses, including:
- `X-Content-Type-Options: nosniff` - Prevents MIME type sniffing
- `X-Frame-Options: SAMEORIGIN` - Prevents clickjacking attacks
- `Referrer-Policy: strict-origin-when-cross-origin` - Controls referrer information
- `Permissions-Policy` - Disables unnecessary browser features (accelerometer, camera, geolocation, etc.)
- `Content-Security-Policy` - Restricts resource loading to prevent XSS attacks

## Related Pages

- [Two-Factor Authentication](two-factor-authentication.md) - Comprehensive 2FA guide
- [Password Policy and Security](password-policy-and-security.md) - Password requirements
- [Routing and Controllers](../architecture/routing-and-controllers.md) - Route authentication middleware
- [Database](../architecture/database.md) - Token storage schema
