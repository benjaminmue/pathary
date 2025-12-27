<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Service\Authentication;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Movary\ValueObject\Url;
use Twig\Environment;

class ErrorController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Authentication $authenticationService,
    ) {
    }

    public function renderInternalServerError(?Request $request = null) : Response
    {
        $isAuthenticated = $this->authenticationService->isUserAuthenticatedWithCookie();

        return Response::create(
            StatusCode::createInternalServerError(),
            $this->twig->render('page/error.html.twig', [
                'statusCode' => 500,
                'isAuthenticated' => $isAuthenticated,
                'currentUrl' => $request?->getPath(),
                'referer' => $this->getReferer($request),
            ]),
        );
    }

    public function renderNotFound(Request $request) : Response
    {
        $isAuthenticated = $this->authenticationService->isUserAuthenticatedWithCookie();

        return Response::create(
            StatusCode::createNotFound(),
            $this->twig->render(
                'page/error.html.twig',
                [
                    'statusCode' => 404,
                    'isAuthenticated' => $isAuthenticated,
                    'referer' => $this->getReferer($request),
                    'currentUrl' => $request->getPath(),
                ],
            ),
        );
    }

    public function renderUnauthorized(Request $request) : Response
    {
        $isAuthenticated = $this->authenticationService->isUserAuthenticatedWithCookie();

        return Response::create(
            StatusCode::createUnauthorized(),
            $this->twig->render(
                'page/error.html.twig',
                [
                    'statusCode' => 401,
                    'isAuthenticated' => $isAuthenticated,
                    'currentUrl' => $request->getPath(),
                    'referer' => $this->getReferer($request),
                ],
            ),
        );
    }

    public function renderForbidden(Request $request) : Response
    {
        $isAuthenticated = $this->authenticationService->isUserAuthenticatedWithCookie();

        return Response::create(
            StatusCode::createForbidden(),
            $this->twig->render(
                'page/error.html.twig',
                [
                    'statusCode' => 403,
                    'isAuthenticated' => $isAuthenticated,
                    'currentUrl' => $request->getPath(),
                    'referer' => $this->getReferer($request),
                ],
            ),
        );
    }

    private function getReferer(?Request $request) : ?string
    {
        if ($request === null) {
            return null;
        }

        $httpReferer = $request->getHttpReferer();
        if ($httpReferer !== null && $httpReferer != '') {
            $url = Url::createFromString($httpReferer);
            return $url->getPath();
        }

        return null;
    }
}
