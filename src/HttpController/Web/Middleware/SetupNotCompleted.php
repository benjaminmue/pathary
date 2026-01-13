<?php declare(strict_types=1);

namespace Movary\HttpController\Web\Middleware;

use Movary\Service\SetupService;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;

class SetupNotCompleted implements MiddlewareInterface
{
    public function __construct(
        private readonly SetupService $setupService,
    ) {
    }

    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function __invoke(Request $request) : ?Response
    {
        // Allow access only if setup wizard can be accessed
        if ($this->setupService->canAccessSetupWizard() === true) {
            return null;
        }

        // If setup is completed or users exist, return 404
        return Response::createNotFound();
    }
}
