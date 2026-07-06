<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Service\Omdb;

use Movary\Api\Omdb\OmdbApi;
use Movary\Domain\Movie\MovieApi;
use Movary\Domain\Movie\MovieEntity;
use Movary\Domain\Movie\MovieRepository;
use Movary\Service\Omdb\OmdbMovieRatingSync;
use Movary\ValueObject\OmdbRatings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(OmdbMovieRatingSync::class)]
class OmdbMovieRatingSyncTest extends TestCase
{
    private OmdbApi&MockObject $omdbApi;

    private MovieApi&MockObject $movieApi;

    private MovieRepository&MockObject $movieRepository;

    private OmdbMovieRatingSync $subject;

    protected function setUp() : void
    {
        $this->omdbApi = $this->createMock(OmdbApi::class);
        $this->movieApi = $this->createMock(MovieApi::class);
        $this->movieRepository = $this->createMock(MovieRepository::class);

        $this->subject = new OmdbMovieRatingSync(
            $this->omdbApi,
            $this->movieApi,
            $this->movieRepository,
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testEmptyOmdbResponseDoesNotWipeStoredRatings() : void
    {
        $this->movieApi->method('findById')->willReturn($this->createMovieWithRatings(9.3, 2000000, 89));

        // OMDb answered with HTTP 200 but returned no ratings at all.
        $this->omdbApi->method('fetchMovieRatings')->willReturn(OmdbRatings::create(null, null, null, null));

        // The stored ratings must be kept, so no destructive update is written.
        $this->movieRepository->expects(self::never())->method('updateOmdbRatings');

        $this->subject->syncMovieRating(1);
    }

    public function testPartialOmdbResponseKeepsMissingRottenTomatoesRating() : void
    {
        $this->movieApi->method('findById')->willReturn($this->createMovieWithRatings(9.3, 2000000, 89));

        // OMDb returned an updated IMDb rating but omitted the Rotten Tomatoes entry.
        $this->omdbApi->method('fetchMovieRatings')->willReturn(OmdbRatings::create(9.5, 2100000, null, null));

        $this->movieRepository->expects(self::once())
            ->method('updateOmdbRatings')
            ->with(
                1,
                self::callback(
                    static fn (OmdbRatings $ratings) : bool => $ratings->getImdbRating() === 9.5
                        && $ratings->getImdbVotes() === 2100000
                        && $ratings->getRottenTomatoesRating() === 89, // preserved, not wiped
                ),
            );

        $this->subject->syncMovieRating(1);
    }

    public function testFullOmdbResponseUpdatesAllRatings() : void
    {
        $this->movieApi->method('findById')->willReturn($this->createMovieWithRatings(9.3, 2000000, 89));

        $this->omdbApi->method('fetchMovieRatings')->willReturn(OmdbRatings::create(9.5, 2100000, 95, 82));

        $this->movieRepository->expects(self::once())
            ->method('updateOmdbRatings')
            ->with(
                1,
                self::callback(
                    static fn (OmdbRatings $ratings) : bool => $ratings->getImdbRating() === 9.5
                        && $ratings->getRottenTomatoesRating() === 95,
                ),
            );

        $this->subject->syncMovieRating(1);
    }

    private function createMovieWithRatings(?float $imdbRating, ?int $imdbVotes, ?int $rtRating) : MovieEntity
    {
        return MovieEntity::createFromArray([
            'id' => 1,
            'title' => 'The Shawshank Redemption',
            'trakt_id' => null,
            'imdb_id' => 'tt0111161',
            'tmdb_id' => 278,
            'poster_path' => null,
            'overview' => null,
            'tagline' => null,
            'original_language' => null,
            'runtime' => null,
            'release_date' => null,
            'tmdb_vote_average' => null,
            'tmdb_vote_count' => null,
            'tmdb_poster_path' => null,
            'imdb_rating_average' => $imdbRating,
            'imdb_rating_vote_count' => $imdbVotes,
            'rt_rating_average' => $rtRating,
            'rt_rating_vote_count' => $rtRating === null ? null : 1,
            'updated_at_tmdb' => null,
            'updated_at_imdb' => null,
            'updated_at_omdb' => null,
        ]);
    }
}
