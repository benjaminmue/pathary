<?php declare(strict_types=1);

namespace Movary\Service;

use Doctrine\DBAL\Connection;

/**
 * Rate limiter for password setup attempts to prevent brute-force attacks on invitation tokens.
 *
 * Rate limit: 5 failed attempts per 15 minutes per token
 */
class PasswordSetupRateLimiterService
{
    // Rate limit: 5 failed attempts per 15 minutes per token
    private const int MAX_ATTEMPTS_PER_TOKEN = 5;
    private const int WINDOW_MINUTES = 15;

    public function __construct(
        private readonly Connection $dbConnection,
    ) {
    }

    /**
     * Check if the given token has exceeded the rate limit for password setup attempts.
     *
     * @param string $token The invitation token
     * @throws \RuntimeException If rate limit exceeded
     */
    public function checkRateLimit(string $token): void
    {
        $tokenHash = $this->hashToken($token);
        $windowStart = (new \DateTime())->modify('-' . self::WINDOW_MINUTES . ' minutes');

        // Count failed attempts in the last 15 minutes for this token
        $failedAttempts = (int)$this->dbConnection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('password_setup_attempts')
            ->where('token_hash = :token_hash')
            ->andWhere('attempted_at >= :window_start')
            ->andWhere('success = 0')
            ->setParameter('token_hash', $tokenHash)
            ->setParameter('window_start', $windowStart->format('Y-m-d H:i:s'))
            ->executeQuery()
            ->fetchOne();

        if ($failedAttempts >= self::MAX_ATTEMPTS_PER_TOKEN) {
            throw new \RuntimeException(
                sprintf(
                    'Too many password setup attempts. Please wait %d minutes and try again.',
                    self::WINDOW_MINUTES
                )
            );
        }
    }

    /**
     * Log a password setup attempt (success or failure).
     *
     * @param string $token The invitation token
     * @param bool $success Whether the password was successfully set
     * @param string|null $ipAddress Optional IP address of the attempt
     */
    public function logAttempt(string $token, bool $success, ?string $ipAddress = null): void
    {
        $tokenHash = $this->hashToken($token);

        $this->dbConnection->insert('password_setup_attempts', [
            'token_hash' => $tokenHash,
            'attempted_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'success' => $success ? 1 : 0,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Hash the invitation token for secure storage.
     * Uses SHA-256 to avoid storing plaintext tokens in the database.
     *
     * @param string $token The invitation token
     * @return string The hashed token
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
