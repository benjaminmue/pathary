<?php declare(strict_types=1);

namespace Movary\Service;

use DateTime;
use Doctrine\DBAL\Connection;
use Movary\Service\Exception\LoginRateLimitExceeded;

/**
 * Persistent rate limiter for login attempts to slow down brute-force and
 * credential-stuffing attacks against the token endpoint.
 *
 * Unlike the session-based RateLimiter, failed attempts are stored in the
 * database, so an attacker cannot reset the counter by discarding the session.
 *
 * Two independent limits apply:
 * - per IP: tight, stops a single source from guessing quickly;
 * - per account: looser, stops an attacker who rotates IPs from guessing one
 *   account's password. It is deliberately higher than the IP limit so a third
 *   party can only lock a victim out by sustaining a large volume of requests.
 */
class LoginRateLimiterService
{
    private const int MAX_FAILED_ATTEMPTS_PER_IP = 10;

    private const int MAX_FAILED_ATTEMPTS_PER_ACCOUNT = 25;

    private const int WINDOW_MINUTES = 15;

    public function __construct(
        private readonly Connection $dbConnection,
    ) {
    }

    /**
     * @throws LoginRateLimitExceeded if the IP or the account has too many recent failed attempts
     */
    public function ensureNotRateLimited(string $ipAddress, string $accountIdentifier) : void
    {
        if ($this->countRecentFailures('ip_hash', $this->hash($ipAddress)) >= self::MAX_FAILED_ATTEMPTS_PER_IP
            || $this->countRecentFailures('account_hash', $this->hashAccount($accountIdentifier)) >= self::MAX_FAILED_ATTEMPTS_PER_ACCOUNT) {
            throw LoginRateLimitExceeded::create(self::WINDOW_MINUTES);
        }
    }

    public function logAttempt(string $ipAddress, string $accountIdentifier, bool $success) : void
    {
        $this->dbConnection->insert('login_attempts', [
            'ip_hash' => $this->hash($ipAddress),
            'account_hash' => $this->hashAccount($accountIdentifier),
            'attempted_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'success' => $success === true ? 1 : 0,
        ]);
    }

    /**
     * @param 'ip_hash'|'account_hash' $column Fixed column name, never user input
     */
    private function countRecentFailures(string $column, string $hash) : int
    {
        $windowStart = (new DateTime())->modify('-' . self::WINDOW_MINUTES . ' minutes');

        return (int)$this->dbConnection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('login_attempts')
            ->where($column . ' = :hash')
            ->andWhere('attempted_at >= :window_start')
            ->andWhere('success = 0')
            ->setParameter('hash', $hash)
            ->setParameter('window_start', $windowStart->format('Y-m-d H:i:s'))
            ->executeQuery()
            ->fetchOne();
    }

    private function hash(string $value) : string
    {
        return hash('sha256', $value);
    }

    private function hashAccount(string $accountIdentifier) : string
    {
        // Emails are matched case-insensitively at login, so the counter must be too.
        return $this->hash(strtolower(trim($accountIdentifier)));
    }
}
