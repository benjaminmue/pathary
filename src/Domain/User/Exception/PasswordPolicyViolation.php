<?php declare(strict_types=1);

namespace Movary\Domain\User\Exception;

use RuntimeException;

class PasswordPolicyViolation extends RuntimeException
{
    public static function create(array $violations) : self
    {
        $message = 'Password does not meet policy requirements: ' . implode(', ', $violations);
        return new self($message);
    }

    public static function tooShort(int $minLength) : self
    {
        return new self("Password must be at least {$minLength} characters long");
    }

    public static function missingUppercase() : self
    {
        return new self('Password must contain at least one uppercase letter');
    }

    public static function missingLowercase() : self
    {
        return new self('Password must contain at least one lowercase letter');
    }

    public static function missingNumber() : self
    {
        return new self('Password must contain at least one number');
    }

    public static function missingSpecialCharacter() : self
    {
        return new self('Password must contain at least one special character');
    }
}
