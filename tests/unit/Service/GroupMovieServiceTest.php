<?php declare(strict_types=1);

namespace Tests\Unit\Movary\Service;

use Doctrine\DBAL\Connection;
use Movary\Service\GroupMovieService;
use Movary\Service\ImageUrlService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GroupMovieService::class)]
class GroupMovieServiceTest extends TestCase
{
    public function testStatsForSeveralMoviesUseTwoQueriesAndCoverEveryId() : void
    {
        $dbConnection = $this->createMock(Connection::class);
        $dbConnection->expects(self::exactly(2))->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            // ratings, grouped by movie (MySQL returns numbers as strings)
            [['movie_id' => '1', 'avg_popcorn' => '5.333', 'rating_count' => '3', 'last_rating_activity' => '2026-03-01 10:00:00']],
            // last watch date, grouped by movie
            [
                ['movie_id' => '1', 'last_watch_activity' => '2026-04-01 00:00:00'],
                ['movie_id' => '2', 'last_watch_activity' => '2026-02-01 00:00:00'],
            ],
        );

        $subject = new GroupMovieService($dbConnection, $this->createStub(ImageUrlService::class));

        self::assertSame(
            [
                1 => ['avg_popcorn' => 5.3, 'rating_count' => 3, 'last_activity_at' => '2026-04-01 00:00:00'],
                2 => ['avg_popcorn' => null, 'rating_count' => 0, 'last_activity_at' => '2026-02-01 00:00:00'],
                3 => ['avg_popcorn' => null, 'rating_count' => 0, 'last_activity_at' => null],
            ],
            $subject->getMovieGroupStatsForMovies([1, 2, 3]),
        );
    }

    public function testNoMoviesRunNoQuery() : void
    {
        $dbConnection = $this->createMock(Connection::class);
        $dbConnection->expects(self::never())->method('fetchAllAssociative');

        $subject = new GroupMovieService($dbConnection, $this->createStub(ImageUrlService::class));

        self::assertSame([], $subject->getMovieGroupStatsForMovies([]));
    }
}
