<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\SecurityAuditService;
use Movary\Service\RateLimiter;
use Movary\Util\Json;
use Movary\Util\RequestContext;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use RuntimeException;

/**
 * Rate limiting middleware for sensitive endpoints. Returns HTTP 429 when the limit
 * of the route's policy is exceeded.
 *
 * Routes declare their bucket explicitly by using one of the concrete subclasses
 * (e.g. RateLimitedPasswordChange); see RateLimitPolicy for the limits.
 */
abstract class RateLimited implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly Authentication $authenticationService,
        private readonly SecurityAuditService $securityAuditService,
    ) {
    }

    abstract protected function policy() : RateLimitPolicy;

    public function __invoke(Request $request) : ?Response
    {
        $policy = $this->policy();
        $userId = $this->findUserId();
        $limitKey = $policy->isPerUser() === true
            ? $policy->value . '_user_' . $userId
            : $policy->value . '_ip_' . RequestContext::clientIp();

        if ($this->rateLimiter->isAllowed($limitKey, $policy->maxAttempts(), $policy->windowSeconds()) === true) {
            return null;
        }

        $this->logRateLimitViolation($request->getPath(), $limitKey, $policy, $userId);

        return $this->createRateLimitExceededResponse(
            $this->rateLimiter->getTimeUntilReset($limitKey, $policy->windowSeconds()),
            $policy,
        );
    }

    /**
     * Returns 0 for anonymous requests.
     */
    private function findUserId() : int
    {
        if ($this->authenticationService->isUserAuthenticatedWithCookie() === false) {
            return 0;
        }

        try {
            return $this->authenticationService->getCurrentUserId();
        } catch (RuntimeException) {
            // Stale or broken session: treat as anonymous.
            return 0;
        }
    }

    private function logRateLimitViolation(string $path, string $limitKey, RateLimitPolicy $policy, int $userId) : void
    {
        // The audit log is per user; anonymous violations have no user to attach to.
        if ($userId === 0) {
            return;
        }

        try {
            $ipAddress = RequestContext::clientIp();
            $this->securityAuditService->log(
                $userId,
                SecurityAuditService::EVENT_RATE_LIMIT_EXCEEDED,
                $ipAddress !== 'unknown' ? $ipAddress : null,
                null,
                [
                    'path' => $path,
                    'limit_key' => $limitKey,
                    'limit_type' => $policy->isPerUser() === true ? 'user' : 'ip',
                ],
            );
        } catch (\Exception $e) {
            // Logging must never turn a 429 into a 500.
            error_log('Failed to log rate limit event: ' . $e->getMessage());
        }
    }

    private function createRateLimitExceededResponse(int $retryAfter, RateLimitPolicy $policy) : Response
    {
        $minutes = (int)ceil($policy->windowSeconds() / 60);
        $message = sprintf(
            'Rate limit exceeded. You can make %d requests per %d minute%s. Please try again in %d seconds.',
            $policy->maxAttempts(),
            $minutes,
            $minutes > 1 ? 's' : '',
            $retryAfter,
        );

        return Response::createJson(Json::encode(['error' => $message]), StatusCode::createTooManyRequests());
    }
}
