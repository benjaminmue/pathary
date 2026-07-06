<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\Movie\History\Location\MovieHistoryLocationApi;
use Movary\Domain\Movie\MovieRepository;
use Movary\Domain\User\Service\Authentication;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\PopcornRating;

class RateMovieController
{
    public function __construct(
        private readonly MovieRepository $movieRepository,
        private readonly Authentication $authenticationService,
        private readonly MovieHistoryLocationApi $locationApi,
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

        [$watchedYear, $watchedMonth, $watchedDay] = $this->normalizeWatchedDate($watchedYear, $watchedMonth, $watchedDay);

        // Parse location
        $locationId = $this->parseIntOrNull($postData['location_id'] ?? null);

        // Validate location ID against database (system-wide locations only)
        if ($locationId !== null) {
            $location = $this->locationApi->findLocationById($locationId);
            if ($location === null || $location->getUserId() !== null) {
                // Location doesn't exist or is not a system-wide location
                $locationId = null;
            }
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

    /**
     * Validate the watched-date hierarchy and ranges, nulling out invalid parts.
     *
     * @return array{0: ?int, 1: ?int, 2: ?int} year, month, day
     */
    private function normalizeWatchedDate(?int $watchedYear, ?int $watchedMonth, ?int $watchedDay) : array
    {
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
        if ($watchedDay !== null && $watchedYear !== null && $watchedMonth !== null) {
            $maxDay = $this->getDaysInMonth($watchedYear, $watchedMonth);
            if ($watchedDay < 1 || $watchedDay > $maxDay) {
                $watchedDay = null;
            }
        }

        return [$watchedYear, $watchedMonth, $watchedDay];
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
        return (int)(new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
    }
}
