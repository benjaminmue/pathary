# First-Time Setup Guide

This guide walks you through the initial setup process for your Pathary instance. The setup wizard will help you create your first admin account with two-factor authentication (2FA) enabled.

## Overview

On first installation, Pathary requires you to complete a one-time setup wizard to create an admin account. This wizard ensures your instance is properly secured from the start by:

- Creating an admin account with a strong password
- Enabling two-factor authentication (2FA) using TOTP
- Generating recovery codes for account recovery

**Important:** The setup wizard is only accessible when no users exist in the database. Once completed, it becomes permanently inaccessible.

## Prerequisites

Before starting the setup wizard, ensure:

1. Pathary is installed and running (via Docker or manual installation)
2. The application is accessible at your configured URL (e.g., `http://localhost` or `https://<your_domain>`)
3. The database is properly configured and migrations have been applied

## Accessing the Setup Wizard

Navigate to your Pathary instance in a web browser. If no admin account exists, you'll be automatically redirected to `/init`.

Alternatively, access the wizard directly at: `http://your-pathary-url/init`

## Step 1: Create Admin Account

The first step collects your basic account information and sets up a secure password.

![Setup Wizard - Step 1: Create Admin Account](images/init-screen1.png)

### Required Information

- **Email Address**: Your admin email address (used for login)
- **Name**: Your display name
- **Password**: Must meet the following requirements:
  - At least 10 characters
  - One uppercase letter
  - One lowercase letter
  - One number
  - One special character
- **Confirm Password**: Must match your password exactly

### Password Requirements

As you type your password, the requirements checklist updates in real-time:

- ✅ Green checkmark: Requirement met
- ⚪ Gray circle: Requirement not met

The confirmation field will show:
- **Green outline**: Passwords match
- **Red outline**: Passwords don't match

### Next Step

Click **Continue** to proceed to 2FA setup. Your account information is stored temporarily in session and won't be saved to the database until you complete all steps.

---

## Step 2: Two-Factor Authentication Setup

Pathary requires two-factor authentication for all admin accounts to enhance security. This step configures TOTP (Time-based One-Time Password) authentication.

![Setup Wizard - Step 2: 2FA Setup](images/init-screen2.png)

### Setting Up 2FA

You'll need an authenticator app on your smartphone or computer. Recommended apps include:

- **Google Authenticator** (iOS/Android)
- **Authy** (iOS/Android/Desktop)
- **1Password** (iOS/Android/Desktop)
- **Microsoft Authenticator** (iOS/Android)
- Any other TOTP-compatible authenticator

### Configuration Methods

**Method 1: Scan QR Code (Recommended)**

1. Open your authenticator app
2. Tap "Add Account" or "Scan QR Code"
3. Point your camera at the QR code displayed on screen
4. The app will automatically add your Pathary account

**Method 2: Enter Secret Manually**

If you can't scan the QR code:

1. Click the **Copy** button below the secret code
2. In your authenticator app, choose "Enter Setup Key" or "Manual Entry"
3. Paste the secret code
4. Enter your Pathary account name (e.g., "Pathary Admin")

### Verify 2FA

1. Open your authenticator app
2. Find your Pathary account
3. Enter the 6-digit code shown in the app
4. Click **Verify & Continue**

The code refreshes every 30 seconds. If verification fails, wait for a new code and try again.

---

## Step 3: Save Recovery Codes

Recovery codes are essential for regaining access to your account if you lose your authenticator device.

![Setup Wizard - Step 3: Recovery Codes](images/init-screen3.png)

### What Are Recovery Codes?

Recovery codes are single-use backup codes that can be used instead of your 2FA code during login. You'll receive **10 codes** in the format: `XXXX-XXXX-XX`

### Saving Your Codes

**Important:** You will only see these codes once. Store them securely!

**Recommended Storage Methods:**

1. **Password Manager**: Store in a secure vault (1Password, Bitwarden, etc.)
2. **Encrypted File**: Save to an encrypted USB drive or cloud storage
3. **Physical Copy**: Print and store in a safe location

**How to Copy:**

- Click **Copy All Codes** to copy all codes to clipboard
- Paste into your chosen storage location

### Before Proceeding

Check the box confirming: **"I have saved these recovery codes in a secure location"**

This enables the **Complete Setup** button.

---

## Step 4: Setup Complete

Once you acknowledge saving the recovery codes, the wizard creates your admin account and applies all settings.

![Setup Wizard - Step 4: Complete](images/init-screen4.png)

### What Happens During Completion

The wizard performs these actions atomically:

1. **Creates admin user** in the database (ID: 1)
2. **Saves TOTP configuration** for 2FA
3. **Stores recovery codes** (hashed with bcrypt)
4. **Marks setup as completed** (prevents re-running the wizard)
5. **Logs security events** (setup completion, 2FA enabled)

### Next Steps

Click **Go to Login** to access the login page at `/login`.

---

## Logging In

After completing setup, log in using:

1. **Email**: The email you provided in Step 1
2. **Password**: The password you created
3. **2FA Code**: Open your authenticator app and enter the current 6-digit code

### First Login

On your first login, you may be asked to:

- **Trust this device**: Optional. If enabled, you won't need 2FA codes on this device for 30 days
- **Update application settings**: Configure TMDB API key and other global settings

---

## Troubleshooting

### Wizard Shows 404 Error

**Cause**: Setup has already been completed or a user exists in the database.

**Solution**: Access the login page at `/login` instead.

### Can't Access `/init` After Installation

**Cause**: A user already exists in the database (possibly from a previous installation).

**Solution**: If this is a fresh install, reset the database:

```bash
# Using Docker
docker compose exec mysql mysql -u <database_user> -p<database_password> -e "DROP DATABASE IF EXISTS <database_name>; CREATE DATABASE <database_name>;"
docker compose exec app php vendor/bin/phinx migrate -c ./settings/phinx.php

# Manual installation
mysql -u <database_user> -p -e "DROP DATABASE IF EXISTS <database_name>; CREATE DATABASE <database_name>;"
php vendor/bin/phinx migrate -c ./settings/phinx.php
```

!!! note "MySQL Password Syntax"
    Note the `-p` flag syntax:

    - `-p<database_password>` - No space between `-p` and password (required for Docker exec)
    - `-p` alone - Prompts for password interactively (safer for manual use, no password in shell history)

**Warning**: This permanently deletes all data. Only use on a fresh installation.

### Lost Authenticator Device Before Completing Setup

**Solution**: Reload the page at `/init` to start over. Since the account isn't created until Step 4, your session data will be cleared and you can begin again.

### Lost Authenticator Device After Completing Setup

**Solution**: Use a recovery code during login:

1. Go to the login page
2. Enter email and password
3. When prompted for 2FA code, click **"Use recovery code instead"**
4. Enter one of your saved recovery codes
5. **Important**: Immediately set up 2FA again in Profile → Security settings

### Session Expired During Wizard

**Cause**: You took too long between steps (session timeout).

**Solution**: Reload the page at `/init` to start fresh. The wizard clears session data on page load.

### Password Requirements Too Strict

**Rationale**: Pathary enforces strong password requirements to protect your movie data and prevent unauthorized access. These requirements are non-configurable to ensure consistent security.

**Alternative**: Use a password manager (1Password, Bitwarden, etc.) to generate and store a strong password automatically.

### 2FA Code Rejected

**Possible causes:**

1. **Time sync issue**: Ensure your device's clock is synchronized (most authenticator apps show a warning if time is off)
2. **Expired code**: Wait for a new code (refreshes every 30 seconds)
3. **Wrong secret**: If you manually entered the secret, ensure it's correct (try scanning QR code instead)

---

## Security Notes

### Why 2FA is Required

Pathary requires two-factor authentication for all admin accounts to:

- Protect against password theft or leaks
- Prevent unauthorized access even if your password is compromised
- Ensure your movie ratings, watchlists, and personal data remain secure

### Recovery Code Best Practices

- **Never share** recovery codes with anyone
- **Don't store** codes in plain text on your computer
- **Use each code only once** (they're single-use)
- **Regenerate codes** if you suspect they've been compromised (Profile → Security)

### Session Security

After completing setup:

- Sessions expire after inactivity
- Trusted devices remember your 2FA status for 30 days
- You can revoke trusted devices at any time in Profile → Security

---

## Next Steps After Setup

Once logged in, configure your Pathary instance:

1. **Add TMDB API Key**: Navigate to Admin → Server Settings → TMDB to enable movie data fetching
2. **Configure Email** (Optional): Set up email notifications in Admin → Server Settings → Email
3. **Add Movies**: Start building your movie collection by searching and adding movies from TMDB
4. **Set Up Integrations** (Optional): Connect Plex, Jellyfin, Trakt, or other services

For detailed configuration guides, see:

- [TMDB Integration](features/movies-and-tmdb.md)
- [Email Configuration](../README.md#email-configuration)
- [Two-Factor Authentication Management](security/two-factor-authentication.md)

---

## Related Documentation

- [Two-Factor Authentication](security/two-factor-authentication.md) - Managing 2FA settings
- [Password Policy and Security](security/password-policy-and-security.md) - Understanding password requirements
- [Authentication and Sessions](security/authentication-and-sessions.md) - How login sessions work
- [Docker Installation](install/docker.md) - Installing Pathary with Docker
- [Manual Installation](install/manual.md) - Installing Pathary manually

---

## Emergency Access

### Locked Out (Lost 2FA Device + Recovery Codes)

If you lose both your authenticator device and recovery codes, you have two options:

#### Option 1: Use CLI to Delete and Recreate User

```bash
# List users to find the ID
docker compose exec app php bin/console.php user:list

# Delete your locked user (replace USER_ID)
docker compose exec app php bin/console.php user:delete USER_ID

# Create new user
docker compose exec app php bin/console.php user:create \
  "<email_address>" \
  "<password>" \
  "<username>" \
  true
```

**Note**: This preserves all movies and ratings data, but you'll need to set up 2FA again via the web interface.

#### Option 2: Database Reset (Last Resort)

**Warning**: This deletes ALL data including movies, ratings, and users.

```bash
# Using Docker
docker compose exec mysql mysql -u <database_user> -p<database_password> -e "DROP DATABASE IF EXISTS <database_name>; CREATE DATABASE <database_name>;"
docker compose exec app php vendor/bin/phinx migrate -c ./settings/phinx.php

# Manual installation
mysql -u <database_user> -p -e "DROP DATABASE IF EXISTS <database_name>; CREATE DATABASE <database_name>;"
php vendor/bin/phinx migrate -c ./settings/phinx.php
```

After reset, access `/init` to run the setup wizard again.

### Emergency Admin Creation (Any Situation)

If you need to create an emergency admin account (locked out, death of admin, fired employee, etc.), use the emergency admin command:

```bash
# Using Docker
docker compose exec app php bin/console.php user:create-emergency-admin

# Manual installation
php bin/console.php user:create-emergency-admin
```

The command will interactively guide you through:
1. Confirming the emergency (if admins already exist)
2. Creating a new admin account
3. Setting up 2FA with QR code
4. Generating recovery codes

**Security Note**: If admin users already exist, the command will warn you and require explicit confirmation before proceeding. This prevents accidental creation of unnecessary admin accounts while still allowing true emergencies.

---

## Frequently Asked Questions

### Can I skip 2FA setup?

No. Two-factor authentication is mandatory for all admin accounts to ensure security. There is no option to disable this requirement during setup.

### Can I change my email or password later?

Yes. After logging in, navigate to **Profile → Security** to:
- Change your password
- Update your email address
- Manage 2FA settings
- Generate new recovery codes

### What if I want to use a different authenticator app?

You can switch authenticator apps at any time:

1. Disable 2FA in Profile → Security (requires password confirmation)
2. Re-enable 2FA with your new authenticator app
3. Save the new recovery codes

### Can I create additional admin accounts?

Yes. After completing initial setup, log in and navigate to:

**Admin → Users → Create User**

Set the "Admin" flag when creating the new user. They can then enable 2FA in their profile settings.

### Does the setup wizard work on mobile?

Yes. The setup wizard is fully responsive and works on mobile devices, tablets, and desktops. However, scanning QR codes is easier on mobile devices with a camera.

---

## Support

If you encounter issues not covered in this guide:

1. Check the [GitHub Issues](https://github.com/benjaminmue/pathary/issues) for known problems
2. Search the [Wiki](https://github.com/benjaminmue/pathary/wiki) for additional documentation
3. Report bugs or request help by [opening a new issue](https://github.com/benjaminmue/pathary/issues/new)

---

**Last Updated**: 2026-01-13
**Applies to**: Pathary v1.0.0+
