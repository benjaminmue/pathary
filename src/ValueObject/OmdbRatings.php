<?php declare(strict_types=1);

namespace Movary\ValueObject;

class OmdbRatings
{
    private function __construct(
        private readonly ?float $imdbRating,
        private readonly ?int $imdbVotes,
        private readonly ?int $rottenTomatoesRating,
        private readonly ?int $metacriticRating,
    ) {
    }

    public static function create(
        ?float $imdbRating,
        ?int $imdbVotes,
        ?int $rottenTomatoesRating,
        ?int $metacriticRating,
    ) : self {
        return new self($imdbRating, $imdbVotes, $rottenTomatoesRating, $metacriticRating);
    }

    public function getImdbRating() : ?float
    {
        return $this->imdbRating;
    }

    public function getImdbVotes() : ?int
    {
        return $this->imdbVotes;
    }

    public function getRottenTomatoesRating() : ?int
    {
        return $this->rottenTomatoesRating;
    }

    public function getMetacriticRating() : ?int
    {
        return $this->metacriticRating;
    }
}
