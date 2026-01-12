<?php declare(strict_types=1);

namespace Movary\Api\Omdb\Exception;

use RuntimeException;

class OmdbResourceNotFound extends RuntimeException
{
    public static function create(string $url) : self
    {
        return new self('OMDb resource not found: ' . $url);
    }
}
