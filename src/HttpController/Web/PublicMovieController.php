<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\Movie\MovieApi;
use Movary\Domain\Movie\MovieRepository;
use Movary\Domain\User\Service\Authentication;
use Movary\Service\GroupMovieService;
use Movary\Service\ServerSettings;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Twig\Environment;

class PublicMovieController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly MovieApi $movieApi,
        private readonly GroupMovieService $groupMovieService,
        private readonly Authentication $authenticationService,
        private readonly MovieRepository $movieRepository,
        private readonly ServerSettings $serverSettings,
    ) {
    }

    public function detail(Request $request) : Response
    {
        $movieId = (int)$request->getRouteParameters()['id'];

        $movie = $this->movieApi->findByIdFormatted($movieId);

        if ($movie === null) {
            return Response::createNotFound();
        }

        $genres = $this->movieApi->findGenresByMovieId($movieId);
        $stats = $this->groupMovieService->getMovieGroupStats($movieId);
        $individualRatings = $this->groupMovieService->getMovieIndividualRatings($movieId);

        $cast = $this->movieApi->findCastByMovieId($movieId);
        $directors = $this->movieApi->findDirectorsByMovieId($movieId);
        $productionCountries = $this->movieApi->findProductionCountriesByMovieId($movieId);
        $productionCompanies = $this->movieApi->findProductionCompaniesByMovieId($movieId);
        $tmdbFacts = $this->movieApi->findTmdbFactsByMovieId($movieId);

        $displayPopcorn = $stats['avg_popcorn'] !== null
            ? (int)round($stats['avg_popcorn'])
            : 0;

        // Get current user's rating if logged in
        $userRating = null;
        if ($this->authenticationService->isUserAuthenticatedWithCookie() === true) {
            $userId = $this->authenticationService->getCurrentUserId();
            $userRating = $this->movieRepository->findUserRatingWithComment($movieId, $userId);
        }

        // Check if OMDb is configured (enables IMDb and RT ratings)
        $omdbApiKey = $this->serverSettings->getOmdbApiKey();
        $isOmdbEnabled = $omdbApiKey !== null && $omdbApiKey !== '';

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('public/movie_detail.twig', [
                'movie' => $movie,
                'genres' => $genres,
                'stats' => $stats,
                'displayPopcorn' => $displayPopcorn,
                'individualRatings' => $individualRatings,
                'userRating' => $userRating,
                'isOmdbEnabled' => $isOmdbEnabled,
                'cast' => $cast,
                'directors' => $directors,
                'productionCountries' => $productionCountries,
                'productionCompanies' => $productionCompanies,
                'tmdbFacts' => $tmdbFacts,
            ]),
        );
    }
}
