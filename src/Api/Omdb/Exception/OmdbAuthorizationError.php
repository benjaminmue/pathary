<?php declare(strict_types=1);

namespace Movary\Api\Omdb\Exception;

use RuntimeException;

class OmdbAuthorizationError extends RuntimeException
{
    public static function create() : self
    {
        return new self('OMDb API authorization error. Please check your API key.');
    }
}
