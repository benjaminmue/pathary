<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

/**
 * Test emails sent from the admin server settings (per admin user).
 */
final class RateLimitedTestEmail extends RateLimited
{
    protected function policy() : RateLimitPolicy
    {
        return RateLimitPolicy::TestEmail;
    }
}
