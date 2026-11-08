<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

/**
 * Password setup via invitation link (anonymous, per client IP).
 */
final class RateLimitedPasswordSetup extends RateLimited
{
    protected function policy() : RateLimitPolicy
    {
        return RateLimitPolicy::PasswordSetup;
    }
}
