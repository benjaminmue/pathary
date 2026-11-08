<?php declare(strict_types=1);

namespace Movary\Service\Health;

use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Cheap runtime checks shared by the unauthenticated /healthz endpoint and the
 * `selftest` console command. Each check returns a boolean (or a list) and
 * never exposes connection details, so the results are safe to publish.
 */
class RuntimeHealthService
{
    private const string MIGRATION_FILE_PATTERN = '/^(\d{14})_\w+\.php$/';

    public function __construct(
        private readonly Connection $dbConnection,
        private readonly string $migrationDirectory,
        private readonly string $storageDirectory,
    ) {
    }

    public function isDatabaseReachable() : bool
    {
        try {
            return (int)$this->dbConnection->fetchOne('SELECT 1') === 1;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Versions of migration files that are not recorded in the phinxlog table.
     *
     * @return list<string>
     * @throws Throwable if the phinxlog table cannot be read
     */
    public function findPendingMigrationVersions() : array
    {
        $applied = array_map('strval', $this->dbConnection->fetchFirstColumn('SELECT version FROM phinxlog'));

        $pending = array_values(array_diff($this->findMigrationFileVersions(), $applied));
        sort($pending);

        return $pending;
    }

    public function areMigrationsUpToDate() : bool
    {
        try {
            return $this->findPendingMigrationVersions() === [];
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Proves writability by creating and removing a file: is_writable() alone
     * misses read-only mounts and full disks.
     */
    public function isStorageWritable() : bool
    {
        if (is_dir($this->storageDirectory) === false || is_writable($this->storageDirectory) === false) {
            return false;
        }

        $probe = rtrim($this->storageDirectory, '/') . '/.healthcheck-' . bin2hex(random_bytes(8));
        $written = file_put_contents($probe, 'ok') === 2;
        if (is_file($probe) === true) {
            unlink($probe);
        }

        return $written;
    }

    public function getStorageDirectory() : string
    {
        return $this->storageDirectory;
    }

    /** @return list<string> */
    private function findMigrationFileVersions() : array
    {
        $versions = [];
        foreach (scandir($this->migrationDirectory) ?: [] as $file) {
            if (preg_match(self::MIGRATION_FILE_PATTERN, $file, $matches) === 1) {
                $versions[] = $matches[1];
            }
        }

        return $versions;
    }
}
