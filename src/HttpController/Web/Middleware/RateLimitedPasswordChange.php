<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

/**
 * Password change of a logged-in user (per user).
 */
final class RateLimitedPasswordChange extends RateLimited
{
    protected function policy() : RateLimitPolicy
    {
        return RateLimitPolicy::PasswordChange;
    }
}
