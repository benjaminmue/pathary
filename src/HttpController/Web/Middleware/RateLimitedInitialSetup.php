<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

/**
 * First-run setup wizard (anonymous, per client IP).
 */
final class RateLimitedInitialSetup extends RateLimited
{
    protected function policy() : RateLimitPolicy
    {
        return RateLimitPolicy::InitialSetup;
    }
}
