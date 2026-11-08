<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

/**
 * User creation by an admin (per admin user).
 */
final class RateLimitedUserCreate extends RateLimited
{
    protected function policy() : RateLimitPolicy
    {
        return RateLimitPolicy::UserCreate;
    }
}
