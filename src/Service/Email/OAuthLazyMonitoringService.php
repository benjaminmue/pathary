<?php declare(strict_types=1);

namespace Movary\Service\Email;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Lazy monitoring service that triggers OAuth monitoring on page loads
 *
 * Monitors OAuth health automatically when users visit the site,
 * without requiring cron jobs or scheduled tasks.
 *
 * Uses database locking to prevent concurrent runs and tracks
 * last run time to avoid excessive monitoring.
 */
class OAuthLazyMonitoringService
{
    // Run monitoring at most every 6 hours
    private const MIN_INTERVAL_SECONDS = 21600; // 6 hours

    // Lock timeout (prevent stuck locks)
    private const LOCK_TIMEOUT_SECONDS = 300; // 5 minutes

    public function __construct(
        private readonly Connection $dbConnection,
        private readonly OAuthMonitoringService $oauthMonitoringService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Check if monitoring should run and trigger if needed
     *
     * Called on page load. Uses database locking to prevent
     * concurrent execution and checks last run time.
     *
     * @return bool True if monitoring was triggered, false if skipped
     */
    public function triggerIfNeeded() : bool
    {
        try {
            // Quick check: Should we even try?
            if (!$this->shouldRunMonitoring()) {
                return false;
            }

            // Try to acquire lock (non-blocking)
            if (!$this->acquireLock()) {
                // Another request is already running monitoring
                $this->logger->debug('OAuth monitoring already running (lock held)');
                return false;
            }

            try {
                $this->logger->info('Triggering lazy OAuth monitoring');

                // Run monitoring
                $result = $this->oauthMonitoringService->runMonitoring();

                // Update last run time
                $this->updateLastRunTime();

                $this->logger->info('Lazy OAuth monitoring completed', [
                    'alert_level' => $result['alert_level'],
                    'success' => $result['success'],
                ]);

                return true;
            } finally {
                // Always release lock
                $this->releaseLock();
            }
        } catch (\Exception $e) {
            $this->logger->error('Lazy OAuth monitoring failed', [
                'error' => $e->getMessage(),
            ]);

            // Release lock on error
            try {
                $this->releaseLock();
            } catch (\Exception $releaseError) {
                $this->logger->error('Failed to release monitoring lock', [
                    'error' => $releaseError->getMessage(),
                ]);
            }

            return false;
        }
    }

    /**
     * Check if monitoring should run based on last run time
     */
    private function shouldRunMonitoring() : bool
    {
        $lastRunTime = $this->getLastRunTime();

        if ($lastRunTime === null) {
            // Never run before
            return true;
        }

        $now = time();
        $lastRun = strtotime($lastRunTime);
        $elapsed = $now - $lastRun;

        // Run if at least MIN_INTERVAL_SECONDS have passed
        return $elapsed >= self::MIN_INTERVAL_SECONDS;
    }

    /**
     * Get last monitoring run time from database
     */
    private function getLastRunTime() : ?string
    {
        $result = $this->dbConnection->fetchOne(
            "SELECT value FROM server_setting WHERE `key` = 'oauth_monitoring_last_run_at'"
        );

        return $result !== false ? (string)$result : null;
    }

    /**
     * Update last monitoring run time
     */
    private function updateLastRunTime() : void
    {
        $now = date('Y-m-d H:i:s');

        // Use INSERT ... ON DUPLICATE KEY UPDATE for MySQL
        $this->dbConnection->executeStatement(
            <<<SQL
            INSERT INTO server_setting (`key`, `value`)
            VALUES ('oauth_monitoring_last_run_at', ?)
            ON DUPLICATE KEY UPDATE
                `value` = VALUES(`value`)
            SQL,
            [$now]
        );
    }

    /**
     * Acquire monitoring lock (non-blocking)
     *
     * Returns true if lock acquired, false if already held
     */
    private function acquireLock() : bool
    {
        try {
            // Try to get current lock
            $lockData = $this->getLockData();

            if ($lockData !== null) {
                // Check if lock is stale (timeout exceeded)
                // Lock value stores timestamp
                $lockTime = (int)($lockData['value'] ?? 0);
                $now = time();
                $lockAge = $now - $lockTime;

                if ($lockAge < self::LOCK_TIMEOUT_SECONDS) {
                    // Lock is fresh, can't acquire
                    return false;
                }

                // Lock is stale, release it and try again
                $this->logger->warning('Releasing stale OAuth monitoring lock', [
                    'lock_age_seconds' => $lockAge,
                ]);
                $this->releaseLock();
            }

            // Try to insert lock (use timestamp as value for stale lock detection)
            $this->dbConnection->executeStatement(
                "INSERT INTO server_setting (`key`, `value`) VALUES (?, ?)",
                ['oauth_monitoring_lock', (string)time()]
            );

            return true;
        } catch (\Exception $e) {
            // Lock already exists (race condition)
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return false;
            }

            // Other error, rethrow
            throw $e;
        }
    }

    /**
     * Release monitoring lock
     */
    private function releaseLock() : void
    {
        $this->dbConnection->executeStatement(
            "DELETE FROM server_setting WHERE `key` = 'oauth_monitoring_lock'"
        );
    }

    /**
     * Get lock data if exists
     */
    private function getLockData() : ?array
    {
        $result = $this->dbConnection->fetchAssociative(
            "SELECT * FROM server_setting WHERE `key` = 'oauth_monitoring_lock'"
        );

        return $result !== false ? $result : null;
    }

    /**
     * Force monitoring to run on next page load
     *
     * Useful for testing or after configuration changes
     */
    public function resetLastRunTime() : void
    {
        $this->dbConnection->executeStatement(
            "DELETE FROM server_setting WHERE `key` = 'oauth_monitoring_last_run_at'"
        );

        $this->logger->info('OAuth monitoring last run time reset');
    }

    /**
     * Get status information for debugging
     *
     * @return array{last_run_at: string|null, should_run: bool, lock_held: bool}
     */
    public function getStatus() : array
    {
        return [
            'last_run_at' => $this->getLastRunTime(),
            'should_run' => $this->shouldRunMonitoring(),
            'lock_held' => $this->getLockData() !== null,
        ];
    }
}
