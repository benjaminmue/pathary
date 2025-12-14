<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\Movie\MovieRepository;
use Movary\Domain\User\Service\Authentication;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\PopcornRating;

class RateMovieController
{
    // Location constants
    public const int LOCATION_CINEMA = 1;
    public const int LOCATION_AT_HOME = 2;
    public const int LOCATION_OTHER = 3;

    public const array LOCATION_LABELS = [
        self::LOCATION_CINEMA => 'Cinema',
        self::LOCATION_AT_HOME => 'At Home',
        self::LOCATION_OTHER => 'Other',
    ];

    public function __construct(
        private readonly MovieRepository $movieRepository,
        private readonly Authentication $authenticationService,
    ) {
    }

    public function rate(Request $request) : Response
    {
        $movieId = (int)$request->getRouteParameters()['id'];
        $userId = $this->authenticationService->getCurrentUserId();

        $postData = $request->getPostParameters();

        $ratingValue = isset($postData['rating_popcorn']) ? (int)$postData['rating_popcorn'] : 0;
        // Rating of 0 means "unrated" - treat as null
        $ratingPopcorn = ($ratingValue >= 1 && $ratingValue <= 7)
            ? PopcornRating::create($ratingValue)
            : null;

        $comment = isset($postData['comment']) && trim($postData['comment']) !== ''
            ? trim($postData['comment'])
            : null;

        // Parse watched date fields
        $watchedYear = $this->parseIntOrNull($postData['watched_year'] ?? null);
        $watchedMonth = $this->parseIntOrNull($postData['watched_month'] ?? null);
        $watchedDay = $this->parseIntOrNull($postData['watched_day'] ?? null);

        // Validate date hierarchy: day requires month, month requires year
        if ($watchedDay !== null && $watchedMonth === null) {
            $watchedDay = null; // Invalid: day without month
        }
        if ($watchedMonth !== null && $watchedYear === null) {
            $watchedMonth = null; // Invalid: month without year
            $watchedDay = null;
        }

        // Validate ranges
        if ($watchedYear !== null && ($watchedYear < 1900 || $watchedYear > 2100)) {
            $watchedYear = null;
            $watchedMonth = null;
            $watchedDay = null;
        }
        if ($watchedMonth !== null && ($watchedMonth < 1 || $watchedMonth > 12)) {
            $watchedMonth = null;
            $watchedDay = null;
        }
        if ($watchedDay !== null) {
            $maxDay = $this->getDaysInMonth($watchedYear, $watchedMonth);
            if ($watchedDay < 1 || $watchedDay > $maxDay) {
                $watchedDay = null;
            }
        }

        // Parse location
        $locationId = $this->parseIntOrNull($postData['location_id'] ?? null);
        if ($locationId !== null && !array_key_exists($locationId, self::LOCATION_LABELS)) {
            $locationId = null; // Invalid location
        }

        $this->movieRepository->upsertUserRatingWithComment(
            $movieId,
            $userId,
            $ratingPopcorn,
            $comment,
            $watchedYear,
            $watchedMonth,
            $watchedDay,
            $locationId,
        );

        return Response::createSeeOther('/movie/' . $movieId . '#ratings');
    }

    public function deleteRating(Request $request) : Response
    {
        $movieId = (int)$request->getRouteParameters()['id'];
        $userId = $this->authenticationService->getCurrentUserId();

        // Delete the user's rating for this movie (gracefully handles non-existent ratings)
        $this->movieRepository->deleteUserRating($movieId, $userId);

        return Response::createSeeOther('/movie/' . $movieId . '#ratings');
    }

    private function parseIntOrNull(mixed $value) : ?int
    {
        if ($value === null || $value === '' || $value === '0') {
            return null;
        }
        $intVal = (int)$value;
        return $intVal > 0 ? $intVal : null;
    }

    private function getDaysInMonth(int $year, int $month) : int
    {
        return (int)date('t', mktime(0, 0, 0, $month, 1, $year));
    }
}
