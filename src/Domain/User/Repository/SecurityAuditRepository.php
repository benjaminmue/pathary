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

    // Admin Methods

    public function findDistinctEventTypes() : array
    {
        return $this->dbConnection->fetchFirstColumn(
            'SELECT DISTINCT `event_type` FROM `user_security_audit_log` ORDER BY `event_type` ASC'
        );
    }

    public function findWithFilters(
        ?string $eventType = null,
        ?string $searchQuery = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        int $limit = 50,
        int $offset = 0
    ) : array {
        $conditions = [];
        $params = [];

        if ($eventType !== null) {
            $conditions[] = '`event_type` = ?';
            $params[] = $eventType;
        }

        if ($searchQuery !== null && $searchQuery !== '') {
            $conditions[] = '(`event_type` LIKE ? OR `ip_address` LIKE ? OR `user_agent` LIKE ? OR `metadata` LIKE ?)';
            $searchParam = '%' . $searchQuery . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        if ($dateFrom !== null) {
            $conditions[] = '`created_at` >= ?';
            $params[] = $dateFrom;
        }

        if ($dateTo !== null) {
            $conditions[] = '`created_at` <= ?';
            $params[] = $dateTo;
        }

        if ($userId !== null) {
            $conditions[] = '`user_id` = ?';
            $params[] = $userId;
        }

        if ($ipAddress !== null && $ipAddress !== '') {
            $conditions[] = '`ip_address` = ?';
            $params[] = $ipAddress;
        }

        $whereClause = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = sprintf(
            'SELECT l.*, u.name as user_name FROM `user_security_audit_log` l LEFT JOIN `user` u ON l.user_id = u.id %s ORDER BY l.`created_at` DESC LIMIT %d OFFSET %d',
            $whereClause,
            $limit,
            $offset
        );

        return $this->dbConnection->fetchAllAssociative($sql, $params);
    }

    public function countWithFilters(
        ?string $eventType = null,
        ?string $searchQuery = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $userId = null,
        ?string $ipAddress = null
    ) : int {
        $conditions = [];
        $params = [];

        if ($eventType !== null) {
            $conditions[] = '`event_type` = ?';
            $params[] = $eventType;
        }

        if ($searchQuery !== null && $searchQuery !== '') {
            $conditions[] = '(`event_type` LIKE ? OR `ip_address` LIKE ? OR `user_agent` LIKE ? OR `metadata` LIKE ?)';
            $searchParam = '%' . $searchQuery . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        if ($dateFrom !== null) {
            $conditions[] = '`created_at` >= ?';
            $params[] = $dateFrom;
        }

        if ($dateTo !== null) {
            $conditions[] = '`created_at` <= ?';
            $params[] = $dateTo;
        }

        if ($userId !== null) {
            $conditions[] = '`user_id` = ?';
            $params[] = $userId;
        }

        if ($ipAddress !== null && $ipAddress !== '') {
            $conditions[] = '`ip_address` = ?';
            $params[] = $ipAddress;
        }

        $whereClause = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = sprintf('SELECT COUNT(*) FROM `user_security_audit_log` %s', $whereClause);

        return (int)$this->dbConnection->fetchOne($sql, $params);
    }

    public function findById(int $id) : ?array
    {
        $result = $this->dbConnection->fetchAssociative(
            'SELECT l.*, u.name as user_name FROM `user_security_audit_log` l LEFT JOIN `user` u ON l.user_id = u.id WHERE l.`id` = ?',
            [$id]
        );

        return $result !== false ? $result : null;
    }
}
