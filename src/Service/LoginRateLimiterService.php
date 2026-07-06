<?php declare(strict_types=1);

namespace Movary\Service;

use DateTime;
use Doctrine\DBAL\Connection;
use Movary\Service\Exception\LoginRateLimitExceeded;

/**
 * Persistent, IP-based rate limiter for login attempts to slow down brute-force
 * and credential-stuffing attacks against the token endpoint.
 *
 * Unlike the session-based RateLimiter, failed attempts are stored in the
 * database, so an attacker cannot reset the counter by discarding the session.
 * Keyed by IP (not account) to avoid a victim being locked out by a third party.
 */
class LoginRateLimiterService
{
    private const int MAX_FAILED_ATTEMPTS = 10;

    private const int WINDOW_MINUTES = 15;

    public function __construct(
        private readonly Connection $dbConnection,
    ) {
    }

    /**
     * @throws LoginRateLimitExceeded if the IP has too many recent failed attempts
     */
    public function ensureNotRateLimited(string $ipAddress) : void
    {
        $windowStart = (new DateTime())->modify('-' . self::WINDOW_MINUTES . ' minutes');

        $failedAttempts = (int)$this->dbConnection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('login_attempts')
            ->where('ip_hash = :ip_hash')
            ->andWhere('attempted_at >= :window_start')
            ->andWhere('success = 0')
            ->setParameter('ip_hash', $this->hashIp($ipAddress))
            ->setParameter('window_start', $windowStart->format('Y-m-d H:i:s'))
            ->executeQuery()
            ->fetchOne();

        if ($failedAttempts >= self::MAX_FAILED_ATTEMPTS) {
            throw LoginRateLimitExceeded::create(self::WINDOW_MINUTES);
        }
    }

    public function logAttempt(string $ipAddress, bool $success) : void
    {
        $this->dbConnection->insert('login_attempts', [
            'ip_hash' => $this->hashIp($ipAddress),
            'attempted_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'success' => $success === true ? 1 : 0,
            'ip_address' => $ipAddress,
        ]);
    }

    private function hashIp(string $ipAddress) : string
    {
        return hash('sha256', $ipAddress);
    }
}
