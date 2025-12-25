# OAuth Email Authentication Setup

## Why OAuth for Email?

### The Problem with Password-Based SMTP

Traditional SMTP authentication uses username and password credentials. However, modern email providers are phasing out this approach:

**Microsoft 365:**
- Basic authentication disabled by default since October 2022
- Requires either App Passwords (often disabled) or OAuth 2.0
- Multi-factor authentication (MFA) blocks traditional SMTP password auth

**Gmail:**
- "Less secure app access" disabled for all Google Workspace accounts
- App Passwords require 2-Step Verification enabled
- OAuth 2.0 is the recommended secure authentication method

### Benefits of OAuth 2.0

1. **Security:** No passwords stored or transmitted - uses short-lived access tokens
2. **MFA Compatible:** Works with multi-factor authentication enabled
3. **Granular Permissions:** Only requests specific scopes needed (SMTP.Send)
4. **Revocable:** Users can revoke access without changing passwords
5. **Future-Proof:** Industry standard authentication method

## Implementation Overview

Pathary implements OAuth 2.0 for email sending using industry-standard PHP libraries and following official provider documentation.

### Libraries Used

| Package | Version | License | Purpose | Maintenance |
|---------|---------|---------|---------|-------------|
| `league/oauth2-client` | 2.9.0+ | MIT | Core OAuth 2.0 framework | Active (50M+ downloads) |
| `league/oauth2-google` | 4.1.0+ | MIT | Gmail provider | Active (19M+ downloads) |
| `thenetworg/oauth2-azure` | Latest | MIT | Microsoft 365 provider | Active (updated Dec 2025) |

**Why These Libraries?**

- **PHPMailer Compatible:** PHPMailer's official examples use `league/oauth2-client`
- **Battle-Tested:** Combined 70M+ downloads, used in production by thousands of projects
- **Actively Maintained:** All packages updated within last 3 months
- **MIT Licensed:** Compatible with Pathary's open-source requirements
- **Well Documented:** Comprehensive documentation and working examples

## Official Documentation Sources

### PHPMailer OAuth Integration

**Primary Resource:** [PHPMailer GitHub Repository](https://github.com/PHPMailer/PHPMailer)
- **Gmail Setup Guide:** [Using Gmail with XOAUTH2](https://github.com/PHPMailer/PHPMailer/wiki/Using-Gmail-with-XOAUTH2)
- **Microsoft Setup Guide:** [Microsoft Azure and XOAUTH2 setup guide](https://github.com/PHPMailer/PHPMailer/wiki/Microsoft-Azure-and-XOAUTH2-setup-guide)
- **Gmail Example Code:** [gmail_xoauth.phps](https://github.com/PHPMailer/PHPMailer/blob/master/examples/gmail_xoauth.phps)
- **Azure Example Code:** [azure_xoauth2.phps](https://github.com/PHPMailer/PHPMailer/blob/master/examples/azure_xoauth2.phps)

**Key Insights:**
- PHPMailer natively supports XOAUTH2 via `AuthType = 'XOAUTH2'`
- Uses `setOAuth()` method to inject OAuth provider
- Automatically handles token refresh during send operations

### OAuth Library Documentation

**league/oauth2-client (Base Framework):**
- **GitHub:** [thephpleague/oauth2-client](https://github.com/thephpleague/oauth2-client)
- **Packagist:** [league/oauth2-client](https://packagist.org/packages/league/oauth2-client)
- **Documentation:** [oauth2-client.thephpleague.com](https://oauth2-client.thephpleague.com/)
- **Third-Party Providers:** [Provider List](https://oauth2-client.thephpleague.com/providers/thirdparty/)

**league/oauth2-google (Gmail):**
- **GitHub:** [thephpleague/oauth2-google](https://github.com/thephpleague/oauth2-google)
- **Packagist:** [league/oauth2-google](https://packagist.org/packages/league/oauth2-google)
- **Latest Version:** 4.1.0 (December 15, 2025)
- **Guide:** [Complete Package Guide](https://generalistprogrammer.com/tutorials/league-oauth2-google-composer-package-guide)

**thenetworg/oauth2-azure (Microsoft 365):**
- **GitHub:** [TheNetworg/oauth2-azure](https://github.com/TheNetworg/oauth2-azure)
- **Packagist:** [thenetworg/oauth2-azure](https://packagist.org/packages/thenetworg/oauth2-azure)
- **License:** [MIT License](https://github.com/TheNetworg/oauth2-azure/blob/master/LICENSE.md)
- **Latest Update:** December 22, 2025

**Alternative (Lighter Option):**
- **greew/oauth2-azure-provider:** [GitHub](https://github.com/greew/oauth2-azure-provider)
- **Version:** 2.0.0 (October 2024)
- **Note:** We chose `thenetworg/oauth2-azure` for better feature coverage and more recent updates

### Provider Documentation

**Google Cloud Console:**
- **Developer Console:** [console.cloud.google.com](https://console.cloud.google.com/)
- **OAuth Documentation:** [Google Identity Platform](https://developers.google.com/identity/protocols/oauth2)
- **Gmail API:** [Enable Gmail API](https://developers.google.com/gmail/api/guides)
- **Scopes:** `https://mail.google.com/` (full Gmail access including SMTP)

**Microsoft Azure Portal:**
- **Portal:** [portal.azure.com](https://portal.azure.com/)
- **Identity Platform:** [Microsoft Identity Platform Documentation](https://docs.microsoft.com/en-us/azure/active-directory/develop/)
- **SMTP AUTH:** [Authenticate with OAuth](https://learn.microsoft.com/en-us/exchange/client-developer/legacy-protocols/how-to-authenticate-an-imap-pop-smtp-application-by-using-oauth)
- **Required Scopes:**
  - `offline_access` - Obtain refresh token
  - `https://outlook.office.com/SMTP.Send` - Send email via SMTP

### Troubleshooting Resources

**Microsoft 365 SMTP Issues:**
- **SMTP AUTH Disabled:** [How to configure an app in Azure Portal for Office 365](https://learn.microsoft.com/en-us/answers/questions/1030736/how-to-configure-an-app-(phpmailer)-in-azure-porta)
- **OAuth and Microsoft Discussion:** [PHPMailer Discussion #2747](https://github.com/PHPMailer/PHPMailer/discussions/2747)
- **Documentation Improvement Request:** [Issue #2788](https://github.com/PHPMailer/PHPMailer/issues/2788)

**Gmail XOAUTH2:**
- **Phppot Tutorial:** [Sending email using PhpMailer with Gmail XOAUTH2](https://phppot.com/php/sending-email-using-phpmailer-with-gmail-xoauth2/)
- **PHPMailer Discussion:** [XOAUTH2 with Gmail Discussion #2891](https://github.com/PHPMailer/PHPMailer/discussions/2891)

**General SMTP OAuth:**
- **PHPMailer Office 365:** [Tutorial with Code Snippets [2025]](https://mailtrap.io/blog/phpmailer-office-365/)
- **PHPMailer Gmail:** [Tutorial with Code Snippets [2025]](https://mailtrap.io/blog/phpmailer-gmail/)

## Architecture Decisions

### Why Separate oauth_email_config Table?

Instead of using the existing `server_setting` key-value table, we created a dedicated `oauth_email_config` table:

**Reasons:**
1. **MySQL Limitation:** `server_setting.value` is VARCHAR(255), insufficient for encrypted tokens (500+ chars)
2. **Type Safety:** Proper column types for datetime, text, etc.
3. **Security Boundary:** Clear separation of encrypted vs non-encrypted data
4. **Auditability:** Dedicated table makes security audits easier
5. **Extensibility:** Room for future features (multi-account, token monitoring)

### Why AES-256-CBC Encryption?

**Algorithm Choice:**
- **AES-256:** Industry standard, NIST approved, unbroken
- **CBC Mode:** Secure with random IVs, widely supported
- **Key Length:** 256 bits (32 bytes) provides maximum security
- **IV Storage:** Random 128-bit IV per encryption operation

**Alternative Considered:**
- **AES-256-GCM:** More secure (authenticated encryption) but less portable
- **Decision:** CBC is sufficient for our threat model and more widely supported

### Why Environment Variable for Encryption Key?

**Production:** Key in `ENCRYPTION_KEY` environment variable
- Allows key rotation without code changes
- Never committed to git
- Can use external secret management (Docker secrets, AWS Secrets Manager)

**Development:** Fallback to database-stored key
- Convenience for local development
- Warning displayed to use env var for production
- Auto-generated on first OAuth configuration attempt

### Why Single OAuth Configuration?

Current design allows one OAuth config per Pathary instance:

**Rationale:**
- Simpler UI and code (no account selection)
- Matches typical self-hosted use case (one outbound email account)
- Can be extended to multi-account in future if needed

**Future Enhancement Path:**
- Add `is_active` boolean to `oauth_email_config`
- Remove uniqueness constraint (allow multiple rows)
- UI dropdown to select active provider

## Security Considerations

### What Gets Encrypted

✅ **Client Secret** - OAuth app credential, highly sensitive
✅ **Refresh Token** - Long-lived token granting email access

### What Doesn't Get Encrypted

❌ **Client ID** - Public identifier, safe to store plaintext
❌ **Tenant ID** - Public Azure AD directory ID
❌ **From Address** - Email address, needed for queries/display
❌ **Scopes** - OAuth scope strings, not sensitive
❌ **Provider** - Provider name (gmail/microsoft), metadata

### Never Stored

🚫 **Access Tokens** - Generated on-demand, kept in memory only (1 hour lifetime)
🚫 **Authorization Codes** - Immediately exchanged for tokens, never persisted
🚫 **User Passwords** - OAuth eliminates password storage entirely

### Logging and Exposure

- OAuth tokens never logged (even in debug mode)
- Client secrets shown as `[REDACTED]` in logs
- Error messages sanitized to avoid leaking credentials
- Admin UI never displays decrypted values (show "Configured" badge only)

## Token Lifecycle

### Authorization Flow

```
1. Admin configures OAuth (client ID, secret, tenant)
   ↓
2. Admin clicks "Connect" button
   ↓
3. Redirect to provider consent screen
   ↓
4. User authorizes app (grants SMTP.Send scope)
   ↓
5. Provider redirects back with authorization code
   ↓
6. Exchange code for access token + refresh token
   ↓
7. Store refresh token (encrypted) in database
   ↓
8. Status: "Connected" (active)
```

### Sending Email

```
1. User triggers email send (test email, password reset, etc.)
   ↓
2. Check email auth mode: 'smtp_oauth'
   ↓
3. Load OAuth config from database
   ↓
4. Decrypt refresh token
   ↓
5. Request fresh access token from provider
   ↓
6. Configure PHPMailer with XOAUTH2
   ↓
7. Send email using access token
   ↓
8. Discard access token (in-memory only)
```

### Token Refresh

- **Access Token:** 1 hour lifetime, refreshed before each send
- **Refresh Token:** Long-lived (weeks/months), stored encrypted
- **Microsoft Client Secret:** Expires 3-24 months (admin-configured)

**Monitoring:**
- Dashboard shows "Expires in X days" for Microsoft secrets
- Email notifications 30/7 days before expiry (future enhancement)

## Provider-Specific Notes

### Gmail Setup

**Requirements:**
1. Google Cloud Console project
2. OAuth consent screen configured
3. Gmail API enabled
4. OAuth 2.0 credentials (Web application type)

**Redirect URI:**
```
{APPLICATION_URL}/admin/server/email/oauth/callback
```
Example: `https://pathary.tv/admin/server/email/oauth/callback`

**Common Issues:**
- Unverified apps show warning on consent screen (cosmetic, works fine)
- API quotas apply (1 billion quota units/day, more than sufficient)
- Users can revoke access via Google Account settings

### Microsoft 365 Setup

**Requirements:**
1. Azure Portal app registration
2. Client secret created (track expiration!)
3. API permissions granted (offline_access + SMTP.Send)
4. Redirect URI configured

**Tenant ID:**
- `common` - Multi-tenant (any Microsoft account)
- `organizations` - Work/school accounts only
- `{tenant-guid}` - Single specific tenant

**Common Issues:**
- Client secrets expire (3-24 months) - must renew
- Admin consent may be required for `SMTP.Send` scope (tenant policy)
- SMTP AUTH must be enabled at tenant level (Exchange Admin Center)

## Comparison: Password vs OAuth

| Aspect | SMTP Password | OAuth 2.0 |
|--------|---------------|-----------|
| **Security** | Password stored in database | Only tokens stored (encrypted) |
| **MFA Support** | Requires App Passwords | Works natively with MFA |
| **Granular Permissions** | Full account access | Only SMTP.Send scope |
| **Revocation** | Must change password | Revoke via provider settings |
| **Expiration** | Password doesn't expire | Tokens auto-refresh |
| **Setup Complexity** | Simple (username/password) | Complex (OAuth flow) |
| **M365 Compatibility** | Often disabled by default | Recommended method |
| **Gmail Compatibility** | Requires less secure apps | Google's preferred method |

**Recommendation:** Use OAuth for production deployments with MFA-enabled accounts. Keep password-based SMTP as fallback for simple setups.

## Maintenance and Updates

### Keeping Dependencies Updated

```bash
# Check for updates
composer outdated

# Update OAuth packages
composer update league/oauth2-client league/oauth2-google thenetworg/oauth2-azure

# Test after updates
composer test
```

### Monitoring Token Health

**Dashboard Indicators (Planned):**
- ✅ Connected (green badge)
- ⚠️ Expiring Soon (yellow badge, <30 days)
- ❌ Expired (red badge)
- ⚠️ Error (red badge with error message)

**Admin Actions:**
- Reconnect button (re-authorize)
- Disconnect button (clear tokens)
- Delete configuration
- View last refresh timestamp

### Security Audits

**Regular Checks:**
- Review `oauth_email_config` table for old/unused configs
- Monitor `token_status` field for persistent errors
- Check `client_secret_expires_at` for upcoming expirations (Microsoft)
- Verify `ENCRYPTION_KEY` is set in production (not in database)

## Migration Path

### From SMTP Password to OAuth

1. ✅ Configure OAuth in admin panel (leaves SMTP password intact)
2. ✅ Test OAuth connection (send test email)
3. ✅ Switch `emailAuthMode` to 'smtp_oauth' when ready
4. ✅ Keep SMTP password configured as fallback

### Rollback if Needed

1. Switch `emailAuthMode` back to 'smtp_password'
2. SMTP password settings still present and working
3. OAuth config remains in database (can reconnect later)

## References and Further Reading

### Official Specifications
- **OAuth 2.0 RFC:** [RFC 6749](https://datatracker.ietf.org/doc/html/rfc6749)
- **OAuth SASL Mechanism:** [RFC 7628](https://datatracker.ietf.org/doc/html/rfc7628)

### Community Resources
- **PHPMailer Community:** [Discussions](https://github.com/PHPMailer/PHPMailer/discussions)
- **The PHP League:** [thephpleague.com](https://thephpleague.com/)

### Related Pathary Documentation
- `src/Service/Email/OAUTH_DESIGN.md` - Technical implementation details
- `docs/oauth_database_schema.md` - Database schema documentation
- GitHub Wiki - User-facing setup guides (to be created)

---

**Document Version:** 1.0
**Last Updated:** 2025-12-23
**Author:** Claude Code (Pathary OAuth Implementation)
**Repository:** [benjaminmue/pathary](https://github.com/benjaminmue/pathary)
