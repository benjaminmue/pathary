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
        [
            'ratingMin' => $ratingMin,
            'ratingMax' => $ratingMax,
            'genre' => $genre,
            'yearMin' => $yearMin,
            'yearMax' => $yearMax,
            'tmdbMin' => $tmdbMin,
            'tmdbMax' => $tmdbMax,
            'imdbMin' => $imdbMin,
            'imdbMax' => $imdbMax,
            'rtMin' => $rtMin,
            'rtMax' => $rtMax,
        ] = $this->parseMovieFilters($params);

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

    /**
     * Parse and normalise the movie filter query parameters.
     *
     * @param array<string, mixed> $params
     * @return array{ratingMin: ?int, ratingMax: ?int, genre: ?string, yearMin: ?int, yearMax: ?int, tmdbMin: ?float, tmdbMax: ?float, imdbMin: ?float, imdbMax: ?float, rtMin: ?int, rtMax: ?int}
     */
    private function parseMovieFilters(array $params) : array
    {
        return [
            'ratingMin' => $this->optionalInt($params, 'rating_min'),
            'ratingMax' => $this->optionalInt($params, 'rating_max'),
            'genre' => $this->optionalString($params, 'genre'),
            'yearMin' => $this->optionalInt($params, 'year_min'),
            'yearMax' => $this->optionalInt($params, 'year_max'),
            'tmdbMin' => $this->optionalFloat($params, 'tmdb_min'),
            'tmdbMax' => $this->optionalFloat($params, 'tmdb_max'),
            'imdbMin' => $this->optionalFloat($params, 'imdb_min'),
            'imdbMax' => $this->optionalFloat($params, 'imdb_max'),
            'rtMin' => $this->optionalInt($params, 'rt_min'),
            'rtMax' => $this->optionalInt($params, 'rt_max'),
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function optionalInt(array $params, string $key) : ?int
    {
        return isset($params[$key]) && $params[$key] !== '' ? (int)$params[$key] : null;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function optionalFloat(array $params, string $key) : ?float
    {
        return isset($params[$key]) && $params[$key] !== '' ? (float)$params[$key] : null;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function optionalString(array $params, string $key) : ?string
    {
        return isset($params[$key]) && $params[$key] !== '' ? (string)$params[$key] : null;
    }
}
