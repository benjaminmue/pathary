# OAuth Token Monitoring Setup Guide

This guide explains the automated OAuth token monitoring system for Email OAuth connections in Pathary.

## Overview

The OAuth monitoring system automatically checks the health of your Email OAuth connection (Gmail or Microsoft 365) and alerts administrators when action is needed. This prevents email sending failures due to expired or revoked OAuth tokens.

**No configuration required** - monitoring runs automatically when users visit the site.

## Features

- **Automatic Health Checks**: Runs on page loads without requiring cron jobs or scheduled tasks
- **Smart Throttling**: Checks run at most every 6 hours to avoid overhead
- **Multi-Tier Alerts**: Warnings at 45, 30, and 15 days before recommended refresh
- **Admin Notifications**: Email alerts to all administrators
- **Admin Popup Banner**: Visual alerts when admins login (dismissible for 3 hours)
- **Event Logging**: All monitoring events logged to Admin → Events
- **System Health Dashboard**: OAuth status visible in System Health (future feature)

## How It Works

### Lazy Monitoring

Pathary uses a "lazy monitoring" approach where OAuth health checks are triggered automatically when any user visits the site:

1. **On Page Load**: Every web request checks if monitoring should run
2. **Time-Based Throttling**: Monitoring only runs if at least 6 hours have passed since the last check
3. **Database Locking**: Concurrent requests are prevented via database locks
4. **Non-Blocking**: Monitoring runs in the background and doesn't slow down page loads

This approach means:
- ✅ No cron jobs to configure
- ✅ No external schedulers needed
- ✅ Works in any hosting environment (Unraid, VPS, Docker, etc.)
- ✅ Monitoring happens automatically as long as someone uses the site
- ⚠️ Requires occasional site visits (at least once every 6 hours for timely alerts)

### Monitoring Interval

The system runs health checks **at most every 6 hours**. This interval balances:
- Timely detection of OAuth issues
- Minimal overhead on page loads
- Reduced API calls to OAuth providers

If you need more frequent checks, you can visit any page in Pathary to trigger a check (if 6+ hours have elapsed).

## Alert Schedule

| Threshold | Alert Level | Notification Frequency |
|-----------|-------------|----------------------|
| 45 days before refresh needed | Warning | Once, then weekly |
| 30 days before refresh needed | Warning | Once, then every 3 days |
| 15 days before refresh needed | Critical | Once, then daily |
| Token refresh failing | Critical | Daily until resolved |
| Re-authorization required | Expired | Immediate, then daily |

## Installation

### 1. Run Database Migration

The OAuth monitoring system requires database schema changes. Run the migration:

```bash
# In Docker (recommended)
docker compose exec app php vendor/bin/phinx migrate -c ./settings/phinx.php

# Locally (if running without Docker)
php vendor/bin/phinx migrate -c ./settings/phinx.php
```

This adds:
- Monitoring fields to `oauth_email_config` table
- `oauth_admin_banner_ack` table for tracking banner acknowledgements

### 2. Verify Installation

**That's it!** No additional configuration needed. The monitoring system is now active.

To verify it's working:

1. **Check the logs** after visiting a page:
   ```bash
   docker compose logs app | grep -i "oauth monitoring"

   # You should see entries like:
   # [INFO] Triggering lazy OAuth monitoring
   # [INFO] Lazy OAuth monitoring completed
   ```

2. **Visit Admin → Events**:
   - Login as admin
   - Go to Admin → Events
   - Filter by event type containing "oauth"
   - You should see monitoring events logged after 6+ hours of site activity

3. **Check last run time** in the database (optional):
   ```bash
   docker compose exec db mysql -u movary -p -e "USE movary; SELECT * FROM server_setting WHERE \`key\` = 'oauth_monitoring_last_run_at';"
   ```

## Monitoring Behavior

### Health Evaluation Logic

The system evaluates OAuth health based on:

1. **Token Refresh Success**: Can the system successfully refresh the access token?
2. **Days Since Last Refresh**: How long since the last successful refresh?
3. **Error Patterns**: What errors are occurring (if any)?
4. **Re-auth Required**: Does the error indicate re-authorization is needed?

### Alert Level Determination

- **OK**: Recent successful refresh, no failures
- **Warning**: Approaching recommended refresh interval (45/30/15 days)
- **Critical**: Token refresh failing or very close to recommended refresh
- **Expired**: Re-authorization required, token revoked, or refresh failing for 7+ days

### Notification Suppression

Notifications are sent according to the schedule above and are suppressed if:
- Already notified within the scheduled interval
- Alert level has not escalated
- Admin has acknowledged the banner within the last 3 hours (banner only)

## Admin Experience

### Email Notifications

Admins receive email alerts when thresholds are crossed. Emails include:

- Alert level (WARNING/CRITICAL/URGENT)
- Provider name (Gmail/Microsoft 365)
- Status message with details
- Days to action (if applicable)
- Last error details (if any)
- Direct link to Email Settings page

**Important**: Emails are sent using fallback SMTP password authentication if OAuth is failing, ensuring you always receive alerts.

### Admin Banner

When admins visit any page in Pathary, they may see a modal banner if:

- OAuth connection requires attention (warn/critical/expired)
- Banner was not acknowledged in the last 3 hours
- Alert level has not been resolved

The banner can be:
- **Acknowledged**: Dismissed for 3 hours
- **Bypassed**: Click "Open Email Settings" to address immediately

If alert level escalates (e.g., warn → critical), banner shows immediately even if recently acknowledged.

### Admin Events Log

All monitoring activities are logged to Admin → Events:

- `oauth_token_warn_45/30/15` - Warning notifications sent
- `oauth_token_warn_daily` - Daily critical alert sent
- `oauth_token_expired` - Token expiry detected
- `oauth_token_refresh_failed` - Token refresh failed
- `oauth_token_refresh_recovered` - Token refresh recovered after failure
- `oauth_banner_acknowledged` - Admin acknowledged banner

Events include metadata:
- Provider (gmail/microsoft)
- Alert level
- Error code (if applicable)
- Re-auth required status

## Troubleshooting

### No Monitoring Events

**Problem**: No OAuth monitoring events appearing in Admin → Events

**Solutions**:
1. Ensure database migration was run: `docker compose exec app php vendor/bin/phinx migrate -c ./settings/phinx.php`
2. Verify OAuth is configured: Admin → Email Settings → OAuth tab
3. Check if anyone has visited the site in the last 6 hours (monitoring needs page loads)
4. Check application logs: `docker compose logs app | grep -i oauth`
5. Visit a page and wait a moment, then check logs again

### No Alerts Received

**Problem**: OAuth may be failing but no alerts sent

**Solutions**:
1. Verify monitoring is running: Check Admin → Events for recent `oauth_token_` events
2. Check if OAuth is actually configured: Admin → Email Settings → OAuth tab
3. Verify admin users have valid email addresses
4. Check application logs: `docker compose logs app | grep -i oauth`
5. Verify email sending works: Admin → Email Settings → Test Email

### Banner Not Showing

**Problem**: Admin banner not appearing

**Solutions**:
1. Verify you're logged in as admin
2. Check OAuth alert level by visiting Admin → Email Settings → OAuth tab
3. Check browser console for JavaScript errors (F12 → Console)
4. Clear browser cache and reload page
5. Verify banner was not recently acknowledged: Check `oauth_admin_banner_ack` table

### Monitoring Running Too Frequently

**Problem**: Monitoring appears to run on every page load

**Solutions**:
1. Check database for last run time:
   ```bash
   docker compose exec db mysql -u movary -p -e "USE movary; SELECT * FROM server_setting WHERE \`key\` = 'oauth_monitoring_last_run_at';"
   ```
2. Verify 6-hour interval is being enforced in logs
3. Check for database lock issues:
   ```bash
   docker compose exec db mysql -u movary -p -e "USE movary; SELECT * FROM server_setting WHERE \`key\` = 'oauth_monitoring_lock';"
   ```
   - If a lock is stuck, delete it: `DELETE FROM server_setting WHERE \`key\` = 'oauth_monitoring_lock';`

### False Alerts

**Problem**: Receiving alerts but OAuth is working fine

**Solutions**:
1. Check if your OAuth provider has temporary issues
2. Review error codes in Admin → Events to identify pattern
3. If using Microsoft 365, verify SMTP AUTH is enabled at tenant level
4. Try reconnecting OAuth: Admin → Email Settings → OAuth → Disconnect → Connect

## Security Considerations

### What's Logged

The monitoring system logs:
- Event types and timestamps
- Provider names (gmail/microsoft)
- Error codes (sanitized)
- Alert levels
- IP addresses of admin actions

### What's NEVER Logged

- Access tokens
- Refresh tokens
- Client secrets
- Full error messages containing tokens
- Admin passwords
- Email content

### Data Retention

- Monitoring events follow the same retention as other security audit logs
- Banner acknowledgements are kept until replaced (one per admin user)
- Recommendation: Implement periodic cleanup of old audit logs (90 days)

## Advanced Configuration

### Adjusting Monitoring Interval

Edit `src/Service/Email/OAuthLazyMonitoringService.php`:

```php
// Run monitoring at most every X hours
private const MIN_INTERVAL_SECONDS = 21600; // 6 hours (default)

// Change to 3 hours:
private const MIN_INTERVAL_SECONDS = 10800;

// Change to 12 hours:
private const MIN_INTERVAL_SECONDS = 43200;
```

**Warning**: More frequent checks increase database queries and OAuth provider API calls.

### Adjusting Alert Thresholds

Edit `src/Service/Email/OAuthMonitoringService.php`:

```php
// Health thresholds (days)
private const THRESHOLD_45_DAYS = 45;  // First warning
private const THRESHOLD_30_DAYS = 30;  // Second warning
private const THRESHOLD_15_DAYS = 15;  // Critical warning
private const DAILY_ALERT_START = 14;  // Start daily alerts
private const MAX_DAYS_WITHOUT_REFRESH = 60; // Token age limit
```

**Warning**: Changing these values requires understanding OAuth refresh token lifecycle for your provider.

### Manual Health Check (For Testing)

You can force an immediate monitoring run by resetting the last run time:

```bash
docker compose exec db mysql -u movary -p -e "USE movary; DELETE FROM server_setting WHERE \`key\` = 'oauth_monitoring_last_run_at';"
```

Then visit any page in Pathary to trigger monitoring immediately.

### Disabling Features

**Disable email notifications**: Remove admin email addresses (banner will still work)

**Disable banner**: Remove the banner include from `templates/layouts/app_base.twig`

**Disable monitoring completely**: Don't run the database migration

## Best Practices

1. **Visit the site regularly**: Monitoring requires page loads, so ensure at least one admin checks the site daily
2. **Test email delivery**: Before relying on email alerts, send a test email from Admin → Email Settings
3. **Document your OAuth setup**: Keep records of client ID, tenant ID, and expiry dates
4. **Monitor the monitoring**: Check Admin → Events periodically to ensure monitoring runs
5. **Act promptly on alerts**: Don't wait until expiry to reconnect
6. **Keep refresh tokens fresh**: Reconnect OAuth yearly even if not prompted

## Maintenance

### Check Monitoring Status

View recent monitoring activity:

```bash
# Check logs
docker compose logs app | grep -i "oauth monitoring"

# Check last run time
docker compose exec db mysql -u movary -p -e "USE movary; SELECT * FROM server_setting WHERE \`key\` = 'oauth_monitoring_last_run_at';"

# Check for stuck locks
docker compose exec db mysql -u movary -p -e "USE movary; SELECT * FROM server_setting WHERE \`key\` = 'oauth_monitoring_lock';"
```

### Force Immediate Check

Reset last run time and visit any page:

```bash
docker compose exec db mysql -u movary -p -e "USE movary; DELETE FROM server_setting WHERE \`key\` = 'oauth_monitoring_last_run_at';"
```

### Cleanup Old Events

Manually clean up old OAuth monitoring events:

```sql
DELETE FROM user_security_audit_log
WHERE event_type LIKE 'oauth_%'
AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

Or programmatically (future feature):
```bash
docker compose exec app php /app/bin/console.php audit:cleanup --days=90
```

## Support

For issues or questions:

1. Check application logs: `docker compose logs app`
2. Review Admin → Events for error patterns
3. Test OAuth connection manually: Admin → Email Settings → OAuth → Test Connection
4. Create an issue: https://github.com/benjaminmue/pathary/issues

## Future Enhancements

Planned features:
- System Health dashboard tile for OAuth status
- Configurable thresholds via admin UI
- Webhook notifications
- Slack/Discord integration
- Multi-tenant OAuth support
- Manual "Check Now" button in admin UI
