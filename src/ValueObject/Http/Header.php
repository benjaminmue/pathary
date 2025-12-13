<?php declare(strict_types=1);

namespace Movary\ValueObject\Http;

class Header
{
    private function __construct(
        private readonly string $name,
        private readonly string $value,
    ) {
    }

    public static function createContentTypeCsv() : self
    {
        return new self('Content-Type', 'text/csv');
    }

    public static function createContentTypeJson() : self
    {
        return new self('Content-Type', 'application/json');
    }

    public static function createContentTypeSVG() : self
    {
        return new self('Content-Type', 'image/svg+xml');
    }

    public static function createLocation(string $value) : self
    {
        return new self('Location', $value);
    }

    public static function createCache(int $maxAgeInSeconds) : self
    {
        return new self('Cache-Control', 'public, max-age=' . $maxAgeInSeconds);
    }

    public static function createContentType(string $mimeType) : self
    {
        return new self('Content-Type', $mimeType);
    }

    public static function createNoSniff() : self
    {
        return new self('X-Content-Type-Options', 'nosniff');
    }

    public function __toString() : string
    {
        return $this->name . ': ' . $this->value;
    }
}
