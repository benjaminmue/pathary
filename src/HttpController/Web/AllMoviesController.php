<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Service\Authentication;
use Movary\Service\GroupMovieService;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Twig\Environment;

class AllMoviesController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly GroupMovieService $groupMovieService,
        private readonly Authentication $authenticationService,
    ) {
    }

    public function index(Request $request) : Response
    {
        $userId = $this->authenticationService->getCurrentUserId();
        $params = $request->getGetParameters();

        // Parse sort parameters
        $sortBy = $params['sort'] ?? 'added';
        $sortOrder = $params['order'] ?? 'desc';

        // Validate sort field
        $validSortFields = ['added', 'title', 'release_date', 'global_rating', 'own_rating'];
        if (in_array($sortBy, $validSortFields, true) === false) {
            $sortBy = 'added';
        }

        // Validate sort order
        if (in_array($sortOrder, ['asc', 'desc'], true) === false) {
            $sortOrder = 'desc';
        }

        // Parse filter parameters
        $ratingMin = isset($params['rating_min']) && $params['rating_min'] !== ''
            ? (int)$params['rating_min']
            : null;
        $ratingMax = isset($params['rating_max']) && $params['rating_max'] !== ''
            ? (int)$params['rating_max']
            : null;
        $genre = isset($params['genre']) && $params['genre'] !== ''
            ? $params['genre']
            : null;
        $yearMin = isset($params['year_min']) && $params['year_min'] !== ''
            ? (int)$params['year_min']
            : null;
        $yearMax = isset($params['year_max']) && $params['year_max'] !== ''
            ? (int)$params['year_max']
            : null;

        // Fetch movies with filters
        $movies = $this->groupMovieService->getAllMovies(
            $userId,
            $sortBy,
            $sortOrder,
            $ratingMin,
            $ratingMax,
            $genre,
            $yearMin,
            $yearMax,
        );

        // Fetch filter options
        $genres = $this->groupMovieService->getAllGenres();
        $yearRange = $this->groupMovieService->getReleaseYearRange();

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('public/all_movies.twig', [
                'movies' => $movies,
                'genres' => $genres,
                'yearRange' => $yearRange,
                'currentSort' => $sortBy,
                'currentOrder' => $sortOrder,
                'currentRatingMin' => $ratingMin,
                'currentRatingMax' => $ratingMax,
                'currentGenre' => $genre,
                'currentYearMin' => $yearMin,
                'currentYearMax' => $yearMax,
                'totalMovies' => count($movies),
            ]),
        );
    }
}
