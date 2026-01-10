# OAuth Email Setup Guide

This guide explains how to configure OAuth 2.0 authentication for sending emails from Pathary using Gmail or Microsoft 365.

## Table of Contents

- [Overview](#overview)
- [Prerequisites](#prerequisites)
- [Gmail OAuth Setup](#gmail-oauth-setup)
- [Microsoft 365 OAuth Setup](#microsoft-365-oauth-setup)
- [Pathary Configuration](#pathary-configuration)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)

---

## Overview

OAuth 2.0 email authentication provides enhanced security over traditional SMTP password authentication:

- **No passwords stored** - Uses OAuth access tokens instead of passwords
- **Granular permissions** - Only grants email sending permissions
- **Automatic token refresh** - Tokens refresh automatically without re-authorization
- **Revocable access** - Can revoke access from provider without changing password

**Supported Providers:**
- **Gmail** - Personal Gmail or Google Workspace accounts
- **Microsoft 365** - Microsoft 365 / Outlook.com accounts

---

## Prerequisites

### 1. Enable HTTPS (Production)

OAuth providers require HTTPS for redirect URIs (except localhost). Configure your reverse proxy (Nginx, Caddy, Traefik) with SSL/TLS certificates.

Example Nginx configuration:
```nginx
server {
    listen 443 ssl http2;
    server_name pathary.example.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        proxy_pass http://localhost:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### 2. Set APPLICATION_URL

Set the `APPLICATION_URL` environment variable to match your public URL:

```bash
# .env or .env.local
APPLICATION_URL=https://pathary.example.com
```

For local development:
```bash
APPLICATION_URL=http://localhost
```

### 3. Generate Encryption Key

Generate a strong encryption key for storing OAuth secrets:

```bash
openssl rand -hex 32
```

Set as environment variable:
```bash
ENCRYPTION_KEY=your_generated_64_character_hex_string
```

Or generate via Pathary UI: Admin → Server Management → Email Settings → OAuth tab → "Generate Encryption Key"

---

## Gmail OAuth Setup

### Step 1: Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click **Select a project** → **New Project**
3. **Project name**: `Pathary Email OAuth` (or any name you prefer)
4. **Organization**: Select your organization (if applicable)
5. Click **Create**

### Step 2: Enable Gmail API

1. In your project, go to **APIs & Services** → **Library**
2. Search for **Gmail API**
3. Click on it and click **Enable**
4. Wait for activation to complete

### Step 3: Configure OAuth Consent Screen

1. Go to **APIs & Services** → **OAuth consent screen**
2. **User Type**:
   - **Internal** (for Google Workspace - only users in your organization)
   - **External** (for personal Gmail - available to any Google account)
3. Click **Create**

**Fill in the form:**
- **App name**: `Pathary`
- **User support email**: Your email address
- **App logo**: (Optional) Upload Pathary logo
- **Application home page**: Your Pathary URL (e.g., `https://pathary.example.com`)
- **Authorized domains**: Your domain (e.g., `example.com`)
- **Developer contact information**: Your email address

4. Click **Save and Continue**
5. **Scopes**: Skip this step (we'll use Gmail API scope, not Graph scopes)
6. Click **Save and Continue**
7. **Test users** (if External): Add email addresses that can use OAuth during testing
8. Click **Save and Continue**
9. Review summary and click **Back to Dashboard**

### Step 4: Create OAuth 2.0 Client ID

1. Go to **APIs & Services** → **Credentials**
2. Click **Create Credentials** → **OAuth client ID**
3. **Application type**: Select **Web application**
4. **Name**: `Pathary Email Client` (or any name you prefer)

**Configure Authorized Redirect URIs:**
5. Under **Authorized redirect URIs**, click **Add URI**
6. Enter your OAuth callback URL:
   - Format: `{APPLICATION_URL}/admin/server/email/oauth/callback`
   - Example production: `https://pathary.example.com/admin/server/email/oauth/callback`
   - Example local: `http://localhost/admin/server/email/oauth/callback`

7. Click **Create**

**Save Credentials:**
8. A popup shows your **Client ID** and **Client Secret**
9. **Copy both values** - you'll need them for Pathary configuration
10. Click **OK**

### Step 5: Configure in Pathary

1. Go to Admin → Server Management → Email Settings → **OAuth** tab
2. **Email Provider**: Select **Gmail**
3. **Client ID**: Paste the Client ID from step 8
4. **Client Secret**: Paste the Client Secret from step 8
5. **From Address**: Enter your Gmail address (e.g., `noreply@example.com`)
6. Click **Save OAuth Settings**

### Step 6: Authorize Gmail Account

1. Click **Connect** button
2. You'll be redirected to Google sign-in
3. Sign in with your Gmail account
4. **Grant permissions**: Review and click **Allow**
   - You should see: "Pathary wants to access your Google Account"
   - Permission: "Read, compose, send, and permanently delete all your email from Gmail"
5. You'll be redirected back to Pathary
6. You should see "OAuth authorization successful!"

### Step 7: Verify Connection

1. Check **Connection Status** shows "Connected" with green checkmark
2. **Token Status** should show "Active"
3. Click **Send Test Email** to verify

---

## Microsoft 365 OAuth Setup

### Step 1: Register App in Azure Portal

1. Go to [Azure Portal](https://portal.azure.com/)
2. Navigate to **Azure Active Directory** (or **Microsoft Entra ID**)
3. Click **App registrations** in left sidebar
4. Click **New registration**

**Fill in the form:**
- **Name**: `Pathary Email OAuth` (or any name you prefer)
- **Supported account types**:
  - **Single tenant** (only your organization) - Recommended
  - **Multitenant** (any Azure AD directory) - If you have multiple tenants
  - **Personal accounts** - Include personal Microsoft accounts
- **Redirect URI**:
  - Platform: **Web**
  - URI: Your OAuth callback URL (e.g., `https://pathary.example.com/admin/server/email/oauth/callback`)

5. Click **Register**

**Save Application Details:**
6. On the **Overview** page, note down:
   - **Application (client) ID** - You'll need this for Pathary
   - **Directory (tenant) ID** - You'll need this for Pathary

### Step 2: Create Client Secret

1. In your app, go to **Certificates & secrets** in left sidebar
2. Click **Client secrets** tab
3. Click **New client secret**
4. **Description**: `Pathary Email OAuth Secret`
5. **Expires**: Select expiration period
   - **Recommended**: 24 months (maximum)
   - **Important**: You must renew before expiration
6. Click **Add**
7. **Copy the secret Value immediately** - it will only be shown once!

### Step 3: Add API Permissions

This is the **most critical step** for Microsoft 365 OAuth to work correctly.

#### Add Office 365 Exchange Online Permissions

1. Go to **API permissions** in left sidebar
2. Click **Add a permission**
3. Click **APIs my organization uses** tab
4. Search for **Office 365 Exchange Online**
5. Click on it
6. Click **Delegated permissions**
7. Check **SMTP.Send**
8. Click **Add permissions**

#### Add Microsoft Graph Permissions

9. Click **Add a permission** again
10. Click **Microsoft Graph**
11. Click **Delegated permissions**
12. Search and check:
    - **User.Read**
    - **offline_access**
13. Click **Add permissions**

#### Grant Admin Consent

14. Click **Grant admin consent for [Your Organization]** button
15. Click **Yes** to confirm
16. Wait for status to update - all permissions should show green checkmarks under "Status"

**Expected permissions list:**
```
API / Permissions name          Type           Status
──────────────────────────────────────────────────────
Microsoft Graph
  offline_access                Delegated      ✓ Granted
  User.Read                     Delegated      ✓ Granted

Office 365 Exchange Online
  SMTP.Send                     Delegated      ✓ Granted
```

### Step 4: Enable SMTP AUTH (Critical!)

Microsoft 365 requires SMTP AUTH to be enabled at both tenant and mailbox levels.

#### Check Tenant-Level SMTP AUTH

Connect to Exchange Online PowerShell:
```powershell
Install-Module -Name ExchangeOnlineManagement
Connect-ExchangeOnline -UserPrincipalName admin@yourdomain.com
```

Check if SMTP AUTH is enabled:
```powershell
Get-OrganizationConfig | Select-Object SmtpClientAuthenticationDisabled
```

If `SmtpClientAuthenticationDisabled` is `True`, enable it:
```powershell
Set-OrganizationConfig -SmtpClientAuthenticationDisabled $false
```

#### Check Mailbox-Level SMTP AUTH

Check specific mailbox:
```powershell
Get-CASMailbox -Identity mail@yourdomain.com | Select-Object SmtpClientAuthenticationDisabled
```

If disabled, enable it:
```powershell
Set-CASMailbox -Identity mail@yourdomain.com -SmtpClientAuthenticationDisabled $false
```

#### Create Authentication Policy (Alternative)

If you want to enable SMTP AUTH only for specific mailboxes:

```powershell
# Create policy
New-AuthenticationPolicy -Name "Allow SMTP AUTH" -AllowBasicAuthSmtp

# Assign to mailbox
Set-User -Identity mail@yourdomain.com -AuthenticationPolicy "Allow SMTP AUTH"
```

### Step 5: (Optional) Register Service Principal

For enhanced permissions (SendAs), register the app as a service principal:

```powershell
# Get app details from Azure Portal Overview page
$appId = "f2da336d-56cd-4e18-b426-b4567b0ca98c"  # Application (client) ID
$objectId = "bdb51601-5178-4fb3-b4ad-f1e79fef5016"  # Object ID

# Register service principal
New-ServicePrincipal -AppId $appId -ServiceId $objectId

# Grant SendAs permission
Add-RecipientPermission -Identity 'mail@yourdomain.com' -Trustee $objectId -AccessRights SendAs
```

### Step 6: Configure in Pathary

1. Go to Admin → Server Management → Email Settings → **OAuth** tab
2. **Email Provider**: Select **Microsoft 365 / Outlook**
3. **Client ID**: Paste the Application (client) ID from Azure
4. **Client Secret**: Paste the secret Value from step 2
5. **Tenant ID**: Paste the Directory (tenant) ID from Azure
6. **Authentication Mailbox**: Enter your Microsoft 365 email (e.g., `mail@yourdomain.com`)
7. **Email From Address**: (Optional) Different from address (e.g., `noreply@yourdomain.com`)
8. Click **Save OAuth Settings**

### Step 7: Authorize Microsoft Account

1. Click **Connect** button
2. You'll be redirected to Microsoft sign-in
3. Sign in with your Microsoft 365 account
4. **Grant permissions**: Review and click **Accept**
   - You should see: "Pathary wants to access your account"
   - Permissions requested:
     - Send mail as you
     - Maintain access to data you have given it access to
     - Sign you in and read your profile
5. You'll be redirected back to Pathary
6. You should see "OAuth authorization successful!"

### Step 8: Verify Connection

1. Check **Connection Status** shows "Connected" with green checkmark
2. **Token Status** should show "Active"
3. **Granted Scopes** should include:
   - `SMTP.Send`
   - `User.Read`
   - `offline_access`
4. Click **Send Test Email** to verify

---

## Pathary Configuration

### Access OAuth Settings

1. Log in to Pathary as admin
2. Go to **Admin** → **Server Management**
3. Click **Email Settings** section
4. Click **OAuth** tab

### Configuration Fields

**Email Provider:**
- Select `Gmail` or `Microsoft 365 / Outlook`
- Shows provider-specific setup instructions when selected

**Client ID:**
- Gmail: From Google Cloud Console → Credentials
- Microsoft: Application (client) ID from Azure Portal

**Client Secret:**
- Gmail: From Google Cloud Console → Credentials
- Microsoft: Secret Value from Azure Portal (Certificates & secrets)

**Tenant ID** (Microsoft 365 only):
- Directory (tenant) ID from Azure Portal
- Leave blank for multi-tenant apps (uses `common`)

**Authentication Mailbox:**
- The email account used for OAuth authorization
- Gmail: Your Gmail address (e.g., `you@gmail.com`)
- Microsoft: Your Microsoft 365 address (e.g., `mail@yourdomain.com`)

**Email From Address** (Optional):
- Different email shown as sender
- Must be an alias or have SendAs permissions
- Leave blank to use Authentication Mailbox as From address

**Secret Expiry Reminder** (Optional):
- Set reminder for client secret expiration (Microsoft 365)
- Recommended: Set to 23 months if secret expires in 24 months
- Pathary will show warning when expiry approaches

### Switch Between Password and OAuth

**Switch to OAuth:**
1. Configure OAuth settings in OAuth tab
2. Click **Connect** to authorize
3. Click **Switch to OAuth Mode** button
4. All emails will now use OAuth authentication

**Switch to Password:**
1. Go to **Password** tab
2. Configure SMTP settings (host, port, username, password)
3. Click **Save SMTP Settings**
4. Click **Switch to Password Mode** button
5. All emails will now use password authentication

**Note**: OAuth configuration is preserved when switching modes.

---

## Testing

### Send Test Email

1. Go to Admin → Server Management → Email Settings
2. Click **Send Test Email** button
3. Check your inbox for test email
4. Email subject: "Test Email from Pathary"
5. Email body: "This is a test email sent to check the currently set email settings."

### Verify Token Refresh

OAuth tokens expire after 1 hour. Pathary automatically refreshes them using the refresh token.

**Monitor token refresh:**
1. Check **Last Token Refresh** timestamp in OAuth tab
2. Send test email after 1 hour
3. Token should refresh automatically
4. **Token Status** should remain "Active"

### Check Logs

If email sending fails, check logs:

```bash
# Docker logs
docker compose logs app

# Look for errors containing:
# - "OAuth"
# - "SMTP"
# - "Failed to refresh"
# - "Authentication unsuccessful"
```

---

## Troubleshooting

### Gmail Issues

#### Error: "redirect_uri_mismatch"

**Cause**: Redirect URI in Pathary doesn't match Google Cloud Console configuration.

**Fix:**
1. Check `APPLICATION_URL` environment variable matches your domain
2. Go to Google Cloud Console → Credentials → Your OAuth Client
3. Verify **Authorized redirect URIs** contains exact URL:
   - `{APPLICATION_URL}/admin/server/email/oauth/callback`
4. Update if needed and try again

#### Error: "invalid_scope"

**Cause**: Gmail API not enabled in Google Cloud project.

**Fix:**
1. Go to Google Cloud Console → APIs & Services → Library
2. Search for "Gmail API"
3. Click **Enable**
4. Wait a few minutes and try again

#### Error: "Access blocked: This app's request is invalid"

**Cause**: OAuth consent screen not configured or missing required fields.

**Fix:**
1. Go to Google Cloud Console → APIs & Services → OAuth consent screen
2. Ensure all required fields are filled:
   - App name
   - User support email
   - Developer contact information
3. Click **Save and Continue**

#### No refresh token received

**Cause**: OAuth consent screen not showing or user previously authorized.

**Fix:**
1. Revoke access: https://myaccount.google.com/permissions
2. Find "Pathary" and click **Remove access**
3. Reconnect OAuth in Pathary
4. You should see consent screen asking for permissions

---

### Microsoft 365 Issues

#### Error: "535 5.7.3 Authentication unsuccessful"

This is the **most common** Microsoft 365 OAuth error. Several possible causes:

**Cause 1: Wrong token audience**

The token is requested for Azure AD Graph instead of Exchange Online.

**Fix:**
Verify code uses correct configuration (already fixed in Pathary):
```php
// OAuthTokenService.php and EmailService.php
'defaultEndPointVersion' => Azure::ENDPOINT_VERSION_2_0,
'urlAPI' => 'https://outlook.office365.com/',
'resource' => 'https://outlook.office365.com/',
```

**Cause 2: Missing SMTP.Send scope**

The app doesn't have Exchange Online SMTP.Send permission.

**Fix:**
1. Go to Azure Portal → App registrations → Your app → API permissions
2. Verify **Office 365 Exchange Online** → **SMTP.Send** exists and is granted
3. If missing, add it:
   - Click **Add a permission**
   - Click **APIs my organization uses** tab
   - Search for **Office 365 Exchange Online**
   - Select **Delegated permissions** → Check **SMTP.Send**
   - Click **Add permissions**
4. Click **Grant admin consent for [Organization]**
5. **Disconnect and reconnect** OAuth in Pathary

**Verify granted scopes in database:**
```sql
SELECT granted_scopes FROM oauth_email_config;
```
Should include: `SMTP.Send User.Read offline_access`

**Cause 3: SMTP AUTH disabled**

SMTP protocol is disabled at tenant or mailbox level.

**Fix:**
```powershell
# Connect to Exchange Online
Connect-ExchangeOnline

# Check tenant level
Get-OrganizationConfig | Select-Object SmtpClientAuthenticationDisabled

# Enable if needed
Set-OrganizationConfig -SmtpClientAuthenticationDisabled $false

# Check mailbox level
Get-CASMailbox -Identity mail@yourdomain.com | Select-Object SmtpClientAuthenticationDisabled

# Enable if needed
Set-CASMailbox -Identity mail@yourdomain.com -SmtpClientAuthenticationDisabled $false
```

**Cause 4: Service principal not registered**

App not registered as service principal in Exchange Online.

**Fix:**
```powershell
# Get app details from Azure Portal
$appId = "YOUR-APPLICATION-CLIENT-ID"
$objectId = "YOUR-APPLICATION-OBJECT-ID"

# Register service principal
New-ServicePrincipal -AppId $appId -ServiceId $objectId

# Grant SendAs permission
Add-RecipientPermission -Identity 'mail@yourdomain.com' -Trustee $objectId -AccessRights SendAs
```

#### Error: "AADSTS50011: redirect uri mismatch"

**Cause**: Redirect URI in Pathary doesn't match Azure app registration.

**Fix:**
1. Check `APPLICATION_URL` environment variable
2. Go to Azure Portal → App registrations → Your app → Authentication
3. Under **Redirect URIs**, verify exact URL exists:
   - `{APPLICATION_URL}/admin/server/email/oauth/callback`
4. Add if missing and click **Save**

#### Error: "AADSTS65001: The user or administrator has not consented"

**Cause**: Admin consent not granted for API permissions.

**Fix:**
1. Go to Azure Portal → App registrations → Your app → API permissions
2. Click **Grant admin consent for [Your Organization]**
3. Click **Yes** to confirm
4. Verify all permissions show green checkmarks under "Status"

#### Error: "AADSTS7000218: The request body must contain the following parameter: 'client_assertion' or 'client_secret'"

**Cause**: Client secret expired or invalid.

**Fix:**
1. Go to Azure Portal → App registrations → Your app → Certificates & secrets
2. Check if secret is expired under **Client secrets**
3. If expired, create new secret:
   - Click **New client secret**
   - Set expiration period
   - Click **Add**
   - **Copy the Value immediately**
4. Update client secret in Pathary OAuth settings
5. Click **Save OAuth Settings**
6. **Disconnect and reconnect** OAuth

#### Error: "invalid_grant" or "Token has been revoked"

**Cause**: Refresh token expired or user revoked access.

**Fix:**
1. Go to Pathary → Admin → Email Settings → OAuth tab
2. Click **Disconnect**
3. Click **Connect** to re-authorize
4. Sign in and grant permissions again

#### Can't find "Office 365 Exchange Online" in API permissions

**Cause**: You're looking in the wrong tab.

**Fix:**
1. Click **Add a permission**
2. **Important**: Click **APIs my organization uses** tab (NOT "Microsoft APIs")
3. Search for **Office 365 Exchange Online**
4. Select it
5. Choose **Delegated permissions**
6. Check **SMTP.Send**

---

### General Issues

#### Error: "OAuth configuration not found"

**Cause**: OAuth settings not saved.

**Fix:**
1. Go to Admin → Email Settings → OAuth tab
2. Fill in all required fields
3. Click **Save OAuth Settings**
4. Verify "Settings saved successfully" message

#### Error: "ENCRYPTION_KEY not configured"

**Cause**: Encryption key not set.

**Fix:**
Option 1 - Generate via UI:
1. Go to Admin → Email Settings → OAuth tab
2. Click **Generate Encryption Key**
3. Key is automatically saved

Option 2 - Set environment variable:
```bash
# Generate key
openssl rand -hex 32

# Add to .env or .env.local
ENCRYPTION_KEY=your_generated_64_character_hex_string

# Restart container
docker compose restart app
```

#### Connection shows "Connected" but emails fail

**Cause**: Email auth mode not set to OAuth.

**Fix:**
1. Check database:
   ```sql
   SELECT value FROM server_setting WHERE key = 'email_auth_mode';
   ```
2. If not `smtp_oauth`, set it:
   ```sql
   INSERT INTO server_setting (key, value)
   VALUES ('email_auth_mode', 'smtp_oauth')
   ON DUPLICATE KEY UPDATE value = 'smtp_oauth';
   ```
3. Or use UI: Click **Switch to OAuth Mode** button

#### Token Status shows "Error"

**Cause**: Token refresh failed.

**Fix:**
1. Check error message in **Token Status** field
2. Common errors:
   - **"invalid_client"**: Client ID or secret incorrect → Update credentials
   - **"invalid_grant"**: Refresh token expired → Disconnect and reconnect
   - **"unauthorized_client"**: Missing API permissions → Add required permissions
3. Click **Disconnect** and **Connect** again to re-authorize

#### SMTP connect() failed

**Cause**: Wrong SMTP host or port for OAuth provider.

**Fix:**
OAuth mode uses provider defaults (cannot be changed):
- **Gmail**: `smtp.gmail.com:587` (TLS)
- **Microsoft 365**: `smtp.office365.com:587` (TLS)

Verify `email_auth_mode` is set to `smtp_oauth` (not `smtp_password`).

---

## Security Considerations

### Client Secret Rotation

**Microsoft 365** client secrets expire (max 24 months). **Plan ahead:**

1. Set **Secret Expiry Reminder** in Pathary to 23 months
2. Before expiry, create new secret in Azure Portal
3. Update client secret in Pathary
4. Click **Disconnect** and **Connect** to get new token
5. Delete old secret in Azure Portal

**Gmail** client secrets don't expire unless you regenerate them.

### Revoking Access

**Gmail:**
1. Go to https://myaccount.google.com/permissions
2. Find "Pathary"
3. Click **Remove access**

**Microsoft 365:**
1. Go to https://myaccount.microsoft.com/privacy/app-permissions
2. Find "Pathary Email OAuth"
3. Click **Remove**

Or via Azure Portal:
1. Azure Portal → Enterprise applications
2. Find your app
3. Click **Delete** or revoke user consent

### Encryption Key Rotation

If you rotate `ENCRYPTION_KEY`:

1. All stored secrets become inaccessible
2. You must **reconnect OAuth** for all providers
3. Plan rotation during maintenance window

### Audit Logging

Monitor OAuth authorization attempts:

```sql
SELECT * FROM security_audit_log
WHERE event_type = 'oauth_authorization'
ORDER BY created_at DESC
LIMIT 20;
```

---

## Additional Resources

- [Google OAuth 2.0 Documentation](https://developers.google.com/identity/protocols/oauth2)
- [Microsoft Identity Platform OAuth 2.0](https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow)
- [Office 365 Exchange Online SMTP AUTH](https://learn.microsoft.com/en-us/exchange/clients-and-mobile-in-exchange-online/authenticated-client-smtp-submission)
- [Pathary GitHub Repository](https://github.com/benjaminmue/pathary)
- [Pathary Issues](https://github.com/benjaminmue/pathary/issues)

---

## Getting Help

If you encounter issues not covered in this guide:

1. Check Pathary logs: `docker compose logs app`
2. Verify all prerequisites are met
3. Search [existing issues](https://github.com/benjaminmue/pathary/issues)
4. Create a [new issue](https://github.com/benjaminmue/pathary/issues/new) with:
   - Provider (Gmail or Microsoft 365)
   - Error message
   - Steps to reproduce
   - Log output (redact secrets!)
