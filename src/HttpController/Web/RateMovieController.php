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

        $ratingPopcorn = isset($postData['rating_popcorn']) && $postData['rating_popcorn'] !== ''
            ? PopcornRating::create((int)$postData['rating_popcorn'])
            : null;

        $comment = isset($postData['comment']) && trim($postData['comment']) !== ''
            ? trim($postData['comment'])
            : null;

        $this->movieRepository->upsertUserRatingWithComment($movieId, $userId, $ratingPopcorn, $comment);

        return Response::createSeeOther('/movie/' . $movieId . '#ratings');
    }
}
