<?php declare(strict_types=1);

namespace Movary\Service;

use Movary\Util\SessionWrapper;

/**
 * Rate limiting service to prevent abuse of sensitive endpoints
 *
 * Uses in-memory session storage to track request attempts per key
 * Supports configurable limits and time windows
 */
class RateLimiter
{
    private const string SESSION_PREFIX = 'rate_limit_';

    public function __construct(
        private readonly SessionWrapper $sessionWrapper,
    ) {
    }

    /**
     * Check if the rate limit has been exceeded for the given key
     *
     * @param string $key Unique identifier (e.g., "password_change_user_123", "oauth_callback_192.168.1.1")
     * @param int $maxAttempts Maximum number of attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if request is allowed, false if rate limit exceeded
     */
    public function isAllowed(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $sessionKey = self::SESSION_PREFIX . $key;
        $now = time();
        $windowStart = $now - $windowSeconds;

        // Get existing attempts
        $attempts = $this->sessionWrapper->find($sessionKey) ?? [];

        // Clean old attempts outside the window
        $attempts = array_filter(
            $attempts,
            fn($timestamp) => $timestamp > $windowStart
        );

        // Check if limit exceeded
        if (count($attempts) >= $maxAttempts) {
            return false;
        }

        // Record this attempt
        $attempts[] = $now;
        $this->sessionWrapper->set($sessionKey, $attempts);

        return true;
    }

    /**
     * Record an attempt and return whether it's allowed
     * Alias for isAllowed() for clarity
     */
    public function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        return $this->isAllowed($key, $maxAttempts, $windowSeconds);
    }

    /**
     * Get the number of remaining attempts before rate limit is hit
     *
     * @param string $key Unique identifier
     * @param int $maxAttempts Maximum number of attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return int Number of remaining attempts (0 if limit exceeded)
     */
    public function getRemainingAttempts(string $key, int $maxAttempts, int $windowSeconds): int
    {
        $sessionKey = self::SESSION_PREFIX . $key;
        $now = time();
        $windowStart = $now - $windowSeconds;

        // Get existing attempts
        $attempts = $this->sessionWrapper->find($sessionKey) ?? [];

        // Clean old attempts outside the window
        $attempts = array_filter(
            $attempts,
            fn($timestamp) => $timestamp > $windowStart
        );

        $remaining = $maxAttempts - count($attempts);
        return max(0, $remaining);
    }

    /**
     * Get the time in seconds until the rate limit resets
     *
     * @param string $key Unique identifier
     * @param int $windowSeconds Time window in seconds
     * @return int Seconds until reset (0 if no rate limit active)
     */
    public function getTimeUntilReset(string $key, int $windowSeconds): int
    {
        $sessionKey = self::SESSION_PREFIX . $key;
        $attempts = $this->sessionWrapper->find($sessionKey) ?? [];

        if (empty($attempts)) {
            return 0;
        }

        // Find the oldest attempt in the current window
        $oldestAttempt = min($attempts);
        $resetTime = $oldestAttempt + $windowSeconds;
        $now = time();

        $timeUntilReset = $resetTime - $now;
        return (int)max(0, $timeUntilReset);
    }

    /**
     * Clear rate limit for a specific key
     * Useful for testing or manual reset
     */
    public function clear(string $key): void
    {
        $sessionKey = self::SESSION_PREFIX . $key;
        $this->sessionWrapper->unset($sessionKey);
    }

    /**
     * Clear all rate limits
     * Useful for testing
     */
    public function clearAll(): void
    {
        // Note: SessionWrapper doesn't expose a way to iterate keys
        // So we can't implement a full clear without extending SessionWrapper
        // For now, individual keys must be cleared explicitly
    }
}
