<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Api\Tmdb\TmdbApi;
use Movary\Api\Tmdb\Exception\TmdbResourceNotFound;
use Movary\Domain\Movie\MovieApi;
use Movary\Domain\User\Service\Authentication;
use Movary\Service\Tmdb\SyncMovie;
use Movary\ValueObject\Date;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Twig\Environment;

class TmdbMovieController
{
    private const string TMDB_IMAGE_BASE_URL = 'https://image.tmdb.org/t/p/w500';

    public function __construct(
        private readonly Environment $twig,
        private readonly TmdbApi $tmdbApi,
        private readonly SyncMovie $syncMovie,
        private readonly MovieApi $movieApi,
        private readonly Authentication $authenticationService,
    ) {
    }

    public function detail(Request $request) : Response
    {
        $tmdbId = (int)$request->getRouteParameters()['tmdbId'];

        try {
            $tmdbMovie = $this->tmdbApi->fetchMovieDetails($tmdbId);
        } catch (TmdbResourceNotFound) {
            return Response::createNotFound();
        }

        $posterUrl = null;
        if ($tmdbMovie->getPosterPath() !== null) {
            $posterUrl = self::TMDB_IMAGE_BASE_URL . $tmdbMovie->getPosterPath();
        }

        // Check if movie already exists locally
        $existingMovie = $this->movieApi->findByTmdbId($tmdbId);

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('public/tmdb_movie_detail.twig', [
                'tmdbId' => $tmdbId,
                'title' => $tmdbMovie->getTitle(),
                'posterUrl' => $posterUrl,
                'overview' => $tmdbMovie->getOverview(),
                'releaseDate' => $tmdbMovie->getReleaseDate(),
                'voteAverage' => $tmdbMovie->getVoteAverage(),
                'voteCount' => $tmdbMovie->getVoteCount(),
                'runtime' => $tmdbMovie->getRuntime(),
                'tagline' => $tmdbMovie->getTagline(),
                'existingMovieId' => $existingMovie?->getId(),
            ]),
        );
    }

    public function add(Request $request) : Response
    {
        $tmdbId = (int)$request->getRouteParameters()['tmdbId'];
        $userId = $this->authenticationService->getCurrentUserId();

        // Sync movie from TMDB (creates if not exists, updates if exists)
        $movie = $this->syncMovie->syncMovie($tmdbId);

        // Create a watched/history entry for the current user with today's date
        $this->movieApi->addPlaysForMovieOnDate(
            movieId: $movie->getId(),
            userId: $userId,
            watchedDate: Date::create(),
            playsToAdd: 1,
        );

        // Redirect to the public movie detail page
        return Response::createSeeOther('/movie/' . $movie->getId());
    }
}
