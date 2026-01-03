<?php declare(strict_types=1);

namespace Movary\Domain\User\Exception;

class InvalidPassword extends InvalidCredentials
{
    public static function create() : self
    {
        // Use parent's generic message to prevent user enumeration
        return new self('Unknown email/password. Please try again.');
    }
}
