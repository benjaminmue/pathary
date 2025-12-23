<?php declare(strict_types=1);

namespace Movary\HttpController\Api\Middleware;

use Movary\Domain\User\Service\Authentication;
use Movary\Util\Json;
use Movary\ValueObject\Http\Header;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;

class IsAdmin implements MiddlewareInterface
{
    public function __construct(
        private readonly Authentication $authenticationService,
    ) {
    }

    public function __invoke(Request $request) : ?Response
    {
        $currentUser = $this->authenticationService->getCurrentUser();

        if ($currentUser === null || $currentUser->isAdmin() === false) {
            return Response::create(
                StatusCode::createForbidden(),
                Json::encode(['error' => 'Admin access required']),
                [Header::createContentTypeJson()],
            );
        }

        return null;
    }
}
