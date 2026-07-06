<?php declare(strict_types=1);

namespace Movary\Domain\Movie;

use Movary\Api\Trakt\ValueObject\TraktId;
use Movary\ValueObject\DateTime;

class MovieEntity
{
    private function __construct(
        private readonly int $id,
        private readonly string $title,
        private readonly ?TraktId $traktId,
        private readonly ?string $imdbId,
        private readonly int $tmdbId,
        private readonly ?string $posterPath,
        private readonly ?string $overview,
        private readonly ?string $tagline,
        private readonly ?string $originalLanguage,
        private readonly ?int $runtime,
        private readonly ?DateTime $releaseDate,
        private readonly ?float $tmdbVoteAverage,
        private readonly ?int $tmdbVoteCount,
        private readonly ?string $tmdbPosterPath,
        private readonly ?float $imdbRatingAverage,
        private readonly ?int $imdbRatingVoteCount,
        private readonly ?int $rtRatingAverage,
        private readonly ?int $rtRatingVoteCount,
        private readonly ?DateTime $updatedAtTmdb,
        private readonly ?DateTime $updatedAtImdb,
        private readonly ?DateTime $updatedAtOmdb,
    ) {
    }

    public static function createFromArray(array $data) : self
    {
        return new self(
            (int)$data['id'],
            $data['title'],
            empty($data['trakt_id']) === false ? TraktId::createFromString((string)$data['trakt_id']) : null,
            empty($data['imdb_id']) === false ? (string)$data['imdb_id'] : null,
            (int)$data['tmdb_id'],
            $data['poster_path'],
            $data['overview'],
            $data['tagline'],
            $data['original_language'],
            self::toIntOrNull($data['runtime']),
            self::toDateTimeOrNull($data['release_date']),
            self::toFloatOrNull($data['tmdb_vote_average']),
            self::toIntOrNull($data['tmdb_vote_count']),
            $data['tmdb_poster_path'],
            self::toFloatOrNull($data['imdb_rating_average']),
            self::toIntOrNull($data['imdb_rating_vote_count']),
            self::toIntOrNull($data['rt_rating_average']),
            self::toIntOrNull($data['rt_rating_vote_count']),
            self::toDateTimeOrNull($data['updated_at_tmdb']),
            self::toDateTimeOrNull($data['updated_at_imdb']),
            self::toDateTimeOrNull($data['updated_at_omdb']),
        );
    }

    private static function toIntOrNull(mixed $value) : ?int
    {
        return $value === null ? null : (int)$value;
    }

    private static function toFloatOrNull(mixed $value) : ?float
    {
        return $value === null ? null : (float)$value;
    }

    private static function toDateTimeOrNull(?string $value) : ?DateTime
    {
        return $value === null ? null : DateTime::createFromString($value);
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getImdbId() : ?string
    {
        return $this->imdbId;
    }

    public function getImdbRatingAverage() : ?float
    {
        return $this->imdbRatingAverage;
    }

    public function getImdbVoteCount() : ?int
    {
        return $this->imdbRatingVoteCount;
    }

    public function getOriginalLanguage() : ?string
    {
        return $this->originalLanguage;
    }

    public function getOverview() : ?string
    {
        return $this->overview;
    }

    public function getPosterPath() : ?string
    {
        return $this->posterPath;
    }

    public function getReleaseDate() : ?DateTime
    {
        return $this->releaseDate;
    }

    public function getRuntime() : ?int
    {
        return $this->runtime;
    }

    public function getTagline() : ?string
    {
        return $this->tagline;
    }

    public function getTitle() : string
    {
        return $this->title;
    }

    public function getTmdbId() : int
    {
        return $this->tmdbId;
    }

    public function getTmdbPosterPath() : ?string
    {
        return $this->tmdbPosterPath;
    }

    public function getTmdbVoteAverage() : ?float
    {
        return $this->tmdbVoteAverage;
    }

    public function getTmdbVoteCount() : ?int
    {
        return $this->tmdbVoteCount;
    }

    public function getTraktId() : ?TraktId
    {
        return $this->traktId;
    }

    public function getUpdatedAtImdb() : ?DateTime
    {
        return $this->updatedAtImdb;
    }

    public function getUpdatedAtTmdb() : ?DateTime
    {
        return $this->updatedAtTmdb;
    }

    public function getRtRatingAverage() : ?int
    {
        return $this->rtRatingAverage;
    }

    public function getRtRatingVoteCount() : ?int
    {
        return $this->rtRatingVoteCount;
    }

    public function getUpdatedAtOmdb() : ?DateTime
    {
        return $this->updatedAtOmdb;
    }
}
