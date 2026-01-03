<?php declare(strict_types=1);

namespace Movary\Domain\User\Exception;

use RuntimeException;

class InvalidCredentials extends RuntimeException
{
    public static function create() : self
    {
        return new self('Unknown email/password. Please try again.');
    }
}
