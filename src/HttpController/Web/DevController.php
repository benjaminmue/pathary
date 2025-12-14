<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Twig\Environment;

class DevController
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function renderPopcornTestPage() : Response
    {
        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('page/dev/popcorn.html.twig'),
        );
    }
}
