<?php declare(strict_types=1);

namespace Movary\Domain\User\Repository;

use Doctrine\DBAL\Connection;
use Movary\ValueObject\DateTime;

class SecurityAuditRepository
{
    public function __construct(private readonly Connection $dbConnection)
    {
    }

    public function create(
        int $userId,
        string $eventType,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $metadata = null
    ) : void {
        $this->dbConnection->insert(
            'user_security_audit_log',
            [
                'user_id' => $userId,
                'event_type' => $eventType,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'metadata' => $metadata,
                'created_at' => (string)DateTime::create(),
            ],
        );
    }

    public function findRecentByUserId(int $userId, int $limit = 20) : array
    {
        return $this->dbConnection->fetchAllAssociative(
            sprintf('SELECT * FROM `user_security_audit_log` WHERE `user_id` = ? ORDER BY `created_at` DESC LIMIT %d', $limit),
            [$userId],
        );
    }

    public function deleteAllByUserId(int $userId) : void
    {
        $this->dbConnection->delete(
            'user_security_audit_log',
            [
                'user_id' => $userId,
            ],
        );
    }

    public function deleteOlderThan(DateTime $olderThan) : void
    {
        $this->dbConnection->executeStatement(
            'DELETE FROM `user_security_audit_log` WHERE `created_at` < ?',
            [(string)$olderThan],
        );
    }
}
