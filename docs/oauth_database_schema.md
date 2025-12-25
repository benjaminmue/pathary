# OAuth Email Configuration Database Schema

## Design Decision

Create a dedicated `oauth_email_config` table instead of using `server_setting` because:
1. **MySQL Limitation:** `server_setting.value` is VARCHAR(255), insufficient for encrypted tokens (typically 500+ chars)
2. **Clear Security Boundary:** Separate table makes encrypted data handling explicit
3. **Better Type Safety:** Can use appropriate column types for each field
4. **Audit Trail:** Can track OAuth-specific metadata separately

## Table Schema

### oauth_email_config

**Purpose:** Store OAuth 2.0 configuration and tokens for email sending

#### Columns

| Column | Type (SQLite) | Type (MySQL) | Nullable | Description |
|--------|--------------|--------------|----------|-------------|
| `id` | INTEGER | INT(10) UNSIGNED | NO | Primary key, auto-increment |
| `provider` | TEXT | VARCHAR(50) | NO | OAuth provider: 'gmail' or 'microsoft' |
| `client_id` | TEXT | VARCHAR(255) | NO | Public OAuth client identifier |
| `client_secret_encrypted` | TEXT | TEXT | NO | Encrypted client secret (AES-256-CBC + base64) |
| `client_secret_iv` | TEXT | VARCHAR(255) | NO | Initialization vector for client_secret decryption |
| `tenant_id` | TEXT | VARCHAR(255) | YES | Microsoft only: Azure AD tenant ID |
| `refresh_token_encrypted` | TEXT | TEXT | YES | Encrypted refresh token (AES-256-CBC + base64) |
| `refresh_token_iv` | TEXT | VARCHAR(255) | YES | Initialization vector for refresh_token decryption |
| `from_address` | TEXT | VARCHAR(255) | NO | Email address to send from (must match OAuth account) |
| `scopes` | TEXT | VARCHAR(500) | YES | OAuth scopes granted (space-separated) |
| `token_status` | TEXT | VARCHAR(50) | NO | Status: 'not_connected', 'active', 'expired', 'error' |
| `token_error` | TEXT | TEXT | YES | Last error message from OAuth provider |
| `client_secret_expires_at` | TEXT | DATETIME | YES | When client secret expires (Microsoft only) |
| `connected_at` | TEXT | DATETIME | YES | When OAuth connection was established |
| `last_token_refresh_at` | TEXT | DATETIME | YES | Last time access token was refreshed |
| `created_at` | TEXT | DATETIME | NO | Record creation timestamp |
| `updated_at` | TEXT | DATETIME | NO | Record last update timestamp |

#### Constraints

- **PRIMARY KEY:** `id`
- **UNIQUE:** Only one OAuth configuration allowed (enforce at application layer)
- **CHECK (SQLite 3.37+):** `provider IN ('gmail', 'microsoft')`
- **CHECK (SQLite 3.37+):** `token_status IN ('not_connected', 'active', 'expired', 'error')`

#### Indexes

- INDEX on `provider` (for future multi-config support)
- INDEX on `token_status` (for monitoring)

## Email Authentication Mode

Add to `server_setting` table:
- **Key:** `emailAuthMode`
- **Values:** 'smtp_password' (default) | 'smtp_oauth'
- **Purpose:** Switch between password-based SMTP and OAuth-based SMTP

## Encryption Strategy

### Encryption Algorithm
- **Algorithm:** AES-256-CBC
- **Key Source:** `ENCRYPTION_KEY` environment variable (32 bytes)
- **IV Storage:** Separate column for each encrypted field
- **Format:** `base64_encode(openssl_encrypt($plaintext, 'AES-256-CBC', $key, 0, $iv))`

### Key Management
1. **Production:** Admin must set `ENCRYPTION_KEY` in `.env` or environment
2. **Development:** Generate random key on first use, store in database
3. **Migration:** Warn if key not set, prevent OAuth configuration until set

### Encrypted Fields
- `client_secret_encrypted` + `client_secret_iv`
- `refresh_token_encrypted` + `refresh_token_iv`

### Non-Encrypted Fields (why)
- `client_id` - Public identifier, safe to store plain
- `tenant_id` - Public Azure AD tenant GUID
- `from_address` - Email address, needed for queries
- `provider` - Metadata, not sensitive

## Migration Strategy

### Step 1: Create Table
- Migration: `CreateOAuthEmailConfigTable`
- SQLite and MySQL versions
- No data migration needed (new feature)

### Step 2: Add emailAuthMode Setting
- Use existing `ServerSettings::updateValue()` method
- Default to 'smtp_password' (preserve existing behavior)
- No migration needed (key-value table)

## Sample Data

### Gmail Configuration
```
id: 1
provider: 'gmail'
client_id: '12345-abcdef.apps.googleusercontent.com'
client_secret_encrypted: 'base64encodedencryptedvalue'
client_secret_iv: 'randomiv123456'
tenant_id: NULL
refresh_token_encrypted: 'base64encodedrefreshtoken'
refresh_token_iv: 'randomiv789012'
from_address: 'noreply@example.com'
scopes: 'https://mail.google.com/'
token_status: 'active'
token_error: NULL
client_secret_expires_at: NULL
connected_at: '2025-12-23 10:30:00'
last_token_refresh_at: '2025-12-23 14:15:00'
created_at: '2025-12-23 10:25:00'
updated_at: '2025-12-23 14:15:00'
```

### Microsoft 365 Configuration
```
id: 1
provider: 'microsoft'
client_id: 'abc123-def456-ghi789'
client_secret_encrypted: 'base64encodedencryptedvalue'
client_secret_iv: 'randomiv123456'
tenant_id: 'common' or 'tenant-guid-here'
refresh_token_encrypted: 'base64encodedrefreshtoken'
refresh_token_iv: 'randomiv789012'
from_address: 'smtp@pathary.tv'
scopes: 'offline_access https://outlook.office.com/SMTP.Send'
token_status: 'active'
token_error: NULL
client_secret_expires_at: '2027-12-23 00:00:00'
connected_at: '2025-12-23 10:30:00'
last_token_refresh_at: '2025-12-23 14:15:00'
created_at: '2025-12-23 10:25:00'
updated_at: '2025-12-23 14:15:00'
```

## Security Considerations

### What Gets Encrypted
- ✅ `client_secret` - OAuth client secret (sensitive credential)
- ✅ `refresh_token` - Long-lived token (grants email access)

### What Doesn't Get Encrypted
- ❌ `client_id` - Public identifier, not sensitive
- ❌ `tenant_id` - Public directory identifier
- ❌ `from_address` - Email address, needed for queries/display
- ❌ `scopes` - OAuth scope strings, not sensitive
- ❌ `provider` - Provider name, metadata
- ❌ `token_status` - Status enum, metadata
- ❌ `token_error` - Error messages (may contain hints, but generally safe)

### Access Tokens
- **NOT stored in database** - too short-lived (1 hour)
- Generated on-demand from refresh_token when sending email
- Kept in memory only during email send operation

## Backwards Compatibility

### Existing SMTP Password Users
- No impact - `server_setting` still has all SMTP fields
- `emailAuthMode` defaults to 'smtp_password'
- Existing functionality preserved completely

### Migration Path
1. Existing users continue using password-based SMTP
2. Admin can configure OAuth in new UI tab
3. Admin switches `emailAuthMode` to 'smtp_oauth' when ready
4. Can switch back to password at any time

### Rollback Strategy
If OAuth fails or needs to be disabled:
1. Switch `emailAuthMode` back to 'smtp_password'
2. SMTP password fields still present and working
3. OAuth config remains in database (can reconnect later)

## Future Enhancements

### Multi-Account Support
Current design allows one OAuth config. Future enhancement:
- Add `is_active` boolean to `oauth_email_config`
- Remove uniqueness constraint
- Allow multiple providers (select at send time)

### Token Expiry Monitoring
- Cron job to check `client_secret_expires_at` (Microsoft)
- Email admin 30/7 days before expiry
- Dashboard warning when approaching expiry

### Audit Logging
- Log OAuth connection/disconnection events
- Track failed token refreshes
- Monitor unusual access patterns

### Scope Expansion
- Add `requested_scopes` vs `granted_scopes` columns
- Support scope incremental authorization
- Detect missing scopes and prompt for re-auth

---

**Schema Version:** 1.0
**Last Updated:** 2025-12-23
**Author:** Claude Code (Pathary OAuth Implementation)
