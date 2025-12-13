<?php declare(strict_types=1);

namespace Movary\ValueObject;

use RuntimeException;

class PopcornRating
{
    private const array ALLOWED_RATINGS = [1, 2, 3, 4, 5, 6, 7];

    private function __construct(private readonly int $rating)
    {
        if (in_array($this->rating, self::ALLOWED_RATINGS, true) === false) {
            throw new RuntimeException('Invalid popcorn rating: ' . $this->rating . '. Allowed values: 1-7');
        }
    }

    public static function create(int $rating) : self
    {
        return new self($rating);
    }

    public static function createFromString(string $rating) : self
    {
        return new self((int)$rating);
    }

    public function __toString() : string
    {
        return (string)$this->rating;
    }

    public function asInt() : int
    {
        return $this->rating;
    }

    public function isEqual(PopcornRating $popcornRating) : bool
    {
        return $this->asInt() === $popcornRating->asInt();
    }
}
