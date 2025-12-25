# OAuth SMTP Email Implementation Design

## Research Summary

This document outlines the technical approach for implementing OAuth2-based SMTP authentication alongside the existing password-based SMTP configuration in Pathary.

**Date:** 2025-12-23
**Status:** Design Phase - Research Completed

---

## 1. Selected OAuth Libraries

### Base OAuth2 Framework
- **Package:** `league/oauth2-client`
- **Version:** 2.9.0 (latest as of Nov 2025)
- **License:** MIT
- **Installs:** Industry standard, widely adopted
- **Purpose:** Core OAuth2 client framework for PHP

### Gmail Provider
- **Package:** `league/oauth2-google`
- **Version:** 4.1.0 (latest as of Dec 2025)
- **License:** MIT
- **Installs:** 19+ million
- **Maintenance:** Actively maintained by The PHP League
- **Requirements:** PHP ^7.3 || ^8.0
- **Repository:** https://github.com/thephpleague/oauth2-google

### Microsoft 365 Provider
- **Package:** `thenetworg/oauth2-azure`
- **Version:** Latest (updated Dec 22, 2025)
- **License:** MIT
- **Maintenance:** Actively maintained, most recent updates
- **Requirements:** PHP ^7.3 || ^8.0
- **Features:**
  - Comprehensive Azure AD support
  - Microsoft Graph integration
  - B2C support
  - On-behalf-of token flows
- **Repository:** https://github.com/TheNetworg/oauth2-azure
- **Alternative:** `greew/oauth2-azure-provider` (v2.0.0, Oct 2024) - simpler, lighter option

**Decision:** Use `thenetworg/oauth2-azure` for better feature coverage and more recent maintenance.

---

## 2. PHPMailer XOAUTH2 Support

### Native Support
PHPMailer provides native XOAUTH2 support via:
- `AuthType = 'XOAUTH2'`
- `setOAuth()` method accepting OAuth token provider
- Built-in refresh token handling

### Official Examples
PHPMailer repository includes production-ready examples:
- `examples/gmail_xoauth.phps` - Gmail implementation
- `examples/azure_xoauth2.phps` - Microsoft 365 implementation
- Wiki guides with setup instructions

### Configuration Patterns

**Gmail SMTP:**
```php
$mail->Host = 'smtp.gmail.com';
$mail->Port = 465;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$mail->AuthType = 'XOAUTH2';
```

**Microsoft 365 SMTP:**
```php
$mail->Host = 'smtp.office365.com';
$mail->Port = 587;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->AuthType = 'XOAUTH2';
```

---

## 3. OAuth Flow Architecture

### Authorization Flow (Standard OAuth 2.0)
1. **Configuration:** Admin configures provider, client ID, client secret, tenant ID (M365)
2. **Authorization:** User clicks "Connect" → redirect to provider consent screen
3. **Callback:** Provider redirects back with authorization code
4. **Token Exchange:** Exchange code for access token + refresh token
5. **Storage:** Securely store refresh token (encrypted) in database
6. **Sending:** Use refresh token to get fresh access token for SMTP AUTH

### Token Lifecycle
- **Access Token:** Short-lived (1 hour typical), used for SMTP authentication
- **Refresh Token:** Long-lived, used to obtain new access tokens
- **Expiration Handling:** Automatically refresh expired access tokens before sending

### Security Considerations
- **State Parameter:** Prevent CSRF attacks on OAuth callback
- **Redirect URI Validation:** Strict validation against configured APPLICATION_URL
- **Encryption at Rest:** Client secrets and refresh tokens encrypted in database
- **No Client-Side Storage:** All tokens server-side only (no localStorage/cookies)
- **No Logging:** Never log tokens, client secrets, or access tokens

---

## 4. Required Scopes and Permissions

### Gmail
- **API:** Gmail API must be enabled in Google Cloud Console
- **Scopes:** Implicit via consent screen (typically `https://mail.google.com/` for full Gmail access)
- **App Type:** Web application
- **Consent Screen:** Required - configure with app name and email

### Microsoft 365
- **API:** Microsoft Graph API
- **Required Scopes:**
  - `offline_access` - Obtain refresh token
  - `SMTP.Send` - Send email via SMTP
- **App Type:** Web application
- **Tenant:** Can be single-tenant or multi-tenant
- **Admin Consent:** May be required depending on tenant configuration

---

## 5. Provider-Specific Configuration

### Gmail Setup Requirements
1. Create project in Google Cloud Console
2. Enable Gmail API
3. Configure OAuth consent screen
4. Create OAuth 2.0 credentials (Web application)
5. Set authorized redirect URI to `{APPLICATION_URL}/admin/server/email/oauth/callback`
6. Obtain Client ID and Client Secret

### Microsoft 365 Setup Requirements
1. Register app in Azure Portal (Azure Active Directory)
2. Record Application (client) ID and Directory (tenant) ID
3. Create client secret (expires 3-24 months - track expiration!)
4. Set API permissions (Microsoft Graph: `offline_access`, `SMTP.Send`)
5. Configure redirect URI to `{APPLICATION_URL}/admin/server/email/oauth/callback`
6. May require admin consent depending on tenant policies

**Critical Note:** Microsoft client secrets expire! Implementation must warn admins and support renewal flow.

---

## 6. Database Schema Design

### New Fields Required (server_setting table)
- `email_auth_mode` - ENUM('smtp_password', 'smtp_oauth') - Default: 'smtp_password'
- `oauth_provider` - VARCHAR(50) - Values: 'gmail', 'microsoft'
- `oauth_client_id` - VARCHAR(255) - Public client identifier
- `oauth_client_secret` - TEXT - Encrypted client secret
- `oauth_tenant_id` - VARCHAR(255) - Microsoft only (Azure AD tenant)
- `oauth_refresh_token` - TEXT - Encrypted refresh token
- `oauth_token_status` - ENUM('not_configured', 'active', 'expired', 'error') - Default: 'not_configured'
- `oauth_token_expires_at` - DATETIME - When client secret expires (M365)
- `oauth_last_error` - TEXT - Last OAuth error for diagnostics

### Encryption Strategy
- Encrypt `oauth_client_secret` and `oauth_refresh_token` before storage
- Use `ENCRYPTION_KEY` environment variable (new requirement)
- Fallback: Generate and store key in database on first run (with warning)
- Use AES-256-CBC or equivalent secure symmetric encryption
- Store initialization vector (IV) with encrypted data

---

## 7. Implementation Components

### Backend Services
1. **EncryptionService** - Encrypt/decrypt sensitive OAuth data
2. **OAuthConfigService** - Manage OAuth provider configuration
3. **OAuthTokenService** - Handle token acquisition, refresh, validation
4. **EmailSenderFactory** - Choose SMTP password vs OAuth based on mode

### HTTP Controllers
1. **AdminController** - Render tabbed email settings UI
2. **OAuthController** - Handle authorization flow:
   - `GET /admin/server/email/oauth/authorize` - Redirect to provider
   - `GET /admin/server/email/oauth/callback` - Handle provider callback
   - `POST /admin/server/email/oauth/save` - Save OAuth configuration
   - `POST /admin/server/email/oauth/disconnect` - Revoke and clear tokens
   - `POST /admin/server/email/test` - Test email (accepts mode: smtp/oauth)

### UI Components
- **Tabbed Interface:** Bootstrap 5 tabs for SMTP vs OAuth
- **Provider Selection:** Dropdown (Gmail, Microsoft 365)
- **Connection Status:** Badge showing active/expired/error state
- **Inline Guidance:** Helper text for scopes, redirect URI, setup steps
- **Warning Banners:** Alert if APPLICATION_URL missing or not HTTPS

---

## 8. Error Handling

### OAuth-Specific Errors
- **Connection Errors:** DNS, TCP, TLS failures
- **Auth Errors:** Invalid client credentials, expired secret
- **Token Errors:** Invalid grant, expired refresh token
- **Consent Errors:** User denied permission, admin consent required
- **Scope Errors:** Missing required scopes

### Error Messages
Provide actionable guidance for each error type:
- Link to Azure Portal for M365 errors
- Link to Google Cloud Console for Gmail errors
- Specific instructions for common issues (expired secret, missing scope, etc.)

### Token Refresh Failures
- Log error to `oauth_last_error` field
- Set `oauth_token_status` to 'error'
- Display error in admin UI with reconnect button
- Fallback to SMTP password if configured (optional)

---

## 9. Migration and Backwards Compatibility

### Safe Migration
1. Add new database fields with defaults (existing setups unaffected)
2. Default `email_auth_mode` to 'smtp_password' (preserve current behavior)
3. Existing SMTP password configs continue working unchanged
4. No breaking changes to existing EmailService API

### Encryption Key Warning
If `ENCRYPTION_KEY` not set on first OAuth save attempt:
- Display prominent warning in UI
- Generate random key and store in database
- Advise user to set env var for production
- Prevent OAuth save until encryption configured

---

## 10. Testing Strategy

### Unit Tests
- EncryptionService encrypt/decrypt
- OAuth state generation and validation
- Token refresh logic with mocked providers
- Email mode selection logic

### Integration Tests
- OAuth authorization flow with test credentials
- Token storage and retrieval (encrypted)
- EmailService XOAUTH2 configuration
- Fallback behavior on token refresh failure

### Manual Verification
1. Configure Gmail OAuth - verify full flow works
2. Configure M365 OAuth - verify full flow works
3. Send test email via OAuth
4. Verify token refresh after expiration
5. Test error handling (invalid credentials, expired secret)
6. Verify settings persist correctly
7. Verify secrets never exposed in responses/logs

---

## 11. Security Checklist

- [ ] Client secrets encrypted at rest (AES-256)
- [ ] Refresh tokens encrypted at rest (AES-256)
- [ ] No tokens in localStorage or client-side cookies
- [ ] OAuth state parameter prevents CSRF
- [ ] Redirect URI strictly validated
- [ ] No secrets logged (use [REDACTED] in logs)
- [ ] HTTPS required for OAuth callback (warn if HTTP)
- [ ] Token refresh errors logged securely
- [ ] Access tokens never persisted (ephemeral, in-memory only)
- [ ] CSRF protection on all OAuth endpoints

---

## 12. User Experience Flow

### Initial Setup (Admin)
1. Navigate to Admin → Server Management → Email Settings
2. Switch to "OAuth" tab
3. Select provider (Gmail or Microsoft 365)
4. Enter Client ID, Client Secret, Tenant ID (M365 only)
5. Copy redirect URI from UI (auto-generated from APPLICATION_URL)
6. Configure in provider console
7. Click "Connect" button
8. Redirected to provider consent screen
9. Authorize app
10. Redirected back to Pathary with success message
11. Status badge shows "Active"
12. Send test email to verify

### Ongoing Use
- Tokens refresh automatically when sending email
- No user intervention needed unless:
  - Refresh token expires (M365 client secret expiry)
  - User revokes app permission
  - Configuration error occurs
- Admin sees status badge and any error messages in UI

### Troubleshooting
- "Run Diagnostics" button tests OAuth configuration
- Detailed error messages with links to provider docs
- "Disconnect and Reconnect" option to fix token issues
- Ability to switch back to SMTP password if needed

---

## 13. Known Limitations

### Microsoft 365
- **Client Secret Expiry:** Maximum 24 months - admin must renew
- **Refresh Token Expiry:** Can be revoked by user or admin policy
- **Tenant Configuration:** Some tenants require admin consent for SMTP.Send scope
- **Hybrid Auth:** Cannot mix password and OAuth - must choose one

### Gmail
- **API Quota:** Subject to Google API quotas and rate limits
- **App Verification:** Unverified apps have user consent screen warnings
- **Refresh Token Revocation:** User can revoke at any time via Google Account settings

### General
- **Reverse Proxy:** Requires correct APPLICATION_URL and X-Forwarded-Proto
- **HTTPS Required:** OAuth providers require HTTPS redirect URIs (local dev: use tunnel or accept warning)
- **Single Account:** One OAuth config per Pathary instance (no multi-account support)

---

## 14. Implementation Timeline

### Phase 1: Foundation (Current)
- ✅ Research and library selection
- ✅ Design documentation
- ⏳ Database schema design
- ⏳ Encryption service implementation

### Phase 2: Backend Core
- OAuth token service
- Provider-specific configuration handling
- Database migration
- Settings persistence

### Phase 3: OAuth Flow
- Authorization endpoint
- Callback handler
- State validation
- Token exchange and storage

### Phase 4: Email Integration
- Update EmailService for XOAUTH2
- Email sender mode selection
- Token refresh automation
- Error handling and logging

### Phase 5: UI
- Tabbed interface implementation
- Provider configuration forms
- Status indicators and error display
- Test email functionality

### Phase 6: Testing and Documentation
- Unit and integration tests
- Manual verification
- User documentation (Wiki)
- Admin setup guides

---

## 15. References

### Official Documentation
- [PHPMailer XOAUTH2 Wiki](https://github.com/PHPMailer/PHPMailer/wiki/Using-Gmail-with-XOAUTH2)
- [PHPMailer Azure Setup Guide](https://github.com/PHPMailer/PHPMailer/wiki/Microsoft-Azure-and-XOAUTH2-setup-guide)
- [league/oauth2-client Documentation](https://oauth2-client.thephpleague.com/)
- [Google OAuth Documentation](https://developers.google.com/identity/protocols/oauth2)
- [Microsoft Identity Platform](https://docs.microsoft.com/en-us/azure/active-directory/develop/)

### Code Examples
- [PHPMailer Gmail Example](https://github.com/PHPMailer/PHPMailer/blob/master/examples/gmail_xoauth.phps)
- [PHPMailer Azure Example](https://github.com/PHPMailer/PHPMailer/blob/master/examples/azure_xoauth2.phps)

### Package Repositories
- [league/oauth2-google](https://github.com/thephpleague/oauth2-google)
- [thenetworg/oauth2-azure](https://github.com/TheNetworg/oauth2-azure)
- [league/oauth2-client](https://github.com/thephpleague/oauth2-client)

---

## 16. Decision Log

### Why league/oauth2-client?
- Industry standard, 50M+ downloads
- Maintained by The PHP League (trusted organization)
- Extensive provider ecosystem
- PHPMailer examples use this framework

### Why thenetworg/oauth2-azure over greew/oauth2-azure-provider?
- More recent updates (Dec 22, 2025 vs Oct 2024)
- Richer feature set (Graph integration, B2C support)
- Better documentation and examples
- Active community and issue resolution

### Why server-side encryption over external key management?
- Simpler deployment (no external KMS dependency)
- Acceptable for self-hosted use case
- Can be upgraded to KMS later if needed
- Balance between security and operational complexity

### Why tabbed UI over separate pages?
- Better UX - quick switching between modes
- Visual clarity of active configuration
- Reduces navigation overhead
- Follows modern admin panel patterns

---

**End of Design Document**
