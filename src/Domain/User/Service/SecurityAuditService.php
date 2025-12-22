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
            default => $eventType,
        };
    }
}
