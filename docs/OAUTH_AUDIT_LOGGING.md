# OAuth Configuration Audit Logging

## Overview

This document describes the security audit logging implementation for OAuth email configuration changes in Pathary.

## Implementation Summary

All OAuth configuration changes are now logged to the `user_security_audit_log` table with actor, timestamp, IP address, user agent, and safe metadata details.

## Event Types

The following OAuth configuration events are logged:

### 1. OAuth Config Created
- **Event Type:** `oauth_config_created`
- **Trigger:** When a new OAuth configuration is saved for the first time
- **Metadata Logged:**
  - `provider` - Provider name (gmail, microsoft)
  - `client_id` - OAuth client ID (safe to log)
  - `from_address` - Email address to send from
  - `tenant_id` - Azure AD tenant ID (Microsoft only)
  - `has_secret_expiry` - Whether client secret has expiry set
- **Never Logged:** `client_secret`

### 2. OAuth Config Updated
- **Event Type:** `oauth_config_updated`
- **Trigger:** When an existing OAuth configuration is modified
- **Metadata Logged:** Same as oauth_config_created
- **Never Logged:** `client_secret`

### 3. OAuth Connected
- **Event Type:** `oauth_connected`
- **Trigger:** When OAuth authorization callback succeeds and refresh token is saved
- **Metadata Logged:**
  - `provider` - Provider name
  - `from_address` - Email address
  - `granted_scopes` - OAuth scopes granted by user
- **Never Logged:** `refresh_token`, `access_token`, `authorization_code`, `state`

### 4. OAuth Callback Failed
- **Event Type:** `oauth_callback_failed`
- **Trigger:** When OAuth authorization callback fails
- **Metadata Logged:**
  - `error` - Error code from provider or 'exception'
  - `error_description` - Sanitized error message
- **Never Logged:** Full stack trace, sensitive query parameters

### 5. OAuth Disconnected
- **Event Type:** `oauth_disconnected`
- **Trigger:** When OAuth connection is disconnected (tokens cleared, config preserved)
- **Metadata Logged:**
  - `provider` - Provider name
  - `from_address` - Email address
- **Never Logged:** Token values

### 6. OAuth Config Deleted
- **Event Type:** `oauth_config_deleted`
- **Trigger:** When OAuth configuration is completely deleted
- **Metadata Logged:**
  - `provider` - Provider name
  - `client_id` - OAuth client ID
  - `from_address` - Email address
- **Never Logged:** `client_secret`, tokens

### 7. Email Auth Mode Changed
- **Event Type:** `oauth_auth_mode_changed`
- **Trigger:** When email authentication mode is switched between `smtp_password` and `smtp_oauth`
- **Metadata Logged:**
  - `old_mode` - Previous auth mode
  - `new_mode` - New auth mode
- **Never Logged:** Passwords, tokens

### 8. OAuth Encryption Key Generated
- **Event Type:** `oauth_encryption_key_generated`
- **Trigger:** When a new AES-256 encryption key is generated for OAuth secrets
- **Metadata Logged:**
  - `key_source` - Where key is stored (database or environment)
  - `key_length` - Length of base64-encoded key
- **Never Logged:** Encryption key value

## Security Guarantees

### What is NEVER Logged:
- ❌ OAuth client secrets (encrypted or plaintext)
- ❌ Refresh tokens
- ❌ Access tokens
- ❌ Authorization codes
- ❌ Encryption key values
- ❌ SMTP passwords
- ❌ Raw callback query strings
- ❌ Full stack traces

### What IS Logged:
- ✅ Provider name (gmail, microsoft)
- ✅ Client ID (public identifier)
- ✅ Tenant ID (Microsoft - public identifier)
- ✅ From email address
- ✅ Granted OAuth scopes
- ✅ Connection status changes
- ✅ Auth mode changes
- ✅ Actor user ID
- ✅ Timestamp
- ✅ IP address
- ✅ User agent (trimmed)

## Database Schema

All events are stored in the `user_security_audit_log` table:

```sql
CREATE TABLE `user_security_audit_log` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `user_id` INTEGER NOT NULL,
    `event_type` TEXT NOT NULL,
    `ip_address` TEXT DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `metadata` TEXT DEFAULT NULL,  -- JSON with safe details only
    `created_at` TEXT NOT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE
)
```

## Implementation Files

### Modified Files:
1. **src/Domain/User/Service/SecurityAuditService.php**
   - Added 8 new event type constants
   - Added human-readable labels for UI display

2. **src/HttpController/Web/OAuthEmailController.php**
   - Injected SecurityAuditService and Authentication
   - Added audit logging to all OAuth actions:
     - saveConfig() - Lines 119-133
     - callback() - Lines 202-213, 238-250, 256-267
     - disconnect() - Lines 300-311
     - deleteConfig() - Lines 355-367
     - generateEncryptionKey() - Lines 420-431
     - updateAuthMode() - Lines 543-554

### Unchanged Files:
- **src/Service/Email/OAuthConfigService.php** - No logging added (to avoid duplicates)
- **src/Service/Email/OAuthTokenService.php** - No logging added (controller handles it)

## Viewing Audit Logs

### User Profile - Security Activity
Admins can view their own OAuth configuration events at:
- URL: `/profile/security`
- Shows last 20 events for the current user
- Includes OAuth config events if the user performed them

### Admin Panel - Events
Admins can view all OAuth configuration events across all users at:
- URL: `/admin/events`
- Filter by event type (select OAuth event types)
- Search by IP address, user agent, or metadata
- View detailed metadata for each event

## Testing Checklist

To verify audit logging is working:

1. **Create OAuth Config**
   - Navigate to Admin → Server Management → Email Settings → OAuth
   - Configure a Gmail or Microsoft provider
   - Check audit log for `oauth_config_created` event
   - Verify metadata contains provider, client_id, from_address
   - Verify client_secret is NOT in metadata

2. **Update OAuth Config**
   - Modify existing OAuth config (change client ID or from address)
   - Check audit log for `oauth_config_updated` event

3. **Connect OAuth**
   - Click "Connect" and complete OAuth authorization flow
   - Check audit log for `oauth_connected` event
   - Verify metadata contains provider and granted_scopes
   - Verify refresh_token and access_token are NOT in metadata

4. **Disconnect OAuth**
   - Click "Disconnect"
   - Check audit log for `oauth_disconnected` event

5. **Delete OAuth Config**
   - Click "Delete Configuration"
   - Check audit log for `oauth_config_deleted` event

6. **Change Auth Mode**
   - Switch between smtp_password and smtp_oauth
   - Check audit log for `oauth_auth_mode_changed` event
   - Verify old_mode and new_mode are logged

7. **Generate Encryption Key**
   - Click "Generate Encryption Key"
   - Check audit log for `oauth_encryption_key_generated` event
   - Verify key value is NOT in metadata

8. **Failed Callback**
   - Trigger a failed OAuth callback (deny consent or use invalid credentials)
   - Check audit log for `oauth_callback_failed` event

## Retention Policy

- Audit logs are retained for 90 days by default
- Configurable via `SecurityAuditService::cleanupOldEvents()`
- No implementation changes required for standard retention

## Compliance

This implementation helps with:
- **GDPR Compliance:** Audit trail of administrative actions
- **Security Audits:** Track who changed OAuth configuration and when
- **Incident Response:** Identify unauthorized changes to email settings
- **Accountability:** Clear record of administrative actions

## Related Issues

- Issue #17: Add security audit logging for OAuth configuration changes
- OAuth Monitoring (Issue #20): OAuth token refresh monitoring

## Notes

- All audit logging occurs AFTER authorization checks pass (admin-only)
- Logging placement: Controller layer (single responsibility)
- No duplicate logs from service layer
- IP address respects reverse proxy forwarded headers (via `$_SERVER['REMOTE_ADDR']`)
- User agent is logged as-is from `$_SERVER['HTTP_USER_AGENT']`
