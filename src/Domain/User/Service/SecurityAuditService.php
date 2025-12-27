<?php declare(strict_types=1);

namespace Movary\Domain\User\Service;

use Movary\Domain\User\Repository\SecurityAuditRepository;
use Movary\ValueObject\DateTime;

class SecurityAuditService
{
    // Event types
    public const EVENT_TOTP_ENABLED = 'totp_enabled';
    public const EVENT_TOTP_DISABLED = 'totp_disabled';
    public const EVENT_PASSWORD_CHANGED = 'password_changed';
    public const EVENT_RECOVERY_CODES_GENERATED = 'recovery_codes_generated';
    public const EVENT_RECOVERY_CODE_USED = 'recovery_code_used';
    public const EVENT_TRUSTED_DEVICE_ADDED = 'trusted_device_added';
    public const EVENT_TRUSTED_DEVICE_REMOVED = 'trusted_device_removed';
    public const EVENT_ALL_TRUSTED_DEVICES_REMOVED = 'all_trusted_devices_removed';
    public const EVENT_LOGIN_SUCCESS = 'login_success';
    public const EVENT_LOGIN_FAILED_PASSWORD = 'login_failed_password';
    public const EVENT_LOGIN_FAILED_TOTP = 'login_failed_totp';
    public const EVENT_LOGIN_FAILED_RECOVERY_CODE = 'login_failed_recovery_code';
    public const EVENT_LOGOUT = 'logout';

    // User Management Event types
    public const EVENT_USER_CREATED = 'user_created';
    public const EVENT_USER_UPDATED = 'user_updated';
    public const EVENT_USER_DELETED = 'user_deleted';
    public const EVENT_USER_PASSWORD_CHANGED_BY_ADMIN = 'user_password_changed_by_admin';
    public const EVENT_USER_WELCOME_EMAIL_SENT = 'user_welcome_email_sent';
    public const EVENT_USER_WELCOME_EMAIL_FAILED = 'user_welcome_email_failed';

    // OAuth Monitoring Event types
    public const EVENT_OAUTH_TOKEN_WARN_45 = 'oauth_token_warn_45';
    public const EVENT_OAUTH_TOKEN_WARN_30 = 'oauth_token_warn_30';
    public const EVENT_OAUTH_TOKEN_WARN_15 = 'oauth_token_warn_15';
    public const EVENT_OAUTH_TOKEN_WARN_DAILY = 'oauth_token_warn_daily';
    public const EVENT_OAUTH_TOKEN_EXPIRED = 'oauth_token_expired';
    public const EVENT_OAUTH_TOKEN_REFRESH_FAILED = 'oauth_token_refresh_failed';
    public const EVENT_OAUTH_TOKEN_REFRESH_RECOVERED = 'oauth_token_refresh_recovered';
    public const EVENT_OAUTH_BANNER_ACKNOWLEDGED = 'oauth_banner_acknowledged';
    public const EVENT_OAUTH_BANNER_SHOWN = 'oauth_banner_shown';

    public function __construct(
        private readonly SecurityAuditRepository $securityAuditRepository,
    ) {
    }

    public function log(
        int $userId,
        string $eventType,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $metadata = null
    ) : void {
        $metadataJson = null;
        if ($metadata !== null) {
            $encoded = json_encode($metadata);
            $metadataJson = $encoded !== false ? $encoded : null;
        }

        $this->securityAuditRepository->create(
            $userId,
            $eventType,
            $ipAddress,
            $userAgent,
            $metadataJson
        );
    }

    public function getRecentEvents(int $userId, int $limit = 20) : array
    {
        $events = $this->securityAuditRepository->findRecentByUserId($userId, $limit);

        // Decode metadata JSON
        foreach ($events as &$event) {
            if ($event['metadata'] !== null) {
                $event['metadata'] = json_decode($event['metadata'], true);
            }
        }

        return $events;
    }

    public function deleteAllEvents(int $userId) : void
    {
        $this->securityAuditRepository->deleteAllByUserId($userId);
    }

    public function cleanupOldEvents(int $daysToKeep = 90) : void
    {
        $olderThan = DateTime::create()->modify('-' . $daysToKeep . ' days');
        $this->securityAuditRepository->deleteOlderThan($olderThan);
    }

    public function getEventTypeLabel(string $eventType) : string
    {
        return match ($eventType) {
            self::EVENT_TOTP_ENABLED => '2FA Enabled',
            self::EVENT_TOTP_DISABLED => '2FA Disabled',
            self::EVENT_PASSWORD_CHANGED => 'Password Changed',
            self::EVENT_RECOVERY_CODES_GENERATED => 'Recovery Codes Generated',
            self::EVENT_RECOVERY_CODE_USED => 'Recovery Code Used',
            self::EVENT_TRUSTED_DEVICE_ADDED => 'Trusted Device Added',
            self::EVENT_TRUSTED_DEVICE_REMOVED => 'Trusted Device Removed',
            self::EVENT_ALL_TRUSTED_DEVICES_REMOVED => 'All Trusted Devices Removed',
            self::EVENT_LOGIN_SUCCESS => 'Login Success',
            self::EVENT_LOGIN_FAILED_PASSWORD => 'Login Failed (Password)',
            self::EVENT_LOGIN_FAILED_TOTP => 'Login Failed (2FA)',
            self::EVENT_LOGIN_FAILED_RECOVERY_CODE => 'Login Failed (Recovery Code)',
            self::EVENT_LOGOUT => 'Logout',
            self::EVENT_USER_CREATED => 'User Created',
            self::EVENT_USER_UPDATED => 'User Updated',
            self::EVENT_USER_DELETED => 'User Deleted',
            self::EVENT_USER_PASSWORD_CHANGED_BY_ADMIN => 'Password Changed by Admin',
            self::EVENT_USER_WELCOME_EMAIL_SENT => 'Welcome Email Sent',
            self::EVENT_USER_WELCOME_EMAIL_FAILED => 'Welcome Email Failed',
            self::EVENT_OAUTH_TOKEN_WARN_45 => 'OAuth Token Warning (45 Days)',
            self::EVENT_OAUTH_TOKEN_WARN_30 => 'OAuth Token Warning (30 Days)',
            self::EVENT_OAUTH_TOKEN_WARN_15 => 'OAuth Token Warning (15 Days)',
            self::EVENT_OAUTH_TOKEN_WARN_DAILY => 'OAuth Token Warning (Daily Alert)',
            self::EVENT_OAUTH_TOKEN_EXPIRED => 'OAuth Token Expired',
            self::EVENT_OAUTH_TOKEN_REFRESH_FAILED => 'OAuth Token Refresh Failed',
            self::EVENT_OAUTH_TOKEN_REFRESH_RECOVERED => 'OAuth Token Refresh Recovered',
            self::EVENT_OAUTH_BANNER_ACKNOWLEDGED => 'OAuth Banner Acknowledged',
            self::EVENT_OAUTH_BANNER_SHOWN => 'OAuth Banner Shown',
            default => $eventType,
        };
    }
}
