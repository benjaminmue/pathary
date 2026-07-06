<?php declare(strict_types=1);

namespace Movary\Service\Exception;

use RuntimeException;

class LoginRateLimitExceeded extends RuntimeException
{
    public static function create(int $windowMinutes) : self
    {
        return new self(
            sprintf('Too many failed login attempts. Please wait %d minutes and try again.', $windowMinutes),
        );
    }
}
