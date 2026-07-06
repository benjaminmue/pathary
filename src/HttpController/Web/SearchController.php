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

        $localResults = [];
        $tmdbResults = [];

        if ($searchTerm !== '') {
            // Unified search: always query the library AND TMDB in one step.
            $localResults = $this->movieRepository->searchByTitle($searchTerm);
            $libraryTmdbIds = array_map('intval', array_column($localResults, 'tmdb_id'));
            $localResults = $this->imageUrlService->replacePosterPathWithImageSrcUrl($localResults);

            $tmdbResponse = $this->tmdbApi->searchMovie($searchTerm);
            $tmdbResults = $tmdbResponse['results'] ?? [];

            // Only surface TMDB results that are not already in the library.
            $tmdbResults = array_filter(
                $tmdbResults,
                static fn(array $result) => in_array((int)($result['id'] ?? 0), $libraryTmdbIds, true) === false,
            );
        }

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('public/search.twig', [
                'searchTerm' => $searchTerm,
                'localResults' => $localResults,
                'tmdbResults' => $tmdbResults,
            ]),
        );
    }
}
