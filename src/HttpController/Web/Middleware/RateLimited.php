<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\Service\RateLimiter;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;

/**
 * Rate limiting middleware to prevent abuse of sensitive endpoints
 *
 * Tracks requests per user or IP address with configurable limits
 * Returns HTTP 429 when rate limit is exceeded
 */
class RateLimited implements MiddlewareInterface
{
    // Default rate limits (can be overridden via environment variables)
    private const int DEFAULT_PASSWORD_CHANGE_MAX = 5;
    private const int DEFAULT_PASSWORD_CHANGE_WINDOW = 300; // 5 minutes

    private const int DEFAULT_USER_CREATE_MAX = 10;
    private const int DEFAULT_USER_CREATE_WINDOW = 60; // 1 minute

    private const int DEFAULT_OAUTH_CALLBACK_MAX = 10;
    private const int DEFAULT_OAUTH_CALLBACK_WINDOW = 600; // 10 minutes

    private const int DEFAULT_TEST_EMAIL_MAX = 5;
    private const int DEFAULT_TEST_EMAIL_WINDOW = 300; // 5 minutes

    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly Authentication $authenticationService,
        private readonly SecurityAuditService $securityAuditService,
    ) {
    }

    /**
     * Apply rate limiting to the request
     *
     * Usage in routes.php:
     * - For password endpoints: will use user ID as key
     * - For OAuth endpoints: will use IP address as key
     *
     * The middleware automatically detects the endpoint type from the path
     *
     * Returns null to allow request to proceed, or Response to block with HTTP 429
     */
    public function __invoke(Request $request) : ?Response
    {
        $path = $request->getPath();

        // Determine rate limit configuration based on endpoint
        [$limitKey, $maxAttempts, $windowSeconds, $limitType] = $this->getRateLimitConfig($path);

        // Check rate limit
        if (!$this->rateLimiter->isAllowed($limitKey, $maxAttempts, $windowSeconds)) {
            // Get time until reset for Retry-After header
            $retryAfter = $this->rateLimiter->getTimeUntilReset($limitKey, $windowSeconds);

            // Log rate limit violation
            $this->logRateLimitViolation($path, $limitKey, $limitType);

            // Return 429 Too Many Requests
            return $this->createRateLimitExceededResponse($retryAfter, $maxAttempts, $windowSeconds);
        }

        // Rate limit not exceeded, proceed to next middleware/controller
        return null;
    }

    /**
     * Get rate limit configuration for the current request
     *
     * @return array [key, maxAttempts, windowSeconds, limitType]
     */
    private function getRateLimitConfig(string $path): array
    {
        // Password change endpoint (user-based)
        if (str_contains($path, '/password')) {
            $userId = $this->getUserId();
            return [
                "password_change_user_{$userId}",
                $this->getEnvInt('RATE_LIMIT_PASSWORD_CHANGE_MAX', self::DEFAULT_PASSWORD_CHANGE_MAX),
                $this->getEnvInt('RATE_LIMIT_PASSWORD_CHANGE_WINDOW', self::DEFAULT_PASSWORD_CHANGE_WINDOW),
                'user'
            ];
        }

        // User creation endpoint (user-based for admin)
        if (str_contains($path, '/users')) {
            $userId = $this->getUserId();
            return [
                "user_create_admin_{$userId}",
                $this->getEnvInt('RATE_LIMIT_USER_CREATE_MAX', self::DEFAULT_USER_CREATE_MAX),
                $this->getEnvInt('RATE_LIMIT_USER_CREATE_WINDOW', self::DEFAULT_USER_CREATE_WINDOW),
                'user'
            ];
        }

        // OAuth callback endpoint (IP-based for security)
        if (str_contains($path, '/oauth/callback')) {
            $ip = $this->getClientIp();
            return [
                "oauth_callback_ip_{$ip}",
                $this->getEnvInt('RATE_LIMIT_OAUTH_CALLBACK_MAX', self::DEFAULT_OAUTH_CALLBACK_MAX),
                $this->getEnvInt('RATE_LIMIT_OAUTH_CALLBACK_WINDOW', self::DEFAULT_OAUTH_CALLBACK_WINDOW),
                'ip'
            ];
        }

        // Test email endpoint (user-based)
        if (str_contains($path, '/email-test')) {
            $userId = $this->getUserId();
            return [
                "test_email_user_{$userId}",
                $this->getEnvInt('RATE_LIMIT_TEST_EMAIL_MAX', self::DEFAULT_TEST_EMAIL_MAX),
                $this->getEnvInt('RATE_LIMIT_TEST_EMAIL_WINDOW', self::DEFAULT_TEST_EMAIL_WINDOW),
                'user'
            ];
        }

        // OAuth authorize endpoint (user-based)
        if (str_contains($path, '/oauth/authorize')) {
            $userId = $this->getUserId();
            return [
                "oauth_authorize_user_{$userId}",
                $this->getEnvInt('RATE_LIMIT_OAUTH_CALLBACK_MAX', self::DEFAULT_OAUTH_CALLBACK_MAX),
                $this->getEnvInt('RATE_LIMIT_OAUTH_CALLBACK_WINDOW', self::DEFAULT_OAUTH_CALLBACK_WINDOW),
                'user'
            ];
        }

        // Default fallback (should not be reached in normal operation)
        $ip = $this->getClientIp();
        return [
            "default_ip_{$ip}",
            10,
            300,
            'ip'
        ];
    }

    /**
     * Get current authenticated user ID
     * Returns 0 if user is not authenticated (fallback)
     */
    private function getUserId(): int
    {
        try {
            if ($this->authenticationService->isUserAuthenticatedWithCookie()) {
                return $this->authenticationService->getCurrentUserId();
            }
        } catch (\Exception $e) {
            // User not authenticated, use fallback
        }

        return 0;
    }

    /**
     * Get client IP address with proxy support
     * Checks X-Forwarded-For header for reverse proxy scenarios
     */
    private function getClientIp(): string
    {
        // Check for X-Forwarded-For header (reverse proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]); // First IP is the original client
        }

        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get integer value from environment variable with fallback
     */
    private function getEnvInt(string $key, int $default): int
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return (int)$value;
    }

    /**
     * Log rate limit violation to security audit log
     */
    private function logRateLimitViolation(string $path, string $limitKey, string $limitType): void
    {
        try {
            $userId = $this->getUserId();
            if ($userId > 0) {
                $ipAddress = $this->getClientIp();
                $this->securityAuditService->log(
                    $userId,
                    'rate_limit_exceeded',
                    $ipAddress !== 'unknown' ? $ipAddress : null,
                    null, // userAgent
                    [
                        'path' => $path,
                        'limit_key' => $limitKey,
                        'limit_type' => $limitType,
                    ]
                );
            }
        } catch (\Exception $e) {
            // Silently fail logging to avoid breaking the request
            // In production, this could be logged to error_log
        }
    }

    /**
     * Create HTTP 429 response with Retry-After header
     */
    private function createRateLimitExceededResponse(int $retryAfter, int $maxAttempts, int $windowSeconds): Response
    {
        $minutes = ceil($windowSeconds / 60);
        $message = sprintf(
            'Rate limit exceeded. You can make %d requests per %d minute%s. Please try again in %d seconds.',
            $maxAttempts,
            $minutes,
            $minutes > 1 ? 's' : '',
            $retryAfter
        );

        // Return JSON response for better client-side handling
        return Response::createJson(
            \Movary\Util\Json::encode(['error' => $message]),
            \Movary\ValueObject\Http\StatusCode::createTooManyRequests(),
            [\Movary\ValueObject\Http\Header::createRetryAfter($retryAfter)]
        );
    }
}
