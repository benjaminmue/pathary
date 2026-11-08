<?php declare(strict_types=1);

namespace Movary\Service;

use Doctrine\DBAL\Connection;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\Domain\User\Service\TrustedDeviceService;
use Movary\ValueObject\DateTime;

/**
 * Deletes security bookkeeping that has outlived its purpose, so tables that
 * grow on every login do not grow forever and personal data (IP addresses,
 * user agents) is not kept longer than needed.
 */
class DataRetentionService
{
    // Rate limiters only look back 15 minutes; 30 days leave room for abuse analysis.
    public const int ATTEMPT_RETENTION_DAYS = 30;

    // Shown to users as their security history (Profile -> Security).
    public const int AUDIT_LOG_RETENTION_DAYS = 90;

    public function __construct(
        private readonly Connection $dbConnection,
        private readonly SecurityAuditService $securityAuditService,
        private readonly TrustedDeviceService $trustedDeviceService,
    ) {
    }

    /**
     * @return array<string, int|null> deleted rows per table (null when the driver does not report a count)
     */
    public function prune() : array
    {
        $now = DateTime::create();
        $attemptCutoff = (string)$now->modify('-' . self::ATTEMPT_RETENTION_DAYS . ' days');

        $deleted = [
            'login_attempts' => (int)$this->dbConnection->executeStatement(
                'DELETE FROM login_attempts WHERE attempted_at < ?',
                [$attemptCutoff],
            ),
            'password_setup_attempts' => (int)$this->dbConnection->executeStatement(
                'DELETE FROM password_setup_attempts WHERE attempted_at < ?',
                [$attemptCutoff],
            ),
            'user_auth_token' => (int)$this->dbConnection->executeStatement(
                'DELETE FROM user_auth_token WHERE expiration_date < ?',
                [(string)$now],
            ),
        ];

        // These two delete through their own services, which do not report counts.
        $this->securityAuditService->cleanupOldEvents(self::AUDIT_LOG_RETENTION_DAYS);
        $deleted['user_security_audit_log'] = null;
        $this->trustedDeviceService->cleanupExpiredDevices();
        $deleted['user_trusted_devices'] = null;

        return $deleted;
    }
}
