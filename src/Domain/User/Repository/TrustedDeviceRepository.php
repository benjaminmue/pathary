<?php declare(strict_types=1);

namespace Movary\Domain\User\Repository;

use Doctrine\DBAL\Connection;
use Movary\ValueObject\DateTime;

class TrustedDeviceRepository
{
    public function __construct(private readonly Connection $dbConnection)
    {
    }

    public function create(int $userId, string $deviceToken, string $deviceName, string $deviceFingerprint, DateTime $expiresAt) : void
    {
        $this->dbConnection->insert(
            'user_trusted_devices',
            [
                'user_id' => $userId,
                'device_token' => $deviceToken,
                'device_name' => $deviceName,
                'device_fingerprint' => $deviceFingerprint,
                'expires_at' => (string)$expiresAt,
                'created_at' => (string)DateTime::create(),
            ],
        );
    }

    public function findByToken(string $deviceToken) : ?array
    {
        $result = $this->dbConnection->fetchAssociative(
            'SELECT * FROM `user_trusted_devices` WHERE `device_token` = ?',
            [$deviceToken],
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }

    public function findAllByUserId(int $userId) : array
    {
        return $this->dbConnection->fetchAllAssociative(
            'SELECT * FROM `user_trusted_devices` WHERE `user_id` = ? ORDER BY `created_at` DESC',
            [$userId],
        );
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

    public function delete(int $deviceId) : void
    {
        $this->dbConnection->delete(
            'user_trusted_devices',
            [
                'id' => $deviceId,
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

    public function deleteAllByUserId(int $userId) : void
    {
        $this->dbConnection->delete(
            'user_trusted_devices',
            [
                'user_id' => $userId,
            ],
        );
    }
}
