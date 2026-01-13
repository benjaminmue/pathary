<?php declare(strict_types=1);

namespace Movary\Service;

use Doctrine\DBAL\Connection;

class SetupService
{
    private const string SETUP_COMPLETED_KEY = 'setup_completed';

    public function __construct(
        private readonly Connection $dbConnection,
    ) {
    }

    public function isSetupCompleted() : bool
    {
        $value = $this->dbConnection->fetchOne(
            'SELECT value FROM server_setting WHERE `key` = ?',
            [self::SETUP_COMPLETED_KEY],
        );

        return $value === 'true';
    }

    public function markSetupCompleted() : void
    {
        $this->dbConnection->executeStatement(
            'UPDATE server_setting SET value = ? WHERE `key` = ?',
            ['true', self::SETUP_COMPLETED_KEY],
        );
    }

    public function canAccessSetupWizard() : bool
    {
        // Allow access to wizard only if setup is not completed
        // This allows recovery if wizard crashes after user creation but before completion

        return !$this->isSetupCompleted();
    }

    public function getUserCount() : int
    {
        return (int)$this->dbConnection->fetchOne('SELECT COUNT(*) FROM user');
    }
}
