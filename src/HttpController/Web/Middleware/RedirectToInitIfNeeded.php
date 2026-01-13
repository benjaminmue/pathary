<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

use Movary\Service\SetupService;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;

class RedirectToInitIfNeeded implements MiddlewareInterface
{
    public function __construct(
        private readonly SetupService $setupService,
    ) {
    }

    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function __invoke(Request $request) : ?Response
    {
        // If setup wizard needs to be accessed, redirect to /init
        if ($this->setupService->canAccessSetupWizard() === true) {
            return Response::createSeeOther('/init');
        }

        // Otherwise allow normal processing
        return null;
    }
}
