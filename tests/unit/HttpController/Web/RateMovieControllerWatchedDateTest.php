<?php declare(strict_types=1);

namespace Tests\Unit\Movary\HttpController\Web;

use Movary\HttpController\Web\RateMovieController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(RateMovieController::class)]
class RateMovieControllerWatchedDateTest extends TestCase
{
    /**
     * @return iterable<string, array{?int, ?int, ?int, array{?int, ?int, ?int}}>
     */
    public static function watchedDateProvider() : iterable
    {
        yield 'full valid date' => [2024, 5, 17, [2024, 5, 17]];
        yield 'year only' => [2024, null, null, [2024, null, null]];
        yield 'year and month' => [2024, 5, null, [2024, 5, null]];
        yield 'nothing' => [null, null, null, [null, null, null]];
        yield 'month without year' => [null, 5, 17, [null, null, null]];
        yield 'day without month' => [2024, null, 17, [2024, null, null]];
        yield 'year too early drops all' => [1899, 5, 17, [null, null, null]];
        yield 'year too late drops all' => [2101, 5, 17, [null, null, null]];
        yield 'bounds 1900 and 2100 are valid' => [2100, 12, 31, [2100, 12, 31]];
        yield 'month 0 drops month and day' => [2024, 0, 17, [2024, null, null]];
        yield 'month 13 drops month and day' => [2024, 13, 17, [2024, null, null]];
        yield 'day 0 drops day' => [2024, 5, 0, [2024, 5, null]];
        yield 'day 31 in a 30-day month' => [2024, 4, 31, [2024, 4, null]];
        yield 'leap day in a leap year' => [2024, 2, 29, [2024, 2, 29]];
        yield 'leap day in a common year' => [2023, 2, 29, [2023, 2, null]];
    }

    /**
     * @param array{?int, ?int, ?int} $expected
     */
    #[DataProvider('watchedDateProvider')]
    public function testNormalizesWatchedDate(?int $year, ?int $month, ?int $day, array $expected) : void
    {
        // normalizeWatchedDate() is private and uses no collaborators, so the
        // controller is created without its constructor.
        $class = new ReflectionClass(RateMovieController::class);
        $subject = $class->newInstanceWithoutConstructor();

        self::assertSame($expected, $class->getMethod('normalizeWatchedDate')->invoke($subject, $year, $month, $day));
    }
}
