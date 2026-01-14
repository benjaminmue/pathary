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
        private readonly \Movary\Service\ServerSettings $serverSettings,
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
        $tmdbMin = isset($params['tmdb_min']) && $params['tmdb_min'] !== ''
            ? (float)$params['tmdb_min']
            : null;
        $tmdbMax = isset($params['tmdb_max']) && $params['tmdb_max'] !== ''
            ? (float)$params['tmdb_max']
            : null;
        $imdbMin = isset($params['imdb_min']) && $params['imdb_min'] !== ''
            ? (float)$params['imdb_min']
            : null;
        $imdbMax = isset($params['imdb_max']) && $params['imdb_max'] !== ''
            ? (float)$params['imdb_max']
            : null;
        $rtMin = isset($params['rt_min']) && $params['rt_min'] !== ''
            ? (int)$params['rt_min']
            : null;
        $rtMax = isset($params['rt_max']) && $params['rt_max'] !== ''
            ? (int)$params['rt_max']
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
            $tmdbMin,
            $tmdbMax,
            $imdbMin,
            $imdbMax,
            $rtMin,
            $rtMax,
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
                'currentTmdbMin' => $tmdbMin,
                'currentTmdbMax' => $tmdbMax,
                'currentImdbMin' => $imdbMin,
                'currentImdbMax' => $imdbMax,
                'currentRtMin' => $rtMin,
                'currentRtMax' => $rtMax,
                'omdbConfigured' => $this->serverSettings->isOmdbApiKeyConfigured(),
                'totalMovies' => count($movies),
            ]),
        );
    }
}
