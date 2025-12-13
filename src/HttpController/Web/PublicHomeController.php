<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Service\GroupMovieService;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Twig\Environment;

class PublicHomeController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly GroupMovieService $groupMovieService,
    ) {
    }

    public function index() : Response
    {
        $movies = $this->groupMovieService->getLatestAddedMovies(20);

        $moviesWithStats = [];
        foreach ($movies as $movie) {
            $stats = $this->groupMovieService->getMovieGroupStats((int)$movie['movie_id']);
            $moviesWithStats[] = [
                'movie' => $movie,
                'stats' => $stats,
            ];
        }

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('public/home.twig', [
                'movies' => $moviesWithStats,
            ]),
        );
    }
}
