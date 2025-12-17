<?php declare(strict_types=1);

namespace Movary\Domain\User\Repository;

use Doctrine\DBAL\Connection;
use Movary\ValueObject\DateTime;

class RecoveryCodeRepository
{
    public function __construct(private readonly Connection $dbConnection)
    {
    }

    public function create(int $userId, string $codeHash) : void
    {
        $this->dbConnection->insert(
            'user_recovery_codes',
            [
                'user_id' => $userId,
                'code_hash' => $codeHash,
                'created_at' => (string)DateTime::create(),
            ],
        );
    }

    public function findAllByUserId(int $userId) : array
    {
        return $this->dbConnection->fetchAllAssociative(
            'SELECT * FROM `user_recovery_codes` WHERE `user_id` = ? ORDER BY `created_at` DESC',
            [$userId],
        );
    }

    public function findUnusedByUserId(int $userId) : array
    {
        return $this->dbConnection->fetchAllAssociative(
            'SELECT * FROM `user_recovery_codes` WHERE `user_id` = ? AND `used_at` IS NULL ORDER BY `created_at` DESC',
            [$userId],
        );
    }

    public function markAsUsed(int $codeId) : void
    {
        $this->dbConnection->update(
            'user_recovery_codes',
            [
                'used_at' => (string)DateTime::create(),
            ],
            [
                'id' => $codeId,
            ],
        );
    }

    public function deleteAllByUserId(int $userId) : void
    {
        $this->dbConnection->delete(
            'user_recovery_codes',
            [
                'user_id' => $userId,
            ],
        );
    }

    public function countUnusedByUserId(int $userId) : int
    {
        return (int)$this->dbConnection->fetchOne(
            'SELECT COUNT(*) FROM `user_recovery_codes` WHERE `user_id` = ? AND `used_at` IS NULL',
            [$userId],
        );
    }
}
