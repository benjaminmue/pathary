<?php declare(strict_types=1);

namespace Movary\Domain\User\Repository;

use Doctrine\DBAL\Connection;
use Movary\Domain\User\TrustedDeviceEntity;
use Movary\ValueObject\DateTime;

class TrustedDeviceRepository
{
    public function __construct(private readonly Connection $dbConnection)
    {
    }

    public function create(
        int $userId,
        string $tokenHash,
        string $deviceName,
        ?string $userAgent,
        ?string $ipAddress,
        DateTime $expiresAt
    ) : int {
        $this->dbConnection->insert(
            'user_trusted_devices',
            [
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'device_name' => $deviceName,
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
                'expires_at' => (string)$expiresAt,
                'created_at' => (string)DateTime::create(),
            ],
        );

        return (int)$this->dbConnection->lastInsertId();
    }

    public function delete(int $deviceId) : void
    {
        $this->dbConnection->delete(
            'user_trusted_devices',
            [
                'id' => $deviceId,
            ],
        );
    }

    public function deleteAllByUserId(int $userId) : void
    {
        $this->dbConnection->delete(
            'user_trusted_devices',
            [
                'user_id' => $userId,
            ],
        );
    }

    public function deleteExpired() : void
    {
        $this->dbConnection->executeStatement(
            'DELETE FROM `user_trusted_devices` WHERE `expires_at` < ?',
            [(string)DateTime::create()],
        );
    }

    public function findAllByUserId(int $userId) : array
    {
        $rows = $this->dbConnection->fetchAllAssociative(
            'SELECT * FROM `user_trusted_devices` WHERE `user_id` = ? ORDER BY `created_at` DESC',
            [$userId],
        );

        $devices = [];
        foreach ($rows as $row) {
            $devices[] = TrustedDeviceEntity::createFromArray($row);
        }

        return $devices;
    }

    public function findByTokenHash(string $tokenHash) : ?TrustedDeviceEntity
    {
        $result = $this->dbConnection->fetchAssociative(
            'SELECT * FROM `user_trusted_devices` WHERE `token_hash` = ?',
            [$tokenHash],
        );

        if ($result === false) {
            return null;
        }

        return TrustedDeviceEntity::createFromArray($result);
    }

    public function updateLastUsed(int $deviceId) : void
    {
        $this->dbConnection->update(
            'user_trusted_devices',
            [
                'last_used_at' => (string)DateTime::create(),
            ],
            [
                'id' => $deviceId,
            ],
        );
    }
}
