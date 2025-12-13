<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Api\Tmdb\TmdbApi;
use Movary\Domain\Movie\MovieRepository;
use Movary\Service\ImageUrlService;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Twig\Environment;

class SearchController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly MovieRepository $movieRepository,
        private readonly TmdbApi $tmdbApi,
        private readonly ImageUrlService $imageUrlService,
    ) {
    }

    public function search(Request $request) : Response
    {
        $searchTerm = trim((string)($request->getGetParameters()['q'] ?? ''));
        $source = $request->getGetParameters()['source'] ?? '';
        $forceTmdb = $source === 'tmdb';

        if ($searchTerm === '') {
            return Response::create(
                StatusCode::createOk(),
                $this->twig->render('public/search.twig', [
                    'searchTerm' => '',
                    'localResults' => [],
                    'tmdbResults' => [],
                    'showTmdbResults' => false,
                    'forceTmdb' => false,
                ]),
            );
        }

        // Search local movies first (unless forcing TMDB)
        $localResults = [];
        if ($forceTmdb === false) {
            $localResults = $this->movieRepository->searchByTitle($searchTerm);
            $localResults = $this->imageUrlService->replacePosterPathWithImageSrcUrl($localResults);
        }

        $tmdbResults = [];
        $showTmdbResults = false;

        // Search TMDB if no local results OR if explicitly requested
        if (count($localResults) === 0 || $forceTmdb) {
            $tmdbResponse = $this->tmdbApi->searchMovie($searchTerm);
            $tmdbResults = $tmdbResponse['results'] ?? [];
            $showTmdbResults = true;
        }

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('public/search.twig', [
                'searchTerm' => $searchTerm,
                'localResults' => $localResults,
                'tmdbResults' => $tmdbResults,
                'showTmdbResults' => $showTmdbResults,
                'forceTmdb' => $forceTmdb,
            ]),
        );
    }
}
