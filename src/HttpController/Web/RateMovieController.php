<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

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

        $this->movieRepository->upsertUserRatingWithComment($movieId, $userId, $ratingPopcorn, $comment);

        return Response::createSeeOther('/movie/' . $movieId . '#ratings');
    }
}
