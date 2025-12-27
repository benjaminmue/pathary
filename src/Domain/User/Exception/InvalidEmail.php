<?php declare(strict_types=1);

namespace Movary\Domain\User\Exception;

use RuntimeException;

class InvalidEmail extends RuntimeException
{
    public static function create() : self
    {
        return new self('Invalid email address.');
    }
}
