<?php declare(strict_types=1);

namespace Movary\Service\Email\Exception;

use RuntimeException;

class EmailRateLimitExceededException extends RuntimeException
{
    public static function forRecipient(string $email, int $limit): self
    {
        return new self(sprintf(
            'Email rate limit exceeded for %s. Maximum %d emails per hour per recipient.',
            $email,
            $limit
        ));
    }

    public static function forAdminHourly(int $limit): self
    {
        return new self(sprintf(
            'Email rate limit exceeded. Maximum %d emails per hour per admin.',
            $limit
        ));
    }

    public static function forAdminDaily(int $limit): self
    {
        return new self(sprintf(
            'Email rate limit exceeded. Maximum %d emails per day per admin.',
            $limit
        ));
    }
}
