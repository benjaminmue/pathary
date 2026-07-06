<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Service\Authentication;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Twig\Environment;

class FaqController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Authentication $authenticationService,
    ) {
    }

    public function show() : Response
    {
        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('public/faq.twig', [
                'loggedIn' => $this->authenticationService->isUserAuthenticatedWithCookie(),
            ]),
        );
    }
}
